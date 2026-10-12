<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;
use Core\Numbering;
use Core\Scope;

/**
 * Stock transfers.
 *  - Between two racks of ONE warehouse: instant (status "posted").
 *  - Between two warehouses: a dispatch (stock leaves the sender and is IN TRANSIT) followed by the receiving warehouse
 *    confirming what actually arrived and on which rack. What does not arrive is settled explicitly (lost, or sent back).
 */
final class TransferController extends StockDocController
{
    /** status => [label, colour] */
    public const STATUS = [
        'posted' => ['Moved', 'secondary'], 'in_transit' => ['In transit', 'warning'], 'partial' => ['Partly received', 'info'],
        'received' => ['Received', 'success'], 'received_short' => ['Received (shortage)', 'danger'], 'cancelled' => ['Cancelled', 'dark'],
    ];
    private const OPEN = ['in_transit', 'partial'];

    // ------------------------------------------------------------------ list

    public function index(): void
    {
        $t = $this->tid();
        $status = (string)($_GET['status'] ?? '');
        $wh = (int)($_GET['warehouse'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $where = "d.tenant_id = ? AND d.type = 'transfer'";
        $params = [$t];
        if ($status === 'open') $where .= " AND d.status IN ('in_transit','partial')";
        elseif ($status === 'overdue') $where .= " AND d.status IN ('in_transit','partial') AND d.expected_date IS NOT NULL AND d.expected_date < CURDATE()";
        elseif (isset(self::STATUS[$status])) { $where .= ' AND d.status = ?'; $params[] = $status; }
        if ($wh) { $where .= ' AND (d.warehouse_id = ? OR d.to_warehouse_id = ?)'; array_push($params, $wh, $wh); }
        $where .= Scope::sqlEither('d.warehouse_id', 'd.to_warehouse_id');   // a warehouse user sees what they send and what is sent to them
        $total = (int)DB::val("SELECT COUNT(*) FROM stock_docs d WHERE $where", $params);
        $rows = DB::all(
            "SELECT d.*, w.name AS warehouse, w2.name AS to_warehouse, u.name AS user_name,
                    (SELECT COUNT(*) FROM stock_doc_lines l WHERE l.doc_id = d.id) AS line_count,
                    (SELECT COALESCE(SUM(l.qty),0) FROM stock_doc_lines l WHERE l.doc_id = d.id) AS qty_sent,
                    (SELECT COALESCE(SUM(l.qty_received),0) FROM stock_doc_lines l WHERE l.doc_id = d.id) AS qty_received,
                    (SELECT COALESCE(SUM(l.qty - l.qty_received - l.qty_lost - l.qty_returned),0) FROM stock_doc_lines l WHERE l.doc_id = d.id) AS qty_pending
             FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN warehouses w2 ON w2.id = d.to_warehouse_id
             LEFT JOIN users u ON u.id = d.created_by WHERE $where ORDER BY d.id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $params);
        $this->view('app/stock/transfers', [
            'title' => 'Stock transfers', 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int)ceil($total / 30)), 'status' => $status, 'wh' => $wh,
            'warehouses' => DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? ORDER BY name', [$t]), 'statuses' => self::STATUS,
            'counts' => [
                'open' => (int)DB::val("SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND status IN ('in_transit','partial')" . Scope::sqlEither('warehouse_id', 'to_warehouse_id'), [$t]),
                'overdue' => (int)DB::val("SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND status IN ('in_transit','partial') AND expected_date IS NOT NULL AND expected_date < CURDATE()" . Scope::sqlEither('warehouse_id', 'to_warehouse_id'), [$t]),
                'short' => (int)DB::val("SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND status = 'received_short'" . Scope::sqlEither('warehouse_id', 'to_warehouse_id'), [$t]),
            ],
        ]);
    }

    // ------------------------------------------------------------------ dispatch

    public function create(): void
    {
        $this->view('app/stock/transfer_form', [
            'title' => 'New stock transfer', 'warehouses' => $this->activeWarehouses(), 'all_warehouses' => $this->allActiveWarehouses(), 'oldLines' => $this->oldLines(), 'racks' => $this->racksByWarehouse(),
        ]);
    }

