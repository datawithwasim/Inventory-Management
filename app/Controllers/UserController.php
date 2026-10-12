<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Scope;

final class UserController extends Controller
{
    /** Roles the current user may hand out: only owners can assign the Owner role. */
    private function assignableRoles(): array
    {
        $me = Auth::user();
        // owners hand out any role; a warehouse user can only add people to their own warehouse's kind of role
        return array_values(array_filter(Role::all('name'), fn($r) => (!$r['is_owner'] || $me['is_owner']) && (!Scope::restricted() || $r['warehouse_bound'])));
    }

    private function warehouseChoices(): array
    {
        return DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1' . Scope::sql('id') . ' ORDER BY is_default DESC, name', [(int)Auth::tenantId()]);
    }

    /** Which warehouse the person works at: a chosen one, or null for head office (all warehouses). Operators / incharges must have one. */
    private function warehouseOf(array $d, int $roleId, string $back): ?int
    {
        if (Scope::restricted()) return Scope::warehouse();
        $role = Role::find($roleId);
        if ($role && $role['is_owner']) return null;
        $raw = $d['warehouse_id'] ?? null;
        if ($raw === null || $raw === '') {
            flash('danger', 'Choose the warehouse this person works at (or "All warehouses" for head office).');
            with_old($d);
            redirect($back);
        }
        if ((string)$raw === '0') {
            if ($role && $role['warehouse_bound']) {
                flash('danger', 'People on the role "' . $role['name'] . '" belong to one warehouse. Choose it.');
                with_old($d);
                redirect($back);
            }
            return null;
        }
        if (!DB::val('SELECT 1 FROM warehouses WHERE tenant_id = ? AND id = ? AND is_active = 1', [(int)Auth::tenantId(), (int)$raw])) {
            flash('danger', 'Choose a valid warehouse.');
            with_old($d);
            redirect($back);
        }
        return (int)$raw;
    }

    private function limitReached(): bool
    {
        $max = (int)Auth::user()['max_users'];
        return $max > 0 && User::count() >= $max;
    }

    private function loadManageable(int $id): array
    {
        $user = User::withRole($id) ?? $this->notFound();
        if ($user['is_owner'] && !Auth::user()['is_owner']) $this->forbidden();
        if (Scope::restricted() && (int)$user['warehouse_id'] !== Scope::warehouse()) $this->notFound();
        return $user;
    }

    public function index(): void
    {
        $users = User::withRoles();
        if (Scope::restricted()) $users = array_values(array_filter($users, fn($u) => (int)$u['warehouse_id'] === Scope::warehouse()));
        $this->view('app/users/index', ['title' => 'Users', 'users' => $users, 'limitReached' => $this->limitReached()]);
    }

    public function create(): void
    {
        if ($this->limitReached()) {
            flash('warning', 'Your plan\'s user limit is reached. Upgrade your plan to add more users.');
            redirect('users');
        }
        $this->view('app/users/form', ['title' => 'Add user', 'user' => null, 'roles' => $this->assignableRoles(), 'warehouses' => $this->warehouseChoices(), 'restricted' => Scope::restricted()]);
    }

    public function store(): void
    {
        if ($this->limitReached()) redirect('users');
        $d = $this->validate([
            'name' => 'required|max:120', 'email' => 'required|email|max:190',
            'password' => 'required|min:8', 'role_id' => 'required|int',
        ], 'users/create');
        $email = strtolower(trim($d['email']));
        $roleIds = array_column($this->assignableRoles(), 'id');
        if (!in_array((int)$d['role_id'], array_map('intval', $roleIds), true)) $this->forbidden();
        if (User::emailTaken($email)) {
            flash('danger', 'That email is already in use.');
            with_old($d);
            redirect('users/create');
        }
        $wh = $this->warehouseOf($d, (int)$d['role_id'], 'users/create');
        $id = User::create([
            'name' => trim($d['name']), 'email' => $email, 'role_id' => (int)$d['role_id'], 'warehouse_id' => $wh,
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
        ]);
        Audit::log('user_create', 'user', $id, $email);
        flash('success', 'User added.');
        redirect('users');
    }

    public function edit(string $id): void
    {
        $user = $this->loadManageable((int)$id);
        $this->view('app/users/form', ['title' => 'Edit user', 'user' => $user, 'roles' => $this->assignableRoles(), 'warehouses' => $this->warehouseChoices(), 'restricted' => Scope::restricted()]);
    }

    public function update(string $id): void
    {
        $user = $this->loadManageable((int)$id);
        $d = $this->validate(['name' => 'required|max:120', 'email' => 'required|email|max:190', 'role_id' => 'required|int'], "users/$id/edit");
        $email = strtolower(trim($d['email']));
        if (User::emailTaken($email, (int)$user['id'])) {
            flash('danger', 'That email is already in use.');
            redirect("users/$id/edit");
        }
        $roleIds = array_map('intval', array_column($this->assignableRoles(), 'id'));
        $roleId = (int)$d['role_id'];
        if (!in_array($roleId, $roleIds, true)) $this->forbidden();
        // The last Owner must stay an Owner.
        if ($user['is_owner'] && $roleId !== (int)$user['role_id'] && $this->ownerCount() <= 1) {
            flash('danger', 'The company needs at least one Owner.');
            redirect("users/$id/edit");
        }
        $data = ['name' => trim($d['name']), 'email' => $email, 'role_id' => $roleId, 'warehouse_id' => $this->warehouseOf($d + ['warehouse_id' => $user['warehouse_id'] ?? '0'], $roleId, "users/$id/edit")];
        if (($d['password'] ?? '') !== '') {
            if (strlen($d['password']) < 8) {
                flash('danger', 'Password must be at least 8 characters.');
                redirect("users/$id/edit");
            }
            $data['password_hash'] = password_hash($d['password'], PASSWORD_DEFAULT);
        }
        User::update((int)$user['id'], $data);
        Audit::log('user_update', 'user', (int)$user['id']);
        flash('success', 'User updated.');
        redirect('users');
    }

    public function toggle(string $id): void
    {
        $user = $this->loadManageable((int)$id);
        if ((int)$user['id'] === (int)Auth::user()['id']) {
            flash('danger', 'You cannot deactivate your own account.');
            redirect('users');
        }
        if ($user['is_owner'] && $user['is_active'] && $this->activeOwnerCount() <= 1) {
            flash('danger', 'The company needs at least one active Owner.');
            redirect('users');
        }
        User::update((int)$user['id'], ['is_active' => $user['is_active'] ? 0 : 1]);
        Audit::log($user['is_active'] ? 'user_deactivate' : 'user_activate', 'user', (int)$user['id']);
        flash('success', $user['is_active'] ? 'User deactivated.' : 'User activated.');
        redirect('users');
    }

    public function destroy(string $id): void
    {
        $user = $this->loadManageable((int)$id);
        if ((int)$user['id'] === (int)Auth::user()['id']) {
            flash('danger', 'You cannot delete your own account.');
            redirect('users');
        }
        if ($user['is_owner'] && $this->ownerCount() <= 1) {
            flash('danger', 'The company needs at least one Owner.');
            redirect('users');
        }
        User::delete((int)$user['id']);
        Audit::log('user_delete', 'user', (int)$user['id'], $user['email']);
        flash('success', 'User deleted.');
        redirect('users');
    }

    private function ownerCount(): int
    {
        return count(array_filter(User::withRoles(), fn($u) => $u['is_owner']));
    }

    private function activeOwnerCount(): int
    {
        return count(array_filter(User::withRoles(), fn($u) => $u['is_owner'] && $u['is_active']));
    }
}
