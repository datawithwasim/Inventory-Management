<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;

/** Physical count: snapshot a warehouse, enter what was counted, post the differences as adjustments. */
final class StocktakeController extends StockDocController
{
    public function index(): void
    {
        $this->docList('stocktake', 'Stock-takes', 'app/stock/takes');
    }

    public function create(): void
    {
        $this->view('app/stock/take_form', ['title' => 'Start a stock-take', 'warehouses' => $this->activeWarehouses()]);
    }

    public function store(): void
    {
        $back = 'stock/takes/create';
        $d = $this->input();
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back);
        $note = trim((string)($d['note'] ?? '')) ?: null;
        if ($note !== null && mb_strlen($note) > 255) $this->bounce('Note is too long.', $back);

        $docId = DB::transaction(function () use ($wh, $note) {
            $t = $this->tid();
            $docId = DB::insert('stock_docs', [
                'tenant_id' => $t, 'type' => 'stocktake', 'doc_no' => Numbering::next($t, 'STK'),
                'warehouse_id' => $wh['id'], 'note' => $note, 'status' => 'draft', 'created_by' => Auth::user()['id'],
            ]);
            $snap = DB::all(
                'SELECT s.variant_id, s.batch_id, s.location_id, s.qty FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id
                 JOIN items i ON i.id = v.item_id
                 WHERE s.tenant_id = ? AND s.warehouse_id = ? AND s.qty > 0 ORDER BY i.name, v.name, s.batch_id, s.location_id', [$t, $wh['id']]);
            foreach ($snap as $s) {
                DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $s['variant_id'],
                    'batch_id' => $s['batch_id'], 'location_id' => $s['location_id'], 'qty' => $s['qty'], 'expected_qty' => $s['qty']]);
            }
            return $docId;
        });
        flash('success', 'Stock-take started. Enter the counted quantities.');
        redirect("stock/takes/$docId");
    }

    public function show(string $id): void
    {
        $doc = $this->loadDoc($id, 'stocktake');
        $this->view('app/stock/take_show', ['title' => $doc['doc_no'], 'doc' => $doc, 'lines' => $this->docLines((int)$doc['id'])]);
    }

    /** One form, two buttons: action=save keeps the draft, action=post applies the differences. */
    public function update(string $id): void
    {
        $doc = $this->loadDoc($id, 'stocktake');
        $back = "stock/takes/{$doc['id']}";
        if ($doc['status'] !== 'draft') $this->bounce('This stock-take is already posted.', $back);
        $counts = (array)($this->input()['counts'] ?? []);
        $post = ($this->input()['action'] ?? '') === 'post';

        try {
            $changes = DB::transaction(function () use ($doc, $counts, $post) {
                $t = $this->tid();
                $changes = 0;
                foreach ($this->docLines((int)$doc['id']) as $l) {
                    $raw = $counts[$l['id']] ?? null;
                    if ($raw === null || trim((string)$raw) === '') continue;
                    if (!is_numeric($raw) || (float)$raw < 0) throw new StockException($l['item_name'] . ': counted quantity must be zero or more.');
                    $counted = Stock::round((float)$raw);
                    DB::run('UPDATE stock_doc_lines SET qty = ? WHERE tenant_id = ? AND id = ?', [$counted, $t, $l['id']]);
                    if (!$post) continue;
                    // The count is the truth: correct whatever the system holds right now.
                    $now = Stock::balance((int)$l['variant_id'], (int)$doc['warehouse_id'], (int)$l['batch_id'], (int)$l['location_id']);
                    $delta = Stock::round($counted - $now);
                    if (abs($delta) > 0.0005) {
                        Stock::move((int)$l['variant_id'], (int)$doc['warehouse_id'], (int)$l['batch_id'], $delta,
                            'stocktake', 'stock_doc', (int)$doc['id'], null, 'Stock-take ' . $doc['doc_no'], (int)$l['location_id']);
                        $changes++;
                    }
                }
                if ($post) DB::run("UPDATE stock_docs SET status = 'posted', posted_at = NOW() WHERE tenant_id = ? AND id = ?", [$t, $doc['id']]);
                return $changes;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        if ($post) {
            Audit::log('stocktake_post', 'stock_doc', (int)$doc['id'], "$changes difference(s)");
            flash('success', $changes ? "Stock-take posted: $changes difference(s) corrected." : 'Stock-take posted: everything matched.');
        } else {
            flash('success', 'Counts saved.');
        }
        redirect($back);
    }

    public function destroy(string $id): void
    {
        $doc = $this->loadDoc($id, 'stocktake');
        if ($doc['status'] !== 'draft') $this->bounce('Posted stock-takes cannot be deleted.', "stock/takes/{$doc['id']}");
        DB::run('DELETE FROM stock_docs WHERE tenant_id = ? AND id = ?', [$this->tid(), $doc['id']]);
        flash('success', 'Draft stock-take deleted.');
        redirect('stock/takes');
    }
}
