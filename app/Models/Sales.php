<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Rules for selling: prices, reservations, picking stock (rolls / racks), deliveries and invoices. */
final class Sales
{
    public static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /** @return array{0: float, 1: float, 2: float, 3: float, 4: float} gross, discount, net, tax, total */
    public static function line(float $qty, float $price, float $discPct, float $taxRate): array
    {
        $gross = round($qty * $price, 2);
        $disc = round($gross * $discPct / 100, 2);
        $net = round($gross - $disc, 2);
        $tax = round($net * $taxRate / 100, 2);
        return [$gross, $disc, $net, $tax, round($net + $tax, 2)];
    }

    public static function customer(int $id): ?array
    {
        return DB::one('SELECT c.*, g.name AS group_name, g.discount_pct AS group_discount FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id WHERE c.tenant_id = ? AND c.id = ?', [self::tid(), $id]);
    }

    public static function walkIn(): int
    {
        return (int)DB::val('SELECT id FROM customers WHERE tenant_id = ? AND is_walkin = 1 ORDER BY id LIMIT 1', [self::tid()]);
    }

    /** Selling price and default discount for this customer (group price list, then group discount, then list price). */
    public static function priceFor(int $variantId, int $customerId): array
    {
        $v = DB::one('SELECT sale_price FROM item_variants WHERE tenant_id = ? AND id = ?', [self::tid(), $variantId]);
        if (!$v) return [0.0, 0.0];
        $c = self::customer($customerId);
        if ($c && $c['group_id']) {
            $gp = DB::val('SELECT price FROM group_prices WHERE tenant_id = ? AND group_id = ? AND variant_id = ?', [self::tid(), $c['group_id'], $variantId]);
            if ($gp !== null) return [(float)$gp, 0.0];
            return [(float)$v['sale_price'], (float)$c['group_discount']];
        }
        return [(float)$v['sale_price'], 0.0];
    }

    /* ---------- stock availability ---------- */

