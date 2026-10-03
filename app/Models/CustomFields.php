<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/** Extra fields a company adds to items, customers and suppliers (Settings → Custom fields). */
final class CustomFields
{
    public const ENTITIES = ['item' => 'Items', 'customer' => 'Customers', 'supplier' => 'Suppliers', 'quotation' => 'Quotations', 'sales_order' => 'Sales orders', 'purchase_order' => 'Purchase orders'];
    public const TYPES = ['text' => 'Single line', 'textarea' => 'Multi-line', 'email' => 'Email', 'phone' => 'Phone', 'url' => 'URL', 'number' => 'Number', 'decimal' => 'Decimal',
        'currency' => 'Currency', 'percent' => 'Percent', 'date' => 'Date', 'datetime' => 'Date & time', 'dropdown' => 'Pick list (dropdown)', 'radio' => 'Radio buttons', 'multiselect' => 'Multi-select (tick several)', 'checkbox' => 'Yes / No'];
    /** Types where a duplicate value can be refused ("Unique"). */
    public const UNIQUE_TYPES = ['text', 'email', 'phone', 'url', 'number', 'decimal', 'dropdown', 'date'];
    /** Types that need a list of choices. */
    public const CHOICE_TYPES = ['dropdown', 'radio', 'multiselect'];

