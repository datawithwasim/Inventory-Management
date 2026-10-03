<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Sales;
use Core\Audit;
use Core\Auth;
use Core\DB;

/** Sales invoices (one per delivery) and the payments received. Basic receivable tracking only. */
final class InvoiceController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT i.*, c.name AS customer, c.address AS cust_address, c.phone AS cust_phone, c.tax_no AS cust_tax, d.delivery_no, d.ship_to, o.order_no
             FROM sales_invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN deliveries d ON d.id = i.delivery_id LEFT JOIN sales_orders o ON o.id = i.order_id
             WHERE i.tenant_id = ? AND i.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $customer = (int)($_GET['customer'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'i.tenant_id = ?';
        $params = [$this->tid()];
        $due = '(i.total - i.returned_amount - i.paid_amount)';
        $where .= match ($status) {
            'unpaid' => " AND i.paid_amount <= 0.004 AND $due > 0.004",
            'partial' => " AND i.paid_amount > 0.004 AND $due > 0.004",
            'paid' => " AND $due <= 0.004",
            'overdue' => " AND $due > 0.004 AND i.due_date IS NOT NULL AND i.due_date < CURDATE()",
            default => '',
        };
        if ($customer) { $where .= ' AND i.customer_id = ?'; $params[] = $customer; }
        if ($q !== '') { $where .= ' AND i.invoice_no LIKE ?'; $params[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        [$page, $pages, $limit] = $this->pageOf('sales_invoices i', $where, $params);
        $rows = DB::all("SELECT i.*, c.name AS customer FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE $where ORDER BY i.id DESC $limit", $params);
        $totalDue = (float)DB::val("SELECT COALESCE(SUM($due),0) FROM sales_invoices i WHERE $where", $params);
        $this->view('app/sales/invoice_index', ['title' => 'Sales invoices', 'rows' => $rows, 'status' => $status, 'customer' => $customer, 'q' => $q,
            'customers' => $this->customers(false), 'page' => $page, 'pages' => $pages, 'totalDue' => $totalDue]);
    }

    private function lines(array $inv): array
    {
        return $inv['delivery_id'] ? DeliveryController::rows((int)$inv['delivery_id'], $this->tid()) : [];
    }

    public function show(string $id): void
    {
        $i = $this->load($id);
        $t = $this->tid();
        $this->view('app/sales/invoice_show', [
            'title' => $i['invoice_no'], 'i' => $i, 'due' => Purchase::outstanding($i), 'status' => Purchase::payStatus($i), 'items' => $this->lines($i),
            'payments' => DB::all('SELECT p.*, u.name AS user_name FROM customer_payments p LEFT JOIN users u ON u.id = p.created_by WHERE p.tenant_id = ? AND p.invoice_id = ? ORDER BY p.paid_on, p.id', [$t, $i['id']]),
            'returns' => DB::all('SELECT id, return_no, return_date, total FROM sales_returns WHERE tenant_id = ? AND invoice_id = ? ORDER BY id', [$t, $i['id']]),
        ]);
    }

    /** Print-friendly page. ?receipt=1 gives a narrow counter receipt. */
    public function print(string $id): void
    {
        $i = $this->load($id);
        $this->view('app/sales/invoice_print', [
            'title' => $i['invoice_no'], 'i' => $i, 'items' => $this->lines($i), 'receipt' => !empty($_GET['receipt']), 'due' => Purchase::outstanding($i),
            'company' => Auth::user()['tenant_name'],
        ], 'layouts/print');
    }

    public function edit(string $id): void
    {
        $this->view('app/sales/invoice_form', ['title' => 'Edit invoice', 'i' => $this->load($id)]);
    }

    public function update(string $id): void
    {
        $i = $this->load($id);
        $back = "sales/invoices/{$i['id']}/edit";
        $d = $this->input();
        $date = $this->date($d['invoice_date'] ?? '', 'Invoice date', $back);
        $dueDate = $this->date($d['due_date'] ?? '', 'Due date', $back, false);
        if ($dueDate !== null && $dueDate < $date) $this->bounce('Due date cannot be before the invoice date.', $back);
        $delivery = $this->money($d['delivery_charge'] ?? '', 'Delivery charge', $back);
        $install = $this->money($d['installation_charge'] ?? '', 'Installation charge', $back);
        $total = round((float)$i['subtotal'] - (float)$i['discount_total'] + (float)$i['tax_total'] + $delivery + $install, 2);
        if ($total + 0.004 < (float)$i['paid_amount']) $this->bounce('The total cannot be less than what is already paid.', $back);
        DB::run('UPDATE sales_invoices SET invoice_date = ?, due_date = ?, delivery_charge = ?, installation_charge = ?, total = ?, notes = ? WHERE tenant_id = ? AND id = ?',
            [$date, $dueDate, $delivery, $install, $total, $this->text($d['notes'] ?? '', 255, 'Notes', $back), $this->tid(), $i['id']]);
        Audit::log('invoice_update', 'sales_invoice', (int)$i['id']);
        flash('success', 'Invoice updated.');
        redirect("sales/invoices/{$i['id']}");
    }

    public function pay(string $id): void
    {
        $i = $this->load($id);
        $back = "sales/invoices/{$i['id']}";
        $d = $this->input();
        $amount = $this->money($d['amount'] ?? '', 'Amount', $back);
        if ($amount <= 0) $this->bounce('Enter the amount received.', $back);
        $due = Purchase::outstanding($i);
        if ($amount > $due + 0.004) $this->bounce('That is more than the balance due (' . number_format(max(0, $due), 2) . ').', $back);
        $method = (string)($d['method'] ?? 'cash');
        if (!isset(Purchase::METHODS[$method])) $this->bounce('Choose a payment method.', $back);
        DB::transaction(function () use ($i, $amount, $method, $d, $back) {
            DB::insert('customer_payments', ['tenant_id' => $this->tid(), 'customer_id' => $i['customer_id'], 'invoice_id' => $i['id'], 'amount' => $amount,
                'paid_on' => $this->date($d['paid_on'] ?? '', 'Payment date', $back), 'method' => $method, 'reference' => $this->text($d['reference'] ?? '', 80, 'Reference', $back),
                'note' => $this->text($d['note'] ?? '', 150, 'Note', $back), 'created_by' => Auth::user()['id']]);
            Sales::refreshPaid((int)$i['id']);
        });
        Audit::log('customer_payment', 'sales_invoice', (int)$i['id'], number_format($amount, 2));
        flash('success', 'Payment recorded.');
        redirect($back);
    }

    public function deletePayment(string $id, string $payId): void
    {
        $i = $this->load($id);
        $p = DB::one('SELECT * FROM customer_payments WHERE tenant_id = ? AND id = ? AND invoice_id = ?', [$this->tid(), (int)$payId, $i['id']]) ?? $this->notFound();
        DB::transaction(function () use ($p, $i) {
            // Advance money moved onto this invoice goes back to the order's advance.
            if ($p['method'] === 'advance' && $i['order_id']) {
                DB::run('UPDATE customer_payments SET applied = GREATEST(0, applied - ?) WHERE tenant_id = ? AND order_id = ? AND invoice_id IS NULL AND applied > 0 ORDER BY id DESC LIMIT 1', [$p['amount'], $this->tid(), $i['order_id']]);
            }
            DB::run('DELETE FROM customer_payments WHERE tenant_id = ? AND id = ?', [$this->tid(), $p['id']]);
            Sales::refreshPaid((int)$i['id']);
        });
        Audit::log('customer_payment_delete', 'sales_invoice', (int)$i['id'], $p['amount']);
        flash('success', 'Payment removed.');
        redirect("sales/invoices/{$i['id']}");
    }
}
