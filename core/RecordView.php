<?php
declare(strict_types=1);

namespace Core;

use App\Models\CustomFields;
use App\Models\ItemTypes;
use App\Models\Purchase;

/** Read-only record pages laid out from the same design as the edit form (Overview, Timeline, Notes). */
final class RecordView
{
    /** entity => [edit permission, table, audit entity name] */
    public const META = ['item' => ['items.edit', 'items'], 'customer' => ['customers.edit', 'customers'], 'supplier' => ['suppliers.edit', 'suppliers']];
    private const MONEY = ['credit_limit', 'cost_price', 'sale_price'];
    private const DAYS = ['payment_terms_days' => 'Immediate', 'credit_days' => 'Immediate', 'lead_time_days' => ''];
    private static array $lookups = [];

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
        $tables = ['category_id' => 'categories', 'brand_id' => 'brands', 'unit_id' => 'units', 'tax_id' => 'taxes', 'group_id' => 'customer_groups'];
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
        return $custom !== '' ? $custom : (FormDesign::FIELDS[$entity][$key] ?? $key);
    }

    /** The strip of key fields at the top of the detail page. */
    public static function summary(string $entity, array $row, array $cfValues): string
    {
        $r = FormDesign::resolve($entity);
        $o = '';
        foreach ($r['summary'] as $k) {
            $val = self::value($entity, $k, $row, $cfValues);
            $o .= '<div class="rec-kv"><span>' . e(self::label($entity, $k, $r)) . '</span><b>' . ($val !== '' ? e($val) : '<i class="text-muted">—</i>') . '</b></div>';
        }
        return $o === '' ? '' : '<div class="card rec-summary"><div class="card-body">' . $o . '</div></div>';
    }

    /** All sections of the design as read-only label / value cards. */
    public static function sections(string $entity, array $row, array $cfValues): string
    {
        $r = FormDesign::resolve($entity);
        $o = '';
        $first = true;
        foreach ($r['sections'] as $s) {
            if (!$s['fields']) continue;
            $title = $s['title'] !== '' ? $s['title'] : ($first ? (FormFields::ENTITY_LABELS[$entity] ?? 'Details') : 'More details');
            $title = $s['title'] === '' && $first ? preg_replace('/ form$/i', '', $title) . ' information' : $title;
            $first = false;
            $cols = in_array((int)$s['cols'], [1, 2, 3], true) ? (int)$s['cols'] : 2;
            $o .= '<section class="card rec-sec"><div class="card-header">' . e($title) . '</div><div class="card-body"><div class="rec-grid c' . $cols . '">';
            foreach ($s['fields'] as $k) {
                $val = self::value($entity, $k, $row, $cfValues);
                $o .= '<div class="rec-kv"><span>' . e(self::label($entity, $k, $r)) . '</span><b>' . ($val !== '' ? nl2br(e($val)) : '<i class="text-muted">—</i>') . '</b></div>';
            }
            $o .= '</div></div></section>';
        }
        return $o;
    }

    // ------------------------------------------------------------------ timeline

    private const VERBS = ['create' => 'created', 'update' => 'updated', 'delete' => 'deleted', 'rate_add' => 'added a rate', 'rate_delete' => 'removed a rate', 'rate_import' => 'imported rates'];

    public static function timeline(string $entity, int $id, ?string $createdAt = null): string
    {
        $rows = DB::all(
            "SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON a.actor_type = 'user' AND u.id = a.actor_id
             WHERE a.tenant_id = ? AND a.entity = ? AND a.entity_id = ? ORDER BY a.id DESC LIMIT 100", [Auth::tenantId(), $entity, $id]);
        $o = '<ul class="rec-tl">';
        foreach ($rows as $a) {
            $suffix = preg_replace('/^' . preg_quote($entity, '/') . '_/', '', (string)$a['action']);
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
