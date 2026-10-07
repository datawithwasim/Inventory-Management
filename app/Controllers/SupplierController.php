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
        $this->view('app/suppliers/form', ['title' => 'New ' . term('supplier', true), 'row' => null, 'cfFields' => CustomFields::fields('supplier'), 'cfValues' => CustomFields::formValues('supplier', null), 'contacts' => $_SESSION['_old']['contacts'] ?? []]);
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
        $type = trim((string)($d['supplier_type'] ?? ''));
        if ($type !== '' && !isset(Purchase::SUPPLIER_TYPES[$type])) $this->bounce('Choose a valid supplier type.', $back);
        $money = function (string $k, string $label) use ($d, $back): float {
            $v = trim((string)($d[$k] ?? ''));
            if ($v !== '' && (!is_numeric($v) || (float)$v < 0)) $this->bounce("$label must be zero or more.", $back);
            return (float)$v;
        };
        $lead = trim((string)($d['lead_time_days'] ?? '0'));
        if ($lead !== '' && (!ctype_digit($lead) || (int)$lead > 365)) $this->bounce('Lead time must be 0 to 365 days.', $back);
        $pin = $this->text($d['pincode'] ?? '', 12, 'Pincode', $back);
        if ($pin !== null && !preg_match('/^[0-9A-Za-z \-]{3,12}$/', $pin)) $this->bounce('Pincode is not valid.', $back);
        $pan = $this->text($d['pan'] ?? '', 20, 'PAN', $back);
        if ($pan !== null) $pan = strtoupper($pan);
        $row = [
            'name' => $name, 'supplier_type' => $type !== '' ? $type : null, 'pan' => $pan,
            'city' => $this->text($d['city'] ?? '', 80, 'City', $back), 'state' => $this->text($d['state'] ?? '', 80, 'State', $back), 'pincode' => $pin,
            'ship_address' => $this->text($d['ship_address'] ?? '', 255, 'Dispatch address', $back), 'transport' => $this->text($d['transport'] ?? '', 100, 'Transport', $back),
            'bank_name' => $this->text($d['bank_name'] ?? '', 100, 'Bank name', $back), 'bank_account' => $this->text($d['bank_account'] ?? '', 40, 'Account number', $back),
            'bank_ifsc' => ($ifsc = $this->text($d['bank_ifsc'] ?? '', 20, 'IFSC', $back)) !== null ? strtoupper($ifsc) : null,
            'credit_limit' => $money('credit_limit', 'Credit limit'), 'lead_time_days' => (int)$lead, 'contact_person' => $this->text($d['contact_person'] ?? '', 100, 'Contact person', $back),
            'phone' => $this->text($d['phone'] ?? '', 40, 'Phone', $back), 'email' => $email,
            'address' => $this->text($d['address'] ?? '', 255, 'Address', $back), 'tax_no' => $this->text($d['tax_no'] ?? '', 40, 'Tax number', $back),
            'payment_terms_days' => (int)$days, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back),
        ];
        [$row, $errs] = \Core\FormFields::apply('supplier', $row);
        if ($errs) $this->bounce(implode(' ', $errs), $back);
        return $row;
    }

    /** Extra contact people posted as contacts[n][name|role|phone|email]. */
    private function contacts(string $back): array
    {
        $out = [];
        foreach (array_slice((array)($this->input()['contacts'] ?? []), 0, 20) as $c) {
            if (!is_array($c)) continue;
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '' && trim(implode('', array_map('strval', $c))) === '') continue;
            if ($name === '') $this->bounce('Each extra contact needs a name.', $back);
            $email = trim((string)($c['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->bounce("Contact $name: email is not valid.", $back);
            $out[] = ['name' => mb_substr($name, 0, 100), 'role' => mb_substr(trim((string)($c['role'] ?? '')), 0, 60) ?: null,
                'phone' => mb_substr(trim((string)($c['phone'] ?? '')), 0, 40) ?: null, 'email' => $email !== '' ? mb_substr($email, 0, 190) : null];
        }
        return $out;
    }

    private function saveContacts(int $supplierId, array $contacts): void
    {
        DB::run('DELETE FROM supplier_contacts WHERE tenant_id = ? AND supplier_id = ?', [$this->tid(), $supplierId]);
        foreach ($contacts as $c) DB::insert('supplier_contacts', ['tenant_id' => $this->tid(), 'supplier_id' => $supplierId] + $c);
    }

    public function store(): void
    {
        $data = $this->fields('suppliers/create', null);
        [$cf, $cfErr] = CustomFields::validate('supplier', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), 'suppliers/create');
        $contacts = $this->contacts('suppliers/create');
        $id = DB::insert('suppliers', ['tenant_id' => $this->tid()] + $data);
        $this->saveContacts($id, $contacts);
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
            'contacts' => DB::all('SELECT * FROM supplier_contacts WHERE tenant_id = ? AND supplier_id = ? ORDER BY id', [$t, $s['id']]),
            'rates' => Purchase::ratesOf((int)$s['id']), 'products' => Purchase::productsOf((int)$s['id']),
            'bills' => DB::all('SELECT * FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 20', [$t, $s['id']]),
        ]);
    }

    public function edit(string $id): void
    {
        $s = $this->load($id);
        $this->view('app/suppliers/form', ['title' => 'Edit ' . term('supplier', true), 'row' => $s, 'cfFields' => CustomFields::fields('supplier'), 'cfValues' => CustomFields::formValues('supplier', (int)$s['id']),
            'contacts' => $_SESSION['_old']['contacts'] ?? DB::all('SELECT * FROM supplier_contacts WHERE tenant_id = ? AND supplier_id = ? ORDER BY id', [$this->tid(), $s['id']])]);
    }

    public function update(string $id): void
    {
        $s = $this->load($id);
        $data = $this->fields("suppliers/{$s['id']}/edit", (int)$s['id']);
        [$cf, $cfErr] = CustomFields::validate('supplier', $this->input(), (int)$id);
        if ($cfErr) $this->bounce(implode(' ', $cfErr), "suppliers/{$s['id']}/edit");
        $contacts = $this->contacts("suppliers/{$s['id']}/edit");
        $this->saveContacts((int)$s['id'], $contacts);
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
