<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/**
 * Report registry. Each report = title, group, filters, columns and a runner returning plain rows.
 * Column types: text, int, qty, money, date. Columns with 'sum' => true get a totals row.
 * Money shown here is simple (excl. tax where noted) — no profit/margin or tax reports by design.
 */
final class Reports
{
    public const GROUPS = ['stock' => 'Stock', 'sales' => 'Sales', 'purchase' => 'Purchase', 'party' => 'Customers & suppliers'];
    public const MOVES = ['opening' => 'Opening stock', 'purchase' => 'Purchase receipt', 'purchase_return' => 'Purchase return', 'sale' => 'Sale / delivery',
        'sales_return' => 'Sales return', 'adjustment' => 'Adjustment', 'stocktake' => 'Stock count', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out'];

    private static function t(): int
    {
        return (int)Auth::tenantId();
    }

    public static function find(string $slug): ?array
    {
        $all = self::all();
        if (!isset($all[$slug])) return null;
        return ['slug' => $slug] + $all[$slug];
    }

    /** Parse and sanitise the filters a report accepts. */
    public static function filters(array $def, array $q): array
    {
        $f = [];
        foreach ($def['filters'] as $name) {
            $v = trim((string)($q[$name] ?? ''));
            switch ($name) {
                case 'from': case 'to':
                    $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
                    $f[$name] = $ok ? $v : ($name === 'from' ? date('Y-m-d', strtotime('-29 days')) : date('Y-m-d'));
                    break;
                case 'warehouse': case 'category': case 'customer': case 'supplier':
                    $f[$name] = (int)$v;
                    break;
                case 'days':
                    $f[$name] = max(1, min(3650, (int)($v === '' ? ($def['days'] ?? 90) : $v)));
                    break;
                case 'q':
                    $f[$name] = mb_substr($v, 0, 60);
                    break;
                default: // select-type: by, status, type, kind, stock, order
                    $opts = $def['select'][$name] ?? [];
                    $f[$name] = isset($opts[$v]) ? $v : (string)array_key_first($opts);
            }
        }
        if (isset($f['from'], $f['to']) && $f['from'] > $f['to']) [$f['from'], $f['to']] = [$f['to'], $f['from']];
        return $f;
    }

    /** Lookup lists for the filter bar. */
    public static function lists(): array
    {
        $t = self::t();
        return [
            'warehouse' => DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? ORDER BY is_default DESC, name', [$t]),
            'category' => DB::all('SELECT id, name FROM categories WHERE tenant_id = ? ORDER BY name', [$t]),
            'customer' => DB::all('SELECT id, name FROM customers WHERE tenant_id = ? ORDER BY name', [$t]),
            'supplier' => DB::all('SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name', [$t]),
        ];
    }

    /** Run a report; returns [rows, truncated]. */
    public static function run(array $def, array $f, int $limit): array
    {
        if (!empty($def['needs']) && empty($f[$def['needs']])) return [[], false];
        $rows = ($def['run'])($f, $limit + 1);
        $trunc = count($rows) > $limit;
        return [$trunc ? array_slice($rows, 0, $limit) : $rows, $trunc];
    }

    public static function totals(array $def, array $rows): array
    {
        $tot = [];
        foreach ($def['columns'] as $k => $c) if (!empty($c['sum'])) $tot[$k] = round(array_sum(array_column($rows, $k)), 3);
        return $tot;
    }

    // ------------------------------------------------------------------ reports

    public static function all(): array
    {
        static $defs = null;
        if ($defs !== null) return $defs;
        $t = self::t();
        $col = fn(string $label, string $type = 'text', bool $sum = false) => ['label' => $label, 'type' => $type, 'sum' => $sum];

        $defs = [];

        $defs['stock-summary'] = [
            'title' => 'Stock summary & valuation', 'group' => 'stock',
            'desc' => 'What is in stock right now: on hand, reserved for orders, free, and value at cost.',
            'filters' => ['warehouse', 'category', 'stock', 'q'],
            'select' => ['stock' => ['in' => 'In stock', 'all' => 'All items', 'zero' => 'Out of stock']],
            'columns' => ['item' => $col('Item'), 'variant' => $col('Variant'), 'sku' => $col('SKU'), 'category' => $col('Category'), 'unit' => $col('Unit'),
                'on_hand' => $col('On hand', 'qty'), 'reserved' => $col('Reserved', 'qty'), 'free' => $col('Free', 'qty'),
                'avg_cost' => $col('Avg cost', 'money'), 'value' => $col('Stock value', 'money', true)],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [];
                $rw = $join = '';
                if ($f['warehouse']) { $rw = ' AND o.warehouse_id = ?'; $p[] = $f['warehouse']; }   // reserved sub-select (SELECT list)
                if ($f['warehouse']) { $join = ' AND s.warehouse_id = ?'; $p[] = $f['warehouse']; } // balances join
                $p[] = $t;
                $where = 'v.tenant_id = ? AND i.is_bundle = 0';
                if ($f['category']) { $where .= ' AND i.category_id = ?'; $p[] = $f['category']; }
                if ($f['q'] !== '') { $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $f['q'] . '%')); }
                $having = $f['stock'] === 'in' ? 'HAVING on_hand > 0.0005' : ($f['stock'] === 'zero' ? 'HAVING on_hand <= 0.0005' : '');
                $sql = "SELECT i.name AS item, v.name AS variant, v.sku, COALESCE(c.name,'') AS category, u.short_name AS unit,
                        COALESCE(SUM(s.qty),0) AS on_hand,
                        (SELECT COALESCE(SUM(oi.qty_ordered - oi.qty_delivered),0) FROM sales_order_items oi JOIN sales_orders o ON o.id = oi.order_id
                          WHERE oi.variant_id = v.id AND o.status IN ('confirmed','partial') AND oi.qty_ordered > oi.qty_delivered$rw) AS reserved,
                        COALESCE(SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)),0) AS value
                        FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN categories c ON c.id = i.category_id
                        LEFT JOIN stock_balances s ON s.variant_id = v.id$join LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0
                        WHERE $where GROUP BY v.id $having ORDER BY i.name, v.name LIMIT " . (int)$limit;
                $rows = DB::all($sql, $p);
                foreach ($rows as &$r) {
                    $r['free'] = (float)$r['on_hand'] - (float)$r['reserved'];
                    $r['avg_cost'] = (float)$r['on_hand'] > 0.0005 ? round((float)$r['value'] / (float)$r['on_hand'], 2) : 0;
                }
                return $rows;
            },
        ];

        $defs['batch-stock'] = [
            'title' => 'Roll / batch-wise stock', 'group' => 'stock',
            'desc' => 'Every roll: how much came in, how much is used, what is left and on which rack.',
            'filters' => ['warehouse', 'category', 'status', 'q'],
            'select' => ['status' => ['open' => 'In stock', 'finished' => 'Finished', 'all' => 'All rolls']],
            'columns' => ['item' => $col('Item'), 'variant' => $col('Variant'), 'batch_no' => $col('Batch / roll'), 'supplier_lot' => $col('Supplier lot'),
                'received_date' => $col('Received', 'date'), 'age' => $col('Age (days)', 'int'), 'received_qty' => $col('Received qty', 'qty', true),
                'used' => $col('Used', 'qty', true), 'balance' => $col('Balance', 'qty', true), 'racks' => $col('Warehouse / rack'), 'status' => $col('Status')],
            'run' => null,
        ];
        $defs['batch-stock']['run'] = function (array $f, int $limit) use ($t) {
            $p = [$t];
            $bw = '';
            if ($f['warehouse']) { $bw = ' AND warehouse_id = ?'; $p[] = $f['warehouse']; }
            $p[] = $t;
            $where = 'b.tenant_id = ?';
            if ($f['category']) { $where .= ' AND i.category_id = ?'; $p[] = $f['category']; }
            if ($f['q'] !== '') { $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR b.batch_no LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $f['q'] . '%')); }
            $having = $f['status'] === 'open' ? 'HAVING balance > 0.0005' : ($f['status'] === 'finished' ? 'HAVING balance <= 0.0005' : '');
            $rows = DB::all(
                "SELECT i.name AS item, v.name AS variant, b.batch_no, b.supplier_lot, b.received_date, b.received_qty,
                        COALESCE(bal.q,0) AS balance, DATEDIFF(CURDATE(), b.received_date) AS age,
                        (SELECT GROUP_CONCAT(DISTINCT CONCAT(w.name, IF(l.code IS NULL, '', CONCAT(' / ', l.code))) ORDER BY w.name SEPARATOR ', ')
                           FROM stock_balances s2 JOIN warehouses w ON w.id = s2.warehouse_id LEFT JOIN locations l ON l.id = s2.location_id AND s2.location_id > 0
                          WHERE s2.batch_id = b.id AND s2.qty > 0.0005) AS racks
                 FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id
                 LEFT JOIN (SELECT batch_id, SUM(qty) AS q FROM stock_balances WHERE tenant_id = ? AND batch_id > 0$bw GROUP BY batch_id) bal ON bal.batch_id = b.id
                 WHERE $where $having ORDER BY i.name, b.received_date, b.id LIMIT " . (int)$limit, $p);
            foreach ($rows as &$r) {
                $r['used'] = round((float)$r['received_qty'] - (float)$r['balance'], 3);
                $r['status'] = Stock::batchStatus((float)$r['balance'], (float)$r['received_qty']);
                $r['racks'] = (string)$r['racks'];
            }
            return $rows;
        };

        $defs['stock-ledger'] = [
            'title' => 'Stock ledger (movements)', 'group' => 'stock',
            'desc' => 'Every stock movement in a period — what came in, what went out, where, and why.',
            'filters' => ['from', 'to', 'warehouse', 'type', 'q'],
            'select' => ['type' => ['' => 'All movements'] + self::MOVES],
            'columns' => ['when' => $col('Date'), 'type' => $col('Movement'), 'item' => $col('Item'), 'sku' => $col('SKU'), 'batch_no' => $col('Batch / roll'),
                'warehouse' => $col('Warehouse'), 'rack' => $col('Rack'), 'qty' => $col('Qty +/−', 'qty', true), 'unit_cost' => $col('Unit cost', 'money'),
                'note' => $col('Note'), 'user' => $col('By')],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$t, $f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'];
                $w = '';
                if ($f['warehouse']) { $w .= ' AND l.warehouse_id = ?'; $p[] = $f['warehouse']; }
                if ($f['type'] !== '') { $w .= ' AND l.type = ?'; $p[] = $f['type']; }
                if ($f['q'] !== '') { $w .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR b.batch_no LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $f['q'] . '%')); }
                $rows = DB::all(
                    "SELECT l.created_at AS `when`, l.type, i.name AS item, v.sku, b.batch_no, w.name AS warehouse, loc.code AS rack, l.qty_change AS qty,
                            l.unit_cost, l.note, u.name AS user
                     FROM stock_ledger l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN warehouses w ON w.id = l.warehouse_id
                     LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations loc ON loc.id = l.location_id AND l.location_id > 0
                     LEFT JOIN users u ON u.id = l.user_id
                     WHERE l.tenant_id = ? AND l.created_at BETWEEN ? AND ?$w ORDER BY l.id DESC LIMIT " . (int)$limit, $p);
                foreach ($rows as &$r) { $r['type'] = self::MOVES[$r['type']] ?? $r['type']; $r['when'] = substr((string)$r['when'], 0, 16); }
                return $rows;
            },
        ];

        $defs['dead-stock'] = [
            'title' => 'Stock ageing & dead stock', 'group' => 'stock', 'days' => 90,
            'desc' => 'Stock that has not sold for a number of days — money lying idle on your shelves.',
            'filters' => ['warehouse', 'category', 'days'],
            'columns' => ['item' => $col('Item'), 'variant' => $col('Variant'), 'sku' => $col('SKU'), 'on_hand' => $col('On hand', 'qty'), 'value' => $col('Stock value', 'money', true),
                'last_sale' => $col('Last sold', 'date'), 'last_in' => $col('Last received', 'date'), 'idle' => $col('Days idle', 'int')],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [];
                $j = '';
                if ($f['warehouse']) { $j = ' AND s.warehouse_id = ?'; $p[] = $f['warehouse']; }
                $p[] = $t;
                $w = 'v.tenant_id = ? AND i.is_bundle = 0';
                if ($f['category']) { $w .= ' AND i.category_id = ?'; $p[] = $f['category']; }
                $p[] = $f['days'];
                return DB::all(
                    "SELECT * FROM (SELECT i.name AS item, v.name AS variant, v.sku, SUM(s.qty) AS on_hand,
                            SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)) AS value,
                            (SELECT MAX(d.delivery_date) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id WHERE di.variant_id = v.id) AS last_sale,
                            (SELECT DATE(MAX(l.created_at)) FROM stock_ledger l WHERE l.variant_id = v.id AND l.type IN ('purchase','opening','transfer_in')) AS last_in
                     FROM item_variants v JOIN items i ON i.id = v.item_id JOIN stock_balances s ON s.variant_id = v.id$j
                     LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0
                     WHERE $w GROUP BY v.id HAVING on_hand > 0.0005) z
                     WHERE DATEDIFF(CURDATE(), COALESCE(z.last_sale, z.last_in, CURDATE())) >= ?
                     ORDER BY DATEDIFF(CURDATE(), COALESCE(z.last_sale, z.last_in, CURDATE())) DESC, z.value DESC LIMIT " . (int)$limit, $p);
            },
            'post' => function (array $rows) {
                foreach ($rows as &$r) $r['idle'] = (int)((strtotime(date('Y-m-d')) - strtotime((string)($r['last_sale'] ?: $r['last_in'] ?: date('Y-m-d')))) / 86400);
                return $rows;
            },
        ];

        $defs['movers'] = [
            'title' => 'Fast & slow movers', 'group' => 'stock',
            'desc' => 'What sells quickly and what does not, with how many days the current stock will last.',
            'filters' => ['from', 'to', 'warehouse', 'category', 'order'],
            'select' => ['order' => ['fast' => 'Fastest first', 'slow' => 'Slowest first (only items in stock)']],
            'columns' => ['item' => $col('Item'), 'variant' => $col('Variant'), 'sku' => $col('SKU'), 'sold' => $col('Sold in period', 'qty', true),
                'on_hand' => $col('On hand', 'qty'), 'cover' => $col('Days of stock left', 'int')],
            'run' => function (array $f, int $limit) use ($t) {
                $dw = $f['warehouse'] ? ' AND d.warehouse_id = ?' : '';
                $sw = $f['warehouse'] ? ' AND s.warehouse_id = ?' : '';
                $w = 'v.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1' . ($f['category'] ? ' AND i.category_id = ?' : '');
                $slow = $f['order'] === 'slow';
                $days = max(1, (int)((strtotime($f['to']) - strtotime($f['from'])) / 86400) + 1);
                $rows = DB::all(
                    "SELECT * FROM (SELECT i.name AS item, v.name AS variant, v.sku, COALESCE(x.sold,0) AS sold,
                            (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s WHERE s.variant_id = v.id$sw) AS on_hand
                     FROM item_variants v JOIN items i ON i.id = v.item_id
                     LEFT JOIN (SELECT di.variant_id, SUM(di.qty - di.qty_returned) AS sold FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id
                                 WHERE di.tenant_id = ? AND d.delivery_date BETWEEN ? AND ?$dw GROUP BY di.variant_id) x ON x.variant_id = v.id
                     WHERE $w) z " . ($slow ? 'WHERE z.on_hand > 0.0005 ORDER BY z.sold ASC, z.on_hand DESC' : 'WHERE z.sold > 0.0005 ORDER BY z.sold DESC') . ' LIMIT ' . (int)$limit,
                    self::reorderMovers($f, $t));
                foreach ($rows as &$r) $r['cover'] = (float)$r['sold'] > 0.0005 ? (int)round((float)$r['on_hand'] / ((float)$r['sold'] / $days)) : null;
                return $rows;
            },
        ];

        $defs['sales-register'] = [
            'title' => 'Sales register', 'group' => 'sales',
            'desc' => 'All invoices in a period with amounts, payments received and balance.',
            'filters' => ['from', 'to', 'customer', 'status'],
            'select' => ['status' => ['' => 'Any payment status', 'unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid']],
            'columns' => ['date' => $col('Date', 'date'), 'no' => $col('Invoice'), 'customer' => $col('Customer'), 'subtotal' => $col('Amount', 'money', true),
                'discount' => $col('Discount', 'money', true), 'tax' => $col('Tax', 'money', true), 'charges' => $col('Delivery / install', 'money', true), 'total' => $col('Total', 'money', true),
                'returned' => $col('Returned', 'money', true), 'paid' => $col('Paid', 'money', true), 'balance' => $col('Balance', 'money', true), 'status' => $col('Payment')],
            'run' => fn(array $f, int $limit) => self::register('sales', $f, $limit),
        ];

        $defs['sales-summary'] = [
            'title' => 'Sales summary', 'group' => 'sales',
            'desc' => 'Sales by item, by category or by customer for a period (invoices net of returns, before tax).',
            'filters' => ['from', 'to', 'by', 'customer', 'category'],
            'select' => ['by' => ['item' => 'By item', 'category' => 'By category', 'customer' => 'By customer']],
            'columns' => ['name' => $col('Name'), 'qty' => $col('Quantity', 'qty'), 'invoices' => $col('Invoices', 'int'), 'value' => $col('Sales value', 'money', true)],
            'run' => function (array $f, int $limit) use ($t) {
                if ($f['by'] === 'customer') {
                    $p = [$t, $f['from'], $f['to']];
                    $w = '';
                    if ($f['customer']) { $w = ' AND inv.customer_id = ?'; $p[] = $f['customer']; }
                    return DB::all(
                        "SELECT c.name, NULL AS qty, COUNT(*) AS invoices, SUM(inv.subtotal - inv.discount_total + inv.delivery_charge + inv.installation_charge) AS value
                         FROM sales_invoices inv JOIN customers c ON c.id = inv.customer_id WHERE inv.tenant_id = ? AND inv.invoice_date BETWEEN ? AND ?$w
                         GROUP BY c.id, c.name ORDER BY value DESC LIMIT " . (int)$limit, $p);
                }
                $p = [$t, $f['from'], $f['to']];
                $w = '';
                if ($f['customer']) { $w .= ' AND inv.customer_id = ?'; $p[] = $f['customer']; }
                if ($f['category']) { $w .= ' AND i.category_id = ?'; $p[] = $f['category']; }
                $g = $f['by'] === 'category' ? ["COALESCE(c.name,'Uncategorised')", 'c.id, c.name', 'NULL'] : ['i.name', 'i.id, i.name', 'SUM(di.qty - di.qty_returned)'];
                return DB::all(
                    "SELECT {$g[0]} AS name, {$g[2]} AS qty, COUNT(DISTINCT inv.id) AS invoices,
                            SUM((di.qty - di.qty_returned) * di.unit_price * (1 - di.discount_pct / 100)) AS value
                     FROM delivery_items di JOIN sales_invoices inv ON inv.delivery_id = di.delivery_id AND inv.tenant_id = di.tenant_id
                     JOIN item_variants v ON v.id = di.variant_id JOIN items i ON i.id = v.item_id LEFT JOIN categories c ON c.id = i.category_id
                     WHERE di.tenant_id = ? AND inv.invoice_date BETWEEN ? AND ? AND di.parent_id IS NULL$w
                     GROUP BY {$g[1]} HAVING value <> 0 ORDER BY value DESC LIMIT " . (int)$limit, $p);
            },
        ];

        $defs['purchase-register'] = [
            'title' => 'Purchase register', 'group' => 'purchase',
            'desc' => 'All supplier bills in a period with amounts, payments made and balance.',
            'filters' => ['from', 'to', 'supplier', 'status'],
            'select' => ['status' => ['' => 'Any payment status', 'unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid']],
            'columns' => ['date' => $col('Date', 'date'), 'no' => $col('Bill'), 'supplier_bill_no' => $col('Supplier bill no.'), 'supplier' => $col('Supplier'),
                'subtotal' => $col('Amount', 'money', true), 'tax' => $col('Tax', 'money', true), 'charges' => $col('Other charges', 'money', true), 'total' => $col('Total', 'money', true),
                'returned' => $col('Returned', 'money', true), 'paid' => $col('Paid', 'money', true), 'balance' => $col('Balance', 'money', true), 'status' => $col('Payment')],
            'run' => fn(array $f, int $limit) => self::register('purchase', $f, $limit),
        ];

        $defs['purchase-summary'] = [
            'title' => 'Purchase summary', 'group' => 'purchase',
            'desc' => 'Purchases by item or by supplier for a period (net of returns, before tax).',
            'filters' => ['from', 'to', 'by', 'supplier', 'category'],
            'select' => ['by' => ['item' => 'By item', 'supplier' => 'By supplier']],
            'columns' => ['name' => $col('Name'), 'qty' => $col('Quantity', 'qty'), 'bills' => $col('Bills', 'int'), 'value' => $col('Purchase value', 'money', true)],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$t, $f['from'], $f['to']];
                $w = '';
                if ($f['supplier']) { $w .= ' AND pb.supplier_id = ?'; $p[] = $f['supplier']; }
                if ($f['by'] === 'supplier') {
                    return DB::all(
                        "SELECT s.name, NULL AS qty, COUNT(*) AS bills, SUM(pb.subtotal) - SUM(pb.returned_amount) AS value
                         FROM purchase_bills pb JOIN suppliers s ON s.id = pb.supplier_id WHERE pb.tenant_id = ? AND pb.bill_date BETWEEN ? AND ?$w
                         GROUP BY s.id, s.name ORDER BY value DESC LIMIT " . (int)$limit, $p);
                }
                if ($f['category']) { $w .= ' AND i.category_id = ?'; $p[] = $f['category']; }
                return DB::all(
                    "SELECT i.name, SUM(gi.qty - gi.qty_returned) AS qty, COUNT(DISTINCT pb.id) AS bills, SUM((gi.qty - gi.qty_returned) * gi.unit_price) AS value
                     FROM grn_items gi JOIN purchase_bills pb ON pb.grn_id = gi.grn_id AND pb.tenant_id = gi.tenant_id
                     JOIN item_variants v ON v.id = gi.variant_id JOIN items i ON i.id = v.item_id
                     WHERE gi.tenant_id = ? AND pb.bill_date BETWEEN ? AND ?$w GROUP BY i.id, i.name HAVING value <> 0 ORDER BY value DESC LIMIT " . (int)$limit, $p);
            },
        ];

        $defs['supplier-dues'] = [
            'title' => 'Supplier purchases & outstanding', 'group' => 'purchase',
            'desc' => 'For each supplier: what we bought in the period, what we owe now (all bills), how much of it is overdue, and the credit limit.',
            'filters' => ['from', 'to', 'supplier', 'status'],
            'select' => ['status' => ['all' => 'All suppliers with activity', 'due' => 'Only where we owe money']],
            'columns' => ['supplier' => $col('Supplier'), 'kind' => $col('Type'), 'city' => $col('City'), 'bills' => $col('Bills in period', 'int', true), 'purchased' => $col('Bought in period', 'money', true),
                'owed' => $col('We owe now', 'money', true), 'overdue' => $col('Of which overdue', 'money', true), 'limit' => $col('Credit limit', 'money'), 'limit_use' => $col('Limit used')],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$f['from'], $f['to'], $f['from'], $f['to'], $t];
                $w = '';
                if ($f['supplier']) { $w = ' AND s.id = ?'; $p[] = $f['supplier']; }
                $rows = DB::all(
                    "SELECT s.name AS supplier, s.supplier_type, s.city, s.credit_limit AS `limit`,
                            (SELECT COUNT(*) FROM purchase_bills b WHERE b.supplier_id = s.id AND b.bill_date BETWEEN ? AND ?) AS bills,
                            (SELECT COALESCE(SUM(b.total - b.returned_amount), 0) FROM purchase_bills b WHERE b.supplier_id = s.id AND b.bill_date BETWEEN ? AND ?) AS purchased,
                            (SELECT COALESCE(SUM(b.total - b.returned_amount - b.paid_amount), 0) FROM purchase_bills b WHERE b.supplier_id = s.id) AS owed,
                            (SELECT COALESCE(SUM(b.total - b.returned_amount - b.paid_amount), 0) FROM purchase_bills b WHERE b.supplier_id = s.id
                                AND b.total - b.returned_amount - b.paid_amount > 0.004 AND COALESCE(b.due_date, b.bill_date) < CURDATE()) AS overdue
                     FROM suppliers s WHERE s.tenant_id = ?$w ORDER BY owed DESC, purchased DESC, s.name LIMIT 2000", $p);
                $out = [];
                foreach ($rows as $r) {
                    $r['owed'] = max(0.0, (float)$r['owed']);
                    if ($f['status'] === 'due' ? $r['owed'] <= 0.004 : ((int)$r['bills'] === 0 && $r['owed'] <= 0.004 && !$f['supplier'])) continue;
                    $r['kind'] = Purchase::SUPPLIER_TYPES[$r['supplier_type'] ?? ''] ?? '';
                    $r['limit_use'] = (float)$r['limit'] > 0 ? round($r['owed'] / (float)$r['limit'] * 100) . '%' . ($r['owed'] > (float)$r['limit'] ? ' — over limit' : '') : '';
                    $out[] = $r;
                    if (count($out) >= $limit) break;
                }
                return $out;
            },
        ];

        $defs['rate-comparison'] = [
            'title' => 'Supplier rate comparison', 'group' => 'purchase',
            'desc' => 'Current rates of every supplier for each item, side by side. The lowest net rate is marked and the others show how much dearer they are.',
            'filters' => ['category', 'supplier', 'q'],
            'columns' => ['item' => $col('Item'), 'variant' => $col('Variant'), 'sku' => $col('SKU'), 'supplier' => $col('Supplier'), 'rate' => $col('Rate', 'money'), 'discount' => $col('Disc. %'),
                'net' => $col('Net rate', 'money'), 'min_qty' => $col('Min qty', 'qty'), 'lead' => $col('Lead time'), 'vs' => $col('Compared to lowest'), 'since' => $col('Valid from', 'date')],
            'run' => function (array $f, int $limit) use ($t) {
                $today = date('Y-m-d');
                $p = [$t, $today, $today];
                $w = '';
                if ($f['category']) { $w .= ' AND i.category_id = ?'; $p[] = $f['category']; }
                if ($f['q'] !== '') { $w .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ? OR i.design_no LIKE ?)'; array_push($p, ...array_fill(0, 4, '%' . $f['q'] . '%')); }
                $all = DB::all(
                    "SELECT r.*, s.name AS supplier, s.lead_time_days AS s_lead, i.name AS item, v.name AS variant, v.sku FROM supplier_rates r
                     JOIN suppliers s ON s.id = r.supplier_id AND s.is_active = 1 JOIN item_variants v ON v.id = r.variant_id JOIN items i ON i.id = v.item_id
                     WHERE r.tenant_id = ? AND r.valid_from <= ? AND (r.valid_to IS NULL OR r.valid_to >= ?)$w ORDER BY r.valid_from DESC, r.id DESC", $p);
                $cur = [];
                foreach ($all as $r) { $k = $r['variant_id'] . '-' . $r['supplier_id']; if (!isset($cur[$k])) $cur[$k] = $r; }
                $by = [];
                foreach ($cur as $r) { $r['net'] = Purchase::netRate($r); $by[$r['variant_id']][] = $r; }
                $out = [];
                foreach ($by as $list) {
                    usort($list, fn($a, $b) => $a['net'] <=> $b['net']);
                    $low = $list[0]['net'];
                    if ($f['supplier'] && !in_array($f['supplier'], array_map(fn($r) => (int)$r['supplier_id'], $list), true)) continue;
                    foreach ($list as $r) {
                        if ($f['supplier'] && (int)$r['supplier_id'] !== $f['supplier']) continue;
                        $out[] = ['item' => $r['item'], 'variant' => $r['variant'] ?? '', 'sku' => $r['sku'], 'supplier' => $r['supplier'], 'rate' => $r['rate'],
                            'discount' => (float)$r['discount_pct'] > 0 ? rtrim(rtrim(number_format((float)$r['discount_pct'], 2), '0'), '.') . '%' : '', 'net' => $r['net'], 'min_qty' => (float)$r['min_qty'] > 0 ? $r['min_qty'] : null,
                            'lead' => ($r['lead_time_days'] ?? $r['s_lead']) ? (int)($r['lead_time_days'] ?? $r['s_lead']) . ' days' : '',
                            'vs' => count($list) < 2 ? 'Only supplier' : ($r['net'] - $low < 0.005 ? 'Lowest' : '+' . round(($r['net'] - $low) / max($low, 0.01) * 100, 1) . '% (' . number_format($r['net'] - $low, 2) . ' more)'), 'since' => $r['valid_from']];
                    }
                }
                usort($out, fn($a, $b) => [$a['item'], $a['variant'], $a['net']] <=> [$b['item'], $b['variant'], $b['net']]);
                return array_slice($out, 0, $limit);
            },
        ];

        $defs['rate-history'] = [
            'title' => 'Supplier rate history', 'group' => 'purchase',
            'desc' => 'Every rate a supplier has quoted, newest first, with how much it changed from their previous rate.',
            'filters' => ['supplier', 'q'],
            'columns' => ['from' => $col('Valid from', 'date'), 'to' => $col('Valid till', 'date'), 'supplier' => $col('Supplier'), 'item' => $col('Item'), 'variant' => $col('Variant'),
                'rate' => $col('Rate', 'money'), 'net' => $col('Net rate', 'money'), 'change' => $col('Change vs previous')],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$t];
                $w = '';
                if ($f['supplier']) { $w .= ' AND r.supplier_id = ?'; $p[] = $f['supplier']; }
                if ($f['q'] !== '') { $w .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $f['q'] . '%')); }
                $rows = DB::all(
                    "SELECT r.*, s.name AS supplier, i.name AS item, v.name AS variant FROM supplier_rates r JOIN suppliers s ON s.id = r.supplier_id
                     JOIN item_variants v ON v.id = r.variant_id JOIN items i ON i.id = v.item_id WHERE r.tenant_id = ?$w ORDER BY r.supplier_id, r.variant_id, r.valid_from, r.id", $p);
                $prev = [];
                $out = [];
                foreach ($rows as $r) {
                    $k = $r['supplier_id'] . '-' . $r['variant_id'];
                    $net = Purchase::netRate($r);
                    $chg = '';
                    if (isset($prev[$k]) && $prev[$k] > 0) { $d = ($net - $prev[$k]) / $prev[$k] * 100; $chg = abs($d) < 0.05 ? 'No change' : ($d > 0 ? '▲ +' : '▼ ') . round($d, 1) . '%'; }
                    else $chg = 'First rate';
                    $prev[$k] = $net;
                    $out[] = ['from' => $r['valid_from'], 'to' => $r['valid_to'], 'supplier' => $r['supplier'], 'item' => $r['item'], 'variant' => $r['variant'] ?? '', 'rate' => $r['rate'], 'net' => $net, 'change' => $chg];
                }
                usort($out, fn($a, $b) => [$b['from']] <=> [$a['from']]);
                return array_slice($out, 0, $limit);
            },
        ];

        $defs['price-paid'] = [
            'title' => 'Purchase price history (what we actually paid)', 'group' => 'purchase',
            'desc' => 'Every goods-receipt line in the period with the price paid and how it compares with the previous purchase of the same item.',
            'filters' => ['from', 'to', 'supplier', 'q'],
            'columns' => ['date' => $col('Received', 'date'), 'grn' => $col('Receipt'), 'supplier' => $col('Supplier'), 'item' => $col('Item'), 'variant' => $col('Variant'),
                'qty' => $col('Qty', 'qty', true), 'price' => $col('Unit price', 'money'), 'change' => $col('Vs previous purchase')],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$t, $f['to']];
                $w = '';
                if ($f['q'] !== '') { $w .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $f['q'] . '%')); }
                $rows = DB::all(
                    "SELECT g.received_date AS date, g.grn_no AS grn, g.supplier_id, s.name AS supplier, l.variant_id, i.name AS item, v.name AS variant, l.qty, l.unit_price AS price
                     FROM grn_items l JOIN grns g ON g.id = l.grn_id JOIN suppliers s ON s.id = g.supplier_id JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id
                     WHERE l.tenant_id = ? AND g.received_date <= ?$w ORDER BY l.variant_id, g.received_date, g.id, l.id", $p);
                $prev = [];
                $out = [];
                foreach ($rows as $r) {
                    $pr = $prev[$r['variant_id']] ?? null;
                    $prev[$r['variant_id']] = (float)$r['price'];
                    if ($r['date'] < $f['from'] || ($f['supplier'] && (int)$r['supplier_id'] !== $f['supplier'])) continue;
                    $r['change'] = $pr === null ? 'First purchase' : ($pr > 0 && abs($r['price'] - $pr) / $pr * 100 >= 0.05 ? (($r['price'] > $pr ? '▲ +' : '▼ ') . round(($r['price'] - $pr) / $pr * 100, 1) . '%') : 'No change');
                    $out[] = $r;
                }
                usort($out, fn($a, $b) => [$b['date'], $b['grn']] <=> [$a['date'], $a['grn']]);
                return array_slice($out, 0, $limit);
            },
        ];

        $defs['ageing'] = [
            'title' => 'Receivables & payables ageing', 'group' => 'party',
            'desc' => 'Who owes you and whom you owe, grouped by how many days past the due date.',
            'filters' => ['kind'],
            'select' => ['kind' => ['sales' => 'Customers owe us', 'purchase' => 'We owe suppliers']],
            'columns' => ['party' => $col('Name'), 'b0' => $col('Not due', 'money', true), 'b1' => $col('1–30 days', 'money', true), 'b2' => $col('31–60 days', 'money', true),
                'b3' => $col('61–90 days', 'money', true), 'b4' => $col('Over 90 days', 'money', true), 'total' => $col('Total', 'money', true)],
            'run' => function (array $f, int $limit) use ($t) {
                [$tb, $dt, $pt, $pj] = $f['kind'] === 'sales' ? ['sales_invoices', 'invoice_date', 'customers', 'customer_id'] : ['purchase_bills', 'bill_date', 'suppliers', 'supplier_id'];
                $rows = DB::all(
                    "SELECT p.name AS party, DATEDIFF(CURDATE(), COALESCE(x.due_date, x.$dt)) AS od, (x.total - x.returned_amount - x.paid_amount) AS due
                     FROM $tb x JOIN $pt p ON p.id = x.$pj WHERE x.tenant_id = ? AND (x.total - x.returned_amount - x.paid_amount) > 0.004", [$t]);
                $by = [];
                foreach ($rows as $r) {
                    $by[$r['party']] ??= array_fill(0, 5, 0.0);
                    $i = Metrics::bucket([$r])  ;
                    foreach ($i as $k => $v) $by[$r['party']][$k] += $v;
                }
                $out = [];
                foreach ($by as $name => $b) $out[] = ['party' => $name, 'b0' => $b[0], 'b1' => $b[1], 'b2' => $b[2], 'b3' => $b[3], 'b4' => $b[4], 'total' => array_sum($b)];
                usort($out, fn($a, $b) => $b['total'] <=> $a['total']);
                return array_slice($out, 0, $limit);
            },
        ];

        $defs['customer-statement'] = [
            'title' => 'Customer statement', 'group' => 'party', 'needs' => 'customer',
            'desc' => 'Invoices, returns and payments for one customer with a running balance.',
            'filters' => ['customer', 'from', 'to'],
            'columns' => ['date' => $col('Date', 'date'), 'doc' => $col('Document'), 'detail' => $col('Details'), 'debit' => $col('Billed', 'money', true), 'credit' => $col('Received / returned', 'money', true), 'balance' => $col('Balance', 'money')],
            'run' => fn(array $f, int $limit) => self::statement('customer', $f, $limit),
        ];

        $defs['supplier-statement'] = [
            'title' => 'Supplier statement', 'group' => 'party', 'needs' => 'supplier',
            'desc' => 'Bills, returns and payments for one supplier with a running balance.',
            'filters' => ['supplier', 'from', 'to'],
            'columns' => ['date' => $col('Date', 'date'), 'doc' => $col('Document'), 'detail' => $col('Details'), 'debit' => $col('Billed', 'money', true), 'credit' => $col('Paid / returned', 'money', true), 'balance' => $col('Balance', 'money')],
            'run' => fn(array $f, int $limit) => self::statement('supplier', $f, $limit),
        ];

        $defs['low-stock'] = [
            'title' => 'Low stock & reorder', 'group' => 'stock',
            'desc' => 'Items at or below their reorder level with a suggested quantity and the supplier you last bought from.',
            'filters' => ['category'],
            'columns' => ['item' => $col('Item'), 'sku' => $col('SKU'), 'on_hand' => $col('On hand', 'qty'), 'reorder_level' => $col('Reorder level', 'qty'),
                'on_order' => $col('Already ordered / requested', 'qty'), 'suggest' => $col('Suggested order', 'qty'), 'unit' => $col('Unit'),
                'supplier' => $col('Last supplier'), 'cost' => $col('Last cost', 'money')],
            'run' => fn(array $f, int $limit) => self::lowStock($f['category'], $limit),
        ];

        $defs['adjustments'] = [
            'title' => 'Adjustments & write-offs', 'group' => 'stock',
            'desc' => 'Stock corrections and stock-count differences with the reason given, and their value at cost.',
            'filters' => ['from', 'to', 'warehouse'],
            'columns' => ['date' => $col('Date', 'date'), 'doc_no' => $col('Document'), 'kind' => $col('Type'), 'reason' => $col('Reason'), 'item' => $col('Item'), 'batch_no' => $col('Batch / roll'),
                'warehouse' => $col('Warehouse'), 'qty' => $col('Qty +/−', 'qty', true), 'value' => $col('Value at cost', 'money', true)],
            'run' => function (array $f, int $limit) use ($t) {
                $p = [$t, $f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'];
                $w = '';
                if ($f['warehouse']) { $w = ' AND l.warehouse_id = ?'; $p[] = $f['warehouse']; }
                $rows = DB::all(
                    "SELECT DATE(l.created_at) AS date, d.doc_no, d.type AS kind, d.reason, i.name AS item, b.batch_no, w.name AS warehouse, l.qty_change AS qty, l.qty_change * l.unit_cost AS value
                     FROM stock_ledger l JOIN stock_docs d ON d.id = l.ref_id AND l.ref_type = 'stock_doc' AND d.tenant_id = l.tenant_id AND d.type IN ('adjustment','stocktake')
                     JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN warehouses w ON w.id = l.warehouse_id
                     LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0
                     WHERE l.tenant_id = ? AND l.created_at BETWEEN ? AND ? AND l.type IN ('adjustment','stocktake')$w ORDER BY l.id DESC LIMIT " . (int)$limit, $p);
                foreach ($rows as &$r) { $r['kind'] = $r['kind'] === 'stocktake' ? 'Stock count' : 'Adjustment'; $r['reason'] = (string)$r['reason']; }
                return $rows;
            },
        ];

        // wrap runners that have a post step
        foreach ($defs as $k => $d) {
            if (!empty($d['post'])) {
                $run = $d['run']; $post = $d['post'];
                $defs[$k]['run'] = fn(array $f, int $limit) => $post($run($f, $limit));
            }
        }
        return $defs;
    }

    /** Params for the movers query in the order the placeholders appear. */
    private static function reorderMovers(array $f, int $t): array
    {
        $p = [];
        if ($f['warehouse']) $p[] = $f['warehouse'];          // stock subquery (SELECT list)
        array_push($p, $t, $f['from'], $f['to']);              // sold sub-select
        if ($f['warehouse']) $p[] = $f['warehouse'];
        $p[] = $t;                                             // WHERE
        if ($f['category']) $p[] = $f['category'];
        return $p;
    }

    private static function register(string $kind, array $f, int $limit): array
    {
        $t = self::t();
        $sales = $kind === 'sales';
        [$tb, $dt, $pt, $pj] = $sales ? ['sales_invoices', 'invoice_date', 'customers', 'customer_id'] : ['purchase_bills', 'bill_date', 'suppliers', 'supplier_id'];
        $p = [$t, $f['from'], $f['to']];
        $w = '';
        $party = $sales ? 'customer' : 'supplier';
        if (!empty($f[$party])) { $w .= " AND x.$pj = ?"; $p[] = $f[$party]; }
        $due = '(x.total - x.returned_amount - x.paid_amount)';
        if (($f['status'] ?? '') === 'paid') $w .= " AND $due <= 0.004";
        elseif (($f['status'] ?? '') === 'unpaid') $w .= ' AND x.paid_amount <= 0.004 AND ' . $due . ' > 0.004';
        elseif (($f['status'] ?? '') === 'partial') $w .= ' AND x.paid_amount > 0.004 AND ' . $due . ' > 0.004';
        $no = $sales ? 'x.invoice_no' : 'x.bill_no';
        $extra = $sales ? 'x.discount_total AS discount, x.delivery_charge + x.installation_charge AS charges, x.tax_total AS tax' : 'x.supplier_bill_no, x.other_charges AS charges, x.tax_total AS tax';
        $rows = DB::all(
            "SELECT x.$dt AS date, $no AS no, p.name AS " . ($sales ? 'customer' : 'supplier') . ", x.subtotal, $extra, x.total, x.returned_amount AS returned, x.paid_amount AS paid, $due AS balance
             FROM $tb x JOIN $pt p ON p.id = x.$pj WHERE x.tenant_id = ? AND x.$dt BETWEEN ? AND ?$w ORDER BY x.$dt DESC, x.id DESC LIMIT " . (int)$limit, $p);
        foreach ($rows as &$r) {
            $bal = (float)$r['balance'];
            $r['balance'] = max(0, $bal);
            $r['status'] = $bal <= 0.004 ? 'Paid' : ((float)$r['paid'] > 0.004 ? 'Partly paid' : 'Unpaid');
        }
        return $rows;
    }

    /** Running-balance statement. Advance receipts are counted once: applied part via the invoice row, remainder as unapplied. */
    private static function statement(string $who, array $f, int $limit): array
    {
        $t = self::t();
        $ev = [];
        if ($who === 'customer') {
            $id = $f['customer'];
            foreach (DB::all('SELECT id, invoice_date AS d, invoice_no AS n, total FROM sales_invoices WHERE tenant_id = ? AND customer_id = ?', [$t, $id]) as $r)
                $ev[] = [$r['d'], 1, (int)$r['id'], 'Invoice ' . $r['n'], '', (float)$r['total'], 0.0];
            foreach (DB::all('SELECT r.id, r.return_date AS d, r.return_no AS n, r.total, i.invoice_no FROM sales_returns r JOIN sales_invoices i ON i.id = r.invoice_id WHERE r.tenant_id = ? AND r.customer_id = ?', [$t, $id]) as $r)
                $ev[] = [$r['d'], 2, (int)$r['id'], 'Return ' . $r['n'], 'Against ' . $r['invoice_no'], 0.0, (float)$r['total']];
            foreach (DB::all('SELECT p.id, p.paid_on AS d, p.amount, p.applied, p.method, p.reference, p.invoice_id, i.invoice_no FROM customer_payments p LEFT JOIN sales_invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.customer_id = ?', [$t, $id]) as $r) {
                $amt = $r['invoice_id'] ? (float)$r['amount'] : (float)$r['amount'] - (float)$r['applied'];
                if ($amt <= 0.004) continue;
                $detail = trim(($r['invoice_id'] ? 'Against ' . $r['invoice_no'] : 'Advance, not yet used') . ($r['reference'] ? ' · ' . $r['reference'] : ''));
                $ev[] = [$r['d'], 3, (int)$r['id'], 'Payment (' . ($r['method'] ?: 'cash') . ')', $detail, 0.0, $amt];
            }
        } else {
            $id = $f['supplier'];
            foreach (DB::all('SELECT id, bill_date AS d, bill_no AS n, total FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ?', [$t, $id]) as $r)
                $ev[] = [$r['d'], 1, (int)$r['id'], 'Bill ' . $r['n'], '', (float)$r['total'], 0.0];
            foreach (DB::all('SELECT r.id, r.return_date AS d, r.return_no AS n, r.total, b.bill_no FROM purchase_returns r JOIN purchase_bills b ON b.id = r.bill_id WHERE r.tenant_id = ? AND r.supplier_id = ?', [$t, $id]) as $r)
                $ev[] = [$r['d'], 2, (int)$r['id'], 'Return ' . $r['n'], 'Against ' . $r['bill_no'], 0.0, (float)$r['total']];
            foreach (DB::all('SELECT p.id, p.paid_on AS d, p.amount, p.method, p.reference, b.bill_no FROM supplier_payments p JOIN purchase_bills b ON b.id = p.bill_id WHERE p.tenant_id = ? AND b.supplier_id = ?', [$t, $id]) as $r)
                $ev[] = [$r['d'], 3, (int)$r['id'], 'Payment (' . ($r['method'] ?: 'cash') . ')', trim('Against ' . $r['bill_no'] . ($r['reference'] ? ' · ' . $r['reference'] : '')), 0.0, (float)$r['amount']];
        }
        usort($ev, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
        $bal = 0.0;
        $rows = [];
        foreach ($ev as $e) {
            if ($e[0] > $f['to']) break;
            $bal = round($bal + $e[5] - $e[6], 2);
            if ($e[0] < $f['from']) { $open = $bal; continue; }
            $rows[] = ['date' => $e[0], 'doc' => $e[3], 'detail' => $e[4], 'debit' => $e[5], 'credit' => $e[6], 'balance' => $bal];
        }
        $opening = ['date' => $f['from'], 'doc' => 'Opening balance', 'detail' => '', 'debit' => 0.0, 'credit' => 0.0, 'balance' => $open ?? 0.0];
        return array_slice(array_merge([$opening], $rows), 0, $limit);
    }

    /** Items at/below reorder level with suggestion + last supplier. Also used to create draft POs. */
    public static function lowStock(int $category = 0, int $limit = 1000): array
    {
        $t = self::t();
        $p = [$t];
        $w = '';
        if ($category) { $w = ' AND i.category_id = ?'; $p[] = $category; }
        $rows = DB::all(
            "SELECT * FROM (SELECT i.id AS item_id, i.name AS item, i.reorder_level, i.reorder_qty, u.short_name AS unit, u.allow_decimal,
                    (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v2 ON v2.id = s.variant_id WHERE v2.item_id = i.id) AS on_hand,
                    (SELECT COALESCE(SUM(poi.qty_ordered - poi.qty_received),0) FROM purchase_order_items poi JOIN purchase_orders po ON po.id = poi.po_id
                      JOIN item_variants v3 ON v3.id = poi.variant_id WHERE v3.item_id = i.id AND po.status IN ('draft','pending_approval','approved','partial') AND poi.qty_ordered > poi.qty_received) AS on_order,
                    (SELECT v.id FROM item_variants v WHERE v.item_id = i.id AND v.is_active = 1 ORDER BY v.id LIMIT 1) AS variant_id,
                    (SELECT COALESCE(SUM(ri.qty),0) FROM purchase_requisition_items ri JOIN purchase_requisitions rq ON rq.id = ri.requisition_id
                      JOIN item_variants v4 ON v4.id = ri.variant_id WHERE v4.item_id = i.id AND rq.status = 'open') AS requested
             FROM items i JOIN units u ON u.id = i.unit_id
             WHERE i.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND i.reorder_level > 0$w) z
             WHERE z.on_hand <= z.reorder_level AND z.variant_id IS NOT NULL ORDER BY z.item LIMIT " . (int)$limit, $p);
        foreach ($rows as &$r) {
            $v = DB::one('SELECT sku, cost_price FROM item_variants WHERE id = ?', [$r['variant_id']]);
            $last = DB::one(
                'SELECT g.supplier_id, s.name, gi.unit_price FROM grn_items gi JOIN grns g ON g.id = gi.grn_id JOIN suppliers s ON s.id = g.supplier_id
                 JOIN item_variants v ON v.id = gi.variant_id WHERE v.item_id = ? AND g.tenant_id = ? ORDER BY g.received_date DESC, g.id DESC LIMIT 1', [$r['item_id'], $t]);
            $need = (float)$r['reorder_qty'] > 0 ? (float)$r['reorder_qty'] : max(1.0, (float)$r['reorder_level'] - (float)$r['on_hand']);
            $r['on_order'] = (float)$r['on_order'] + (float)$r['requested'];
            $need = max(0.0, $need - (float)$r['on_order']);
            $r['sku'] = $v['sku'];
            $r['suggest'] = $r['allow_decimal'] ? round($need, 3) : ceil($need);
            $r['supplier_id'] = $last['supplier_id'] ?? null;
            $r['supplier'] = $last['name'] ?? '— none yet —';
            $r['cost'] = $last ? (float)$last['unit_price'] : (float)$v['cost_price'];
        }
        return $rows;
    }
}
