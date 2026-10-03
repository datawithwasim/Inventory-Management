<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Sales;
use App\Models\StockException;
use App\Models\CustomFields;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Sales orders. A confirmed order reserves its stock so it cannot be sold to someone else. */
final class SalesOrderController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT o.*, c.name AS customer, c.credit_days, w.name AS warehouse, u.name AS user_name, q.quote_no
             FROM sales_orders o JOIN customers c ON c.id = o.customer_id JOIN warehouses w ON w.id = o.warehouse_id
             LEFT JOIN users u ON u.id = o.created_by LEFT JOIN sales_quotations q ON q.id = o.quotation_id
             WHERE o.tenant_id = ? AND o.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function items(int $id): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, i.track_batch, i.is_bundle, i.id AS item_id, u.short_name AS unit, u.allow_decimal
             FROM sales_order_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE l.tenant_id = ? AND l.order_id = ? ORDER BY l.id', [$this->tid(), $id]);
    }

    public function index(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $customer = (int)($_GET['customer'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'o.tenant_id = ?';
        $params = [$this->tid()];
        if ($status === 'open') $where .= " AND o.status IN ('confirmed','partial')";
        elseif (isset(Sales::ORDER_STATUS[$status])) { $where .= ' AND o.status = ?'; $params[] = $status; }
        if ($customer) { $where .= ' AND o.customer_id = ?'; $params[] = $customer; }
        if ($q !== '') { $where .= ' AND o.order_no LIKE ?'; $params[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        [$page, $pages, $limit] = $this->pageOf('sales_orders o', $where, $params);
        $rows = DB::all("SELECT o.*, c.name AS customer, w.name AS warehouse FROM sales_orders o JOIN customers c ON c.id = o.customer_id JOIN warehouses w ON w.id = o.warehouse_id WHERE $where ORDER BY o.id DESC $limit", $params);
        $this->view('app/sales/order_index', ['title' => 'Sales orders', 'rows' => $rows, 'status' => $status, 'customer' => $customer, 'q' => $q,
            'customers' => $this->customers(false), 'page' => $page, 'pages' => $pages]);
    }

    private function form(string $title, ?array $o, array $lines): void
    {
        $this->view('app/sales/order_form', ['title' => $title, 'o' => $o, 'customers' => $this->customers(), 'warehouses' => $this->warehouses(), 'oldLines' => $lines, 'mode' => 'order',
            'cfFields' => CustomFields::fields('sales_order'), 'cfValues' => CustomFields::formValues('sales_order', $o ? (int)$o['id'] : null)]);
    }

    public function create(): void
    {
        $this->form('New sales order', null, $this->oldLines());
    }

    private function header(string $back): array
    {
        $d = $this->input();
        $c = $this->activeCustomer($d['customer_id'] ?? 0, $back);
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $date = $this->date($d['order_date'] ?? '', 'Order date', $back);
        $expected = $this->date($d['expected_date'] ?? '', 'Delivery date', $back, false);
        if ($expected !== null && $expected < $date) $this->bounce('Delivery date cannot be before the order date.', $back);
        return ['customer_id' => $c['id'], 'warehouse_id' => $wh['id'], 'order_date' => $date, 'expected_date' => $expected,
            'ship_to' => $this->text($d['ship_to'] ?? '', 255, 'Delivery address', $back), 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back),
            'delivery_charge' => $this->money($d['delivery_charge'] ?? '', 'Delivery charge', $back), 'installation_charge' => $this->money($d['installation_charge'] ?? '', 'Installation charge', $back),
            'allow_backorder' => empty($d['allow_backorder']) ? 0 : 1];
    }

    private function saveLines(int $id, array $lines): void
    {
        DB::run('DELETE FROM sales_order_items WHERE tenant_id = ? AND order_id = ?', [$this->tid(), $id]);
        foreach ($lines as $l) {
            DB::insert('sales_order_items', ['tenant_id' => $this->tid(), 'order_id' => $id, 'variant_id' => $l['variant_id'], 'qty_ordered' => $l['qty'],
                'unit_price' => $l['price'], 'discount_pct' => $l['disc'], 'tax_rate' => $l['tax']]);
        }
        Sales::recalc('sales_orders', 'sales_order_items', 'order_id', 'qty_ordered', $id);
    }

    public function store(): void
    {
        $back = 'sales/orders/create';
        $head = $this->header($back);
        $lines = $this->collectSaleLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('sales_order', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        $id = DB::transaction(function () use ($head, $lines, $cf) {
            $t = $this->tid();
            $id = DB::insert('sales_orders', ['tenant_id' => $t, 'order_no' => Numbering::next($t, 'SO'), 'status' => 'draft', 'created_by' => Auth::user()['id']] + $head);
            $this->saveLines($id, $lines);
            CustomFields::save('sales_order', $id, $cf);
            return $id;
        });
        Audit::log('order_create', 'sales_order', $id);
        flash('success', 'Sales order saved as a draft. Confirm it to reserve the stock.');
        redirect("sales/orders/$id");
    }

    public function edit(string $id): void
    {
        $o = $this->load($id);
        if ($o['status'] !== 'draft') {
            flash('warning', 'Only draft orders can be edited. Cancel and recreate it if the order has changed.');
            redirect("sales/orders/{$o['id']}");
        }
        $lines = $this->oldLines() ?: array_map(fn($l) => ['variant_id' => $l['variant_id'], 'label' => $this->saleLabel($l), 'unit' => $l['unit'], 'dec' => (int)$l['allow_decimal'],
            'qty' => (float)$l['qty_ordered'], 'unit_price' => $l['unit_price'], 'discount_pct' => $l['discount_pct'], 'tax_rate' => $l['tax_rate']], $this->items((int)$o['id']));
        $this->form('Edit ' . $o['order_no'], $o, $lines);
    }

    public function update(string $id): void
    {
        $o = $this->load($id);
        $back = "sales/orders/{$o['id']}/edit";
        if ($o['status'] !== 'draft') $this->bounce('Only draft orders can be edited.', "sales/orders/{$o['id']}");
        $head = $this->header($back);
        $lines = $this->collectSaleLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('sales_order', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        DB::transaction(function () use ($o, $head, $lines, $cf) {
            $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($head)));
            DB::run("UPDATE sales_orders SET $set WHERE tenant_id = ? AND id = ?", [...array_values($head), $this->tid(), $o['id']]);
            $this->saveLines((int)$o['id'], $lines);
            CustomFields::save('sales_order', (int)$o['id'], $cf);
        });
        Audit::log('order_update', 'sales_order', (int)$o['id']);
        flash('success', 'Sales order updated.');
        redirect("sales/orders/{$o['id']}");
    }

    public function show(string $id): void
    {
        $o = $this->load($id);
        $t = $this->tid();
        $items = $this->items((int)$o['id']);
        foreach ($items as &$l) {
            $l['free'] = $l['is_bundle'] ? (float)\App\Models\Stock::bundleAvailable((int)$l['item_id']) : Sales::available((int)$l['variant_id'], (int)$o['warehouse_id']);
        }
        unset($l);
        $remaining = array_sum(array_map(fn($l) => max(0, (float)$l['qty_ordered'] - (float)$l['qty_delivered']), $items));
        $this->view('app/sales/order_show', [
            'title' => $o['order_no'], 'o' => $o, 'items' => $items, 'remaining' => $remaining,
            'cfFields' => CustomFields::fields('sales_order'), 'cfValues' => CustomFields::values('sales_order', (int)$o['id']),
            'deliveries' => DB::all('SELECT d.id, d.delivery_no, d.delivery_date, i.id AS invoice_id, i.invoice_no FROM deliveries d LEFT JOIN sales_invoices i ON i.delivery_id = d.id WHERE d.tenant_id = ? AND d.order_id = ? ORDER BY d.id', [$t, $o['id']]),
            'advances' => DB::all('SELECT * FROM customer_payments WHERE tenant_id = ? AND order_id = ? AND invoice_id IS NULL ORDER BY id', [$t, $o['id']]),
        ]);
    }

    public function confirm(string $id): void
    {
        $o = $this->load($id);
        $back = "sales/orders/{$o['id']}";
        if ($o['status'] !== 'draft') $this->bounce('This order is already confirmed or closed.', $back);
        $c = Sales::customer((int)$o['customer_id']);
        if (!$c || !$c['is_active']) $this->bounce('The customer of this order is inactive.', $back);
        $needs = [];
        foreach ($this->items((int)$o['id']) as $l) {
            if (!$this->sellable((int)$l['variant_id'])) $this->bounce($l['item_name'] . ' is no longer available for sale. Edit the order first.', $back);
            $needs[(int)$l['variant_id']] = ($needs[(int)$l['variant_id']] ?? 0) + (float)$l['qty_ordered'];
        }
        if (!$o['allow_backorder']) {
            try {
                Sales::assertAvailable($needs, (int)$o['warehouse_id']);
            } catch (StockException $e) {
                $this->bounce($e->getMessage() . ' Tick "Take the order even if stock is short" on the order to confirm anyway.', $back);
            }
        }
        DB::run("UPDATE sales_orders SET status = 'confirmed', confirmed_at = NOW() WHERE tenant_id = ? AND id = ?", [$this->tid(), $o['id']]);
        Audit::log('order_confirm', 'sales_order', (int)$o['id'], $o['order_no']);
        flash('success', 'Order confirmed. The stock is now reserved for this customer.');
        redirect($back);
    }

    private function transition(string $id, array $from, string $to, string $msg): never
    {
        $o = $this->load($id);
        if (!in_array($o['status'], $from, true)) {
            flash('danger', 'That change is not possible right now.');
            redirect("sales/orders/{$o['id']}");
        }
        DB::run('UPDATE sales_orders SET status = ? WHERE tenant_id = ? AND id = ?', [$to, $this->tid(), $o['id']]);
        Audit::log('order_' . $to, 'sales_order', (int)$o['id'], $o['order_no']);
        flash('success', $msg);
        redirect("sales/orders/{$o['id']}");
    }

    public function cancel(string $id): void
    {
        $o = $this->load($id);
        if (DB::val('SELECT 1 FROM deliveries WHERE tenant_id = ? AND order_id = ? LIMIT 1', [$this->tid(), $o['id']])) {
            flash('danger', 'Goods were already delivered on this order. Close it instead.');
            redirect("sales/orders/{$o['id']}");
        }
        $this->transition($id, ['draft', 'confirmed'], 'cancelled', 'Order cancelled. Its reserved stock is free again.');
    }

    public function close(string $id): void
    {
        $this->transition($id, ['partial'], 'closed', 'Order closed. The undelivered quantity is released.');
    }

    public function destroy(string $id): void
    {
        $o = $this->load($id);
        if ($o['status'] !== 'draft') {
            flash('danger', 'Only draft orders can be deleted. Cancel the order instead.');
            redirect("sales/orders/{$o['id']}");
        }
        DB::transaction(function () use ($o) {
            // a quotation that was converted into this draft goes back to "accepted" so it can be converted again
            DB::run("UPDATE sales_quotations SET order_id = NULL, status = 'accepted' WHERE tenant_id = ? AND order_id = ?", [$this->tid(), $o['id']]);
            DB::run('DELETE FROM sales_orders WHERE tenant_id = ? AND id = ?', [$this->tid(), $o['id']]);
        });
        Audit::log('order_delete', 'sales_order', (int)$o['id'], $o['order_no']);
        flash('success', 'Draft deleted.');
        redirect('sales/orders');
    }

    public function advance(string $id): void
    {
        $o = $this->load($id);
        $back = "sales/orders/{$o['id']}";
        if (in_array($o['status'], ['cancelled', 'closed'], true)) $this->bounce('This order is closed.', $back);
        $d = $this->input();
        $amount = $this->money($d['amount'] ?? '', 'Amount', $back);
        if ($amount <= 0) $this->bounce('Enter the advance amount.', $back);
        $method = (string)($d['method'] ?? 'cash');
        if (!isset(Purchase::METHODS[$method])) $this->bounce('Choose a payment method.', $back);
        DB::insert('customer_payments', ['tenant_id' => $this->tid(), 'customer_id' => $o['customer_id'], 'order_id' => $o['id'], 'amount' => $amount,
            'paid_on' => $this->date($d['paid_on'] ?? '', 'Payment date', $back), 'method' => $method,
            'reference' => $this->text($d['reference'] ?? '', 80, 'Reference', $back), 'created_by' => Auth::user()['id']]);
        Audit::log('order_advance', 'sales_order', (int)$o['id'], number_format($amount, 2));
        flash('success', 'Advance recorded. It will be adjusted on the invoice.');
        redirect($back);
    }

    /** Hands an unused advance back to the customer (marks it as settled). */
    public function refundAdvance(string $id, string $payId): void
    {
        $o = $this->load($id);
        DB::run("UPDATE customer_payments SET applied = amount, note = CONCAT(COALESCE(note,''), ' Refunded to customer') WHERE tenant_id = ? AND id = ? AND order_id = ? AND invoice_id IS NULL",
            [$this->tid(), (int)$payId, $o['id']]);
        Audit::log('order_advance_refund', 'sales_order', (int)$o['id']);
        flash('success', 'Marked as refunded to the customer.');
        redirect("sales/orders/{$o['id']}");
    }
}
