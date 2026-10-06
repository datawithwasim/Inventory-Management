<?php
declare(strict_types=1);

namespace Core;

use App\Models\CustomFields;

/** Per-form look: field order, width, own label wording and help text (Settings → Form design). */
final class FormDesign
{
    /** entity => [column => default label]. Order here is the default order. */
    public const FIELDS = [
        'item' => ['name' => 'Item name', 'item_type' => 'Product type', 'category_id' => 'Category', 'brand_id' => 'Brand', 'unit_id' => 'Unit', 'tax_id' => 'Tax', 'hsn_code' => 'HSN code',
            'design_no' => 'Design / quality no.', 'composition' => 'Composition', 'width' => 'Width', 'gsm' => 'GSM / weight', 'pattern' => 'Pattern', 'finish' => 'Finish', 'description' => 'Description'],
        'customer' => ['name' => 'Customer name', 'group_id' => 'Group', 'contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Billing address',
            'ship_address' => 'Delivery address', 'tax_no' => 'Tax number', 'credit_days' => 'Credit days', 'notes' => 'Notes'],
        'supplier' => ['name' => 'Supplier name', 'supplier_type' => 'Supplier type', 'contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'tax_no' => 'Tax number',
            'pan' => 'PAN', 'address' => 'Address', 'city' => 'City', 'state' => 'State', 'pincode' => 'Pincode', 'ship_address' => 'Godown / dispatch address',
            'payment_terms_days' => 'Payment terms (days)', 'credit_limit' => 'Credit limit', 'lead_time_days' => 'Lead time (days)', 'transport' => 'Preferred transport',
            'bank_name' => 'Bank name', 'bank_account' => 'Account number', 'bank_ifsc' => 'IFSC', 'notes' => 'Notes'],
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
        'item' => ['name' => 12, 'item_type' => 4, 'category_id' => 4, 'brand_id' => 4, 'unit_id' => 4, 'tax_id' => 4, 'hsn_code' => 4, 'design_no' => 4, 'composition' => 4, 'width' => 4,
            'gsm' => 4, 'pattern' => 4, 'finish' => 4, 'description' => 12],
        'customer' => ['name' => 8, 'group_id' => 4, 'contact_person' => 4, 'phone' => 4, 'email' => 4, 'address' => 12, 'ship_address' => 12, 'tax_no' => 4, 'credit_days' => 3, 'notes' => 5],
        'supplier' => ['name' => 8, 'supplier_type' => 4, 'contact_person' => 4, 'phone' => 4, 'email' => 4, 'tax_no' => 6, 'pan' => 6, 'address' => 12, 'city' => 4, 'state' => 4, 'pincode' => 4,
            'ship_address' => 12, 'payment_terms_days' => 3, 'credit_limit' => 3, 'lead_time_days' => 3, 'transport' => 3, 'bank_name' => 4, 'bank_account' => 4, 'bank_ifsc' => 4, 'notes' => 12],
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
    /** Forms that have a record detail page (Overview / Timeline) laid out from the same design. */
    public const DETAIL = ['item', 'customer', 'supplier'];
    /** Fields shown in the summary strip at the top of the detail page when the company has not chosen. */
    public const SUMMARY_DEFAULT = [
        'item' => ['item_type', 'category_id', 'unit_id', 'hsn_code'],
        'customer' => ['phone', 'email', 'group_id', 'credit_days'],
        'supplier' => ['supplier_type', 'phone', 'city', 'payment_terms_days'],
    ];
    public const WIDTHS = ['' => 'Default', '25' => '¼ width', '33' => '⅓ width', '50' => '½ width', '66' => '⅔ width', '75' => '¾ width', '100' => 'Full width'];

    public const COLS = [0 => 'Original', 1 => '1 column', 2 => '2 columns', 3 => '3 columns'];
    /** Forms that carry a line-items table whose columns can be switched off. */
    public const LINE_FORMS = ['quotation', 'sales_order', 'purchase_order'];

    private static ?array $cfg = null;
    private static array $res = [];

    // ---------------------------------------------------------------- storage

    private static function all(): array
    {
        if (self::$cfg === null) {
            $v = json_decode(Settings::get('forms.layout', '{}'), true);
            self::$cfg = is_array($v) ? $v : [];
            // forms designed with the earlier, simpler designer: one untitled section in the old order
            $old = json_decode(Settings::get('forms.design', '{}'), true);
            if (is_array($old)) foreach ($old as $entity => $o) {
                if (isset(self::$cfg[$entity]) || !isset(self::FIELDS[$entity])) continue;
                self::$cfg[$entity] = ['style' => 'top', 'sections' => [['id' => 's1', 'title' => '', 'cols' => 0, 'fields' => array_values((array)($o['order'] ?? []))]],
                    'props' => (array)($o['fields'] ?? []), 'lines' => []];
            }
        }
        return self::$cfg;
    }

    public static function customised(string $entity): bool
    {
        return isset(self::all()[$entity]);
    }

    /** Standard field keys of a form (those with a slot in the layout). */
    private static function stdKeys(string $entity): array
    {
        return array_keys(self::FIELDS[$entity] ?? []);
    }

    private static function cfKey(array $f): string
    {
        return 'cf:' . (int)$f['id'];
    }

    /**
     * Resolved layout of a form: sections with only valid, visible keys; the "unused" (hidden) keys; props; style.
     * Always complete, whether or not the company has designed it.
     */
    public static function resolve(string $entity): array
    {
        if (isset(self::$res[$entity])) return self::$res[$entity];
        $saved = self::all()[$entity] ?? null;
        $std = self::stdKeys($entity);
        $cfs = Auth::user() && isset(CustomFields::ENTITIES[$entity]) ? CustomFields::fields($entity, false) : [];
        $cfByKey = [];
        foreach ($cfs as $f) $cfByKey[self::cfKey($f)] = $f;
        $valid = array_merge($std, array_keys($cfByKey));
        $visible = function (string $k) use ($entity, $cfByKey): bool {
            if (isset($cfByKey[$k])) return (bool)$cfByKey[$k]['is_active'];
            return !isset(FormFields::REGISTRY[$entity][$k]) || FormFields::shown($entity, $k);
        };
        $sections = [];
        $seen = [];
        foreach ((array)($saved['sections'] ?? []) as $i => $sec) {
            $keys = [];
            foreach ((array)($sec['fields'] ?? []) as $k) {
                if (in_array($k, $valid, true) && !isset($seen[$k]) && $visible($k)) { $keys[] = $k; $seen[$k] = 1; }
            }
            $sections[] = ['id' => (string)($sec['id'] ?? 's' . ($i + 1)), 'title' => (string)($sec['title'] ?? ''), 'cols' => (int)($sec['cols'] ?? 0), 'fields' => $keys];
        }
        if (!$sections) $sections[] = ['id' => 's1', 'title' => '', 'cols' => 0, 'fields' => []];
        // anything not yet placed: standard fields join the first section, new custom fields the last
        foreach ($std as $k) if (!isset($seen[$k]) && $visible($k)) { $sections[0]['fields'][] = $k; $seen[$k] = 1; }
        $extra = array_values(array_filter(array_keys($cfByKey), fn($k) => !isset($seen[$k]) && $visible($k)));
        if ($extra) {
            if ($saved === null) $sections[] = ['id' => 's2', 'title' => 'Additional details', 'cols' => 0, 'fields' => $extra];
            else $sections[count($sections) - 1]['fields'] = array_merge($sections[count($sections) - 1]['fields'], $extra);
        }
        $unused = array_values(array_filter($valid, fn($k) => !$visible($k)));
        $props = [];
        foreach ($valid as $k) {
            $p = (array)($saved['props'][$k] ?? []);
            $props[$k] = ['w' => isset(self::WIDTHS[(string)($p['w'] ?? '')]) ? (string)($p['w'] ?? '') : '', 'label' => (string)($p['label'] ?? ''), 'help' => (string)($p['help'] ?? '')];
        }
        $order = [];
        $n = 0;
        foreach ($sections as $si => $sec) {
            $sections[$si]['order'] = ++$n * 10 - 5;
            foreach ($sec['fields'] as $k) $order[$k] = ++$n * 10;
        }
        $style = ($saved['style'] ?? 'top') === 'left' ? 'left' : 'top';
        $pickSum = isset($saved['summary']) ? (array)$saved['summary'] : (self::SUMMARY_DEFAULT[$entity] ?? []);
        $summary = array_slice(array_values(array_filter(array_unique($pickSum), fn($k) => in_array($k, $valid, true) && $visible($k))), 0, 4);
        $lines = ['disc' => !isset($saved['lines']['disc']) || !empty($saved['lines']['disc']), 'tax' => !isset($saved['lines']['tax']) || !empty($saved['lines']['tax'])];
        return self::$res[$entity] = ['customised' => $saved !== null, 'style' => $style, 'sections' => $sections, 'unused' => $unused, 'props' => $props, 'order' => $order, 'lines' => $lines, 'cf' => $cfByKey, 'summary' => $summary];
    }

    // ---------------------------------------------------------------- rendering helpers

    private static function secOf(array $r, string $key): ?array
    {
        foreach ($r['sections'] as $s) if (in_array($key, $s['fields'], true)) return $s;
        return null;
    }

    /** style (+ data-help) for a field wrapper: order in the form, width from its section's column count */
    public static function attrs(string $entity, string $key): string
    {
        if (!self::customised($entity)) return '';
        $r = self::resolve($entity);
        $p = $r['props'][$key] ?? ['w' => '', 'help' => ''];
        $style = 'order:' . ($r['order'][$key] ?? 999) . ';';
        $sec = self::secOf($r, $key);
        if ($p['w'] !== '') $style .= '--w:' . $p['w'] . '%;';
        elseif ($sec && $sec['cols'] > 0) $style .= '--w:' . ($sec['cols'] === 3 ? '33.3333' : (string)(100 / $sec['cols'])) . '%;';
        return ' style="' . $style . '"' . ($p['help'] !== '' ? ' data-help="' . htmlspecialchars($p['help'], ENT_QUOTES) . '"' : '');
    }

    public static function label(string $entity, string $key): string
    {
        return self::customised($entity) ? self::resolve($entity)['props'][$key]['label'] ?? '' : '';
    }

    /** Extra class for the form grid: label position. */
    public static function cls(string $entity): string
    {
        return self::customised($entity) && self::resolve($entity)['style'] === 'left' ? 'ff-left' : '';
    }

    /** Which line-table columns are switched off, for data-hide-cols. */
    public static function hiddenLineCols(string $entity): string
    {
        if (!self::customised($entity) || !in_array($entity, self::LINE_FORMS, true)) return '';
        $l = self::resolve($entity)['lines'];
        return implode(',', array_keys(array_filter(['disc' => !$l['disc'], 'tax' => !$l['tax']])));
    }

    /** Section headings + custom fields, to be echoed inside the form grid. */
    public static function extras(string $entity, array $cfFields, array $cfValues): string
    {
        $r = self::resolve($entity);
        $o = '';
        if ($r['customised']) {
            foreach ($r['sections'] as $s) if ($s['title'] !== '' && $s['fields']) $o .= '<div class="ff-section" style="order:' . $s['order'] . '"><span>' . htmlspecialchars($s['title'], ENT_QUOTES) . '</span></div>';
        } elseif ($cfFields) {
            $o .= '<div class="ff-section" style="order:900"><span>Additional details</span></div>';
        }
        foreach ($cfFields as $f) {
            $key = self::cfKey($f);
            if ($r['customised'] && !isset($r['order'][$key])) continue;
            $label = $r['customised'] && ($r['props'][$key]['label'] ?? '') !== '' ? $r['props'][$key]['label'] : $f['label'];
            $attrs = $r['customised'] ? self::attrs($entity, $key) : ' style="order:901"';
            $o .= '<div class="col-md-4 mb-3"' . $attrs . '><label class="form-label">' . htmlspecialchars($label, ENT_QUOTES) . ($f['is_required'] ? ' <span class="text-danger">*</span>' : '') . '</label>'
                . CustomFields::inputHtml($f, (string)($cfValues[$f['id']] ?? '')) . '</div>';
        }
        return $o;
    }

    // ---------------------------------------------------------------- designer model & save

    private static function kind(string $col): string
    {
        return match (true) {
            str_ends_with($col, '_date') || $col === 'valid_until' => 'calendar-date',
            str_ends_with($col, '_id') => 'list-ul',
            $col === 'email' => 'envelope',
            $col === 'phone' => 'telephone',
            (bool)preg_match('/charge|cost|price|days|qty/', $col) => 'currency-rupee',
            (bool)preg_match('/note|description|address|reason|ship_to/', $col) => 'text-paragraph',
            default => 'input-cursor-text',
        };
    }

    private static function typeIcon(string $type): string
    {
        return ['text' => 'input-cursor-text', 'textarea' => 'text-paragraph', 'email' => 'envelope', 'phone' => 'telephone', 'url' => 'link-45deg', 'number' => '123', 'decimal' => 'hash',
            'currency' => 'currency-rupee', 'percent' => 'percent', 'date' => 'calendar-date', 'datetime' => 'calendar-event', 'dropdown' => 'list-ul', 'radio' => 'ui-radios', 'multiselect' => 'ui-checks', 'checkbox' => 'check2-square'][$type] ?? 'input-cursor-text';
    }

    /** Everything the visual designer needs, for every form. */
    public static function model(): array
    {
        $out = [];
        foreach (FormFields::SECTIONS as $group => $entities) {
            foreach ($entities as $entity) {
                $layout = isset(self::FIELDS[$entity]);
                $r = self::resolve($entity);
                $fields = [];
                $keys = array_merge(self::stdKeys($entity), array_keys($r['cf']));
                if (!$layout) $keys = array_keys(FormFields::REGISTRY[$entity] ?? []);
                foreach ($keys as $k) {
                    if (isset($r['cf'][$k])) {
                        $c = $r['cf'][$k];
                        $fields[$k] = ['key' => $k, 'label' => $c['label'], 'kind' => self::typeIcon($c['type']), 'optional' => true, 'custom' => true, 'cfId' => (int)$c['id'], 'type' => $c['type'],
                            'options' => implode("\n", $c['choices']), 'show' => (bool)$c['is_active'], 'req' => (bool)$c['is_required'], 'unique' => (bool)$c['is_unique'], 'inList' => (bool)$c['show_in_list'],
                            'w' => $r['props'][$k]['w'], 'help' => $r['props'][$k]['help'], 'span' => 4];
                        continue;
                    }
                    $opt = isset(FormFields::REGISTRY[$entity][$k]);
                    $fields[$k] = ['key' => $k, 'label' => self::FIELDS[$entity][$k] ?? FormFields::REGISTRY[$entity][$k], 'kind' => self::kind($k), 'optional' => $opt, 'custom' => false,
                        'show' => $opt ? FormFields::shown($entity, $k) : true, 'req' => $opt ? FormFields::required($entity, $k) : false,
                        'w' => $r['props'][$k]['w'] ?? '', 'help' => $r['props'][$k]['help'] ?? '', 'custom_label' => $r['props'][$k]['label'] ?? '', 'span' => self::SPANS[$entity][$k] ?? 12];
                }
                $secs = $r['sections'];
                if (!$layout) $secs = [['id' => 's1', 'title' => '', 'cols' => 0, 'fields' => array_keys(array_filter($fields, fn($f) => $f['show']))]];
                $out[] = ['key' => $entity, 'title' => FormFields::ENTITY_LABELS[$entity], 'group' => $group, 'layout' => $layout, 'customFields' => isset(CustomFields::ENTITIES[$entity]),
                    'lineForm' => in_array($entity, self::LINE_FORMS, true), 'detail' => in_array($entity, self::DETAIL, true), 'summary' => $r['summary'], 'customised' => $r['customised'], 'style' => $r['style'], 'lines' => $r['lines'],
                    'sections' => $secs, 'unused' => $r['unused'], 'stdOrder' => self::stdKeys($entity), 'fields' => $fields];
            }
        }
        return $out;
    }

    /**
     * Saves what the designer sends.
     * payload[entity] = {style, sections:[{id,title,cols,fields:[key]}], fields:{key:{label,help,w,show,req, type?,options?,inList?,isNew?}}, deleted:[key], lines:{disc,tax}, reset?}
     * New custom fields use temporary keys ("cf:new1"); the final keys are returned in the layout.
     */
    public static function savePayload(array $payload): array
    {
        $t = (int)Auth::tenantId();
        $cfg = self::all();
        $hidden = $required = [];
        $notes = ['created' => 0, 'deleted' => 0];
        foreach (FormFields::SECTIONS as $entities) foreach ($entities as $entity) {
            $in = (array)($payload[$entity] ?? []);
            if (!$in) { // untouched form: keep its visibility lists as they are
                foreach (FormFields::REGISTRY[$entity] ?? [] as $col => $_) {
                    if (!FormFields::shown($entity, $col)) $hidden[] = "$entity.$col";
                    elseif (FormFields::required($entity, $col)) $required[] = "$entity.$col";
                }
                continue;
            }
            $reset = !empty($in['reset']);
            $fieldsIn = (array)($in['fields'] ?? []);

            // 1. optional standard fields: show / required
            foreach (FormFields::REGISTRY[$entity] ?? [] as $col => $_) {
                $f = (array)($fieldsIn[$col] ?? []);
                $show = $reset || !array_key_exists('show', $f) || !empty($f['show']);
                if (!$show) $hidden[] = "$entity.$col";
                elseif (!$reset && !empty($f['req'])) $required[] = "$entity.$col";
            }
            if (!isset(self::FIELDS[$entity])) continue;

            // 2. custom fields: delete / create / update
            $map = [];
            $existing = [];
            if (isset(CustomFields::ENTITIES[$entity])) {
                foreach (CustomFields::fields($entity, false) as $c) $existing[self::cfKey($c)] = $c;
                foreach ((array)($in['deleted'] ?? []) as $k) {
                    if (isset($existing[$k])) {
                        DB::run('DELETE FROM custom_fields WHERE tenant_id = ? AND id = ?', [$t, $existing[$k]['id']]);
                        unset($existing[$k]); $notes['deleted']++;
                    }
                }
                $taken = array_map(fn($c) => mb_strtolower($c['label']), $existing);
                foreach ($fieldsIn as $k => $f) {
                    if (!str_starts_with((string)$k, 'cf:')) continue;
                    $f = (array)$f;
                    $label = trim(preg_replace('/\s+/', ' ', strip_tags((string)($f['name'] ?? $f['label'] ?? ''))) ?? '');
                    if ($label === '' || mb_strlen($label) > 80) $label = $existing[$k]['label'] ?? 'New field';
                    if (isset($existing[$k])) {
                        $c = $existing[$k];
                        $opts = $c['options'];
                        if (in_array($c['type'], CustomFields::CHOICE_TYPES, true) && isset($f['options'])) $opts = self::cleanChoices((string)$f['options'], $opts);
                        $dup = false;
                        foreach ($existing as $k2 => $c2) if ($k2 !== $k && mb_strtolower($c2['label']) === mb_strtolower($label)) $dup = true;
                        DB::run('UPDATE custom_fields SET label = ?, options = ?, is_required = ?, show_in_list = ?, is_active = ?, is_unique = ? WHERE tenant_id = ? AND id = ?',
                            [$dup ? $c['label'] : $label, $opts, !empty($f['req']) && $c['type'] !== 'checkbox' ? 1 : 0, !empty($f['inList']) ? 1 : 0, !array_key_exists('show', $f) || !empty($f['show']) ? 1 : 0, !empty($f['unique']) && in_array($c['type'], CustomFields::UNIQUE_TYPES, true) ? 1 : 0, $t, $c['id']]);
                        $map[$k] = $k;
                    } elseif (!empty($f['isNew'])) {
                        $type = (string)($f['type'] ?? 'text');
                        if (!isset(CustomFields::TYPES[$type])) $type = 'text';
                        $base = $label; $i = 2;
                        while (in_array(mb_strtolower($label), $taken, true)) $label = $base . ' ' . $i++;
                        $taken[] = mb_strtolower($label);
                        $opts = null;
                        if (in_array($type, CustomFields::CHOICE_TYPES, true)) $opts = self::cleanChoices((string)($f['options'] ?? ''), "Option 1\nOption 2");
                        $id = DB::insert('custom_fields', ['tenant_id' => $t, 'entity' => $entity, 'label' => $label, 'type' => $type, 'options' => $opts,
                            'is_required' => !empty($f['req']) && $type !== 'checkbox' ? 1 : 0, 'show_in_list' => !empty($f['inList']) ? 1 : 0, 'sort_order' => 0, 'is_unique' => !empty($f['unique']) && in_array($type, CustomFields::UNIQUE_TYPES, true) ? 1 : 0,
                            'is_active' => !array_key_exists('show', $f) || !empty($f['show']) ? 1 : 0]);
                        $map[$k] = 'cf:' . $id; $notes['created']++;
                    }
                }
            }
            $mapKey = fn($k) => $map[$k] ?? $k;

            // 3. layout
            if ($reset) { unset($cfg[$entity]); continue; }
            $sections = [];
            foreach (array_slice((array)($in['sections'] ?? []), 0, 12) as $i => $s) {
                $s = (array)$s;
                $title = trim(preg_replace('/\s+/', ' ', strip_tags((string)($s['title'] ?? ''))) ?? '');
                $sections[] = ['id' => 's' . ($i + 1), 'title' => mb_substr($title, 0, 60), 'cols' => in_array((int)($s['cols'] ?? 0), [0, 1, 2, 3], true) ? (int)$s['cols'] : 0,
                    'fields' => array_values(array_unique(array_map($mapKey, array_map('strval', (array)($s['fields'] ?? [])))))];
            }
            $props = [];
            foreach ($fieldsIn as $k => $f) {
                $f = (array)$f; $k = $mapKey((string)$k);
                if (!isset(self::FIELDS[$entity][$k]) && !(str_starts_with($k, 'cf:') && (isset($existing[$k]) || in_array($k, $map, true)))) continue;
                $w = (string)($f['w'] ?? '');
                $help = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)($f['help'] ?? ''))) ?? ''), 0, 140);
                $label = '';
                if (!str_starts_with($k, 'cf:')) {
                    $label = trim(preg_replace('/\s+/', ' ', strip_tags((string)($f['custom_label'] ?? $f['label'] ?? ''))) ?? '');
                    if ($label === (self::FIELDS[$entity][$k] ?? '')) $label = '';
                    $label = mb_substr($label, 0, 60);
                }
                $row = [];
                if ($w !== '' && isset(self::WIDTHS[$w])) $row['w'] = $w;
                if ($label !== '') $row['label'] = $label;
                if ($help !== '') $row['help'] = $help;
                if ($row) $props[$k] = $row;
            }
            $sum = array_key_exists('summary', $in)
                ? array_slice(array_values(array_unique(array_map($mapKey, array_map('strval', (array)$in['summary'])))), 0, 4)
                : ($cfg[$entity]['summary'] ?? null);
            $cfg[$entity] = ['style' => ($in['style'] ?? 'top') === 'left' ? 'left' : 'top', 'sections' => $sections, 'props' => $props,
                'lines' => ['disc' => !isset($in['lines']['disc']) || !empty($in['lines']['disc']) ? 1 : 0, 'tax' => !isset($in['lines']['tax']) || !empty($in['lines']['tax']) ? 1 : 0]];
            if ($sum !== null) $cfg[$entity]['summary'] = $sum;
        }
        FormFields::save($hidden, $required);
        Settings::set('forms.layout', json_encode($cfg, JSON_UNESCAPED_UNICODE));
        Settings::set('forms.design', '{}'); // superseded
        self::$cfg = null; self::$res = [];
        return $notes;
    }

    private static function cleanChoices(string $text, ?string $fallback): string
    {
        $c = array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 60), preg_split('/\R/', $text)))));
        $c = array_slice($c, 0, 50);
        return count($c) >= 2 ? implode("\n", $c) : (string)$fallback;
    }
}
