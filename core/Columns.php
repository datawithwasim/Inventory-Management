<?php
declare(strict_types=1);

namespace Core;

use App\Models\CustomFields;

/** "Columns" picker for list pages: each user chooses which optional columns they see (stored per user). */
final class Columns
{
    public const LISTS = [
        'items' => ['entity' => 'item', 'route' => 'items', 'cols' => ['category' => 'Category', 'brand' => 'Brand', 'variants' => 'Variants', 'price' => 'Sale price', 'stock' => 'Stock']],
        'customers' => ['entity' => 'customer', 'route' => 'customers', 'cols' => ['group' => 'Group', 'phone' => 'Phone', 'credit' => 'Credit', 'owes' => 'Owes us']],
        'suppliers' => ['entity' => 'supplier', 'route' => 'suppliers', 'cols' => ['contact' => 'Contact', 'phone' => 'Phone', 'terms' => 'Terms', 'owes' => 'We owe']],
    ];

    /** All choices incl. the company's custom fields: key => label. */
    public static function options(string $list): array
    {
        $cfg = self::LISTS[$list];
        $o = $cfg['cols'];
        foreach (CustomFields::fields($cfg['entity']) as $f) $o['cf:' . $f['id']] = $f['label'];
        return $o;
    }

    /** Keys the user wants to see (defaults: every standard column + custom fields flagged "show in list"). */
    public static function visible(string $list): array
    {
        $opts = self::options($list);
        $saved = Prefs::get('cols.' . $list);
        if (is_array($saved)) return array_values(array_filter($saved, fn($k) => isset($opts[$k])));
        $def = array_keys(self::LISTS[$list]['cols']);
        foreach (CustomFields::fields(self::LISTS[$list]['entity']) as $f) if ($f['show_in_list']) $def[] = 'cf:' . $f['id'];
        return $def;
    }

    public static function cfIds(array $visible): array
    {
        $ids = [];
        foreach ($visible as $k) if (str_starts_with($k, 'cf:')) $ids[] = (int)substr($k, 3);
        return $ids;
    }

    public static function picker(string $list, array $visible): string
    {
        $fid = 'colsForm-' . $list;
        // The real <form> is printed at the end of the page (see layouts/app.php) because this picker sits inside a GET filter form.
        $GLOBALS['foot_html'] = ($GLOBALS['foot_html'] ?? '') . '<form id="' . $fid . '" method="post" action="' . e(url('prefs/columns')) . '" hidden>' . csrf_field()
            . '<input type="hidden" name="list" value="' . e($list) . '"></form>';
        $o = '<div class="dropdown d-inline-block col-picker"><button type="button" class="btn btn-sm col-picker-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-expanded="false" aria-label="Choose columns" title="Choose columns"><i class="bi bi-layout-three-columns"></i></button>'
            . '<div class="dropdown-menu dropdown-menu-end p-3 shadow text-start" style="min-width:240px"><div class="fw-semibold small mb-2">Show these columns</div>';
        foreach (self::options($list) as $k => $label) {
            $o .= '<div class="form-check mb-1"><input class="form-check-input" form="' . $fid . '" type="checkbox" name="cols[]" value="' . e($k) . '" id="col-' . e($list . $k) . '"' . (in_array($k, $visible, true) ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="col-' . e($list . $k) . '">' . e($label) . '</label></div>';
        }
        return $o . '<div class="d-flex gap-2 mt-3"><button type="submit" form="' . $fid . '" class="btn btn-sm btn-primary flex-grow-1">Apply</button>'
            . '<button type="submit" form="' . $fid . '" name="reset" value="1" class="btn btn-sm btn-outline-secondary">Reset</button></div></div></div>';
    }
}
