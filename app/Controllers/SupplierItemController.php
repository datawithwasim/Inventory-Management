<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomFields;
use App\Models\Purchase;
use Core\Audit;
use Core\Columns;
use Core\DB;
use Core\FormFields;

/**
 * Supplier items: the supplier's own catalogue (their product name / code), linked to one of OUR item variants.
 * One of our items can have many supplier items; a supplier item can stay unlinked until it is mapped. Rates and purchase orders use the link.
 */
final class SupplierItemController extends PurchaseBase
{
    private const SEL = 'SELECT p.*, s.name AS supplier, v.sku, v.name AS vname, i.name AS item_name, i.id AS item_id, u.short_name AS unit
        FROM supplier_items p JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN item_variants v ON v.id = p.variant_id LEFT JOIN items i ON i.id = v.item_id LEFT JOIN units u ON u.id = i.unit_id';

    private function load(string $id): array
    {
        return DB::one(self::SEL . ' WHERE p.tenant_id = ? AND p.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public static function ourName(array $r): string
    {
        return $r['variant_id'] ? $r['item_name'] . ($r['vname'] ? ' — ' . $r['vname'] : '') : '';
    }

    // ------------------------------------------------------------------ list

    public function index(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $supplier = (int)($_GET['supplier'] ?? 0);
        $link = in_array($_GET['link'] ?? '', ['linked', 'unlinked'], true) ? $_GET['link'] : '';
        $state = in_array($_GET['state'] ?? '', ['active', 'inactive'], true) ? $_GET['state'] : '';
        $where = 'p.tenant_id = ?'; $args = [$t];
        if ($supplier) { $where .= ' AND p.supplier_id = ?'; $args[] = $supplier; }
        if ($link === 'linked') $where .= ' AND p.variant_id IS NOT NULL'; elseif ($link === 'unlinked') $where .= ' AND p.variant_id IS NULL';
        if ($state) { $where .= ' AND p.is_active = ?'; $args[] = $state === 'active' ? 1 : 0; }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (p.supplier_name LIKE ? OR p.supplier_code LIKE ? OR s.name LIKE ? OR i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like, $like);
        }
        $rows = DB::all(self::SEL . " WHERE $where ORDER BY s.name, p.supplier_name, p.supplier_code LIMIT 500", $args);
        $cols = Columns::visible('supplier_items');
        if (in_array('rate', $cols, true)) {
            foreach ($rows as &$r) { $cur = $r['variant_id'] ? Purchase::currentRate((int)$r['supplier_id'], (int)$r['variant_id']) : null; $r['net'] = $cur ? Purchase::netRate($cur) : null; }
            unset($r);
        }
        $cfList = CustomFields::attachList('supplier_item', $rows, Columns::cfIds($cols));
        $this->view('app/supplier_items/index', ['title' => 'Supplier items', 'rows' => $rows, 'cols' => $cols, 'cfList' => $cfList, 'q' => $q, 'supplier' => $supplier, 'link' => $link, 'state' => $state,
            'suppliers' => DB::all('SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name', [$t]),
            'unlinked' => (int)DB::val('SELECT COUNT(*) FROM supplier_items WHERE tenant_id = ? AND variant_id IS NULL AND is_active = 1', [$t])]);
    }

    // ------------------------------------------------------------------ create / edit

    private function form(?array $row, string $title): void
    {
        $pre = (int)($_GET['supplier'] ?? 0);
        $variant = (int)($_GET['variant'] ?? 0);
        $ourLabel = '';
        $vid = (int)old('variant_id', $row['variant_id'] ?? $variant);
        if ($vid) {
            $o = DB::one('SELECT v.id, v.sku, v.name, i.name AS item_name FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ?', [$this->tid(), $vid]);
            if ($o) $ourLabel = $o['item_name'] . ($o['name'] ? ' — ' . $o['name'] : '') . ' (' . $o['sku'] . ')'; else $vid = 0;
        }
        $this->view('app/supplier_items/form', ['title' => $title, 'row' => $row, 'pre' => $pre, 'vid' => $vid, 'ourLabel' => $ourLabel,
            'suppliers' => DB::all('SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name', [$this->tid()]),
            'cfFields' => CustomFields::fields('supplier_item'), 'cfValues' => CustomFields::formValues('supplier_item', $row ? (int)$row['id'] : null)]);
    }

    public function create(): void
    {
        $this->form(null, 'New supplier item');
    }

    public function edit(string $id): void
    {
        $r = $this->load($id);
        $this->form($r, 'Edit supplier item');
    }

    /** Checks the posted fields; returns the clean row for supplier_items. */
    private function fields(string $back, ?array $current): array
    {
        $t = $this->tid();
        $d = FormFields::strip('supplier_item', $this->input());
        $sid = $current ? (int)$current['supplier_id'] : (int)($d['supplier_id'] ?? 0);
        $sup = Purchase::supplier($sid);
        if (!$sup || (!$current && !$sup['is_active'])) $this->bounce('Choose a valid supplier.', $back);
        $name = $this->text($d['supplier_name'] ?? '', 150, 'Supplier item name', $back);
        $code = $this->text($d['supplier_code'] ?? '', 60, 'Supplier item code', $back);
        if ($name === null && $code === null) $this->bounce('Enter the name or the code the supplier uses for this item.', $back);
        if ($code !== null && DB::val('SELECT 1 FROM supplier_items WHERE tenant_id = ? AND supplier_id = ? AND supplier_code = ? AND id <> ?', [$t, $sid, $code, $current['id'] ?? 0])) $this->bounce("This supplier already has an item with the code \"$code\".", $back);
        $vid = (int)($d['variant_id'] ?? 0);
        if ($vid) {
            if (!DB::val('SELECT 1 FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ? AND i.is_bundle = 0', [$t, $vid])) $this->bounce('Pick our item from the list, or leave it unlinked.', $back);
            if (DB::val('SELECT 1 FROM supplier_items WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ? AND id <> ?', [$t, $sid, $vid, $current['id'] ?? 0])) $this->bounce('This supplier already has a supplier item linked to that item. Edit that one instead.', $back);
        }
        $moq = trim((string)($d['min_order_qty'] ?? ''));
        if ($moq !== '' && (!is_numeric($moq) || (float)$moq < 0 || (float)$moq > 99999999)) $this->bounce('Minimum order qty must be zero or more.', $back);
        $lead = trim((string)($d['lead_time_days'] ?? ''));
        if ($lead !== '' && (!ctype_digit($lead) || (int)$lead > 365)) $this->bounce('Lead time must be 0 to 365 days.', $back);
        $row = ['supplier_id' => $sid, 'supplier_name' => $name, 'supplier_code' => $code, 'variant_id' => $vid ?: null, 'min_order_qty' => $moq === '' ? 0 : (float)$moq,
            'lead_time_days' => $lead === '' ? null : (int)$lead, 'note' => $this->text($d['note'] ?? '', 150, 'Notes', $back)];
        [$row, $errs] = FormFields::apply('supplier_item', $row);
        if ($errs) $this->bounce(implode(' ', $errs), $back);
        return $row;
    }

    public function store(): void
    {
        $data = $this->fields('purchase/supplier-items/create', null);
        [$cf, $cfErr] = CustomFields::validate('supplier_item', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), 'purchase/supplier-items/create');
        $in = $this->input();
        $id = DB::insert('supplier_items', ['tenant_id' => $this->tid(), 'is_active' => 1] + $data + ['supplier_code' => null, 'supplier_name' => null]);
        Purchase::setPreferred($id, (int)($data['variant_id'] ?? 0), !empty($in['is_preferred']));
        CustomFields::save('supplier_item', $id, $cf);
        Audit::log('supplier_item_create', 'supplier_item', $id, (string)($data['supplier_name'] ?? $data['supplier_code']));
        flash('success', 'Supplier item added.' . (empty($data['variant_id']) ? ' It is not linked to one of your items yet.' : ''));
        redirect("purchase/supplier-items/$id");
    }

