<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Numbering;

/** Items CSV export / import. Excel users: File > Save As > CSV. */
final class ImportController extends Controller
{
    private const COLUMNS = ['item_name', 'category', 'brand', 'unit', 'tax', 'track_batch', 'reorder_level',
        'variant_name', 'sku', 'barcode', 'cost_price', 'sale_price'];
    private const MAX_ROWS = 2000;

    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function csv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) fputcsv($out, array_map(fn($c) => self::safe((string)$c), $r));
        exit;
    }

    /** Stops spreadsheet formula injection in exported cells. */
    private static function safe(string $v): string
    {
        return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v) ? "'" . $v : $v;
    }

    public function show(): void
    {
        $this->view('app/items/import', ['title' => 'Import items', 'columns' => self::COLUMNS]);
    }

    public function template(): void
    {
        $this->csv('items-template.csv', [
            self::COLUMNS,
            ['Velvet Fabric', 'Fabric', '', 'Meter', '', 'yes', '20', 'Grey', '', '', '450', '750'],
            ['Velvet Fabric', '', '', '', '', '', '', 'Blue', '', '', '450', '750'],
            ['Wooden Side Table', 'Furniture', 'Woodcraft', 'Piece', '', 'no', '5', '', 'TABLE-01', '', '2500', '4200'],
        ]);
    }

    public function export(): void
    {
        $rows = DB::all(
            'SELECT i.name AS item_name, c.name AS category, b.name AS brand, u.name AS unit, x.name AS tax,
                    i.track_batch, i.reorder_level, v.name AS variant_name, v.sku, v.barcode, v.cost_price, v.sale_price
             FROM items i JOIN item_variants v ON v.item_id = i.id AND v.is_active = 1 JOIN units u ON u.id = i.unit_id
             LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN taxes x ON x.id = i.tax_id
             WHERE i.tenant_id = ? AND i.is_bundle = 0 ORDER BY i.name, v.id', [$this->tid()]);
        $out = [self::COLUMNS];
        foreach ($rows as $r) {
            $out[] = [$r['item_name'], $r['category'], $r['brand'], $r['unit'], $r['tax'], $r['track_batch'] ? 'yes' : 'no',
                $r['reorder_level'] + 0, $r['variant_name'], $r['sku'], $r['barcode'], $r['cost_price'], $r['sale_price']];
        }
        $this->csv('items-' . date('Y-m-d') . '.csv', $out);
    }

    public function run(): void
    {
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $this->fail('Choose a CSV file to upload.');
        if ($f['size'] > 2 * 1024 * 1024) $this->fail('The file is larger than 2 MB.');

        $h = fopen($f['tmp_name'], 'r');
        $head = fgetcsv($h);
        if (!$head) $this->fail('The file is empty.');
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$head[0]);
        $head = array_map(fn($c) => strtolower(trim((string)$c)), $head);
        foreach (['item_name', 'unit'] as $need) {
            if (!in_array($need, $head, true)) $this->fail("Missing column \"$need\". Download the template to see the expected columns.");
        }
        $rows = [];
        $line = 1;
        while (($r = fgetcsv($h)) !== false) {
            $line++;
            if (count(array_filter($r, fn($c) => trim((string)$c) !== '')) === 0) continue;
            if (count($rows) >= self::MAX_ROWS) $this->fail('Too many rows (max ' . self::MAX_ROWS . ').');
            $assoc = [];
            foreach ($head as $i => $col) $assoc[$col] = trim((string)($r[$i] ?? ''));
            $assoc['_line'] = $line;
            $rows[] = $assoc;
        }
        fclose($h);
        if (!$rows) $this->fail('The file has no data rows.');

        $t = $this->tid();
        $units = [];
        foreach (DB::all('SELECT id, name FROM units WHERE tenant_id = ?', [$t]) as $u) $units[strtolower($u['name'])] = (int)$u['id'];
        $taxes = [];
        foreach (DB::all('SELECT id, name FROM taxes WHERE tenant_id = ?', [$t]) as $x) $taxes[strtolower($x['name'])] = (int)$x['id'];
        $existingItems = array_column(DB::all('SELECT LOWER(name) AS n FROM items WHERE tenant_id = ?', [$t]), 'n');

        // Group variant rows under their item. A blank item_name continues the previous item.
        $items = [];
        $errors = [];
        $current = null;
        $seenSku = $seenBar = [];
        foreach ($rows as $r) {
            $L = $r['_line'];
            $name = $r['item_name'] ?? '';
            if ($name !== '') {
                $key = strtolower($name);
                if (in_array($key, $existingItems, true)) { $errors[] = "Row $L: item \"$name\" already exists."; $current = null; continue; }
                if (isset($items[$key])) { $current = $key; }
                else {
                    $unit = $units[strtolower($r['unit'] ?? '')] ?? null;
                    if (!$unit) { $errors[] = "Row $L: unit \"" . ($r['unit'] ?? '') . '" does not exist (add it under Masters → Units).'; $current = null; continue; }
                    $tax = null;
                    if (($r['tax'] ?? '') !== '') {
                        $tax = $taxes[strtolower($r['tax'])] ?? null;
                        if (!$tax) { $errors[] = "Row $L: tax \"{$r['tax']}\" does not exist."; $current = null; continue; }
                    }
                    if (mb_strlen($name) > 190) { $errors[] = "Row $L: item name too long."; $current = null; continue; }
                    $items[$key] = ['name' => $name, 'category' => $r['category'] ?? '', 'brand' => $r['brand'] ?? '', 'unit_id' => $unit, 'tax_id' => $tax,
                        'track' => in_array(strtolower($r['track_batch'] ?? ''), ['yes', 'y', '1', 'true'], true) ? 1 : 0,
                        'reorder' => (float)($r['reorder_level'] ?? 0), 'variants' => []];
                    $current = $key;
                }
            }
            if ($current === null) { if ($name === '') $errors[] = "Row $L: no item to attach this variant to."; continue; }
            $sku = $r['sku'] ?? '';
            $bar = $r['barcode'] ?? '';
            foreach (['cost_price', 'sale_price'] as $c) {
                if (($r[$c] ?? '') !== '' && (!is_numeric($r[$c]) || (float)$r[$c] < 0)) { $errors[] = "Row $L: $c must be a number."; continue 2; }
            }
            if ($sku !== '') {
                if (isset($seenSku[strtolower($sku)]) || DB::val('SELECT 1 FROM item_variants WHERE tenant_id = ? AND sku = ?', [$t, $sku])) { $errors[] = "Row $L: SKU \"$sku\" already exists."; continue; }
                $seenSku[strtolower($sku)] = 1;
            }
            if ($bar !== '') {
                if (isset($seenBar[$bar]) || DB::val('SELECT 1 FROM item_variants WHERE tenant_id = ? AND barcode = ?', [$t, $bar])) { $errors[] = "Row $L: barcode \"$bar\" already exists."; continue; }
                $seenBar[$bar] = 1;
            }
            $items[$current]['variants'][] = ['name' => $r['variant_name'] ?? '', 'sku' => $sku, 'barcode' => $bar,
                'cost' => (float)($r['cost_price'] ?? 0), 'sale' => (float)($r['sale_price'] ?? 0)];
        }

        $max = (int)Auth::user()['max_items'];
        $have = (int)DB::val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$t]);
        if ($max > 0 && $have + count($items) > $max) $errors[] = "Your plan allows $max items; you have $have and this file adds " . count($items) . '.';
        if ($errors) {
            foreach (array_slice($errors, 0, 15) as $e) flash('danger', $e);
            if (count($errors) > 15) flash('danger', '…and ' . (count($errors) - 15) . ' more problems. Nothing was imported.');
            else flash('warning', 'Nothing was imported. Fix the problems above and upload again.');
            redirect('items/import');
        }

        $created = DB::transaction(function () use ($items, $t) {
            $lookup = function (string $table, string $name) use ($t): ?int {
                if ($name === '') return null;
                $id = DB::val("SELECT id FROM `$table` WHERE tenant_id = ? AND name = ?", [$t, $name]);
                return $id ? (int)$id : DB::insert($table, ['tenant_id' => $t, 'name' => mb_substr($name, 0, 100)]);
            };
            $n = 0;
            foreach ($items as $it) {
                $itemId = DB::insert('items', [
                    'tenant_id' => $t, 'name' => $it['name'], 'category_id' => $lookup('categories', $it['category']),
                    'brand_id' => $lookup('brands', $it['brand']), 'unit_id' => $it['unit_id'], 'tax_id' => $it['tax_id'],
                    'track_batch' => $it['track'], 'reorder_level' => $it['reorder'],
                ]);
                foreach ($it['variants'] ?: [['name' => '', 'sku' => '', 'barcode' => '', 'cost' => 0, 'sale' => 0]] as $v) {
                    DB::insert('item_variants', [
                        'tenant_id' => $t, 'item_id' => $itemId, 'name' => $v['name'] ?: null,
                        'sku' => $v['sku'] !== '' ? $v['sku'] : Numbering::next($t, 'SKU'), 'barcode' => $v['barcode'] ?: null,
                        'cost_price' => $v['cost'], 'sale_price' => $v['sale'],
                    ]);
                }
                $n++;
            }
            return $n;
        });
        Audit::log('items_import', 'item', null, "$created item(s)");
        flash('success', "$created item(s) imported.");
        redirect('items');
    }

    private function fail(string $msg): never
    {
        flash('danger', $msg);
        redirect('items/import');
    }
}
