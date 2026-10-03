<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Sales;
use Core\Audit;
use Core\DB;

final class CustomerController extends SalesBase
{
    private function load(string $id): array
    {
        return Sales::customer((int)$id) ?? $this->notFound();
    }

    private function groups(): array
    {
        return DB::all('SELECT id, name, discount_pct FROM customer_groups WHERE tenant_id = ? ORDER BY name', [$this->tid()]);
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $group = (int)($_GET['group'] ?? 0);
        $where = 'c.tenant_id = ?';
        $params = [$this->tid()];
        if ($q !== '') { $where .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like, $like, $like); }
        if ($group) { $where .= ' AND c.group_id = ?'; $params[] = $group; }
        $rows = DB::all(
            "SELECT c.*, g.name AS group_name, COALESCE((SELECT SUM(i.total - i.returned_amount - i.paid_amount) FROM sales_invoices i WHERE i.customer_id = c.id), 0) AS outstanding
             FROM customers c LEFT JOIN customer_groups g ON g.id = c.group_id WHERE $where ORDER BY c.is_walkin DESC, c.name LIMIT 500", $params);
        $this->view('app/customers/index', ['title' => 'Customers', 'rows' => $rows, 'q' => $q, 'group' => $group, 'groups' => $this->groups()]);
    }

    public function create(): void
    {
        $this->view('app/customers/form', ['title' => 'New customer', 'row' => null, 'groups' => $this->groups()]);
    }

    private function fields(string $back, ?int $exceptId): array
    {
        $d = $this->input();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') $this->bounce('Customer name is required.', $back);
        if (mb_strlen($name) > 150) $this->bounce('Customer name is too long.', $back);
        if (DB::val('SELECT 1 FROM customers WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId ?? 0])) $this->bounce('A customer with that name already exists.', $back);
        $email = $this->text($d['email'] ?? '', 190, 'Email', $back);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->bounce('Email is not valid.', $back);
        $days = trim((string)($d['credit_days'] ?? '0'));
        if ($days !== '' && (!ctype_digit($days) || (int)$days > 365)) $this->bounce('Credit days must be 0 to 365.', $back);
        $group = (int)($d['group_id'] ?? 0);
        if ($group && !DB::val('SELECT 1 FROM customer_groups WHERE tenant_id = ? AND id = ?', [$this->tid(), $group])) $this->bounce('Choose a valid customer group.', $back);
        return ['name' => $name, 'group_id' => $group ?: null, 'contact_person' => $this->text($d['contact_person'] ?? '', 100, 'Contact person', $back),
            'phone' => $this->text($d['phone'] ?? '', 40, 'Phone', $back), 'email' => $email, 'address' => $this->text($d['address'] ?? '', 255, 'Address', $back),
            'ship_address' => $this->text($d['ship_address'] ?? '', 255, 'Delivery address', $back), 'tax_no' => $this->text($d['tax_no'] ?? '', 40, 'Tax number', $back),
            'credit_days' => (int)$days, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back)];
    }

    public function store(): void
    {
        $data = $this->fields('customers/create', null);
        $id = DB::insert('customers', ['tenant_id' => $this->tid()] + $data);
        Audit::log('customer_create', 'customer', $id, $data['name']);
        flash('success', 'Customer added.');
        redirect("customers/$id");
    }

    public function show(string $id): void
    {
        $c = $this->load($id);
        $t = $this->tid();
        $this->view('app/customers/show', [
            'title' => $c['name'], 'c' => $c,
            'owed' => (float)DB::val('SELECT COALESCE(SUM(total - returned_amount - paid_amount),0) FROM sales_invoices WHERE tenant_id = ? AND customer_id = ?', [$t, $c['id']]),
            'advance' => (float)DB::val('SELECT COALESCE(SUM(amount - applied),0) FROM customer_payments WHERE tenant_id = ? AND customer_id = ? AND order_id IS NOT NULL AND invoice_id IS NULL', [$t, $c['id']]),
            'orders' => DB::all('SELECT id, order_no, order_date, status, total FROM sales_orders WHERE tenant_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 10', [$t, $c['id']]),
            'invoices' => DB::all('SELECT * FROM sales_invoices WHERE tenant_id = ? AND customer_id = ? ORDER BY id DESC LIMIT 20', [$t, $c['id']]),
        ]);
    }

    public function edit(string $id): void
    {
        $this->view('app/customers/form', ['title' => 'Edit customer', 'row' => $this->load($id), 'groups' => $this->groups()]);
    }

    public function update(string $id): void
    {
        $c = $this->load($id);
        $data = $this->fields("customers/{$c['id']}/edit", (int)$c['id']);
        if ($c['is_walkin']) $data['name'] = $c['name'];
        $data['is_active'] = $c['is_walkin'] || !empty($this->input()['is_active']) ? 1 : 0;
        $set = implode(',', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        DB::run("UPDATE customers SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), $this->tid(), $c['id']]);
        Audit::log('customer_update', 'customer', (int)$c['id']);
        flash('success', 'Customer updated.');
        redirect("customers/{$c['id']}");
    }

    public function destroy(string $id): void
    {
        $c = $this->load($id);
        $t = $this->tid();
        $used = $c['is_walkin']
            || DB::val('SELECT 1 FROM sales_quotations WHERE tenant_id = ? AND customer_id = ? LIMIT 1', [$t, $c['id']])
            || DB::val('SELECT 1 FROM sales_orders WHERE tenant_id = ? AND customer_id = ? LIMIT 1', [$t, $c['id']])
            || DB::val('SELECT 1 FROM deliveries WHERE tenant_id = ? AND customer_id = ? LIMIT 1', [$t, $c['id']])
            || DB::val('SELECT 1 FROM sales_invoices WHERE tenant_id = ? AND customer_id = ? LIMIT 1', [$t, $c['id']])
            || DB::val('SELECT 1 FROM customer_payments WHERE tenant_id = ? AND customer_id = ? LIMIT 1', [$t, $c['id']]);
        if ($used) {
            flash('danger', $c['is_walkin'] ? 'The walk-in customer cannot be deleted.' : 'This customer has sales history and cannot be deleted. Mark it inactive instead.');
            redirect("customers/{$c['id']}");
        }
        DB::run('DELETE FROM customers WHERE tenant_id = ? AND id = ?', [$t, $c['id']]);
        Audit::log('customer_delete', 'customer', (int)$c['id'], $c['name']);
        flash('success', 'Customer deleted.');
        redirect('customers');
    }
}
