<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomFields;
use App\Models\Purchase;
use Core\Audit;
use Core\DB;

final class SupplierController extends PurchaseBase
{
    private function load(string $id): array
    {
        return Purchase::supplier((int)$id) ?? $this->notFound();
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 's.tenant_id = ?';
        $params = [$this->tid()];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (s.name LIKE ? OR s.phone LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $rows = DB::all(
            "SELECT s.*, COALESCE((SELECT SUM(b.total - b.returned_amount - b.paid_amount) FROM purchase_bills b WHERE b.supplier_id = s.id), 0) AS outstanding
             FROM suppliers s WHERE $where ORDER BY s.name LIMIT 500", $params);
        $cols = \Core\Columns::visible('suppliers');
        $cfList = CustomFields::attachList('supplier', $rows, \Core\Columns::cfIds($cols));
        $this->view('app/suppliers/index', ['title' => term('suppliers'), 'cols' => $cols, 'cfList' => $cfList, 'rows' => $rows, 'q' => $q]);
    }

    public function create(): void
    {
        $this->view('app/suppliers/form', ['title' => 'New ' . term('supplier', true), 'row' => null, 'cfFields' => CustomFields::fields('supplier'), 'cfValues' => CustomFields::formValues('supplier', null)]);
    }

    private function fields(string $back, ?int $exceptId): array
    {
        $d = \Core\FormFields::strip('supplier', $this->input());
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') $this->bounce('Supplier name is required.', $back);
        if (mb_strlen($name) > 150) $this->bounce('Supplier name is too long.', $back);
        if (DB::val('SELECT 1 FROM suppliers WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId ?? 0])) {
            $this->bounce('A supplier with that name already exists.', $back);
        }
        $email = $this->text($d['email'] ?? '', 190, 'Email', $back);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->bounce('Email is not valid.', $back);
        $days = trim((string)($d['payment_terms_days'] ?? '0'));
        if ($days !== '' && (!ctype_digit($days) || (int)$days > 365)) $this->bounce('Payment terms must be 0 to 365 days.', $back);
        $row = [
            'name' => $name, 'contact_person' => $this->text($d['contact_person'] ?? '', 100, 'Contact person', $back),
            'phone' => $this->text($d['phone'] ?? '', 40, 'Phone', $back), 'email' => $email,
            'address' => $this->text($d['address'] ?? '', 255, 'Address', $back), 'tax_no' => $this->text($d['tax_no'] ?? '', 40, 'Tax number', $back),
            'payment_terms_days' => (int)$days, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back),
        ];
        [$row, $errs] = \Core\FormFields::apply('supplier', $row);
        if ($errs) $this->bounce(implode(' ', $errs), $back);
        return $row;
    }

    public function store(): void
    {
        $data = $this->fields('suppliers/create', null);
        [$cf, $cfErr] = CustomFields::validate('supplier', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), 'suppliers/create');
        $id = DB::insert('suppliers', ['tenant_id' => $this->tid()] + $data);
        CustomFields::save('supplier', $id, $cf);
        Audit::log('supplier_create', 'supplier', $id, $data['name']);
        flash('success', 'Supplier added.');
        redirect("suppliers/$id");
    }

    public function show(string $id): void
    {
        $s = $this->load($id);
        $t = $this->tid();
        $this->view('app/suppliers/show', [
            'title' => $s['name'], 's' => $s, 'cfFields' => CustomFields::fields('supplier'), 'cfValues' => CustomFields::values('supplier', (int)$s['id']), 'outstanding' => Purchase::supplierOutstanding((int)$s['id']),
            'pos' => DB::all('SELECT id, po_no, order_date, status, total FROM purchase_orders WHERE tenant_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 10', [$t, $s['id']]),
            'grns' => DB::all('SELECT id, grn_no, received_date FROM grns WHERE tenant_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 10', [$t, $s['id']]),
            'bills' => DB::all('SELECT * FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 20', [$t, $s['id']]),
        ]);
    }

    public function edit(string $id): void
    {
        $s = $this->load($id);
        $this->view('app/suppliers/form', ['title' => 'Edit ' . term('supplier', true), 'row' => $s, 'cfFields' => CustomFields::fields('supplier'), 'cfValues' => CustomFields::formValues('supplier', (int)$s['id'])]);
    }

    public function update(string $id): void
    {
        $s = $this->load($id);
        $data = $this->fields("suppliers/{$s['id']}/edit", (int)$s['id']);
        [$cf, $cfErr] = CustomFields::validate('supplier', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), "suppliers/{$s['id']}/edit");
        CustomFields::save('supplier', (int)$s['id'], $cf);
        $data['is_active'] = empty($this->input()['is_active']) ? 0 : 1;
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        DB::run("UPDATE suppliers SET $set WHERE tenant_id = ? AND id = ?", [...array_values($data), $this->tid(), $s['id']]);
        Audit::log('supplier_update', 'supplier', (int)$s['id']);
        flash('success', 'Supplier updated.');
        redirect("suppliers/{$s['id']}");
    }

    public function destroy(string $id): void
    {
        $s = $this->load($id);
        $t = $this->tid();
        $used = DB::val('SELECT 1 FROM purchase_orders WHERE tenant_id = ? AND supplier_id = ? LIMIT 1', [$t, $s['id']])
            || DB::val('SELECT 1 FROM grns WHERE tenant_id = ? AND supplier_id = ? LIMIT 1', [$t, $s['id']])
            || DB::val('SELECT 1 FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ? LIMIT 1', [$t, $s['id']]);
        if ($used) {
            flash('danger', 'This supplier has purchase history and cannot be deleted. Mark it inactive instead.');
            redirect("suppliers/{$s['id']}");
        }
        DB::run('DELETE FROM suppliers WHERE tenant_id = ? AND id = ?', [$t, $s['id']]);
        Audit::log('supplier_delete', 'supplier', (int)$s['id'], $s['name']);
        flash('success', 'Supplier deleted.');
        redirect('suppliers');
    }
}
