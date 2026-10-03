<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\DB;
use Core\Numbering;

final class AdjustmentController extends StockDocController
{
    public const REASONS = [
        'opening' => 'Opening stock', 'damage' => 'Damaged', 'loss' => 'Lost / missing', 'found' => 'Found',
        'correction' => 'Count correction', 'other' => 'Other',
    ];

    public function index(): void
    {
        $this->docList('adjustment', 'Stock adjustments', 'app/stock/adjustments');
    }

    public function create(): void
    {
        $this->view('app/stock/adjustment_form', [
            'title' => 'New stock adjustment', 'warehouses' => $this->activeWarehouses(),
            'reasons' => self::REASONS, 'oldLines' => $this->oldLines(), 'racks' => $this->racksByWarehouse(),
        ]);
    }

    public function store(): void
    {
        $back = 'stock/adjustments/create';
        $d = $this->input();
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $reason = (string)($d['reason'] ?? '');
        if (!isset(self::REASONS[$reason])) $this->bounce('Choose a reason.', $back);
        $note = trim((string)($d['note'] ?? '')) ?: null;
        if ($note !== null && mb_strlen($note) > 255) $this->bounce('Note is too long.', $back);
        $lines = $this->postedLines($back);

        try {
            $docId = DB::transaction(function () use ($wh, $reason, $note, $lines) {
                $t = $this->tid();
                $docId = DB::insert('stock_docs', [
                    'tenant_id' => $t, 'type' => 'adjustment', 'doc_no' => Numbering::next($t, 'ADJ'),
                    'warehouse_id' => $wh['id'], 'reason' => $reason, 'note' => $note, 'status' => 'posted',
                    'created_by' => \Core\Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s'),
                ]);
                foreach ($lines as $n => $l) {
                    try {
                        $this->postLine($docId, $wh, $reason, $note, $l);
                    } catch (StockException $e) {
                        throw new StockException('Line ' . ($n + 1) . ': ' . $e->getMessage());
                    }
                }
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('stock_adjustment', 'stock_doc', $docId);
        flash('success', 'Stock adjustment posted.');
        redirect("stock/adjustments/$docId");
    }

    private function postLine(int $docId, array $wh, string $reason, ?string $note, array $l): void
    {
        $v = Stock::variant((int)$l['variant_id']);
        if (!$v || $v['is_bundle']) throw new StockException('Choose a valid item.');
        $qtyRaw = trim((string)($l['qty'] ?? ''));
        if ($qtyRaw === '' || !is_numeric($qtyRaw)) throw new StockException('Enter the quantity (use a minus sign to remove stock).');
        $qty = Stock::round((float)$qtyRaw);
        $cost = ($l['unit_cost'] ?? '') !== '' && is_numeric($l['unit_cost']) ? max(0.0, (float)$l['unit_cost']) : (float)$v['cost_price'];

        $batchId = 0;
        if ($v['track_batch']) {
            $choice = (string)($l['batch_id'] ?? '');
            if ($qty > 0 && ($choice === 'new' || $choice === '')) {
                $lot = trim((string)($l['lot_no'] ?? ''));
                if (mb_strlen($lot) > 60) throw new StockException('Supplier lot number is too long.');
                $batchId = Stock::newBatch((int)$v['id'], $qty, $lot, $cost, null, 'Created by stock adjustment');
            } elseif (ctype_digit($choice) && (int)$choice > 0) {
                $batchId = (int)$choice;
            } else {
                throw new StockException('Choose the batch (roll) to take stock from.');
            }
        }
        $type = $reason === 'opening' ? 'opening' : 'adjustment';
        $text = self::REASONS[$reason] . ($note ? ' — ' . $note : '');
        $rackId = (int)($l['location_id'] ?? 0);
        Stock::move((int)$v['id'], (int)$wh['id'], $batchId, $qty, $type, 'stock_doc', $docId, $cost, mb_substr($text, 0, 255), $rackId);
        DB::insert('stock_doc_lines', [
            'tenant_id' => $this->tid(), 'doc_id' => $docId, 'variant_id' => $v['id'], 'batch_id' => $batchId,
            'location_id' => $rackId, 'qty' => $qty, 'unit_cost' => $cost,
        ]);
    }

    public function show(string $id): void
    {
        $doc = $this->loadDoc($id, 'adjustment');
        $this->view('app/stock/doc_show', [
            'title' => $doc['doc_no'], 'doc' => $doc, 'lines' => $this->docLines((int)$doc['id']),
            'heading' => 'Stock adjustment', 'reasons' => self::REASONS, 'back' => 'stock/adjustments', 'signed' => true,
        ]);
    }
}