    public static function onHand(int $variantId, int $warehouseId): float
    {
        return (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ?', [self::tid(), $variantId, $warehouseId]);
    }

    /** Stock promised to confirmed orders that are not delivered yet (including the parts of sets). */
    public static function reserved(int $variantId, int $warehouseId, ?int $excludeOrderId = null): float
    {
        $open = "o.tenant_id = ? AND o.warehouse_id = ? AND o.status IN ('confirmed','partial') AND i.qty_ordered > i.qty_delivered" . ($excludeOrderId ? ' AND o.id <> ?' : '');
        $params = [$variantId, self::tid(), $warehouseId];
        if ($excludeOrderId) $params[] = $excludeOrderId;
        $direct = (float)DB::val("SELECT COALESCE(SUM(i.qty_ordered - i.qty_delivered),0) FROM sales_order_items i JOIN sales_orders o ON o.id = i.order_id
            WHERE i.variant_id = ? AND $open", $params);
        $parts = (float)DB::val("SELECT COALESCE(SUM((i.qty_ordered - i.qty_delivered) * bc.qty),0) FROM sales_order_items i JOIN sales_orders o ON o.id = i.order_id
            JOIN item_variants bv ON bv.id = i.variant_id JOIN bundle_components bc ON bc.bundle_item_id = bv.item_id
            WHERE bc.component_variant_id = ? AND $open", $params);
        return $direct + $parts;
    }

    public static function available(int $variantId, int $warehouseId, ?int $excludeOrderId = null): float
    {
        return self::onHand($variantId, $warehouseId) - self::reserved($variantId, $warehouseId, $excludeOrderId);
    }

    /** Components of a set (bundle) variant: [[variant_id, qty], ...]. Empty for ordinary items. */
    public static function parts(int $variantId): array
    {
        return DB::all(
            'SELECT bc.component_variant_id AS variant_id, bc.qty FROM item_variants v JOIN bundle_components bc ON bc.bundle_item_id = v.item_id
             WHERE v.tenant_id = ? AND v.id = ?', [self::tid(), $variantId]);
    }

    /** Turns [variant => qty] into the real stock needed, replacing sets with their parts. */
    public static function expandNeeds(array $lines): array
    {
        $needs = [];
        foreach ($lines as $variantId => $qty) {
            $parts = self::parts((int)$variantId);
            if ($parts) {
                foreach ($parts as $p) $needs[(int)$p['variant_id']] = ($needs[(int)$p['variant_id']] ?? 0) + (float)$p['qty'] * $qty;
            } else {
                $needs[(int)$variantId] = ($needs[(int)$variantId] ?? 0) + $qty;
            }
        }
        return $needs;
    }

    /** Throws when selling these quantities would eat into stock promised to other orders. */
    public static function assertAvailable(array $lines, int $warehouseId, ?int $excludeOrderId = null): void
    {
        foreach (self::expandNeeds($lines) as $variantId => $need) {
            $free = self::available((int)$variantId, $warehouseId, $excludeOrderId);
            if ($need > $free + 0.0005) {
                $v = Stock::variant((int)$variantId);
                $reserved = self::reserved((int)$variantId, $warehouseId, $excludeOrderId);
                throw new StockException('Not enough free stock for ' . Stock::label($v) . ': need ' . Stock::fmt($need) . ' ' . $v['unit'] . ', free ' . Stock::fmt(max(0, $free)) . ' ' . $v['unit']
                    . ($reserved > 0.0005 ? ' (' . Stock::fmt($reserved) . ' is reserved for confirmed orders)' : '') . '.');
            }
        }
    }

    /**
     * Picks stock for a sale. Rolls: the oldest single roll that can cover the quantity, otherwise the oldest rolls in turn
     * (flagged "multi" so the screen can warn about shade differences). Other items: the fullest racks first.
     * @return array<int, array{batch_id:int, location_id:int, qty:float, batch_no:?string, rack:?string, multi:bool}>
     */
    public static function allocate(int $variantId, int $warehouseId, float $qty): array
    {
        $t = self::tid();
        $v = Stock::variant($variantId) ?? throw new StockException('Item not found.');
        $short = fn(float $have) => new StockException('Not enough stock for ' . Stock::label($v) . ': only ' . Stock::fmt($have) . ' ' . $v['unit'] . ' in this warehouse.');
        $racksOf = fn(int $batchId) => DB::all(
            'SELECT s.location_id, l.code, SUM(s.qty) AS qty FROM stock_balances s LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
             WHERE s.tenant_id = ? AND s.variant_id = ? AND s.warehouse_id = ? AND s.batch_id = ? GROUP BY s.location_id, l.code HAVING qty > 0.0005 ORDER BY qty DESC',
            [$t, $variantId, $warehouseId, $batchId]);
        $take = function (array $racks, float $want, int $batchId, ?string $batchNo, bool $multi) {
            $out = [];
            foreach ($racks as $r) {
                if ($want <= 0.0005) break;
                $q = min($want, (float)$r['qty']);
                $out[] = ['batch_id' => $batchId, 'location_id' => (int)$r['location_id'], 'qty' => Stock::round($q), 'batch_no' => $batchNo, 'rack' => $r['code'] ?: null, 'multi' => $multi];
                $want -= $q;
            }
            return $out;
        };

        if (!$v['track_batch']) {
            $racks = $racksOf(0);
            $have = array_sum(array_column($racks, 'qty'));
            if ($have + 0.0005 < $qty) throw $short($have);
            return $take($racks, $qty, 0, null, false);
        }

        $batches = DB::all(
            'SELECT b.id, b.batch_no, SUM(s.qty) AS bal FROM batches b JOIN stock_balances s ON s.batch_id = b.id AND s.tenant_id = b.tenant_id AND s.warehouse_id = ?
             WHERE b.tenant_id = ? AND b.variant_id = ? GROUP BY b.id, b.batch_no, b.received_date HAVING bal > 0.0005 ORDER BY b.received_date, b.id',
            [$warehouseId, $t, $variantId]);
        $have = array_sum(array_column($batches, 'bal'));
        if ($have + 0.0005 < $qty) throw $short($have);
        foreach ($batches as $b) {
            if ((float)$b['bal'] + 0.0005 >= $qty) return $take($racksOf((int)$b['id']), $qty, (int)$b['id'], $b['batch_no'], false);
        }
        $out = [];
        $want = $qty;
        foreach ($batches as $b) {
            if ($want <= 0.0005) break;
            $q = min($want, (float)$b['bal']);
            array_push($out, ...$take($racksOf((int)$b['id']), $q, (int)$b['id'], $b['batch_no'], true));
            $want -= $q;
        }
        return $out;
    }

    /** Cost per unit to remember on a sale line (batch cost for rolls, else the item's cost). */
    public static function unitCost(int $variantId, int $batchId): float
    {
        if ($batchId > 0) {
            $c = (float)DB::val('SELECT unit_cost FROM batches WHERE tenant_id = ? AND id = ?', [self::tid(), $batchId]);
            if ($c > 0) return $c;
        }
        return (float)DB::val('SELECT cost_price FROM item_variants WHERE tenant_id = ? AND id = ?', [self::tid(), $variantId]);
    }

    /* ---------- documents ---------- */

    /** Recomputes totals of a quotation or order from its lines and charges. */
    public static function recalc(string $table, string $itemsTable, string $fk, string $qtyCol, int $id): void
    {
        $gross = $disc = $net = $tax = 0.0;
        foreach (DB::all("SELECT $qtyCol AS q, unit_price, discount_pct, tax_rate FROM $itemsTable WHERE tenant_id = ? AND $fk = ?", [self::tid(), $id]) as $l) {
            [$g, $d, $n, $x] = self::line((float)$l['q'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']);
            $gross += $g; $disc += $d; $net += $n; $tax += $x;
        }
        $h = DB::one("SELECT delivery_charge, installation_charge FROM $table WHERE tenant_id = ? AND id = ?", [self::tid(), $id]);
        DB::run("UPDATE $table SET subtotal = ?, discount_total = ?, tax_total = ?, total = ? WHERE tenant_id = ? AND id = ?",
            [round($gross, 2), round($disc, 2), round($tax, 2), round($net + $tax + (float)$h['delivery_charge'] + (float)$h['installation_charge'], 2), self::tid(), $id]);
    }

    public static function refreshOrderStatus(int $orderId): void
    {
        $t = self::tid();
        $o = DB::one('SELECT status FROM sales_orders WHERE tenant_id = ? AND id = ?', [$t, $orderId]);
        if (!$o || !in_array($o['status'], ['confirmed', 'partial', 'delivered'], true)) return;
        $row = DB::one('SELECT SUM(qty_delivered) AS got, SUM(qty_delivered >= qty_ordered - 0.0005) AS done, COUNT(*) AS n FROM sales_order_items WHERE tenant_id = ? AND order_id = ?', [$t, $orderId]);
        $status = (int)$row['done'] === (int)$row['n'] ? 'delivered' : ((float)$row['got'] > 0.0005 ? 'partial' : 'confirmed');
        DB::run('UPDATE sales_orders SET status = ? WHERE tenant_id = ? AND id = ?', [$status, $t, $orderId]);
    }

    /**
     * Posts a delivery: takes the stock out (rolls and racks), records the lines, updates the order.
     * Lines: variant_id, qty, price, disc, tax, order_item_id?, batch_id?, location_id?, auto (pick stock automatically).
     * Call inside a transaction (it opens one itself when needed).
     */
    public static function postDelivery(array $head, array $lines): int
    {
        return DB::transaction(function () use ($head, $lines) {
            $t = self::tid();
            $wh = (int)$head['warehouse_id'];
            $orderId = $head['order_id'] ?? null;

            $needs = [];
            foreach ($lines as $l) $needs[$l['variant_id']] = ($needs[$l['variant_id']] ?? 0) + $l['qty'];
            self::assertAvailable($needs, $wh, $orderId ? (int)$orderId : null);

            $no = Numbering::next($t, 'DLV');
            $id = DB::insert('deliveries', ['tenant_id' => $t, 'delivery_no' => $no, 'order_id' => $orderId, 'customer_id' => $head['customer_id'], 'warehouse_id' => $wh,
                'delivery_date' => $head['delivery_date'], 'ship_to' => $head['ship_to'] ?? null, 'note' => $head['note'] ?? null,
                'source' => $head['source'] ?? 'direct', 'created_by' => Auth::user()['id'] ?? null]);
            $note = "$no · " . ($head['customer_name'] ?? 'customer');

            $row = fn(array $l, int $variant, int $batch, int $loc, float $qty, float $price, float $disc, float $tax, ?int $parent) => DB::insert('delivery_items', [
                'tenant_id' => $t, 'delivery_id' => $id, 'order_item_id' => $l['order_item_id'] ?? null, 'parent_id' => $parent, 'variant_id' => $variant,
                'batch_id' => $batch, 'location_id' => $loc, 'qty' => $qty, 'unit_price' => $price, 'discount_pct' => $disc, 'tax_rate' => $tax,
                'unit_cost' => $batch >= 0 ? self::unitCost($variant, $batch) : 0]);

            foreach ($lines as $n => $l) {
                $v = Stock::variant((int)$l['variant_id']);
                try {
                    if ($v['is_bundle']) {
                        $parent = $row($l, (int)$v['id'], 0, 0, $l['qty'], $l['price'], $l['disc'], $l['tax'], null);
                        foreach (self::parts((int)$v['id']) as $p) {
                            foreach (self::allocate((int)$p['variant_id'], $wh, (float)$p['qty'] * $l['qty']) as $a) {
                                Stock::move((int)$p['variant_id'], $wh, $a['batch_id'], -$a['qty'], 'sale', 'delivery', $id, self::unitCost((int)$p['variant_id'], $a['batch_id']), $note, $a['location_id']);
                                $row([], (int)$p['variant_id'], $a['batch_id'], $a['location_id'], $a['qty'], 0, 0, 0, $parent);
                            }
                        }
                    } else {
                        $picks = !empty($l['auto']) ? self::allocate((int)$v['id'], $wh, $l['qty'])
                            : [['batch_id' => (int)($l['batch_id'] ?? 0), 'location_id' => (int)($l['location_id'] ?? 0), 'qty' => $l['qty']]];
                        foreach ($picks as $a) {
                            Stock::move((int)$v['id'], $wh, $a['batch_id'], -$a['qty'], 'sale', 'delivery', $id, self::unitCost((int)$v['id'], $a['batch_id']), $note, $a['location_id']);
                            $row($l, (int)$v['id'], $v['track_batch'] ? $a['batch_id'] : 0, $a['location_id'], $a['qty'], $l['price'], $l['disc'], $l['tax'], null);
                        }
                    }
                } catch (StockException $e) {
                    throw new StockException('Line ' . ($n + 1) . ': ' . $e->getMessage());
                }
                if (!empty($l['order_item_id'])) {
                    DB::run('UPDATE sales_order_items SET qty_delivered = qty_delivered + ? WHERE tenant_id = ? AND id = ?', [$l['qty'], $t, $l['order_item_id']]);
                }
            }
            if ($orderId) self::refreshOrderStatus((int)$orderId);
            return $id;
        });
    }

    /** Creates the invoice for a delivery (once). Order charges and advances are applied on the first invoice. */
    public static function createInvoice(int $deliveryId, ?string $invoiceDate = null): int
    {
        return DB::transaction(function () use ($deliveryId, $invoiceDate) {
            $t = self::tid();
            $d = DB::one('SELECT d.*, c.credit_days FROM deliveries d JOIN customers c ON c.id = d.customer_id WHERE d.tenant_id = ? AND d.id = ?', [$t, $deliveryId]) ?? throw new \RuntimeException('Delivery not found');
            if ($existing = DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? AND delivery_id = ?', [$t, $deliveryId])) return (int)$existing;

            $gross = $disc = $tax = 0.0;
            foreach (DB::all('SELECT qty, unit_price, discount_pct, tax_rate FROM delivery_items WHERE tenant_id = ? AND delivery_id = ?', [$t, $deliveryId]) as $l) {
                [$g, $dd, , $x] = self::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']);
                $gross += $g; $disc += $dd; $tax += $x;
            }
            $delivery = $install = 0.0;
            $order = $d['order_id'] ? DB::one('SELECT * FROM sales_orders WHERE tenant_id = ? AND id = ?', [$t, $d['order_id']]) : null;
            if ($order && !$order['charges_billed']) {
                $delivery = (float)$order['delivery_charge'];
                $install = (float)$order['installation_charge'];
                DB::run('UPDATE sales_orders SET charges_billed = 1 WHERE tenant_id = ? AND id = ?', [$t, $order['id']]);
            }
            $date = $invoiceDate ?: date('Y-m-d');
            $total = round($gross - $disc + $tax + $delivery + $install, 2);
            $id = DB::insert('sales_invoices', [
                'tenant_id' => $t, 'invoice_no' => Numbering::next($t, 'INV'), 'customer_id' => $d['customer_id'], 'delivery_id' => $deliveryId, 'order_id' => $d['order_id'],
                'invoice_date' => $date, 'due_date' => $d['credit_days'] ? date('Y-m-d', strtotime("$date +{$d['credit_days']} days")) : null,
                'subtotal' => round($gross, 2), 'discount_total' => round($disc, 2), 'tax_total' => round($tax, 2),
                'delivery_charge' => $delivery, 'installation_charge' => $install, 'total' => $total, 'created_by' => Auth::user()['id'] ?? null,
            ]);
            if ($order) self::applyAdvances($id, (int)$order['id'], (int)$d['customer_id'], $total);
            return $id;
        });
    }

    /** Moves advance money taken on an order onto its invoice. */
    private static function applyAdvances(int $invoiceId, int $orderId, int $customerId, float $total): void
    {
        $t = self::tid();
        $left = $total;
        foreach (DB::all('SELECT * FROM customer_payments WHERE tenant_id = ? AND order_id = ? AND invoice_id IS NULL AND amount - applied > 0.004 ORDER BY id', [$t, $orderId]) as $a) {
            if ($left <= 0.004) break;
            $use = round(min($left, (float)$a['amount'] - (float)$a['applied']), 2);
            DB::run('UPDATE customer_payments SET applied = applied + ? WHERE tenant_id = ? AND id = ?', [$use, $t, $a['id']]);
            DB::insert('customer_payments', ['tenant_id' => $t, 'customer_id' => $customerId, 'invoice_id' => $invoiceId, 'amount' => $use, 'paid_on' => $a['paid_on'],
                'method' => 'advance', 'reference' => 'Advance ' . ($a['reference'] ?: '#' . $a['id']), 'created_by' => Auth::user()['id'] ?? null]);
            $left -= $use;
        }
        self::refreshPaid($invoiceId);
    }

    public static function refreshPaid(int $invoiceId): void
    {
        DB::run('UPDATE sales_invoices SET paid_amount = (SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE invoice_id = ?) WHERE tenant_id = ? AND id = ?',
            [$invoiceId, self::tid(), $invoiceId]);
    }

    public const QUOTE_STATUS = ['draft' => ['Draft', 'secondary'], 'sent' => ['Sent', 'info'], 'accepted' => ['Accepted', 'success'], 'rejected' => ['Rejected', 'danger'], 'converted' => ['Order created', 'primary']];
    public const ORDER_STATUS = ['draft' => ['Draft', 'secondary'], 'confirmed' => ['Confirmed', 'primary'], 'partial' => ['Partly delivered', 'info'], 'delivered' => ['Delivered', 'success'], 'closed' => ['Closed', 'dark'], 'cancelled' => ['Cancelled', 'danger']];
}
