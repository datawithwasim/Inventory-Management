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
use Core\Scope;

/**
 * Catalogue launches. The catalogue team orders fabric from a warehouse for a new catalogue; the warehouse dispatches it roll by roll
 * to the place the catalogue team named (it only comes back if the wrong fabric was sent); when the printed books arrive, in sizes,
 * they are received into stock as items of their own, costed from the fabric that went into them.
 */
final class LaunchController extends StockDocController
{
    /** Rolls are issued whole, so a little more than ordered is accepted. */
    private const TOLERANCE = 0.10;
    private const OPEN = ['ordered', 'partial', 'dispatched'];

    public const STATUS = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Partly dispatched', 'dispatched' => 'Fabric dispatched', 'received' => 'Books received', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

    private function load(string $id): array
    {
        return DB::one(
            'SELECT l.*, c.name AS catalogue, c.code AS catalogue_code, w.name AS warehouse, u.name AS user_name
             FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id JOIN warehouses w ON w.id = l.warehouse_id LEFT JOIN users u ON u.id = l.created_by
             WHERE l.tenant_id = ? AND l.id = ?' . Scope::sql('l.warehouse_id'), [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function fabric(int $launchId): array
    {
        return DB::all(
            'SELECT f.*, v.sku, i.name AS item_name, i.track_batch, u.short_name AS unit, u.allow_decimal
             FROM launch_fabric f JOIN item_variants v ON v.id = f.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE f.tenant_id = ? AND f.launch_id = ? ORDER BY f.id', [$this->tid(), $launchId]);
    }

    private function sizes(int $launchId): array
    {
        return DB::all('SELECT s.*, v.item_id FROM launch_sizes s LEFT JOIN item_variants v ON v.id = s.variant_id WHERE s.tenant_id = ? AND s.launch_id = ? ORDER BY s.id', [$this->tid(), $launchId]);
    }

    /** What the launch has cost so far and what each received copy cost. */
    private function money(array $l, array $fabric): array
    {
        $value = round(array_sum(array_map(fn($f) => (float)$f['value_issued'], $fabric)), 2);
        $copies = (int)DB::val('SELECT COALESCE(SUM(qty_received),0) FROM launch_sizes WHERE tenant_id = ? AND launch_id = ?', [$this->tid(), $l['id']]);
        $total = round($value + (float)$l['extra_cost'], 2);
        return ['fabric_value' => $value, 'extra_cost' => (float)$l['extra_cost'], 'total_cost' => $total, 'copies' => $copies, 'cost_per_copy' => $copies > 0 ? round($total / $copies, 2) : null,
            'unabsorbed' => round($value - (float)$l['fabric_absorbed'], 2)];
    }

    /** Where the fabric order stands, from what has been dispatched (less what came back). Only for an order that is out. */
    private function refreshStatus(int $id): void
    {
        $l = DB::one('SELECT status FROM catalogue_launches WHERE tenant_id = ? AND id = ?', [$this->tid(), $id]);
        if (!$l || !in_array($l['status'], self::OPEN, true)) return;
        $rows = DB::all('SELECT qty_ordered, qty_issued - qty_returned AS net FROM launch_fabric WHERE tenant_id = ? AND launch_id = ?', [$this->tid(), $id]);
        $all = (bool)$rows; $any = false;
        foreach ($rows as $r) { if ((float)$r['net'] > 0.0005) $any = true; if ((float)$r['net'] + 0.0005 < (float)$r['qty_ordered']) $all = false; }
        DB::run('UPDATE catalogue_launches SET status = ? WHERE tenant_id = ? AND id = ?', [$all ? 'dispatched' : ($any ? 'partial' : 'ordered'), $this->tid(), $id]);
    }

    /* ---------- list ---------- */

    public function index(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? '');
        $where = 'l.tenant_id = ?' . Scope::sql('l.warehouse_id'); $p = [$t];
        if ($q !== '') { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $where .= ' AND (l.launch_no LIKE ? OR c.name LIKE ? OR c.code LIKE ?)'; array_push($p, $like, $like, $like); }
        if (isset(self::STATUS[$status])) { $where .= ' AND l.status = ?'; $p[] = $status; }
        elseif ($status === 'open') $where .= " AND l.status IN ('draft','ordered','partial','dispatched','received')";
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = (int)DB::val("SELECT COUNT(*) FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id WHERE $where", $p);
        $rows = DB::all(
            "SELECT l.*, c.name AS catalogue, c.code AS catalogue_code, w.name AS warehouse,
                    (SELECT COALESCE(SUM(f.qty_ordered),0) FROM launch_fabric f WHERE f.launch_id = l.id) AS fabric_ordered,
                    (SELECT COALESCE(SUM(f.qty_issued - f.qty_returned),0) FROM launch_fabric f WHERE f.launch_id = l.id) AS fabric_issued,
                    (SELECT COALESCE(SUM(s.qty_planned),0) FROM launch_sizes s WHERE s.launch_id = l.id) AS copies_planned,
                    (SELECT COALESCE(SUM(s.qty_received),0) FROM launch_sizes s WHERE s.launch_id = l.id) AS copies_received
             FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id JOIN warehouses w ON w.id = l.warehouse_id
             WHERE $where ORDER BY l.id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $p);
        $this->view('app/launches/index', ['title' => 'Catalogue launches', 'rows' => $rows, 'q' => $q, 'status' => $status, 'statuses' => self::STATUS, 'page' => $page, 'pages' => max(1, (int)ceil($total / 30)), 'total' => $total]);
    }

    /* ---------- create / edit ---------- */

    private function formData(?array $l, array $extra = []): array
    {
        return $extra + [
            'catalogues' => DB::all('SELECT id, name, code, named FROM catalogues WHERE tenant_id = ? AND is_active = 1 ORDER BY name', [$this->tid()]),
            'warehouses' => $this->allActiveWarehouses(), 'launch' => $l,
            'fabric' => $l ? $this->fabric((int)$l['id']) : [], 'sizes' => $l ? $this->sizes((int)$l['id']) : [],
        ];
    }

    public function create(): void
    {
        $this->view('app/launches/form', $this->formData(null, ['title' => 'New catalogue launch', 'preset' => ['catalogue_id' => (int)($_GET['catalogue'] ?? 0)]]));
    }

    public function edit(string $id): void
    {
        $l = $this->load($id);
        if (!in_array($l['status'], ['draft', ...self::OPEN], true)) {
            flash('danger', 'This launch can no longer be changed.');
            redirect("launches/{$l['id']}");
        }
        $this->view('app/launches/form', $this->formData($l, ['title' => 'Edit ' . $l['launch_no']]));
    }

    /** The posted header, fabric lines and sizes, checked. */
    private function collect(string $back): array
    {
        $d = $this->input();
        $t = $this->tid();
        $cat = DB::one('SELECT id FROM catalogues WHERE tenant_id = ? AND id = ?', [$t, (int)($d['catalogue_id'] ?? 0)]);
        if (!$cat) $this->bounce('Choose the catalogue this launch is for.', $back);
        $wh = Stock::warehouse((int)($d['warehouse_id'] ?? 0));
        if (!$wh || !$wh['is_active']) $this->bounce('Choose the warehouse that will dispatch the fabric.', $back);
        $shipTo = trim((string)($d['ship_to'] ?? ''));
        $note = trim((string)($d['note'] ?? ''));
        if (mb_strlen($shipTo) > 255 || mb_strlen($note) > 255) $this->bounce('The address or note is too long (max 255 characters).', $back);

        $fabric = [];
        foreach ((array)($d['fabric'] ?? []) as $n => $f) {
            if (!is_array($f) || (int)($f['variant_id'] ?? 0) <= 0) continue;
            $prefix = 'Fabric line ' . ($n + 1);
            $v = Stock::variant((int)$f['variant_id']);
            if (!$v || $v['is_bundle'] || !$v['is_active']) $this->bounce("$prefix: choose a valid item.", $back);
            if (isset($fabric[(int)$v['id']])) $this->bounce("$prefix: " . $v['item_name'] . ' is listed twice. Put the total on one line.', $back);
            $raw = trim((string)($f['qty'] ?? ''));
            if ($raw === '' || !is_numeric($raw) || (float)$raw <= 0) $this->bounce("$prefix: enter the quantity needed.", $back);
            $qty = Stock::round((float)$raw);
            if (!$v['allow_decimal'] && floor($qty) != $qty) $this->bounce("$prefix: " . $v['item_name'] . ' is counted in whole ' . $v['unit'] . '.', $back);
            $fnote = trim((string)($f['note'] ?? ''));
            if (mb_strlen($fnote) > 150) $this->bounce("$prefix: the note is too long.", $back);
            $fabric[(int)$v['id']] = ['variant_id' => (int)$v['id'], 'qty' => $qty, 'note' => $fnote !== '' ? $fnote : null];
        }
        $sizes = [];
        foreach ((array)($d['sizes'] ?? []) as $n => $s) {
            if (!is_array($s)) continue;
            $name = trim((string)($s['size_name'] ?? ''));
            $raw = trim((string)($s['qty'] ?? ''));
            if ($name === '' && $raw === '') continue;
            $prefix = 'Size ' . ($n + 1);
            if ($name === '' || mb_strlen($name) > 60) $this->bounce("$prefix: give the size a name (Large, Small …).", $back);
            if (isset($sizes[mb_strtolower($name)])) $this->bounce("$prefix: \"$name\" is listed twice.", $back);
            if ($raw !== '' && (!ctype_digit($raw) || (int)$raw > 10000000)) $this->bounce("$prefix: enter the number of books as a whole number.", $back);
            $sizes[mb_strtolower($name)] = ['size_name' => $name, 'qty' => $raw === '' ? 0 : (int)$raw];
        }
        return ['head' => ['catalogue_id' => (int)$cat['id'], 'warehouse_id' => (int)$wh['id'], 'ship_to' => $shipTo !== '' ? $shipTo : null, 'note' => $note !== '' ? $note : null], 'fabric' => array_values($fabric), 'sizes' => array_values($sizes)];
    }

    public function store(): void
    {
        $back = 'launches/create';
        $in = $this->collect($back);
        $id = DB::transaction(function () use ($in) {
            $t = $this->tid();
            $id = DB::insert('catalogue_launches', ['tenant_id' => $t, 'launch_no' => Numbering::next($t, 'CL'), 'status' => 'draft', 'created_by' => Auth::user()['id']] + $in['head']);
            foreach ($in['fabric'] as $f) DB::insert('launch_fabric', ['tenant_id' => $t, 'launch_id' => $id, 'variant_id' => $f['variant_id'], 'qty_ordered' => $f['qty'], 'note' => $f['note']]);
            foreach ($in['sizes'] as $s) DB::insert('launch_sizes', ['tenant_id' => $t, 'launch_id' => $id, 'size_name' => $s['size_name'], 'qty_planned' => $s['qty']]);
            return $id;
        });
        Audit::log('launch_create', 'catalogue_launch', $id);
        flash('success', 'Launch saved as a draft. When the fabric list is ready, send the order to the warehouse.');
        redirect("launches/$id");
    }

    public function update(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}/edit";
        if (!in_array($l['status'], ['draft', ...self::OPEN], true)) $this->bounce('This launch can no longer be changed.', "launches/{$l['id']}");
        $in = $this->collect($back);
        $t = $this->tid();
        // dispatch has started: a warehouse cannot be changed, and a fabric line that has been dispatched stays (with at least what went out)
        if ($l['status'] !== 'draft' && $in['head']['warehouse_id'] !== (int)$l['warehouse_id'] && (float)DB::val('SELECT COALESCE(SUM(qty_issued),0) FROM launch_fabric WHERE launch_id = ?', [$l['id']]) > 0) {
            $this->bounce('Fabric has already been dispatched from ' . $l['warehouse'] . ', so the warehouse cannot be changed.', $back);
        }
        $have = [];
        foreach ($this->fabric((int)$l['id']) as $f) $have[(int)$f['variant_id']] = $f;
        $posted = [];
        foreach ($in['fabric'] as $f) {
            $posted[$f['variant_id']] = $f;
            $net = isset($have[$f['variant_id']]) ? (float)$have[$f['variant_id']]['qty_issued'] - (float)$have[$f['variant_id']]['qty_returned'] : 0.0;
            if ($f['qty'] + 0.0005 < $net) $this->bounce('You have already dispatched ' . Stock::fmt($net) . ' of ' . $have[$f['variant_id']]['item_name'] . '; the order cannot be less than that.', $back);
        }
        foreach ($have as $vid => $f) if (!isset($posted[$vid]) && (float)$f['qty_issued'] > 0) $this->bounce($f['item_name'] . ' has been dispatched, so it cannot be taken off the order.', $back);
        $sizesHave = [];
        foreach ($this->sizes((int)$l['id']) as $s) $sizesHave[mb_strtolower($s['size_name'])] = $s;
        $sizesPosted = [];
        foreach ($in['sizes'] as $s) $sizesPosted[mb_strtolower($s['size_name'])] = $s;
        foreach ($sizesHave as $k => $s) if (!isset($sizesPosted[$k]) && ((int)$s['qty_received'] > 0 || $s['variant_id'])) $this->bounce('Size ' . $s['size_name'] . ' already has books received, so it cannot be removed.', $back);

        DB::transaction(function () use ($l, $in, $have, $posted, $sizesHave, $sizesPosted, $t) {
            DB::run('UPDATE catalogue_launches SET catalogue_id = ?, warehouse_id = ?, ship_to = ?, note = ? WHERE tenant_id = ? AND id = ?', [$in['head']['catalogue_id'], $in['head']['warehouse_id'], $in['head']['ship_to'], $in['head']['note'], $t, $l['id']]);
            foreach ($posted as $vid => $f) {
                if (isset($have[$vid])) DB::run('UPDATE launch_fabric SET qty_ordered = ?, note = ? WHERE tenant_id = ? AND id = ?', [$f['qty'], $f['note'], $t, $have[$vid]['id']]);
                else DB::insert('launch_fabric', ['tenant_id' => $t, 'launch_id' => $l['id'], 'variant_id' => $vid, 'qty_ordered' => $f['qty'], 'note' => $f['note']]);
            }
            foreach ($have as $vid => $f) if (!isset($posted[$vid])) DB::run('DELETE FROM launch_fabric WHERE tenant_id = ? AND id = ?', [$t, $f['id']]);
            foreach ($sizesPosted as $k => $s) {
                if (isset($sizesHave[$k])) DB::run('UPDATE launch_sizes SET size_name = ?, qty_planned = ? WHERE tenant_id = ? AND id = ?', [$s['size_name'], $s['qty'], $t, $sizesHave[$k]['id']]);
                else DB::insert('launch_sizes', ['tenant_id' => $t, 'launch_id' => $l['id'], 'size_name' => $s['size_name'], 'qty_planned' => $s['qty']]);
            }
            foreach ($sizesHave as $k => $s) if (!isset($sizesPosted[$k])) DB::run('DELETE FROM launch_sizes WHERE tenant_id = ? AND id = ?', [$t, $s['id']]);
        });
        $this->refreshStatus((int)$l['id']);
        Audit::log('launch_update', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch updated.');
        redirect("launches/{$l['id']}");
    }

    /* ---------- show ---------- */

    public function show(string $id): void
    {
        $l = $this->load($id);
        $fabric = $this->fabric((int)$l['id']);
        $docs = DB::all(
            "SELECT d.id, d.type, d.doc_no, d.note, d.created_at, d.warehouse_id, w.name AS warehouse, u.name AS user_name
             FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN users u ON u.id = d.created_by
             WHERE d.tenant_id = ? AND d.launch_id = ? ORDER BY d.id", [$this->tid(), $l['id']]);
        foreach ($docs as &$d) {
            $d['lines'] = DB::all(
                'SELECT x.qty, x.unit_cost, i.name AS item_name, v.sku, b.batch_no, r.code AS rack
                 FROM stock_doc_lines x JOIN item_variants v ON v.id = x.variant_id JOIN items i ON i.id = v.item_id
                 LEFT JOIN batches b ON b.id = x.batch_id AND x.batch_id > 0 LEFT JOIN locations r ON r.id = x.location_id AND x.location_id > 0
                 WHERE x.tenant_id = ? AND x.doc_id = ? ORDER BY x.id', [$this->tid(), $d['id']]);
        }
        unset($d);
        $mine = Scope::allows((int)$l['warehouse_id']);
        $this->view('app/launches/show', ['title' => $l['launch_no'], 'l' => $l, 'fabric' => $fabric, 'sizes' => $this->sizes((int)$l['id']), 'docs' => $docs, 'money' => $this->money($l, $fabric),
            'statuses' => self::STATUS, 'can' => [
                'order' => $l['status'] === 'draft' && $fabric ? true : false,
                'edit' => in_array($l['status'], ['draft', ...self::OPEN], true),
                'cancel' => in_array($l['status'], ['draft', 'ordered'], true) && !array_sum(array_map(fn($f) => (float)$f['qty_issued'], $fabric)),
                'dispatch' => in_array($l['status'], ['ordered', 'partial'], true) && $mine,
                'return' => in_array($l['status'], self::OPEN, true) && $mine && array_sum(array_map(fn($f) => (float)$f['qty_issued'] - (float)$f['qty_returned'], $fabric)) > 0.0005,
                'receive' => in_array($l['status'], ['ordered', 'partial', 'dispatched', 'received'], true),
                'close' => in_array($l['status'], ['partial', 'dispatched', 'received'], true),
            ]]);
    }

    /* ---------- order / cancel / close ---------- */

    public function order(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}";
        if ($l['status'] !== 'draft') $this->bounce('This order has already been sent.', $back);
        if (!$this->fabric((int)$l['id'])) $this->bounce('Add the fabric you need before sending the order.', $back);
        DB::run("UPDATE catalogue_launches SET status = 'ordered', ordered_at = NOW() WHERE tenant_id = ? AND id = ?", [$this->tid(), $l['id']]);
        Audit::log('launch_order', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Order sent to ' . $l['warehouse'] . '. They can now dispatch the fabric.');
        redirect($back);
    }

    public function cancel(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}";
        if (!in_array($l['status'], ['draft', 'ordered'], true)) $this->bounce('Only a launch that has not started dispatching can be cancelled.', $back);
        if ((float)DB::val('SELECT COALESCE(SUM(qty_issued),0) FROM launch_fabric WHERE launch_id = ?', [$l['id']]) > 0) $this->bounce('Fabric has already been dispatched for this launch.', $back);
        DB::run("UPDATE catalogue_launches SET status = 'cancelled' WHERE tenant_id = ? AND id = ?", [$this->tid(), $l['id']]);
        Audit::log('launch_cancel', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch cancelled.');
        redirect($back);
    }

    public function close(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}";
        if (!in_array($l['status'], ['partial', 'dispatched', 'received'], true)) $this->bounce('This launch cannot be closed yet.', $back);
        DB::run("UPDATE catalogue_launches SET status = 'closed' WHERE tenant_id = ? AND id = ?", [$this->tid(), $l['id']]);
        Audit::log('launch_close', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch closed.');
        redirect($back);
    }

    /* ---------- dispatch the fabric ---------- */

    private function ownWarehouse(array $l, string $back): void
    {
        if (!Scope::allows((int)$l['warehouse_id'])) $this->bounce('You work at ' . Scope::name() . ', so you can only do this for ' . Scope::name() . '.', $back);
    }

    public function dispatchForm(string $id): void
    {
        $l = $this->load($id);
        if (!in_array($l['status'], ['ordered', 'partial'], true)) {
            flash('danger', 'There is nothing to dispatch for this launch right now.');
            redirect("launches/{$l['id']}");
        }
        $this->ownWarehouse($l, "launches/{$l['id']}");
        $this->view('app/launches/dispatch', ['title' => 'Dispatch fabric · ' . $l['launch_no'], 'l' => $l, 'fabric' => $this->fabric((int)$l['id']), 'racks' => $this->racksByWarehouse()]);
    }

    /** Posted roll lines (variant, roll, rack, qty), checked against the launch's fabric lines. */
    private function rollLines(array $fabric, string $back, bool $needBatch = true): array
    {
        $byV = [];
        foreach ($fabric as $f) $byV[(int)$f['variant_id']] = $f;
        $lines = [];
        foreach ((array)($this->input()['lines'] ?? []) as $n => $x) {
            if (!is_array($x)) continue;
            $raw = trim((string)($x['qty'] ?? ''));
            if ($raw === '' || (is_numeric($raw) && (float)$raw == 0.0)) continue;
            $prefix = 'Line ' . ($n + 1);
            $f = $byV[(int)($x['variant_id'] ?? 0)] ?? $this->bounce("$prefix: that fabric is not on this launch.", $back);
            if (!is_numeric($raw) || (float)$raw < 0) $this->bounce("$prefix: enter a quantity above zero.", $back);
            $qty = Stock::round((float)$raw);
            if (!$f['allow_decimal'] && floor($qty) != $qty) $this->bounce("$prefix: " . $f['item_name'] . ' is counted in whole ' . $f['unit'] . '.', $back);
            $batch = (int)($x['batch_id'] ?? 0);
            if ($needBatch && $f['track_batch'] && $batch <= 0) $this->bounce("$prefix: choose the roll of " . $f['item_name'] . '.', $back);
            $lines[] = ['n' => $n + 1, 'f' => $f, 'qty' => $qty, 'batch' => $f['track_batch'] ? $batch : 0, 'rack' => (int)($x['location_id'] ?? 0)];
        }
        if (!$lines) $this->bounce('Enter the quantity on at least one line.', $back);
        return $lines;
    }

    public function dispatch(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}/dispatch";
        if (!in_array($l['status'], ['ordered', 'partial'], true)) $this->bounce('There is nothing to dispatch for this launch right now.', "launches/{$l['id']}");
        $this->ownWarehouse($l, "launches/{$l['id']}");
        $fabric = $this->fabric((int)$l['id']);
        $lines = $this->rollLines($fabric, $back);
        $per = [];
        foreach ($lines as $x) $per[(int)$x['f']['variant_id']] = ($per[(int)$x['f']['variant_id']] ?? 0) + $x['qty'];
        foreach ($per as $vid => $got) {
            foreach ($fabric as $f) {
                if ((int)$f['variant_id'] !== $vid) continue;
                $left = (float)$f['qty_ordered'] - ((float)$f['qty_issued'] - (float)$f['qty_returned']);
                if ($got > $left * (1 + self::TOLERANCE) + 0.0005) $this->bounce($f['item_name'] . ': you are dispatching ' . Stock::fmt($got) . ' but only ' . Stock::fmt(max(0, $left)) . ' is still due (up to 10% extra is accepted).', $back);
            }
        }
        try {
            $docId = DB::transaction(function () use ($l, $lines) {
                $t = $this->tid();
                $no = Numbering::next($t, 'LIS');
                $docId = DB::insert('stock_docs', ['tenant_id' => $t, 'type' => 'launch_issue', 'doc_no' => $no, 'warehouse_id' => $l['warehouse_id'], 'launch_id' => $l['id'], 'status' => 'posted',
                    'note' => $l['ship_to'] ? 'To: ' . $l['ship_to'] : null, 'created_by' => Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s')]);
                foreach ($lines as $x) {
                    $vid = (int)$x['f']['variant_id'];
                    $cost = Sales::unitCost($vid, $x['batch']);
                    try {
                        Stock::move($vid, (int)$l['warehouse_id'], $x['batch'], -$x['qty'], 'launch_issue', 'stock_doc', $docId, $cost, $no . ' · fabric for ' . $l['launch_no'] . ($l['ship_to'] ? ' · to ' . $l['ship_to'] : ''), $x['rack']);
                    } catch (StockException $e) {
                        throw new StockException('Line ' . $x['n'] . ': ' . $e->getMessage());
                    }
                    DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $vid, 'batch_id' => $x['batch'], 'location_id' => $x['rack'], 'qty' => $x['qty'], 'unit_cost' => $cost]);
                    DB::run('UPDATE launch_fabric SET qty_issued = qty_issued + ?, value_issued = value_issued + ? WHERE tenant_id = ? AND id = ?', [$x['qty'], round($x['qty'] * $cost, 2), $t, $x['f']['id']]);
                }
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        $this->refreshStatus((int)$l['id']);
        Audit::log('launch_dispatch', 'stock_doc', $docId);
        flash('success', 'Fabric dispatched and taken out of stock.');
        redirect("launches/{$l['id']}");
    }

    /* ---------- wrong fabric comes back ---------- */

    /** Rolls that went out on this launch and what of them is still out: variant, roll, issued, returned. */
    private function rollsOut(int $launchId): array
    {
        return DB::all(
            "SELECT x.variant_id, x.batch_id, b.batch_no,
                    SUM(CASE WHEN d.type = 'launch_issue' THEN x.qty ELSE 0 END) AS issued, SUM(CASE WHEN d.type = 'launch_return' THEN x.qty ELSE 0 END) AS returned
             FROM stock_doc_lines x JOIN stock_docs d ON d.id = x.doc_id AND d.tenant_id = x.tenant_id AND d.launch_id = ? AND d.type IN ('launch_issue','launch_return')
             LEFT JOIN batches b ON b.id = x.batch_id AND x.batch_id > 0 WHERE x.tenant_id = ? GROUP BY x.variant_id, x.batch_id, b.batch_no
             HAVING issued - returned > 0.0005 ORDER BY x.variant_id, x.batch_id", [$launchId, $this->tid()]);
    }

    public function returnForm(string $id): void
    {
        $l = $this->load($id);
        $this->ownWarehouse($l, "launches/{$l['id']}");
        if (!in_array($l['status'], self::OPEN, true)) {
            flash('danger', 'No fabric can be returned for this launch now.');
            redirect("launches/{$l['id']}");
        }
        $this->view('app/launches/return', ['title' => 'Return fabric · ' . $l['launch_no'], 'l' => $l, 'fabric' => $this->fabric((int)$l['id']), 'rolls' => $this->rollsOut((int)$l['id']), 'racks' => $this->racksByWarehouse()]);
    }

    public function giveBack(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}/return";
        $this->ownWarehouse($l, "launches/{$l['id']}");
        if (!in_array($l['status'], self::OPEN, true)) $this->bounce('No fabric can be returned for this launch now.', "launches/{$l['id']}");
        $fabric = $this->fabric((int)$l['id']);
        $lines = $this->rollLines($fabric, $back);
        $out = [];
        foreach ($this->rollsOut((int)$l['id']) as $r) $out[(int)$r['variant_id'] . ':' . (int)$r['batch_id']] = (float)$r['issued'] - (float)$r['returned'];
        $per = [];
        foreach ($lines as $x) {
            $key = (int)$x['f']['variant_id'] . ':' . $x['batch'];
            $per[$key] = ($per[$key] ?? 0) + $x['qty'];
            if ($per[$key] > ($out[$key] ?? 0) + 0.0005) $this->bounce('Line ' . $x['n'] . ': only ' . Stock::fmt($out[$key] ?? 0) . ' ' . $x['f']['unit'] . ' of that ' . ($x['f']['track_batch'] ? 'roll' : 'fabric') . ' is out on this launch.', $back);
        }
        try {
            $docId = DB::transaction(function () use ($l, $lines) {
                $t = $this->tid();
                $no = Numbering::next($t, 'LRT');
                $docId = DB::insert('stock_docs', ['tenant_id' => $t, 'type' => 'launch_return', 'doc_no' => $no, 'warehouse_id' => $l['warehouse_id'], 'launch_id' => $l['id'], 'status' => 'posted',
                    'reason' => 'wrong_fabric', 'note' => trim((string)($this->input()['note'] ?? '')) ?: 'Wrong fabric sent back', 'created_by' => Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s')]);
                foreach ($lines as $x) {
                    $vid = (int)$x['f']['variant_id'];
                    $row = DB::one('SELECT qty_issued, qty_returned, value_issued FROM launch_fabric WHERE tenant_id = ? AND id = ? FOR UPDATE', [$t, $x['f']['id']]);
                    // it goes back at the cost it went out at, and that value leaves the launch
                    $net = (float)$row['qty_issued'] - (float)$row['qty_returned'];
                    $cost = $net > 0 ? round((float)$row['value_issued'] / $net, 2) : Sales::unitCost($vid, $x['batch']);
                    Stock::move($vid, (int)$l['warehouse_id'], $x['batch'], $x['qty'], 'launch_return', 'stock_doc', $docId, $cost, $no . ' · wrong fabric back from ' . $l['launch_no'], $x['rack']);
                    DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $vid, 'batch_id' => $x['batch'], 'location_id' => $x['rack'], 'qty' => $x['qty'], 'unit_cost' => $cost]);
                    DB::run('UPDATE launch_fabric SET qty_returned = qty_returned + ?, value_issued = GREATEST(0, value_issued - ?) WHERE tenant_id = ? AND id = ?', [$x['qty'], round($x['qty'] * $cost, 2), $t, $x['f']['id']]);
                }
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        $this->refreshStatus((int)$l['id']);
        Audit::log('launch_return', 'stock_doc', $docId);
        flash('success', 'Fabric taken back into stock. The order is open again for the right fabric.');
        redirect("launches/{$l['id']}");
    }

    /* ---------- the printed books arrive ---------- */

    public function receiveForm(string $id): void
    {
        $l = $this->load($id);
        if (!in_array($l['status'], ['ordered', 'partial', 'dispatched', 'received'], true)) {
            flash('danger', 'Books cannot be received for this launch now.');
            redirect("launches/{$l['id']}");
        }
        $fabric = $this->fabric((int)$l['id']);
        $this->view('app/launches/receive', ['title' => 'Receive catalogues · ' . $l['launch_no'], 'l' => $l, 'sizes' => $this->sizes((int)$l['id']), 'money' => $this->money($l, $fabric),
            'warehouses' => $this->activeWarehouses(), 'racks' => $this->racksByWarehouse()]);
    }

    /** The item a size of this catalogue is stocked as: made the first time books of that size are received (an earlier launch's item is reused). */
    private function sizeItem(array $l, array $size): int
    {
        if ($size['variant_id']) return (int)$size['variant_id'];
        $t = $this->tid();
        $name = $l['catalogue'] . ' (catalogue) — ' . $size['size_name'];
        $v = DB::val("SELECT v.id FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND i.item_type = 'catalogue' AND i.name = ? AND v.is_active = 1 LIMIT 1", [$t, $name]);
        if (!$v) {
            $unit = (int)(DB::val("SELECT id FROM units WHERE tenant_id = ? AND allow_decimal = 0 ORDER BY (short_name = 'pcs') DESC, id LIMIT 1", [$t]) ?: DB::val('SELECT id FROM units WHERE tenant_id = ? ORDER BY id LIMIT 1', [$t]));
            $itemId = DB::insert('items', ['tenant_id' => $t, 'name' => mb_substr($name, 0, 190), 'item_type' => 'catalogue', 'unit_id' => $unit, 'description' => 'Printed catalogue, size ' . $size['size_name'] . '. Given free to showrooms and dealers.']);
            $v = DB::insert('item_variants', ['tenant_id' => $t, 'item_id' => $itemId, 'sku' => Numbering::next($t, 'SKU'), 'size' => $size['size_name'], 'cost_price' => 0, 'sale_price' => 0, 'is_active' => 1]);
        }
        DB::run('UPDATE launch_sizes SET variant_id = ? WHERE tenant_id = ? AND id = ?', [(int)$v, $t, $size['id']]);
        return (int)$v;
    }

    public function receive(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}/receive";
        if (!in_array($l['status'], ['ordered', 'partial', 'dispatched', 'received'], true)) $this->bounce('Books cannot be received for this launch now.', "launches/{$l['id']}");
        $d = $this->input();
        $wh = $this->activeWarehouse($d['warehouse_id'] ?? 0, $back, 'warehouse');
        $extraRaw = trim((string)($d['extra_cost'] ?? ''));
        if ($extraRaw !== '' && (!is_numeric($extraRaw) || (float)$extraRaw < 0 || (float)$extraRaw > 99999999)) $this->bounce('Extra cost must be a number of zero or more.', $back);
        $extra = $extraRaw === '' ? 0.0 : round((float)$extraRaw, 2);
        $sizes = [];
        foreach ($this->sizes((int)$l['id']) as $s) $sizes[(int)$s['id']] = $s;
        $lines = []; $copies = 0;
        foreach ((array)($d['lines'] ?? []) as $n => $x) {
            if (!is_array($x)) continue;
            $raw = trim((string)($x['qty'] ?? ''));
            if ($raw === '' || $raw === '0') continue;
            $s = $sizes[(int)($x['size_id'] ?? 0)] ?? $this->bounce('A size on the form does not belong to this launch.', $back);
            if (!ctype_digit($raw) || (int)$raw > 10000000) $this->bounce('Size ' . $s['size_name'] . ': enter the number of books received as a whole number.', $back);
            $rack = (int)($x['location_id'] ?? 0);
            $lines[] = ['s' => $s, 'qty' => (int)$raw, 'rack' => $rack];
            $copies += (int)$raw;
        }
        if (!$lines) $this->bounce('Enter how many books of each size were received.', $back);
        try {
            $docId = DB::transaction(function () use ($l, $wh, $lines, $copies, $extra, $d) {
                $t = $this->tid();
                $fabric = $this->fabric((int)$l['id']);
                $value = round(array_sum(array_map(fn($f) => (float)$f['value_issued'], $fabric)), 2);
                $pool = round($value - (float)$l['fabric_absorbed'] + $extra, 2);
                $unit = round($pool / $copies, 2);
                $no = Numbering::next($t, 'LRC');
                $docId = DB::insert('stock_docs', ['tenant_id' => $t, 'type' => 'launch_receipt', 'doc_no' => $no, 'warehouse_id' => $wh['id'], 'launch_id' => $l['id'], 'status' => 'posted',
                    'note' => trim((string)($d['note'] ?? '')) ?: null, 'created_by' => Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s')]);
                foreach ($lines as $x) {
                    $vid = $this->sizeItem($l, $x['s']);
                    Stock::move($vid, (int)$wh['id'], 0, (float)$x['qty'], 'launch_receipt', 'stock_doc', $docId, $unit, $no . ' · ' . $l['catalogue'] . ' ' . $x['s']['size_name'] . ' from ' . $l['launch_no'], $x['rack']);
                    DB::insert('stock_doc_lines', ['tenant_id' => $t, 'doc_id' => $docId, 'variant_id' => $vid, 'batch_id' => 0, 'location_id' => $x['rack'], 'qty' => $x['qty'], 'unit_cost' => $unit]);
                    DB::run('UPDATE launch_sizes SET qty_received = qty_received + ? WHERE tenant_id = ? AND id = ?', [$x['qty'], $t, $x['s']['id']]);
                    DB::run('UPDATE item_variants SET cost_price = ? WHERE tenant_id = ? AND id = ?', [$unit, $t, $vid]);
                }
                DB::run("UPDATE catalogue_launches SET status = 'received', extra_cost = extra_cost + ?, fabric_absorbed = ? WHERE tenant_id = ? AND id = ?", [$extra, $value, $t, $l['id']]);
                return $docId;
            });
        } catch (StockException $e) {
            $this->bounce($e->getMessage(), $back);
        }
        Audit::log('launch_receive', 'stock_doc', $docId);
        flash('success', 'Catalogues received into stock.');
        redirect("launches/{$l['id']}");
    }
}
