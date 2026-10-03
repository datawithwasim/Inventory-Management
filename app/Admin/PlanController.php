<?php
declare(strict_types=1);

namespace App\Admin;

use Core\Audit;
use Core\Controller;
use Core\DB;

final class PlanController extends Controller
{
    private const RULES = [
        'name' => 'required|max:80', 'max_users' => 'required|int', 'max_items' => 'required|int',
        'max_warehouses' => 'required|int', 'price' => 'required|numeric', 'trial_days' => 'required|int',
    ];

    private function load(string $id): array
    {
        return DB::one('SELECT * FROM plans WHERE id = ?', [(int)$id]) ?? $this->notFound();
    }

    private function fields(array $d): array
    {
        return [
            'name' => trim($d['name']),
            'max_users' => max(0, (int)$d['max_users']), 'max_items' => max(0, (int)$d['max_items']),
            'max_warehouses' => max(0, (int)$d['max_warehouses']),
            'price' => max(0, (float)$d['price']), 'trial_days' => max(0, (int)$d['trial_days']),
            'is_active' => empty($d['is_active']) ? 0 : 1,
        ];
    }

    public function index(): void
    {
        $this->view('admin/plans/index', [
            'title' => 'Plans',
            'plans' => DB::all('SELECT p.*, (SELECT COUNT(*) FROM tenants t WHERE t.plan_id = p.id) AS tenant_count FROM plans p ORDER BY p.price'),
        ], 'layouts/admin');
    }

    public function create(): void
    {
        $this->view('admin/plans/form', ['title' => 'New plan', 'plan' => null], 'layouts/admin');
    }

    public function store(): void
    {
        $d = $this->validate(self::RULES, 'admin/plans/create');
        $data = $this->fields($d);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $id = DB::insert('plans', $data);
        Audit::log('plan_create', 'plan', $id, $data['name']);
        flash('success', 'Plan created.');
        redirect('admin/plans');
    }

    public function edit(string $id): void
    {
        $this->view('admin/plans/form', ['title' => 'Edit plan', 'plan' => $this->load($id)], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $plan = $this->load($id);
        $d = $this->validate(self::RULES, "admin/plans/$id/edit");
        $set = $this->fields($d);
        DB::run('UPDATE plans SET name=?, max_users=?, max_items=?, max_warehouses=?, price=?, trial_days=?, is_active=? WHERE id=?',
            [...array_values($set), $plan['id']]);
        Audit::log('plan_update', 'plan', (int)$plan['id'], $set['name']);
        flash('success', 'Plan updated.');
        redirect('admin/plans');
    }

    private function uniqueSlug(string $name): string
    {
        $base = slugify($name);
        $slug = $base;
        for ($i = 2; DB::val('SELECT 1 FROM plans WHERE slug = ?', [$slug]); $i++) $slug = "$base-$i";
        return $slug;
    }
}
