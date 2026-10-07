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
        'stocktake' => ['stock.adjust', 'stock_docs', 'stock_doc', 'stock/takes'],
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
            $title = $s['title'] === '' && $first ? preg_replace('/ form$/i', '', $title) . ' information' : $title;
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

    // ------------------------------------------------------------------ timeline

    private const VERBS = ['create' => 'created', 'update' => 'updated', 'delete' => 'deleted', 'rate_add' => 'added a rate', 'rate_delete' => 'removed a rate', 'rate_import' => 'imported rates'];

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
