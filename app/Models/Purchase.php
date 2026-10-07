<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/** Money maths and status rules for the purchase side. */
final class Purchase
{
    public const SUPPLIER_TYPES = ['manufacturer' => 'Manufacturer / mill', 'trader' => 'Trader / wholesaler', 'jobworker' => 'Job worker (dyeing, stitching…)', 'importer' => 'Importer'];

    public static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /** @return array{0: float, 1: float, 2: float} net, tax, total */
    public static function line(float $qty, float $price, float $taxRate): array
    {
        $net = round($qty * $price, 2);
        $tax = round($net * $taxRate / 100, 2);
        return [$net, $tax, round($net + $tax, 2)];
    }

    public static function outstanding(array $bill): float
    {
        return round((float)$bill['total'] - (float)$bill['returned_amount'] - (float)$bill['paid_amount'], 2);
    }

    /** unpaid / partial / paid, from the bill's amounts. */
    public static function payStatus(array $bill): string
    {
        $due = self::outstanding($bill);
        if ($due <= 0.004) return 'paid';
        return (float)$bill['paid_amount'] > 0.004 ? 'partial' : 'unpaid';
    }

    public static function supplierOutstanding(int $supplierId): float
    {
        return (float)DB::val(
            'SELECT COALESCE(SUM(total - returned_amount - paid_amount), 0) FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ?',
            [self::tid(), $supplierId]);
    }

    public static function supplier(int $id): ?array
    {
        return DB::one('SELECT * FROM suppliers WHERE tenant_id = ? AND id = ?', [self::tid(), $id]);
    }

    /** Recomputes PO header totals from its lines. */
    public static function recalcPo(int $poId): void
    {
        $sub = $tax = 0.0;
        foreach (DB::all('SELECT qty_ordered, unit_price, tax_rate FROM purchase_order_items WHERE tenant_id = ? AND po_id = ?', [self::tid(), $poId]) as $l) {
            [$n, $t] = self::line((float)$l['qty_ordered'], (float)$l['unit_price'], (float)$l['tax_rate']);
            $sub += $n;
            $tax += $t;
        }
        DB::run('UPDATE purchase_orders SET subtotal = ?, tax_total = ?, total = ? WHERE tenant_id = ? AND id = ?',
            [round($sub, 2), round($tax, 2), round($sub + $tax, 2), self::tid(), $poId]);
    }

    public const PO_STATUS = [
        'draft' => ['Draft', 'secondary'], 'pending_approval' => ['Waiting for approval', 'warning'], 'approved' => ['Approved', 'primary'],
        'partial' => ['Partly received', 'info'], 'received' => ['Received', 'success'], 'closed' => ['Closed', 'dark'], 'cancelled' => ['Cancelled', 'danger'],
    ];

