<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use Core\Audit;
use Core\DB;
use Core\Scope;

/**
 * Products: what the showroom calls a design (CARAVAN-7). A product has its own price and is sold from one item;
 * the stock, rolls and purchasing stay on the item. It may sit in a catalogue (a book) or stand alone.
 */
final class ProductController extends SalesBase
{
    private const MAX_ROWS = 5000;
    private const SEL = 'SELECT p.*, c.name AS catalogue, c.code AS catalogue_code, v.sku, i.name AS item_name, i.id AS item_id,
            (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s WHERE s.tenant_id = p.tenant_id AND s.variant_id = p.variant_id%WH%) AS stock, u.short_name AS unit
        FROM products p LEFT JOIN catalogues c ON c.id = p.catalogue_id JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id';

    private function sel(): string
    {
        $w = Scope::warehouse();
        return str_replace('%WH%', $w !== null ? ' AND s.warehouse_id = ' . (int)$w : '', self::SEL);
    }

    private function load(string $id): array
    {
        return DB::one($this->sel() . ' WHERE p.tenant_id = ? AND p.id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    private function catalogues(bool $onlyActive = false): array
    {
        return DB::all('SELECT id, name, code, named, is_active FROM catalogues WHERE tenant_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY name', [$this->tid()]);
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $cat = (string)($_GET['catalogue'] ?? '');
        $state = (string)($_GET['state'] ?? '');
        $where = 'p.tenant_id = ?'; $p = [$this->tid()];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (p.name LIKE ? OR p.code LIKE ? OR c.name LIKE ? OR c.code LIKE ? OR i.name LIKE ? OR v.sku LIKE ?)'; array_push($p, $like, $like, $like, $like, $like, $like);
        }
        if ($cat === 'none') $where .= ' AND p.catalogue_id IS NULL';
        elseif ((int)$cat > 0) { $where .= ' AND p.catalogue_id = ?'; $p[] = (int)$cat; }
        if ($state === 'active') $where .= ' AND p.is_active = 1'; elseif ($state === 'inactive') $where .= ' AND p.is_active = 0';
        $count = (int)DB::val("SELECT COUNT(*) FROM products p LEFT JOIN catalogues c ON c.id = p.catalogue_id JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id WHERE $where", $p);
        $page = max(1, (int)($_GET['page'] ?? 1)); $per = 50;
        $rows = DB::all($this->sel() . " WHERE $where ORDER BY c.name IS NULL, c.name, LENGTH(p.name), p.name LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $this->view('app/products/index', ['title' => 'Products', 'rows' => $rows, 'q' => $q, 'catalogue' => $cat, 'state' => $state, 'catalogues' => $this->catalogues(), 'total' => $count,
            'page' => $page, 'pages' => max(1, (int)ceil($count / $per))]);
    }

    public function create(): void
    {
        $this->view('app/products/form', ['title' => 'New product', 'row' => null, 'catalogues' => $this->catalogues(true), 'preset' => ['catalogue_id' => (int)($_GET['catalogue'] ?? 0), 'variant_id' => (int)($_GET['item'] ?? 0)],
            'item' => ($_GET['item'] ?? '') !== '' ? $this->itemOf((int)$_GET['item']) : null]);
    }

    public function edit(string $id): void
    {
        $r = $this->load($id);
        $this->view('app/products/form', ['title' => 'Edit ' . $r['name'], 'row' => $r, 'catalogues' => $this->catalogues(), 'preset' => null, 'item' => $this->itemOf((int)$r['variant_id'])]);
    }

    private function itemOf(int $variantId): ?array
    {
        $v = Stock::variant($variantId);
        return $v ? ['id' => (int)$v['id'], 'label' => Stock::label($v), 'sale_price' => (float)$v['sale_price']] : null;
    }

