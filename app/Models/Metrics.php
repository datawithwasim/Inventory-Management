<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/** Numbers behind the dashboard. Sales/purchases are net of returns; no profit or tax reporting on purpose. */
final class Metrics
{
    public const AGEING = ['Not due', '1–30 days', '31–60 days', '61–90 days', 'Over 90 days'];

    private static function t(): int
    {
        return (int)Auth::tenantId();
    }

    /** Resolve a preset/from/to into [from, to, label]. */
    public static function period(array $q): array
    {
        $today = date('Y-m-d');
        $p = (string)($q['range'] ?? '30');
        switch ($p) {
            case '7': $from = date('Y-m-d', strtotime('-6 days')); $label = 'Last 7 days'; break;
            case '90': $from = date('Y-m-d', strtotime('-89 days')); $label = 'Last 90 days'; break;
            case 'mtd': $from = date('Y-m-01'); $label = 'This month'; break;
            case 'ytd': $from = date('Y-01-01'); $label = 'This year'; break;
            default: $p = '30'; $from = date('Y-m-d', strtotime('-29 days')); $label = 'Last 30 days';
        }
        $days = (int)((strtotime($today) - strtotime($from)) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime("$from -1 day"));
        $prevFrom = date('Y-m-d', strtotime("$prevTo -" . ($days - 1) . ' days'));
        return ['key' => $p, 'from' => $from, 'to' => $today, 'days' => $days, 'label' => $label, 'prev_from' => $prevFrom, 'prev_to' => $prevTo];
    }

    private static function sum(string $table, string $col, string $from, string $to): float
    {
        return (float)DB::val("SELECT COALESCE(SUM(total - returned_amount),0) FROM $table WHERE tenant_id = ? AND $col BETWEEN ? AND ?", [self::t(), $from, $to]);
    }

    public static function sales(string $from, string $to): float
    {
        return self::sum('sales_invoices', 'invoice_date', $from, $to);
    }

    public static function purchases(string $from, string $to): float
    {
        return self::sum('purchase_bills', 'bill_date', $from, $to);
    }

    /** Daily/weekly/monthly buckets with gaps filled. Returns [labels, values]. */
    public static function series(string $table, string $col, string $from, string $to): array
    {
        $days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($days <= 45) { $expr = $col; $step = '+1 day'; $fmt = 'd M'; }
        elseif ($days <= 200) { $expr = "DATE_SUB($col, INTERVAL WEEKDAY($col) DAY)"; $step = '+1 week'; $fmt = 'd M'; }
        else { $expr = "DATE_FORMAT($col, '%Y-%m-01')"; $step = '+1 month'; $fmt = 'M y'; }
        $rows = DB::all("SELECT $expr AS b, SUM(total - returned_amount) AS v FROM $table WHERE tenant_id = ? AND $col BETWEEN ? AND ? GROUP BY b", [self::t(), $from, $to]);
        $map = array_column($rows, 'v', 'b');
        $start = $step === '+1 day' ? $from : ($step === '+1 week' ? date('Y-m-d', strtotime($from . ' -' . ((int)date('N', strtotime($from)) - 1) . ' days')) : date('Y-m-01', strtotime($from)));
        $labels = $vals = [];
        for ($d = $start; $d <= $to; $d = date('Y-m-d', strtotime("$d $step"))) {
            $labels[] = date($fmt, strtotime($d));
            $vals[] = round((float)($map[$d] ?? 0), 2);
        }
        return [$labels, $vals];
    }

