<?php
declare(strict_types=1);

namespace Core;

/**
 * The records a "Lookup" field can point to. One registry feeds the custom-field type "Lookup", the searchable pickers on the standard
 * forms and the way a picked record is shown (its name, linked to its page). Adding a source here makes it available everywhere.
 *
 * Each source: label, from (tables), id / label / sub (SQL expressions), tenant (column), search (columns), where (extra), order,
 * link (page path of the picked record) and link_id (what goes after the path, default the id).
 */
final class Lookups
{
    public const SOURCES = [
        'item' => ['label' => 'Item', 'from' => 'item_variants v JOIN items i ON i.id = v.item_id', 'id' => 'v.id',
            'label_sql' => "CONCAT(i.name, IF(v.name IS NULL OR v.name = '', '', CONCAT(' — ', v.name)))", 'sub' => 'v.sku', 'tenant' => 'v.tenant_id',
            'search' => ['i.name', 'v.name', 'v.sku', 'v.barcode'], 'where' => 'v.is_active = 1', 'order' => 'i.name, v.name', 'link' => 'items', 'link_id' => 'i.id'],
        'supplier' => ['label' => 'Supplier', 'from' => 'suppliers s', 'id' => 's.id', 'label_sql' => 's.name', 'sub' => "COALESCE(s.city, '')", 'tenant' => 's.tenant_id',
            'search' => ['s.name', 's.phone', 's.city'], 'where' => 's.is_active = 1', 'order' => 's.name', 'link' => 'suppliers'],
        'customer' => ['label' => 'Customer', 'from' => 'customers c', 'id' => 'c.id', 'label_sql' => 'c.name', 'sub' => "COALESCE(c.phone, '')", 'tenant' => 'c.tenant_id',
            'search' => ['c.name', 'c.phone'], 'where' => 'c.is_active = 1', 'order' => 'c.name', 'link' => 'customers'],
        'warehouse' => ['label' => 'Warehouse', 'from' => 'warehouses w', 'id' => 'w.id', 'label_sql' => 'w.name', 'sub' => "COALESCE(w.code, '')", 'tenant' => 'w.tenant_id',
            'search' => ['w.name', 'w.code'], 'where' => 'w.is_active = 1', 'order' => 'w.name', 'link' => 'warehouses', 'scoped' => 'w.id'],
        'rack' => ['label' => 'Rack', 'from' => 'locations l JOIN warehouses w ON w.id = l.warehouse_id', 'id' => 'l.id', 'label_sql' => 'l.code', 'sub' => 'w.name', 'tenant' => 'l.tenant_id',
            'search' => ['l.code', 'w.name'], 'where' => 'l.is_active = 1', 'order' => 'w.name, l.code', 'link' => 'locations', 'scoped' => 'l.warehouse_id'],
        'product' => ['label' => 'Product', 'from' => 'products p LEFT JOIN catalogues c ON c.id = p.catalogue_id', 'id' => 'p.id', 'label_sql' => 'p.name', 'sub' => "CONCAT_WS(' · ', c.name, p.code)", 'tenant' => 'p.tenant_id',
            'search' => ['p.name', 'p.code', 'c.name'], 'where' => 'p.is_active = 1', 'order' => 'p.name', 'link' => ''],
        'catalogue' => ['label' => 'Catalogue', 'from' => 'catalogues c', 'id' => 'c.id', 'label_sql' => 'c.name', 'sub' => "COALESCE(c.code, '')", 'tenant' => 'c.tenant_id',
            'search' => ['c.name', 'c.code'], 'where' => 'c.is_active = 1', 'order' => 'c.name', 'link' => 'catalogues'],
        'category' => ['label' => 'Category', 'from' => 'categories g', 'id' => 'g.id', 'label_sql' => 'g.name', 'sub' => "''", 'tenant' => 'g.tenant_id',
            'search' => ['g.name'], 'where' => '1', 'order' => 'g.name', 'link' => ''],
        'brand' => ['label' => 'Brand', 'from' => 'brands b', 'id' => 'b.id', 'label_sql' => 'b.name', 'sub' => "''", 'tenant' => 'b.tenant_id',
            'search' => ['b.name'], 'where' => '1', 'order' => 'b.name', 'link' => ''],
    ];

    /** key => label, for the designer's "Pick from" list. */
    public static function labels(): array
    {
        return array_map(fn($s) => $s['label'], self::SOURCES);
    }

    public static function valid(string $source): bool
    {
        return isset(self::SOURCES[$source]);
    }

    private static function select(array $s): string
    {
        return "SELECT {$s['id']} AS id, {$s['label_sql']} AS label, {$s['sub']} AS sub, " . ($s['link_id'] ?? $s['id']) . " AS link_id FROM {$s['from']} WHERE {$s['tenant']} = ?";
    }

    /** Records matching what was typed (name, code, phone …), best matches first. */
    public static function find(string $source, string $q, int $limit = 20): array
    {
        $s = self::SOURCES[$source] ?? null;
        if (!$s) return [];
        $sql = self::select($s) . " AND {$s['where']}" . (isset($s['scoped']) ? Scope::sql($s['scoped']) : '');
        $params = [(int)Auth::tenantId()];
        $q = trim($q);
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $sql .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $s['search'])) . ')';
            array_push($params, ...array_fill(0, count($s['search']), $like));
        }
        return DB::all($sql . " ORDER BY {$s['order']} LIMIT " . max(1, min(200, $limit)), $params);
    }

    /** One record by id (also an inactive one: a saved value must still show its name). */
    public static function one(string $source, int $id): ?array
    {
        $s = self::SOURCES[$source] ?? null;
        if (!$s || $id <= 0) return null;
        return DB::one(self::select($s) . " AND {$s['id']} = ?", [(int)Auth::tenantId(), $id]);
    }

    public static function label(string $source, int $id): string
    {
        $r = self::one($source, $id);
        return $r ? (string)$r['label'] : '';
    }

    /** The page of a picked record (relative path), or null when the source has no page. */
    public static function path(string $source, int $id): ?string
    {
        $s = self::SOURCES[$source] ?? null;
        if (!$s || ($s['link'] ?? '') === '') return null;
        $r = self::one($source, $id);
        return $r ? $s['link'] . '/' . (int)$r['link_id'] : null;
    }
}
