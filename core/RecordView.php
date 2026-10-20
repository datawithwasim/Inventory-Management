<?php
declare(strict_types=1);

namespace Core;

use App\Models\CustomFields;
use App\Models\ItemTypes;
use App\Models\Purchase;

/** Read-only record pages laid out from the same design as the edit form (Overview, Timeline, Notes). */
final class RecordView
{
    /** entity => [edit permission, table, audit entity name, path of its record pages] */
    public const META = [
        'item' => ['items.edit', 'items', 'item', 'items'], 'customer' => ['customers.edit', 'customers', 'customer', 'customers'], 'supplier' => ['suppliers.edit', 'suppliers', 'supplier', 'suppliers'],
        'warehouse' => ['warehouses.edit', 'warehouses', 'warehouse', 'warehouses'], 'location' => ['warehouses.edit', 'locations', 'location', 'locations'],
        'requisition' => ['purchase.edit', 'purchase_requisitions', 'requisition', 'purchase/requisitions'], 'purchase_order' => ['purchase.edit', 'purchase_orders', 'purchase_order', 'purchase/orders'],
        'grn' => ['purchase.edit', 'grns', 'grn', 'purchase/grns'], 'bill' => ['purchase.edit', 'purchase_bills', 'purchase_bill', 'purchase/bills'],
        'purchase_return' => ['purchase.edit', 'purchase_returns', 'purchase_return', 'purchase/returns'],
        'sales_order' => ['sales.edit', 'sales_orders', 'sales_order', 'sales/orders'], 'delivery' => ['sales.edit', 'deliveries', 'delivery', 'sales/deliveries'],
        'invoice' => ['sales.edit', 'sales_invoices', 'sales_invoice', 'sales/invoices'], 'sales_return' => ['sales.edit', 'sales_returns', 'sales_return', 'sales/returns'],
        'adjustment' => ['stock.adjust', 'stock_docs', 'stock_doc', 'stock/adjustments'], 'transfer' => ['stock.transfer', 'stock_docs', 'stock_doc', 'stock/transfers'],
        'stocktake' => ['stock.adjust', 'stock_docs', 'stock_doc', 'stock/takes'], 'supplier_item' => ['suppliers.edit', 'supplier_items', 'supplier_item', 'purchase/supplier-items'],
    ];
    /** Fields whose value links to the record it names. */
    private const LINKS = ['supplier_id' => 'suppliers', 'customer_id' => 'customers', 'warehouse_id' => 'warehouses', 'to_warehouse_id' => 'warehouses'];
    private const MONEY = ['credit_limit', 'cost_price', 'sale_price', 'delivery_charge', 'installation_charge', 'extra_cost', 'other_charges'];
    private const DAYS = ['payment_terms_days' => 'Immediate', 'credit_days' => 'Immediate', 'lead_time_days' => ''];
    private static array $lookups = [];
    /** Record-only facts of the page being drawn: key => [label, html]. */
    private static array $facts = [];

    private static function lookup(string $table, int $id): string
    {
        $k = "$table:$id";
        if (!isset(self::$lookups[$k])) {
            $r = DB::one("SELECT * FROM `$table` WHERE tenant_id = ? AND id = ?", [Auth::tenantId(), $id]);
            $name = (string)($r['name'] ?? '');
            if ($table === 'taxes' && $r) $name .= ' (' . rtrim(rtrim((string)$r['rate'], '0'), '.') . '%)';
            if ($table === 'units' && $r) $name .= ' (' . $r['short_name'] . ')';
            self::$lookups[$k] = $name;
        }
        return self::$lookups[$k];
    }

