<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomFields;
use App\Models\Stock;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Numbering;

final class ItemController extends Controller
{
    private const PER_PAGE = 25;

    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function load(string $id): array
    {
        return DB::one(
            'SELECT i.*, u.short_name AS unit, u.name AS unit_name, u.allow_decimal FROM items i JOIN units u ON u.id = i.unit_id
             WHERE i.tenant_id = ? AND i.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function lookups(): array
    {
        $t = $this->tid();
        return [
            'categories' => DB::all('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name', [$t]),
            'brands'     => DB::all('SELECT id, name FROM brands WHERE tenant_id = ? ORDER BY name', [$t]),
            'units'      => DB::all('SELECT id, name, short_name FROM units WHERE tenant_id = ? ORDER BY name', [$t]),
            'taxes'      => DB::all('SELECT id, name, rate FROM taxes WHERE tenant_id = ? ORDER BY name', [$t]),
        ];
    }

    private function limitReached(): bool
    {
        $max = (int)Auth::user()['max_items'];
        return $max > 0 && (int)DB::val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$this->tid()]) >= $max;
    }

    private function hasMovements(int $itemId): bool
    {
        return (bool)DB::val(
            'SELECT 1 FROM stock_ledger l JOIN item_variants v ON v.id = l.variant_id WHERE v.tenant_id = ? AND v.item_id = ? LIMIT 1',
            [$this->tid(), $itemId]);
    }

    /* ---------- list ---------- */

    public function index(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $cat = (int)($_GET['category'] ?? 0);
        $low = !empty($_GET['low']);
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = 'i.tenant_id = ?';
        $params = [$t];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (i.name LIKE ? OR EXISTS (SELECT 1 FROM item_variants v WHERE v.item_id = i.id AND (v.sku LIKE ? OR v.name LIKE ? OR v.barcode = ?)))';
            array_push($params, $like, $like, $like, $q);
        }
        if ($cat) {
            $where .= ' AND i.category_id = ?';
            $params[] = $cat;
        }
        $stockSql = '(SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id WHERE v.item_id = i.id)';
        if ($low) $where .= " AND i.is_bundle = 0 AND i.reorder_level > 0 AND $stockSql <= i.reorder_level";

        $total = (int)DB::val("SELECT COUNT(*) FROM items i WHERE $where", $params);
        $rows = DB::all(
            "SELECT i.*, c.name AS category, b.name AS brand, u.short_name AS unit,
                    (SELECT COUNT(*) FROM item_variants v WHERE v.item_id = i.id) AS variant_count,
                    (SELECT MIN(v.sale_price) FROM item_variants v WHERE v.item_id = i.id) AS min_price,
                    $stockSql AS stock
             FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id
             JOIN units u ON u.id = i.unit_id WHERE $where ORDER BY i.name LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        foreach ($rows as &$r) {
            if ($r['is_bundle']) $r['stock'] = Stock::bundleAvailable((int)$r['id']);
        }
        unset($r);
        $cols = \Core\Columns::visible('items');
        $cfList = CustomFields::attachList('item', $rows, \Core\Columns::cfIds($cols));

        $this->view('app/items/index', [
            'title' => term('items'), 'cols' => $cols, 'rows' => $rows, 'q' => $q, 'cat' => $cat, 'low' => $low,
            'categories' => DB::all('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name', [$t]),
            'page' => $page, 'pages' => max(1, (int)ceil($total / self::PER_PAGE)), 'total' => $total,
            'limitReached' => $this->limitReached(), 'cfList' => $cfList,
        ]);
    }

    /* ---------- detail ---------- */

