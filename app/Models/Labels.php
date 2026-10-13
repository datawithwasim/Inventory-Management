<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\Barcode;
use Core\DB;
use Core\Scope;
use Core\Settings;

/**
 * Barcode label designer: the data a label can show, the ready-made templates, saved templates,
 * and the HTML of a label (shared by the printout; the designer draws the same thing on screen).
 */
final class Labels
{
    /** key => [label, group, applies to ("items", "rolls" or "both"), sample shown while designing] */
    public const FIELDS = [
        'item_name' => ['Item name', 'Item', 'both', 'Royal Velvet Wine 54in'],
        'sku' => ['SKU', 'Item', 'both', 'RVW-054'],
        'barcode' => ['Barcode (if the item has one)', 'Item', 'both', '8901234567895'],
        'code' => ['Barcode, or the SKU when there is none', 'Item', 'items', '8901234567895'],
        'sale_price' => ['Sale price (with currency)', 'Item', 'both', '₹ 480.00'],
        'price' => ['Sale price (number only)', 'Item', 'both', '480.00'],
        'category' => ['Category', 'Item', 'both', 'Fabric'],
        'brand' => ['Brand', 'Item', 'both', 'Royal'],
        'unit' => ['Unit', 'Item', 'both', 'm'],
        'hsn_code' => ['HSN code', 'Item', 'both', '5407'],
        'tax_rate' => ['Tax rate', 'Item', 'both', '5%'],
        'item_type' => ['Product type', 'Item', 'both', 'Fabric'],
        'description' => ['Description', 'Item', 'both', 'Plain polyester velvet'],
        'supplier' => ['Supplier (preferred)', 'Supplier item', 'both', 'Surat Mills'],
        'supplier_code' => ['Supplier item code', 'Supplier item', 'both', 'RV-12-W'],
        'supplier_item_name' => ['Supplier item name', 'Supplier item', 'both', 'RV Wine 54'],
        'design_no' => ['Design / quality no.', 'Supplier item', 'both', 'RV-12'],
        'composition' => ['Composition', 'Supplier item', 'both', '100% polyester'],
        'width' => ['Width', 'Supplier item', 'both', '54 in'],
        'gsm' => ['GSM / weight', 'Supplier item', 'both', '280'],
        'pattern' => ['Pattern', 'Supplier item', 'both', 'Plain'],
        'finish' => ['Finish', 'Supplier item', 'both', 'Soft'],
        'roll_no' => ['Roll number (batch no.)', 'Roll', 'rolls', 'R-000123'],
        'supplier_lot' => ['Supplier lot', 'Roll', 'rolls', 'LOT-77'],
        'received_date' => ['Received on', 'Roll', 'rolls', '2026-10-12'],
        'roll_qty' => ['Quantity left on the roll, with unit', 'Roll', 'rolls', '50 m'],
        'roll_qty_num' => ['Quantity left (number only)', 'Roll', 'rolls', '50'],
        'received_qty' => ['Quantity when received', 'Roll', 'rolls', '50'],
        'rack' => ['Rack(s) the roll is on', 'Roll', 'rolls', 'A-07'],
        'warehouse' => ['Warehouse(s)', 'Roll', 'rolls', 'Main Warehouse'],
        'company' => ['Company name', 'General', 'both', 'Your Company'],
        'today' => ['Printed on (date)', 'General', 'both', '2026-10-12'],
    ];
    public const FONTS = [
        'Arial' => 'Arial, Helvetica, sans-serif', 'Verdana' => 'Verdana, Geneva, sans-serif', 'Tahoma' => 'Tahoma, Geneva, sans-serif', 'Trebuchet MS' => '"Trebuchet MS", Helvetica, sans-serif',
        'Times New Roman' => '"Times New Roman", Times, serif', 'Georgia' => 'Georgia, serif', 'Courier New' => '"Courier New", Courier, monospace', 'Impact' => 'Impact, "Arial Black", sans-serif',
    ];
    public const PAPERS = ['A4' => [210, 297], 'Letter' => [215.9, 279.4], 'A5' => [148, 210]];
    public const MAX_ELEMENTS = 40;