    /** Display text of one field for a record row. */
    public static function value(string $entity, string $key, array $row, array $cfValues = []): string
    {
        if (str_starts_with($key, 'cf:')) {
            $id = (int)substr($key, 3);
            foreach (CustomFields::fields($entity, false) as $f) if ((int)$f['id'] === $id) return CustomFields::display($f, $cfValues[$id] ?? null);
            return '';
        }
        $v = $row[$key] ?? null;
        if ($v === null || $v === '') return '';
        if (str_ends_with($key, '_date') || $key === 'valid_until') return fdate((string)$v);
        $tables = ['supplier_id' => 'suppliers', 'customer_id' => 'customers', 'warehouse_id' => 'warehouses', 'to_warehouse_id' => 'warehouses', 'category_id' => 'categories', 'brand_id' => 'brands', 'unit_id' => 'units', 'tax_id' => 'taxes', 'group_id' => 'customer_groups'];
        if ($key === 'variant_id' && $entity === 'supplier_item') {
            $o = DB::one('SELECT v.name, v.sku, i.name AS item_name FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ?', [Auth::tenantId(), (int)$v]);
            return $o ? $o['item_name'] . ($o['name'] ? ' — ' . $o['name'] : '') . ' (' . $o['sku'] . ')' : '';
        }
        if (isset($tables[$key])) return self::lookup($tables[$key], (int)$v);
        if ($key === 'item_type') return ItemTypes::LABELS[$v] ?? (string)$v;
        if ($key === 'supplier_type') return Purchase::SUPPLIER_TYPES[$v] ?? (string)$v;
        if (in_array($key, self::MONEY, true)) return (float)$v > 0 ? money($v) : '';
        if (isset(self::DAYS[$key])) return (int)$v > 0 ? (int)$v . ' days' : self::DAYS[$key];
        return (string)$v;
    }

    private static function label(string $entity, string $key, array $r): string
    {
        if (isset($r['cf'][$key])) return (string)$r['cf'][$key]['label'];
        $custom = (string)($r['props'][$key]['label'] ?? '');
        if ($custom !== '') return $custom;
        return str_starts_with($key, 'sys:') ? FormDesign::sysLabel($entity, $key) : (FormDesign::FIELDS[$entity][$key] ?? $key);
    }

    /** One value as safe HTML: linked to its record when it names one, a dash when empty. */
    private static function cell(string $entity, string $k, array $row, array $cfValues): string
    {
        if (str_starts_with($k, 'sys:')) {
            $h = self::$facts[substr($k, 4)][1] ?? '';
            return $h === '' || $h === null ? '<i class="text-muted">—</i>' : (string)$h;
        }
        $val = self::value($entity, $k, $row, $cfValues);
        if ($val === '') return '<i class="text-muted">—</i>';
        if ($k === 'variant_id' && $entity === 'supplier_item' && !empty($row['item_id'])) return '<a href="' . e(url('items/' . (int)$row['item_id'])) . '">' . e($val) . '</a>';
        if ($k === 'variant_id' && $entity === 'supplier_item') return '<i class="text-muted">—</i>';
        if (isset(self::LINKS[$k]) && (int)($row[$k] ?? 0) > 0) return '<a href="' . e(url(self::LINKS[$k] . '/' . (int)$row[$k])) . '">' . e($val) . '</a>';
        return nl2br(e($val));
    }

    /** The strip of key fields at the top of the detail page. */
    public static function summary(string $entity, array $row, array $cfValues, array $facts = []): string
    {
        self::$facts = $facts;
        if (!isset(FormDesign::FIELDS[$entity])) return '';
        $r = FormDesign::resolve($entity);
        $o = '';
        foreach ($r['summary'] as $k) {
            $o .= '<div class="rec-kv"><span>' . e(self::label($entity, $k, $r)) . '</span><b>' . self::cell($entity, $k, $row, $cfValues) . '</b></div>';
        }
        return $o === '' ? '' : '<div class="card rec-summary"><div class="card-body">' . $o . '</div></div>';
    }