    public function show(string $id): void
    {
        $item = $this->load($id);
        $t = $this->tid();
        $variants = DB::all('SELECT * FROM item_variants WHERE tenant_id = ? AND item_id = ? ORDER BY id', [$t, $item['id']]);
        foreach ($variants as &$v) {
            $v['by_wh'] = DB::all(
                'SELECT w.name, SUM(s.qty) AS qty FROM stock_balances s JOIN warehouses w ON w.id = s.warehouse_id
                 WHERE s.tenant_id = ? AND s.variant_id = ? GROUP BY w.id, w.name HAVING qty <> 0 ORDER BY w.name', [$t, $v['id']]);
            $v['by_rack'] = DB::all(
                'SELECT w.name AS warehouse, COALESCE(l.code, \'\') AS rack, SUM(s.qty) AS qty FROM stock_balances s
                 JOIN warehouses w ON w.id = s.warehouse_id LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
                 WHERE s.tenant_id = ? AND s.variant_id = ? GROUP BY w.id, w.name, s.location_id, l.code HAVING qty > 0.0005
                 ORDER BY w.name, l.code', [$t, $v['id']]);
            $v['total'] = array_sum(array_column($v['by_wh'], 'qty'));
            $v['batches'] = $item['track_batch'] ? Stock::batchesFor((int)$v['id'], null, false) : [];
        }
        unset($v);
        $components = $item['is_bundle'] ? DB::all(
            'SELECT bc.qty, v.sku, v.name AS vname, i.name AS iname, u.short_name AS unit,
                    COALESCE((SELECT SUM(s.qty) FROM stock_balances s WHERE s.tenant_id = bc.tenant_id AND s.variant_id = v.id),0) AS have
             FROM bundle_components bc JOIN item_variants v ON v.id = bc.component_variant_id
             JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE bc.tenant_id = ? AND bc.bundle_item_id = ?', [$t, $item['id']]) : [];
        $history = DB::all(
            'SELECT l.*, w.name AS warehouse, b.batch_no, v.sku, rk.code AS rack FROM stock_ledger l
             JOIN item_variants v ON v.id = l.variant_id JOIN warehouses w ON w.id = l.warehouse_id
             LEFT JOIN locations rk ON rk.id = l.location_id AND l.location_id > 0
             LEFT JOIN batches b ON b.id = l.batch_id
             WHERE l.tenant_id = ? AND v.item_id = ? ORDER BY l.id DESC LIMIT 20', [$t, $item['id']]);
        $meta = DB::one(
            'SELECT c.name AS category, b.name AS brand, x.name AS tax, x.rate FROM items i
             LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN taxes x ON x.id = i.tax_id
             WHERE i.id = ?', [$item['id']]);
        $this->view('app/items/show', [
            'title' => $item['name'], 'item' => $item, 'meta' => $meta, 'variants' => $variants, 'components' => $components,
            'cfFields' => CustomFields::fields('item'), 'cfValues' => CustomFields::values('item', (int)$item['id']),
            'history' => $history, 'bundleAvailable' => $item['is_bundle'] ? Stock::bundleAvailable((int)$item['id']) : null,
        ]);
    }

    /* ---------- create / edit ---------- */

    private function form(string $title, ?array $item, array $variants, array $components): void
    {
        $this->view('app/items/form', $this->lookups() + [
            'title' => $title, 'item' => $item, 'variants' => $variants, 'components' => $components,
            'locked' => $item ? $this->hasMovements((int)$item['id']) : false,
            'cfFields' => CustomFields::fields('item'), 'cfValues' => CustomFields::formValues('item', $item ? (int)$item['id'] : null),
            'pickable' => DB::all(
                'SELECT v.id, v.sku, v.name, i.name AS item_name FROM item_variants v JOIN items i ON i.id = v.item_id
                 WHERE v.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 ORDER BY i.name, v.name', [$this->tid()]),
        ]);
    }

    public function create(): void
    {
        if ($this->limitReached()) {
            flash('warning', "Your plan's item limit is reached. Upgrade your plan to add more items.");
            redirect('items');
        }
        $old = $_SESSION['_old'] ?? null;
        $this->form('New item', null, $old['variants'] ?? [['id' => '', 'name' => '', 'sku' => '', 'barcode' => '', 'cost_price' => '', 'sale_price' => '']], $old['components'] ?? []);
    }

    public function edit(string $id): void
    {
        $item = $this->load($id);
        $old = $_SESSION['_old'] ?? null;
        $variants = $old['variants'] ?? DB::all('SELECT * FROM item_variants WHERE tenant_id = ? AND item_id = ? ORDER BY id', [$this->tid(), $item['id']]);
        $components = $old['components'] ?? DB::all('SELECT component_variant_id AS variant_id, qty FROM bundle_components WHERE tenant_id = ? AND bundle_item_id = ?', [$this->tid(), $item['id']]);
        $this->form('Edit item', $item, $variants, $components);
    }

    /** Validates the posted form; returns [itemFields, variants, components] or bounces back. */
    private function collect(?array $item, string $back): array
    {
        $d = \Core\FormFields::strip('item', $this->input());
        $t = $this->tid();
        $bounce = function (string $msg) use ($d, $back): never {
            flash('danger', $msg);
            with_old($d);
            redirect($back);
        };

        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) $bounce('Item name is required (max 190 characters).');
        $unit = DB::one('SELECT * FROM units WHERE tenant_id = ? AND id = ?', [$t, (int)($d['unit_id'] ?? 0)]);
        if (!$unit) $bounce('Choose a unit.');
        $own = function (string $table, $id) use ($t, $bounce) {
            $id = (int)$id;
            if ($id === 0) return null;
            if (!DB::val("SELECT 1 FROM `$table` WHERE tenant_id = ? AND id = ?", [$t, $id])) $bounce('Invalid selection.');
            return $id;
        };
        $isBundle = $item ? (int)$item['is_bundle'] : (empty($d['is_bundle']) ? 0 : 1);
        $track = $isBundle ? 0 : (empty($d['track_batch']) ? 0 : 1);
        if ($item && $track !== (int)$item['track_batch'] && $this->hasMovements((int)$item['id'])) {
            $bounce('Batch tracking cannot be changed after stock has moved for this item.');
        }
        foreach (['reorder_level', 'reorder_qty'] as $f) {
            if (($d[$f] ?? '') !== '' && (!is_numeric($d[$f]) || (float)$d[$f] < 0)) $bounce('Reorder values must be zero or more.');
        }
        $fields = [
            'name' => $name,
            'category_id' => $own('categories', $d['category_id'] ?? 0),
            'brand_id' => $own('brands', $d['brand_id'] ?? 0),
            'unit_id' => (int)$unit['id'],
            'tax_id' => $own('taxes', $d['tax_id'] ?? 0),
            'description' => trim((string)($d['description'] ?? '')) ?: null,
            'track_batch' => $track, 'is_bundle' => $isBundle,
            'reorder_level' => (float)($d['reorder_level'] ?? 0), 'reorder_qty' => (float)($d['reorder_qty'] ?? 0),
            'is_active' => empty($d['is_active']) && $item ? 0 : 1,
        ];
        [$fields, $ffErr] = \Core\FormFields::apply('item', $fields);
        if ($ffErr) $bounce(implode(' ', $ffErr));

        // Variants
        $rows = array_values(array_filter((array)($d['variants'] ?? []), fn($v) => is_array($v) && implode('', array_map('trim', array_map('strval', $v))) !== ''));
        if (!$rows) $rows = [['id' => '', 'name' => '', 'sku' => '', 'barcode' => '', 'cost_price' => '', 'sale_price' => '']];
        if ($isBundle) $rows = [$rows[0]];
        $seenSku = $seenBar = [];
        $variants = [];
        foreach ($rows as $i => $v) {
            $n = $i + 1;
            $vid = (int)($v['id'] ?? 0);
            if ($vid && !DB::val('SELECT 1 FROM item_variants WHERE tenant_id = ? AND id = ? AND item_id = ?', [$t, $vid, $item['id'] ?? 0])) $bounce('Invalid variant.');
            $sku = trim((string)($v['sku'] ?? ''));
            $bar = trim((string)($v['barcode'] ?? ''));
            foreach (['cost_price', 'sale_price'] as $f) {
                if (($v[$f] ?? '') !== '' && (!is_numeric($v[$f]) || (float)$v[$f] < 0)) $bounce("Variant $n: prices must be zero or more.");
            }
            if (mb_strlen($sku) > 60 || mb_strlen($bar) > 60 || mb_strlen((string)($v['name'] ?? '')) > 150) $bounce("Variant $n: a value is too long.");
            if ($sku !== '') {
                if (isset($seenSku[strtolower($sku)])) $bounce("SKU $sku is used twice in this form.");
                $seenSku[strtolower($sku)] = 1;
                if (DB::val('SELECT 1 FROM item_variants WHERE tenant_id = ? AND sku = ? AND id <> ?', [$t, $sku, $vid])) $bounce("SKU $sku already exists.");
            }
            if ($bar !== '') {
                if (isset($seenBar[$bar])) $bounce("Barcode $bar is used twice in this form.");
                $seenBar[$bar] = 1;
                if (DB::val('SELECT 1 FROM item_variants WHERE tenant_id = ? AND barcode = ? AND id <> ?', [$t, $bar, $vid])) $bounce("Barcode $bar already exists.");
            }
            $variants[] = ['id' => $vid, 'name' => trim((string)($v['name'] ?? '')) ?: null, 'sku' => $sku, 'barcode' => $bar ?: null,
                'cost_price' => (float)($v['cost_price'] ?? 0), 'sale_price' => (float)($v['sale_price'] ?? 0)];
        }

        // Bundle components
        $components = [];
        if ($isBundle) {
            $seen = [];
            foreach ((array)($d['components'] ?? []) as $c) {
                $vid = (int)($c['variant_id'] ?? 0);
                if (!$vid && ($c['qty'] ?? '') === '') continue;
                $qty = (float)($c['qty'] ?? 0);
                if ($qty <= 0) $bounce('Each bundle component needs a quantity above zero.');
                if (isset($seen[$vid])) $bounce('A component is listed twice.');
                $seen[$vid] = 1;
                if (!DB::val('SELECT 1 FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ? AND i.is_bundle = 0', [$t, $vid])) $bounce('Invalid bundle component.');
                $components[] = ['variant_id' => $vid, 'qty' => $qty];
            }
            if (!$components) $bounce('Add at least one component to the bundle.');
        }
        [$cfValues, $cfErrors] = CustomFields::validate('item', $d, $item ? (int)$item['id'] : null);
        if ($cfErrors) $bounce(implode(' ', $cfErrors));
        return [$fields, $variants, $components, $cfValues];
    }

    private function saveChildren(int $itemId, array $variants, array $components, bool $isBundle): void
    {
        $t = $this->tid();
        $keep = [];
        foreach ($variants as $v) {
            $sku = $v['sku'] !== '' ? $v['sku'] : Numbering::next($t, 'SKU');
            $data = ['name' => $v['name'], 'sku' => $sku, 'barcode' => $v['barcode'], 'cost_price' => $v['cost_price'], 'sale_price' => $v['sale_price'], 'is_active' => 1];
            if ($v['id']) {
                $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
                DB::run("UPDATE item_variants SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), $t, $v['id']]);
                $keep[] = $v['id'];
            } else {
                $keep[] = DB::insert('item_variants', ['tenant_id' => $t, 'item_id' => $itemId] + $data);
            }
        }
        // Variants removed from the form: delete when never used, otherwise just hide them.
        foreach (DB::all('SELECT id FROM item_variants WHERE tenant_id = ? AND item_id = ?', [$t, $itemId]) as $r) {
            if (in_array((int)$r['id'], $keep, true)) continue;
            $used = DB::val('SELECT 1 FROM stock_ledger WHERE variant_id = ? LIMIT 1', [$r['id']])
                || DB::val('SELECT 1 FROM bundle_components WHERE component_variant_id = ? LIMIT 1', [$r['id']]);
            $used ? DB::run('UPDATE item_variants SET is_active = 0 WHERE id = ?', [$r['id']])
                  : DB::run('DELETE FROM item_variants WHERE id = ?', [$r['id']]);
        }
        if ($isBundle) {
            DB::run('DELETE FROM bundle_components WHERE tenant_id = ? AND bundle_item_id = ?', [$t, $itemId]);
            foreach ($components as $c) {
                DB::insert('bundle_components', ['tenant_id' => $t, 'bundle_item_id' => $itemId, 'component_variant_id' => $c['variant_id'], 'qty' => $c['qty']]);
            }
        }
    }

    public function store(): void
    {
        if ($this->limitReached()) redirect('items');
        [$fields, $variants, $components, $cf] = $this->collect(null, 'items/create');
        $id = DB::transaction(function () use ($fields, $variants, $components, $cf) {
            $id = DB::insert('items', ['tenant_id' => $this->tid()] + $fields);
            $this->saveChildren($id, $variants, $components, (bool)$fields['is_bundle']);
            CustomFields::save('item', $id, $cf);
            return $id;
        });
        Audit::log('item_create', 'item', $id, $fields['name']);
        flash('success', 'Item saved.');
        redirect("items/$id");
    }

    public function update(string $id): void
    {
        $item = $this->load($id);
        [$fields, $variants, $components, $cf] = $this->collect($item, "items/{$item['id']}/edit");
        unset($fields['is_bundle']);
        DB::transaction(function () use ($item, $fields, $variants, $components, $cf) {
            CustomFields::save('item', (int)$item['id'], $cf);
            $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($fields)));
            DB::run("UPDATE items SET $set WHERE tenant_id = ? AND id = ?", [...array_values($fields), $this->tid(), $item['id']]);
            $this->saveChildren((int)$item['id'], $variants, $components, (bool)$item['is_bundle']);
        });
        Audit::log('item_update', 'item', (int)$item['id']);
        flash('success', 'Item updated.');
        redirect("items/{$item['id']}");
    }

    public function destroy(string $id): void
    {
        $item = $this->load($id);
        $asComponent = DB::val(
            'SELECT 1 FROM bundle_components bc JOIN item_variants v ON v.id = bc.component_variant_id WHERE bc.tenant_id = ? AND v.item_id = ? LIMIT 1',
            [$this->tid(), $item['id']]);
        if ($this->hasMovements((int)$item['id']) || $asComponent) {
            flash('danger', $asComponent ? 'This item is part of a bundle and cannot be deleted.' : 'This item has stock history and cannot be deleted. Mark it inactive instead.');
            redirect("items/{$item['id']}");
        }
        DB::run('DELETE FROM items WHERE tenant_id = ? AND id = ?', [$this->tid(), $item['id']]);
        Audit::log('item_delete', 'item', (int)$item['id'], $item['name']);
        flash('success', 'Item deleted.');
        redirect('items');
    }
}
