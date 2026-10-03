<?php
declare(strict_types=1);

/**
 * End-to-end test for racks / locations (stock kept per rack).
 *   php tests/racks.php http://127.0.0.1:8099        # against a throw-away database
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\DB;

$sfx = bin2hex(random_bytes(3));
$sa = config('superadmin');
$admin = new Client($base);
$admin->post('/admin/login', ['email' => $sa['email'], 'password' => $sa['password']], '/admin/login');
$pro = (int)DB::val("SELECT id FROM plans WHERE slug = 'pro'");
$mk = function (string $name) use ($admin, $pro, $sfx, $base): array {
    $email = strtolower($name) . "-$sfx@test.local";
    $admin->post('/admin/tenants', ['name' => "$name $sfx", 'plan_id' => $pro, 'status' => 'active', 'subscription_ends_at' => '',
        'owner_name' => $name, 'owner_email' => $email, 'owner_password' => 'Password123'], '/admin/tenants/create');
    $c = new Client($base);
    $c->post('/login', ['email' => $email, 'password' => 'Password123'], '/login');
    return [$c, (int)DB::val('SELECT id FROM tenants WHERE name = ?', ["$name $sfx"])];
};
[$a, $tA] = $mk('Rackco');
[$b, $tB] = $mk('Other');

$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$unit = fn(int $t, string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$t, $n]);
$whId = fn(int $t, string $n) => (int)DB::val('SELECT id FROM warehouses WHERE tenant_id = ? AND name = ?', [$t, $n]);
$rack = fn(int $t, int $w, string $code) => (int)DB::val('SELECT id FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = ?', [$t, $w, $code]);
$bal = fn(int $t, int $v, int $w, int $batch, int $loc) => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ?', [$t, $v, $w, $batch, $loc]);
$ledger = fn(int $t) => (int)DB::val('SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ?', [$t]);

$main = $whId($tA, 'Main Warehouse');
$a->post('/warehouses', ['name' => 'Showroom', 'code' => 'SHW'], '/warehouses/create');
$show = $whId($tA, 'Showroom');
$mainB = $whId($tB, 'Main Warehouse');

echo "Creating racks\n";
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'single', 'code' => 'A-01', 'description' => 'Front left'], '/locations/create');
check('single rack created', $rack($tA, $main, 'A-01') > 0 && $val('SELECT description FROM locations WHERE id = ?', [$rack($tA, $main, 'A-01')]) === 'Front left');
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'single', 'code' => 'A-01'], '/locations/create');
check('duplicate code in the same warehouse refused', (int)$val('SELECT COUNT(*) FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = ?', [$tA, $main, 'A-01']) === 1);
$a->post('/locations', ['warehouse_id' => $show, 'mode' => 'single', 'code' => 'A-01'], '/locations/create');
check('same code is fine in another warehouse', $rack($tA, $show, 'A-01') > 0);
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'range', 'prefix' => 'b-', 'from' => 1, 'to' => 3, 'pad' => 2], '/locations/create');
check('range creates B-01 … B-03 (upper-cased)', $rack($tA, $main, 'B-01') > 0 && $rack($tA, $main, 'B-02') > 0 && $rack($tA, $main, 'B-03') > 0);
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'range', 'prefix' => 'B-', 'from' => 2, 'to' => 4, 'pad' => 2], '/locations/create');
check('overlapping range only adds the new rack', (int)$val("SELECT COUNT(*) FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code LIKE 'B-%'", [$tA, $main]) === 4);
$n = (int)$val('SELECT COUNT(*) FROM locations WHERE tenant_id = ?', [$tA]);
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'range', 'prefix' => 'Z', 'from' => 1, 'to' => 500, 'pad' => 3], '/locations/create');
check('a range over 200 is refused', (int)$val('SELECT COUNT(*) FROM locations WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/locations', ['warehouse_id' => $main, 'mode' => 'single', 'code' => 'c 1'], '/locations/create');
check('spaces in a code become dashes', $rack($tA, $main, 'C-1') > 0);
$b->post('/locations', ['warehouse_id' => $mainB, 'mode' => 'single', 'code' => 'X-1'], '/locations/create');
$bRack = $rack($tB, $mainB, 'X-1');
check('company B has its own rack', $bRack > 0);
$a->post('/locations', ['warehouse_id' => $mainB, 'mode' => 'single', 'code' => 'HACK'], '/locations/create');
check("cannot create a rack in another company's warehouse", !$val("SELECT 1 FROM locations WHERE code = 'HACK'"));
check('rack list and form pages load', $a->get('/locations')['status'] === 200 && $a->get('/locations/create')['status'] === 200 && str_contains($a->get('/locations?warehouse=' . $main)['body'], 'B-03'));

[$A1, $B1, $B2, $B3] = [$rack($tA, $main, 'A-01'), $rack($tA, $main, 'B-01'), $rack($tA, $main, 'B-02'), $rack($tA, $main, 'B-03')];
$S1 = $rack($tA, $show, 'A-01');

echo "Items\n";
$a->post('/items', ['name' => 'Side Table', 'unit_id' => $unit($tA, 'Piece'), 'variants' => [['sku' => 'TBL']]], '/items/create');
$a->post('/items', ['name' => 'Velvet', 'unit_id' => $unit($tA, 'Meter'), 'track_batch' => 1, 'variants' => [['sku' => 'VEL']]], '/items/create');
$tbl = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'TBL'", [$tA]);
$vel = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'VEL'", [$tA]);

echo "Putting stock on racks\n";
$adj = fn(int $w, array $lines, string $reason = 'found') => $a->post('/stock/adjustments', ['warehouse_id' => $w, 'reason' => $reason, 'lines' => $lines], '/stock/adjustments/create');
$adj($main, [
    ['variant_id' => $tbl, 'qty' => 6, 'location_id' => $A1],
    ['variant_id' => $tbl, 'qty' => 4, 'location_id' => 0],
    ['variant_id' => $tbl, 'qty' => 5, 'location_id' => $B1],
    ['variant_id' => $vel, 'qty' => 50, 'batch_id' => 'new', 'lot_no' => 'L1', 'location_id' => $B2],
], 'opening');
$roll = (int)$val('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $vel]);
check('table stock is split over racks (6 / 4 no rack / 5)', $bal($tA, $tbl, $main, 0, $A1) === 6.0 && $bal($tA, $tbl, $main, 0, 0) === 4.0 && $bal($tA, $tbl, $main, 0, $B1) === 5.0);
check('the roll sits on rack B-02', $bal($tA, $vel, $main, $roll, $B2) === 50.0);
check('ledger remembers the rack', (int)$val('SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND location_id = ?', [$tA, $A1]) === 1);
check('warehouse total still adds up (15)', (float)$val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ?', [$tA, $tbl, $main]) === 15.0);

echo "Rack rules\n";
$before = $ledger($tA);
$r = $adj($main, [['variant_id' => $tbl, 'qty' => -5, 'location_id' => $B3]]);
$page = $a->follow($r)['body'];
check('cannot take stock from a rack that has none', $ledger($tA) === $before && str_contains($page, 'Not enough stock') && str_contains($page, 'B-03'));
$r = $adj($main, [['variant_id' => $tbl, 'qty' => -7, 'location_id' => $A1]]);
check('message mentions stock on other racks', str_contains($a->follow($r)['body'], 'other racks') && $bal($tA, $tbl, $main, 0, $A1) === 6.0);
$adj($main, [['variant_id' => $tbl, 'qty' => -4, 'location_id' => $A1]]);
check('remove from the right rack works (A-01: 2 left, others unchanged)', $bal($tA, $tbl, $main, 0, $A1) === 2.0 && $bal($tA, $tbl, $main, 0, $B1) === 5.0);
$n = $ledger($tA);
$adj($main, [['variant_id' => $tbl, 'qty' => 1, 'location_id' => $S1]]);
check("a rack of another warehouse is refused", $ledger($tA) === $n);
$adj($main, [['variant_id' => $tbl, 'qty' => 1, 'location_id' => $bRack]]);
check("another company's rack is refused", $ledger($tA) === $n);
$adj($main, [['variant_id' => $tbl, 'qty' => 1, 'location_id' => 999999]]);
check('an unknown rack is refused', $ledger($tA) === $n);
$a->post("/locations/$B3", ['code' => 'B-03', 'description' => '', 'is_active' => ''], "/locations/$B3/edit");
check('rack can be marked inactive', (int)$val('SELECT is_active FROM locations WHERE id = ?', [$B3]) === 0);
$adj($main, [['variant_id' => $tbl, 'qty' => 1, 'location_id' => $B3]]);
check('no new stock into an inactive rack', $ledger($tA) === $n);
$a->post("/locations/$B3", ['code' => 'B-03', 'description' => '', 'is_active' => '1'], "/locations/$B3/edit");

echo "Moving between racks\n";
$tr = fn(int $from, int $to, array $lines) => $a->post('/stock/transfers', ['warehouse_id' => $from, 'to_warehouse_id' => $to, 'lines' => $lines], '/stock/transfers/create');
$tr($main, $main, [['variant_id' => $vel, 'batch_id' => $roll, 'qty' => 50, 'location_id' => $B2, 'to_location_id' => $B3]]);
check('roll moved B-02 → B-03 inside the warehouse', $bal($tA, $vel, $main, $roll, $B2) === 0.0 && $bal($tA, $vel, $main, $roll, $B3) === 50.0);
check('it is still the same batch, nothing lost', (float)$val('SELECT SUM(qty) FROM stock_balances WHERE batch_id = ?', [$roll]) === 50.0);
$n = $ledger($tA);
$tr($main, $main, [['variant_id' => $vel, 'batch_id' => $roll, 'qty' => 5, 'location_id' => $B3, 'to_location_id' => $B3]]);
check('moving to the same rack is refused', $ledger($tA) === $n);
$tr($main, $main, [['variant_id' => $vel, 'batch_id' => $roll, 'qty' => 5, 'location_id' => $B2, 'to_location_id' => $B1]]);
check('moving from a rack that is empty is refused', $ledger($tA) === $n);
$tr($main, $show, [['variant_id' => $tbl, 'qty' => 5, 'location_id' => $B1, 'to_location_id' => $S1]]);
check('stock moved to a rack in another warehouse', $bal($tA, $tbl, $show, 0, $S1) === 5.0 && $bal($tA, $tbl, $main, 0, $B1) === 0.0);
$n = $ledger($tA);
$tr($main, $show, [['variant_id' => $tbl, 'qty' => 1, 'location_id' => 0, 'to_location_id' => $B1]]);
check("destination rack must belong to the destination warehouse", $ledger($tA) === $n);
$tr($main, $show, [['variant_id' => $tbl, 'qty' => 3, 'location_id' => 0, 'to_location_id' => 0]]);
check('stock with no rack can move (to no rack)', $bal($tA, $tbl, $show, 0, 0) === 3.0 && $bal($tA, $tbl, $main, 0, 0) === 1.0);

echo "Seeing where things are\n";
$pl = json_decode($a->get("/stock/placement?variant=$tbl&warehouse=$main")['body'], true);
check('placement lists racks, fullest first', is_array($pl) && (int)$pl[0]['location_id'] === $A1 && (float)$pl[0]['qty'] === 2.0 && count($pl) === 2);
$pl = json_decode($a->get("/stock/placement?variant=$vel&warehouse=$main&batch=$roll")['body'], true);
check('placement for one roll shows its rack', count($pl) === 1 && $pl[0]['code'] === 'B-03');
$racks = $a->get('/stock/racks')['body'];
check('Stock by rack lists items on racks', str_contains($racks, 'A-01') && str_contains($racks, 'B-03') && str_contains($racks, 'Velvet') && str_contains($racks, 'Side Table'));
check('search by rack code', str_contains($a->get('/stock/racks?q=B-03')['body'], 'Velvet') && !str_contains($a->get('/stock/racks?q=B-03')['body'], 'Side Table'));
$nr = $a->get('/stock/racks?q=-')['body'];
check('"no rack" filter shows only unassigned stock', str_contains($nr, 'No rack') && !str_contains($nr, 'Velvet'));
check('search by item shows its racks', str_contains($a->get('/stock/racks?q=velvet')['body'], 'B-03'));
$item = $a->get("/items/" . $val('SELECT item_id FROM item_variants WHERE id = ?', [$tbl]))['body'];
check('item page says where it is kept', str_contains($item, 'Kept at') && str_contains($item, 'A-01') && str_contains($item, 'Showroom'));
check('stock page shows rack badges', str_contains($a->get('/stock')['body'], 'A-01: 2'));
check('batch page shows the rack and its moves', str_contains($a->get("/stock/batches/$roll")['body'], 'B-03') && str_contains($a->get("/stock/batches/$roll")['body'], 'B-02'));
check('ledger shows and searches racks', str_contains($a->get('/stock/ledger?q=B-03')['body'], 'B-03'));
check('rack list warns about stock without a rack', str_contains($a->get('/locations')['body'], 'no rack'));
$doc = (int)$val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' ORDER BY id LIMIT 1", [$tA]);
check('transfer document shows from → to racks', str_contains($a->get("/stock/transfers/$doc")['body'], 'B-02') && str_contains($a->get("/stock/transfers/$doc")['body'], 'B-03'));

echo "Stock-take per rack\n";
$a->post('/stock/takes', ['warehouse_id' => $main], '/stock/takes/create');
$take = (int)$val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake'", [$tA]);
$lines = DB::all('SELECT * FROM stock_doc_lines WHERE doc_id = ?', [$take]);
check('snapshot is per rack (table A-01, table no-rack, roll B-03)', count($lines) === 3);
$counts = [];
foreach ($lines as $ln) $counts[$ln['id']] = (int)$ln['location_id'] === $A1 ? '1' : (string)(float)$ln['qty'];
$a->post("/stock/takes/$take", ['action' => 'post', 'counts' => $counts], "/stock/takes/$take");
check('only the miscounted rack was corrected (A-01: 2 → 1)', $bal($tA, $tbl, $main, 0, $A1) === 1.0 && $bal($tA, $tbl, $main, 0, 0) === 1.0 && $bal($tA, $vel, $main, $roll, $B3) === 50.0);
check('stock-take page shows racks', str_contains($a->get("/stock/takes/$take")['body'], 'A-01'));

echo "Deleting racks\n";
$a->post("/locations/$A1/delete", [], '/locations');
check('rack with history cannot be deleted', (bool)$val('SELECT 1 FROM locations WHERE id = ?', [$A1]));
$C1 = $rack($tA, $main, 'C-1');
$a->post("/locations/$C1/delete", [], '/locations');
check('unused rack can be deleted', !$val('SELECT 1 FROM locations WHERE id = ?', [$C1]));

echo "Ledger always agrees with balances (per rack)\n";
$bad = DB::all(
    'SELECT b.variant_id FROM stock_balances b
     LEFT JOIN (SELECT variant_id, warehouse_id, batch_id, location_id, SUM(qty_change) AS s FROM stock_ledger WHERE tenant_id = ? GROUP BY variant_id, warehouse_id, batch_id, location_id) l
       ON l.variant_id = b.variant_id AND l.warehouse_id = b.warehouse_id AND l.batch_id = b.batch_id AND l.location_id = b.location_id
     WHERE b.tenant_id = ? AND ABS(b.qty - COALESCE(l.s, 0)) > 0.0005', [$tA, $tA]);
check('every rack balance equals the sum of its ledger rows', !$bad);
check('no negative balances', !DB::val('SELECT 1 FROM stock_balances WHERE tenant_id = ? AND qty < -0.0005', [$tA]));

echo "Permissions and isolation\n";
$staffRole = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Staff'", [$tA]);
$a->post('/users', ['name' => 'Staff', 'email' => "staff-$sfx@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users/create');
$s = new Client($base);
$s->post('/login', ['email' => "staff-$sfx@test.local", 'password' => 'Password123'], '/login');
check('staff can see racks and where stock is', $s->get('/locations')['status'] === 200 && $s->get('/stock/racks')['status'] === 200);
check('staff cannot add racks (403)', $s->get('/locations/create')['status'] === 403 && $s->get("/locations/$A1/edit")['status'] === 403);
$n = (int)$val('SELECT COUNT(*) FROM locations WHERE tenant_id = ?', [$tA]);
$s->post('/locations', ['warehouse_id' => $main, 'mode' => 'single', 'code' => 'STAFF'], '/locations');
check('staff POST is blocked', (int)$val('SELECT COUNT(*) FROM locations WHERE tenant_id = ?', [$tA]) === $n);
check("company B cannot open or change A's racks", $b->get("/locations/$A1/edit")['status'] === 404);
$b->post("/locations/$A1/delete", [], '/locations');
check("company B cannot delete A's rack", (bool)$val('SELECT 1 FROM locations WHERE id = ?', [$A1]));
check("B's rack list and stock-by-rack show nothing of A", !str_contains($b->get('/locations')['body'], 'B-03') && !str_contains($b->get('/stock/racks')['body'], 'Velvet'));
check("placement endpoint reveals nothing across companies", trim($b->get("/stock/placement?variant=$vel&warehouse=$main")['body']) === '[]');

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
