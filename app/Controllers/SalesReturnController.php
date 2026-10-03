<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Sales;
use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Customer returns against an invoice: credit the customer and (optionally) put the goods back on a rack. */
final class SalesReturnController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT r.*, c.name AS customer, i.invoice_no, w.name AS warehouse, u.name AS user_name FROM sales_returns r JOIN customers c ON c.id = r.customer_id
             JOIN sales_invoices i ON i.id = r.invoice_id JOIN warehouses w ON w.id = r.warehouse_id LEFT JOIN users u ON u.id = r.created_by
             WHERE r.tenant_id = ? AND r.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        [$page, $pages, $limit] = $this->pageOf('sales_returns', 'tenant_id = ?', [$this->tid()]);
        $rows = DB::all("SELECT r.*, c.name AS customer, i.invoice_no FROM sales_returns r JOIN customers c ON c.id = r.customer_id JOIN sales_invoices i ON i.id = r.invoice_id
                         WHERE r.tenant_id = ? ORDER BY r.id DESC $limit", [$this->tid()]);
        $this->view('app/sales/return_index', ['title' => 'Sales returns', 'rows' => $rows, 'page' => $page, 'pages' => $pages]);
    }

    public function create(): void
    {
        $t = $this->tid();
        $invId = (int)($_GET['invoice'] ?? 0);
        if (!$invId) {
            $q = trim((string)($_GET['q'] ?? ''));
            $where = 'i.tenant_id = ? AND i.delivery_id IS NOT NULL';
            $params = [$t];
            if ($q !== '') { $where .= ' AND (i.invoice_no LIKE ? OR c.name LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like); }
            $rows = DB::all("SELECT i.id, i.invoice_no, i.invoice_date, i.total, c.name AS customer FROM sales_invoices i JOIN customers c ON c.id = i.customer_id WHERE $where ORDER BY i.id DESC LIMIT 30", $params);
            $this->view('app/sales/return_pick', ['title' => 'New sales return', 'rows' => $rows, 'q' => $q]);
            return;
        }
        $i = DB::one('SELECT i.*, c.name AS customer, d.warehouse_id, w.name AS warehouse FROM sales_invoices i JOIN customers c ON c.id = i.customer_id
                      JOIN deliveries d ON d.id = i.delivery_id JOIN warehouses w ON w.id = d.warehouse_id WHERE i.tenant_id = ? AND i.id = ?', [$t, $invId]) ?? $this->notFound();
        $items = DeliveryController::rows((int)$i['delivery_id'], $t);
        foreach ($items as &$l) $l['returnable'] = round((float)$l['qty'] - (float)$l['qty_returned'], 3);
        unset($l);
        $this->view('app/sales/return_form', ['title' => 'Return · ' . $i['invoice_no'], 'i' => $i, 'items' => $items, 'racks' => $this->racksByWarehouse()]);
    }

    public function store(): void
    {
        $t = $this->tid();
        $d = $this->input();
        $invId = (int)($d['invoice_id'] ?? 0);
        $back = "sales/returns/create?invoice=$invId";
        $this->enforceFields('sales_return', $back);
        $i = DB::one('SELECT i.*, d.warehouse_id, d.delivery_no FROM sales_invoices i JOIN deliveries d ON d.id = i.delivery_id WHERE i.tenant_id = ? AND i.id = ?', [$t, $invId]) ?? $this->notFound();
        $date = $this->date($d['return_date'] ?? '', 'Return date', $back);
        $reason = $this->text($d['reason'] ?? '', 150, 'Reason', $back);

        $lines = [];
        foreach ((array)($d['lines'] ?? []) as $itemId => $l) {
            $raw = trim((string)($l['qty'] ?? ''));
            if ($raw === '' || (is_numeric($raw) && (float)$raw == 0.0)) continue;
            $it = DB::one('SELECT l.*, i.name AS item_name, i.is_bundle, u.allow_decimal, u.short_name AS unit FROM delivery_items l JOIN item_variants v ON v.id = l.variant_id
                           JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id WHERE l.tenant_id = ? AND l.delivery_id = ? AND l.id = ?', [$t, $i['delivery_id'], (int)$itemId])
                ?? $this->bounce('A returned line does not belong to this invoice.', $back);
            if (!is_numeric($raw) || (float)$raw < 0) $this->bounce($it['item_name'] . ': enter a quantity of zero or more.', $back);
            $qty = Stock::round((float)$raw);
            if (!$it['allow_decimal'] && floor($qty) != $qty) $this->bounce($it['item_name'] . ' is counted in whole ' . $it['unit'] . '.', $back);
            $can = round((float)$it['qty'] - (float)$it['qty_returned'], 3);
            if ($qty > $can + 0.0005) $this->bounce($it['item_name'] . ': only ' . Stock::fmt($can) . ' ' . $it['unit'] . ' can still be returned.', $back);
            $lines[] = ['it' => $it, 'qty' => $qty, 'rack' => (int)($l['location_id'] ?? 0), 'restock' => !empty($l['restock'])];
        }
        if (!$lines) $this->bounce('Enter the quantity to return on at least one line.', $back);

        try {
            $retId = DB::transaction(function () use ($t, $i, $date, $reason, $lines) {
                $no = Numbering::next($t, 'SR');
                $retId = DB::insert('sales_returns', ['tenant_id' => $t, 'return_no' => $no, 'invoice_id' => $i['id'], 'delivery_id' => $i['delivery_id'], 'customer_id' => $i['customer_id'],
                    'warehouse_id' => $i['warehouse_id'], 'return_date' => $date, 'reason' => $reason, 'created_by' => Auth::user()['id']]);
                $total = 0.0;
                foreach ($lines as $l) {
                    $it = $l['it'];
                    $restock = $l['restock'] && !$it['is_bundle'];
                    if ($restock) {
                        try {
                            Stock::move((int)$it['variant_id'], (int)$i['warehouse_id'], (int)$it['batch_id'], $l['qty'], 'sales_return', 'sales_return', $retId, (float)$it['unit_cost'], "$no · returned by customer ({$i['delivery_no']})", $l['rack']);
                        } catch (StockException $e) {
                            throw new StockException($it['item_name'] . ': ' . $e->getMessage());
                        }
                    }
                    [, , , , $lineTotal] = Sales::line($l['qty'], (float)$it['unit_price'], (float)$it['discount_pct'], (float)$it['tax_rate']);
                    $total += $lineTotal;
                    DB::insert('sales_return_items', ['tenant_id' => $t, 'return_id' => $retId, 'delivery_item_id' => $it['id'], 'variant_id' => $it['variant_id'], 'batch_id' => $it['batch_id'],
                        'location_id' => $restock ? $l['rack'] : 0, 'qty' => $l['qty'], 'restock' => $restock ? 1 : 0, 'unit_price' => $it['unit_price'], 'discount_pct' => $it['discount_pct'],
                        'tax_rate' => $it['tax_rate'], 'line_total' => $lineTotal]);
                    DB::run('UPDATE delivery_items SET qty_returned = qty_returned + ? WHERE tenant_id = ? AND id = ?', [$l['qty'], $t, $it['id']]);
                }
                $total = round($total, 2);
                DB::run('UPDATE sales_returns SET total = ? WHERE tenant_id = ? AND id = ?', [$total, $t, $retId]);
                DB::run('UPDATE sales_invoices SET returned_amount = returned_amount + ? WHERE tenant_id = ? AND id = ?', [$total, $t, $i['id']]);
                return $retId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('sales_return', 'sales_return', $retId);
        flash('success', 'Return recorded. The customer is credited on the invoice.');
        redirect("sales/returns/$retId");
    }

    public function show(string $id): void
    {
        $r = $this->load($id);
        $items = DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no, k.code AS rack
             FROM sales_return_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations k ON k.id = l.location_id AND l.location_id > 0
             WHERE l.tenant_id = ? AND l.return_id = ? ORDER BY l.id', [$this->tid(), $r['id']]);
        $this->view('app/sales/return_show', ['title' => $r['return_no'], 'r' => $r, 'items' => $items]);
    }
}
