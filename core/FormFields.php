<?php
declare(strict_types=1);

namespace Core;

/** Optional standard fields a company can hide or make mandatory (Settings → Form fields). */
final class FormFields
{
    /** entity => [column => label]. Only fields that are safe to hide are listed. */
    public const REGISTRY = [
        'item' => ['item_type' => 'Product type', 'brand_id' => 'Brand', 'tax_id' => 'Tax rate', 'hsn_code' => 'HSN code', 'design_no' => 'Design / quality no.', 'composition' => 'Composition',
            'width' => 'Width', 'gsm' => 'GSM / weight', 'pattern' => 'Pattern', 'finish' => 'Finish', 'description' => 'Description'],
        'customer' => ['contact_person' => 'Contact person', 'email' => 'Email', 'address' => 'Billing address', 'ship_address' => 'Delivery address',
            'tax_no' => 'Tax number', 'credit_days' => 'Credit days', 'notes' => 'Notes', 'group_id' => 'Group'],
        'supplier' => ['supplier_type' => 'Supplier type', 'contact_person' => 'Contact person', 'email' => 'Email', 'address' => 'Address', 'tax_no' => 'Tax number',
            'pan' => 'PAN', 'city' => 'City', 'state' => 'State', 'pincode' => 'Pincode', 'ship_address' => 'Godown / dispatch address', 'payment_terms_days' => 'Payment terms',
            'credit_limit' => 'Credit limit', 'lead_time_days' => 'Lead time', 'transport' => 'Preferred transport', 'bank_name' => 'Bank name', 'bank_account' => 'Account number',
            'bank_ifsc' => 'IFSC', 'notes' => 'Notes'],
        'warehouse' => ['address' => 'Address'],
        'location' => ['description' => 'Description'],
        'sales_order' => ['expected_date' => 'Delivery date', 'ship_to' => 'Delivery address', 'delivery_charge' => 'Delivery charge', 'installation_charge' => 'Installation charge', 'notes' => 'Notes'],
        'delivery' => ['ship_to' => 'Deliver to', 'note' => 'Note'],
        'sales_return' => ['reason' => 'Reason'],
        'requisition' => ['note' => 'Note'],
        'purchase_order' => ['expected_date' => 'Expected on', 'notes' => 'Notes'],
        'grn' => ['supplier_ref' => 'Supplier challan / invoice no.', 'extra_cost' => 'Extra cost', 'extra_cost_note' => 'Extra cost note', 'note' => 'Note'],
        'bill' => ['supplier_bill_no' => "Supplier's bill number", 'due_date' => 'Due date', 'other_charges' => 'Other charges', 'notes' => 'Notes'],
        'purchase_return' => ['reason' => 'Reason'],
        'adjustment' => ['note' => 'Note'],
        'transfer' => ['note' => 'Note'], 'invoice' => [], 'stocktake' => [],
        'supplier_item' => ['supplier_code' => 'Supplier item code', 'variant_id' => 'Our item (link)', 'min_order_qty' => 'Minimum order qty', 'lead_time_days' => 'Lead time (days)', 'note' => 'Notes'],
    ];
    public const ENTITY_LABELS = ['item' => 'Item form', 'customer' => 'Customer form', 'supplier' => 'Supplier form', 'warehouse' => 'Warehouse form', 'location' => 'Rack / location form',
        'sales_order' => 'Sales order', 'delivery' => 'Delivery (goods out)', 'sales_return' => 'Sales return', 'requisition' => 'Purchase requisition',
        'purchase_order' => 'Purchase order', 'grn' => 'Goods receipt (GRN)', 'bill' => 'Supplier bill', 'purchase_return' => 'Purchase return', 'adjustment' => 'Stock adjustment', 'transfer' => 'Stock transfer', 'invoice' => 'Sales invoice', 'stocktake' => 'Stock-take', 'supplier_item' => 'Supplier item form'];
    /** Settings page sections: heading => entities. */
    public const SECTIONS = ['Master data' => ['item', 'customer', 'supplier', 'supplier_item', 'warehouse', 'location'], 'Sales documents' => ['sales_order', 'delivery', 'invoice', 'sales_return'],
        'Purchase documents' => ['requisition', 'purchase_order', 'grn', 'bill', 'purchase_return'], 'Stock documents' => ['adjustment', 'transfer', 'stocktake']];

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

    /** Mandatory (and visible) fields that were left empty in the posted data — for forms that build their own rows. */
    public static function missing(string $entity, array $post): array
    {
        $errors = [];
        foreach (self::REGISTRY[$entity] as $col => $label) {
            if (self::required($entity, $col) && trim((string)($post[$col] ?? '')) === '') $errors[] = "$label is required.";
        }
        return $errors;
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
