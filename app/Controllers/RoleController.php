<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use Core\Audit;
use Core\Controller;
use Core\DB;

final class RoleController extends Controller
{
    private function load(string $id): array
    {
        return Role::find((int)$id) ?? $this->notFound();
    }

    private function form(string $title, ?array $role): void
    {
        $this->view('app/roles/form', [
            'title' => $title, 'role' => $role,
            'modules' => config('permissions'),
            'granted' => $role ? Role::permissions((int)$role['id']) : [],
        ]);
    }

    public function index(): void
    {
        $this->view('app/roles/index', ['title' => 'Roles & permissions', 'roles' => Role::withCounts()]);
    }

    public function create(): void
    {
        $this->form('New role', null);
    }

    public function store(): void
    {
        $d = $this->validate(['name' => 'required|max:80'], 'roles/create');
        $name = trim($d['name']);
        if (DB::val('SELECT 1 FROM roles WHERE tenant_id = ? AND name = ?', [\Core\Auth::tenantId(), $name])) {
            flash('danger', 'A role with that name already exists.');
            with_old($d);
            redirect('roles/create');
        }
        $id = Role::create(['name' => $name]);
        Role::savePermissions($id, (array)($d['perms'] ?? []));
        Audit::log('role_create', 'role', $id, $name);
        flash('success', 'Role created.');
        redirect('roles');
    }

    public function edit(string $id): void
    {
        $role = $this->load($id);
        if ($role['is_owner']) {
            flash('info', 'The Owner role always has full access and cannot be edited.');
            redirect('roles');
        }
        $this->form('Edit role', $role);
    }

    public function update(string $id): void
    {
        $role = $this->load($id);
        if ($role['is_owner']) redirect('roles');
        $d = $this->validate(['name' => 'required|max:80'], "roles/$id/edit");
        $name = trim($d['name']);
        // System roles keep their names so the defaults stay recognisable.
        if (!$role['is_system'] && $name !== $role['name']) {
            if (DB::val('SELECT 1 FROM roles WHERE tenant_id = ? AND name = ? AND id <> ?', [\Core\Auth::tenantId(), $name, $role['id']])) {
                flash('danger', 'A role with that name already exists.');
                redirect("roles/$id/edit");
            }
            Role::update((int)$role['id'], ['name' => $name]);
        }
        Role::savePermissions((int)$role['id'], (array)($d['perms'] ?? []));
        Audit::log('role_update', 'role', (int)$role['id']);
        flash('success', 'Role updated.');
        redirect('roles');
    }

    public function destroy(string $id): void
    {
        $role = $this->load($id);
        if ($role['is_system']) {
            flash('danger', 'Default roles cannot be deleted.');
            redirect('roles');
        }
        if (DB::val('SELECT COUNT(*) FROM users WHERE role_id = ?', [$role['id']])) {
            flash('danger', 'Move the users on this role to another role first.');
            redirect('roles');
        }
        Role::delete((int)$role['id']);
        Audit::log('role_delete', 'role', (int)$role['id'], $role['name']);
        flash('success', 'Role deleted.');
        redirect('roles');
    }
}