    private function optionalText(mixed $v, int $max, string $label, string $back): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;
        if (mb_strlen($v) > $max) $this->bounce("$label is too long (max $max characters).", $back);
        return $v;
    }

    public function store(): void
    {
        $back = 'stock/transfers/create';
        $this->enforceFields('transfer', $back);
        $d = $this->input();
        $from = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back, 'source warehouse');
        $to = $this->activeWarehouse($d['to_warehouse_id'] ?? 0, $back, 'destination warehouse', false);
        $sameWh = (int)$from['id'] === (int)$to['id'];
        $note = $this->optionalText($d['note'] ?? '', 255, 'Note', $back);
        $transport = $this->optionalText($d['transport'] ?? '', 100, 'Transporter', $back);
        $vehicle = $this->optionalText($d['vehicle_no'] ?? '', 30, 'Vehicle number', $back);
        $lr = $this->optionalText($d['lr_no'] ?? '', 60, 'LR / challan number', $back);
        $expected = null;
        $rawExp = trim((string)($d['expected_date'] ?? ''));
        if ($rawExp !== '' && !$sameWh) {
            $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawExp, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
            if (!$ok) $this->bounce('Expected arrival is not a valid date.', $back);
            if ($rawExp < date('Y-m-d')) $this->bounce('Expected arrival cannot be in the past.', $back);
            $expected = $rawExp;
        }
        $lines = $this->postedLines($back);

        try {
            $docId = DB::transaction(function () use ($from, $to, $note, $lines, $sameWh, $transport, $vehicle, $lr, $expected) {
                $t = $this->tid();
                $now = date('Y-m-d H:i:s');
                $docId = DB::insert('stock_docs', [
                    'tenant_id' => $t, 'type' => 'transfer', 'doc_no' => Numbering::next($t, 'TRF'),
                    'warehouse_id' => $from['id'], 'to_warehouse_id' => $to['id'], 'note' => $note, 'status' => $sameWh ? 'posted' : 'in_transit',
                    'created_by' => Auth::user()['id'], 'posted_at' => $now, 'dispatched_at' => $sameWh ? null : $now,
                    'expected_date' => $expected, 'transport' => $sameWh ? null : $transport, 'vehicle_no' => $sameWh ? null : $vehicle, 'lr_no' => $sameWh ? null : $lr,
                ]);
                $seen = [];
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
                        $toRack = (int)($l['to_location_id'] ?? 0);   // between warehouses this is only a suggestion: the receiving side confirms the rack on arrival
                        if (!$sameWh && $toRack > 0) {
                            $rk = Stock::location($toRack);
                            if (!$rk || (int)$rk['warehouse_id'] !== (int)$to['id']) throw new StockException('That rack does not belong to the chosen warehouse.');
                        }
                        if ($sameWh && $fromRack === $toRack) {
                            throw new StockException('Source and destination are the same place. Choose a different warehouse or rack.');
                        }
                        $key = "{$v['id']}:$batchId:$fromRack";
                        if (isset($seen[$key])) throw new StockException('The same item / roll / rack is listed twice. Combine the quantities.');
                        $seen[$key] = 1;
                        if ($sameWh) {
                            $text = 'Transfer ' . $from['name'] . ' → ' . $to['name'];
                            Stock::move((int)$v['id'], (int)$from['id'], $batchId, -$qty, 'transfer_out', 'stock_doc', $docId, null, $text, $fromRack);
                            Stock::move((int)$v['id'], (int)$to['id'], $batchId, $qty, 'transfer_in', 'stock_doc', $docId, null, $text, $toRack);
                            DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $v['id'], 'batch_id' => $batchId,
                                'location_id' => $fromRack, 'to_location_id' => $toRack, 'qty' => $qty, 'qty_received' => $qty]);
                        } else {
                            Stock::move((int)$v['id'], (int)$from['id'], $batchId, -$qty, 'transfer_out', 'stock_doc', $docId, null, 'Dispatched to ' . $to['name'] . ' (in transit)', $fromRack);
                            $lineId = DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $v['id'], 'batch_id' => $batchId,
                                'location_id' => $fromRack, 'to_location_id' => $toRack, 'qty' => $qty]);
                            DB::insert('stock_transfer_events', ['tenant_id' => $t, 'doc_id' => $docId, 'line_id' => $lineId, 'event' => 'dispatch', 'qty' => $qty,
                                'location_id' => $fromRack, 'user_id' => Auth::user()['id']]);
                        }
                    } catch (StockException $e) {
                        throw new StockException('Line ' . ($n + 1) . ': ' . $e->getMessage());
                    }
                }
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log($sameWh ? 'stock_transfer' : 'transfer_dispatch', 'stock_doc', $docId, $sameWh ? null : $from['name'] . ' → ' . $to['name']);
        flash('success', $sameWh ? 'Stock moved.' : 'Dispatched. The stock is now in transit until ' . $to['name'] . ' confirms what it received.');
        redirect("stock/transfers/$docId");
    }

    // ------------------------------------------------------------------ one transfer

    /** Lines with what is still on the road, and the totals. */
    private function tracked(int $docId): array
    {
        $lines = $this->docLines($docId);
        foreach ($lines as &$l) {
            $l['pending'] = Stock::round((float)$l['qty'] - (float)$l['qty_received'] - (float)$l['qty_lost'] - (float)$l['qty_returned']);
        }
        unset($l);
        return $lines;
    }

    private function events(int $docId): array
    {
        return DB::all(
            "SELECT e.*, u.name AS user_name, i.name AS item_name, v.name AS vname, v.sku, b.batch_no, k.code AS rack, un.short_name AS unit
             FROM stock_transfer_events e LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN stock_doc_lines l ON l.id = e.line_id LEFT JOIN item_variants v ON v.id = l.variant_id LEFT JOIN items i ON i.id = v.item_id
             LEFT JOIN units un ON un.id = i.unit_id LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0 LEFT JOIN locations k ON k.id = e.location_id AND e.location_id > 0
             WHERE e.tenant_id = ? AND e.doc_id = ? ORDER BY e.id", [$this->tid(), $docId]);
    }

    public function show(string $id): void
    {
        $doc = $this->loadDoc($id, 'transfer');
        $lines = $this->tracked((int)$doc['id']);
        $sum = ['sent' => 0.0, 'received' => 0.0, 'lost' => 0.0, 'returned' => 0.0, 'pending' => 0.0];
        foreach ($lines as $l) {
            $sum['sent'] += (float)$l['qty']; $sum['received'] += (float)$l['qty_received']; $sum['lost'] += (float)$l['qty_lost'];
            $sum['returned'] += (float)$l['qty_returned']; $sum['pending'] += (float)$l['pending'];
        }
        $between = $doc['to_warehouse_id'] !== null && (int)$doc['to_warehouse_id'] !== (int)$doc['warehouse_id'];
        $this->view('app/stock/doc_show', [
            'title' => $doc['doc_no'], 'doc' => $doc, 'lines' => $lines, 'heading' => 'Stock transfer', 'reasons' => [], 'back' => 'stock/transfers', 'signed' => false,
            'events' => $this->events((int)$doc['id']), 'sum' => $sum, 'between' => $between, 'open' => in_array($doc['status'], self::OPEN, true),
            'racks' => $this->racksByWarehouse(), 'statuses' => self::STATUS,
            'overdue' => in_array($doc['status'], self::OPEN, true) && $doc['expected_date'] !== null && $doc['expected_date'] < date('Y-m-d'),
            // what this user may do with it (their warehouse and their role)
            'can' => [
                'receive' => $between && in_array($doc['status'], self::OPEN, true) && \Core\Auth::can('stock.receive') && Scope::allows((int)$doc['to_warehouse_id']),
                'settle' => $between && in_array($doc['status'], self::OPEN, true) && \Core\Auth::can('stock.adjust') && (Scope::warehouse() === null || in_array(Scope::warehouse(), [(int)$doc['warehouse_id'], (int)$doc['to_warehouse_id']], true)),
                'cancel' => $between && $doc['status'] === 'in_transit' && \Core\Auth::can('stock.transfer') && Scope::allows((int)$doc['warehouse_id']),
            ],
        ]);
    }

    /** Warehouse users act only on their side of a transfer: 'to' = the receiver, 'from' = the sender, 'either' = settling a shortage. */
    private function guard(array $doc, string $side, string $back): void
    {
        $own = Scope::warehouse();
        if ($own === null) return;
        $from = (int)$doc['warehouse_id'] === $own; $to = (int)$doc['to_warehouse_id'] === $own;
        $ok = match ($side) { 'to' => $to, 'from' => $from, default => $from || $to };
        if ($ok) return;
        $name = fn(int $id) => (string)DB::val('SELECT name FROM warehouses WHERE id = ?', [$id]);
        $this->bounce(match ($side) {
            'to' => 'Only ' . $name((int)$doc['to_warehouse_id']) . ', the receiving warehouse, can receive this transfer.',
            'from' => 'Only ' . $name((int)$doc['warehouse_id']) . ', the sending warehouse, can cancel this transfer.',
            default => 'Only the sending or the receiving warehouse can settle this transfer.',
        }, $back);
    }

    private function openDoc(string $id, string $side, string $back = ''): array
    {
        $doc = $this->loadDoc($id, 'transfer');
        $back = $back ?: "stock/transfers/{$doc['id']}";
        if (!in_array($doc['status'], self::OPEN, true)) $this->bounce('This transfer is not waiting for anything any more.', $back);
        $this->guard($doc, $side, $back);
        return $doc;
    }

    /** Works the status out from the lines: still on the road, partly in, or finished (cleanly or with a shortage). */
    private function refreshStatus(array $doc): string
    {
        $r = DB::one('SELECT COALESCE(SUM(qty),0) AS sent, COALESCE(SUM(qty_received),0) AS rec, COALESCE(SUM(qty_lost + qty_returned),0) AS gone
                      FROM stock_doc_lines WHERE tenant_id = ? AND doc_id = ?', [$this->tid(), $doc['id']]);
        $pending = (float)$r['sent'] - (float)$r['rec'] - (float)$r['gone'];
        if ($pending <= 0.0005) {
            $status = (float)$r['gone'] > 0.0005 ? 'received_short' : 'received';
            DB::run('UPDATE stock_docs SET status = ?, received_at = NOW(), received_by = ? WHERE tenant_id = ? AND id = ?', [$status, Auth::user()['id'], $this->tid(), $doc['id']]);
            return $status;
        }
        $status = ((float)$r['rec'] + (float)$r['gone']) > 0.0005 ? 'partial' : 'in_transit';
        DB::run('UPDATE stock_docs SET status = ? WHERE tenant_id = ? AND id = ?', [$status, $this->tid(), $doc['id']]);
        return $status;
    }

    /** The receiving warehouse confirms what arrived and puts it on racks. Can be done in several goes. */
    public function receive(string $id): void
    {
        $doc = $this->openDoc($id, 'to');
        $back = "stock/transfers/{$doc['id']}";
        $d = $this->input();
        $note = $this->optionalText($d['note'] ?? '', 255, 'Note', $back);
        $in = (array)($d['lines'] ?? []);
        $to = Stock::warehouse((int)$doc['to_warehouse_id']) ?? $this->notFound();
        $hasRacks = (bool)DB::val('SELECT 1 FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND is_active = 1 LIMIT 1', [$this->tid(), $to['id']]);

        try {
            $count = DB::transaction(function () use ($doc, $in, $note, $to, $hasRacks) {
                $t = $this->tid();
                $count = 0;
                foreach ($this->tracked((int)$doc['id']) as $l) {
                    $row = $in[$l['id']] ?? null;
                    if (!is_array($row)) continue;
                    $raw = trim((string)($row['qty'] ?? ''));
                    if ($raw === '' || (is_numeric($raw) && (float)$raw == 0.0)) continue;
                    if (!is_numeric($raw) || (float)$raw < 0) throw new StockException($l['item_name'] . ': enter the quantity received (zero or more).');
                    $qty = Stock::round((float)$raw);
                    if ($qty > (float)$l['pending'] + 0.0005) throw new StockException($l['item_name'] . ': only ' . Stock::fmt((float)$l['pending']) . ' ' . $l['unit'] . ' is still on the way.');
                    $given = $row['location_id'] ?? '';
                    $rack = $given === '' || $given === null ? (int)$l['to_location_id'] : (int)$given;   // not said: the rack suggested at dispatch
                    if ($rack === 0 && $hasRacks && \Core\Settings::bool('require_rack')) throw new StockException($l['item_name'] . ': choose the rack it is put on (your settings require it).');
                    $lineNote = $this->optionalText($row['note'] ?? '', 255, 'Line note', "stock/transfers/{$doc['id']}");
                    Stock::move((int)$l['variant_id'], (int)$to['id'], (int)$l['batch_id'], $qty, 'transfer_in', 'stock_doc', (int)$doc['id'], null,
                        'Received from transfer ' . $doc['doc_no'] . ($lineNote ? ' — ' . $lineNote : ''), $rack);
                    DB::run('UPDATE stock_doc_lines SET qty_received = qty_received + ? WHERE tenant_id = ? AND id = ?', [$qty, $t, $l['id']]);
                    DB::insert('stock_transfer_events', ['tenant_id' => $t, 'doc_id' => $doc['id'], 'line_id' => $l['id'], 'event' => 'receive', 'qty' => $qty,
                        'location_id' => $rack, 'note' => $lineNote ?? $note, 'user_id' => Auth::user()['id']]);
                    $count++;
                }
                if ($count === 0) throw new StockException('Enter the quantity received on at least one line.');
                return $count;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        $status = $this->refreshStatus($doc);
        Audit::log('transfer_receive', 'stock_doc', (int)$doc['id'], "$count line(s) → $status");
        flash($status === 'received' ? 'success' : 'info', match ($status) {
            'received' => 'All received. The transfer is complete and the stock is on the racks.',
            'received_short' => 'Received. The transfer is closed with a shortage.',
            default => 'Receipt saved. The rest is still on the way: receive it when it arrives, or settle what will not come.',
        });
        redirect($back);
    }

    /** What did not arrive: write it off as lost, or send it back to the sender's racks. */
    public function settle(string $id): void
    {
        $doc = $this->openDoc($id, 'either');
        $back = "stock/transfers/{$doc['id']}";
        $d = $this->input();
        $in = (array)($d['lines'] ?? []);
        try {
            $count = DB::transaction(function () use ($doc, $in) {
                $t = $this->tid();
                $count = 0;
                foreach ($this->tracked((int)$doc['id']) as $l) {
                    $row = $in[$l['id']] ?? null;
                    if (!is_array($row)) continue;
                    $raw = trim((string)($row['qty'] ?? ''));
                    if ($raw === '' || (is_numeric($raw) && (float)$raw == 0.0)) continue;
                    if (!is_numeric($raw) || (float)$raw < 0) throw new StockException($l['item_name'] . ': enter a quantity of zero or more.');
                    $qty = Stock::round((float)$raw);
                    if ($qty > (float)$l['pending'] + 0.0005) throw new StockException($l['item_name'] . ': only ' . Stock::fmt((float)$l['pending']) . ' ' . $l['unit'] . ' is unaccounted for.');
                    $action = (string)($row['action'] ?? '');
                    $note = $this->optionalText($row['note'] ?? '', 255, 'Note', "stock/transfers/{$doc['id']}");
                    if ($action === 'lost') {
                        DB::run('UPDATE stock_doc_lines SET qty_lost = qty_lost + ? WHERE tenant_id = ? AND id = ?', [$qty, $t, $l['id']]);
                        DB::insert('stock_transfer_events', ['tenant_id' => $t, 'doc_id' => $doc['id'], 'line_id' => $l['id'], 'event' => 'lost', 'qty' => $qty,
                            'note' => $note ?? 'Lost / damaged in transit', 'user_id' => Auth::user()['id']]);
                    } elseif ($action === 'return') {
                        $rack = (int)($row['location_id'] ?? 0);
                        Stock::move((int)$l['variant_id'], (int)$doc['warehouse_id'], (int)$l['batch_id'], $qty, 'transfer_return', 'stock_doc', (int)$doc['id'], null,
                            'Sent back to the sender — transfer ' . $doc['doc_no'] . ($note ? ' — ' . $note : ''), $rack);
                        DB::run('UPDATE stock_doc_lines SET qty_returned = qty_returned + ? WHERE tenant_id = ? AND id = ?', [$qty, $t, $l['id']]);
                        DB::insert('stock_transfer_events', ['tenant_id' => $t, 'doc_id' => $doc['id'], 'line_id' => $l['id'], 'event' => 'returned', 'qty' => $qty,
                            'location_id' => $rack, 'note' => $note, 'user_id' => Auth::user()['id']]);
                    } else {
                        throw new StockException($l['item_name'] . ': choose "lost" or "send back".');
                    }
                    $count++;
                }
                if ($count === 0) throw new StockException('Enter a quantity on at least one line.');
                return $count;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        $status = $this->refreshStatus($doc);
        Audit::log('transfer_settle', 'stock_doc', (int)$doc['id'], "$count line(s) → $status");
        flash('success', $status === 'received_short' ? 'Settled. The transfer is closed with a shortage.' : 'Settled. Some stock is still on the way.');
        redirect($back);
    }

    /** Takes a dispatch back before anything arrived: every roll goes back to the rack it left. */
    public function cancel(string $id): void
    {
        $doc = $this->openDoc($id, 'from');
        $back = "stock/transfers/{$doc['id']}";
        $touched = (float)DB::val('SELECT COALESCE(SUM(qty_received + qty_lost + qty_returned),0) FROM stock_doc_lines WHERE tenant_id = ? AND doc_id = ?', [$this->tid(), $doc['id']]);
        if ($touched > 0.0005 || $doc['status'] !== 'in_transit') $this->bounce('Part of this transfer was already received or settled, so it cannot be cancelled.', $back);
        try {
            DB::transaction(function () use ($doc) {
                $t = $this->tid();
                foreach ($this->tracked((int)$doc['id']) as $l) {
                    $rackId = (int)$l['location_id'];
                    $rack = $rackId > 0 ? Stock::location($rackId) : null;
                    if ($rackId > 0 && (!$rack || !$rack['is_active'])) $rackId = 0;   // the old rack is switched off: it goes back with no rack
                    Stock::move((int)$l['variant_id'], (int)$doc['warehouse_id'], (int)$l['batch_id'], (float)$l['qty'], 'transfer_return', 'stock_doc', (int)$doc['id'], null,
                        'Dispatch cancelled — transfer ' . $doc['doc_no'], $rackId);
                    DB::run('UPDATE stock_doc_lines SET qty_returned = qty WHERE tenant_id = ? AND id = ?', [$t, $l['id']]);
                    DB::insert('stock_transfer_events', ['tenant_id' => $t, 'doc_id' => $doc['id'], 'line_id' => $l['id'], 'event' => 'cancel', 'qty' => $l['qty'],
                        'location_id' => $rackId, 'user_id' => Auth::user()['id']]);
                }
                DB::run("UPDATE stock_docs SET status = 'cancelled' WHERE tenant_id = ? AND id = ?", [$t, $doc['id']]);
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('transfer_cancel', 'stock_doc', (int)$doc['id'], $doc['doc_no']);
        flash('success', 'Dispatch cancelled. The stock is back on the sender\'s racks.');
        redirect($back);
    }

    /** The paper that travels with the goods: what is on the vehicle, with blanks for the receiver to fill in. */
    public function challan(string $id): void
    {
        $doc = $this->loadDoc($id, 'transfer');
        $w = fn(int $wid) => DB::one('SELECT name, address FROM warehouses WHERE tenant_id = ? AND id = ?', [$this->tid(), $wid]) ?? ['name' => '', 'address' => ''];
        $this->view('app/print/transfer_challan', [
            'title' => $doc['doc_no'] . ' challan', 'doc' => $doc, 'lines' => $this->tracked((int)$doc['id']),
            'from' => $w((int)$doc['warehouse_id']), 'to' => $w((int)$doc['to_warehouse_id']),
            'tpl' => \App\Models\PrintTemplate::get('delivery_note'),
        ], 'layouts/print');
    }
}