    private static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /** @return array<int, array> */
    public static function fields(string $entity, bool $onlyActive = true): array
    {
        $rows = DB::all('SELECT * FROM custom_fields WHERE tenant_id = ? AND entity = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id', [self::tid(), $entity]);
        foreach ($rows as &$r) $r['choices'] = in_array($r['type'], self::CHOICE_TYPES, true) ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$r['options'])))) : [];
        return $rows;
    }

    /** @return array<int, string> field_id => value */
    public static function values(string $entity, int $id): array
    {
        return array_column(DB::all(
            'SELECT v.field_id, v.value FROM custom_field_values v JOIN custom_fields f ON f.id = v.field_id
             WHERE v.tenant_id = ? AND v.entity_id = ? AND f.entity = ?', [self::tid(), $id, $entity]), 'value', 'field_id');
    }

    /** Values to show in a form: what was just posted (after an error), else what is saved. */
    public static function formValues(string $entity, ?int $id): array
    {
        $old = $_SESSION['_old']['cf'] ?? null;
        if (is_array($old)) return $old;
        return $id ? self::values($entity, $id) : [];
    }

    /** @return array{0: array<int,string>, 1: string[]} values by field id, error messages */
    public static function validate(string $entity, array $input, ?int $entityId = null): array
    {
        $posted = (array)($input['cf'] ?? []);
        $values = [];
        $errors = [];
        foreach (self::fields($entity) as $f) {
            $id = (int)$f['id'];
            $raw = $posted[$id] ?? '';
            if ($f['type'] === 'multiselect') {
                $picked = array_values(array_intersect($f['choices'], array_map('strval', is_array($raw) ? $raw : [])));
                $raw = $picked ? json_encode($picked, JSON_UNESCAPED_UNICODE) : '';
            }
            $v = is_array($raw) ? '' : trim((string)$raw);
            $label = $f['label'];
            if ($f['type'] === 'checkbox') {
                $v = $v === '1' ? '1' : '0';
                if ($f['is_required'] && $v !== '1') $errors[] = "$label must be ticked.";
            } elseif ($v === '') {
                if ($f['is_required']) $errors[] = "$label is required.";
            } elseif (in_array($f['type'], ['number', 'decimal', 'currency', 'percent'], true)) {
                if (!is_numeric($v)) $errors[] = "$label must be a number.";
                elseif ($f['type'] === 'percent' && ((float)$v < 0 || (float)$v > 100)) $errors[] = "$label must be between 0 and 100.";
                elseif ($f['type'] === 'number' && !preg_match('/^-?\d+$/', $v)) $errors[] = "$label must be a whole number.";
            } elseif ($f['type'] === 'email') {
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $errors[] = "$label must be a valid email address.";
            } elseif ($f['type'] === 'url') {
                if (!preg_match('~^https?://[^\s]+$~i', $v)) $errors[] = "$label must be a web address starting with http:// or https://.";
            } elseif ($f['type'] === 'phone') {
                if (!preg_match('/^[0-9+()\-.\s]{5,25}$/', $v)) $errors[] = "$label must be a valid phone number.";
            } elseif ($f['type'] === 'date') {
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) $errors[] = "$label must be a valid date.";
            } elseif ($f['type'] === 'datetime') {
                $d = \DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: \DateTime::createFromFormat('Y-m-d H:i', $v);
                if (!$d) $errors[] = "$label must be a valid date and time."; else $v = $d->format('Y-m-d H:i');
            } elseif ($f['type'] === 'multiselect') {
                // already filtered to the listed options
            } elseif (in_array($f['type'], self::CHOICE_TYPES, true)) {
                if (!in_array($v, $f['choices'], true)) $errors[] = "$label: choose one of the listed options.";
            } elseif (mb_strlen($v) > ($f['type'] === 'textarea' ? 1000 : 255)) {
                $errors[] = "$label is too long (max " . ($f['type'] === 'textarea' ? 1000 : 255) . " characters).";
            }
            if ($v !== '' && !empty($f['is_unique']) && in_array($f['type'], self::UNIQUE_TYPES, true)
                && DB::val('SELECT 1 FROM custom_field_values WHERE tenant_id = ? AND field_id = ? AND value = ? AND entity_id <> ? LIMIT 1', [self::tid(), $id, $v, (int)$entityId]) !== null) {
                $errors[] = "$label \"$v\" is already used. It must be unique.";
            }
            $values[$id] = $v;
        }
        return [$values, $errors];
    }

    public static function save(string $entity, int $entityId, array $values): void
    {
        $t = self::tid();
        foreach ($values as $fieldId => $v) {
            if ($v === '' || ($v === '0' && self::isCheckbox((int)$fieldId))) {
                DB::run('DELETE FROM custom_field_values WHERE field_id = ? AND entity_id = ? AND tenant_id = ?', [$fieldId, $entityId, $t]);
                continue;
            }
            DB::run('INSERT INTO custom_field_values (field_id, entity_id, tenant_id, value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$fieldId, $entityId, $t, $v]);
        }
    }

    private static function isCheckbox(int $fieldId): bool
    {
        return DB::val("SELECT 1 FROM custom_fields WHERE id = ? AND tenant_id = ? AND type = 'checkbox'", [$fieldId, self::tid()]) !== null;
    }

    public static function display(array $f, ?string $value): string
    {
        if ($value === null || $value === '') return '';
        return match ($f['type']) {
            'checkbox' => $value === '1' ? 'Yes' : 'No',
            'date' => fdate($value),
            'datetime' => fdate(substr($value, 0, 10)) . ' ' . substr($value, 11, 5),
            'multiselect' => implode(', ', (array)(json_decode($value, true) ?: [])),
            'currency' => money($value),
            'percent' => rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . '%',
            default => $value,
        };
    }

    /** Adds ['cf' => [field_id => text]] to list rows for the fields marked "show in list". Returns those fields. */
    public static function attachList(string $entity, array &$rows, ?array $ids = null): array
    {
        $fields = $ids === null
            ? array_slice(array_values(array_filter(self::fields($entity), fn($f) => $f['show_in_list'])), 0, 3)
            : array_values(array_filter(self::fields($entity), fn($f) => in_array((int)$f['id'], $ids, true)));
        if (!$fields || !$rows) return [];
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $fin = implode(',', array_fill(0, count($fields), '?'));
        $map = [];
        foreach (DB::all("SELECT entity_id, field_id, value FROM custom_field_values WHERE tenant_id = ? AND entity_id IN ($in) AND field_id IN ($fin)",
            [self::tid(), ...$ids, ...array_map(fn($f) => (int)$f['id'], $fields)]) as $v) $map[$v['entity_id']][$v['field_id']] = $v['value'];
        foreach ($rows as &$r) $r['cf'] = $map[$r['id']] ?? [];
        return $fields;
    }

    /** One input for a custom field, for use inside a form grid. */
    public static function inputHtml(array $f, string $val): string
    {
        $name = 'cf[' . (int)$f['id'] . ']';
        $req = $f['is_required'] ? ' required' : '';
        $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
        switch ($f['type']) {
            case 'checkbox':
                return '<div class="form-check"><input type="hidden" name="' . $name . '" value="0"><input class="form-check-input" type="checkbox" name="' . $name . '" value="1"' . ($val === '1' ? ' checked' : '') . '><label class="form-check-label">Yes</label></div>';
            case 'dropdown':
                $o = '<select name="' . $name . '" class="form-select"' . $req . '><option value="">—</option>';
                foreach ($f['choices'] as $c) $o .= '<option' . ($val === $c ? ' selected' : '') . '>' . $e($c) . '</option>';
                return $o . '</select>';
            case 'radio':
                $o = '<div class="d-flex flex-wrap gap-3 pt-1">';
                foreach ($f['choices'] as $i => $c) $o .= '<div class="form-check"><input class="form-check-input" type="radio" name="' . $name . '" id="' . $name . $i . '" value="' . $e($c) . '"' . ($val === $c ? ' checked' : '') . '><label class="form-check-label" for="' . $name . $i . '">' . $e($c) . '</label></div>';
                return $o . '</div>';
            case 'textarea':
                return '<textarea name="' . $name . '" class="form-control" rows="2" maxlength="1000"' . $req . '>' . $e($val) . '</textarea>';
            case 'multiselect':
                $sel = (array)(json_decode($val, true) ?: []);
                $o = '<div class="d-flex flex-wrap gap-3 pt-1">';
                foreach ($f['choices'] as $i => $c) $o .= '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $name . '[]" id="' . $name . $i . '" value="' . $e($c) . '"' . (in_array($c, $sel, true) ? ' checked' : '') . '><label class="form-check-label" for="' . $name . $i . '">' . $e($c) . '</label></div>';
                return $o . '</div>';
            case 'datetime': $t = 'datetime-local'; $x = ''; $val = str_replace(' ', 'T', $val); break;
            case 'date': $t = 'date'; $x = ''; break;
            case 'number': $t = 'number'; $x = ' step="1"'; break;
            case 'decimal': case 'currency': $t = 'number'; $x = ' step="0.01"'; break;
            case 'percent': $t = 'number'; $x = ' step="0.01" min="0" max="100"'; break;
            case 'email': $t = 'email'; $x = ' maxlength="255"'; break;
            case 'phone': $t = 'tel'; $x = ' maxlength="25"'; break;
            case 'url': $t = 'url'; $x = ' maxlength="255" placeholder="https://"'; break;
            default: $t = 'text'; $x = ' maxlength="255"';
        }
        return '<input name="' . $name . '" class="form-control" type="' . $t . '"' . $x . ' value="' . $e($val) . '"' . $req . '>';
    }
}