    /**
     * A design starts as a gutt number; its name can come later. Without a name it is called "<catalogue>-<number>" until it is named.
     * The number is unique inside its catalogue, the name among all the company's products.
     */
    private function fields(string $back, ?int $exceptId, ?array $row = null): array
    {
        $d = $this->input();
        $t = $this->tid();
        $code = trim((string)($d['code'] ?? ''));
        $name = trim((string)($d['name'] ?? ''));
        if ($code === '' && $name === '') $this->bounce('Give the design a gutt number, or a name.', $back);
        if (mb_strlen($code) > 40) $this->bounce('The gutt number is too long (max 40 characters).', $back);
        $cat = (int)($d['catalogue_id'] ?? 0);
        $catRow = $cat ? DB::one('SELECT id, name FROM catalogues WHERE tenant_id = ? AND id = ?', [$t, $cat]) : null;
        if ($cat && !$catRow) $this->bounce('Choose a valid catalogue, or leave it empty.', $back);
        $generated = $code !== '' ? ($catRow ? $catRow['name'] . '-' : '') . $code : '';
        $named = $name !== '' && $name !== $generated ? 1 : 0;
        if ($name === '') $name = $generated;
        if ($row !== null && $named === 0 && $name === $row['name'] && (int)$row['named'] === 1) $named = 1;
        if (mb_strlen($name) > 150) $this->bounce('Product name is too long (max 150 characters).', $back);
        if (DB::val('SELECT 1 FROM products WHERE tenant_id = ? AND name = ? AND id <> ?', [$t, $name, $exceptId ?? 0])) $this->bounce('A product with that name already exists.', $back);
        if ($code !== '' && DB::val('SELECT 1 FROM products WHERE tenant_id = ? AND code = ? AND catalogue_id <=> ? AND id <> ?', [$t, $code, $cat ?: null, $exceptId ?? 0])) $this->bounce('That gutt number is already used in this catalogue.', $back);
        $v = Stock::variant((int)($d['variant_id'] ?? 0));
        if (!$v) $this->bounce('Search and choose the item this product is sold from.', $back);
        return ['name' => $name, 'code' => $code !== '' ? $code : null, 'named' => $named, 'catalogue_id' => $cat ?: null, 'variant_id' => (int)$v['id'], 'price' => $this->money($d['price'] ?? '', 'Price', $back),
            'is_active' => array_key_exists('is_active', $d) && empty($d['is_active']) ? 0 : 1];
    }

    public function store(): void
    {
        $data = $this->fields('products/create', null);
        $id = DB::insert('products', ['tenant_id' => $this->tid()] + $data);
        Audit::log('product_create', 'product', $id, $data['name']);
        flash('success', 'Product created.');
        redirect(!empty($_POST['add_another']) ? 'products/create' . ($data['catalogue_id'] ? '?catalogue=' . $data['catalogue_id'] : '') : ($data['catalogue_id'] ? 'catalogues/' . $data['catalogue_id'] : 'products'));
    }

    public function update(string $id): void
    {
        $r = $this->load($id);
        $data = $this->fields("products/{$r['id']}/edit", (int)$r['id'], $r);
        DB::run('UPDATE products SET name = ?, code = ?, named = ?, catalogue_id = ?, variant_id = ?, price = ?, is_active = ? WHERE tenant_id = ? AND id = ?',
            [$data['name'], $data['code'], $data['named'], $data['catalogue_id'], $data['variant_id'], $data['price'], $data['is_active'], $this->tid(), $r['id']]);
        Audit::log('product_update', 'product', (int)$r['id']);
        flash('success', 'Product updated.');
        redirect($data['catalogue_id'] ? 'catalogues/' . $data['catalogue_id'] : 'products');
    }

    public function destroy(string $id): void
    {
        $r = $this->load($id); $t = $this->tid();
        if (DB::val('SELECT 1 FROM sales_order_items WHERE tenant_id = ? AND product_id = ? LIMIT 1', [$t, $r['id']]) || DB::val('SELECT 1 FROM delivery_items WHERE tenant_id = ? AND product_id = ? LIMIT 1', [$t, $r['id']])) {
            $this->bounce('This product is on sales documents, so it cannot be deleted. Mark it inactive instead: it stops appearing when selling, and the documents keep its name.', "products/{$r['id']}/edit");
        }
        DB::run('DELETE FROM products WHERE tenant_id = ? AND id = ?', [$t, $r['id']]);
        Audit::log('product_delete', 'product', (int)$r['id'], $r['name']);
        flash('success', 'Product deleted.');
        redirect($r['catalogue_id'] ? 'catalogues/' . $r['catalogue_id'] : 'products');
    }