    /** All sections of the design as read-only label / value cards. */
    public static function sections(string $entity, array $row, array $cfValues, array $facts = []): string
    {
        self::$facts = $facts;
        if (!isset(FormDesign::FIELDS[$entity])) return '';
        $r = FormDesign::resolve($entity);
        $o = '';
        $first = true;
        foreach ($r['sections'] as $s) {
            $s['fields'] = array_values(array_diff($s['fields'], $r['summary']));   // the summary strip already shows these
            if (!$s['fields']) continue;
            $title = $s['title'] !== '' ? $s['title'] : ($first ? (FormFields::ENTITY_LABELS[$entity] ?? 'Details') : 'More details');
            $title = $s['title'] === '' && $first ? trim(preg_replace(['/ form$/i', '/\s*\(.*?\)/'], '', $title)) . ' information' : $title;
            $first = false;
            $cols = in_array((int)$s['cols'], [1, 2, 3], true) ? (int)$s['cols'] : 2;
            $o .= '<section class="card rec-sec"><div class="card-header">' . e($title) . '</div><div class="card-body"><div class="rec-grid c' . $cols . '">';
            foreach ($s['fields'] as $k) {
                $o .= '<div class="rec-kv"><span>' . e(self::label($entity, $k, $r)) . '</span><b>' . self::cell($entity, $k, $row, $cfValues) . '</b></div>';
            }
            $o .= '</div></div></section>';
        }
        return $o;
    }

