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

/** Goods receipt: brings stock in, one batch per roll for batch-tracked items, onto a rack. */
final class GrnController extends PurchaseBase
{
    /** Receiving up to 10% over the ordered quantity is accepted (rolls are rarely exactly the ordered length). */
    private const TOLERANCE = 0.10;

    private function load(string $id): array
    {
        return DB::one(
            'SELECT g.*, s.name AS supplier, w.name AS warehouse, p.po_no, u.name AS user_name, b.id AS bill_id, b.bill_no
             FROM grns g JOIN suppliers s ON s.id = g.supplier_id JOIN warehouses w ON w.id = g.warehouse_id
             LEFT JOIN purchase_orders p ON p.id = g.po_id LEFT JOIN users u ON u.id = g.created_by
             LEFT JOIN purchase_bills b ON b.grn_id = g.id AND b.tenant_id = g.tenant_id
             WHERE g.tenant_id = ? AND g.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $t = $this->tid();
        $supplier = (int)($_GET['supplier'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'g.tenant_id = ?';
        $params = [$t];
        if ($supplier) { $where .= ' AND g.supplier_id = ?'; $params[] = $supplier; }
        if ($q !== '') { $where .= ' AND (g.grn_no LIKE ? OR g.supplier_ref LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like); }
        [$page, $pages, $limit] = $this->pageOf('grns g', $where, $params);
        $rows = DB::all(
            "SELECT g.*, s.name AS supplier, w.name AS warehouse, p.po_no, b.id AS bill_id,
                    (SELECT COUNT(*) FROM grn_items i WHERE i.grn_id = g.id) AS line_count
             FROM grns g JOIN suppliers s ON s.id = g.supplier_id JOIN warehouses w ON w.id = g.warehouse_id
             LEFT JOIN purchase_orders p ON p.id = g.po_id LEFT JOIN purchase_bills b ON b.grn_id = g.id AND b.tenant_id = g.tenant_id
             WHERE $where ORDER BY g.id DESC $limit", $params);
        $this->view('app/purchase/grn_index', ['title' => 'Goods receipts', 'rows' => $rows, 'supplier' => $supplier, 'q' => $q,
            'suppliers' => $this->suppliers(false), 'page' => $page, 'pages' => $pages]);
    }

    /* ---------- receive ---------- */

    private function form(array $extra): void
    {
        $this->view('app/purchase/grn_form', $extra + [
            'title' => 'Receive goods', 'suppliers' => $this->suppliers(), 'warehouses' => $this->warehouses(),
            'racks' => $this->racksByWarehouse(), 'po' => null, 'oldLines' => $this->oldLines(),
        ]);
    }

    public function create(): void
    {
        $this->form(['title' => 'Receive goods (no purchase order)']);
    }

    private function openPo(string $id): array
    {
        $po = DB::one('SELECT p.*, s.name AS supplier FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id WHERE p.tenant_id = ? AND p.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
        if (!in_array($po['status'], ['approved', 'partial'], true)) {
            flash('danger', 'Goods can only be received against an approved purchase order that is not finished.');
            redirect("purchase/orders/{$po['id']}");
        }
        return $po;
    }

    public function createFromPo(string $id): void
    {
        $po = $this->openPo($id);
        $lines = $this->oldLines();
        if (!$lines) {
            foreach (DB::all(
                'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, i.track_batch, u.short_name AS unit, u.allow_decimal
                 FROM purchase_order_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 WHERE l.tenant_id = ? AND l.po_id = ? ORDER BY l.id', [$this->tid(), $po['id']]) as $l) {
                $left = round((float)$l['qty_ordered'] - (float)$l['qty_received'], 3);
                if ($left <= 0) continue;
                $lines[] = ['po_item_id' => $l['id'], 'variant_id' => $l['variant_id'], 'label' => $l['item_name'] . ($l['vname'] ? ' — ' . $l['vname'] : '') . ' (' . $l['sku'] . ')',
                    'tb' => (int)$l['track_batch'], 'unit' => $l['unit'], 'dec' => (int)$l['allow_decimal'], 'qty' => $left, 'left' => $left,
                    'unit_price' => $l['unit_price'], 'tax_rate' => $l['tax_rate'], 'location_id' => 0, 'lot_no' => ''];
            }
        }
        $this->form(['title' => 'Receive goods · ' . $po['po_no'], 'po' => $po, 'oldLines' => $lines]);
    }

    public function store(): void
    {
        $d = $this->input();
        $poId = (int)($d['po_id'] ?? 0);
        $back = $poId ? "purchase/orders/$poId/receive" : 'purchase/grns/create';
        $po = $poId ? $this->openPo((string)$poId) : null;
        $supplier = $po ? Purchase::supplier((int)$po['supplier_id']) : $this->activeSupplier($d['supplier_id'] ?? 0, $back);
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $date = $this->date($d['received_date'] ?? '', 'Received date', $back);
        $ref = $this->text($d['supplier_ref'] ?? '', 60, 'Supplier challan / invoice no.', $back);
        $extra = $this->money($d['extra_cost'] ?? '', 'Extra cost', $back);
        $extraNote = $this->text($d['extra_cost_note'] ?? '', 150, 'Extra cost note', $back);
        $note = $this->text($d['note'] ?? '', 255, 'Note', $back);

        $poItems = [];
        if ($po) {
            foreach (DB::all('SELECT * FROM purchase_order_items WHERE tenant_id = ? AND po_id = ?', [$this->tid(), $po['id']]) as $pi) $poItems[$pi['id']] = $pi;
        }
        $lines = [];
        $perPoItem = [];
        foreach ($this->postedLines($back) as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            if ($po) {
                $pi = $poItems[(int)($l['po_item_id'] ?? 0)] ?? $this->bounce("$prefix: this line is not on the purchase order.", $back);
                $v = Stock::variant((int)$pi['variant_id']);
                $price = (float)$pi['unit_price'];
                $tax = (float)$pi['tax_rate'];
            } else {
                $pi = null;
                $v = $this->buyable((int)($l['variant_id'] ?? 0)) ?? $this->bounce("$prefix: choose a valid item.", $back);
                $price = $this->money($l['unit_price'] ?? '', "$prefix price", $back);
                $tax = $this->taxFor($l['tax_rate'] ?? '', $prefix, $back);
            }
            $qty = $this->qtyFor($v, $l['qty'] ?? '', $prefix, $back);
            $lot = $this->text($l['lot_no'] ?? '', 60, "$prefix supplier lot", $back);
            if ($this->rackRequired((int)$wh['id'], (int)($l['location_id'] ?? 0))) $this->bounce("$prefix: choose the rack this goes on (your settings require it).", $back);
            $lines[] = ['n' => $n + 1, 'po_item' => $pi, 'v' => $v, 'qty' => $qty, 'price' => $price, 'tax' => $tax, 'lot' => $lot, 'rack' => (int)($l['location_id'] ?? 0)];
            if ($pi) $perPoItem[$pi['id']] = ($perPoItem[$pi['id']] ?? 0) + $qty;
        }
        foreach ($perPoItem as $piId => $got) {
            $pi = $poItems[$piId];
            $left = (float)$pi['qty_ordered'] - (float)$pi['qty_received'];
            if ($got > $left * (1 + self::TOLERANCE) + 0.0005) {
                $v = Stock::variant((int)$pi['variant_id']);
                $this->bounce($v['item_name'] . ': you are receiving ' . Stock::fmt($got) . ' but only ' . Stock::fmt($left) . ' is still due (up to 10% extra is accepted).', $back);
            }
        }

        try {
            $grnId = DB::transaction(function () use ($po, $supplier, $wh, $date, $ref, $extra, $extraNote, $note, $lines, $poItems) {
                $t = $this->tid();
                $grnNo = Numbering::next($t, 'GRN');
                $grnId = DB::insert('grns', ['tenant_id' => $t, 'grn_no' => $grnNo, 'po_id' => $po['id'] ?? null, 'supplier_id' => $supplier['id'],
                    'warehouse_id' => $wh['id'], 'received_date' => $date, 'supplier_ref' => $ref, 'extra_cost' => $extra,
                    'extra_cost_note' => $extraNote, 'note' => $note, 'created_by' => Auth::user()['id']]);
                // Freight / duty is spread over the lines by value (or by quantity when everything is free).
                $value = array_sum(array_map(fn($l) => $l['qty'] * $l['price'], $lines));
                $qtyAll = array_sum(array_map(fn($l) => $l['qty'], $lines));
                foreach ($lines as $l) {
                    $share = $extra <= 0 ? 0.0 : ($value > 0 ? $extra * ($l['qty'] * $l['price']) / $value : $extra * $l['qty'] / $qtyAll);
                    $landed = round($l['price'] + $share / $l['qty'], 4);
                    $v = $l['v'];
                    try {
                        $batch = $v['track_batch'] ? Stock::newBatch((int)$v['id'], $l['qty'], $l['lot'], $landed, $date, "Received on $grnNo from " . $supplier['name']) : 0;
                        Stock::move((int)$v['id'], (int)$wh['id'], $batch, $l['qty'], 'purchase', 'grn', $grnId, $landed, "$grnNo · " . $supplier['name'], $l['rack']);
                    } catch (StockException $e) {
                        throw new StockException('Line ' . $l['n'] . ': ' . $e->getMessage());
                    }
                    DB::insert('grn_items', ['tenant_id' => $t, 'grn_id' => $grnId, 'po_item_id' => $l['po_item']['id'] ?? null, 'variant_id' => $v['id'],
                        'batch_id' => $batch, 'location_id' => $l['rack'], 'qty' => $l['qty'], 'unit_price' => $l['price'], 'tax_rate' => $l['tax'],
                        'landed_unit_cost' => $landed, 'supplier_lot' => $l['lot']]);
                    if ($l['po_item']) {
                        DB::run('UPDATE purchase_order_items SET qty_received = qty_received + ? WHERE tenant_id = ? AND id = ?', [$l['qty'], $t, $l['po_item']['id']]);
                    }
                }
                if ($po) {
                    $open = (int)DB::val('SELECT COUNT(*) FROM purchase_order_items WHERE tenant_id = ? AND po_id = ? AND qty_received < qty_ordered - 0.0005', [$t, $po['id']]);
                    DB::run('UPDATE purchase_orders SET status = ? WHERE tenant_id = ? AND id = ?', [$open ? 'partial' : 'received', $t, $po['id']]);
                }
                return $grnId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('grn_create', 'grn', $grnId);
        flash('success', 'Goods received and added to stock.');
        redirect("purchase/grns/$grnId");
    }

    public function show(string $id): void
    {
        $g = $this->load($id);
        $items = DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no, r.code AS rack
             FROM grn_items l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations r ON r.id = l.location_id AND l.location_id > 0
             WHERE l.tenant_id = ? AND l.grn_id = ? ORDER BY l.id', [$this->tid(), $g['id']]);
        $returns = DB::all('SELECT id, return_no, return_date, total FROM purchase_returns WHERE tenant_id = ? AND grn_id = ? ORDER BY id', [$this->tid(), $g['id']]);
        $this->view('app/purchase/grn_show', ['title' => $g['grn_no'], 'g' => $g, 'items' => $items, 'returns' => $returns]);
    }
}