    /* ---------------- CSV ---------------- */

    private function csvOut(string $file, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF");
        $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) && !is_numeric($v) ? "'" . $v : (string)$v;
        foreach ($rows as $r) fputcsv($o, array_map($safe, $r), ',', '"', '\\');
        fclose($o);
        exit;
    }

    public function template(): void
    {
        $this->csvOut('products-template.csv', [['Catalogue Name', 'Product Name', 'Item Name', 'Price', 'Gutt No'], ['CARAVAN', 'CARAVAN-1', 'NUC-212589', '450', ''], ['CARAVAN', '', 'NUC-212572', '450', '2'], ['', 'Plain Cotton Bedsheet', 'BS-K-BL', '1199', '']]);
    }

    public function export(): void
    {
        $rows = DB::all($this->sel() . ' WHERE p.tenant_id = ? ORDER BY c.name IS NULL, c.name, LENGTH(p.name), p.name', [$this->tid()]);
        $out = [['Catalogue Name', 'Product Name', 'Item Name', 'Price', 'Active', 'Gutt No']];
        foreach ($rows as $r) $out[] = [$r['catalogue'] ?? '', $r['name'], $r['item_name'], $r['price'] + 0, $r['is_active'] ? 'yes' : 'no', $r['code'] ?? ''];
        $this->csvOut('products-' . date('Y-m-d') . '.csv', $out);
    }

    /** Catalogue Name, Product Name, Item Name (and optionally Price). All rows are checked first; nothing is saved if anything is wrong. */
    public function import(): void
    {
        $back = 'products';
        $fail = function (string $m) use ($back): never { flash('danger', $m); redirect($back); };
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) $fail('Choose a CSV file to import.');
        if ($f['size'] > 2 * 1024 * 1024) $fail('The file is larger than 2 MB.');
        $h = fopen($f['tmp_name'], 'r');
        $head = fgetcsv($h, 0, ',', '"', '\\');
        if (!$head) $fail('The file is empty.');
        $norm = fn($x) => preg_replace('/\s+/', '_', strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$x))));
        $head = array_map($norm, $head);
        $col = fn(array $names) => (function () use ($head, $names) { foreach ($names as $n) { $i = array_search($n, $head, true); if ($i !== false) return $i; } return null; })();
        $cC = $col(['catalogue_name', 'catalogue']); $cP = $col(['product_name', 'product']); $cI = $col(['item_name', 'item']); $cPr = $col(['price', 'sale_price']); $cG = $col(['gutt_no', 'gutt_number', 'gutt', 'code']);
        if (($cP === null && $cG === null) || $cI === null) $fail('The first row must have the columns "Item Name" and "Product Name" or "Gutt No" (and "Catalogue Name", "Price" if you have them).');
        $t = $this->tid();
        $rows = []; $line = 1;
        while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            $line++;
            if (!array_filter($r, fn($x) => trim((string)$x) !== '')) continue;
            if (count($rows) >= self::MAX_ROWS) $fail('Too many rows (max ' . self::MAX_ROWS . ').');
            $rows[] = ['line' => $line, 'cat' => $cC === null ? '' : trim((string)($r[$cC] ?? '')), 'name' => $cP === null ? '' : trim((string)($r[$cP] ?? '')), 'gutt' => $cG === null ? '' : trim((string)($r[$cG] ?? '')), 'item' => trim((string)($r[$cI] ?? '')), 'price' => $cPr === null ? '' : trim((string)($r[$cPr] ?? ''))];
        }
        fclose($h);
        if (!$rows) $fail('The file has no data rows.');
        $items = [];
        foreach (DB::all('SELECT v.id, v.sale_price, i.name FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND i.is_active = 1', [$t]) as $x) $items[mb_strtolower($x['name'])][] = $x;
        $existing = [];
        foreach (DB::all('SELECT id, name FROM products WHERE tenant_id = ?', [$t]) as $x) $existing[mb_strtolower($x['name'])] = (int)$x['id'];
        $cats = [];
        foreach (DB::all('SELECT id, name FROM catalogues WHERE tenant_id = ?', [$t]) as $x) $cats[mb_strtolower($x['name'])] = (int)$x['id'];
        $errors = []; $seen = []; $plan = [];
        foreach ($rows as $r) {
            $L = $r['line'];
            // a design known only by its gutt number is called "<catalogue>-<number>" until it is named
            if ($r['name'] === '' && $r['gutt'] !== '') $r['name'] = ($r['cat'] !== '' ? $r['cat'] . '-' : '') . $r['gutt'];
            if ($r['name'] === '' || mb_strlen($r['name']) > 150) { $errors[] = "Row $L: product name (or gutt number) is empty or too long."; continue; }
            if (mb_strlen($r['gutt']) > 40) { $errors[] = "Row $L: gutt number is too long."; continue; }
            if (isset($seen[mb_strtolower($r['name'])])) { $errors[] = "Row $L: product \"{$r['name']}\" is listed twice in this file."; continue; }
            $seen[mb_strtolower($r['name'])] = 1;
            if (mb_strlen($r['cat']) > 120) { $errors[] = "Row $L: catalogue name is too long."; continue; }
            $m = $items[mb_strtolower($r['item'])] ?? [];
            if (!$m) { $errors[] = "Row $L: item \"{$r['item']}\" was not found (it must already exist under Items)."; continue; }
            if (count($m) > 1) { $errors[] = "Row $L: more than one item is called \"{$r['item']}\"."; continue; }
            if ($r['price'] !== '' && (!is_numeric($r['price']) || (float)$r['price'] < 0)) { $errors[] = "Row $L: price must be a number."; continue; }
            $plan[] = $r + ['variant' => (int)$m[0]['id'], 'fallback' => (float)$m[0]['sale_price']];
        }
        if ($errors) {
            foreach (array_slice($errors, 0, 15) as $e) flash('danger', $e);
            flash('warning', count($errors) > 15 ? '…and ' . (count($errors) - 15) . ' more problems. Nothing was imported.' : 'Nothing was imported. Fix the problems above and upload again.');
            redirect($back);
        }
        [$added, $updated, $newCats] = DB::transaction(function () use ($plan, $existing, &$cats, $t) {
            $a = $u = $nc = 0;
            foreach ($plan as $r) {
                $cid = null;
                if ($r['cat'] !== '') {
                    $k = mb_strtolower($r['cat']);
                    if (!isset($cats[$k])) { $cats[$k] = DB::insert('catalogues', ['tenant_id' => $t, 'name' => $r['cat']]); $nc++; }
                    $cid = $cats[$k];
                }
                $k = mb_strtolower($r['name']);
                if (isset($existing[$k])) {
                    DB::run('UPDATE products SET catalogue_id = ?, variant_id = ?' . ($r['price'] !== '' ? ', price = ?' : '') . ($r['gutt'] !== '' ? ', code = ?' : '') . ' WHERE tenant_id = ? AND id = ?',
                        array_merge([$cid, $r['variant']], $r['price'] !== '' ? [round((float)$r['price'], 2)] : [], $r['gutt'] !== '' ? [$r['gutt']] : [], [$t, $existing[$k]]));
                    $u++;
                } else {
                    DB::insert('products', ['tenant_id' => $t, 'catalogue_id' => $cid, 'variant_id' => $r['variant'], 'name' => $r['name'], 'code' => $r['gutt'] !== '' ? $r['gutt'] : null, 'named' => $r['gutt'] !== '' && $r['name'] === ($r['cat'] !== '' ? $r['cat'] . '-' : '') . $r['gutt'] ? 0 : 1, 'price' => $r['price'] !== '' ? round((float)$r['price'], 2) : $r['fallback']]);
                    $a++;
                }
            }
            return [$a, $u, $nc];
        });
        Audit::log('product_import', 'product', null, "$added added, $updated updated");
        flash('success', "$added product(s) added" . ($updated ? ", $updated updated" : '') . ($newCats ? ", $newCats catalogue(s) created" : '') . '.');
        redirect($back);
    }
}