    /**
     * The record-only facts (and any extra columns) the generic record endpoint needs for entities whose old page worked them out in its view.
     * @return array{0: array, 1: array} the row, facts [key => [label, html]]
     */
    public static function prepare(string $entity, array $row): array
    {
        $facts = [];
        if ($entity === 'supplier_item') {
            $vid = (int)($row['variant_id'] ?? 0);
            $row['item_id'] = $vid ? (int)DB::val('SELECT item_id FROM item_variants WHERE tenant_id = ? AND id = ?', [Auth::tenantId(), $vid]) : 0;
            $cur = $vid ? Purchase::currentRate((int)$row['supplier_id'], $vid) : null;
            $last = $vid ? Purchase::lastPaid((int)$row['supplier_id'], $vid) : null;
            $facts = ['rate' => ['Current rate', $cur ? e(money(Purchase::netRate($cur))) : ''], 'last_paid' => ['Last paid', $last ? e(money($last['unit_price'])) . ' on ' . e(fdate($last['received_date'])) : ''],
                'preferred' => ['Preferred source', !empty($row['is_preferred']) ? 'Yes' : '']];
        }
        $name = fn(string $sql, array $a) => (string)DB::val($sql, $a);
        $link = fn(string $path, int $id, string $label) => $id > 0 && $label !== '' ? '<a href="' . e(url($path . '/' . $id)) . '">' . e($label) . '</a>' : '';
        $t = Auth::tenantId();
        if ($entity === 'purchase_order') {
            $creator = $row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '';
            $approver = $row['approved_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['approved_by']]) : '';
            $facts = ['created_by' => ['Created by', e($creator ?: '—')], 'approved_by' => ['Approved by', $approver ? e($approver) . ' on ' . e(substr((string)$row['approved_at'], 0, 10)) : ''], 'total' => ['Total', e(money($row['total']))]];
        } elseif ($entity === 'grn') {
            $pos = DB::all('SELECT p.id, p.po_no FROM grn_pos gp JOIN purchase_orders p ON p.id = gp.po_id WHERE gp.tenant_id = ? AND gp.grn_id = ? ORDER BY p.id', [$t, $row['id']]);
            $bill = DB::one('SELECT id, bill_no FROM purchase_bills WHERE tenant_id = ? AND grn_id = ?', [$t, $row['id']]);
            $facts = ['supplier' => [term('supplier'), $link('suppliers', (int)$row['supplier_id'], $name('SELECT name FROM suppliers WHERE id = ?', [$row['supplier_id']]))],
                'po' => [count($pos) > 1 ? 'Purchase orders' : 'Purchase order', implode(', ', array_map(fn($p) => $link('purchase/orders', (int)$p['id'], $p['po_no']), $pos))], 'bill' => ['Bill', $bill ? $link('purchase/bills', (int)$bill['id'], $bill['bill_no']) : ''],
                'received_by' => ['Received by', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')]];
        } elseif ($entity === 'bill') {
            $grn = $row['grn_id'] ? DB::one('SELECT id, grn_no FROM grns WHERE tenant_id = ? AND id = ?', [$t, $row['grn_id']]) : null;
            $facts = ['supplier' => [term('supplier'), $link('suppliers', (int)$row['supplier_id'], $name('SELECT name FROM suppliers WHERE id = ?', [$row['supplier_id']]))],
                'grn' => ['Goods receipt', $grn ? $link('purchase/grns', (int)$grn['id'], $grn['grn_no']) : ''], 'balance_due' => ['Balance due', e(money(max(0, Purchase::outstanding($row))))]];
        } elseif ($entity === 'purchase_return') {
            $grn = DB::one('SELECT id, grn_no FROM grns WHERE tenant_id = ? AND id = ?', [$t, $row['grn_id']]);
            $bill = $row['bill_id'] ? DB::one('SELECT id, bill_no FROM purchase_bills WHERE tenant_id = ? AND id = ?', [$t, $row['bill_id']]) : null;
            $facts = ['supplier' => [term('supplier'), $link('suppliers', (int)$row['supplier_id'], $name('SELECT name FROM suppliers WHERE id = ?', [$row['supplier_id']]))],
                'grn' => ['Goods receipt', $grn ? $link('purchase/grns', (int)$grn['id'], $grn['grn_no']) : ''], 'bill' => ['Bill', $bill ? $link('purchase/bills', (int)$bill['id'], $bill['bill_no']) : ''],
                'by' => ['By', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')], 'total' => ['Total (incl. tax)', e(money($row['total']))]];
        } elseif ($entity === 'requisition') {
            $po = $row['po_id'] ? DB::one('SELECT id, po_no FROM purchase_orders WHERE tenant_id = ? AND id = ?', [$t, $row['po_id']]) : null;
            $facts = ['raised' => ['Raised', e(fdate(substr((string)$row['created_at'], 0, 10)))], 'by' => ['By', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')],
                'po' => ['Purchase order', $po ? $link('purchase/orders', (int)$po['id'], $po['po_no']) : '']];
        } elseif ($entity === 'sales_order') {
            $facts = ['created_by' => ['Created by', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')], 'total' => ['Total', e(money($row['total']))]];
        } elseif ($entity === 'delivery') {
            $o = $row['order_id'] ? DB::one('SELECT id, order_no FROM sales_orders WHERE tenant_id = ? AND id = ?', [$t, $row['order_id']]) : null;
            $inv = DB::one('SELECT id, invoice_no FROM sales_invoices WHERE tenant_id = ? AND delivery_id = ?', [$t, $row['id']]);
            $facts = ['customer' => [term('customer'), $link('customers', (int)$row['customer_id'], $name('SELECT name FROM customers WHERE id = ?', [$row['customer_id']]))],
                'order' => ['Order', $o ? $link('sales/orders', (int)$o['id'], $o['order_no']) : ''], 'invoice' => ['Invoice', $inv ? $link('sales/invoices', (int)$inv['id'], $inv['invoice_no']) : ''],
                'by' => ['By', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')]];
        } elseif ($entity === 'invoice') {
            $d = $row['delivery_id'] ? DB::one('SELECT id, delivery_no FROM deliveries WHERE tenant_id = ? AND id = ?', [$t, $row['delivery_id']]) : null;
            $o = $row['order_id'] ? DB::one('SELECT id, order_no FROM sales_orders WHERE tenant_id = ? AND id = ?', [$t, $row['order_id']]) : null;
            $facts = ['customer' => [term('customer'), $link('customers', (int)$row['customer_id'], $name('SELECT name FROM customers WHERE id = ?', [$row['customer_id']]))],
                'delivery' => ['Delivery', $d ? $link('sales/deliveries', (int)$d['id'], $d['delivery_no']) : ''], 'order' => ['Order', $o ? $link('sales/orders', (int)$o['id'], $o['order_no']) : ''],
                'balance_due' => ['Balance due', e(money(max(0, Purchase::outstanding($row))))]];
        } elseif ($entity === 'sales_return') {
            $inv = DB::one('SELECT id, invoice_no FROM sales_invoices WHERE tenant_id = ? AND id = ?', [$t, $row['invoice_id']]);
            $facts = ['customer' => [term('customer'), $link('customers', (int)$row['customer_id'], $name('SELECT name FROM customers WHERE id = ?', [$row['customer_id']]))],
                'invoice' => ['Invoice', $inv ? $link('sales/invoices', (int)$inv['id'], $inv['invoice_no']) : ''],
                'by' => ['By', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')], 'total' => ['Total credit (incl. tax)', e(money($row['total']))]];
        } elseif ($entity === 'location') {
            $facts = ['warehouse' => [term('warehouse'), $link('warehouses', (int)$row['warehouse_id'], $name('SELECT name FROM warehouses WHERE id = ?', [$row['warehouse_id']]))]];
        } elseif ($entity === 'stocktake') {
            $facts = ['started' => ['Started', e(fdate(substr((string)$row['created_at'], 0, 10)))]];
        } elseif ($entity === 'adjustment' || $entity === 'transfer') {
            if (!empty($row['reason'])) $row['reason'] = \App\Controllers\AdjustmentController::REASONS[$row['reason']] ?? $row['reason'];
            $facts = ['date' => ['Date', e(fdate(substr((string)$row['created_at'], 0, 10))) . ' ' . e(substr((string)$row['created_at'], 11, 5))],
                'by' => ['By', e($row['created_by'] ? $name('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : '—')]];
            if ($entity === 'transfer') {
                $facts['status'] = ['Status', e(\App\Controllers\TransferController::STATUS[$row['status']][0] ?? (string)$row['status'])];
                $facts['received'] = ['Received', !empty($row['received_at']) ? e(fdate(substr((string)$row['received_at'], 0, 10))) . ' ' . e(substr((string)$row['received_at'], 11, 5)) : ''];
            }
        }
        return [$row, $facts];
    }

    /** A value as {text, href}: the structured twin of cell(), for the React pages. */
    private static function item(string $entity, string $k, array $row, array $cfValues, array $r, array $facts): array
    {
        $label = self::label($entity, $k, $r);
        if (str_starts_with($k, 'sys:')) {
            $html = (string)($facts[substr($k, 4)][1] ?? '');
            $href = preg_match('/href="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : null;
            $out = ['key' => $k, 'label' => $label, 'text' => trim(html_entity_decode(strip_tags($html))), 'href' => $href];
            // a value with several links (a receipt covering several purchase orders): one entry per link
            if (preg_match_all('/<a href="([^"]+)">(.*?)<\/a>/s', $html, $all, PREG_SET_ORDER) && count($all) > 1) {
                $out['links'] = array_map(fn($m) => ['text' => html_entity_decode(strip_tags($m[2])), 'href' => html_entity_decode($m[1])], $all);
            }
            return $out;
        }
        $text = self::value($entity, $k, $row, $cfValues);
        $href = null;
        if ($text !== '' && isset(self::LINKS[$k]) && (int)($row[$k] ?? 0) > 0) $href = url(self::LINKS[$k] . '/' . (int)$row[$k]);
        if ($text !== '' && $k === 'variant_id' && $entity === 'supplier_item' && !empty($row['item_id'])) $href = url('items/' . (int)$row['item_id']);
        return ['key' => $k, 'label' => $label, 'text' => $text, 'href' => $href];
    }

    /** The designed record page as data: summary strip + sections (title, columns, items). Same rules as summary() / sections(). */
    public static function payload(string $entity, array $row, array $cfValues, array $facts = []): array
    {
        if (!isset(FormDesign::FIELDS[$entity])) return ['summary' => [], 'sections' => []];
        $r = FormDesign::resolve($entity);
        $summary = array_map(fn($k) => self::item($entity, $k, $row, $cfValues, $r, $facts), $r['summary']);
        $sections = [];
        $first = true;
        foreach ($r['sections'] as $s) {
            $keys = array_values(array_diff($s['fields'], $r['summary']));
            if (!$keys) continue;
            $title = $s['title'] !== '' ? $s['title'] : ($first ? (FormFields::ENTITY_LABELS[$entity] ?? 'Details') : 'More details');
            $title = $s['title'] === '' && $first ? trim(preg_replace(['/ form$/i', '/\\s*\\(.*?\\)/'], '', $title)) . ' information' : $title;
            $first = false;
            $cols = in_array((int)$s['cols'], [1, 2, 3], true) ? (int)$s['cols'] : 2;
            $sections[] = ['title' => $title, 'cols' => $cols, 'items' => array_map(fn($k) => self::item($entity, $k, $row, $cfValues, $r, $facts), $keys)];
        }
        return ['summary' => $summary, 'sections' => $sections, 'related' => array_map(fn($x) => $x, $r['related'] ?? []), 'customised' => $r['customised']];
    }

    // ------------------------------------------------------------------ timeline

    private const VERBS = ['create' => 'created', 'update' => 'updated', 'delete' => 'deleted', 'rate_add' => 'added a rate', 'rate_delete' => 'removed a rate', 'rate_import' => 'imported rates'];

    /** Timeline entries as data. */
    public static function timelineRows(string $entity, int $id): array
    {
        $audit = self::META[$entity][2] ?? $entity;
        $out = [];
        foreach (DB::all(
            "SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON a.actor_type = 'user' AND u.id = a.actor_id
             WHERE a.tenant_id = ? AND a.entity = ? AND a.entity_id = ? ORDER BY a.id DESC LIMIT 100", [Auth::tenantId(), $audit, $id]) as $a) {
            $suffix = preg_replace('/^' . preg_quote($audit, '/') . '_/', '', (string)$a['action']);
            $out[] = ['who' => $a['user_name'] ?: ($a['actor_type'] === 'admin' ? 'Support' : 'System'), 'verb' => self::VERBS[$suffix] ?? str_replace('_', ' ', $suffix),
                'details' => $a['details'] ? mb_substr((string)$a['details'], 0, 120) : null, 'at' => (string)$a['created_at']];
        }
        return $out;
    }

    public static function timeline(string $entity, int $id, ?string $createdAt = null): string
    {
        $audit = self::META[$entity][2] ?? $entity;
        $rows = DB::all(
            "SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON a.actor_type = 'user' AND u.id = a.actor_id
             WHERE a.tenant_id = ? AND a.entity = ? AND a.entity_id = ? ORDER BY a.id DESC LIMIT 100", [Auth::tenantId(), $audit, $id]);
        $o = '<ul class="rec-tl">';
        foreach ($rows as $a) {
            $suffix = preg_replace('/^' . preg_quote($audit, '/') . '_/', '', (string)$a['action']);
            $verb = self::VERBS[$suffix] ?? str_replace('_', ' ', $suffix);
            $who = $a['user_name'] ?: ($a['actor_type'] === 'admin' ? 'Support' : 'System');
            $o .= '<li><span class="dot"></span><div><b>' . e($who) . '</b> ' . e($verb) . ($a['details'] ? ' <span class="text-muted">· ' . e(mb_substr((string)$a['details'], 0, 120)) . '</span>' : '')
                . '<div class="small text-muted">' . e(fdate(substr((string)$a['created_at'], 0, 10))) . ' ' . e(substr((string)$a['created_at'], 11, 5)) . '</div></div></li>';
        }
        if (!$rows) $o .= '<li><span class="dot"></span><div class="text-muted">No activity recorded yet' . ($createdAt ? ' · record added ' . e(fdate(substr($createdAt, 0, 10))) : '') . '.</div></li>';
        return $o . '</ul>';
    }

    // ------------------------------------------------------------------ notes

    public static function notes(string $entity, int $id): array
    {
        return DB::all(
            'SELECT n.*, u.name AS user_name FROM record_notes n LEFT JOIN users u ON u.id = n.created_by
             WHERE n.tenant_id = ? AND n.entity = ? AND n.entity_id = ? ORDER BY n.id DESC LIMIT 200', [Auth::tenantId(), $entity, $id]);
    }
}
