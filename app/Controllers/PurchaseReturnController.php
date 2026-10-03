<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Sending goods back to the supplier, always against the goods receipt they came in on. */
final class PurchaseReturnController extends PurchaseBase
{
    private function load(string $id): array
    {
        return DB::one(
            'SELECT r.*, s.name AS supplier, w.name AS warehouse, g.grn_no, b.bill_no, u.name AS user_name
             FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id JOIN warehouses w ON w.id = r.warehouse_id JOIN grns g ON g.id = r.grn_id
             LEFT JOIN purchase_bills b ON b.id = r.bill_id LEFT JOIN users u ON u.id = r.created_by
             WHERE r.tenant_id = ? AND r.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $t = $this->tid();
        [$page, $pages, $limit] = $this->pageOf('purchase_returns', 'tenant_id = ?', [$t]);
        $rows = DB::all(
            "SELECT r.*, s.name AS supplier, g.grn_no FROM purchase_returns r JOIN suppliers s ON s.id = r.supplier_id JOIN grns g ON g.id = r.grn_id
             WHERE r.tenant_id = ? ORDER BY r.id DESC $limit", [$t]);
        $this->view('app/purchase/return_index', ['title' => 'Purchase returns', 'rows' => $rows, 'page' => $page, 'pages' => $pages]);
    }

    /** Step 1: pick the goods receipt. Step 2 (with ?grn=): choose what to send back. */
    public function create(): void
    {
        $t = $this->tid();
        $grnId = (int)($_GET['grn'] ?? 0);
        if (!$grnId) {
            $q = trim((string)($_GET['q'] ?? ''));
            $where = 'g.tenant_id = ?';
            $params = [$t];
            if ($q !== '') { $where .= ' AND (g.grn_no LIKE ? OR s.name LIKE ? OR g.supplier_ref LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like, $like); }
            $rows = DB::all(
                "SELECT g.id, g.grn_no, g.received_date, g.supplier_ref, s.name AS supplier,
                        (SELECT COALESCE(SUM(i.qty - i.qty_returned),0) FROM grn_items i WHERE i.grn_id = g.id) AS returnable
                 FROM grns g JOIN suppliers s ON s.id = g.supplier_id WHERE $where ORDER BY g.id DESC LIMIT 30", $params);
            $this->view('app/purchase/return_pick', ['title' => 'New purchase return', 'rows' => $rows, 'q' => $q]);
            return;
        }
        $g = DB::one('SELECT g.*, s.name AS supplier, w.name AS warehouse FROM grns g JOIN suppliers s ON s.id = g.supplier_id JOIN warehouses w ON w.id = g.warehouse_id WHERE g.tenant_id = ? AND g.id = ?', [$t, $grnId]) ?? $this->notFound();
        $items = DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no
             FROM grn_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 WHERE l.tenant_id = ? AND l.grn_id = ? ORDER BY l.id', [$t, $g['id']]);
        foreach ($items as &$it) {
            $it['returnable'] = round((float)$it['qty'] - (float)$it['qty_returned'], 3);
            $it['placements'] = Stock::placements((int)$it['variant_id'], (int)$g['warehouse_id'], $it['batch_id'] ? (int)$it['batch_id'] : null);
            $it['on_hand'] = array_sum(array_column($it['placements'], 'qty'));
        }
        unset($it);
        $this->view('app/purchase/return_form', ['title' => 'Return goods · ' . $g['grn_no'], 'g' => $g, 'items' => $items, 'racks' => $this->racksByWarehouse()]);
    }

    public function store(): void
    {
        $t = $this->tid();
        $d = $this->input();
        $grnId = (int)($d['grn_id'] ?? 0);
        $back = "purchase/returns/create?grn=$grnId";
        $this->enforceFields('purchase_return', $back);
        $g = DB::one('SELECT * FROM grns WHERE tenant_id = ? AND id = ?', [$t, $grnId]) ?? $this->notFound();
        $date = $this->date($d['return_date'] ?? '', 'Return date', $back);
        $reason = $this->text($d['reason'] ?? '', 150, 'Reason', $back);

        $lines = [];
        foreach ((array)($d['lines'] ?? []) as $itemId => $l) {
            $raw = trim((string)($l['qty'] ?? ''));
            if ($raw === '' || (is_numeric($raw) && (float)$raw == 0.0)) continue;
            $it = DB::one('SELECT l.*, i.name AS item_name, u.allow_decimal, u.short_name AS unit FROM grn_items l JOIN item_variants v ON v.id = l.variant_id
                           JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id WHERE l.tenant_id = ? AND l.grn_id = ? AND l.id = ?', [$t, $g['id'], (int)$itemId])
                ?? $this->bounce('A returned line does not belong to this receipt.', $back);
            if (!is_numeric($raw) || (float)$raw < 0) $this->bounce($it['item_name'] . ': enter a quantity of zero or more.', $back);
            $qty = Stock::round((float)$raw);
            if (!$it['allow_decimal'] && floor($qty) != $qty) $this->bounce($it['item_name'] . ' is counted in whole ' . $it['unit'] . '.', $back);
            $can = round((float)$it['qty'] - (float)$it['qty_returned'], 3);
            if ($qty > $can + 0.0005) $this->bounce($it['item_name'] . ': only ' . Stock::fmt($can) . ' ' . $it['unit'] . ' can still be returned from this receipt.', $back);
            $lines[] = ['it' => $it, 'qty' => $qty, 'rack' => (int)($l['location_id'] ?? 0)];
        }
        if (!$lines) $this->bounce('Enter the quantity to return on at least one line.', $back);

        try {
            $retId = DB::transaction(function () use ($t, $g, $date, $reason, $lines) {
                $bill = DB::one('SELECT id FROM purchase_bills WHERE tenant_id = ? AND grn_id = ?', [$t, $g['id']]);
                $no = Numbering::next($t, 'PRT');
                $retId = DB::insert('purchase_returns', ['tenant_id' => $t, 'return_no' => $no, 'grn_id' => $g['id'], 'supplier_id' => $g['supplier_id'],
                    'warehouse_id' => $g['warehouse_id'], 'bill_id' => $bill['id'] ?? null, 'return_date' => $date, 'reason' => $reason, 'created_by' => Auth::user()['id']]);
                $total = 0.0;
                foreach ($lines as $l) {
                    $it = $l['it'];
                    try {
                        Stock::move((int)$it['variant_id'], (int)$g['warehouse_id'], (int)$it['batch_id'], -$l['qty'], 'purchase_return', 'purchase_return', $retId,
                            (float)$it['landed_unit_cost'], "$no · returned to supplier", $l['rack']);
                    } catch (StockException $e) {
                        throw new StockException($it['item_name'] . ': ' . $e->getMessage());
                    }
                    [, , $lineTotal] = Purchase::line($l['qty'], (float)$it['unit_price'], (float)$it['tax_rate']);
                    $total += $lineTotal;
                    DB::insert('purchase_return_items', ['tenant_id' => $t, 'return_id' => $retId, 'grn_item_id' => $it['id'], 'variant_id' => $it['variant_id'],
                        'batch_id' => $it['batch_id'], 'location_id' => $l['rack'], 'qty' => $l['qty'], 'unit_price' => $it['unit_price'], 'tax_rate' => $it['tax_rate'], 'line_total' => $lineTotal]);
                    DB::run('UPDATE grn_items SET qty_returned = qty_returned + ? WHERE tenant_id = ? AND id = ?', [$l['qty'], $t, $it['id']]);
                }
                $total = round($total, 2);
                DB::run('UPDATE purchase_returns SET total = ? WHERE tenant_id = ? AND id = ?', [$total, $t, $retId]);
                if ($bill) DB::run('UPDATE purchase_bills SET returned_amount = returned_amount + ? WHERE tenant_id = ? AND id = ?', [$total, $t, $bill['id']]);
                return $retId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('purchase_return', 'purchase_return', $retId);
        flash('success', 'Goods returned to the supplier and taken out of stock.');
        redirect("purchase/returns/$retId");
    }

    public function show(string $id): void
    {
        $r = $this->load($id);
        $items = DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no, k.code AS rack
             FROM purchase_return_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations k ON k.id = l.location_id AND l.location_id > 0
             WHERE l.tenant_id = ? AND l.return_id = ? ORDER BY l.id', [$this->tid(), $r['id']]);
        $this->view('app/purchase/return_show', ['title' => $r['return_no'], 'r' => $r, 'items' => $items]);
    }
}
