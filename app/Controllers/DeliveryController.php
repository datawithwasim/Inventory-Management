<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Sales;
use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\DB;

/** Delivery: this is where stock really leaves, from a chosen roll and rack. */
final class DeliveryController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT d.*, c.name AS customer, w.name AS warehouse, u.name AS user_name, o.order_no, i.id AS invoice_id, i.invoice_no
             FROM deliveries d JOIN customers c ON c.id = d.customer_id JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN users u ON u.id = d.created_by
             LEFT JOIN sales_orders o ON o.id = d.order_id LEFT JOIN sales_invoices i ON i.delivery_id = d.id AND i.tenant_id = d.tenant_id
             WHERE d.tenant_id = ? AND d.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $customer = (int)($_GET['customer'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'd.tenant_id = ?';
        $params = [$this->tid()];
        if ($customer) { $where .= ' AND d.customer_id = ?'; $params[] = $customer; }
        if ($q !== '') { $where .= ' AND d.delivery_no LIKE ?'; $params[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        [$page, $pages, $limit] = $this->pageOf('deliveries d', $where, $params);
        $rows = DB::all(
            "SELECT d.*, c.name AS customer, w.name AS warehouse, o.order_no, i.id AS invoice_id, i.invoice_no
             FROM deliveries d JOIN customers c ON c.id = d.customer_id JOIN warehouses w ON w.id = d.warehouse_id
             LEFT JOIN sales_orders o ON o.id = d.order_id LEFT JOIN sales_invoices i ON i.delivery_id = d.id AND i.tenant_id = d.tenant_id
             WHERE $where ORDER BY d.id DESC $limit", $params);
        $this->view('app/sales/delivery_index', ['title' => 'Deliveries', 'rows' => $rows, 'customer' => $customer, 'q' => $q, 'customers' => $this->customers(false), 'page' => $page, 'pages' => $pages]);
    }

    private function form(array $extra): void
    {
        $this->view('app/sales/delivery_form', $extra + ['title' => 'Deliver goods', 'customers' => $this->customers(), 'warehouses' => $this->warehouses(), 'order' => null, 'oldLines' => $this->oldLines()]);
    }

    public function create(): void
    {
        $this->form(['title' => 'Deliver goods (no order)']);
    }

    private function openOrder(string $id): array
    {
        $o = DB::one('SELECT o.*, c.name AS customer FROM sales_orders o JOIN customers c ON c.id = o.customer_id WHERE o.tenant_id = ? AND o.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
        if (!in_array($o['status'], ['confirmed', 'partial'], true)) {
            flash('danger', 'Goods can only be delivered on a confirmed order that is not finished.');
            redirect("sales/orders/{$o['id']}");
        }
        return $o;
    }

    /** Suggests where to pick from: the oldest roll that covers the quantity, else rolls in turn; fullest racks for other items. */
    private function suggest(array $l, int $warehouseId): array
    {
        $variant = (int)$l['variant_id'];
        $want = (float)$l['qty'];
        $rows = [];
        $warn = null;
        try {
            $picks = Sales::allocate($variant, $warehouseId, $want);
        } catch (StockException $e) {
            $have = Sales::onHand($variant, $warehouseId);
            $warn = $e->getMessage();
            $picks = $have > 0.0005 ? Sales::allocate($variant, $warehouseId, min($want, $have)) : [];
            if (!$picks) return [$l + ['batch_id' => 0, 'location_id' => 0, 'qty' => 0, 'warn' => $warn]];
        }
        $multi = count(array_unique(array_column($picks, 'batch_id'))) > 1;
        foreach ($picks as $p) {
            $rows[] = array_merge($l, ['batch_id' => $p['batch_id'], 'location_id' => $p['location_id'], 'qty' => $p['qty'], 'warn' => $warn]);
        }
        if ($multi) $rows[0]['multi'] = 1;
        return $rows;
    }

    public function createFromOrder(string $id): void
    {
        $o = $this->openOrder($id);
        $lines = $this->oldLines();
        if (!$lines) {
            foreach (DB::all(
                'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, i.track_batch, i.is_bundle, u.short_name AS unit, u.allow_decimal
                 FROM sales_order_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 WHERE l.tenant_id = ? AND l.order_id = ? ORDER BY l.id', [$this->tid(), $o['id']]) as $l) {
                $left = round((float)$l['qty_ordered'] - (float)$l['qty_delivered'], 3);
                if ($left <= 0) continue;
                $base = ['order_item_id' => (int)$l['id'], 'variant_id' => (int)$l['variant_id'], 'label' => $this->saleLabel($l), 'tb' => (int)$l['track_batch'], 'bundle' => (int)$l['is_bundle'],
                    'unit' => $l['unit'], 'dec' => (int)$l['allow_decimal'], 'qty' => $left, 'left' => $left, 'unit_price' => $l['unit_price'], 'discount_pct' => $l['discount_pct'], 'tax_rate' => $l['tax_rate']];
                if ($l['is_bundle']) { $lines[] = $base + ['batch_id' => 0, 'location_id' => 0]; continue; }
                array_push($lines, ...$this->suggest($base, (int)$o['warehouse_id']));
            }
        }
        $this->form(['title' => 'Deliver · ' . $o['order_no'], 'order' => $o, 'oldLines' => $lines]);
    }

    public function store(): void
    {
        $d = $this->input();
        $orderId = (int)($d['order_id'] ?? 0);
        $back = $orderId ? "sales/orders/$orderId/deliver" : 'sales/deliveries/create';
        $order = $orderId ? $this->openOrder((string)$orderId) : null;
        $customer = $order ? Sales::customer((int)$order['customer_id']) : $this->activeCustomer($d['customer_id'] ?? 0, $back);
        $wh = $order ? Stock::warehouse((int)$order['warehouse_id']) : $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $date = $this->date($d['delivery_date'] ?? '', 'Delivery date', $back);
        $shipTo = $this->text($d['ship_to'] ?? '', 255, 'Delivery address', $back);
        $note = $this->text($d['note'] ?? '', 255, 'Note', $back);

        $orderItems = [];
        if ($order) foreach (DB::all('SELECT * FROM sales_order_items WHERE tenant_id = ? AND order_id = ?', [$this->tid(), $order['id']]) as $oi) $orderItems[$oi['id']] = $oi;
        $lines = [];
        $perItem = [];
        foreach ($this->postedLines($back) as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            $qtyRaw = trim((string)($l['qty'] ?? ''));
            if ($qtyRaw === '' || (is_numeric($qtyRaw) && (float)$qtyRaw == 0.0)) continue;       // a line left empty or at 0 is simply not delivered now
            if ($order) {
                $oi = $orderItems[(int)($l['order_item_id'] ?? 0)] ?? $this->bounce("$prefix: this line is not on the order.", $back);
                $v = Stock::variant((int)$oi['variant_id']);
                $price = (float)$oi['unit_price']; $disc = (float)$oi['discount_pct']; $tax = (float)$oi['tax_rate'];
            } else {
                $oi = null;
                $v = $this->sellable((int)($l['variant_id'] ?? 0)) ?? $this->bounce("$prefix: choose a valid item.", $back);
                $price = $this->money($l['unit_price'] ?? '', "$prefix price", $back); $disc = $this->percent($l['discount_pct'] ?? '', "$prefix discount", $back); $tax = $this->taxFor($l['tax_rate'] ?? '', $prefix, $back);
            }
            $qty = $this->qtyFor($v, $qtyRaw, $prefix, $back);
            $batch = (int)($l['batch_id'] ?? 0);
            if (!$v['is_bundle'] && $v['track_batch'] && $batch <= 0) $this->bounce("$prefix: choose the roll (batch) to cut " . $v['item_name'] . ' from.', $back);
            $lines[] = ['order_item_id' => $oi['id'] ?? null, 'variant_id' => (int)$v['id'], 'qty' => $qty, 'price' => $price, 'disc' => $disc, 'tax' => $tax,
                'batch_id' => $batch, 'location_id' => (int)($l['location_id'] ?? 0), 'auto' => false];
            if ($oi) $perItem[$oi['id']] = ($perItem[$oi['id']] ?? 0) + $qty;
        }
        if (!$lines) $this->bounce('Enter a quantity on at least one line.', $back);
        foreach ($perItem as $oiId => $got) {
            $left = (float)$orderItems[$oiId]['qty_ordered'] - (float)$orderItems[$oiId]['qty_delivered'];
            if ($got > $left + 0.0005) $this->bounce('You are delivering ' . Stock::fmt($got) . ' but only ' . Stock::fmt($left) . ' is still due on that line.', $back);
        }

        try {
            $deliveryId = Sales::postDelivery([
                'customer_id' => $customer['id'], 'customer_name' => $customer['name'], 'warehouse_id' => $wh['id'], 'delivery_date' => $date, 'ship_to' => $shipTo,
                'note' => $note, 'order_id' => $order['id'] ?? null, 'source' => $order ? 'order' : 'direct',
            ], $lines);
            $invoiceId = !empty($d['make_invoice']) ? Sales::createInvoice($deliveryId, $date) : null;
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('delivery_create', 'delivery', $deliveryId);
        flash('success', $invoiceId ? 'Goods delivered and invoice created.' : 'Goods delivered. Stock has been reduced.');
        redirect($invoiceId ? "sales/invoices/$invoiceId" : "sales/deliveries/$deliveryId");
    }

    public function show(string $id): void
    {
        $d = $this->load($id);
        $this->view('app/sales/delivery_show', ['title' => $d['delivery_no'], 'd' => $d, 'items' => self::rows((int)$d['id'], $this->tid())]);
    }

    /** Delivery lines with their rolls / racks, in display order (set parts under their set). */
    public static function rows(int $deliveryId, int $tenantId): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, i.is_bundle, u.short_name AS unit, b.batch_no, k.code AS rack
             FROM delivery_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations k ON k.id = l.location_id AND l.location_id > 0
             WHERE l.tenant_id = ? AND l.delivery_id = ? ORDER BY COALESCE(l.parent_id, l.id), l.parent_id IS NOT NULL, l.id', [$tenantId, $deliveryId]);
    }

    public function invoice(string $id): void
    {
        $d = $this->load($id);
        $invoiceId = Sales::createInvoice((int)$d['id']);
        flash('success', 'Invoice ready.');
        redirect("sales/invoices/$invoiceId");
    }
}
