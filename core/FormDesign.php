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
    /** Default width (of 12 columns) each field has on the real form. */
    public const SPANS = [
        'item' => ['name' => 12, 'category_id' => 4, 'brand_id' => 4, 'unit_id' => 4, 'tax_id' => 4, 'description' => 12],
        'customer' => ['name' => 8, 'group_id' => 4, 'contact_person' => 4, 'phone' => 4, 'email' => 4, 'address' => 12, 'ship_address' => 12, 'tax_no' => 4, 'credit_days' => 3, 'notes' => 5],
        'supplier' => ['name' => 12, 'contact_person' => 6, 'phone' => 6, 'email' => 6, 'tax_no' => 6, 'address' => 12, 'payment_terms_days' => 4, 'notes' => 8],
        'warehouse' => ['name' => 12, 'code' => 12, 'address' => 12],
        'quotation' => ['customer_id' => 5, 'quote_date' => 3, 'valid_until' => 4, 'delivery_charge' => 3, 'installation_charge' => 3, 'notes' => 6],
        'sales_order' => ['customer_id' => 4, 'warehouse_id' => 3, 'order_date' => 2, 'expected_date' => 3, 'ship_to' => 6, 'delivery_charge' => 3, 'installation_charge' => 3, 'notes' => 8],
        'delivery' => ['delivery_date' => 2, 'ship_to' => 3, 'note' => 8],
        'sales_return' => ['return_date' => 3, 'reason' => 5],
        'purchase_order' => ['supplier_id' => 4, 'warehouse_id' => 3, 'order_date' => 2, 'expected_date' => 3, 'notes' => 12],
        'grn' => ['warehouse_id' => 3, 'received_date' => 2, 'supplier_ref' => 3, 'extra_cost' => 3, 'extra_cost_note' => 4, 'note' => 5],
        'bill' => ['supplier_bill_no' => 12, 'bill_date' => 6, 'due_date' => 6, 'other_charges' => 12, 'notes' => 12],
        'purchase_return' => ['return_date' => 3, 'reason' => 5],
        'adjustment' => ['warehouse_id' => 3, 'reason' => 3, 'note' => 6],
        'transfer' => ['warehouse_id' => 3, 'to_warehouse_id' => 3, 'note' => 6],
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

    /** Everything the visual designer needs, for every form. */
    public static function model(): array
    {
        $out = [];
        foreach (FormFields::SECTIONS as $section => $entities) {
            foreach ($entities as $entity) {
                $cols = self::FIELDS[$entity] ?? null;
                $layout = $cols !== null;
                if (!$layout) $cols = FormFields::REGISTRY[$entity] ?? [];
                $fields = [];
                $order = $layout ? self::order($entity) : array_keys($cols);
                foreach ($order as $col) {
                    $f = self::field($entity, $col);
                    $opt = isset(FormFields::REGISTRY[$entity][$col]);
                    $fields[] = ['col' => $col, 'label' => $cols[$col], 'span' => self::SPANS[$entity][$col] ?? 12, 'optional' => $opt,
                        'show' => $opt ? FormFields::shown($entity, $col) : true, 'req' => $opt ? FormFields::required($entity, $col) : false,
                        'w' => $f['w'], 'custom' => $f['label'], 'help' => $f['help'], 'kind' => self::kind($col),
                        'pos' => (int)array_search($col, array_keys($cols), true)];
                }
                // optional fields that have no layout slot (e.g. rack description) still need to be switchable
                foreach (FormFields::REGISTRY[$entity] ?? [] as $col => $label) {
                    if (isset($cols[$col])) continue;
                    $fields[] = ['col' => $col, 'label' => $label, 'span' => 12, 'optional' => true, 'show' => FormFields::shown($entity, $col),
                        'req' => FormFields::required($entity, $col), 'w' => '', 'custom' => '', 'help' => '', 'kind' => self::kind($col), 'pos' => 99];
                }
                $out[] = ['key' => $entity, 'title' => FormFields::ENTITY_LABELS[$entity], 'section' => $section, 'layout' => $layout, 'customised' => self::customised($entity), 'fields' => $fields];
            }
        }
        return $out;
    }

    private static function kind(string $col): string
    {
        return match (true) {
            str_ends_with($col, '_date') || $col === 'valid_until' => 'calendar-date',
            str_ends_with($col, '_id') => 'list-ul',
            $col === 'email' => 'envelope',
            $col === 'phone' => 'telephone',
            (bool)preg_match('/charge|cost|price|days|qty/', $col) => 'currency-rupee',
            (bool)preg_match('/note|description|address|reason|ship_to|help/', $col) => 'text-paragraph',
            default => 'input-cursor-text',
        };
    }

    /** Saves everything the designer sends: {entity: {order:[], fields:{col:{w,label,help,show,req}}, reset?:bool}} */
    public static function savePayload(array $payload): void
    {
        $design = $order = $reset = [];
        $hidden = $required = [];
        foreach (FormFields::SECTIONS as $entities) foreach ($entities as $entity) {
            $in = (array)($payload[$entity] ?? []);
            $fieldsIn = (array)($in['fields'] ?? []);
            foreach (FormFields::REGISTRY[$entity] ?? [] as $col => $_) {
                $f = (array)($fieldsIn[$col] ?? []);
                $reg = !empty($in['reset']) ? ['show' => true, 'req' => false] : ['show' => !array_key_exists('show', $f) || !empty($f['show']), 'req' => !empty($f['req'])];
                if (!$reg['show']) $hidden[] = "$entity.$col";
                elseif ($reg['req']) $required[] = "$entity.$col";
            }
            if (!isset(self::FIELDS[$entity])) continue;
            if (!empty($in['reset'])) { $reset[$entity] = 1; continue; }
            $design[$entity] = [];
            foreach ($fieldsIn as $col => $f) $design[$entity][$col] = ['w' => (string)($f['w'] ?? ''), 'label' => (string)($f['label'] ?? ''), 'help' => (string)($f['help'] ?? '')];
            $order[$entity] = array_map('strval', (array)($in['order'] ?? []));
        }
        FormFields::save($hidden, $required);
        self::save($design, $order, $reset);
    }
}
