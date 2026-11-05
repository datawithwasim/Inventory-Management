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
 * Catalogue launches. The catalogue team asks a warehouse for fabric the way every order reaches a warehouse: with a SALES ORDER (addressed to the
 * catalogue department, the place to send the fabric as its delivery address). The warehouse delivers it from its sales orders, roll by roll; wrong
 * fabric comes back as an ordinary sales return. The launch follows those orders: what was asked, what was delivered and what it cost. When the
 * printed books arrive, in sizes, they are received into stock as items of their own, costed from the fabric delivered.
 */
final class LaunchController extends StockDocController
{
    private const OPEN = ['ordered', 'partial', 'dispatched'];

    public const STATUS = ['draft' => 'Draft', 'ordered' => 'Ordered', 'partial' => 'Fabric partly delivered', 'dispatched' => 'Fabric delivered', 'received' => 'Books received', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

    /** The catalogue team (products.view), and the warehouse people who deliver the fabric order (sales.view) or receive the books (stock.receive), look at launches. */
    private function mayView(): void
    {
        if (!Auth::can('products.view') && !Auth::can('sales.view') && !Auth::can('stock.receive')) $this->forbidden();
    }

    private function load(string $id): array
    {
        $this->mayView();
        return DB::one(
            'SELECT l.*, c.name AS catalogue, c.code AS catalogue_code, w.name AS warehouse, u.name AS user_name
             FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id JOIN warehouses w ON w.id = l.warehouse_id LEFT JOIN users u ON u.id = l.created_by
             WHERE l.id = ?' . Scope::sql('l.warehouse_id'), [(int)$id]) ?? $this->notFound();
    }

    /** The sales orders asked for this launch (cancelled ones too, so nothing seems to vanish). */
    private function orders(int $launchId): array
    {
        return DB::all(
            "SELECT o.id, o.order_no, o.status, o.order_date, o.ship_to, o.warehouse_id, w.name AS warehouse, c.name AS customer,
                    (SELECT COUNT(*) FROM deliveries d WHERE d.order_id = o.id) AS deliveries
             FROM sales_orders o JOIN customers c ON c.id = o.customer_id JOIN warehouses w ON w.id = o.warehouse_id
             WHERE o.launch_id = ? ORDER BY o.id", [$launchId]);
    }

    /** The fabric of this launch, from its sales orders: asked, delivered, sent back, and what the delivered fabric cost. */
    private function fabric(int $launchId): array
    {
        $rows = DB::all(
            "SELECT v.id AS variant_id, v.sku, i.name AS item_name, u.short_name AS unit,
                    (SELECT COALESCE(SUM(CASE WHEN o.status = 'closed' THEN oi.qty_delivered ELSE oi.qty_ordered END), 0) FROM sales_order_items oi JOIN sales_orders o ON o.id = oi.order_id
                      WHERE o.launch_id = ? AND o.status <> 'cancelled' AND oi.variant_id = v.id) AS qty_ordered,
                    (SELECT COALESCE(SUM(di.qty), 0) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id JOIN sales_orders o ON o.id = d.order_id
                      WHERE o.launch_id = ? AND di.parent_id IS NULL AND di.variant_id = v.id) AS qty_issued,
                    (SELECT COALESCE(SUM(di.qty_returned), 0) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id JOIN sales_orders o ON o.id = d.order_id
                      WHERE o.launch_id = ? AND di.parent_id IS NULL AND di.variant_id = v.id) AS qty_returned,
                    (SELECT COALESCE(SUM((di.qty - di.qty_returned) * di.unit_cost), 0) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id JOIN sales_orders o ON o.id = d.order_id
                      WHERE o.launch_id = ? AND di.parent_id IS NULL AND di.variant_id = v.id) AS value_issued
             FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE v.id IN (SELECT oi.variant_id FROM sales_order_items oi JOIN sales_orders o ON o.id = oi.order_id WHERE o.launch_id = ?)
             ORDER BY i.name",
            [$launchId, $launchId, $launchId, $launchId, $launchId]);
        foreach ($rows as &$r) $r['value_issued'] = round((float)$r['value_issued'], 2);
        return $rows;
    }

    private function sizes(int $launchId): array
    {
        return DB::all('SELECT s.*, v.item_id FROM launch_sizes s LEFT JOIN item_variants v ON v.id = s.variant_id WHERE s.launch_id = ? ORDER BY s.id', [$launchId]);
    }

    /** What the launch has cost so far and what each received copy cost. */
    private function money(array $l, array $fabric): array
    {
        $value = round(array_sum(array_map(fn($f) => (float)$f['value_issued'], $fabric)), 2);
        $copies = (int)DB::val('SELECT COALESCE(SUM(qty_received),0) FROM launch_sizes WHERE launch_id = ?', [$l['id']]);
        $total = round($value + (float)$l['extra_cost'], 2);
        return ['fabric_value' => $value, 'extra_cost' => (float)$l['extra_cost'], 'total_cost' => $total, 'copies' => $copies, 'cost_per_copy' => $copies > 0 ? round($total / $copies, 2) : null,
            'unabsorbed' => round($value - (float)$l['fabric_absorbed'], 2)];
    }

    /**
     * Where the fabric stands, from the launch's sales orders: none sent yet (draft), asked (ordered), partly delivered, all delivered (what was delivered, even if some came back: wrong fabric is replaced by a new order).
     * Called whenever one of those orders changes or is delivered against, or fabric comes back. Only for a launch that is still on the fabric.
     */
    public static function refresh(int $launchId): void
    {
        $l = DB::one('SELECT status FROM catalogue_launches WHERE id = ?', [$launchId]);
        if (!$l || !in_array($l['status'], ['draft', ...self::OPEN], true)) return;
        $sent = (int)DB::val("SELECT COUNT(*) FROM sales_orders WHERE launch_id = ? AND status NOT IN ('cancelled','draft')", [$launchId]);
        if ($sent === 0) { DB::run("UPDATE catalogue_launches SET status = 'draft' WHERE id = ?", [$launchId]); return; }
        $rows = DB::all(
            "SELECT oi.variant_id, SUM(CASE WHEN o.status = 'closed' THEN oi.qty_delivered ELSE oi.qty_ordered END) AS asked,
                    (SELECT COALESCE(SUM(di.qty), 0) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id JOIN sales_orders o2 ON o2.id = d.order_id
                      WHERE o2.launch_id = o.launch_id AND di.parent_id IS NULL AND di.variant_id = oi.variant_id) AS got
             FROM sales_order_items oi JOIN sales_orders o ON o.id = oi.order_id
             WHERE o.launch_id = ? AND o.status NOT IN ('cancelled','draft') GROUP BY oi.variant_id, o.launch_id", [$launchId]);
        $all = (bool)$rows; $any = false;
        foreach ($rows as $r) { if ((float)$r['got'] > 0.0005) $any = true; if ((float)$r['got'] + 0.0005 < (float)$r['asked']) $all = false; }
        DB::run('UPDATE catalogue_launches SET status = ? WHERE id = ?', [$all ? 'dispatched' : ($any ? 'partial' : 'ordered'), $launchId]);
    }

    /** The same, starting from a sales order (does nothing for an ordinary one). */
    public static function refreshForOrder(int $orderId): void
    {
        $id = (int)DB::val('SELECT launch_id FROM sales_orders WHERE id = ?', [$orderId]);
        if ($id > 0) self::refresh($id);
    }

    /** The customer that fabric orders are addressed to: the catalogue department. Made the first time it is needed. */
    public static function internalCustomer(): int
    {
        $id = (int)DB::val("SELECT id FROM customers WHERE name = 'Catalogue Department' LIMIT 1");
        return $id ?: DB::insert('customers', ['name' => 'Catalogue Department', 'notes' => 'Internal: fabric the catalogue team orders from the warehouse for catalogue launches.', 'is_active' => 1]);
    }

    /* ---------- list ---------- */

    public function index(): void
    {
        $this->mayView();
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? '');
        $where = '1=1' . Scope::sql('l.warehouse_id'); $p = [];
        if ($q !== '') { $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; $where .= ' AND (l.launch_no LIKE ? OR c.name LIKE ? OR c.code LIKE ?)'; array_push($p, $like, $like, $like); }
        if (isset(self::STATUS[$status])) { $where .= ' AND l.status = ?'; $p[] = $status; }
        elseif ($status === 'todo') $where .= " AND l.status IN ('ordered','partial')";
        elseif ($status === 'open') $where .= " AND l.status IN ('draft','ordered','partial','dispatched','received')";
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = (int)DB::val("SELECT COUNT(*) FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id WHERE $where", $p);
        $rows = DB::all(
            "SELECT l.*, c.name AS catalogue, c.code AS catalogue_code, w.name AS warehouse,
                    (SELECT COALESCE(SUM(CASE WHEN o.status = 'closed' THEN oi.qty_delivered ELSE oi.qty_ordered END),0) FROM sales_order_items oi JOIN sales_orders o ON o.id = oi.order_id WHERE o.launch_id = l.id AND o.status <> 'cancelled') AS fabric_ordered,
                    (SELECT COALESCE(SUM(di.qty - di.qty_returned),0) FROM delivery_items di JOIN deliveries d ON d.id = di.delivery_id JOIN sales_orders o ON o.id = d.order_id WHERE o.launch_id = l.id AND di.parent_id IS NULL) AS fabric_issued,
                    (SELECT COALESCE(SUM(s.qty_planned),0) FROM launch_sizes s WHERE s.launch_id = l.id) AS copies_planned,
                    (SELECT COALESCE(SUM(s.qty_received),0) FROM launch_sizes s WHERE s.launch_id = l.id) AS copies_received
             FROM catalogue_launches l JOIN catalogues c ON c.id = l.catalogue_id JOIN warehouses w ON w.id = l.warehouse_id
             WHERE $where ORDER BY l.id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $p);
        $this->view('app/launches/index', ['title' => 'Catalogue launches', 'rows' => $rows, 'q' => $q, 'status' => $status, 'statuses' => ['todo' => 'Waiting for dispatch'] + self::STATUS, 'page' => $page, 'pages' => max(1, (int)ceil($total / 30)), 'total' => $total]);
    }

    /* ---------- create / edit ---------- */

    private function formData(?array $l, array $extra = []): array
    {
        return $extra + [
            'catalogues' => DB::all('SELECT id, name, code, named FROM catalogues WHERE is_active = 1 ORDER BY name'),
            'warehouses' => $this->allActiveWarehouses(), 'launch' => $l, 'sizes' => $l ? $this->sizes((int)$l['id']) : [],
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

    /** The posted header and sizes, checked. */
    private function collect(string $back): array
    {
        $d = $this->input();
        $cat = DB::one('SELECT id FROM catalogues WHERE id = ?', [(int)($d['catalogue_id'] ?? 0)]);
        if (!$cat) $this->bounce('Choose the catalogue this launch is for.', $back);
        $wh = Stock::warehouse((int)($d['warehouse_id'] ?? 0));
        if (!$wh || !$wh['is_active']) $this->bounce('Choose the warehouse that gives the fabric.', $back);
        $shipTo = trim((string)($d['ship_to'] ?? ''));
        $note = trim((string)($d['note'] ?? ''));
        if (mb_strlen($shipTo) > 255 || mb_strlen($note) > 255) $this->bounce('The address or note is too long (max 255 characters).', $back);
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
        return ['head' => ['catalogue_id' => (int)$cat['id'], 'warehouse_id' => (int)$wh['id'], 'ship_to' => $shipTo !== '' ? $shipTo : null, 'note' => $note !== '' ? $note : null], 'sizes' => array_values($sizes)];
    }

    public function store(): void
    {
        $in = $this->collect('launches/create');
        $id = DB::transaction(function () use ($in) {
            $id = DB::insert('catalogue_launches', ['launch_no' => Numbering::next('CL'), 'status' => 'draft', 'created_by' => Auth::user()['id']] + $in['head']);
            foreach ($in['sizes'] as $s) DB::insert('launch_sizes', ['launch_id' => $id, 'size_name' => $s['size_name'], 'qty_planned' => $s['qty']]);
            return $id;
        });
        Audit::log('launch_create', 'catalogue_launch', $id);
        flash('success', 'Launch saved. Now ask the warehouse for the fabric: create the fabric order (a sales order) from this page.');
        redirect("launches/$id");
    }

    public function update(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}/edit";
        if (!in_array($l['status'], ['draft', ...self::OPEN], true)) $this->bounce('This launch can no longer be changed.', "launches/{$l['id']}");
        $in = $this->collect($back);
        $sizesHave = [];
        foreach ($this->sizes((int)$l['id']) as $s) $sizesHave[mb_strtolower($s['size_name'])] = $s;
        $sizesPosted = [];
        foreach ($in['sizes'] as $s) $sizesPosted[mb_strtolower($s['size_name'])] = $s;
        foreach ($sizesHave as $k => $s) if (!isset($sizesPosted[$k]) && ((int)$s['qty_received'] > 0 || $s['variant_id'])) $this->bounce('Size ' . $s['size_name'] . ' already has books received, so it cannot be removed.', $back);
        DB::transaction(function () use ($l, $in, $sizesHave, $sizesPosted) {
            DB::run('UPDATE catalogue_launches SET catalogue_id = ?, warehouse_id = ?, ship_to = ?, note = ? WHERE id = ?', [$in['head']['catalogue_id'], $in['head']['warehouse_id'], $in['head']['ship_to'], $in['head']['note'], $l['id']]);
            foreach ($sizesPosted as $k => $s) {
                if (isset($sizesHave[$k])) DB::run('UPDATE launch_sizes SET size_name = ?, qty_planned = ? WHERE id = ?', [$s['size_name'], $s['qty'], $sizesHave[$k]['id']]);
                else DB::insert('launch_sizes', ['launch_id' => $l['id'], 'size_name' => $s['size_name'], 'qty_planned' => $s['qty']]);
            }
            foreach ($sizesHave as $k => $s) if (!isset($sizesPosted[$k])) DB::run('DELETE FROM launch_sizes WHERE id = ?', [$s['id']]);
        });
        Audit::log('launch_update', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch updated.');
        redirect("launches/{$l['id']}");
    }

    /* ---------- show ---------- */

    public function show(string $id): void
    {
        $l = $this->load($id);
        $fabric = $this->fabric((int)$l['id']);
        $orders = $this->orders((int)$l['id']);
        $docs = DB::all(
            "SELECT d.id, d.type, d.doc_no, d.note, d.created_at, d.warehouse_id, w.name AS warehouse, u.name AS user_name
             FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN users u ON u.id = d.created_by
             WHERE d.launch_id = ? AND d.type = 'launch_receipt' ORDER BY d.id", [$l['id']]);
        foreach ($docs as &$d) {
            $d['lines'] = DB::all(
                'SELECT x.qty, x.unit_cost, i.name AS item_name, v.sku, r.code AS rack
                 FROM stock_doc_lines x JOIN item_variants v ON v.id = x.variant_id JOIN items i ON i.id = v.item_id
                 LEFT JOIN locations r ON r.id = x.location_id AND x.location_id > 0
                 WHERE x.doc_id = ? ORDER BY x.id', [$d['id']]);
        }
        unset($d);
        $live = array_filter($orders, fn($o) => $o['status'] !== 'cancelled');
        $this->view('app/launches/show', ['title' => $l['launch_no'], 'l' => $l, 'fabric' => $fabric, 'orders' => $orders, 'sizes' => $this->sizes((int)$l['id']), 'docs' => $docs, 'money' => $this->money($l, $fabric),
            'statuses' => self::STATUS, 'can' => [
                'order' => in_array($l['status'], ['draft', ...self::OPEN], true),
                'edit' => in_array($l['status'], ['draft', ...self::OPEN], true),
                'cancel' => in_array($l['status'], ['draft', 'ordered'], true) && !$live,
                'receive' => in_array($l['status'], ['ordered', 'partial', 'dispatched', 'received'], true),
                'close' => in_array($l['status'], ['partial', 'dispatched', 'received'], true),
            ]]);
    }

    /* ---------- cancel / close ---------- */

    public function cancel(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}";
        if (!in_array($l['status'], ['draft', 'ordered'], true)) $this->bounce('Only a launch whose fabric has not started going out can be cancelled.', $back);
        if (DB::val("SELECT 1 FROM sales_orders WHERE launch_id = ? AND status <> 'cancelled' LIMIT 1", [$l['id']])) $this->bounce('Cancel its fabric orders (sales orders) first.', $back);
        DB::run("UPDATE catalogue_launches SET status = 'cancelled' WHERE id = ?", [$l['id']]);
        Audit::log('launch_cancel', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch cancelled.');
        redirect($back);
    }

    public function close(string $id): void
    {
        $l = $this->load($id);
        $back = "launches/{$l['id']}";
        if (!in_array($l['status'], ['partial', 'dispatched', 'received'], true)) $this->bounce('This launch cannot be closed yet.', $back);
        DB::run("UPDATE catalogue_launches SET status = 'closed' WHERE id = ?", [$l['id']]);
        Audit::log('launch_close', 'catalogue_launch', (int)$l['id']);
        flash('success', 'Launch closed.');
        redirect($back);
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
        $name = $l['catalogue'] . ' (catalogue) — ' . $size['size_name'];
        $v = DB::val("SELECT v.id FROM item_variants v JOIN items i ON i.id = v.item_id WHERE i.item_type = 'catalogue' AND i.name = ? AND v.is_active = 1 LIMIT 1", [$name]);
        if (!$v) {
            $unit = (int)(DB::val("SELECT id FROM units WHERE allow_decimal = 0 ORDER BY (short_name = 'pcs') DESC, id LIMIT 1") ?: DB::val('SELECT id FROM units ORDER BY id LIMIT 1'));
            $itemId = DB::insert('items', ['name' => mb_substr($name, 0, 190), 'item_type' => 'catalogue', 'unit_id' => $unit, 'description' => 'Printed catalogue, size ' . $size['size_name'] . '. Given free to showrooms and dealers.']);
            $v = DB::insert('item_variants', ['item_id' => $itemId, 'sku' => Numbering::next('SKU'), 'size' => $size['size_name'], 'cost_price' => 0, 'sale_price' => 0, 'is_active' => 1]);
        }
        DB::run('UPDATE launch_sizes SET variant_id = ? WHERE id = ?', [(int)$v, $size['id']]);
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
                $fabric = $this->fabric((int)$l['id']);
                $value = round(array_sum(array_map(fn($f) => (float)$f['value_issued'], $fabric)), 2);
                $pool = round($value - (float)$l['fabric_absorbed'] + $extra, 2);
                $unit = round($pool / $copies, 2);
                $no = Numbering::next('LRC');
                $docId = DB::insert('stock_docs', ['type' => 'launch_receipt', 'doc_no' => $no, 'warehouse_id' => $wh['id'], 'launch_id' => $l['id'], 'status' => 'posted',
                    'note' => trim((string)($d['note'] ?? '')) ?: null, 'created_by' => Auth::user()['id'], 'posted_at' => date('Y-m-d H:i:s')]);
                foreach ($lines as $x) {
                    $vid = $this->sizeItem($l, $x['s']);
                    Stock::move($vid, (int)$wh['id'], 0, (float)$x['qty'], 'launch_receipt', 'stock_doc', $docId, $unit, $no . ' · ' . $l['catalogue'] . ' ' . $x['s']['size_name'] . ' from ' . $l['launch_no'], $x['rack']);
                    DB::insert('stock_doc_lines', ['doc_id' => $docId, 'variant_id' => $vid, 'batch_id' => 0, 'location_id' => $x['rack'], 'qty' => $x['qty'], 'unit_cost' => $unit]);
                    DB::run('UPDATE launch_sizes SET qty_received = qty_received + ? WHERE id = ?', [$x['qty'], $x['s']['id']]);
                    DB::run('UPDATE item_variants SET cost_price = ? WHERE id = ?', [$unit, $vid]);
                }
                DB::run("UPDATE catalogue_launches SET status = 'received', extra_cost = extra_cost + ?, fabric_absorbed = ? WHERE id = ?", [$extra, $value, $l['id']]);
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