    /** Outstanding amounts by how overdue they are (5 buckets). */
    public static function ageing(string $kind): array
    {
        [$table, $date] = $kind === 'sales' ? ['sales_invoices', 'invoice_date'] : ['purchase_bills', 'bill_date'];
        $rows = DB::all(
            "SELECT DATEDIFF(CURDATE(), COALESCE(due_date, $date)) AS od, (total - returned_amount - paid_amount) AS due
             FROM $table WHERE tenant_id = ? AND (total - returned_amount - paid_amount) > 0.004", [self::t()]);
        return self::bucket($rows);
    }

    public static function bucket(array $rows): array
    {
        $b = array_fill(0, 5, 0.0);
        foreach ($rows as $r) {
            $od = (int)$r['od'];
            $i = $od <= 0 ? 0 : ($od <= 30 ? 1 : ($od <= 60 ? 2 : ($od <= 90 ? 3 : 4)));
            $b[$i] += (float)$r['due'];
        }
        return array_map(fn($v) => round($v, 2), $b);
    }

    public static function topItems(string $from, string $to, int $limit = 8): array
    {
        return DB::all(
            'SELECT i.name, SUM((di.qty - di.qty_returned) * di.unit_price * (1 - di.discount_pct / 100)) AS value
             FROM delivery_items di JOIN sales_invoices inv ON inv.delivery_id = di.delivery_id AND inv.tenant_id = di.tenant_id
             JOIN item_variants v ON v.id = di.variant_id JOIN items i ON i.id = v.item_id
             WHERE di.tenant_id = ? AND inv.invoice_date BETWEEN ? AND ? AND di.parent_id IS NULL
             GROUP BY i.id, i.name HAVING value > 0.004 ORDER BY value DESC LIMIT ' . (int)$limit, [self::t(), $from, $to]);
    }

    public static function stockByCategory(int $limit = 8): array
    {
        return DB::all(
            'SELECT COALESCE(c.name, \'Uncategorised\') AS name, SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)) AS value
             FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id JOIN items i ON i.id = v.item_id
             LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0
             WHERE s.tenant_id = ? GROUP BY c.id, c.name HAVING value > 0.004 ORDER BY value DESC LIMIT ' . (int)$limit, [self::t()]);
    }

    public static function stockValue(): float
    {
        return (float)DB::val(
            'SELECT COALESCE(SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)),0) FROM stock_balances s
             JOIN item_variants v ON v.id = s.variant_id LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0 WHERE s.tenant_id = ?', [self::t()]);
    }

    public static function lowStock(int $limit = 6): array
    {
        return DB::all(
            'SELECT i.id, i.name, i.reorder_level, u.short_name AS unit,
                    (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id WHERE v.item_id = i.id) AS stock
             FROM items i JOIN units u ON u.id = i.unit_id
             WHERE i.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND i.reorder_level > 0
             HAVING stock <= i.reorder_level ORDER BY (stock / i.reorder_level), i.name LIMIT ' . (int)$limit, [self::t()]);
    }

    public static function lowStockCount(): int
    {
        return (int)DB::val(
            'SELECT COUNT(*) FROM items i WHERE i.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND i.reorder_level > 0
               AND (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id WHERE v.item_id = i.id) <= i.reorder_level', [self::t()]);
    }

    public static function overdueInvoices(int $limit = 5): array
    {
        return DB::all(
            'SELECT inv.id, inv.invoice_no, c.name AS customer, (inv.total - inv.returned_amount - inv.paid_amount) AS due, DATEDIFF(CURDATE(), inv.due_date) AS od
             FROM sales_invoices inv JOIN customers c ON c.id = inv.customer_id
             WHERE inv.tenant_id = ? AND inv.due_date IS NOT NULL AND inv.due_date < CURDATE() AND (inv.total - inv.returned_amount - inv.paid_amount) > 0.004
             ORDER BY inv.due_date LIMIT ' . (int)$limit, [self::t()]);
    }

    public static function openOrders(int $limit = 5): array
    {
        return DB::all(
            "SELECT o.id, o.order_no, c.name AS customer, o.expected_date, o.total FROM sales_orders o JOIN customers c ON c.id = o.customer_id
             WHERE o.tenant_id = ? AND o.status IN ('confirmed','partial') ORDER BY (o.expected_date IS NULL), o.expected_date, o.id LIMIT " . (int)$limit, [self::t()]);
    }

    public static function due(string $kind): float
    {
        $table = $kind === 'sales' ? 'sales_invoices' : 'purchase_bills';
        return (float)DB::val("SELECT COALESCE(SUM(total - returned_amount - paid_amount),0) FROM $table WHERE tenant_id = ?", [self::t()]);
    }
}
