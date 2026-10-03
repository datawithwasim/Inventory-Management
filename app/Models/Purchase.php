<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/** Money maths and status rules for the purchase side. */
final class Purchase
{
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
}
