<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrintTemplate;
use App\Models\Sales;
use App\Models\CustomFields;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

final class QuotationController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT q.*, c.name AS customer, u.name AS user_name, o.order_no FROM sales_quotations q JOIN customers c ON c.id = q.customer_id
             LEFT JOIN users u ON u.id = q.created_by LEFT JOIN sales_orders o ON o.id = q.order_id WHERE q.tenant_id = ? AND q.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function items(int $id): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, u.allow_decimal, i.track_batch, i.is_bundle
             FROM sales_quotation_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE l.tenant_id = ? AND l.quotation_id = ? ORDER BY l.id', [$this->tid(), $id]);
    }

    public function index(): void
    {
        $status = (string)($_GET['status'] ?? '');
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'q.tenant_id = ?';
        $params = [$this->tid()];
        if (isset(Sales::QUOTE_STATUS[$status])) { $where .= ' AND q.status = ?'; $params[] = $status; }
        if ($q !== '') { $where .= ' AND (q.quote_no LIKE ? OR c.name LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like); }
        [$page, $pages, $limit] = $this->pageOf('sales_quotations q JOIN customers c ON c.id = q.customer_id', $where, $params);
        $rows = DB::all("SELECT q.*, c.name AS customer FROM sales_quotations q JOIN customers c ON c.id = q.customer_id WHERE $where ORDER BY q.id DESC $limit", $params);
        $this->view('app/sales/quote_index', ['title' => 'Quotations', 'rows' => $rows, 'status' => $status, 'q' => $q, 'page' => $page, 'pages' => $pages]);
    }

    private function form(string $title, ?array $q, array $lines): void
    {
        $this->view('app/sales/quote_form', ['title' => $title, 'q' => $q, 'customers' => $this->customers(), 'oldLines' => $lines, 'mode' => 'quote',
            'cfFields' => CustomFields::fields('quotation'), 'cfValues' => CustomFields::formValues('quotation', $q ? (int)$q['id'] : null)]);
    }

    public function create(): void
    {
        $this->form('New quotation', null, $this->oldLines());
    }

    private function header(string $back): array
    {
        $d = $this->input();
        $c = $this->activeCustomer($d['customer_id'] ?? 0, $back);
        $date = $this->date($d['quote_date'] ?? '', 'Quotation date', $back);
        $valid = $this->date($d['valid_until'] ?? '', 'Valid until', $back, false);
        if ($valid !== null && $valid < $date) $this->bounce('"Valid until" cannot be before the quotation date.', $back);
        return ['customer_id' => $c['id'], 'quote_date' => $date, 'valid_until' => $valid, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back),
            'delivery_charge' => $this->money($d['delivery_charge'] ?? '', 'Delivery charge', $back), 'installation_charge' => $this->money($d['installation_charge'] ?? '', 'Installation charge', $back)];
    }

    private function saveLines(int $id, array $lines): void
    {
        DB::run('DELETE FROM sales_quotation_items WHERE tenant_id = ? AND quotation_id = ?', [$this->tid(), $id]);
        foreach ($lines as $l) {
            DB::insert('sales_quotation_items', ['tenant_id' => $this->tid(), 'quotation_id' => $id, 'variant_id' => $l['variant_id'], 'qty' => $l['qty'],
                'unit_price' => $l['price'], 'discount_pct' => $l['disc'], 'tax_rate' => $l['tax']]);
        }
        Sales::recalc('sales_quotations', 'sales_quotation_items', 'quotation_id', 'qty', $id);
    }

    public function store(): void
    {
        $back = 'sales/quotations/create';
        $this->enforceFields('quotation', $back);
        $head = $this->header($back);
        $lines = $this->collectSaleLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('quotation', $this->input());
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        $id = DB::transaction(function () use ($head, $lines, $cf) {
            $t = $this->tid();
            $id = DB::insert('sales_quotations', ['tenant_id' => $t, 'quote_no' => Numbering::next($t, 'QT'), 'status' => 'draft', 'created_by' => Auth::user()['id']] + $head);
            $this->saveLines($id, $lines);
            CustomFields::save('quotation', $id, $cf);
            return $id;
        });
        Audit::log('quotation_create', 'quotation', $id);
        flash('success', 'Quotation saved.');
        redirect("sales/quotations/$id");
    }

    public function show(string $id): void
    {
        $q = $this->load($id);
        $this->view('app/sales/quote_show', ['title' => $q['quote_no'], 'q' => $q, 'items' => $this->items((int)$q['id']), 'warehouses' => $this->warehouses(),
            'cfFields' => CustomFields::fields('quotation'), 'cfValues' => CustomFields::values('quotation', (int)$q['id'])]);
    }

    public function print(string $id): void
    {
        $q = $this->load($id);
        $c = DB::one('SELECT phone, address FROM customers WHERE tenant_id = ? AND id = ?', [$this->tid(), $q['customer_id']]);
        $q['cust_phone'] = $c['phone'] ?? null;
        $q['cust_address'] = $c['address'] ?? null;
        $this->view('app/sales/quote_print', ['title' => $q['quote_no'], 'q' => $q, 'items' => $this->items((int)$q['id']), 'tpl' => PrintTemplate::get('quotation')], 'layouts/print');
    }

    public function edit(string $id): void
    {
        $q = $this->load($id);
        if (!in_array($q['status'], ['draft', 'sent'], true)) {
            flash('warning', 'This quotation can no longer be edited.');
            redirect("sales/quotations/{$q['id']}");
        }
        $lines = $this->oldLines() ?: array_map(fn($l) => ['variant_id' => $l['variant_id'], 'label' => $this->saleLabel($l), 'unit' => $l['unit'], 'dec' => (int)$l['allow_decimal'],
            'qty' => (float)$l['qty'], 'unit_price' => $l['unit_price'], 'discount_pct' => $l['discount_pct'], 'tax_rate' => $l['tax_rate']], $this->items((int)$q['id']));
        $this->form('Edit ' . $q['quote_no'], $q, $lines);
    }

    public function update(string $id): void
    {
        $q = $this->load($id);
        $back = "sales/quotations/{$q['id']}/edit";
        $this->enforceFields('quotation', $back);
        if (!in_array($q['status'], ['draft', 'sent'], true)) $this->bounce('This quotation can no longer be edited.', "sales/quotations/{$q['id']}");
        $head = $this->header($back);
        $lines = $this->collectSaleLines($this->postedLines($back), $back);
        [$cf, $cfErr] = CustomFields::validate('quotation', $this->input(), (int)$id);
        if ($cfErr) $this->bounce(implode(' ', $cfErr), $back);
        DB::transaction(function () use ($q, $head, $lines, $cf) {
            $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($head)));
            DB::run("UPDATE sales_quotations SET $set WHERE tenant_id = ? AND id = ?", [...array_values($head), $this->tid(), $q['id']]);
            $this->saveLines((int)$q['id'], $lines);
            CustomFields::save('quotation', (int)$q['id'], $cf);
        });
        Audit::log('quotation_update', 'quotation', (int)$q['id']);
        flash('success', 'Quotation updated.');
        redirect("sales/quotations/{$q['id']}");
    }

    private function setStatus(string $id, array $from, string $to, string $msg): never
    {
        $q = $this->load($id);
        if (!in_array($q['status'], $from, true)) {
            flash('danger', 'That change is not possible right now.');
            redirect("sales/quotations/{$q['id']}");
        }
        DB::run('UPDATE sales_quotations SET status = ? WHERE tenant_id = ? AND id = ?', [$to, $this->tid(), $q['id']]);
        Audit::log('quotation_' . $to, 'quotation', (int)$q['id']);
        flash('success', $msg);
        redirect("sales/quotations/{$q['id']}");
    }

    public function send(string $id): void { $this->setStatus($id, ['draft'], 'sent', 'Marked as sent to the customer.'); }
    public function accept(string $id): void { $this->setStatus($id, ['draft', 'sent'], 'accepted', 'Marked as accepted.'); }
    public function reject(string $id): void { $this->setStatus($id, ['draft', 'sent', 'accepted'], 'rejected', 'Marked as rejected.'); }

    public function convert(string $id): void
    {
        $q = $this->load($id);
        $back = "sales/quotations/{$q['id']}";
        if (!in_array($q['status'], ['draft', 'sent', 'accepted'], true)) $this->bounce('This quotation cannot be turned into an order.', $back);
        $wh = $this->activeWarehouse($this->input()['warehouse_id'] ?? 0, $back);
        $c = Sales::customer((int)$q['customer_id']);
        if (!$c || !$c['is_active']) $this->bounce('The customer of this quotation is inactive.', $back);
        $items = $this->items((int)$q['id']);
        foreach ($items as $l) {
            if (!$this->sellable((int)$l['variant_id'])) $this->bounce($l['item_name'] . ' is no longer available for sale. Edit the quotation first.', $back);
        }
        $orderId = DB::transaction(function () use ($q, $wh, $c, $items) {
            $t = $this->tid();
            $orderId = DB::insert('sales_orders', ['tenant_id' => $t, 'order_no' => Numbering::next($t, 'SO'), 'customer_id' => $q['customer_id'], 'warehouse_id' => $wh['id'],
                'order_date' => date('Y-m-d'), 'status' => 'draft', 'ship_to' => $c['ship_address'] ?: $c['address'], 'notes' => 'From quotation ' . $q['quote_no'],
                'delivery_charge' => $q['delivery_charge'], 'installation_charge' => $q['installation_charge'], 'quotation_id' => $q['id'], 'created_by' => Auth::user()['id']]);
            foreach ($items as $l) {
                DB::insert('sales_order_items', ['tenant_id' => $t, 'order_id' => $orderId, 'variant_id' => $l['variant_id'], 'qty_ordered' => $l['qty'],
                    'unit_price' => $l['unit_price'], 'discount_pct' => $l['discount_pct'], 'tax_rate' => $l['tax_rate']]);
            }
            Sales::recalc('sales_orders', 'sales_order_items', 'order_id', 'qty_ordered', $orderId);
            DB::run("UPDATE sales_quotations SET status = 'converted', order_id = ? WHERE tenant_id = ? AND id = ?", [$orderId, $t, $q['id']]);
            return $orderId;
        });
        Audit::log('quotation_convert', 'quotation', (int)$q['id']);
        flash('success', 'Draft sales order created from the quotation. Review it and confirm.');
        redirect("sales/orders/$orderId");
    }

    public function destroy(string $id): void
    {
        $q = $this->load($id);
        if ($q['status'] !== 'draft') {
            flash('danger', 'Only draft quotations can be deleted.');
            redirect("sales/quotations/{$q['id']}");
        }
        DB::run('DELETE FROM sales_quotations WHERE tenant_id = ? AND id = ?', [$this->tid(), $q['id']]);
        flash('success', 'Quotation deleted.');
        redirect('sales/quotations');
    }
}
