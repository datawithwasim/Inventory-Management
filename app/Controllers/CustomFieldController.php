<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomFields;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class CustomFieldController extends Controller
{
    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function load(string $id): array
    {
        return DB::one('SELECT * FROM custom_fields WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $by = [];
        foreach (array_keys(CustomFields::ENTITIES) as $e) {
            $by[$e] = CustomFields::fields($e, false);
            foreach ($by[$e] as &$f) $f['used'] = (int)DB::val('SELECT COUNT(*) FROM custom_field_values WHERE field_id = ?', [$f['id']]);
        }
        $this->settingsView('app/settings/fields', ['title' => 'Settings', 'by' => $by], 'fields');
    }

    public function create(): void
    {
        $this->settingsView('app/settings/field_form', ['title' => 'New custom field', 'row' => null, 'entity' => (string)($_GET['entity'] ?? 'item')], 'fields');
    }

    private function collect(?array $row, string $back): array
    {
        $d = $_POST;
        $bounce = function (string $m) use ($d, $back): never {
            flash('danger', $m);
            with_old($d);
            redirect($back);
        };
        $label = trim((string)($d['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 80) $bounce('The field name is required (max 80 characters).');
        $entity = $row['entity'] ?? (string)($d['entity'] ?? '');
        if (!isset(CustomFields::ENTITIES[$entity])) $bounce('Choose where the field belongs.');
        $type = $row['type'] ?? (string)($d['type'] ?? '');
        if (!isset(CustomFields::TYPES[$type])) $bounce('Choose a field type.');
        if (DB::val('SELECT 1 FROM custom_fields WHERE tenant_id = ? AND entity = ? AND label = ? AND id <> ?', [$this->tid(), $entity, $label, $row['id'] ?? 0])) $bounce('A field with that name already exists there.');
        $options = null;
        if (in_array($type, CustomFields::CHOICE_TYPES, true)) { $choices = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string)($d['options'] ?? ''))))));
            if (count($choices) < 2 || count($choices) > 50) $bounce('A dropdown needs 2 to 50 choices, one per line.');
            foreach ($choices as $c) if (mb_strlen($c) > 60) $bounce('Each choice can have at most 60 characters.');
            $options = implode("\n", $choices);
        }
        $sort = trim((string)($d['sort_order'] ?? '0'));
        if (!preg_match('/^-?\d{1,5}$/', $sort)) $bounce('The order must be a whole number.');
        if ($type === 'checkbox' && !empty($d['is_required'])) $bounce('A Yes / No field cannot be required. Use a dropdown if an answer is needed.');
        return [$entity, $type, ['label' => $label, 'options' => $options, 'is_required' => empty($d['is_required']) ? 0 : 1,
            'show_in_list' => empty($d['show_in_list']) ? 0 : 1, 'sort_order' => (int)$sort, 'is_active' => $row && empty($d['is_active']) ? 0 : 1]];
    }

    public function store(): void
    {
        [$entity, $type, $data] = $this->collect(null, 'settings/custom-fields/create');
        $id = DB::insert('custom_fields', ['tenant_id' => $this->tid(), 'entity' => $entity, 'type' => $type] + $data);
        Audit::log('custom_field_create', 'custom_field', $id, $data['label']);
        flash('success', 'Field added. It now appears on the ' . strtolower(rtrim(CustomFields::ENTITIES[$entity], 's')) . ' screens.');
        redirect('settings/custom-fields');
    }

    public function edit(string $id): void
    {
        $this->settingsView('app/settings/field_form', ['title' => 'Edit custom field', 'row' => $this->load($id), 'entity' => ''], 'fields');
    }

    public function update(string $id): void
    {
        $row = $this->load($id);
        [, , $data] = $this->collect($row, "settings/custom-fields/{$row['id']}/edit");
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run("UPDATE custom_fields SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), $this->tid(), $row['id']]);
        Audit::log('custom_field_update', 'custom_field', (int)$row['id']);
        flash('success', 'Field updated.');
        redirect('settings/custom-fields');
    }

    public function destroy(string $id): void
    {
        $row = $this->load($id);
        DB::run('DELETE FROM custom_fields WHERE tenant_id = ? AND id = ?', [$this->tid(), $row['id']]);
        Audit::log('custom_field_delete', 'custom_field', (int)$row['id'], $row['label']);
        flash('success', 'Field and the values entered in it were deleted.');
        redirect('settings/custom-fields');
    }
}
