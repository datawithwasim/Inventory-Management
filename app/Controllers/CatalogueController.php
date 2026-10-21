<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\DB;

/** Catalogues: the books the showroom sells from (CARAVAN …). The designs in a book are Products. */
final class CatalogueController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one('SELECT * FROM catalogues WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'c.tenant_id = ?'; $p = [$this->tid()];
        if ($q !== '') { $where .= ' AND (c.name LIKE ? OR c.code LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; array_push($p, $like, $like); }
        $this->view('app/catalogues/index', ['title' => 'Catalogues', 'q' => $q, 'rows' => DB::all(
            "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.catalogue_id = c.id) AS products, (SELECT COUNT(*) FROM products p WHERE p.catalogue_id = c.id AND p.is_active = 1) AS active_products
             FROM catalogues c WHERE $where ORDER BY c.name", $p),
            'loose' => (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ? AND catalogue_id IS NULL', [$this->tid()])]);
    }

    public function show(string $id): void
    {
        $c = $this->load($id);
        $this->view('app/catalogues/show', ['title' => $c['name'], 'c' => $c, 'products' => DB::all(
            'SELECT p.*, v.sku, i.name AS item_name, i.id AS item_id FROM products p JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id
             WHERE p.tenant_id = ? AND p.catalogue_id = ? ORDER BY LENGTH(p.name), p.name', [$this->tid(), $c['id']])]);
    }

    public function create(): void
    {
        $this->view('app/catalogues/form', ['title' => 'New catalogue', 'row' => null]);
    }

    public function edit(string $id): void
    {
        $c = $this->load($id);
        $this->view('app/catalogues/form', ['title' => 'Edit ' . $c['name'], 'row' => $c]);
    }

    /** A catalogue starts as a gutt number; the name can come later. Name and number are each unique among the company's catalogues. */
    private function fields(string $back, ?int $exceptId, ?array $row = null): array
    {
        $d = $this->input();
        $code = trim((string)($d['code'] ?? ''));
        $name = trim((string)($d['name'] ?? ''));
        if ($code === '' && $name === '') $this->bounce('Give the catalogue a gutt number, or a name.', $back);
        if (mb_strlen($code) > 40) $this->bounce('The gutt number is too long (max 40 characters).', $back);
        if (mb_strlen($name) > 120) $this->bounce('Catalogue name is too long (max 120 characters).', $back);
        // no name yet: the catalogue is called by its number until it is named
        $named = $name !== '' && $name !== $code ? 1 : 0;
        if ($name === '') $name = $code;
        if ($row !== null && $named === 0 && $name === $row['name'] && (int)$row['named'] === 1) $named = 1;   // an untouched real name stays a real name
        if ($code !== '' && DB::val('SELECT 1 FROM catalogues WHERE tenant_id = ? AND code = ? AND id <> ?', [$this->tid(), $code, $exceptId ?? 0])) $this->bounce('A catalogue with that gutt number already exists.', $back);
        if (DB::val('SELECT 1 FROM catalogues WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId ?? 0])) $this->bounce('A catalogue with that name already exists.', $back);
        return ['name' => $name, 'code' => $code !== '' ? $code : null, 'named' => $named, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back), 'is_active' => array_key_exists('is_active', $d) && empty($d['is_active']) ? 0 : 1];
    }

    public function store(): void
    {
        $data = $this->fields('catalogues/create', null);
        $id = DB::insert('catalogues', ['tenant_id' => $this->tid()] + $data);
        Audit::log('catalogue_create', 'catalogue', $id, $data['name']);
        flash('success', 'Catalogue created. Add its designs now, or import them from a CSV file.');
        redirect("catalogues/$id");
    }

    public function update(string $id): void
    {
        $c = $this->load($id);
        $data = $this->fields("catalogues/{$c['id']}/edit", (int)$c['id'], $c);
        DB::run('UPDATE catalogues SET name = ?, code = ?, named = ?, notes = ?, is_active = ? WHERE tenant_id = ? AND id = ?', [$data['name'], $data['code'], $data['named'], $data['notes'], $data['is_active'], $this->tid(), $c['id']]);
        Audit::log('catalogue_update', 'catalogue', (int)$c['id']);
        flash('success', 'Catalogue updated.');
        redirect("catalogues/{$c['id']}");
    }

    public function destroy(string $id): void
    {
        $c = $this->load($id);
        $n = (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ? AND catalogue_id = ?', [$this->tid(), $c['id']]);
        if ($n > 0) $this->bounce("This catalogue still has $n product(s). Delete or move them first, or mark the catalogue inactive.", "catalogues/{$c['id']}");
        DB::run('DELETE FROM catalogues WHERE tenant_id = ? AND id = ?', [$this->tid(), $c['id']]);
        Audit::log('catalogue_delete', 'catalogue', (int)$c['id'], $c['name']);
        flash('success', 'Catalogue deleted.');
        redirect('catalogues');
    }
}