    /** Common label sizes and sheet layouts offered in the designer. */
    public const PRESETS = [
        ['key' => 'roll-50x25', 'name' => '50 × 25 mm — roll printer', 'w' => 50, 'h' => 25, 'page' => ['mode' => 'roll', 'cols' => 1]],
        ['key' => 'roll-38x25', 'name' => '38 × 25 mm — small roll', 'w' => 38, 'h' => 25, 'page' => ['mode' => 'roll', 'cols' => 1]],
        ['key' => 'roll-75x50', 'name' => '75 × 50 mm — roll', 'w' => 75, 'h' => 50, 'page' => ['mode' => 'roll', 'cols' => 1]],
        ['key' => 'roll-100x50', 'name' => '100 × 50 mm — thermal', 'w' => 100, 'h' => 50, 'page' => ['mode' => 'roll', 'cols' => 1]],
        ['key' => 'roll-100x150', 'name' => '100 × 150 mm — large thermal', 'w' => 100, 'h' => 150, 'page' => ['mode' => 'roll', 'cols' => 1]],
        ['key' => 'roll-2up-50x25', 'name' => '2 across, 50 × 25 mm roll', 'w' => 50, 'h' => 25, 'page' => ['mode' => 'roll', 'cols' => 2, 'gap_x' => 3]],
        ['key' => 'a4-65x33', 'name' => 'A4 sheet · 65 × 33 mm (3 × 8 = 24)', 'w' => 65, 'h' => 33, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 3, 'rows' => 8]],
        ['key' => 'a4-70x37', 'name' => 'A4 sheet · 70 × 37 mm (3 × 8 = 24)', 'w' => 70, 'h' => 37, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 3, 'rows' => 8]],
        ['key' => 'a4-63x38', 'name' => 'A4 sheet · 63.5 × 38.1 mm (3 × 7 = 21)', 'w' => 63.5, 'h' => 38.1, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 3, 'rows' => 7, 'margin_top' => 15.1, 'margin_left' => 7.2, 'gap_x' => 2.5]],
        ['key' => 'a4-99x38', 'name' => 'A4 sheet · 99.1 × 38.1 mm (2 × 7 = 14)', 'w' => 99.1, 'h' => 38.1, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 2, 'rows' => 7, 'margin_top' => 15.1, 'margin_left' => 4.65, 'gap_x' => 2.5]],
        ['key' => 'a4-99x57', 'name' => 'A4 sheet · 99.1 × 57 mm (2 × 5 = 10)', 'w' => 99.1, 'h' => 57, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 2, 'rows' => 5, 'margin_top' => 6, 'margin_left' => 4.65, 'gap_x' => 2.5]],
        ['key' => 'a4-46x25', 'name' => 'A4 sheet · 45.7 × 25.4 mm (4 × 10 = 40)', 'w' => 45.7, 'h' => 25.4, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 4, 'rows' => 10, 'margin_top' => 21.4, 'margin_left' => 9.8, 'gap_x' => 2.5]],
        ['key' => 'a4-38x21', 'name' => 'A4 sheet · 38.1 × 21.2 mm (5 × 13 = 65)', 'w' => 38.1, 'h' => 21.2, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 5, 'rows' => 13, 'margin_top' => 10.7, 'margin_left' => 4.7, 'gap_x' => 2.5]],
        ['key' => 'a4-105x74', 'name' => 'A4 sheet · 105 × 74 mm (2 × 4 = 8)', 'w' => 105, 'h' => 74, 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 2, 'rows' => 4]],
    ];

    private static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /* ------------------------------------------------------------ what a label can show */

    /** The resolved values of one item (variant) or roll (batch), or null when it is not the company's / not visible to the user. */
    public static function values(string $type, int $id): ?array
    {
        $t = self::tid();
        $dec = max(0, min(3, (int)Settings::get('currency.decimals')));
        if ($type === 'rolls') {
            $w = Scope::warehouse();
            $r = DB::one(
                'SELECT b.batch_no, b.supplier_lot, b.received_date, b.received_qty, v.id AS variant_id, v.sku, v.barcode, v.sale_price, i.name AS item_name, i.item_type, i.hsn_code, i.description,
                        c.name AS category, br.name AS brand, u.short_name AS unit, COALESCE(x.rate, 0) AS tax_rate
                 FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands br ON br.id = i.brand_id LEFT JOIN taxes x ON x.id = i.tax_id
                 WHERE b.tenant_id = ? AND b.id = ?', [$t, $id]);
            if (!$r) return null;
            if ($w !== null && !DB::val('SELECT 1 FROM stock_ledger WHERE batch_id = ? AND warehouse_id = ? LIMIT 1', [$id, $w])) return null;
            $where = $w !== null ? ' AND s.warehouse_id = ' . (int)$w : '';
            $bal = (float)DB::val("SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s WHERE s.batch_id = ? AND s.tenant_id = ?$where", [$id, $t]);
            $racks = (string)DB::val("SELECT GROUP_CONCAT(DISTINCT l.code ORDER BY l.code SEPARATOR ', ') FROM stock_balances s JOIN locations l ON l.id = s.location_id
                                      WHERE s.batch_id = ? AND s.tenant_id = ? AND s.qty > 0.0005 AND s.location_id > 0$where", [$id, $t]);
            $whs = (string)DB::val("SELECT GROUP_CONCAT(DISTINCT wh.name ORDER BY wh.name SEPARATOR ', ') FROM stock_balances s JOIN warehouses wh ON wh.id = s.warehouse_id
                                    WHERE s.batch_id = ? AND s.tenant_id = ? AND s.qty > 0.0005$where", [$id, $t]);
            $out = self::itemValues($r, (int)$r['variant_id'], $dec) + [
                'roll_no' => (string)$r['batch_no'], 'supplier_lot' => (string)($r['supplier_lot'] ?? ''), 'received_date' => $r['received_date'] ? fdate($r['received_date']) : '',
                'roll_qty' => qty($bal) . ' ' . $r['unit'], 'roll_qty_num' => qty($bal), 'received_qty' => qty($r['received_qty']), 'rack' => $racks, 'warehouse' => $whs,
            ];
            $out['code'] = (string)$r['batch_no'];
            return self::general($out);
        }
        $r = DB::one(
            'SELECT v.id AS variant_id, v.sku, v.barcode, v.sale_price, i.name AS item_name, i.item_type, i.hsn_code, i.description, c.name AS category, br.name AS brand, u.short_name AS unit, COALESCE(x.rate, 0) AS tax_rate
             FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands br ON br.id = i.brand_id LEFT JOIN taxes x ON x.id = i.tax_id
             WHERE v.tenant_id = ? AND v.id = ?', [$t, $id]);
        if (!$r) return null;
        return self::general(self::itemValues($r, (int)$r['variant_id'], $dec));
    }

    private static function itemValues(array $r, int $variantId, int $dec): array
    {
        $sp = DB::one(
            'SELECT p.supplier_name, p.supplier_code, p.design_no, p.composition, p.width, p.gsm, p.pattern, p.finish, s.name AS supplier
             FROM supplier_items p JOIN suppliers s ON s.id = p.supplier_id WHERE p.tenant_id = ? AND p.variant_id = ? AND p.is_active = 1
             ORDER BY p.is_preferred DESC, p.id LIMIT 1', [self::tid(), $variantId]) ?? [];
        $code = (string)($r['barcode'] ?? '') !== '' ? (string)$r['barcode'] : (string)$r['sku'];
        $types = \App\Models\ItemTypes::LABELS;
        return [
            'item_name' => (string)$r['item_name'], 'sku' => (string)$r['sku'], 'barcode' => (string)($r['barcode'] ?? ''), 'code' => $code,
            'sale_price' => money($r['sale_price']), 'price' => number_format((float)$r['sale_price'], $dec, '.', ''),
            'category' => (string)($r['category'] ?? ''), 'brand' => (string)($r['brand'] ?? ''), 'unit' => (string)$r['unit'], 'hsn_code' => (string)($r['hsn_code'] ?? ''),
            'tax_rate' => (float)$r['tax_rate'] > 0 ? rtrim(rtrim(number_format((float)$r['tax_rate'], 2, '.', ''), '0'), '.') . '%' : '',
            'item_type' => (string)($types[$r['item_type'] ?? ''] ?? ''), 'description' => mb_substr((string)($r['description'] ?? ''), 0, 120),
            'supplier' => (string)($sp['supplier'] ?? ''), 'supplier_code' => (string)($sp['supplier_code'] ?? ''), 'supplier_item_name' => (string)($sp['supplier_name'] ?? ''),
            'design_no' => (string)($sp['design_no'] ?? ''), 'composition' => (string)($sp['composition'] ?? ''), 'width' => (string)($sp['width'] ?? ''), 'gsm' => (string)($sp['gsm'] ?? ''),
            'pattern' => (string)($sp['pattern'] ?? ''), 'finish' => (string)($sp['finish'] ?? ''),
        ];
    }

    private static function general(array $v): array
    {
        return $v + ['company' => Settings::get('company.name', ''), 'today' => fdate(date('Y-m-d'))];
    }

    /** What the designer shows before a real item or roll is picked. */
    public static function sample(string $type): array
    {
        $out = [];
        foreach (self::FIELDS as $k => [, , $for, $sample]) $out[$k] = $sample;
        if ($type === 'rolls') $out['code'] = $out['roll_no'];
        return $out;
    }

    /** Fills {field} placeholders in a text. */
    public static function fill(string $text, array $values): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn($m) => (string)($values[$m[1]] ?? ''), $text) ?? $text;
    }

    /* ------------------------------------------------------------ templates */

    /** @return array<string,array> ready-made templates by key ("b:…") */
    public static function builtin(): array
    {
        $roll = fn(int $cols = 1, float $gap = 0) => ['mode' => 'roll', 'paper' => 'A4', 'paper_w' => 210, 'paper_h' => 297, 'cols' => $cols, 'rows' => 1, 'margin_top' => 0, 'margin_left' => 0, 'gap_x' => $gap, 'gap_y' => 0, 'outline' => false];
        $sheet = fn(int $cols, int $rows) => ['mode' => 'sheet', 'paper' => 'A4', 'paper_w' => 210, 'paper_h' => 297, 'cols' => $cols, 'rows' => $rows, 'margin_top' => null, 'margin_left' => null, 'gap_x' => 0, 'gap_y' => 0, 'outline' => false];
        $txt = fn(float $x, float $y, float $w, float $h, string $text, array $o = []) => $o + ['type' => 'text', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'text' => $text];
        $bar = fn(float $x, float $y, float $w, float $h, string $value, array $o = []) => $o + ['type' => 'barcode', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'sym' => 'code128', 'value' => $value, 'showtext' => true, 'tsize' => 6];
        $qr = fn(float $x, float $y, float $s, string $value) => ['type' => 'qr', 'x' => $x, 'y' => $y, 'w' => $s, 'h' => $s, 'value' => $value];
        $line = fn(float $x, float $y, float $w, float $h) => ['type' => 'box', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'border' => 0, 'fill' => '#000000'];
        $b = [];
        $b['b:item-50x25'] = ['name' => 'Item label · 50 × 25 mm', 'for' => 'items', 'w' => 50, 'h' => 25, 'page' => $roll(), 'elements' => [
            $txt(1.5, 1, 47, 7, '{item_name}', ['size' => 8, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(1.5, 8.3, 24, 5, '{sale_price}', ['size' => 11, 'bold' => true]),
            $txt(26, 9.2, 22.5, 4, '{sku}', ['size' => 6.5, 'align' => 'right', 'valign' => 'middle']),
            $bar(1.5, 13.8, 47, 10.5, '{code}'),
        ]];
        $b['b:item-38x25'] = ['name' => 'Item label · 38 × 25 mm', 'for' => 'items', 'w' => 38, 'h' => 25, 'page' => $roll(), 'elements' => [
            $txt(1.5, 1, 35, 6.5, '{item_name}', ['size' => 7, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(1.5, 7.8, 35, 4.5, '{sale_price}  ·  {sku}', ['size' => 7, 'bold' => true]),
            $bar(1.5, 12.8, 35, 11.5, '{code}', ['tsize' => 5.5]),
        ]];
        $b['b:item-sheet'] = ['name' => 'Item label · A4 sheet 65 × 33 mm (3 × 8)', 'for' => 'items', 'w' => 65, 'h' => 33, 'page' => $sheet(3, 8), 'elements' => [
            $txt(2, 1.5, 61, 8, '{item_name}', ['size' => 9, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(2, 10, 30, 6, '{sale_price}', ['size' => 13, 'bold' => true]),
            $txt(33, 10.5, 30, 5, '{sku}', ['size' => 8, 'align' => 'right', 'valign' => 'middle']),
            $bar(2, 17, 61, 14, '{code}', ['tsize' => 7]),
        ]];
        $b['b:roll-50x25'] = ['name' => 'Roll label · 50 × 25 mm', 'for' => 'rolls', 'w' => 50, 'h' => 25, 'page' => $roll(), 'elements' => [
            $txt(1.5, 1, 47, 6.5, '{item_name}', ['size' => 8, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(1.5, 7.6, 47, 4, '{roll_qty}  ·  Rack {rack}', ['size' => 7, 'bold' => true]),
            $bar(1.5, 12, 47, 11, '{roll_no}', ['tsize' => 6.5]),
            $txt(1.5, 22.6, 47, 2.4, 'Received {received_date}', ['size' => 5, 'align' => 'right']),
        ]];
        $b['b:roll-100x50'] = ['name' => 'Roll label · 100 × 50 mm with QR (thermal)', 'for' => 'rolls', 'w' => 100, 'h' => 50, 'page' => $roll(), 'elements' => [
            $txt(3, 2.5, 66, 11, '{item_name}', ['size' => 13, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(3, 14.5, 66, 5, 'Roll  {roll_no}', ['size' => 11, 'bold' => true]),
            $txt(3, 20.5, 66, 5, '{roll_qty}', ['size' => 16, 'bold' => true]),
            $txt(3, 27.5, 66, 4.5, 'Lot {supplier_lot}   ·   Rack {rack}', ['size' => 9]),
            $txt(3, 32, 66, 4, '{design_no}  {width}  {gsm} GSM', ['size' => 8]),
            $bar(3, 37, 66, 10.5, '{roll_no}', ['tsize' => 7]),
            $qr(72, 6, 25, '{roll_no}'),
            $txt(72, 32, 25, 4, 'Received {received_date}', ['size' => 6, 'align' => 'center']),
        ]];
        $b['b:shelf-tag'] = ['name' => 'Shelf price tag · 70 × 40 mm', 'for' => 'items', 'w' => 70, 'h' => 40, 'page' => $roll(), 'elements' => [
            $txt(3, 2, 64, 11, '{item_name}', ['size' => 12, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(3, 13.5, 64, 12, '{sale_price}', ['size' => 26, 'bold' => true, 'align' => 'center', 'valign' => 'middle']),
            $txt(3, 26, 64, 4, '{category}  ·  SKU {sku}', ['size' => 7, 'align' => 'center', 'color' => '#555555']),
            $bar(12, 30.5, 46, 8, '{code}', ['tsize' => 5.5]),
        ]];
        $b['b:swatch-card'] = ['name' => 'Fabric details tag · 60 × 40 mm with QR', 'for' => 'items', 'w' => 60, 'h' => 40, 'page' => $roll(), 'elements' => [
            $txt(2.5, 2, 36, 9, '{item_name}', ['size' => 10, 'bold' => true, 'maxlines' => 2, 'fit' => true]),
            $txt(2.5, 12, 36, 4, 'Design {design_no}', ['size' => 8, 'bold' => true]),
            $txt(2.5, 16.5, 36, 4, '{composition}', ['size' => 7.5]),
            $txt(2.5, 21, 36, 4, 'Width {width}  ·  {gsm} GSM', ['size' => 7.5]),
            $txt(2.5, 25.5, 36, 4, '{pattern}  {finish}', ['size' => 7.5]),
            $txt(2.5, 32, 36, 6, '{sale_price}', ['size' => 13, 'bold' => true]),
            $qr(38, 3, 20, '{sku}'),
            $txt(38, 24, 20, 4, '{sku}', ['size' => 6.5, 'align' => 'center']),
        ]];
        foreach ($b as $k => &$t) {
            $t['id'] = $k; $t['builtin'] = true; $t['is_default'] = false;
            foreach ($t['elements'] as $i => $e) $t['elements'][$i] = ['id' => 'e' . ($i + 1)] + (self::cleanElement(['id' => 'e' . ($i + 1)] + $e) ?? []);
        }
        unset($t);
        return $b;
    }

    /** Sanitised template array from a DB row. */
    private static function fromRow(array $r): array
    {
        $page = json_decode((string)$r['page'], true);
        $els = json_decode((string)$r['elements'], true);
        return ['id' => (int)$r['id'], 'name' => $r['name'], 'for' => $r['for_type'], 'w' => (float)$r['width_mm'], 'h' => (float)$r['height_mm'],
            'page' => self::cleanPage(is_array($page) ? $page : [], (float)$r['width_mm'], (float)$r['height_mm']),
            'elements' => array_values(array_filter(array_map(fn($e) => is_array($e) ? self::cleanElement($e) : null, is_array($els) ? $els : []))),
            'builtin' => false, 'is_default' => (bool)$r['is_default']];
    }

    /** Saved templates (newest edits first by name). */
    public static function saved(): array
    {
        return array_map([self::class, 'fromRow'], DB::all('SELECT * FROM label_templates WHERE tenant_id = ? ORDER BY name', [self::tid()]));
    }

    /** A template by id: a number for a saved one, "b:…" for a ready-made one. */
    public static function find(string $id): ?array
    {
        if (str_starts_with($id, 'b:')) return self::builtin()[$id] ?? null;
        if (!ctype_digit($id)) return null;
        $r = DB::one('SELECT * FROM label_templates WHERE tenant_id = ? AND id = ?', [self::tid(), (int)$id]);
        return $r ? self::fromRow($r) : null;
    }

    /** The template to open for items / rolls: the saved default for that kind, else a ready-made one. */
    public static function defaultFor(string $type): array
    {
        $r = DB::one("SELECT * FROM label_templates WHERE tenant_id = ? AND is_default = 1 AND for_type IN (?, 'any') ORDER BY for_type = ? DESC, id LIMIT 1", [self::tid(), $type, $type]);
        if ($r) return self::fromRow($r);
        return self::builtin()[$type === 'rolls' ? 'b:roll-50x25' : 'b:item-50x25'];
    }

    /** Every template the user can pick (saved ones first), in full: the picker draws thumbnails from the elements. */
    public static function summaries(): array
    {
        $out = [];
        foreach (array_merge(self::saved(), array_values(self::builtin())) as $t) $out[] = ['id' => (string)$t['id']] + $t;
        return $out;
    }

    /* ------------------------------------------------------------ sanitising what comes from the designer */

    private static function num(mixed $v, float $min, float $max, float $default): float
    {
        return is_numeric($v) ? round(max($min, min($max, (float)$v)), 2) : $default;
    }

    private static function color(mixed $v, string $default): string
    {
        $v = (string)$v;
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $default;
    }

    public static function cleanPage(array $p, float $w, float $h): array
    {
        $mode = ($p['mode'] ?? 'roll') === 'sheet' ? 'sheet' : 'roll';
        $paper = isset(self::PAPERS[$p['paper'] ?? '']) ? $p['paper'] : (($p['paper'] ?? '') === 'custom' ? 'custom' : 'A4');
        [$pw, $ph] = $paper === 'custom' ? [self::num($p['paper_w'] ?? 210, 20, 1000, 210), self::num($p['paper_h'] ?? 297, 20, 1000, 297)] : self::PAPERS[$paper];
        $cols = (int)self::num($p['cols'] ?? 1, 1, 20, 1);
        $rows = $mode === 'roll' ? 1 : (int)self::num($p['rows'] ?? 1, 1, 60, 1);
        $gx = self::num($p['gap_x'] ?? 0, 0, 100, 0);
        $gy = self::num($p['gap_y'] ?? 0, 0, 100, 0);
        // blank margins mean "centre the grid on the page"
        $mt = ($p['margin_top'] ?? null) === null || $p['margin_top'] === '' ? null : self::num($p['margin_top'], 0, 200, 0);
        $ml = ($p['margin_left'] ?? null) === null || $p['margin_left'] === '' ? null : self::num($p['margin_left'], 0, 200, 0);
        return ['mode' => $mode, 'paper' => $paper, 'paper_w' => $pw, 'paper_h' => $ph, 'cols' => $cols, 'rows' => $rows, 'margin_top' => $mt, 'margin_left' => $ml,
            'gap_x' => $gx, 'gap_y' => $gy, 'outline' => !empty($p['outline'])];
    }

    /** One element, cleaned; null when it is not a known kind. */
    public static function cleanElement(array $e): ?array
    {
        $type = $e['type'] ?? '';
        if (!in_array($type, ['text', 'barcode', 'qr', 'box', 'image'], true)) return null;
        $o = ['id' => preg_replace('/[^\w-]/', '', (string)($e['id'] ?? '')) ?: ('e' . substr(md5(json_encode($e)), 0, 6)), 'type' => $type,
            'x' => self::num($e['x'] ?? 0, -100, 1000, 0), 'y' => self::num($e['y'] ?? 0, -100, 1000, 0), 'w' => self::num($e['w'] ?? 10, 0.5, 1000, 10), 'h' => self::num($e['h'] ?? 5, 0.5, 1000, 5),
            'rot' => in_array($rot = (int)($e['rot'] ?? 0), [0, 90, 180, 270], true) ? $rot : 0];
        if ($type === 'text') {
            $font = isset(self::FONTS[$e['font'] ?? '']) ? $e['font'] : 'Arial';
            $o += ['text' => mb_substr((string)($e['text'] ?? ''), 0, 300), 'font' => $font, 'size' => self::num($e['size'] ?? 8, 3, 120, 8), 'bold' => !empty($e['bold']), 'italic' => !empty($e['italic']),
                'underline' => !empty($e['underline']), 'upper' => !empty($e['upper']), 'align' => in_array($e['align'] ?? '', ['left', 'center', 'right'], true) ? $e['align'] : 'left',
                'valign' => in_array($e['valign'] ?? '', ['top', 'middle', 'bottom'], true) ? $e['valign'] : 'top', 'color' => self::color($e['color'] ?? '', '#000000'),
                'bg' => ($e['bg'] ?? '') === '' ? '' : self::color($e['bg'], ''), 'lh' => self::num($e['lh'] ?? 1.1, 0.8, 3, 1.1), 'ls' => self::num($e['ls'] ?? 0, -1, 5, 0),
                'maxlines' => (int)self::num($e['maxlines'] ?? 0, 0, 20, 0), 'fit' => !empty($e['fit'])];
        } elseif ($type === 'barcode') {
            $o += ['sym' => isset(Barcode::SYMBOLS[$e['sym'] ?? '']) && ($e['sym'] ?? '') !== 'qr' ? $e['sym'] : 'code128', 'value' => mb_substr((string)($e['value'] ?? '{code}'), 0, 200),
                'showtext' => !empty($e['showtext']), 'tsize' => self::num($e['tsize'] ?? 6, 3, 40, 6), 'font' => isset(self::FONTS[$e['font'] ?? '']) ? $e['font'] : 'Arial', 'color' => self::color($e['color'] ?? '', '#000000')];
        } elseif ($type === 'qr') {
            $o += ['value' => mb_substr((string)($e['value'] ?? '{code}'), 0, 200), 'ecc' => ($e['ecc'] ?? 'M') === 'L' ? 'L' : 'M'];
        } elseif ($type === 'box') {
            $o += ['border' => self::num($e['border'] ?? 0.3, 0, 5, 0.3), 'color' => self::color($e['color'] ?? '', '#000000'), 'fill' => ($e['fill'] ?? '') === '' ? '' : self::color($e['fill'], ''), 'radius' => self::num($e['radius'] ?? 0, 0, 50, 0)];
        } else {
            $o += ['src' => 'logo'];
        }
        return $o;
    }

    /** Validates a posted template. @return array{0:?array,1:string[]} */
    public static function clean(array $in): array
    {
        $errors = [];
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Give the template a name (max 100 characters).';
        $w = is_numeric($in['w'] ?? null) ? (float)$in['w'] : 0.0;
        $h = is_numeric($in['h'] ?? null) ? (float)$in['h'] : 0.0;
        if ($w < 10 || $w > 300 || $h < 8 || $h > 300) $errors[] = 'The label must be between 10 and 300 mm wide and 8 and 300 mm high.';
        $for = in_array($in['for'] ?? '', ['items', 'rolls', 'any'], true) ? $in['for'] : 'any';
        $els = [];
        foreach ((array)($in['elements'] ?? []) as $e) {
            if (!is_array($e)) continue;
            $c = self::cleanElement($e);
            if ($c) $els[] = $c;
        }
        if (count($els) > self::MAX_ELEMENTS) $errors[] = 'A label can hold at most ' . self::MAX_ELEMENTS . ' elements.';
        $page = self::cleanPage((array)($in['page'] ?? []), $w, $h);
        if ($page['mode'] === 'sheet') {
            [$pw, $ph] = [$page['paper_w'], $page['paper_h']];
            $gw = $page['cols'] * $w + ($page['cols'] - 1) * $page['gap_x'] + 2 * ($page['margin_left'] ?? 0);
            $gh = $page['rows'] * $h + ($page['rows'] - 1) * $page['gap_y'] + 2 * ($page['margin_top'] ?? 0);
            if ($gw > $pw + 0.5 || $gh > $ph + 0.5) $errors[] = "The labels do not fit on the paper: {$page['cols']} × {$page['rows']} labels need " . round($gw, 1) . ' × ' . round($gh, 1) . " mm but the paper is $pw × $ph mm.";
        }
        if ($errors) return [null, $errors];
        return [['name' => $name, 'for' => $for, 'w' => round($w, 2), 'h' => round($h, 2), 'page' => $page, 'elements' => $els], []];
    }

    /* ------------------------------------------------------------ HTML of a label (printout) */

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function mm(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . 'mm';
    }

    /** One element as an absolutely placed block, with its text / barcode filled from $values. */
    public static function elementHtml(array $e, array $values): string
    {
        $pos = 'left:' . self::mm($e['x']) . ';top:' . self::mm($e['y']) . ';width:' . self::mm($e['w']) . ';height:' . self::mm($e['h']) . ($e['rot'] ? ';transform:rotate(' . $e['rot'] . 'deg)' : '');
        switch ($e['type']) {
            case 'text':
                $text = self::fill($e['text'], $values);
                if ($e['upper']) $text = mb_strtoupper($text);
                if (trim($text) === '') return '';
                $st = $pos . ';font-family:' . self::FONTS[$e['font']] . ';font-size:' . $e['size'] . 'pt;line-height:' . $e['lh'] . ';color:' . $e['color'] . ';text-align:' . $e['align']
                    . ($e['bold'] ? ';font-weight:700' : '') . ($e['italic'] ? ';font-style:italic' : '') . ($e['underline'] ? ';text-decoration:underline' : '')
                    . ($e['bg'] !== '' ? ';background:' . $e['bg'] : '') . ($e['ls'] ? ';letter-spacing:' . $e['ls'] . 'pt' : '')
                    . ';justify-content:' . ($e['valign'] === 'middle' ? 'center' : ($e['valign'] === 'bottom' ? 'flex-end' : 'flex-start'));
                $clamp = $e['maxlines'] > 0 && !$e['fit'] ? ';-webkit-line-clamp:' . $e['maxlines'] . ';display:-webkit-box;-webkit-box-orient:vertical' : '';
                return '<div class="el t' . ($e['fit'] ? ' fit' : '') . '" style="' . $st . '"><span style="width:100%;overflow:hidden' . $clamp . '">' . nl2br(self::e($text)) . '</span></div>';
            case 'barcode':
            case 'qr':
                $sym = $e['type'] === 'qr' ? 'qr' : $e['sym'];
                $val = self::fill($e['value'], $values);
                $ok = Barcode::prepare($sym, $val);
                if ($val === '' || trim($val) === '') return '';
                $svg = Barcode::svg($sym, $val, 40, $e['ecc'] ?? 'M');
                $txt = '';
                if ($e['type'] === 'barcode' && $e['showtext'] && $ok !== null) $txt = '<div class="bct" style="font-size:' . $e['tsize'] . 'pt;font-family:' . self::FONTS[$e['font']] . ';color:' . $e['color'] . '">' . self::e($ok) . '</div>';
                return '<div class="el bcw" style="' . $pos . '"><div class="bcs">' . $svg . '</div>' . $txt . '</div>';
            case 'box':
                return '<div class="el" style="' . $pos . ';box-sizing:border-box;border:' . self::mm($e['border']) . ' solid ' . $e['color'] . ($e['fill'] !== '' ? ';background:' . $e['fill'] : '') . ($e['radius'] ? ';border-radius:' . self::mm($e['radius']) : '') . '"></div>';
            default:
                $logo = company_logo_url();
                return $logo ? '<div class="el" style="' . $pos . '"><img src="' . self::e($logo) . '" alt="" style="width:100%;height:100%;object-fit:contain"></div>' : '';
        }
    }

    public static function labelHtml(array $tpl, array $values): string
    {
        $out = '';
        foreach ($tpl['elements'] as $e) $out .= self::elementHtml($e, $values);
        return $out;
    }
}
