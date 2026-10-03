<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Reports;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;
use Core\Xlsx;

final class ReportController extends PurchaseBase
{
    private const SCREEN = 500;
    private const EXPORT = 50000;

    public function index(): void
    {
        $groups = [];
        foreach (Reports::all() as $slug => $d) $groups[$d['group']][$slug] = $d;
        $this->view('app/reports/index', ['title' => 'Reports', 'groups' => $groups, 'names' => Reports::GROUPS]);
    }

    private function load(string $slug): array
    {
        return Reports::find($slug) ?? $this->notFound();
    }

    public function show(string $slug): void
    {
        $def = $this->load($slug);
        $f = Reports::filters($def, $_GET);
        [$rows, $trunc] = Reports::run($def, $f, self::SCREEN);
        $data = ['title' => $def['title'], 'def' => $def, 'f' => $f, 'rows' => $rows, 'truncated' => $trunc, 'totals' => Reports::totals($def, $rows),
            'lists' => Reports::lists(), 'screenLimit' => self::SCREEN, 'query' => $_GET];
        if (!empty($_GET['print'])) {
            $this->view('app/reports/print', $data + ['company' => \Core\Settings::get('company.name', '')], 'layouts/print');
            return;
        }
        $this->view('app/reports/show', $data);
    }

    public function export(string $slug, string $format): void
    {
        $def = $this->load($slug);
        $f = Reports::filters($def, $_GET);
        [$rows] = Reports::run($def, $f, self::EXPORT);
        $head = array_column($def['columns'], 'label');
        $out = [$head];
        foreach ($rows as $r) {
            $line = [];
            foreach ($def['columns'] as $k => $c) {
                $v = $r[$k] ?? '';
                $line[] = in_array($c['type'], ['money', 'qty'], true) && $v !== '' && $v !== null ? round((float)$v, 3) : ($c['type'] === 'int' && $v !== null && $v !== '' ? (int)$v : $v);
            }
            $out[] = $line;
        }
        $tot = Reports::totals($def, $rows);
        if ($tot) {
            $line = [];
            foreach ($def['columns'] as $k => $c) $line[] = $k === array_key_first($def['columns']) ? 'Total' : ($tot[$k] ?? '');
            $out[] = $line;
        }
        $file = $slug . '-' . date('Ymd');
        Audit::log('report_export', 'report', null, "$slug.$format " . count($rows) . ' rows');
        if ($format === 'xlsx' && Xlsx::available()) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $file . '.xlsx"');
            echo Xlsx::build($out, $def['title']);
            return;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.csv"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        foreach ($out as $i => $line) {
            if ($i > 0) $line = array_map(fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v, $line);
            fputcsv($h, $line);
        }
        fclose($h);
    }

    /** Low-stock → draft POs grouped by last supplier; items with no supplier history go to one requisition. */
    public function createPos(): void
    {
        $t = $this->tid();
        $back = 'reports/low-stock';
        $pick = array_map('intval', (array)($_POST['pick'] ?? []));
        $rows = array_filter(Reports::lowStock(0), fn($r) => (float)$r['suggest'] > 0 && (!$pick || in_array((int)$r['item_id'], $pick, true)));
        if (!$rows) {
            flash('info', 'Nothing to order: every low item is already covered by open purchase orders.');
            redirect($back);
        }
        $wh = (int)DB::val('SELECT id FROM warehouses WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, id LIMIT 1', [$t]);
        $by = [];
        foreach ($rows as $r) $by[(int)($r['supplier_id'] ?? 0)][] = $r;
        $made = [];
        DB::transaction(function () use ($by, $t, $wh, &$made) {
            foreach ($by as $sid => $list) {
                if ($sid === 0) {
                    $id = DB::insert('purchase_requisitions', ['tenant_id' => $t, 'req_no' => Numbering::next($t, 'REQ'), 'note' => 'Low stock — no supplier history', 'created_by' => Auth::user()['id']]);
                    foreach ($list as $r) DB::insert('purchase_requisition_items', ['tenant_id' => $t, 'requisition_id' => $id, 'variant_id' => $r['variant_id'], 'qty' => $r['suggest'], 'note' => 'Low stock']);
                    $made[] = "requisition (" . count($list) . ' items)';
                    continue;
                }
                $poId = DB::insert('purchase_orders', ['tenant_id' => $t, 'po_no' => Numbering::next($t, 'PO'), 'supplier_id' => $sid, 'warehouse_id' => $wh,
                    'order_date' => date('Y-m-d'), 'status' => 'draft', 'notes' => 'Created from low-stock report', 'created_by' => Auth::user()['id']]);
                foreach ($list as $r) {
                    $tax = (float)DB::val('SELECT COALESCE(x.rate,0) FROM items i LEFT JOIN taxes x ON x.id = i.tax_id WHERE i.id = ?', [$r['item_id']]);
                    DB::insert('purchase_order_items', ['tenant_id' => $t, 'po_id' => $poId, 'variant_id' => $r['variant_id'], 'qty_ordered' => $r['suggest'], 'unit_price' => $r['cost'], 'tax_rate' => $tax]);
                }
                Purchase::recalcPo($poId);
                $made[] = (string)DB::val('SELECT po_no FROM purchase_orders WHERE id = ?', [$poId]);
            }
        });
        Audit::log('low_stock_orders', 'report', null, implode(', ', $made));
        flash('success', 'Created: ' . implode(', ', $made) . '. Check prices and quantities, then submit.');
        redirect('purchase/orders');
    }
}
