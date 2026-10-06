<?php
declare(strict_types=1);

/**
 * End-to-end test for Phase 2 (masters, items, batches, stock movements).
 *   php -S 127.0.0.1:8099 -t public public/index.php &
 *   php tests/phase2.php http://127.0.0.1:8099        # against a throw-away database
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\DB;

$sfx = bin2hex(random_bytes(3));
$sa = config('superadmin');
$admin = new Client($base);
$admin->post('/admin/login', ['email' => $sa['email'], 'password' => $sa['password']], '/admin/login');

$proPlan = (int)DB::val("SELECT id FROM plans WHERE slug = 'pro'");
$limitPlan = DB::insert('plans', ['name' => "Limited $sfx", 'slug' => "limited-$sfx", 'max_users' => 0, 'max_items' => 1, 'max_warehouses' => 1]);
$mkTenant = function (string $name, int $plan) use ($admin, $sfx): array {
    $email = strtolower(preg_replace('/\W/', '', $name)) . "-$sfx@test.local";
    $admin->post('/admin/tenants', ['name' => "$name $sfx", 'plan_id' => $plan, 'status' => 'active', 'subscription_ends_at' => '',
        'owner_name' => "Owner $name", 'owner_email' => $email, 'owner_password' => 'Password123'], '/admin/tenants/create');
    $c = new Client($GLOBALS['base']);
    $c->post('/login', ['email' => $email, 'password' => 'Password123'], '/login');
    return [$c, (int)DB::val('SELECT id FROM tenants WHERE name = ?', ["$name $sfx"]), $email];
};
[$a, $tA] = $mkTenant('Alpha', $proPlan);
[$b, $tB] = $mkTenant('Bravo', $proPlan);
[$l, $tL] = $mkTenant('Limited', $limitPlan);

$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$unit = fn(int $t, string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$t, $n]);
$wh = fn(int $t, string $n) => (int)DB::val('SELECT id FROM warehouses WHERE tenant_id = ? AND name = ?', [$t, $n]);
$bal = fn(int $t, int $variant, int $w, int $batch = 0) => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ?', [$t, $variant, $w, $batch]);
$docs = fn(int $t, string $type) => (int)DB::val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = ?', [$t, $type]);
$ledgerRows = fn(int $t) => (int)DB::val('SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ?', [$t]);


echo "Defaults for a new company\n";
check('6 starter units', (int)$val('SELECT COUNT(*) FROM units WHERE tenant_id = ?', [$tA]) === 6);
check('Main warehouse exists and is default', (int)$val("SELECT is_default FROM warehouses WHERE tenant_id = ? AND name = 'Main Warehouse'", [$tA]) === 1);

echo "Masters\n";
$a->post('/masters/categories', ['name' => 'Fabric'], '/masters/categories/create');
$a->post('/masters/categories', ['name' => 'Fabric'], '/masters/categories/create');
check('category created once, duplicate refused', (int)$val("SELECT COUNT(*) FROM categories WHERE tenant_id = ? AND name = 'Fabric'", [$tA]) === 1);
$a->post('/masters/brands', ['name' => 'Woodcraft'], '/masters/brands/create');
$a->post('/masters/taxes', ['name' => 'GST 12', 'rate' => '12'], '/masters/taxes/create');
check('tax saved with rate', (float)$val("SELECT rate FROM taxes WHERE tenant_id = ? AND name = 'GST 12'", [$tA]) === 12.0);
$a->post('/masters/taxes', ['name' => 'Bad', 'rate' => '-5'], '/masters/taxes/create');
check('negative tax rate rejected', !$val("SELECT 1 FROM taxes WHERE tenant_id = ? AND name = 'Bad'", [$tA]));
check('masters pages load', $a->get('/masters/units')['status'] === 200 && $a->get('/masters/taxes')['status'] === 200);
$catId = (int)$val("SELECT id FROM categories WHERE tenant_id = ? AND name = 'Fabric'", [$tA]);
$taxId = (int)$val("SELECT id FROM taxes WHERE tenant_id = ? AND name = 'GST 12'", [$tA]);
$brandId = (int)$val("SELECT id FROM brands WHERE tenant_id = ? AND name = 'Woodcraft'", [$tA]);

echo "Warehouses\n";
$a->post('/warehouses', ['name' => 'Showroom', 'code' => 'shw'], '/warehouses/create');
check('second warehouse added (code upper-cased)', $val("SELECT code FROM warehouses WHERE tenant_id = ? AND name = 'Showroom'", [$tA]) === 'SHW');
$main = $wh($tA, 'Main Warehouse');
$show = $wh($tA, 'Showroom');
$l->post('/warehouses', ['name' => 'Second', 'code' => 'S2'], '/warehouses');
check('plan warehouse limit enforced', (int)$val('SELECT COUNT(*) FROM warehouses WHERE tenant_id = ?', [$tL]) === 1);

echo "Items\n";
$a->post('/items', ['name' => 'Velvet', 'category_id' => $catId, 'unit_id' => $unit($tA, 'Meter'), 'tax_id' => $taxId, 'track_batch' => 1,
    'variants' => [['name' => 'Grey', 'cost_price' => 400, 'sale_price' => 700], ['name' => 'Blue', 'cost_price' => 400, 'sale_price' => 700]]], '/items/create');
$velvet = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Velvet'", [$tA]);
check('fabric item with 2 variants', (int)$val('SELECT COUNT(*) FROM item_variants WHERE item_id = ?', [$velvet]) === 2);
check('SKUs auto-generated and unique', (int)$val('SELECT COUNT(DISTINCT sku) FROM item_variants WHERE item_id = ?', [$velvet]) === 2);
check('batch tracking on for fabric', (int)$val('SELECT track_batch FROM items WHERE id = ?', [$velvet]) === 1);
$grey = (int)$val("SELECT id FROM item_variants WHERE item_id = ? AND name = 'Grey'", [$velvet]);
$greySku = (string)$val('SELECT sku FROM item_variants WHERE id = ?', [$grey]);

$a->post('/items', ['name' => 'Side Table', 'brand_id' => $brandId, 'unit_id' => $unit($tA, 'Piece'), 'reorder_level' => 5,
    'variants' => [['sku' => 'TABLE-01', 'barcode' => '8901', 'cost_price' => 2500, 'sale_price' => 4200]]], '/items/create');
$table = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Side Table'", [$tA]);
$tableV = (int)$val('SELECT id FROM item_variants WHERE item_id = ?', [$table]);
check('simple item created with its SKU', $val('SELECT sku FROM item_variants WHERE id = ?', [$tableV]) === 'TABLE-01');
$a->post('/items', ['name' => 'Other', 'unit_id' => $unit($tA, 'Piece'), 'variants' => [['sku' => 'TABLE-01']]], '/items/create');
check('duplicate SKU refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Other'", [$tA]));
$a->post('/items', ['name' => 'Other', 'unit_id' => $unit($tA, 'Piece'), 'variants' => [['sku' => 'X1', 'barcode' => '8901']]], '/items/create');
check('duplicate barcode refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Other'", [$tA]));
$a->post('/items', ['name' => 'Other', 'unit_id' => $unit($tB, 'Piece'), 'variants' => [['sku' => 'X2']]], '/items/create');
check("another company's unit is refused", !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Other'", [$tA]));

$a->post('/items', ['name' => 'Dining Set', 'is_bundle' => 1, 'unit_id' => $unit($tA, 'Set'), 'variants' => [['sku' => 'SET-01', 'sale_price' => 9000]],
    'components' => [['variant_id' => $tableV, 'qty' => 2]]], '/items/create');
$set = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Dining Set'", [$tA]);
check('bundle created with component', (int)$val('SELECT COUNT(*) FROM bundle_components WHERE bundle_item_id = ?', [$set]) === 1);
$a->post('/items', ['name' => 'Empty Set', 'is_bundle' => 1, 'unit_id' => $unit($tA, 'Set'), 'variants' => [['sku' => 'SET-02']]], '/items/create');
check('bundle without components refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Empty Set'", [$tA]));

$a->post("/items/$table", ['name' => 'Side Table (Oak)', 'unit_id' => $unit($tA, 'Piece'), 'is_active' => 1, 'reorder_level' => 5,
    'variants' => [['id' => $tableV, 'sku' => 'TABLE-01', 'barcode' => '8901', 'cost_price' => 2600, 'sale_price' => 4200]]], "/items/$table/edit");
check('item edited, same variant kept', $val('SELECT name FROM items WHERE id = ?', [$table]) === 'Side Table (Oak)' && (float)$val('SELECT cost_price FROM item_variants WHERE id = ?', [$tableV]) === 2600.0);
$a->post("/items/$table", ['name' => 'Side Table', 'unit_id' => $unit($tA, 'Piece'), 'is_active' => 1, 'reorder_level' => 5,
    'variants' => [['id' => $tableV, 'sku' => 'TABLE-01', 'barcode' => '8901', 'cost_price' => 2500, 'sale_price' => 4200]]], "/items/$table/edit");
check('item list, search and detail pages load', $a->get('/items')['status'] === 200 && str_contains($a->get('/items?q=velv')['body'], 'Velvet') && $a->get("/items/$velvet")['status'] === 200);

$l->post('/items', ['name' => 'One', 'unit_id' => $unit($tL, 'Piece'), 'variants' => [['sku' => 'L1']]], '/items/create');
$l->post('/items', ['name' => 'Two', 'unit_id' => $unit($tL, 'Piece'), 'variants' => [['sku' => 'L2']]], '/items/create');
check('plan item limit enforced', (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tL]) === 1);

echo "Stock in (opening stock, new batch)\n";
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'opening', 'lines' => [
    ['variant_id' => $tableV, 'qty' => 10, 'unit_cost' => 2500],
    ['variant_id' => $grey, 'qty' => 50, 'batch_id' => 'new', 'lot_no' => 'LOT-A', 'unit_cost' => 400],
]], '/stock/adjustments/create');
check('adjustment document posted', $docs($tA, 'adjustment') === 1);
check('table stock = 10', $bal($tA, $tableV, $main) === 10.0);
$batch = (int)$val('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $grey]);
check('fabric roll became a batch of 50 m', $batch > 0 && (float)$val('SELECT received_qty FROM batches WHERE id = ?', [$batch]) === 50.0);
check('batch number auto-generated from SKU', $val('SELECT batch_no FROM batches WHERE id = ?', [$batch]) === $greySku . '-B0001');
check('supplier lot saved', $val('SELECT supplier_lot FROM batches WHERE id = ?', [$batch]) === 'LOT-A');
check('batch balance = 50', $bal($tA, $grey, $main, $batch) === 50.0);
check('ledger rows typed as opening', (int)$val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND type = 'opening'", [$tA]) === 2);

echo "Stock rules\n";
$before = $ledgerRows($tA);
$adj = fn(array $lines) => $a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'damage', 'lines' => $lines], '/stock/adjustments/create');
$r = $adj([['variant_id' => $grey, 'qty' => -60, 'batch_id' => $batch]]);
check('cannot remove more than the batch holds', $bal($tA, $grey, $main, $batch) === 50.0 && str_contains($a->follow($r)['body'], 'Not enough stock'));
$adj([['variant_id' => $tableV, 'qty' => -3], ['variant_id' => $grey, 'qty' => -60, 'batch_id' => $batch]]);
check('failed adjustment changes nothing (all-or-nothing)', $bal($tA, $tableV, $main) === 10.0 && $ledgerRows($tA) === $before && $docs($tA, 'adjustment') === 1);
$adj([['variant_id' => $tableV, 'qty' => -1.5]]);
check('whole-number units refuse decimals', $bal($tA, $tableV, $main) === 10.0);
$adj([['variant_id' => $grey, 'qty' => -5, 'batch_id' => '']]);
check('batch item needs a batch on removal', $bal($tA, $grey, $main, $batch) === 50.0);
$adj([['variant_id' => $tableV, 'qty' => 0]]);
check('zero quantity refused', $ledgerRows($tA) === $before);
$adj([['variant_id' => $set ? (int)$val('SELECT id FROM item_variants WHERE item_id = ?', [$set]) : 0, 'qty' => 1]]);
check('bundles hold no stock of their own', $ledgerRows($tA) === $before);
$adj([['variant_id' => $grey, 'qty' => -8, 'batch_id' => $batch], ['variant_id' => $tableV, 'qty' => -6]]);
check('valid removal: batch 42, table 4', $bal($tA, $grey, $main, $batch) === 42.0 && $bal($tA, $tableV, $main) === 4.0);
$adj([['variant_id' => $grey, 'qty' => -2.5, 'batch_id' => $batch]]);
check('meters accept decimals (39.5 left)', $bal($tA, $grey, $main, $batch) === 39.5);
check('low-stock filter lists the table', str_contains($a->get('/stock?low=1')['body'], 'Side Table') && str_contains($a->get('/items?low=1')['body'], 'Side Table'));
check('bundle availability = 2 sets', str_contains($a->get("/items/$set")['body'], '2 set(s)'));

echo "Transfers\n";
$tr = fn(array $lines, ?int $to = null) => $a->post('/stock/transfers', ['warehouse_id' => $main, 'to_warehouse_id' => $to ?? $show, 'lines' => $lines], '/stock/transfers/create');
$tr([['variant_id' => $grey, 'batch_id' => $batch, 'qty' => 10]]);
check('roll moved: main 29.5, showroom 10, same batch', $bal($tA, $grey, $main, $batch) === 29.5 && $bal($tA, $grey, $show, $batch) === 10.0);
check('transfer wrote out/in ledger rows', (int)$val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND type IN ('transfer_out','transfer_in')", [$tA]) === 2);
$before = $ledgerRows($tA);
$tr([['variant_id' => $grey, 'batch_id' => $batch, 'qty' => 5], ['variant_id' => $tableV, 'qty' => 100]]);
check('failed transfer is all-or-nothing', $bal($tA, $grey, $main, $batch) === 29.5 && $ledgerRows($tA) === $before && $docs($tA, 'transfer') === 1);
$tr([['variant_id' => $grey, 'batch_id' => $batch, 'qty' => 1]], $main);
check('same-warehouse transfer refused', $docs($tA, 'transfer') === 1);
$tr([['variant_id' => $grey, 'batch_id' => 0, 'qty' => 1]]);
check('transfer of a batch item needs a batch', $docs($tA, 'transfer') === 1);

echo "Stock-take\n";
$a->post('/stock/takes', ['warehouse_id' => $main], '/stock/takes/create');
$take = (int)$val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake'", [$tA]);
$lines = DB::all('SELECT * FROM stock_doc_lines WHERE doc_id = ?', [$take]);
check('snapshot has the table and the roll', count($lines) === 2);
$counts = [];
foreach ($lines as $ln) $counts[$ln['id']] = (int)$ln['variant_id'] === $grey ? '28' : (string)(float)$ln['qty'];
$a->post("/stock/takes/$take", ['action' => 'post', 'counts' => $counts], "/stock/takes/$take");
check('stock-take corrected the roll to 28', $bal($tA, $grey, $main, $batch) === 28.0 && $bal($tA, $tableV, $main) === 4.0);
check('difference recorded as a stock-take movement of −1.5', (float)$val("SELECT qty_change FROM stock_ledger WHERE tenant_id = ? AND type = 'stocktake'", [$tA]) === -1.5);
$a->post("/stock/takes/$take", ['action' => 'post', 'counts' => array_map(fn($x) => '1', $counts)], "/stock/takes/$take");
check('a posted stock-take cannot be posted again', $bal($tA, $grey, $main, $batch) === 28.0);

echo "Batch history and status\n";
$page = $a->get("/stock/batches/$batch")['body'];
check('batch page shows its whole history', str_contains($page, 'Opening stock') && str_contains($page, 'Transfer out') && str_contains($page, 'Stock-take') && str_contains($page, 'LOT-A'));
check('batch is listed as in stock / partially used', str_contains($a->get('/stock/batches')['body'], $greySku . '-B0001') && str_contains($a->get('/stock/batches')['body'], 'Partially used'));
$adj([['variant_id' => $grey, 'qty' => -28, 'batch_id' => $batch]]);
$a->post('/stock/adjustments', ['warehouse_id' => $show, 'reason' => 'loss', 'lines' => [['variant_id' => $grey, 'qty' => -10, 'batch_id' => $batch]]], '/stock/adjustments/create');
check('roll fully used up', abs((float)$val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE batch_id = ?', [$batch])) < 0.0005);
check('finished roll hidden by default, shown under Finished', !str_contains($a->get('/stock/batches')['body'], $greySku . '-B0001') && str_contains($a->get('/stock/batches?status=finished')['body'], $greySku . '-B0001'));
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'found', 'lines' => [['variant_id' => $grey, 'qty' => 30, 'batch_id' => 'new', 'lot_no' => 'LOT-B']]], '/stock/adjustments/create');
check('second roll gets its own batch number', (int)$val('SELECT COUNT(*) FROM batches WHERE variant_id = ?', [$grey]) === 2 && $val('SELECT batch_no FROM batches WHERE variant_id = ? ORDER BY id DESC LIMIT 1', [$grey]) === $greySku . '-B0002');
check('ledger / stock / adjustments / transfers / takes pages load', array_reduce(['/stock', '/stock/ledger', '/stock/ledger?type=transfer_out&q=velvet', '/stock/adjustments', '/stock/adjustments/create', '/stock/transfers', '/stock/transfers/create', '/stock/takes', '/stock/takes/create', "/stock/takes/$take", '/warehouses', '/warehouses/create', '/items/create', "/items/$table/edit", "/items/$set/edit"], fn($ok, $p) => $ok && $a->get($p)['status'] === 200, true));
check('item lookup and batch options return JSON', str_contains($a->get('/stock/lookup?q=velv')['body'], 'Velvet') && str_contains($a->get("/stock/batch-options?variant=$grey&warehouse=$main")['body'], 'LOT-B'));

echo "Ledger always agrees with balances\n";
$bad = DB::all(
    'SELECT b.variant_id FROM stock_balances b
     LEFT JOIN (SELECT variant_id, warehouse_id, batch_id, SUM(qty_change) AS s FROM stock_ledger WHERE tenant_id = ? GROUP BY variant_id, warehouse_id, batch_id) l
       ON l.variant_id = b.variant_id AND l.warehouse_id = b.warehouse_id AND l.batch_id = b.batch_id
     WHERE b.tenant_id = ? AND ABS(b.qty - COALESCE(l.s, 0)) > 0.0005', [$tA, $tA]);
check('every balance equals the sum of its ledger rows', !$bad);
check('no negative balances', !DB::val('SELECT 1 FROM stock_balances WHERE tenant_id = ? AND qty < -0.0005', [$tA]));

echo "Delete rules\n";
$a->post("/items/$table/delete", [], '/items');
check('item with stock history cannot be deleted', (bool)$val('SELECT 1 FROM items WHERE id = ?', [$table]));
$a->post("/masters/categories/$catId/delete", [], '/masters/categories');
check('category in use cannot be deleted', (bool)$val('SELECT 1 FROM categories WHERE id = ?', [$catId]));
$a->post('/items', ['name' => 'Scratch', 'unit_id' => $unit($tA, 'Piece'), 'variants' => [['sku' => 'SCR-1']]], '/items/create');
$scratch = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Scratch'", [$tA]);
$a->post("/items/$scratch/delete", [], '/items');
check('unused item can be deleted', !$val('SELECT 1 FROM items WHERE id = ?', [$scratch]));
$defWh = $main;
$a->post("/warehouses/$defWh/delete", [], '/warehouses');
check('default warehouse cannot be deleted', (bool)$val('SELECT 1 FROM warehouses WHERE id = ?', [$defWh]));

echo "CSV export / import\n";
$exp = $a->get('/items/export');
check('export is a CSV with the items', $exp['status'] === 200 && str_contains($exp['headers'], 'text/csv') && str_contains($exp['body'], 'Velvet') && str_contains($exp['body'], 'TABLE-01'));
check('template downloads', str_contains($a->get('/items/import/template')['body'], 'item_name'));
$csv = function (string $content): CURLFile { $f = tempnam(sys_get_temp_dir(), 'csv'); file_put_contents($f, $content); return new CURLFile($f, 'text/csv', 'items.csv'); };
$hdr = "item_name,category,brand,unit,tax,track_batch,location,reorder_level,variant_name,sku,barcode,cost_price,sale_price\n";
$items0 = (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]);
$a->post('/items/import', ['file' => $csv($hdr . "Cotton,Decor,,Meter,,yes,,10,White,,,100,200\n,,,,,,,,Cream,,,100,200\nLamp,Decor,,Piece,GST 12,no,,0,,LAMP-1,,500,900\n")], '/items/import');
check('valid file imports 2 items', (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]) === $items0 + 2);
$cotton = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Cotton'", [$tA]);
check('blank item name adds a variant to the previous item', (int)$val('SELECT COUNT(*) FROM item_variants WHERE item_id = ?', [$cotton]) === 2 && (int)$val('SELECT track_batch FROM items WHERE id = ?', [$cotton]) === 1);
check('categories are created on the fly, tax matched by name', (bool)$val("SELECT 1 FROM categories WHERE tenant_id = ? AND name = 'Decor'", [$tA]) && (int)$val("SELECT tax_id FROM items WHERE tenant_id = ? AND name = 'Lamp'", [$tA]) === $taxId);
$n = (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]);
$a->post('/items/import', ['file' => $csv($hdr . "Good One,,,Piece,,no,,0,,,,1,2\nBad One,,,Furlong,,no,,0,,,,1,2\n")], '/items/import');
check('one bad row imports nothing', (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/items/import', ['file' => $csv($hdr . "Lamp,,,Piece,,no,,0,,,,1,2\n")], '/items/import');
check('existing item name is refused', (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/items/import', ['file' => $csv($hdr . "Evil,,,Piece,,no,,0,,LAMP-1,,1,2\n")], '/items/import');
check('duplicate SKU in file is refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Evil'", [$tA]));
$a->post('/items/import', ['file' => $csv($hdr . "=cmd|' /C calc'!A0,,,Piece,,no,,0,,,,1,2\n")], '/items/import');
check('formula-looking names are exported safely', str_contains($a->get('/items/export')['body'], "'=cmd") || !str_contains($a->get('/items/export')['body'], ",=cmd") );

echo "Permissions\n";
$staffRole = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Staff'", [$tA]);
$a->post('/users', ['name' => 'Staff', 'email' => "staff-$sfx@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users/create');
$s = new Client($base);
$s->post('/login', ['email' => "staff-$sfx@test.local", 'password' => 'Password123'], '/login');
check('staff can view items and stock', $s->get('/items')['status'] === 200 && $s->get('/stock')['status'] === 200 && $s->get("/stock/batches/$batch")['status'] === 200);
check('staff cannot add items or adjust stock (403)', $s->get('/items/create')['status'] === 403 && $s->get('/stock/adjustments/create')['status'] === 403 && $s->get('/warehouses/create')['status'] === 403);
$n = $ledgerRows($tA);
$s->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'found', 'lines' => [['variant_id' => $tableV, 'qty' => 5]]], '/stock');
check('staff POST to adjust is blocked', $ledgerRows($tA) === $n);

echo "Tenant isolation\n";
check("company B cannot open A's item / batch / category / warehouse (404)",
    $b->get("/items/$table")['status'] === 404 && $b->get("/stock/batches/$batch")['status'] === 404
    && $b->get("/masters/categories/$catId/edit")['status'] === 404 && $b->get("/warehouses/$main/edit")['status'] === 404);
$nB = $ledgerRows($tB);
$bMain = $wh($tB, 'Main Warehouse');
$b->post('/stock/adjustments', ['warehouse_id' => $bMain, 'reason' => 'found', 'lines' => [['variant_id' => $tableV, 'qty' => 5]]], '/stock/adjustments/create');
check("company B cannot add stock to A's item", $ledgerRows($tB) === $nB);
$b->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'found', 'lines' => [['variant_id' => $tableV, 'qty' => 5]]], '/stock/adjustments/create');
check("company B cannot use A's warehouse", $ledgerRows($tB) === $nB);
check("A's items are invisible to B in search and JSON", !str_contains($b->get('/stock/lookup?q=velv')['body'], 'Velvet') && trim($b->get("/stock/batch-options?variant=$grey")['body']) === '[]' && !str_contains($b->get('/items?q=Velvet')['body'], "items/$velvet"));
check("A's data untouched", $ledgerRows($tA) >= 10);

echo "Admin: database updates\n";
$sys = $admin->get('/admin/system');
check('system page loads and reports up to date', $sys['status'] === 200 && str_contains($sys['body'], 'up to date') && str_contains($sys['body'], '002_inventory.sql'));

echo "Record pages (Zoho-style) for stock documents and warehouses\n";
$adjId = (int)$val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'adjustment'", [$tA]);
$trId = (int)$val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer'", [$tA]);
$tkId = (int)$val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake'", [$tA]);
$h = $a->get("/stock/adjustments/$adjId")['body'];
check('adjustment page: shell, reason shown by its label, lines', str_contains($h, 'class="rec-top"') && str_contains($h, 'id="lines"') && str_contains($h, 'Stock adjustment') | str_contains($h, 'Adjustment') && !str_contains($h, '>damage<'));
$h = $a->get("/stock/transfers/$trId")['body'];
check('transfer page: shell with From and To warehouse as links', str_contains($h, 'class="rec-top"') && preg_match('~<span>To warehouse</span><b><a href="[^"]*warehouses/\d+"~', $h) === 1);
$h = $a->get("/stock/takes/$tkId")['body'];
check('stock-take page: shell and the count form still work', str_contains($h, 'class="rec-top"') && str_contains($h, 'name="counts[') | str_contains($h, 'Counted'));
$w = $a->get("/warehouses/$main");
check('warehouse page: shell, stats, racks, stock here, edit link', $w['status'] === 200 && str_contains($w['body'], 'class="rec-top"') && str_contains($w['body'], 'Total stock') && str_contains($w['body'], 'id="racks"') && str_contains($w['body'], 'What is stored here'));
check('warehouse list links to the page; unknown / other-company ids are 404', str_contains($a->get('/warehouses')['body'], "warehouses/$main\"") && $a->get('/warehouses/99999999')['status'] === 404 && $b->get("/warehouses/$main")['status'] === 404);
$locId = (int)$val('SELECT MAX(id) FROM locations WHERE tenant_id = ?', [$tA]);
if ($locId) { $l = $a->get("/locations/$locId"); check('rack page: shell, warehouse link, stock on the rack; other company gets 404', $l['status'] === 200 && str_contains($l['body'], 'class="rec-top"') && str_contains($l['body'], 'Stored on this') && $b->get("/locations/$locId")['status'] === 404); }

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
