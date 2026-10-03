<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Supplier bills (one per goods receipt) and the payments made against them. Basic payment tracking only. */
final class BillController extends PurchaseBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT b.*, s.name AS supplier, g.grn_no FROM purchase_bills b JOIN suppliers s ON s.id = b.supplier_id
             LEFT JOIN grns g ON g.id = b.grn_id WHERE b.tenant_id = ? AND b.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $t = $this->tid();
        $status = (string)($_GET['status'] ?? '');
        $supplier = (int)($_GET['supplier'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'b.tenant_id = ?';
        $params = [$t];
        $due = '(b.total - b.returned_amount - b.paid_amount)';
        $where .= match ($status) {
            'unpaid' => " AND b.paid_amount <= 0.004 AND $due > 0.004",
            'partial' => " AND b.paid_amount > 0.004 AND $due > 0.004",
            'paid' => " AND $due <= 0.004",
            'overdue' => " AND $due > 0.004 AND b.due_date IS NOT NULL AND b.due_date < CURDATE()",
            default => '',
        };
        if ($supplier) { $where .= ' AND b.supplier_id = ?'; $params[] = $supplier; }
        if ($q !== '') { $where .= ' AND (b.bill_no LIKE ? OR b.supplier_bill_no LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like); }
        [$page, $pages, $limit] = $this->pageOf('purchase_bills b', $where, $params);
        $rows = DB::all("SELECT b.*, s.name AS supplier FROM purchase_bills b JOIN suppliers s ON s.id = b.supplier_id WHERE $where ORDER BY b.id DESC $limit", $params);
        $totalDue = (float)DB::val("SELECT COALESCE(SUM($due),0) FROM purchase_bills b WHERE $where", $params);
        $this->view('app/purchase/bill_index', ['title' => 'Purchase bills', 'rows' => $rows, 'status' => $status, 'supplier' => $supplier, 'q' => $q,
            'suppliers' => $this->suppliers(false), 'page' => $page, 'pages' => $pages, 'totalDue' => $totalDue]);
    }

    public function createFromGrn(string $id): void
    {
        $t = $this->tid();
        $g = DB::one('SELECT g.*, s.payment_terms_days FROM grns g JOIN suppliers s ON s.id = g.supplier_id WHERE g.tenant_id = ? AND g.id = ?', [$t, (int)$id]) ?? $this->notFound();
        if ($existing = DB::val('SELECT id FROM purchase_bills WHERE tenant_id = ? AND grn_id = ?', [$t, $g['id']])) {
            flash('info', 'This receipt already has a bill.');
            redirect("purchase/bills/$existing");
        }
        $sub = $tax = 0.0;
        foreach (DB::all('SELECT qty, unit_price, tax_rate FROM grn_items WHERE tenant_id = ? AND grn_id = ?', [$t, $g['id']]) as $l) {
            [$n, $x] = Purchase::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['tax_rate']);
            $sub += $n;
            $tax += $x;
        }
        $billDate = date('Y-m-d');
        $billId = DB::transaction(function () use ($t, $g, $sub, $tax, $billDate) {
            $returned = (float)DB::val('SELECT COALESCE(SUM(total),0) FROM purchase_returns WHERE tenant_id = ? AND grn_id = ?', [$t, $g['id']]);
            $id = DB::insert('purchase_bills', [
                'tenant_id' => $t, 'bill_no' => Numbering::next($t, 'PB'), 'supplier_id' => $g['supplier_id'], 'grn_id' => $g['id'],
                'supplier_bill_no' => $g['supplier_ref'], 'bill_date' => $billDate,
                'due_date' => $g['payment_terms_days'] ? date('Y-m-d', strtotime("$billDate +{$g['payment_terms_days']} days")) : null,
                'subtotal' => round($sub, 2), 'tax_total' => round($tax, 2), 'total' => round($sub + $tax, 2),
                'returned_amount' => $returned, 'created_by' => Auth::user()['id'],
            ]);
            DB::run('UPDATE purchase_returns SET bill_id = ? WHERE tenant_id = ? AND grn_id = ?', [$id, $t, $g['id']]);
            return $id;
        });
        Audit::log('bill_create', 'purchase_bill', $billId);
        flash('success', 'Bill created from the goods receipt.');
        redirect("purchase/bills/$billId");
    }

    public function show(string $id): void
    {
        $b = $this->load($id);
        $t = $this->tid();
        $this->view('app/purchase/bill_show', [
            'title' => $b['bill_no'], 'b' => $b, 'due' => Purchase::outstanding($b), 'status' => Purchase::payStatus($b),
            'items' => $b['grn_id'] ? DB::all(
                'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit FROM grn_items l
                 JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 WHERE l.tenant_id = ? AND l.grn_id = ? ORDER BY l.id', [$t, $b['grn_id']]) : [],
            'payments' => DB::all('SELECT p.*, u.name AS user_name FROM supplier_payments p LEFT JOIN users u ON u.id = p.created_by WHERE p.tenant_id = ? AND p.bill_id = ? ORDER BY p.paid_on, p.id', [$t, $b['id']]),
            'returns' => DB::all('SELECT id, return_no, return_date, total FROM purchase_returns WHERE tenant_id = ? AND bill_id = ? ORDER BY id', [$t, $b['id']]),
        ]);
    }

    public function edit(string $id): void
    {
        $this->view('app/purchase/bill_form', ['title' => 'Edit bill', 'b' => $this->load($id)]);
    }

    public function update(string $id): void
    {
        $b = $this->load($id);
        $back = "purchase/bills/{$b['id']}/edit";
        $d = $this->input();
        $billDate = $this->date($d['bill_date'] ?? '', 'Bill date', $back);
        $dueDate = $this->date($d['due_date'] ?? '', 'Due date', $back, false);
        if ($dueDate !== null && $dueDate < $billDate) $this->bounce('Due date cannot be before the bill date.', $back);
        $other = $this->money($d['other_charges'] ?? '', 'Other charges', $back);
        $total = round((float)$b['subtotal'] + (float)$b['tax_total'] + $other, 2);
        if ($total + 0.004 < (float)$b['paid_amount']) $this->bounce('The total cannot be less than what is already paid.', $back);
        DB::run('UPDATE purchase_bills SET supplier_bill_no = ?, bill_date = ?, due_date = ?, other_charges = ?, total = ?, notes = ? WHERE tenant_id = ? AND id = ?',
            [$this->text($d['supplier_bill_no'] ?? '', 60, 'Supplier bill no.', $back), $billDate, $dueDate, $other, $total,
             $this->text($d['notes'] ?? '', 255, 'Notes', $back), $this->tid(), $b['id']]);
        Audit::log('bill_update', 'purchase_bill', (int)$b['id']);
        flash('success', 'Bill updated.');
        redirect("purchase/bills/{$b['id']}");
    }

    private function refreshPaid(int $billId): void
    {
        DB::run('UPDATE purchase_bills SET paid_amount = (SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE bill_id = ?) WHERE tenant_id = ? AND id = ?',
            [$billId, $this->tid(), $billId]);
    }

    public function pay(string $id): void
    {
        $b = $this->load($id);
        $back = "purchase/bills/{$b['id']}";
        $d = $this->input();
        $amount = $this->money($d['amount'] ?? '', 'Amount', $back);
        if ($amount <= 0) $this->bounce('Enter the amount paid.', $back);
        $due = Purchase::outstanding($b);
        if ($amount > $due + 0.004) $this->bounce('That is more than the balance due (' . number_format(max(0, $due), 2) . ').', $back);
        $method = (string)($d['method'] ?? 'cash');
        if (!isset(Purchase::METHODS[$method])) $this->bounce('Choose a payment method.', $back);
        $paidOn = $this->date($d['paid_on'] ?? '', 'Payment date', $back);
        DB::transaction(function () use ($b, $amount, $method, $paidOn, $d, $back) {
            DB::insert('supplier_payments', ['tenant_id' => $this->tid(), 'bill_id' => $b['id'], 'amount' => $amount, 'paid_on' => $paidOn, 'method' => $method,
                'reference' => $this->text($d['reference'] ?? '', 80, 'Reference', $back), 'note' => $this->text($d['note'] ?? '', 150, 'Note', $back), 'created_by' => Auth::user()['id']]);
            $this->refreshPaid((int)$b['id']);
        });
        Audit::log('supplier_payment', 'purchase_bill', (int)$b['id'], number_format($amount, 2));
        flash('success', 'Payment recorded.');
        redirect($back);
    }

    public function deletePayment(string $id, string $payId): void
    {
        $b = $this->load($id);
        $p = DB::one('SELECT * FROM supplier_payments WHERE tenant_id = ? AND id = ? AND bill_id = ?', [$this->tid(), (int)$payId, $b['id']]) ?? $this->notFound();
        DB::transaction(function () use ($p, $b) {
            DB::run('DELETE FROM supplier_payments WHERE tenant_id = ? AND id = ?', [$this->tid(), $p['id']]);
            $this->refreshPaid((int)$b['id']);
        });
        Audit::log('supplier_payment_delete', 'purchase_bill', (int)$b['id'], $p['amount']);
        flash('success', 'Payment removed.');
        redirect("purchase/bills/{$b['id']}");
    }
}
