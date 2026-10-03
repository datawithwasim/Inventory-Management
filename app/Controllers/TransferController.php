<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

final class TransferController extends StockDocController
{
    public function index(): void
    {
        $this->docList('transfer', 'Stock transfers', 'app/stock/transfers');
    }

    public function create(): void
    {
        $this->view('app/stock/transfer_form', [
            'title' => 'New stock transfer', 'warehouses' => $this->activeWarehouses(), 'oldLines' => $this->oldLines(), 'racks' => $this->racksByWarehouse(),
        ]);
    }

    public function store(): void
    {
        $back = 'stock/transfers/create';
        $d = $this->input();
        $from = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back, 'source warehouse');
        $to = $this->activeWarehouse($d['to_warehouse_id'] ?? 0, $back, 'destination warehouse');
        $sameWh = (int)$from['id'] === (int)$to['id'];
        $note = trim((string)($d['note'] ?? '')) ?: null;
        if ($note !== null && mb_strlen($note) > 255) $this->bounce('Note is too long.', $back);
        $lines = $this->postedLines($back);

        try {
            $docId = DB::transaction(function () use ($from, $to, $note, $lines, $sameWh) {
                $t = $this->tid();
                $docId = DB::insert('stock_docs', [
                    'tenant_id' => $t, 'type' => 'transfer', 'doc_no' => Numbering::next($t, 'TRF'),
                    'warehouse_id' => $from['id'], 'to_warehouse_id' => $to['id'], 'note' => $note, 'status' => 'posted',
                    'created_by' => Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s'),
                ]);
                foreach ($lines as $n => $l) {
                    try {
                        $v = Stock::variant((int)$l['variant_id']);
                        if (!$v || $v['is_bundle']) throw new StockException('Choose a valid item.');
                        $qtyRaw = trim((string)($l['qty'] ?? ''));
                        $qty = is_numeric($qtyRaw) ? Stock::round((float)$qtyRaw) : 0.0;
                        if ($qty <= 0) throw new StockException('Enter a quantity above zero.');
                        $batchId = $v['track_batch'] ? (int)($l['batch_id'] ?? 0) : 0;
                        if ($v['track_batch'] && $batchId <= 0) throw new StockException('Choose the batch (roll) to move.');
                        $fromRack = (int)($l['location_id'] ?? 0);
                        $toRack = (int)($l['to_location_id'] ?? 0);
                        if ($sameWh && $fromRack === $toRack) {
                            throw new StockException('Source and destination are the same place. Choose a different warehouse or rack.');
                        }
                        $text = 'Transfer ' . $from['name'] . ' → ' . $to['name'];
                        Stock::move((int)$v['id'], (int)$from['id'], $batchId, -$qty, 'transfer_out', 'stock_doc', $docId, null, $text, $fromRack);
                        Stock::move((int)$v['id'], (int)$to['id'], $batchId, $qty, 'transfer_in', 'stock_doc', $docId, null, $text, $toRack);
                        DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $v['id'], 'batch_id' => $batchId,
                            'location_id' => $fromRack, 'to_location_id' => $toRack, 'qty' => $qty]);
                    } catch (StockException $e) {
                        throw new StockException('Line ' . ($n + 1) . ': ' . $e->getMessage());
                    }
                }
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('stock_transfer', 'stock_doc', $docId);
        flash('success', 'Stock moved.');
        redirect("stock/transfers/$docId");
    }

    public function show(string $id): void
    {
        $doc = $this->loadDoc($id, 'transfer');
        $this->view('app/stock/doc_show', [
            'title' => $doc['doc_no'], 'doc' => $doc, 'lines' => $this->docLines((int)$doc['id']),
            'heading' => 'Stock transfer', 'reasons' => [], 'back' => 'stock/transfers', 'signed' => false,
        ]);
    }
}
