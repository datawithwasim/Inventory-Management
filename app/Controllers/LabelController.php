<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Labels;
use Core\Audit;
use Core\Auth;
use Core\Barcode;
use Core\Controller;
use Core\DB;
use Core\Scope;
use Core\Settings;

/** Barcode labels: pick what to print, design the label (templates), and the printout itself. */
final class LabelController extends Controller
{
    private const ROW_LIMIT = 300;
    private const MAX_LABELS = 2000;

    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function typeOf(): string
    {
        return ($_GET['type'] ?? 'items') === 'rolls' ? 'rolls' : 'items';
    }

    /* ---------- the page: choose what to print ---------- */

    public function index(): void
    {
        $t = $this->tid();
        $type = $this->typeOf();
        $q = trim((string)($_GET['q'] ?? ''));
        $cat = (int)($_GET['category'] ?? 0);
        $stock = !empty($_GET['stock']);
        $grn = (int)($_GET['grn'] ?? 0);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $w = Scope::warehouse();
        $wsql = $w !== null ? ' AND s.warehouse_id = ' . (int)$w : '';
        if ($type === 'rolls') {
            $where = 'b.tenant_id = ?';
            $params = [$t];
            if ($q !== '') { $where .= ' AND (i.name LIKE ? OR b.batch_no LIKE ? OR v.sku LIKE ? OR b.supplier_lot LIKE ?)'; array_push($params, $like, $like, $like, $like); }
            if ($grn) { $where .= ' AND b.id IN (SELECT gi.batch_id FROM grn_items gi WHERE gi.tenant_id = b.tenant_id AND gi.grn_id = ? AND gi.batch_id > 0)'; $params[] = $grn; }
            if ($w !== null) { $where .= ' AND EXISTS (SELECT 1 FROM stock_ledger sl WHERE sl.batch_id = b.id AND sl.warehouse_id = ' . (int)$w . ')'; }
            $rows = DB::all(
                "SELECT b.id, b.batch_no AS code, i.name AS item, v.sku, b.supplier_lot, b.received_date, COALESCE(SUM(s.qty),0) AS bal, u.short_name AS unit,
                        (SELECT GROUP_CONCAT(DISTINCT l.code ORDER BY l.code SEPARATOR ', ') FROM stock_balances s2 JOIN locations l ON l.id = s2.location_id
                          WHERE s2.batch_id = b.id AND s2.tenant_id = b.tenant_id AND s2.qty > 0.0005 AND s2.location_id > 0" . str_replace('s.', 's2.', $wsql) . ") AS rack
                 FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 LEFT JOIN stock_balances s ON s.batch_id = b.id AND s.tenant_id = b.tenant_id$wsql
                 WHERE $where GROUP BY b.id" . ($grn || $q !== '' && !$stock ? '' : ' HAVING bal > 0.0005') . ' ORDER BY b.id DESC LIMIT ' . self::ROW_LIMIT, $params);
        } else {
            $where = 'v.tenant_id = ? AND i.is_bundle = 0 AND v.is_active = 1 AND i.is_active = 1';
            $params = [$t];
            if ($q !== '') {
                $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ? OR EXISTS (SELECT 1 FROM supplier_items sp WHERE sp.variant_id = v.id AND (sp.supplier_code LIKE ? OR sp.design_no LIKE ?)))';
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($cat) { $where .= ' AND i.category_id = ?'; $params[] = $cat; }
            $rows = DB::all(
                "SELECT v.id, COALESCE(NULLIF(v.barcode, ''), v.sku) AS code, i.name AS item, v.sku, v.sale_price, c.name AS category, u.short_name AS unit,
                        COALESCE((SELECT SUM(s.qty) FROM stock_balances s WHERE s.variant_id = v.id AND s.tenant_id = v.tenant_id$wsql), 0) AS stock
                 FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN categories c ON c.id = i.category_id
                 WHERE $where " . ($stock ? 'HAVING stock > 0.0005 ' : '') . 'ORDER BY i.name LIMIT ' . self::ROW_LIMIT, $params);
        }
        $this->view('app/labels/index', ['title' => 'Barcode labels', 'type' => $type, 'q' => $q, 'category' => $cat, 'stock' => $stock, 'grn' => $grn, 'rows' => $rows, 'limit' => self::ROW_LIMIT,
            'templates' => Labels::summaries(), 'default' => (string)Labels::defaultFor($type)['id'],
            'categories' => DB::all('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name', [$t]),
            'can' => ['create' => Auth::can('labels.create'), 'edit' => Auth::can('labels.edit'), 'delete' => Auth::can('labels.delete')]]);
    }

    /** Resolved label values for the chosen rows (the on-screen preview and the designer use these). */
    public function data(): void
    {
        $type = $this->typeOf();
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))), fn($i) => $i > 0))), 0, 50);
        $out = [];
        foreach ($ids as $id) {
            $v = Labels::values($type, $id);
            if ($v !== null) $out[(string)$id] = $v;
        }
        $this->json(['ok' => true, 'values' => (object)$out, 'sample' => Labels::sample($type)]);
    }

    /** One barcode as an SVG picture (the designer draws these live). */
    public function barcode(): void
    {
        $sym = isset(Barcode::SYMBOLS[$_GET['sym'] ?? '']) ? (string)$_GET['sym'] : 'code128';
        $value = mb_substr((string)($_GET['value'] ?? ''), 0, 300);
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        echo Barcode::svg($sym, $value, 40, ($_GET['ecc'] ?? 'M') === 'L' ? 'L' : 'M');
        exit;
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /* ---------- templates ---------- */

    public function templates(): void
    {
        $this->view('app/labels/templates', ['title' => 'Label templates', 'templates' => Labels::summaries(),
            'can' => ['create' => Auth::can('labels.create'), 'edit' => Auth::can('labels.edit'), 'delete' => Auth::can('labels.delete')]]);
    }

    private function designer(string $title, ?array $tpl): void
    {
        $fields = [];
        foreach (Labels::FIELDS as $k => [$label, $group, $for, $sample]) $fields[] = ['key' => $k, 'label' => $label, 'group' => $group, 'for' => $for, 'sample' => $sample];
        $this->view('app/labels/designer', ['title' => $title, 'template' => $tpl, 'fields' => $fields, 'fonts' => array_keys(Labels::FONTS), 'symbols' => Barcode::SYMBOLS,
            'presets' => Labels::PRESETS, 'papers' => array_map(fn($k, $v) => ['key' => $k, 'w' => $v[0], 'h' => $v[1]], array_keys(Labels::PAPERS), Labels::PAPERS), 'maxElements' => Labels::MAX_ELEMENTS,
            'can' => ['edit' => Auth::can('labels.edit'), 'delete' => Auth::can('labels.delete'), 'create' => Auth::can('labels.create')]]);
    }

    /** A new template: blank, or a copy of a ready-made / saved one (?copy=). */
    public function create(): void
    {
        $copy = (string)($_GET['copy'] ?? '');
        $src = $copy !== '' ? Labels::find($copy) : null;
        $tpl = $src ? ['id' => null, 'name' => 'Copy of ' . $src['name'], 'for' => $src['for'], 'w' => $src['w'], 'h' => $src['h'], 'page' => $src['page'], 'elements' => $src['elements'], 'builtin' => false, 'is_default' => false]
            : ['id' => null, 'name' => 'New label', 'for' => 'any', 'w' => 50, 'h' => 25, 'page' => Labels::cleanPage(['mode' => 'roll'], 50, 25), 'elements' => [], 'builtin' => false, 'is_default' => false];
        $this->designer('New label template', $tpl);
    }

    public function edit(string $id): void
    {
        $tpl = Labels::find($id) ?? $this->notFound();
        $this->designer($tpl['builtin'] ? $tpl['name'] : 'Edit label template', $tpl);
    }

    private function saved(string $id): array
    {
        $r = ctype_digit($id) ? DB::one('SELECT * FROM label_templates WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id]) : null;
        return $r ?? $this->notFound();
    }

    private function fail(array $errors, int $status = 422): never
    {
        $this->json(['ok' => false, 'messages' => array_map(fn($m) => ['type' => 'danger', 'msg' => $m], $errors)], $status);
    }

    public function store(): void
    {
        [$tpl, $errors] = Labels::clean($this->input());
        if ($errors) $this->fail($errors);
        $this->guardName($tpl['name'], 0);
        $id = DB::insert('label_templates', ['tenant_id' => $this->tid(), 'name' => $tpl['name'], 'for_type' => $tpl['for'], 'width_mm' => $tpl['w'], 'height_mm' => $tpl['h'],
            'page' => json_encode($tpl['page']), 'elements' => json_encode($tpl['elements']), 'created_by' => Auth::user()['id'] ?? null]);
        Audit::log('label_template_create', 'label_template', $id, $tpl['name']);
        $this->json(['ok' => true, 'id' => $id, 'redirect' => "labels/templates/$id/edit", 'messages' => [['type' => 'success', 'msg' => 'Label template saved.']]]);
    }

    public function update(string $id): void
    {
        $row = $this->saved($id);
        [$tpl, $errors] = Labels::clean($this->input());
        if ($errors) $this->fail($errors);
        $this->guardName($tpl['name'], (int)$row['id']);
        DB::run('UPDATE label_templates SET name = ?, for_type = ?, width_mm = ?, height_mm = ?, page = ?, elements = ? WHERE tenant_id = ? AND id = ?',
            [$tpl['name'], $tpl['for'], $tpl['w'], $tpl['h'], json_encode($tpl['page']), json_encode($tpl['elements']), $this->tid(), $row['id']]);
        Audit::log('label_template_update', 'label_template', (int)$row['id'], $tpl['name']);
        $this->json(['ok' => true, 'id' => (int)$row['id'], 'messages' => [['type' => 'success', 'msg' => 'Label template saved.']]]);
    }

    private function guardName(string $name, int $exceptId): void
    {
        if (DB::val('SELECT 1 FROM label_templates WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId])) $this->fail(['A label template named "' . $name . '" already exists.']);
    }

    public function makeDefault(string $id): void
    {
        $row = $this->saved($id);
        DB::transaction(function () use ($row) {
            if ($row['for_type'] === 'any') DB::run('UPDATE label_templates SET is_default = 0 WHERE tenant_id = ?', [$this->tid()]);
            else DB::run("UPDATE label_templates SET is_default = 0 WHERE tenant_id = ? AND for_type IN (?, 'any')", [$this->tid(), $row['for_type']]);
            DB::run('UPDATE label_templates SET is_default = 1 WHERE tenant_id = ? AND id = ?', [$this->tid(), $row['id']]);
        });
        flash('success', '"' . $row['name'] . '" is now the template opened first.');
        redirect('labels/templates');
    }

    public function destroy(string $id): void
    {
        $row = $this->saved($id);
        DB::run('DELETE FROM label_templates WHERE tenant_id = ? AND id = ?', [$this->tid(), $row['id']]);
        Audit::log('label_template_delete', 'label_template', (int)$row['id'], $row['name']);
        flash('success', 'Label template deleted.');
        redirect('labels/templates');
    }

    /* ---------- the printout ---------- */

    public function print(): void
    {
        $type = $this->typeOf();
        $tpl = Labels::find((string)($_GET['template'] ?? '')) ?? Labels::defaultFor($type);
        $want = [];
        foreach ((array)($_GET['n'] ?? []) as $id => $n) {
            $n = max(0, min(200, (int)$n));
            if ($n > 0 && (int)$id > 0) $want[(int)$id] = $n;
        }
        $labels = [];
        foreach (array_slice($want, 0, 300, true) as $id => $copies) {
            $v = Labels::values($type, $id);
            if ($v === null) continue;
            for ($i = 0; $i < $copies && count($labels) < self::MAX_LABELS; $i++) $labels[] = $v;
        }
        $page = $tpl['page'];
        if (isset($_GET['outline'])) $page['outline'] = $_GET['outline'] === '1';
        $start = max(1, min(500, (int)($_GET['start'] ?? 1)));
        // a sheet can be started part-way through (labels already used up); a roll cannot
        $skip = $page['mode'] === 'sheet' && $labels ? $start - 1 : 0;
        $this->view('app/labels/print', ['title' => 'Labels', 'tpl' => $tpl, 'page' => $page, 'labels' => $labels, 'skip' => $skip, 'company' => Settings::get('company.name', '')], 'layouts/print');
    }
}
