<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\DB;

/** Customer groups (Retail, Dealer, Designer…) with a default discount and an optional price list. */
final class CustomerGroupController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one('SELECT * FROM customer_groups WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $this->view('app/customers/groups', ['title' => 'Customer groups', 'rows' => DB::all(
            'SELECT g.*, (SELECT COUNT(*) FROM customers c WHERE c.group_id = g.id) AS customers, (SELECT COUNT(*) FROM group_prices p WHERE p.group_id = g.id) AS prices
             FROM customer_groups g WHERE g.tenant_id = ? ORDER BY g.name', [$this->tid()])]);
    }

    public function create(): void
    {
        $this->view('app/customers/group_form', ['title' => 'New customer group', 'row' => null, 'prices' => []]);
    }

    private function fields(string $back, ?int $exceptId): array
    {
        $d = $this->input();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) $this->bounce('Group name is required (max 80 characters).', $back);
        if (DB::val('SELECT 1 FROM customer_groups WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId ?? 0])) $this->bounce('A group with that name already exists.', $back);
        return ['name' => $name, 'discount_pct' => $this->percent($d['discount_pct'] ?? '', 'Discount', $back)];
    }

    public function store(): void
    {
        $data = $this->fields('customers/groups/create', null);
        $id = DB::insert('customer_groups', ['tenant_id' => $this->tid()] + $data);
        Audit::log('customer_group_create', 'customer_group', $id, $data['name']);
        flash('success', 'Group created. You can now add special prices for it.');
        redirect("customers/groups/$id/edit");
    }

    public function edit(string $id): void
    {
        $g = $this->load($id);
        $this->view('app/customers/group_form', ['title' => 'Edit group', 'row' => $g, 'prices' => DB::all(
            'SELECT p.*, v.sku, v.name AS vname, v.sale_price, i.name AS item_name FROM group_prices p JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id
             WHERE p.tenant_id = ? AND p.group_id = ? ORDER BY i.name, v.name', [$this->tid(), $g['id']])]);
    }

    public function update(string $id): void
    {
        $g = $this->load($id);
        $data = $this->fields("customers/groups/{$g['id']}/edit", (int)$g['id']);
        DB::run('UPDATE customer_groups SET name = ?, discount_pct = ? WHERE tenant_id = ? AND id = ?', [$data['name'], $data['discount_pct'], $this->tid(), $g['id']]);
        Audit::log('customer_group_update', 'customer_group', (int)$g['id']);
        flash('success', 'Group updated.');
        redirect("customers/groups/{$g['id']}/edit");
    }

    public function setPrice(string $id): void
    {
        $g = $this->load($id);
        $back = "customers/groups/{$g['id']}/edit";
        $d = $this->input();
        $v = $this->sellable((int)($d['variant_id'] ?? 0)) ?? $this->bounce('Search and choose an item first.', $back);
        $price = trim((string)($d['price'] ?? ''));
        if ($price === '' || !is_numeric($price) || (float)$price < 0) $this->bounce('Enter the price for this group.', $back);
        DB::run('INSERT INTO group_prices (tenant_id, group_id, variant_id, price) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE price = VALUES(price)', [$this->tid(), $g['id'], $v['id'], round((float)$price, 2)]);
        flash('success', 'Price saved.');
        redirect($back);
    }

    public function deletePrice(string $id, string $priceId): void
    {
        $g = $this->load($id);
        DB::run('DELETE FROM group_prices WHERE tenant_id = ? AND group_id = ? AND id = ?', [$this->tid(), $g['id'], (int)$priceId]);
        flash('success', 'Price removed.');
        redirect("customers/groups/{$g['id']}/edit");
    }

    public function destroy(string $id): void
    {
        $g = $this->load($id);
        DB::run('DELETE FROM customer_groups WHERE tenant_id = ? AND id = ?', [$this->tid(), $g['id']]);
        Audit::log('customer_group_delete', 'customer_group', (int)$g['id'], $g['name']);
        flash('success', 'Group deleted. Its customers now have no group.');
        redirect('customers/groups');
    }
}
