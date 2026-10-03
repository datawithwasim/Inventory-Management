<?php
declare(strict_types=1);

namespace App\Admin;

use App\Models\Tenants;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class TenantController extends Controller
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT t.*, p.name AS plan_name FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE t.id = ?',
            [(int)$id]) ?? $this->notFound();
    }

    private function plans(): array
    {
        return DB::all('SELECT * FROM plans WHERE is_active = 1 ORDER BY price');
    }

    public function index(): void
    {
        $this->view('admin/tenants/index', [
            'title' => 'Companies',
            'tenants' => DB::all(
                'SELECT t.*, p.name AS plan_name,
                        (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count
                 FROM tenants t JOIN plans p ON p.id = t.plan_id ORDER BY t.id DESC'),
        ], 'layouts/admin');
    }

    public function create(): void
    {
        $this->view('admin/tenants/form', ['title' => 'New company', 'tenant' => null, 'plans' => $this->plans()], 'layouts/admin');
    }

    public function store(): void
    {
        $d = $this->validate([
            'name' => 'required|max:150', 'owner_name' => 'required|max:120',
            'owner_email' => 'required|email|max:190', 'owner_password' => 'required|min:8',
            'plan_id' => 'required|int', 'subscription_ends_at' => 'date',
        ], 'admin/tenants/create');
        $d['owner_email'] = strtolower(trim($d['owner_email']));
        $d['status'] = in_array($d['status'] ?? '', ['trial', 'active', 'suspended'], true) ? $d['status'] : 'active';

        if (!DB::val('SELECT 1 FROM plans WHERE id = ? AND is_active = 1', [(int)$d['plan_id']])) {
            flash('danger', 'Choose a valid plan.');
            with_old($d);
            redirect('admin/tenants/create');
        }
        if (DB::val('SELECT 1 FROM users WHERE email = ?', [$d['owner_email']])) {
            flash('danger', 'That owner email is already registered.');
            with_old($d);
            redirect('admin/tenants/create');
        }
        $d['name'] = trim($d['name']);
        $d['owner_name'] = trim($d['owner_name']);
        $id = Tenants::provision($d);
        Audit::log('tenant_create', 'tenant', $id, $d['name'], $id);
        flash('success', 'Company created. The owner can now sign in.');
        redirect('admin/tenants');
    }

    public function edit(string $id): void
    {
        $this->view('admin/tenants/form', [
            'title' => 'Edit company', 'tenant' => $this->load($id), 'plans' => DB::all('SELECT * FROM plans ORDER BY price'),
            'users' => DB::all('SELECT u.name, u.email, u.is_active, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.tenant_id = ? ORDER BY u.id', [(int)$id]),
        ], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $t = $this->load($id);
        $d = $this->validate(['name' => 'required|max:150', 'plan_id' => 'required|int',
            'subscription_ends_at' => 'date', 'trial_ends_at' => 'date'], "admin/tenants/$id/edit");
        if (!DB::val('SELECT 1 FROM plans WHERE id = ?', [(int)$d['plan_id']])) {
            flash('danger', 'Choose a valid plan.');
            redirect("admin/tenants/$id/edit");
        }
        $status = in_array($d['status'] ?? '', ['trial', 'active', 'suspended'], true) ? $d['status'] : $t['status'];
        DB::run('UPDATE tenants SET name=?, plan_id=?, status=?, trial_ends_at=?, subscription_ends_at=? WHERE id=?', [
            trim($d['name']), (int)$d['plan_id'], $status,
            ($d['trial_ends_at'] ?? '') ?: null, ($d['subscription_ends_at'] ?? '') ?: null, $t['id'],
        ]);
        Audit::log('tenant_update', 'tenant', (int)$t['id'], null, (int)$t['id']);
        flash('success', 'Company updated.');
        redirect('admin/tenants');
    }

    public function status(string $id): void
    {
        $t = $this->load($id);
        $new = $t['status'] === 'suspended' ? 'active' : 'suspended';
        DB::run('UPDATE tenants SET status = ? WHERE id = ?', [$new, $t['id']]);
        Audit::log('tenant_' . ($new === 'suspended' ? 'suspend' : 'activate'), 'tenant', (int)$t['id'], null, (int)$t['id']);
        flash('success', $new === 'suspended' ? 'Company suspended.' : 'Company activated.');
        redirect('admin/tenants');
    }

    public function impersonate(string $id): void
    {
        $t = $this->load($id);
        if ($msg = Auth::tenantProblem(['tenant_status' => $t['status']] + $t)) {
            flash('danger', "Cannot open this company: $msg");
            redirect('admin/tenants');
        }
        if (!Auth::impersonate((int)$t['id'])) {
            flash('danger', 'This company has no active owner to sign in as.');
            redirect('admin/tenants');
        }
        redirect('dashboard');
    }

    public function stopImpersonating(): void
    {
        if (empty($_SESSION['impersonator_admin_id'])) redirect('login');
        Audit::log('impersonate_stop', 'tenant', Auth::tenantId());
        Auth::stopImpersonating();
        redirect('admin/tenants');
    }
}
