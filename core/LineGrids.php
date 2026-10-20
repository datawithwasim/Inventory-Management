<?php
declare(strict_types=1);

namespace Core;

/**
 * The columns of the items table of a document, as data: which columns exist, their default names, which cannot be switched off,
 * and what the company chose (show / hide, own names, order, saved with the form layout). The React tables draw themselves from this.
 * Names may use {item} {batch} {rack} {supplier} (the company's own words) and {rack_l} (the same in lower case).
 */
final class LineGrids
{
    /** entity => key => [default name, cannot be hidden]. The order here is the default order. */
    public const GRIDS = [
        'purchase_order' => ['item' => ['{item}', true], 'qty' => ['Qty', true], 'price' => ['Unit price'], 'tax' => ['Tax %'], 'total' => ['Line total']],
        'sales_order' => ['item' => ['Product', true], 'qty' => ['Qty', true], 'price' => ['Price'], 'disc' => ['Disc %'], 'tax' => ['Tax %'], 'total' => ['Total']],
        'grn' => ['item' => ['{item}', true], 'billed' => ['Billed / due'], 'rolls' => ['Rolls received', true], 'received' => ['Received'], 'rack' => ['{rack}'], 'price' => ['Unit price'], 'tax' => ['Tax %']],
        'adjustment' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'rack' => ['{rack}'], 'qty' => ['Qty change (− to remove)', true], 'cost' => ['Unit cost']],
        'transfer' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'from_rack' => ['From {rack_l}'], 'to_rack' => ['To {rack_l}'], 'qty' => ['Qty to move', true]],
    ];

    /** The same, for the items table on the document's detail page (what was ordered / received / delivered). */
    public const VIEWS = [
        'purchase_order' => ['item' => ['{item}', true], 'their' => ['{supplier}\'s item'], 'ordered' => ['Ordered', true], 'received' => ['Received'], 'due' => ['Still due'], 'price' => ['Price'], 'tax' => ['Tax %'], 'total' => ['Total']],
        'grn' => ['item' => ['{item}', true], 'their' => ['{supplier}\'s item'], 'po' => ['PO'], 'batch' => ['{batch}'], 'rack' => ['{rack}'], 'qty' => ['Qty', true], 'returned' => ['Returned'], 'price' => ['Price'], 'cost' => ['Stock cost']],
        'bill' => ['item' => ['{item}', true], 'their' => ['{supplier}\'s item'], 'qty' => ['Qty', true], 'price' => ['Price'], 'tax' => ['Tax %'], 'amount' => ['Amount']],
        'purchase_return' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'rack' => ['{rack}'], 'qty' => ['Qty', true], 'price' => ['Price'], 'value' => ['Value']],
        'requisition' => ['item' => ['{item}', true], 'qty' => ['Qty', true], 'note' => ['Note']],
        'sales_order' => ['item' => ['{item}', true], 'ordered' => ['Qty', true], 'delivered' => ['Delivered'], 'price' => ['Price'], 'disc' => ['Disc %'], 'tax' => ['Tax %'], 'total' => ['Total']],
        'delivery' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'rack' => ['{rack}'], 'qty' => ['Qty', true], 'price' => ['Price'], 'total' => ['Total']],
        'invoice' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'rack' => ['{rack}'], 'qty' => ['Qty', true], 'price' => ['Price'], 'total' => ['Total']],
        'sales_return' => ['item' => ['{item}', true], 'batch' => ['{batch}'], 'back' => ['Back on'], 'qty' => ['Qty', true], 'credit' => ['Credit']],
    ];

    public static function has(string $entity, bool $view = false): bool
    {
        return isset(($view ? self::VIEWS : self::GRIDS)[$entity]);
    }

    private static function name(string $def): string
    {
        return strtr($def, ['{item}' => term('item'), '{batch}' => term('batch'), '{rack}' => term('rack'), '{rack_l}' => strtolower(term('rack')), '{supplier}' => term('supplier')]);
    }

    /**
     * The columns in the company's order: [{key, label, def, show, locked}].
     * `$saved` is what the form layout stored: ['order' => [keys], 'cols' => [key => ['show' => 0|1, 'label' => text]]];
     * `$legacy` the older {disc, tax} switches, used until the grid has been designed.
     */
    public static function resolve(string $entity, ?array $saved, array $legacy = [], bool $view = false): array
    {
        $defs = ($view ? self::VIEWS : self::GRIDS)[$entity] ?? [];
        if (!$defs) return [];
        $order = [];
        foreach ((array)($saved['order'] ?? []) as $k) if (isset($defs[$k]) && !in_array($k, $order, true)) $order[] = $k;
        foreach (array_keys($defs) as $k) if (!in_array($k, $order, true)) $order[] = $k;
        $out = [];
        foreach ($order as $k) {
            $locked = !empty($defs[$k][1]);
            $c = $saved['cols'][$k] ?? null;
            $show = $locked || ($c !== null ? !empty($c['show']) : !array_key_exists($k, $legacy) || !empty($legacy[$k]));
            $def = self::name($defs[$k][0]);
            $label = trim((string)($c['label'] ?? ''));
            $out[] = ['key' => $k, 'label' => $label !== '' ? $label : $def, 'def' => $def, 'show' => $show, 'locked' => $locked];
        }
        return $out;
    }

    /** What the designer posted ([{key, show, label}] in order), checked, as it is stored; null when nothing valid was sent. */
    public static function clean(string $entity, mixed $in, bool $view = false): ?array
    {
        $defs = ($view ? self::VIEWS : self::GRIDS)[$entity] ?? [];
        if (!$defs || !is_array($in)) return null;
        $order = []; $cols = [];
        foreach ($in as $c) {
            $c = (array)$c; $k = (string)($c['key'] ?? '');
            if (!isset($defs[$k]) || isset($cols[$k])) continue;
            $label = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)($c['label'] ?? ''))) ?? ''), 0, 40);
            if ($label === self::name($defs[$k][0])) $label = '';
            $order[] = $k;
            $cols[$k] = ['show' => !empty($defs[$k][1]) || !empty($c['show']) ? 1 : 0, 'label' => $label];
        }
        return $order ? ['order' => $order, 'cols' => $cols] : null;
    }
}
