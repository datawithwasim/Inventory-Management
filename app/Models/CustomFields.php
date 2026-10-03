<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/** Extra fields a company adds to items, customers and suppliers (Settings → Custom fields). */
final class CustomFields
{
    public const ENTITIES = ['item' => 'Items', 'customer' => 'Customers', 'supplier' => 'Suppliers'];
    public const TYPES = ['text' => 'Text', 'number' => 'Number', 'date' => 'Date', 'dropdown' => 'Dropdown (choose one)', 'checkbox' => 'Yes / No'];

    private static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    /** @return array<int, array> */
    public static function fields(string $entity, bool $onlyActive = true): array
    {
        $rows = DB::all('SELECT * FROM custom_fields WHERE tenant_id = ? AND entity = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id', [self::tid(), $entity]);
        foreach ($rows as &$r) $r['choices'] = $r['type'] === 'dropdown' ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$r['options'])))) : [];
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
    public static function validate(string $entity, array $input): array
    {
        $posted = (array)($input['cf'] ?? []);
        $values = [];
        $errors = [];
        foreach (self::fields($entity) as $f) {
            $id = (int)$f['id'];
            $raw = $posted[$id] ?? '';
            $v = is_array($raw) ? '' : trim((string)$raw);
            $label = $f['label'];
            if ($f['type'] === 'checkbox') {
                $v = $v === '1' ? '1' : '0';
                if ($f['is_required'] && $v !== '1') $errors[] = "$label must be ticked.";
            } elseif ($v === '') {
                if ($f['is_required']) $errors[] = "$label is required.";
            } elseif ($f['type'] === 'number') {
                if (!is_numeric($v)) $errors[] = "$label must be a number.";
            } elseif ($f['type'] === 'date') {
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) $errors[] = "$label must be a valid date.";
            } elseif ($f['type'] === 'dropdown') {
                if (!in_array($v, $f['choices'], true)) $errors[] = "$label: choose one of the listed options.";
            } elseif (mb_strlen($v) > 255) {
                $errors[] = "$label is too long (max 255 characters).";
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
            default => $value,
        };
    }

    /** Adds ['cf' => [field_id => text]] to list rows for the fields marked "show in list". Returns those fields. */
    public static function attachList(string $entity, array &$rows): array
    {
        $fields = array_slice(array_values(array_filter(self::fields($entity), fn($f) => $f['show_in_list'])), 0, 3);
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
}
