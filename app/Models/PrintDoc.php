<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/**
 * Designed print layouts. A layout (config) says how a printed document looks: the paper, the letterhead, and the ordered
 * sections (parties, items table, totals, notes, bank, terms, own text, signatures, footer) with their options.
 * The app draws the document from the config; this class only keeps, checks and lists the configs.
 * The built-in "Standard" layout follows the older simple options (Settings → template.*), so nobody loses what they had.
 */
final class PrintDoc
{
    public const MAX_PER_DOC = 20;
    public const STD = 'std';

    public const SIZES = ['A4' => [210, 297], 'A5' => [148, 210], 'A6' => [105, 148], 'Letter' => [216, 279], 'Legal' => [216, 356]];
    public const FONTS = ['Arial', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Times New Roman', 'Georgia', 'Courier New'];
    public const TABLE_STYLES = ['lines', 'grid', 'plain'];

    /** What each document can show. columns: key => [label, align, on by default]; rows: key => [label, on]. */
    public const SPEC = [
        'invoice' => [
            'title' => 'Tax invoice',
            'meta' => ['due' => 'Due date', 'order' => 'Order no.'],
            'parties' => ['billed_to' => 'Billed to', 'ship_to' => 'Deliver to'],
            'columns' => ['item' => ['Item', 'left', 1], 'sku' => ['SKU', 'left', 0], 'hsn' => ['HSN', 'left', 0], 'roll' => ['Roll', 'left', 1], 'qty' => ['Qty', 'right', 1], 'price' => ['Price', 'right', 1],
                'disc' => ['Disc', 'right', 1], 'tax' => ['Tax', 'right', 1], 'amount' => ['Amount', 'right', 1]],
            'totals' => ['gross' => ['Gross', 1], 'discount' => ['Discount', 1], 'tax' => ['Tax', 1], 'delivery' => ['Delivery', 1], 'installation' => ['Installation', 1], 'total' => ['Total', 1],
                'returns' => ['Returns', 1], 'paid' => ['Paid', 1], 'balance' => ['Balance due', 1]],
        ],
        'delivery_note' => [
            'title' => 'Delivery note',
            'meta' => ['order' => 'Order no.', 'invoice' => 'Invoice no.'],
            'parties' => ['delivered_to' => 'Delivered to'],
            'columns' => ['item' => ['Item', 'left', 1], 'sku' => ['SKU', 'left', 0], 'hsn' => ['HSN', 'left', 0], 'roll' => ['Roll', 'left', 1], 'rack' => ['Rack', 'left', 0], 'qty' => ['Qty', 'right', 1], 'amount' => ['Amount', 'right', 0]],
            'totals' => [],
        ],
        'purchase_order' => [
            'title' => 'Purchase order',
            'meta' => ['expected' => 'Expected delivery'],
            'parties' => ['supplier' => 'Supplier', 'deliver_to' => 'Deliver to'],
            'columns' => ['item' => ['Item', 'left', 1], 'sku' => ['SKU', 'left', 0], 'hsn' => ['HSN', 'left', 0], 'their' => ['Supplier code / name', 'left', 0], 'qty' => ['Qty', 'right', 1], 'price' => ['Price', 'right', 1],
                'tax' => ['Tax %', 'right', 1], 'amount' => ['Amount', 'right', 1]],
            'totals' => ['subtotal' => ['Subtotal', 1], 'tax' => ['Tax', 1], 'total' => ['Total', 1]],
        ],
    ];

    private const HEX = '/^#[0-9a-fA-F]{6}$/';

    /* ---------------- defaults ---------------- */

    public static function defaults(string $doc): array
    {
        $s = self::SPEC[$doc];
        $cols = [];
        foreach ($s['columns'] as $k => [$label, $align, $on]) $cols[] = ['k' => $k, 'label' => $label, 'show' => $on, 'align' => $align, 'w' => 0];
        $rows = [];
        foreach ($s['totals'] as $k => [$label, $on]) $rows[] = ['k' => $k, 'label' => $label, 'show' => $on];
        $parties = [];
        foreach ($s['parties'] as $k => $label) $parties[] = ['k' => $k, 'label' => $label, 'show' => 1];
        $blocks = [
            ['id' => 'parties', 'type' => 'parties', 'show' => 1, 'cols' => 2, 'items' => $parties],
            ['id' => 'items', 'type' => 'items', 'show' => 1, 'columns' => $cols, 'style' => 'lines', 'head_bg' => 'light', 'zebra' => 0, 'row_no' => 0, 'compact' => 0],
        ];
        if ($rows) $blocks[] = ['id' => 'totals', 'type' => 'totals', 'show' => 1, 'rows' => $rows, 'width' => 45, 'side' => 'right', 'words' => 0, 'words_prefix' => 'Rupees'];
        $blocks[] = ['id' => 'notes', 'type' => 'notes', 'show' => 1, 'title' => 'Note'];
        $blocks[] = ['id' => 'bank', 'type' => 'bank', 'show' => 0, 'title' => 'Bank details'];
        $blocks[] = ['id' => 'terms', 'type' => 'terms', 'show' => 1, 'title' => 'Terms & conditions', 'text' => ''];
        $blocks[] = ['id' => 'signature', 'type' => 'signature', 'show' => 1, 'boxes' => ['Authorised signatory'], 'space' => 18];
        $blocks[] = ['id' => 'footer', 'type' => 'footer', 'show' => 1, 'text' => '', 'align' => 'center'];
        return [
            'v' => 2,
            'page' => ['size' => 'A4', 'orient' => 'portrait', 'margin' => 12, 'font' => 'Arial', 'fsize' => 10, 'color' => '#111111', 'accent' => '#1f2937', 'wm_text' => '', 'wm_opacity' => 8],
            'header' => ['logo' => 'left', 'logo_h' => 18, 'layout' => 'split', 'name_size' => 16, 'show_address' => 1, 'show_phone' => 1, 'show_email' => 1, 'show_website' => 1, 'show_tax' => 1,
                'title' => $s['title'], 'title_size' => 20, 'title_color' => 'accent', 'rule' => 'solid', 'rule_w' => 3, 'meta' => array_keys($s['meta'])],
            'blocks' => $blocks,
        ];
    }

    /** The Standard layout: the defaults, with whatever the company chose in the older simple options laid over them. */
    public static function standard(string $doc): array
    {
        $c = self::defaults($doc);
        $old = PrintTemplate::get($doc);
        $c['page']['accent'] = preg_match(self::HEX, (string)($old['accent'] ?? '')) ? $old['accent'] : $c['page']['accent'];
        $c['header']['logo'] = !empty($old['show_logo']) ? 'left' : 'none';
        if (empty($old['show_company'])) foreach (['show_address', 'show_phone', 'show_email', 'show_website', 'show_tax'] as $k) $c['header'][$k] = 0;
        if (($old['title'] ?? '') !== '') $c['header']['title'] = (string)$old['title'];
        $flag = [
            'invoice' => ['disc' => 'show_discount', 'tax' => 'show_tax', 'roll' => 'show_batch'],
            'delivery_note' => ['amount' => 'show_prices', 'roll' => 'show_batch', 'rack' => 'show_rack'],
            'purchase_order' => ['tax' => 'show_tax'],
        ][$doc] ?? [];
        foreach ($c['blocks'] as &$b) {
            if ($b['type'] === 'items') foreach ($b['columns'] as &$col) if (isset($flag[$col['k']])) $col['show'] = !empty($old[$flag[$col['k']]]) ? 1 : 0;
            unset($col);
            if ($b['type'] === 'parties') foreach ($b['items'] as &$p) if ($p['k'] === 'ship_to' || $p['k'] === 'delivered_to') $p['show'] = isset($old['show_ship_to']) ? (empty($old['show_ship_to']) ? 0 : 1) : 1;
            unset($p);
            if ($b['type'] === 'bank') $b['show'] = !empty($old['show_bank']) ? 1 : 0;
            if ($b['type'] === 'terms') { $b['text'] = (string)($old['terms'] ?? ''); $b['show'] = $b['text'] !== '' ? 1 : 0; }
            if ($b['type'] === 'footer') { $b['text'] = (string)($old['footer'] ?? ''); $b['show'] = $b['text'] !== '' ? 1 : 0; }
            if ($b['type'] === 'signature') { $b['show'] = !empty($old['signature']) ? 1 : 0; $b['boxes'] = [(string)(($old['signature_label'] ?? '') ?: 'Authorised signatory')]; }
        }
        unset($b);
        if ($doc === 'purchase_order' && empty($old['show_expected'])) $c['header']['meta'] = [];
        return $c;
    }

    /* ---------------- checking ---------------- */

    private static function bool(mixed $v): int { return !empty($v) && $v !== '0' && $v !== 'false' ? 1 : 0; }
    private static function num(mixed $v, float $min, float $max, float $def): float { return is_numeric($v) ? max($min, min($max, (float)$v)) : $def; }
    private static function str(mixed $v, int $max): string { return mb_substr(trim((string)(is_scalar($v) ? $v : '')), 0, $max); }
    private static function pick(mixed $v, array $allowed, string $def): string { return is_string($v) && in_array($v, $allowed, true) ? $v : $def; }
    private static function hex(mixed $v, string $def): string { return is_string($v) && preg_match(self::HEX, $v) ? strtolower($v) : $def; }
    /** Texts keep their line breaks. */
    private static function text(mixed $v, int $max): string { return mb_substr(str_replace("\r", '', trim((string)(is_scalar($v) ? $v : ''))), 0, $max); }

    /** Any posted or stored config, made safe and complete: unknown things dropped, missing things filled with defaults. */
    public static function clean(string $doc, mixed $in): array
    {
        $d = self::defaults($doc); $s = self::SPEC[$doc];
        $in = is_array($in) ? $in : [];
        $p = is_array($in['page'] ?? null) ? $in['page'] : []; $dp = $d['page'];
        $page = ['size' => self::pick($p['size'] ?? '', array_keys(self::SIZES), $dp['size']), 'orient' => self::pick($p['orient'] ?? '', ['portrait', 'landscape'], 'portrait'),
            'margin' => round(self::num($p['margin'] ?? null, 0, 40, $dp['margin']), 1), 'font' => self::pick($p['font'] ?? '', self::FONTS, $dp['font']), 'fsize' => round(self::num($p['fsize'] ?? null, 7, 16, $dp['fsize']), 1),
            'color' => self::hex($p['color'] ?? '', $dp['color']), 'accent' => self::hex($p['accent'] ?? '', $dp['accent']), 'wm_text' => self::str($p['wm_text'] ?? '', 24), 'wm_opacity' => (int)self::num($p['wm_opacity'] ?? null, 2, 30, $dp['wm_opacity'])];
        $h = is_array($in['header'] ?? null) ? $in['header'] : []; $dh = $d['header'];
        $meta = [];
        foreach ((array)($h['meta'] ?? $dh['meta']) as $k) if (is_string($k) && isset($s['meta'][$k]) && !in_array($k, $meta, true)) $meta[] = $k;
        $header = ['logo' => self::pick($h['logo'] ?? '', ['left', 'right', 'none'], $dh['logo']), 'logo_h' => (int)self::num($h['logo_h'] ?? null, 8, 40, $dh['logo_h']), 'layout' => self::pick($h['layout'] ?? '', ['split', 'center'], 'split'),
            'name_size' => (int)self::num($h['name_size'] ?? null, 10, 30, $dh['name_size']), 'title' => self::str($h['title'] ?? $dh['title'], 60), 'title_size' => (int)self::num($h['title_size'] ?? null, 10, 40, $dh['title_size']),
            'title_color' => (($h['title_color'] ?? '') === 'accent' || !isset($h['title_color'])) ? 'accent' : self::hex($h['title_color'], 'accent'),
            'rule' => self::pick($h['rule'] ?? '', ['solid', 'double', 'none'], 'solid'), 'rule_w' => (int)self::num($h['rule_w'] ?? null, 1, 6, $dh['rule_w']), 'meta' => $meta];
        foreach (['show_address', 'show_phone', 'show_email', 'show_website', 'show_tax'] as $k) $header[$k] = array_key_exists($k, $h) ? self::bool($h[$k]) : $dh[$k];

        // sections: those sent, in the order sent, then any the document has that were not sent
        $byId = []; $customN = 0;
        $order = [];
        foreach ((array)($in['blocks'] ?? []) as $b) {
            if (!is_array($b) || !is_string($b['id'] ?? null)) continue;
            $id = $b['id'];
            if (isset($byId[$id])) continue;
            if (str_starts_with($id, 'text:')) { if (++$customN > 8 || !preg_match('/^text:[a-z0-9]{1,12}$/', $id)) continue; }
            elseif (!self::hasBlock($d, $id)) continue;
            $byId[$id] = $b; $order[] = $id;
        }
        foreach ($d['blocks'] as $b) if (!isset($byId[$b['id']])) { $byId[$b['id']] = $b; $order[] = $b['id']; }
        $blocks = [];
        foreach ($order as $id) $blocks[] = str_starts_with($id, 'text:') ? self::cleanText($byId[$id]) : self::cleanBlock($d, $byId[$id]);
        return ['v' => 2, 'page' => $page, 'header' => $header, 'blocks' => $blocks];
    }

    private static function hasBlock(array $d, string $id): bool { foreach ($d['blocks'] as $b) if ($b['id'] === $id) return true; return false; }
    private static function defaultBlock(array $d, string $id): array { foreach ($d['blocks'] as $b) if ($b['id'] === $id) return $b; return []; }

    private static function cleanText(array $b): array
    {
        return ['id' => $b['id'], 'type' => 'text', 'show' => self::bool($b['show'] ?? 1), 'title' => self::str($b['title'] ?? '', 60), 'text' => self::text($b['text'] ?? '', 1000),
            'align' => self::pick($b['align'] ?? '', ['left', 'center', 'right'], 'left'), 'boxed' => self::bool($b['boxed'] ?? 0), 'size' => (int)self::num($b['size'] ?? null, 7, 20, 10)];
    }

    private static function cleanBlock(array $d, array $b): array
    {
        $id = $b['id']; $def = self::defaultBlock($d, $id); $type = $def['type'];
        $out = ['id' => $id, 'type' => $type, 'show' => array_key_exists('show', $b) ? self::bool($b['show']) : $def['show']];
        // lists: keep the order and the labels sent, ignore keys the document does not have, add the missing ones at the end
        $list = function (string $field, array $items, array $allow, callable $one) use ($def): array {
            $seen = []; $res = [];
            foreach ($items as $x) {
                if (!is_array($x) || !is_string($x['k'] ?? null) || !isset($allow[$x['k']]) || isset($seen[$x['k']])) continue;
                $seen[$x['k']] = 1; $res[] = $one($x, $allow[$x['k']]);
            }
            foreach ($def[$field] as $x) if (!isset($seen[$x['k']])) $res[] = $x;
            return $res;
        };
        switch ($type) {
            case 'parties':
                $out['cols'] = (int)self::pick((string)($b['cols'] ?? ''), ['1', '2'], '2');
                $allow = []; foreach ($def['items'] as $x) $allow[$x['k']] = $x;
                $out['items'] = $list('items', (array)($b['items'] ?? []), $allow, fn($x, $dx) => ['k' => $x['k'], 'label' => self::str($x['label'] ?? '', 40) ?: $dx['label'], 'show' => self::bool($x['show'] ?? 1)]);
                break;
            case 'items':
                $allow = []; foreach ($def['columns'] as $x) $allow[$x['k']] = $x;
                $out['columns'] = $list('columns', (array)($b['columns'] ?? []), $allow, fn($x, $dx) => ['k' => $x['k'], 'label' => self::str($x['label'] ?? '', 40) ?: $dx['label'], 'show' => self::bool($x['show'] ?? 1),
                    'align' => self::pick($x['align'] ?? '', ['left', 'center', 'right'], $dx['align']), 'w' => (int)self::num($x['w'] ?? null, 0, 60, 0)]);
                if (!array_filter($out['columns'], fn($c) => $c['show'])) $out['columns'][0]['show'] = 1;   // a table needs a column
                $out['style'] = self::pick($b['style'] ?? '', self::TABLE_STYLES, 'lines'); $out['head_bg'] = self::pick($b['head_bg'] ?? '', ['accent', 'light', 'none'], 'light');
                foreach (['zebra', 'row_no', 'compact'] as $k) $out[$k] = self::bool($b[$k] ?? 0);
                break;
            case 'totals':
                $allow = []; foreach ($def['rows'] as $x) $allow[$x['k']] = $x;
                $out['rows'] = $list('rows', (array)($b['rows'] ?? []), $allow, fn($x, $dx) => ['k' => $x['k'], 'label' => self::str($x['label'] ?? '', 40) ?: $dx['label'], 'show' => self::bool($x['show'] ?? 1)]);
                $out['width'] = (int)self::num($b['width'] ?? null, 25, 100, 45); $out['side'] = self::pick($b['side'] ?? '', ['left', 'right'], 'right');
                $out['words'] = self::bool($b['words'] ?? 0); $out['words_prefix'] = self::str($b['words_prefix'] ?? 'Rupees', 20);
                break;
            case 'notes': case 'bank':
                $out['title'] = self::str($b['title'] ?? $def['title'], 60);
                break;
            case 'terms':
                $out['title'] = self::str($b['title'] ?? $def['title'], 60); $out['text'] = self::text($b['text'] ?? '', 2000);
                break;
            case 'signature':
                $boxes = [];
                foreach ((array)($b['boxes'] ?? []) as $x) { $x = self::str($x, 40); if ($x !== '' && count($boxes) < 4) $boxes[] = $x; }
                $out['boxes'] = $boxes ?: ['Authorised signatory']; $out['space'] = (int)self::num($b['space'] ?? null, 6, 50, 18);
                break;
            case 'footer':
                $out['text'] = self::text($b['text'] ?? '', 300); $out['align'] = self::pick($b['align'] ?? '', ['left', 'center', 'right'], 'center');
                break;
        }
        return $out;
    }

    /* ---------------- keeping and listing ---------------- */

    private static function tid(): int { return (int)Auth::tenantId(); }

    /** Latest document of this kind, so a layout can be tried with real data. */
    public static function latestId(string $doc): ?int
    {
        $t = self::tid();
        $id = match ($doc) {
            'invoice' => DB::val('SELECT MAX(id) FROM sales_invoices WHERE tenant_id = ?', [$t]),
            'delivery_note' => DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$t]),
            'purchase_order' => DB::val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$t]),
            default => null,
        };
        return $id ? (int)$id : null;
    }

    /** Every layout of a document: Standard first, then the company's own. */
    public static function list(string $doc): array
    {
        $rows = DB::all('SELECT id, name, is_default, updated_at FROM print_templates WHERE tenant_id = ? AND doc = ? ORDER BY name', [self::tid(), $doc]);
        $anyDefault = (bool)array_filter($rows, fn($r) => (int)$r['is_default'] === 1);
        $out = [['id' => self::STD, 'name' => 'Standard', 'builtin' => true, 'is_default' => !$anyDefault]];
        foreach ($rows as $r) $out[] = ['id' => (string)$r['id'], 'name' => $r['name'], 'builtin' => false, 'is_default' => (int)$r['is_default'] === 1, 'updated_at' => $r['updated_at']];
        return $out;
    }

    public static function find(string $doc, ?string $id): ?array
    {
        if ($id === null || $id === '' || $id === self::STD) return ['id' => self::STD, 'name' => 'Standard', 'builtin' => true, 'cfg' => self::standard($doc)];
        $r = DB::one('SELECT * FROM print_templates WHERE tenant_id = ? AND doc = ? AND id = ?', [self::tid(), $doc, (int)$id]);
        return $r ? ['id' => (string)$r['id'], 'name' => $r['name'], 'builtin' => false, 'cfg' => self::clean($doc, json_decode((string)$r['config'], true))] : null;
    }

    /** The layout to print with: the one asked for, else the company's default, else Standard. Plus the list, for the "template" switch. */
    public static function forPrint(string $doc, ?string $id): array
    {
        $found = ($id !== null && $id !== '') ? self::find($doc, $id) : null;
        if (!$found) {
            $d = DB::val('SELECT id FROM print_templates WHERE tenant_id = ? AND doc = ? AND is_default = 1 LIMIT 1', [self::tid(), $doc]);
            $found = self::find($doc, $d ? (string)$d : self::STD);
        }
        return ['id' => $found['id'], 'name' => $found['name'], 'cfg' => $found['cfg'], 'list' => array_map(fn($x) => ['id' => $x['id'], 'name' => $x['name'], 'is_default' => $x['is_default']], self::list($doc))];
    }
}
