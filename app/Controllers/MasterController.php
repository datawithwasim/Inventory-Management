<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

/** Categories, brands, units and taxes share one generic CRUD screen (config/masters.php). */
final class MasterController extends Controller
{
    private function def(string $type): array
    {
        return config('masters')[$type] ?? $this->notFound();
    }

    private function record(array $def, string $id): array
    {
        return DB::one("SELECT * FROM `{$def['table']}` WHERE tenant_id = ? AND id = ?", [Auth::tenantId(), (int)$id]) ?? $this->notFound();
    }

    public function index(string $type): void
    {
        $def = $this->def($type);
        $rows = DB::all(
            "SELECT m.*, (SELECT COUNT(*) FROM items i WHERE i.tenant_id = m.tenant_id AND i.{$def['used_by']} = m.id) AS used
             FROM `{$def['table']}` m WHERE m.tenant_id = ? ORDER BY m.name", [Auth::tenantId()]);
        $this->view('app/masters/index', ['title' => $def['title'], 'type' => $type, 'def' => $def, 'rows' => $rows, 'tabs' => config('masters')]);
    }

    public function create(string $type): void
    {
        $def = $this->def($type);
        $this->view('app/masters/form', ['title' => 'New ' . strtolower($def['one']), 'type' => $type, 'def' => $def, 'row' => null]);
    }

    private function collect(array $def, string $type, ?int $exceptId, string $back): array
    {
        $d = $this->input();
        $data = [];
        foreach ($def['fields'] as [$col, $label, $kind, $required]) {
            $v = trim((string)($d[$col] ?? ''));
            if ($kind === 'checkbox') {
                $data[$col] = $v === '1' ? 1 : 0;
                continue;
            }
            if ($required && $v === '') $this->fail("$label is required.", $d, $back);
            if ($kind === 'number') {
                if (!is_numeric($v) || (float)$v < 0 || (float)$v > 999) $this->fail("$label must be a number between 0 and 999.", $d, $back);
                $v = (string)round((float)$v, 2);
            } elseif (mb_strlen($v) > 100) {
                $this->fail("$label is too long.", $d, $back);
            }
            $data[$col] = $v;
        }
        if (DB::val("SELECT 1 FROM `{$def['table']}` WHERE tenant_id = ? AND name = ? AND id <> ?", [Auth::tenantId(), $data['name'], $exceptId ?? 0])) {
            $this->fail("A {$def['one']} named \"{$data['name']}\" already exists.", $d, $back);
        }
        return $data;
    }

    private function fail(string $msg, array $d, string $back): never
    {
        flash('danger', $msg);
        with_old($d);
        redirect($back);
    }

    public function store(string $type): void
    {
        $def = $this->def($type);
        $data = $this->collect($def, $type, null, "masters/$type/create");
        $id = DB::insert($def['table'], ['tenant_id' => Auth::tenantId()] + $data);
        Audit::log($type . '_create', $type, $id, $data['name']);
        flash('success', $def['one'] . ' added.');
        redirect("masters/$type");
    }

    public function edit(string $type, string $id): void
    {
        $def = $this->def($type);
        $this->view('app/masters/form', ['title' => 'Edit ' . strtolower($def['one']), 'type' => $type, 'def' => $def, 'row' => $this->record($def, $id)]);
    }

    public function update(string $type, string $id): void
    {
        $def = $this->def($type);
        $row = $this->record($def, $id);
        $data = $this->collect($def, $type, (int)$row['id'], "masters/$type/{$row['id']}/edit");
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run("UPDATE `{$def['table']}` SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), Auth::tenantId(), $row['id']]);
        Audit::log($type . '_update', $type, (int)$row['id']);
        flash('success', $def['one'] . ' updated.');
        redirect("masters/$type");
    }

    public function destroy(string $type, string $id): void
    {
        $def = $this->def($type);
        $row = $this->record($def, $id);
        $used = (int)DB::val("SELECT COUNT(*) FROM items WHERE tenant_id = ? AND {$def['used_by']} = ?", [Auth::tenantId(), $row['id']]);
        if ($used > 0) {
            flash('danger', "Cannot delete: $used item(s) use this {$def['one']}.");
            redirect("masters/$type");
        }
        DB::run("DELETE FROM `{$def['table']}` WHERE tenant_id = ? AND id = ?", [Auth::tenantId(), $row['id']]);
        Audit::log($type . '_delete', $type, (int)$row['id'], $row['name']);
        flash('success', $def['one'] . ' deleted.');
        redirect("masters/$type");
    }
}
