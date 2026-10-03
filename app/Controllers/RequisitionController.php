<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** "We need to buy these": an internal request that is later turned into a purchase order. */
final class RequisitionController extends PurchaseBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT r.*, u.name AS user_name, p.po_no FROM purchase_requisitions r LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN purchase_orders p ON p.id = r.po_id WHERE r.tenant_id = ? AND r.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function items(int $id): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, v.cost_price, i.name AS item_name, u.short_name AS unit, COALESCE(x.rate,0) AS tax_rate
             FROM purchase_requisition_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id
             JOIN units u ON u.id = i.unit_id LEFT JOIN taxes x ON x.id = i.tax_id WHERE l.tenant_id = ? AND l.requisition_id = ? ORDER BY l.id', [$this->tid(), $id]);
    }

    public function index(): void
    {
        $t = $this->tid();
        [$page, $pages, $limit] = $this->pageOf('purchase_requisitions', 'tenant_id = ?', [$t]);
        $rows = DB::all(
            "SELECT r.*, u.name AS user_name, p.po_no, (SELECT COUNT(*) FROM purchase_requisition_items i WHERE i.requisition_id = r.id) AS line_count
             FROM purchase_requisitions r LEFT JOIN users u ON u.id = r.created_by LEFT JOIN purchase_orders p ON p.id = r.po_id
             WHERE r.tenant_id = ? ORDER BY r.id DESC $limit", [$t]);
        $this->view('app/purchase/req_index', ['title' => 'Purchase requisitions', 'rows' => $rows, 'page' => $page, 'pages' => $pages]);
    }

    /** Items at or below their reorder level, as ready-made requisition lines. */
    private function lowStockLines(): array
    {
        $out = [];
        foreach (DB::all(
            'SELECT i.id, i.name, i.reorder_level, i.reorder_qty, u.short_name AS unit, u.allow_decimal,
                    (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v2 ON v2.id = s.variant_id WHERE v2.item_id = i.id) AS stock,
                    (SELECT v.id FROM item_variants v WHERE v.item_id = i.id AND v.is_active = 1 ORDER BY v.id LIMIT 1) AS variant_id
             FROM items i JOIN units u ON u.id = i.unit_id
             WHERE i.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND i.reorder_level > 0
             HAVING stock <= i.reorder_level AND variant_id IS NOT NULL ORDER BY i.name', [$this->tid()]) as $r) {
            $need = (float)$r['reorder_qty'] > 0 ? (float)$r['reorder_qty'] : max(1.0, (float)$r['reorder_level'] - (float)$r['stock']);
            $v = \App\Models\Stock::variant((int)$r['variant_id']);
            $out[] = ['variant_id' => $r['variant_id'], 'label' => \App\Models\Stock::label($v), 'unit' => $r['unit'], 'dec' => (int)$r['allow_decimal'],
                'qty' => $r['allow_decimal'] ? $need : ceil($need), 'note' => 'Low stock: ' . \App\Models\Stock::fmt($r['stock']) . ' left'];
        }
        return $out;
    }

    public function create(): void
    {
        $lines = $this->oldLines();
        $fromLow = !empty($_GET['low']);
        if (!$lines && $fromLow) {
            $lines = $this->lowStockLines();
            if (!$lines) flash('info', 'Nothing is below its reorder level right now.');
        }
        $this->view('app/purchase/req_form', ['title' => 'New requisition', 'oldLines' => $lines]);
    }

    public function store(): void
    {
        $back = 'purchase/requisitions/create';
        $d = $this->input();
        $note = $this->text($d['note'] ?? '', 255, 'Note', $back);
        $lines = [];
        $seen = [];
        foreach ($this->postedLines($back) as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            $v = $this->buyable((int)($l['variant_id'] ?? 0)) ?? $this->bounce("$prefix: choose a valid item.", $back);
            if (isset($seen[$v['id']])) $this->bounce("$prefix: " . $v['item_name'] . ' is listed twice.', $back);
            $seen[$v['id']] = 1;
            $lines[] = ['variant_id' => (int)$v['id'], 'qty' => $this->qtyFor($v, $l['qty'] ?? '', $prefix, $back), 'note' => $this->text($l['note'] ?? '', 150, "$prefix note", $back)];
        }
        $id = DB::transaction(function () use ($note, $lines) {
            $t = $this->tid();
            $id = DB::insert('purchase_requisitions', ['tenant_id' => $t, 'req_no' => Numbering::next($t, 'REQ'), 'note' => $note, 'created_by' => Auth::user()['id']]);
            foreach ($lines as $l) DB::insert('purchase_requisition_items', ['tenant_id' => $t, 'requisition_id' => $id] + $l);
            return $id;
        });
        Audit::log('requisition_create', 'requisition', $id);
        flash('success', 'Requisition saved.');
        redirect("purchase/requisitions/$id");
    }

    public function show(string $id): void
    {
        $r = $this->load($id);
        $this->view('app/purchase/req_show', ['title' => $r['req_no'], 'r' => $r, 'items' => $this->items((int)$r['id']),
            'suppliers' => $this->suppliers(), 'warehouses' => $this->warehouses()]);
    }

    public function convert(string $id): void
    {
        $r = $this->load($id);
        $back = "purchase/requisitions/{$r['id']}";
        if ($r['status'] !== 'open') $this->bounce('This requisition is already closed.', $back);
        $d = $this->input();
        $supplier = $this->activeSupplier($d['supplier_id'] ?? 0, $back);
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $items = $this->items((int)$r['id']);
        $poId = DB::transaction(function () use ($r, $supplier, $wh, $items) {
            $t = $this->tid();
            $poId = DB::insert('purchase_orders', ['tenant_id' => $t, 'po_no' => Numbering::next($t, 'PO'), 'supplier_id' => $supplier['id'], 'warehouse_id' => $wh['id'],
                'order_date' => date('Y-m-d'), 'status' => 'draft', 'notes' => 'From requisition ' . $r['req_no'], 'created_by' => Auth::user()['id']]);
            foreach ($items as $l) {
                DB::insert('purchase_order_items', ['tenant_id' => $t, 'po_id' => $poId, 'variant_id' => $l['variant_id'], 'qty_ordered' => $l['qty'],
                    'unit_price' => $l['cost_price'], 'tax_rate' => $l['tax_rate']]);
            }
            Purchase::recalcPo($poId);
            DB::run("UPDATE purchase_requisitions SET status = 'converted', po_id = ? WHERE tenant_id = ? AND id = ?", [$poId, $t, $r['id']]);
            return $poId;
        });
        Audit::log('requisition_convert', 'requisition', (int)$r['id'], "PO #$poId");
        flash('success', 'Draft purchase order created. Check the prices, then submit it.');
        redirect("purchase/orders/$poId/edit");
    }

    public function cancel(string $id): void
    {
        $r = $this->load($id);
        if ($r['status'] === 'open') {
            DB::run("UPDATE purchase_requisitions SET status = 'cancelled' WHERE tenant_id = ? AND id = ?", [$this->tid(), $r['id']]);
            flash('success', 'Requisition cancelled.');
        }
        redirect("purchase/requisitions/{$r['id']}");
    }
}
