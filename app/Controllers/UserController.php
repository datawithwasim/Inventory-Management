<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use Core\Audit;
use Core\Auth;
use Core\Controller;

final class UserController extends Controller
{
    /** Roles the current user may hand out: only owners can assign the Owner role. */
    private function assignableRoles(): array
    {
        $me = Auth::user();
        return array_values(array_filter(Role::all('name'), fn($r) => !$r['is_owner'] || $me['is_owner']));
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
        return $user;
    }

    public function index(): void
    {
        $this->view('app/users/index', ['title' => 'Users', 'users' => User::withRoles(), 'limitReached' => $this->limitReached()]);
    }

    public function create(): void
    {
        if ($this->limitReached()) {
            flash('warning', 'Your plan\'s user limit is reached. Upgrade your plan to add more users.');
            redirect('users');
        }
        $this->view('app/users/form', ['title' => 'Add user', 'user' => null, 'roles' => $this->assignableRoles()]);
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
        $id = User::create([
            'name' => trim($d['name']), 'email' => $email, 'role_id' => (int)$d['role_id'],
            'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
        ]);
        Audit::log('user_create', 'user', $id, $email);
        flash('success', 'User added.');
        redirect('users');
    }

    public function edit(string $id): void
    {
        $user = $this->loadManageable((int)$id);
        $this->view('app/users/form', ['title' => 'Edit user', 'user' => $user, 'roles' => $this->assignableRoles()]);
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
        $data = ['name' => trim($d['name']), 'email' => $email, 'role_id' => $roleId];
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
