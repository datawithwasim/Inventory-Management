<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrintTemplate;
use Core\Controller;
use Core\DB;

/**
 * Stock-out voucher (issue / pick slip): what the warehouse hands to the cutter. Lists the exact rack and roll to take each item from,
 * in rack order, with the roll's length before the issue and blank columns for the cut quantity and the remnant.
 */
final class VoucherController extends Controller
{
    private function tid(): int
    {
        return (int)\Core\Auth::tenantId();
    }

    /** Length on a roll / stock at a rack just before the document moved it: ledger total up to the document's first movement there. */
    private function before(string $refType, int $refId, int $variant, int $wh, int $batch, int $loc): ?float
    {
        $first = DB::val('SELECT MIN(id) FROM stock_ledger WHERE tenant_id = ? AND ref_type = ? AND ref_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ?',
            [$this->tid(), $refType, $refId, $variant, $wh, $batch, $loc]);
        if (!$first) return null;
        return (float)DB::val('SELECT COALESCE(SUM(qty_change),0) FROM stock_ledger WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ? AND id < ?',
            [$this->tid(), $variant, $wh, $batch, $loc, $first]);
    }

    /** Sorts in walking order (rack, then item, then roll) and renders. @param array[] $lines */
    private function render(array $v, array $lines): void
    {
        usort($lines, fn($a, $b) => [$a['rack'] === '' ? 1 : 0, $a['rack'], $a['item'], $a['batch']] <=> [$b['rack'] === '' ? 1 : 0, $b['rack'], $b['item'], $b['batch']]);
        $tpl = PrintTemplate::get('delivery_note');
        $tpl['title'] = ''; $tpl['signature'] = 0; $tpl['terms'] = '';   // the voucher has its own signature boxes
        $this->view('app/print/stock_voucher', ['title' => $v['no'] . ' voucher', 'v' => $v, 'lines' => $lines, 'tpl' => $tpl, 'head' => PrintTemplate::head()], 'layouts/print');
    }

    public function delivery(string $id): void
    {
        $d = DB::one('SELECT d.*, c.name AS customer, w.name AS warehouse, o.order_no, u.name AS user_name FROM deliveries d JOIN customers c ON c.id = d.customer_id JOIN warehouses w ON w.id = d.warehouse_id
                      LEFT JOIN sales_orders o ON o.id = d.order_id LEFT JOIN users u ON u.id = d.created_by WHERE d.tenant_id = ? AND d.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
        $lines = [];
        foreach (DeliveryController::rows((int)$d['id'], $this->tid()) as $l) {
            if ($l['is_bundle']) continue;   // a bundle line is only a heading: its parts carry the stock
            $lines[] = ['rack' => (string)($l['rack'] ?? ''), 'to_rack' => '', 'item' => $l['item_name'] . ($l['vname'] ? ' — ' . $l['vname'] : ''), 'sku' => $l['sku'], 'batch' => (string)($l['batch_no'] ?? ''),
                'qty' => (float)$l['qty'], 'unit' => $l['unit'], 'before' => (int)$l['batch_id'] > 0 ? $this->before('delivery', (int)$d['id'], (int)$l['variant_id'], (int)$d['warehouse_id'], (int)$l['batch_id'], (int)$l['location_id']) : null];
        }
        $this->render(['kind' => 'delivery', 'no' => $d['delivery_no'], 'date' => $d['delivery_date'], 'back' => "sales/deliveries/{$d['id']}", 'warehouse' => $d['warehouse'], 'for' => $d['customer'],
            'meta' => ['Order' => $d['order_no'] ?? null, 'Warehouse' => $d['warehouse']], 'note' => $d['note'] ?? null, 'by' => $d['user_name']], $lines);
    }

    /** Stock adjustment: only the lines that take stock out. */
    public function adjustment(string $id): void
    {
        $this->doc($id, 'adjustment', false);
    }

    public function transfer(string $id): void
    {
        $this->doc($id, 'transfer', true);
    }

    private function doc(string $id, string $type, bool $transfer): void
    {
        $d = DB::one('SELECT d.*, w.name AS warehouse, w2.name AS to_warehouse, u.name AS user_name FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN warehouses w2 ON w2.id = d.to_warehouse_id
                      LEFT JOIN users u ON u.id = d.created_by WHERE d.tenant_id = ? AND d.id = ? AND d.type = ?', [$this->tid(), (int)$id, $type]) ?? $this->notFound();
        $rows = DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no, r1.code AS rack, r2.code AS to_rack
             FROM stock_doc_lines l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations r1 ON r1.id = l.location_id AND l.location_id > 0 LEFT JOIN locations r2 ON r2.id = l.to_location_id AND l.to_location_id > 0
             WHERE l.tenant_id = ? AND l.doc_id = ? ORDER BY l.id', [$this->tid(), $d['id']]);
        $lines = [];
        foreach ($rows as $l) {
            $q = (float)$l['qty'];
            if (!$transfer && $q >= 0) continue;   // adjustments: stock out only
            $lines[] = ['rack' => (string)($l['rack'] ?? ''), 'to_rack' => $transfer ? (string)($l['to_rack'] ?? '') : '', 'item' => $l['item_name'] . ($l['vname'] ? ' — ' . $l['vname'] : ''), 'sku' => $l['sku'], 'batch' => (string)($l['batch_no'] ?? ''),
                'qty' => abs($q), 'unit' => $l['unit'], 'before' => (int)$l['batch_id'] > 0 ? $this->before('stock_doc', (int)$d['id'], (int)$l['variant_id'], (int)$d['warehouse_id'], (int)$l['batch_id'], (int)$l['location_id']) : null];
        }
        if (!$lines) { flash('warning', 'This document does not take any stock out, so there is nothing to issue.'); redirect(($transfer ? 'stock/transfers/' : 'stock/adjustments/') . $d['id']); }
        $this->render(['kind' => $type, 'no' => $d['doc_no'], 'date' => substr((string)$d['created_at'], 0, 10), 'back' => ($transfer ? 'stock/transfers/' : 'stock/adjustments/') . $d['id'], 'warehouse' => $d['warehouse'],
            'for' => $transfer ? 'Transfer to ' . $d['to_warehouse'] : 'Stock adjustment', 'meta' => ['Warehouse' => $d['warehouse'], 'To' => $d['to_warehouse'] ?? null], 'note' => $d['note'] ?? null, 'by' => $d['user_name']], $lines);
    }
}