    public const PAY_STATUS = ['unpaid' => ['Unpaid', 'danger'], 'partial' => ['Partly paid', 'warning'], 'paid' => ['Paid', 'success']];
    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank transfer', 'upi' => 'UPI', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'];

    // ------------------------------------------------------------ supplier rate list

    /** Rate after the supplier's own discount. */
    public static function netRate(array $r): float
    {
        return round((float)$r['rate'] * (1 - (float)$r['discount_pct'] / 100), 2);
    }

    /** Rate in force on a date (default today) for one supplier + variant, or null. */
    public static function currentRate(int $supplierId, int $variantId, ?string $on = null): ?array
    {
        $on = $on ?: date('Y-m-d');
        return DB::one(
            'SELECT * FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ? AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)
             ORDER BY valid_from DESC, id DESC LIMIT 1', [self::tid(), $supplierId, $variantId, $on, $on]);
    }

    /** What we actually paid this supplier last time for a variant (latest goods receipt line). */
    public static function lastPaid(int $supplierId, int $variantId): ?array
    {
        return DB::one(
            'SELECT l.unit_price, g.received_date FROM grn_items l JOIN grns g ON g.id = l.grn_id
             WHERE l.tenant_id = ? AND g.supplier_id = ? AND l.variant_id = ? ORDER BY g.received_date DESC, g.id DESC LIMIT 1', [self::tid(), $supplierId, $variantId]);
    }

    // ------------------------------------------------------------ supplier product master

    /** The supplier's own name / code for one variant, or null when it is not in their catalogue. */
    public static function productOf(int $supplierId, int $variantId): ?array
    {
        return DB::one('SELECT * FROM supplier_products WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ?', [self::tid(), $supplierId, $variantId]);
    }

    /**
     * Puts a variant in a supplier's catalogue (or updates it). Blank name / code never wipe what is already saved unless $overwrite.
     * @return int id of the catalogue row
     */
    public static function saveProduct(int $supplierId, int $variantId, ?string $name, ?string $code, ?string $note = null, bool $overwrite = false): int
    {
        $t = self::tid();
        $name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 150) : null;
        $code = $code !== null && trim($code) !== '' ? mb_substr(trim($code), 0, 60) : null;
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 150) : null;
        $have = self::productOf($supplierId, $variantId);
        if (!$have) return DB::insert('supplier_products', ['tenant_id' => $t, 'supplier_id' => $supplierId, 'variant_id' => $variantId, 'supplier_name' => $name, 'supplier_code' => $code, 'note' => $note]);
        $set = $overwrite ? ['supplier_name' => $name, 'supplier_code' => $code, 'note' => $note]
            : array_filter(['supplier_name' => $name, 'supplier_code' => $code, 'note' => $note], fn($v) => $v !== null);
        if ($set) DB::run('UPDATE supplier_products SET ' . implode(',', array_map(fn($c) => "`$c` = ?", array_keys($set))) . ' WHERE tenant_id = ? AND id = ?', [...array_values($set), $t, $have['id']]);
        return (int)$have['id'];
    }

    /** A supplier's catalogue with item names, the rate in force today and what we last paid. */
    public static function productsOf(int $supplierId): array
    {
        $rows = DB::all(
            'SELECT p.*, v.sku, v.name AS vname, v.is_active AS v_active, i.name AS item_name, i.id AS item_id, i.item_type, u.short_name AS unit
             FROM supplier_products p JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE p.tenant_id = ? AND p.supplier_id = ? ORDER BY i.name, v.name', [self::tid(), $supplierId]);
        foreach ($rows as &$r) {
            $cur = self::currentRate($supplierId, (int)$r['variant_id']);
            $r['rate_row'] = $cur;
            $r['net'] = $cur ? self::netRate($cur) : null;
            $last = self::lastPaid($supplierId, (int)$r['variant_id']);
            $r['last_paid'] = $last ? (float)$last['unit_price'] : null;
            $r['last_on'] = $last['received_date'] ?? null;
        }
        return $rows;
    }

    /** SQL columns sup_name / sup_code: the supplier's own name and code for a line's variant. $supplierSql names the supplier id (an expression using the line alias l). */
    public static function theirCols(string $supplierSql): string
    {
        $sub = fn($c) => "(SELECT sp.$c FROM supplier_products sp WHERE sp.tenant_id = l.tenant_id AND sp.supplier_id = ($supplierSql) AND sp.variant_id = l.variant_id)";
        return ', ' . $sub('supplier_name') . ' AS sup_name, ' . $sub('supplier_code') . ' AS sup_code';
    }

    /** variant_id => [supplier_name, supplier_code] for one supplier. */
    public static function productMap(int $supplierId): array
    {
        $o = [];
        foreach (DB::all('SELECT variant_id, supplier_name, supplier_code FROM supplier_products WHERE tenant_id = ? AND supplier_id = ?', [self::tid(), $supplierId]) as $r) $o[(int)$r['variant_id']] = $r;
        return $o;
    }

    /** Adds a rate. A newer rate closes the one before it, so the old one stays as history. */
    public static function addRate(int $supplierId, int $variantId, array $d): int
    {
        $t = self::tid();
        $from = $d['valid_from'];
        $same = DB::val('SELECT id FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ? AND valid_from = ?', [$t, $supplierId, $variantId, $from]);
        $row = ['rate' => $d['rate'], 'discount_pct' => $d['discount_pct'], 'min_qty' => $d['min_qty'], 'lead_time_days' => $d['lead_time_days'],
            'supplier_code' => $d['supplier_code'], 'valid_to' => $d['valid_to'], 'note' => $d['note']];
        if ($same) {
            $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($row)));
            DB::run("UPDATE supplier_rates SET $set WHERE tenant_id = ? AND id = ?", [...array_values($row), $t, $same]);
            return (int)$same;
        }
        if ($row['valid_to'] === null) {
            DB::run('UPDATE supplier_rates SET valid_to = DATE_SUB(?, INTERVAL 1 DAY) WHERE tenant_id = ? AND supplier_id = ? AND variant_id = ? AND valid_from < ? AND (valid_to IS NULL OR valid_to >= ?)',
                [$from, $t, $supplierId, $variantId, $from, $from]);
        }
        return DB::insert('supplier_rates', ['tenant_id' => $t, 'supplier_id' => $supplierId, 'variant_id' => $variantId, 'valid_from' => $from, 'created_by' => Auth::user()['id'] ?? null] + $row);
    }

    /** Every rate of one supplier (newest first) with item names; 'is_current' marks the ones in force today. */
    public static function ratesOf(int $supplierId): array
    {
        $rows = DB::all(
            'SELECT r.*, v.sku, v.name AS vname, i.name AS item_name, i.id AS item_id, sp.supplier_name AS sname, COALESCE(sp.supplier_code, r.supplier_code) AS scode
             FROM supplier_rates r JOIN item_variants v ON v.id = r.variant_id JOIN items i ON i.id = v.item_id
             LEFT JOIN supplier_products sp ON sp.tenant_id = r.tenant_id AND sp.supplier_id = r.supplier_id AND sp.variant_id = r.variant_id
             WHERE r.tenant_id = ? AND r.supplier_id = ? ORDER BY i.name, v.name, r.valid_from DESC, r.id DESC', [self::tid(), $supplierId]);
        $today = date('Y-m-d');
        $seen = [];
        foreach ($rows as &$r) {
            $live = $r['valid_from'] <= $today && ($r['valid_to'] === null || $r['valid_to'] >= $today);
            $r['is_current'] = $live && !isset($seen[$r['variant_id']]);
            if ($r['is_current']) $seen[$r['variant_id']] = 1;
            $r['net'] = self::netRate($r);
        }
        return $rows;
    }

    /** Adds 'is_current' and 'net' to rate rows (any mix of suppliers / items). Rows must be ordered newest start date first. */
    public static function markCurrent(array $rows): array
    {
        $today = date('Y-m-d');
        $seen = [];
        foreach ($rows as &$r) {
            $k = $r['supplier_id'] . '-' . $r['variant_id'];
            $live = $r['valid_from'] <= $today && ($r['valid_to'] === null || $r['valid_to'] >= $today);
            $r['is_current'] = $live && !isset($seen[$k]);
            if ($r['is_current']) $seen[$k] = 1;
            $r['net'] = self::netRate($r);
        }
        return $rows;
    }

    /** Current rates of every supplier for the variants of one item, cheapest first (for the item page). */
    public static function ratesForItem(int $itemId): array
    {
        $today = date('Y-m-d');
        $rows = DB::all(
            'SELECT r.*, s.name AS supplier_name, v.sku, v.name AS vname, sp.supplier_name AS sname, COALESCE(sp.supplier_code, r.supplier_code) AS scode FROM supplier_rates r JOIN suppliers s ON s.id = r.supplier_id JOIN item_variants v ON v.id = r.variant_id
             LEFT JOIN supplier_products sp ON sp.tenant_id = r.tenant_id AND sp.supplier_id = r.supplier_id AND sp.variant_id = r.variant_id
             WHERE r.tenant_id = ? AND v.item_id = ? AND s.is_active = 1 AND r.valid_from <= ? AND (r.valid_to IS NULL OR r.valid_to >= ?)
             ORDER BY r.variant_id, r.valid_from DESC, r.id DESC', [self::tid(), $itemId, $today, $today]);
        $out = [];
        foreach ($rows as $r) {
            $k = $r['variant_id'] . '-' . $r['supplier_id'];
            if (isset($out[$k])) continue;
            $r['net'] = self::netRate($r);
            $out[$k] = $r;
        }
        $out = array_values($out);
        usort($out, fn($a, $b) => [$a['variant_id'], $a['net']] <=> [$b['variant_id'], $b['net']]);
        return $out;
    }
}
