<?php
declare(strict_types=1);

namespace Core;

/** Per-form look: field order, width, own label wording and help text (Settings → Form design). */
final class FormDesign
{
    /** entity => [column => default label]. Order here is the default order. */
    public const FIELDS = [
        'item' => ['name' => 'Item name', 'category_id' => 'Category', 'brand_id' => 'Brand', 'unit_id' => 'Unit', 'tax_id' => 'Tax', 'description' => 'Description'],
        'customer' => ['name' => 'Customer name', 'group_id' => 'Group', 'contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Billing address',
            'ship_address' => 'Delivery address', 'tax_no' => 'Tax number', 'credit_days' => 'Credit days', 'notes' => 'Notes'],
        'supplier' => ['name' => 'Supplier name', 'contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'tax_no' => 'Tax number', 'address' => 'Address',
            'payment_terms_days' => 'Payment terms (days)', 'notes' => 'Notes'],
        'warehouse' => ['name' => 'Name', 'code' => 'Code', 'address' => 'Address'],
        'quotation' => ['customer_id' => 'Customer', 'quote_date' => 'Date', 'valid_until' => 'Valid until', 'delivery_charge' => 'Delivery charge', 'installation_charge' => 'Installation charge', 'notes' => 'Notes / terms'],
        'sales_order' => ['customer_id' => 'Customer', 'warehouse_id' => 'Ship from', 'order_date' => 'Order date', 'expected_date' => 'Deliver by', 'ship_to' => 'Delivery address',
            'delivery_charge' => 'Delivery charge', 'installation_charge' => 'Installation charge', 'notes' => 'Notes'],
        'delivery' => ['delivery_date' => 'Delivery date', 'ship_to' => 'Deliver to', 'note' => 'Note'],
        'sales_return' => ['return_date' => 'Return date', 'reason' => 'Reason'],
        'purchase_order' => ['supplier_id' => 'Supplier', 'warehouse_id' => 'Deliver to', 'order_date' => 'Order date', 'expected_date' => 'Expected on', 'notes' => 'Notes'],
        'grn' => ['warehouse_id' => 'Receive into', 'received_date' => 'Received on', 'supplier_ref' => 'Supplier challan / invoice no.', 'extra_cost' => 'Extra cost', 'extra_cost_note' => 'Extra cost note', 'note' => 'Note'],
        'bill' => ['supplier_bill_no' => "Supplier's bill number", 'bill_date' => 'Bill date', 'due_date' => 'Due date', 'other_charges' => 'Other charges', 'notes' => 'Notes'],
        'purchase_return' => ['return_date' => 'Return date', 'reason' => 'Reason'],
        'adjustment' => ['warehouse_id' => 'Warehouse', 'reason' => 'Reason', 'note' => 'Note'],
        'transfer' => ['warehouse_id' => 'From warehouse', 'to_warehouse_id' => 'To warehouse', 'note' => 'Note'],
    ];
    public const WIDTHS = ['' => 'Default', '25' => '¼ width', '33' => '⅓ width', '50' => '½ width', '66' => '⅔ width', '75' => '¾ width', '100' => 'Full width'];

    private static ?array $cfg = null;

    private static function cfg(): array
    {
        if (self::$cfg === null) {
            $v = json_decode(Settings::get('forms.design', '{}'), true);
            self::$cfg = is_array($v) ? $v : [];
        }
        return self::$cfg;
    }

    public static function field(string $entity, string $col): array
    {
        $f = self::cfg()[$entity]['fields'][$col] ?? [];
        return ['w' => (string)($f['w'] ?? ''), 'label' => (string)($f['label'] ?? ''), 'help' => (string)($f['help'] ?? '')];
    }

    public static function label(string $entity, string $col): string
    {
        return self::field($entity, $col)['label'];
    }

    /** Fields of a form in the company's chosen order (unknown/new ones appended). */
    public static function order(string $entity): array
    {
        $all = array_keys(self::FIELDS[$entity] ?? []);
        $saved = array_values(array_filter((array)(self::cfg()[$entity]['order'] ?? []), fn($c) => in_array($c, $all, true)));
        return array_values(array_unique(array_merge($saved, $all)));
    }

    public static function customised(string $entity): bool
    {
        return isset(self::cfg()[$entity]);
    }

    /** HTML attributes for a field wrapper: CSS order, width variable and help text. Empty when nothing is customised. */
    public static function attrs(string $entity, string $col): string
    {
        if (!self::customised($entity)) return '';
        $f = self::field($entity, $col);
        $pos = array_search($col, self::order($entity), true);
        $style = 'order:' . (($pos === false ? 99 : $pos) + 1) . ';';
        if ($f['w'] !== '' && isset(self::WIDTHS[$f['w']])) $style .= '--w:' . $f['w'] . '%;';
        return ' style="' . $style . '"' . ($f['help'] !== '' ? ' data-help="' . htmlspecialchars($f['help'], ENT_QUOTES) . '"' : '');
    }

    /** @param array $post design[entity][col][w|label|help] and order[entity][] */
    public static function save(array $design, array $order, array $reset): void
    {
        $cfg = self::cfg();
        foreach (self::FIELDS as $entity => $cols) {
            if (isset($reset[$entity])) { unset($cfg[$entity]); continue; }
            if (!isset($design[$entity]) && !isset($order[$entity])) continue;
            $fields = [];
            foreach ($cols as $col => $_) {
                $in = (array)($design[$entity][$col] ?? []);
                $w = (string)($in['w'] ?? '');
                $label = trim(preg_replace('/\s+/', ' ', strip_tags((string)($in['label'] ?? ''))) ?? '');
                $help = trim(preg_replace('/\s+/', ' ', strip_tags((string)($in['help'] ?? ''))) ?? '');
                $row = [];
                if ($w !== '' && isset(self::WIDTHS[$w])) $row['w'] = $w;
                if ($label !== '' && $label !== $cols[$col]) $row['label'] = mb_substr($label, 0, 60);
                if ($help !== '') $row['help'] = mb_substr($help, 0, 140);
                if ($row) $fields[$col] = $row;
            }
            $ord = array_values(array_unique(array_filter(array_map('strval', (array)($order[$entity] ?? [])), fn($c) => isset($cols[$c]))));
            $ord = array_values(array_unique(array_merge($ord, array_keys($cols))));
            $isDefaultOrder = $ord === array_keys($cols);
            if (!$fields && $isDefaultOrder) unset($cfg[$entity]);
            else $cfg[$entity] = ['fields' => $fields, 'order' => $ord];
        }
        Settings::set('forms.design', json_encode($cfg, JSON_UNESCAPED_UNICODE));
        self::$cfg = $cfg;
    }
}
