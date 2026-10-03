<?php
declare(strict_types=1);

namespace Core;

/** Optional standard fields a company can hide or make mandatory (Settings → Form fields). */
final class FormFields
{
    /** entity => [column => label]. Only fields that are safe to hide are listed. */
    public const REGISTRY = [
        'item' => ['brand_id' => 'Brand', 'tax_id' => 'Tax rate', 'description' => 'Description'],
        'customer' => ['contact_person' => 'Contact person', 'email' => 'Email', 'address' => 'Billing address', 'ship_address' => 'Delivery address',
            'tax_no' => 'Tax number', 'credit_days' => 'Credit days', 'notes' => 'Notes', 'group_id' => 'Group'],
        'supplier' => ['contact_person' => 'Contact person', 'email' => 'Email', 'address' => 'Address', 'tax_no' => 'Tax number',
            'payment_terms_days' => 'Payment terms', 'notes' => 'Notes'],
    ];
    public const ENTITY_LABELS = ['item' => 'Items', 'customer' => 'Customers', 'supplier' => 'Suppliers'];

    private static ?array $hidden = null;
    private static ?array $required = null;

    private static function load(): void
    {
        if (self::$hidden !== null) return;
        $h = json_decode(Settings::get('fields.hidden', '[]'), true);
        $r = json_decode(Settings::get('fields.required', '[]'), true);
        self::$hidden = is_array($h) ? $h : [];
        self::$required = is_array($r) ? $r : [];
    }

    public static function valid(string $entity, string $col): bool
    {
        return isset(self::REGISTRY[$entity][$col]);
    }

    public static function shown(string $entity, string $col): bool
    {
        self::load();
        return !in_array("$entity.$col", self::$hidden, true);
    }

    public static function required(string $entity, string $col): bool
    {
        self::load();
        return self::shown($entity, $col) && in_array("$entity.$col", self::$required, true);
    }

    public static function save(array $hidden, array $required): void
    {
        $ok = fn(array $l) => array_values(array_filter($l, function ($k) {
            [$e, $c] = array_pad(explode('.', (string)$k, 2), 2, '');
            return self::valid($e, $c);
        }));
        $hidden = $ok($hidden);
        $required = array_values(array_diff($ok($required), $hidden));
        Settings::set('fields.hidden', json_encode($hidden));
        Settings::set('fields.required', json_encode($required));
        self::$hidden = $hidden;
        self::$required = $required;
    }

    /** Removes posted values for fields the company has hidden, so they are neither validated nor saved. */
    public static function strip(string $entity, array $data): array
    {
        foreach (self::REGISTRY[$entity] as $col => $label) if (!self::shown($entity, $col)) unset($data[$col]);
        return $data;
    }

    /**
     * Applies the company's rules to posted data: drops hidden columns (so they're left untouched) and reports missing mandatory ones.
     * @return array{0: array, 1: string[]} cleaned data, error messages
     */
    public static function apply(string $entity, array $data): array
    {
        $errors = [];
        foreach (self::REGISTRY[$entity] as $col => $label) {
            if (!self::shown($entity, $col)) { unset($data[$col]); continue; }
            if (self::required($entity, $col)) {
                $v = $data[$col] ?? null;
                if ($v === null || $v === '' || $v === 0 || $v === '0') $errors[] = "$label is required.";
            }
        }
        return [$data, $errors];
    }
}
