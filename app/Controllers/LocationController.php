<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

/** Racks / bins inside a warehouse. Stock can be kept per rack. */
final class LocationController extends Controller
{
    private const MAX_RANGE = 200;

    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function load(string $id): array
    {
        return DB::one(
            'SELECT l.*, w.name AS warehouse FROM locations l JOIN warehouses w ON w.id = l.warehouse_id WHERE l.tenant_id = ? AND l.id = ?',
            [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function warehouses(): array
    {
        return DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name', [$this->tid()]);
    }

    private function warehouseId(mixed $id, string $back): int
    {
        if (!DB::val('SELECT 1 FROM warehouses WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id])) {
            flash('danger', 'Choose a warehouse.');
            with_old($this->input());
            redirect($back);
        }
        return (int)$id;
    }

    public function index(): void
    {
        $wh = (int)($_GET['warehouse'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'l.tenant_id = ?';
        $params = [$this->tid()];
        if ($wh) { $where .= ' AND l.warehouse_id = ?'; $params[] = $wh; }
        if ($q !== '') { $where .= ' AND (l.code LIKE ? OR l.description LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($params, $like, $like); }
        $rows = DB::all(
            "SELECT l.*, w.name AS warehouse,
                    (SELECT COUNT(DISTINCT s.variant_id) FROM stock_balances s WHERE s.location_id = l.id AND s.qty > 0.0005) AS items
             FROM locations l JOIN warehouses w ON w.id = l.warehouse_id WHERE $where ORDER BY w.name, l.code LIMIT 500", $params);
        $unassigned = (int)DB::val('SELECT COUNT(DISTINCT variant_id) FROM stock_balances WHERE tenant_id = ? AND location_id = 0 AND qty > 0.0005', [$this->tid()]);
        $this->view('app/locations/index', ['title' => term('racks') . ' / locations', 'rows' => $rows, 'wh' => $wh, 'q' => $q,
            'warehouses' => DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? ORDER BY name', [$this->tid()]), 'unassigned' => $unassigned]);
    }

    public function show(string $id): void
    {
        $l = $this->load($id);
        $this->view('app/locations/show', ['title' => $l['code'], 'l' => $l,
            'stock' => DB::all('SELECT i.id AS item_id, i.name AS item_name, v.name AS vname, v.sku, u.short_name AS unit, b.batch_no, b.id AS batch_id, s.qty FROM stock_balances s
                                JOIN item_variants v ON v.id = s.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0
                                WHERE s.tenant_id = ? AND s.location_id = ? AND s.qty > 0.0005 ORDER BY i.name, b.batch_no LIMIT 200', [$this->tid(), $l['id']])]);
    }

    public function create(): void
    {
        $this->view('app/locations/form', ['title' => 'Add racks', 'row' => null, 'warehouses' => $this->warehouses()]);
    }

    private function cleanCode(string $c): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '-', $c)));
    }

    public function store(): void
    {
        $back = 'locations/create';
        $this->enforceFields('location', $back);
        $d = $this->input();
        $wh = $this->warehouseId($d['warehouse_id'] ?? 0, $back);
        $desc = trim((string)($d['description'] ?? '')) ?: null;
        if ($desc !== null && mb_strlen($desc) > 150) $this->fail('Description is too long.', $back);

        $codes = [];
        if (($d['mode'] ?? 'single') === 'range') {
            $prefix = $this->cleanCode((string)($d['prefix'] ?? ''));
            $from = (int)($d['from'] ?? 0);
            $to = (int)($d['to'] ?? 0);
            $pad = max(0, min(6, (int)($d['pad'] ?? 0)));
            if ($from < 0 || $to < $from) $this->fail('The range end must be the same as or after its start.', $back);
            if ($to - $from + 1 > self::MAX_RANGE) $this->fail('A range can create at most ' . self::MAX_RANGE . ' racks at a time.', $back);
            for ($i = $from; $i <= $to; $i++) $codes[] = $prefix . str_pad((string)$i, $pad, '0', STR_PAD_LEFT);
        } else {
            $codes[] = $this->cleanCode((string)($d['code'] ?? ''));
        }
        foreach ($codes as $c) {
            if ($c === '' || mb_strlen($c) > 30) $this->fail('Rack code is required (max 30 characters).', $back);
        }

        $made = 0;
        DB::transaction(function () use ($codes, $wh, $desc, &$made) {
            foreach ($codes as $c) {
                if (DB::val('SELECT 1 FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = ?', [$this->tid(), $wh, $c])) continue;
                DB::insert('locations', ['tenant_id' => $this->tid(), 'warehouse_id' => $wh, 'code' => $c, 'description' => $desc]);
                $made++;
            }
        });
        if ($made === 0) $this->fail('Those racks already exist in this warehouse.', $back);
        $skipped = count($codes) - $made;
        Audit::log('location_create', 'location', null, "$made rack(s)");
        flash('success', "$made rack(s) added" . ($skipped ? " ($skipped already existed)" : '') . '.');
        redirect('locations?warehouse=' . $wh);
    }

    private function fail(string $msg, string $back): never
    {
        flash('danger', $msg);
        with_old($this->input());
        redirect($back);
    }

    public function edit(string $id): void
    {
        $this->view('app/locations/form', ['title' => 'Edit rack', 'row' => $this->load($id), 'warehouses' => $this->warehouses()]);
    }

    public function update(string $id): void
    {
        $row = $this->load($id);
        $back = "locations/{$row['id']}/edit";
        $this->enforceFields('location', $back);
        $d = $this->input();
        $code = $this->cleanCode((string)($d['code'] ?? ''));
        if ($code === '' || mb_strlen($code) > 30) $this->fail('Rack code is required (max 30 characters).', $back);
        if (DB::val('SELECT 1 FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = ? AND id <> ?', [$this->tid(), $row['warehouse_id'], $code, $row['id']])) {
            $this->fail('Another rack in this warehouse already has that code.', $back);
        }
        $desc = trim((string)($d['description'] ?? '')) ?: null;
        if ($desc !== null && mb_strlen($desc) > 150) $this->fail('Description is too long.', $back);
        DB::run('UPDATE locations SET code = ?, description = ?, is_active = ? WHERE tenant_id = ? AND id = ?',
            [$code, $desc, empty($d['is_active']) ? 0 : 1, $this->tid(), $row['id']]);
        Audit::log('location_update', 'location', (int)$row['id']);
        flash('success', 'Rack updated.');
        redirect('locations?warehouse=' . $row['warehouse_id']);
    }

    public function destroy(string $id): void
    {
        $row = $this->load($id);
        $used = DB::val('SELECT 1 FROM stock_ledger WHERE tenant_id = ? AND location_id = ? LIMIT 1', [$this->tid(), $row['id']])
            || DB::val('SELECT 1 FROM stock_doc_lines WHERE tenant_id = ? AND (location_id = ? OR to_location_id = ?) LIMIT 1', [$this->tid(), $row['id'], $row['id']]);
        if ($used) {
            flash('danger', 'This rack has stock history and cannot be deleted. Mark it inactive instead.');
            redirect('locations?warehouse=' . $row['warehouse_id']);
        }
        DB::run('DELETE FROM locations WHERE tenant_id = ? AND id = ?', [$this->tid(), $row['id']]);
        Audit::log('location_delete', 'location', (int)$row['id'], $row['code']);
        flash('success', 'Rack deleted.');
        redirect('locations?warehouse=' . $row['warehouse_id']);
    }
}
