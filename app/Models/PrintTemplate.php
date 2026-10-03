<?php
declare(strict_types=1);

namespace App\Models;

use Core\Settings;

/** Editable print layouts (Settings → Print templates). */
final class PrintTemplate
{
    public const DOCS = [
        'invoice' => 'Sales invoice', 'receipt' => 'Counter receipt', 'delivery_note' => 'Delivery note',
        'purchase_order' => 'Purchase order', 'quotation' => 'Quotation',
    ];

    /** key => [label, type, default]. type: text | textarea | bool | color */
    private const COMMON = [
        'title' => ['Heading on the document', 'text', ''],
        'show_logo' => ['Show my logo', 'bool', 1],
        'show_company' => ['Show company address, phone and tax number', 'bool', 1],
        'accent' => ['Accent colour', 'color', '#1f2937'],
    ];
    private const TAIL = [
        'terms' => ['Terms & conditions (printed at the bottom)', 'textarea', ''],
        'footer' => ['Footer line', 'text', ''],
        'signature' => ['Show a signature line', 'bool', 1],
        'signature_label' => ['Signature line text', 'text', 'Authorised signatory'],
    ];
    private const SPECIFIC = [
        'invoice' => ['show_discount' => ['Show the discount column', 'bool', 1], 'show_tax' => ['Show the tax column', 'bool', 1], 'show_batch' => ['Show the roll (batch) number', 'bool', 1],
            'show_ship_to' => ['Show the delivery address', 'bool', 1], 'show_bank' => ['Print my bank details', 'bool', 1]],
        'receipt' => ['show_batch' => ['Show the roll (batch) number', 'bool', 0]],
        'delivery_note' => ['show_prices' => ['Show prices and amounts', 'bool', 0], 'show_batch' => ['Show the roll (batch) number', 'bool', 1], 'show_rack' => ['Show the rack picked from', 'bool', 0],
            'show_ship_to' => ['Show the delivery address', 'bool', 1]],
        'purchase_order' => ['show_tax' => ['Show the tax column', 'bool', 1], 'show_expected' => ['Show the expected delivery date', 'bool', 1], 'show_bank' => ['Print my bank details', 'bool', 0]],
        'quotation' => ['show_discount' => ['Show the discount column', 'bool', 1], 'show_tax' => ['Show the tax column', 'bool', 1], 'show_valid' => ['Show the "valid until" date', 'bool', 1]],
    ];
    private const DEFAULT_TEXT = [
        'invoice' => ['title' => 'Tax invoice', 'footer' => 'Thank you for your business.', 'signature_label' => 'Authorised signatory'],
        'receipt' => ['title' => 'Receipt', 'footer' => 'Thank you!', 'signature' => 0],
        'delivery_note' => ['title' => 'Delivery note', 'signature_label' => 'Received by'],
        'purchase_order' => ['title' => 'Purchase order', 'signature_label' => 'Authorised signatory'],
        'quotation' => ['title' => 'Quotation', 'terms' => 'This quotation is valid until the date shown. Prices are subject to stock availability.', 'signature_label' => 'Authorised signatory'],
    ];

    /** Editable options for one document type, in display order. */
    public static function fields(string $doc): array
    {
        return self::COMMON + (self::SPECIFIC[$doc] ?? []) + self::TAIL;
    }

    public static function defaults(string $doc): array
    {
        $out = [];
        foreach (self::fields($doc) as $k => [, , $d]) $out[$k] = $d;
        return array_replace($out, self::DEFAULT_TEXT[$doc] ?? []);
    }

    public static function get(string $doc): array
    {
        return Settings::json('template.' . $doc, self::defaults($doc));
    }

    /** Validates a posted form. @return array{0: array, 1: string[]} values, errors */
    public static function clean(string $doc, array $in): array
    {
        $out = [];
        $errors = [];
        foreach (self::fields($doc) as $k => [$label, $type]) {
            $raw = $in[$k] ?? '';
            if ($type === 'bool') { $out[$k] = empty($raw) ? 0 : 1; continue; }
            $v = trim((string)$raw);
            if ($type === 'color') {
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) { $errors[] = "$label must be a colour like #1f2937."; $v = '#1f2937'; }
            } else {
                $max = $type === 'textarea' ? 1000 : 120;
                if (mb_strlen($v) > $max) { $errors[] = "$label is too long (max $max characters)."; $v = mb_substr($v, 0, $max); }
            }
            $out[$k] = $v;
        }
        return [$out, $errors];
    }
}
