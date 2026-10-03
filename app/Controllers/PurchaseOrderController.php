<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrintTemplate;
use App\Models\Purchase;
use App\Models\CustomFields;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;
use Core\Settings;

final class PurchaseOrderController extends PurchaseBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT p.*, s.name AS supplier, w.name AS warehouse, u1.name AS created_name, u2.name AS approved_name
             FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id JOIN warehouses w ON w.id = p.warehouse_id
             LEFT JOIN users u1 ON u1.id = p.created_by LEFT JOIN users u2 ON u2.id = p.approved_by
             WHERE p.tenant_id = ? AND p.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function items(int $poId): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, i.track_batch, u.short_name AS unit
             FROM purchase_order_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE l.tenant_id = ? AND l.po_id = ? ORDER BY l.id', [$this->tid(), $poId]);
    }

    /* ---------- list ---------- */

    public function index(): void
    {
        $t = $this->tid();
        $status = (string)($_GET['status'] ?? '');
        $supplier = (int)($_GET['supplier'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'p.tenant_id = ?';
        $params = [$t];
        if (isset(Purchase::PO_STATUS[$status])) { $where .= ' AND p.status = ?'; $params[] = $status; }
        if ($status === 'open') { $where = 'p.tenant_id = ? AND p.status IN (\'pending_approval\',\'approved\',\'partial\')'; }
        if ($supplier) { $where .= ' AND p.supplier_id = ?'; $params[] = $supplier; }
        if ($q !== '') { $where .= ' AND p.po_no LIKE ?'; $params[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        [$page, $pages, $limit] = $this->pageOf('purchase_orders p', $where, $params);
        $rows = DB::all(
            "SELECT p.*, s.name AS supplier, w.name AS warehouse FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id
             JOIN warehouses w ON w.id = p.warehouse_id WHERE $where ORDER BY p.id DESC $limit", $params);
        $this->view('app/purchase/po_index', [
            'title' => 'Purchase orders', 'rows' => $rows, 'status' => $status, 'supplier' => $supplier, 'q' => $q,
            'suppliers' => $this->suppliers(false), 'page' => $page, 'pages' => $pages, 'approval' => Settings::bool('po_approval'),
        ]);
    }

    public function setApproval(): void
    {
        Settings::set('po_approval', empty($this->input()['po_approval']) ? '0' : '1');
        Audit::log('setting_po_approval', 'setting', null, Settings::get('po_approval'));
        flash('success', Settings::bool('po_approval') ? 'Purchase orders now need approval.' : 'Purchase orders no longer need approval.');
        redirect('purchase/orders');
    }

    /* ---------- create / edit ---------- */

    private function form(string $title, ?array $po, array $lines): void
    {
        $this->view('app/purchase/po_form', [
            'title' => $title, 'po' => $po, 'suppliers' => $this->suppliers(), 'warehouses' => $this->warehouses(), 'oldLines' => $lines,
            'cfFields' => CustomFields::fields('purchase_order'), 'cfValues' => CustomFields::formValues('purchase_order', $po ? (int)$po['id'] : null),
        ]);
    }

    public function create(): void
    {
        $this->form('New purchase order', null, $this->oldLines());
    }

    /** Validates lines: [{variant_id, qty, price, tax}] */
    public function collectLines(array $raw, string $back): array
    {
        $out = [];
        $seen = [];
        foreach ($raw as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            $v = $this->buyable((int)($l['variant_id'] ?? 0)) ?? $this->bounce("$prefix: choose a valid item.", $back);
            if (isset($seen[$v['id']])) $this->bounce("$prefix: " . $v['item_name'] . ' is listed twice. Combine the quantities.', $back);
            $seen[$v['id']] = 1;
            $price = $this->money($l['unit_price'] ?? '', "$prefix price", $back);
            $out[] = ['variant_id' => (int)$v['id'], 'qty' => $this->qtyFor($v, $l['qty'] ?? '', $prefix, $back),
                'price' => $price, 'tax' => $this->taxFor($l['tax_rate'] ?? '', $prefix, $back)];
        }
        return $out;
    }

    private function header(string $back): array
    {
        $d = $this->input();
        $supplier = $this->activeSupplier($d['supplier_id'] ?? 0, $back);
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $order = $this->date($d['order_date'] ?? '', 'Order date', $back);
        $expected = $this->date($d['expected_date'] ?? '', 'Expected date', $back, false);
        if ($expected !== null && $expected < $order) $this->bounce('Expected date cannot be before the order date.', $back);
        return ['supplier_id' => $supplier['id'], 'warehouse_id' => $wh['id'], 'order_date' => $order, 'expected_date' => $expected,
            'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back)];
    }

    public function store(): void
    {
        $back = 'purchase/orders/create';
        $head = $this->header($back);
        $lines = $this->collectLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('purchase_order', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        $id = DB::transaction(function () use ($head, $lines, $cf) {
            $t = $this->tid();
            $id = DB::insert('purchase_orders', ['tenant_id' => $t, 'po_no' => Numbering::next($t, 'PO'), 'status' => 'draft',
                'created_by' => Auth::user()['id']] + $head);
            $this->saveLines($id, $lines);
            CustomFields::save('purchase_order', $id, $cf);
            return $id;
        });
        Audit::log('po_create', 'purchase_order', $id);
        flash('success', 'Purchase order saved as a draft. Review it and submit when ready.');
        redirect("purchase/orders/$id");
    }

    private function saveLines(int $poId, array $lines): void
    {
        DB::run('DELETE FROM purchase_order_items WHERE tenant_id = ? AND po_id = ?', [$this->tid(), $poId]);
        foreach ($lines as $l) {
            DB::insert('purchase_order_items', ['tenant_id' => $this->tid(), 'po_id' => $poId, 'variant_id' => $l['variant_id'],
                'qty_ordered' => $l['qty'], 'unit_price' => $l['price'], 'tax_rate' => $l['tax']]);
        }
        Purchase::recalcPo($poId);
    }

    public function edit(string $id): void
    {
        $po = $this->load($id);
        if ($po['status'] !== 'draft') {
            flash('warning', 'Only draft purchase orders can be edited.');
            redirect("purchase/orders/{$po['id']}");
        }
        $lines = $this->oldLines() ?: array_map(fn($l) => [
            'variant_id' => $l['variant_id'], 'label' => $l['item_name'] . ($l['vname'] ? ' — ' . $l['vname'] : '') . ' (' . $l['sku'] . ')',
            'unit' => $l['unit'], 'dec' => 1, 'qty' => (float)$l['qty_ordered'], 'unit_price' => $l['unit_price'], 'tax_rate' => $l['tax_rate'],
        ], $this->items((int)$po['id']));
        $this->form('Edit ' . $po['po_no'], $po, $lines);
    }

    public function update(string $id): void
    {
        $po = $this->load($id);
        $back = "purchase/orders/{$po['id']}/edit";
        if ($po['status'] !== 'draft') $this->bounce('Only draft purchase orders can be edited.', "purchase/orders/{$po['id']}");
        $head = $this->header($back);
        $lines = $this->collectLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('purchase_order', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        DB::transaction(function () use ($po, $head, $lines, $cf) {
            $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($head)));
            DB::run("UPDATE purchase_orders SET $set WHERE tenant_id = ? AND id = ?", [...array_values($head), $this->tid(), $po['id']]);
            $this->saveLines((int)$po['id'], $lines);
            CustomFields::save('purchase_order', (int)$po['id'], $cf);
        });
        Audit::log('po_update', 'purchase_order', (int)$po['id']);
        flash('success', 'Purchase order updated.');
        redirect("purchase/orders/{$po['id']}");
    }

    /* ---------- show & workflow ---------- */

    public function show(string $id): void
    {
        $po = $this->load($id);
        $t = $this->tid();
        $items = $this->items((int)$po['id']);
        $remaining = array_sum(array_map(fn($l) => max(0, (float)$l['qty_ordered'] - (float)$l['qty_received']), $items));
        $this->view('app/purchase/po_show', [
            'title' => $po['po_no'], 'po' => $po, 'items' => $items, 'remaining' => $remaining, 'approval' => Settings::bool('po_approval'),
            'cfFields' => CustomFields::fields('purchase_order'), 'cfValues' => CustomFields::values('purchase_order', (int)$po['id']),
            'grns' => DB::all('SELECT id, grn_no, received_date FROM grns WHERE tenant_id = ? AND po_id = ? ORDER BY id', [$t, $po['id']]),
        ]);
    }

    public function print(string $id): void
    {
        $po = $this->load($id);
        $s = DB::one('SELECT address, phone, tax_no FROM suppliers WHERE tenant_id = ? AND id = ?', [$this->tid(), $po['supplier_id']]);
        $po += ['sup_address' => $s['address'] ?? null, 'sup_phone' => $s['phone'] ?? null, 'sup_tax' => $s['tax_no'] ?? null];
        $this->view('app/purchase/po_print', ['title' => $po['po_no'], 'po' => $po, 'items' => $this->items((int)$po['id']), 'tpl' => PrintTemplate::get('purchase_order')], 'layouts/print');
    }

    private function transition(string $id, array $from, string $to, ?string $msg, ?callable $extra = null): array
    {
        $po = $this->load($id);
        if (!in_array($po['status'], $from, true)) {
            flash('danger', 'This purchase order cannot be changed that way right now.');
            redirect("purchase/orders/{$po['id']}");
        }
        DB::run('UPDATE purchase_orders SET status = ? WHERE tenant_id = ? AND id = ?', [$to, $this->tid(), $po['id']]);
        if ($extra) $extra($po);
        Audit::log('po_' . $to, 'purchase_order', (int)$po['id'], $po['po_no']);
        if ($msg) flash('success', $msg);
        redirect("purchase/orders/{$po['id']}");
    }

    public function submit(string $id): void
    {
        $po = $this->load($id);
        if (!DB::val('SELECT 1 FROM purchase_order_items WHERE tenant_id = ? AND po_id = ? LIMIT 1', [$this->tid(), $po['id']])) {
            flash('danger', 'Add at least one item first.');
            redirect("purchase/orders/{$po['id']}/edit");
        }
        if (Settings::bool('po_approval')) {
            $this->transition($id, ['draft'], 'pending_approval', 'Submitted for approval.');
        }
        $this->transition($id, ['draft'], 'approved', 'Purchase order confirmed. You can now receive goods against it.', function ($po) {
            DB::run('UPDATE purchase_orders SET approved_by = ?, approved_at = NOW() WHERE tenant_id = ? AND id = ?', [Auth::user()['id'], $this->tid(), $po['id']]);
        });
    }

    public function approve(string $id): void
    {
        $this->transition($id, ['pending_approval'], 'approved', 'Purchase order approved.', function ($po) {
            DB::run('UPDATE purchase_orders SET approved_by = ?, approved_at = NOW() WHERE tenant_id = ? AND id = ?', [Auth::user()['id'], $this->tid(), $po['id']]);
        });
    }

    public function reject(string $id): void
    {
        $this->transition($id, ['pending_approval'], 'draft', 'Sent back to draft.');
    }

    public function cancel(string $id): void
    {
        $po = $this->load($id);
        if (DB::val('SELECT 1 FROM grns WHERE tenant_id = ? AND po_id = ? LIMIT 1', [$this->tid(), $po['id']])) {
            flash('danger', 'Goods were already received against this order. Close it instead.');
            redirect("purchase/orders/{$po['id']}");
        }
        $this->transition($id, ['draft', 'pending_approval', 'approved'], 'cancelled', 'Purchase order cancelled.');
    }

    public function close(string $id): void
    {
        $this->transition($id, ['partial'], 'closed', 'Purchase order closed. No more goods can be received against it.');
    }

    public function destroy(string $id): void
    {
        $po = $this->load($id);
        if ($po['status'] !== 'draft') {
            flash('danger', 'Only drafts can be deleted. Cancel the order instead.');
            redirect("purchase/orders/{$po['id']}");
        }
        DB::run('DELETE FROM purchase_orders WHERE tenant_id = ? AND id = ?', [$this->tid(), $po['id']]);
        Audit::log('po_delete', 'purchase_order', (int)$po['id'], $po['po_no']);
        flash('success', 'Draft deleted.');
        redirect('purchase/orders');
    }
}