    public function update(string $id): void
    {
        $r = $this->load($id);
        $back = "purchase/supplier-items/{$r['id']}/edit";
        $data = $this->fields($back, $r);
        [$cf, $cfErr] = CustomFields::validate('supplier_item', $this->input(), (int)$r['id']);
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        unset($data['supplier_id']);
        $in = $this->input();
        $data['is_active'] = !empty($in['is_active']) ? 1 : 0;
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run("UPDATE supplier_items SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), $this->tid(), $r['id']]);
        Purchase::setPreferred((int)$r['id'], (int)($data['variant_id'] ?? 0), !empty($in['is_preferred']) && $data['is_active']);
        CustomFields::save('supplier_item', (int)$r['id'], $cf);
        Audit::log('supplier_item_update', 'supplier_item', (int)$r['id']);
        flash('success', 'Supplier item updated.');
        redirect("purchase/supplier-items/{$r['id']}");
    }

    public function destroy(string $id): void
    {
        $r = $this->load($id);
        DB::run('DELETE FROM supplier_items WHERE tenant_id = ? AND id = ?', [$this->tid(), $r['id']]);
        Audit::log('supplier_item_delete', 'supplier_item', (int)$r['id'], (string)($r['supplier_name'] ?? $r['supplier_code']));
        flash('success', 'Supplier item removed. Its rates stay in the rate history.');
        redirect('purchase/supplier-items');
    }

    // ------------------------------------------------------------------ record page

    public function show(string $id): void
    {
        $r = $this->load($id);
        $t = $this->tid();
        $rates = $r['variant_id'] ? array_values(array_filter(Purchase::ratesOf((int)$r['supplier_id']), fn($x) => (int)$x['variant_id'] === (int)$r['variant_id'])) : [];
        $purchases = $r['variant_id'] ? DB::all(
            'SELECT g.id AS grn_id, g.grn_no, g.received_date, l.qty, l.unit_price FROM grn_items l JOIN grns g ON g.id = l.grn_id
             WHERE l.tenant_id = ? AND g.supplier_id = ? AND l.variant_id = ? ORDER BY g.received_date DESC, g.id DESC LIMIT 10', [$t, $r['supplier_id'], $r['variant_id']]) : [];
        $this->view('app/supplier_items/show', ['title' => $r['supplier_name'] ?: $r['supplier_code'], 'r' => $r, 'rates' => $rates, 'purchases' => $purchases,
            'cur' => $r['variant_id'] ? Purchase::currentRate((int)$r['supplier_id'], (int)$r['variant_id']) : null,
            'last' => $r['variant_id'] ? Purchase::lastPaid((int)$r['supplier_id'], (int)$r['variant_id']) : null,
            'cfFields' => CustomFields::fields('supplier_item'), 'cfValues' => CustomFields::values('supplier_item', (int)$r['id'])]);
    }

    // ------------------------------------------------------------------ CSV

    private const CSV_HEAD = ['supplier', 'supplier_item_name', 'supplier_item_code', 'sku', 'min_order_qty', 'lead_time_days', 'preferred', 'rate', 'discount_pct', 'valid_from'];

    public function export(): void
    {
        $rows = DB::all(self::SEL . ' WHERE p.tenant_id = ? ORDER BY s.name, p.supplier_name, p.supplier_code', [$this->tid()]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="supplier-items.csv"');
        $o = fopen('php://output', 'w');
        fputcsv($o, [...self::CSV_HEAD, 'our_item', 'active'], ',', '"', '\\');
        foreach ($rows as $r) {
            $cur = $r['variant_id'] ? Purchase::currentRate((int)$r['supplier_id'], (int)$r['variant_id']) : null;
            $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;   // spreadsheet formula guard
            fputcsv($o, [$safe($r['supplier']), $safe($r['supplier_name'] ?? ''), $safe($r['supplier_code'] ?? ''), $r['sku'] ?? '', $r['min_order_qty'] + 0, $r['lead_time_days'] ?? '', $r['is_preferred'] ? 'yes' : '',
                $cur['rate'] ?? '', $cur['discount_pct'] ?? '', $cur['valid_from'] ?? '', $safe(self::ourName($r)), $r['is_active'] ? 'yes' : 'no'], ',', '"', '\\');
        }
        fclose($o);
        exit;
    }

    public function template(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="supplier-items-template.csv"');
        echo implode(',', self::CSV_HEAD) . "\nSurat Mills,Royal Velvet 54,MR-54,RVW,100,7,yes,285.00,5," . date('Y-m-d') . "\n";
        exit;
    }

    public function import(): void
    {
        $back = 'purchase/supplier-items';
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) { flash('danger', 'Choose a CSV file to import.'); redirect($back); }
        if ($f['size'] > 2 * 1024 * 1024) { flash('danger', 'The file is larger than 2 MB.'); redirect($back); }
        $h = fopen($f['tmp_name'], 'r');
        $head = fgetcsv($h, 0, ',', '"', '\\');
        $head = array_map(fn($x) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$x))), $head ?: []);
        if (!in_array('supplier', $head, true) || (!in_array('supplier_item_name', $head, true) && !in_array('supplier_item_code', $head, true))) {
            flash('danger', 'The first row must have the columns "supplier" and "supplier_item_name" (or "supplier_item_code").'); redirect($back);
        }
        $t = $this->tid(); $ok = 0; $bad = []; $line = 1;
        DB::transaction(function () use ($h, $head, $t, &$ok, &$bad, &$line) {
            while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                $line++;
                if ($line > 2001) { $bad[] = 'Stopped at 2000 rows.'; break; }
                if (!array_filter($r, fn($x) => trim((string)$x) !== '')) continue;
                $d = [];
                foreach ($head as $i => $k) $d[$k] = trim((string)($r[$i] ?? ''));
                $sid = (int)DB::val('SELECT id FROM suppliers WHERE tenant_id = ? AND name = ?', [$t, $d['supplier'] ?? '']);
                if (!$sid) { $bad[] = "Row $line: supplier \"" . ($d['supplier'] ?? '') . '" not found.'; continue; }
                $name = ($d['supplier_item_name'] ?? '') ?: null; $code = ($d['supplier_item_code'] ?? '') ?: null;
                if ($name === null && $code === null) { $bad[] = "Row $line: needs a name or a code."; continue; }
                if (mb_strlen((string)$name) > 150 || mb_strlen((string)$code) > 60) { $bad[] = "Row $line: name or code too long."; continue; }
                $vid = null;
                if (($d['sku'] ?? '') !== '') {
                    $vid = (int)DB::val('SELECT v.id FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.sku = ? AND i.is_bundle = 0', [$t, $d['sku']]);
                    if (!$vid) { $bad[] = "Row $line: SKU \"{$d['sku']}\" not found."; continue; }
                }
                $moq = ($d['min_order_qty'] ?? '') === '' ? 0 : (float)$d['min_order_qty'];
                $lead = ($d['lead_time_days'] ?? '') === '' ? null : (int)$d['lead_time_days'];
                if ($moq < 0 || ($lead !== null && ($lead < 0 || $lead > 365))) { $bad[] = "Row $line: minimum qty or lead time is out of range."; continue; }
                $have = $vid ? DB::one('SELECT id FROM supplier_items WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ?', [$t, $sid, $vid])
                    : ($code ? DB::one('SELECT id FROM supplier_items WHERE tenant_id = ? AND supplier_id = ? AND supplier_code = ?', [$t, $sid, $code]) : null);
                if ($have) {
                    DB::run('UPDATE supplier_items SET supplier_name = COALESCE(?, supplier_name), supplier_code = COALESCE(?, supplier_code), variant_id = COALESCE(?, variant_id), min_order_qty = ?, lead_time_days = ? WHERE tenant_id = ? AND id = ?',
                        [$name, $code, $vid, $moq, $lead, $t, $have['id']]);
                    $id = (int)$have['id'];
                } else {
                    $id = DB::insert('supplier_items', ['tenant_id' => $t, 'supplier_id' => $sid, 'variant_id' => $vid, 'supplier_name' => $name, 'supplier_code' => $code, 'min_order_qty' => $moq, 'lead_time_days' => $lead, 'is_active' => 1]);
                }
                if ($vid && in_array(strtolower($d['preferred'] ?? ''), ['yes', 'y', '1', 'true'], true)) Purchase::setPreferred($id, $vid, true);
                if ($vid && ($d['rate'] ?? '') !== '') {
                    if (!is_numeric($d['rate']) || (float)$d['rate'] < 0) { $bad[] = "Row $line: rate must be a number."; continue; }
                    $disc = ($d['discount_pct'] ?? '') === '' ? 0.0 : (float)$d['discount_pct'];
                    $from = ($d['valid_from'] ?? '') ?: date('Y-m-d');
                    if ($disc < 0 || $disc > 100 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $bad[] = "Row $line: discount or date is not valid."; continue; }
                    Purchase::addRate($sid, $vid, ['rate' => round((float)$d['rate'], 2), 'discount_pct' => $disc, 'min_qty' => $moq, 'lead_time_days' => $lead, 'supplier_code' => $code, 'valid_from' => $from, 'valid_to' => null, 'note' => null]);
                }
                $ok++;
            }
        });
        fclose($h);
        Audit::log('supplier_item_import', 'supplier_item', 0, "$ok rows");
        flash($bad ? ($ok ? 'warning' : 'danger') : 'success', "$ok supplier item(s) imported" . ($bad ? ', ' . count($bad) . ' skipped. ' . implode(' ', array_slice($bad, 0, 5)) : '.'));
        redirect($back);
    }
}
