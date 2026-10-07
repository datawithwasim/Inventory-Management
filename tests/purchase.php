<?php
declare(strict_types=1);

/**
 * End-to-end test for Phase 3 (suppliers, requisitions, purchase orders, goods receipts, bills, returns).
 *   php tests/purchase.php http://127.0.0.1:8099        # against a throw-away database
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
[$a, $tA] = $mk('Buyco');
[$b, $tB] = $mk('Other');

$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$unit = fn(string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$GLOBALS['tA'], $n]);
$bal = fn(int $v, int $w, int $batch, int $loc) => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ?', [$GLOBALS['tA'], $v, $w, $batch, $loc]);
$ledger = fn() => (int)DB::val('SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ?', [$GLOBALS['tA']]);
$count = fn(string $table) => (int)DB::val("SELECT COUNT(*) FROM `$table` WHERE tenant_id = ?", [$GLOBALS['tA']]);
$near = fn($x, $y, $eps = 0.011) => abs((float)$x - (float)$y) < $eps;
$owed = fn(int $supplier) => (float)DB::val('SELECT COALESCE(SUM(total - returned_amount - paid_amount),0) FROM purchase_bills WHERE tenant_id = ? AND supplier_id = ?', [$GLOBALS['tA'], $supplier]);

$main = (int)$val("SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1", [$tA]);
$a->post('/warehouses', ['name' => 'Showroom', 'code' => 'SHW'], '/warehouses/create');
$show = (int)$val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Showroom'", [$tA]);
foreach ([[$main, 'A-01'], [$main, 'B-01'], [$show, 'S-01']] as [$w, $code]) DB::insert('locations', ['tenant_id' => $tA, 'warehouse_id' => $w, 'code' => $code]);
$rackId = fn(int $w, string $c) => (int)DB::val('SELECT id FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = ?', [$GLOBALS['tA'], $w, $c]);
[$A1, $B1, $S1] = [$rackId($main, 'A-01'), $rackId($main, 'B-01'), $rackId($show, 'S-01')];

$a->post('/masters/taxes', ['name' => 'GST 12', 'rate' => '12'], '/masters/taxes/create');
$tax = (int)$val("SELECT id FROM taxes WHERE tenant_id = ? AND name = 'GST 12'", [$tA]);
$a->post('/items', ['name' => 'Velvet', 'unit_id' => $unit('Meter'), 'tax_id' => $tax, 'track_batch' => 1, 'variants' => [['sku' => 'VEL', 'cost_price' => 400]]], '/items/create');
$a->post('/items', ['name' => 'Side Table', 'unit_id' => $unit('Piece'), 'tax_id' => $tax, 'reorder_level' => 5, 'reorder_qty' => 12, 'variants' => [['sku' => 'TBL', 'cost_price' => 2500]]], '/items/create');
$vel = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'VEL'", [$tA]);
$tbl = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'TBL'", [$tA]);

echo "Suppliers\n";
$a->post('/suppliers', ['name' => 'Shree Textiles', 'contact_person' => 'Mr Shah', 'phone' => '99999', 'email' => 'shree@example.com', 'payment_terms_days' => 30], '/suppliers/create');
$sup = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Shree Textiles'", [$tA]);
check('supplier created with payment terms', $sup > 0 && (int)$val('SELECT payment_terms_days FROM suppliers WHERE id = ?', [$sup]) === 30);
$a->post('/suppliers', ['name' => 'Shree Textiles'], '/suppliers/create');
check('duplicate supplier name refused', (int)$val("SELECT COUNT(*) FROM suppliers WHERE tenant_id = ? AND name = 'Shree Textiles'", [$tA]) === 1);
$a->post('/suppliers', ['name' => 'Bad Email', 'email' => 'nope'], '/suppliers/create');
check('invalid email refused', !$val("SELECT 1 FROM suppliers WHERE tenant_id = ? AND name = 'Bad Email'", [$tA]));
$a->post('/suppliers', ['name' => 'Wood Works'], '/suppliers/create');
$sup2 = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Wood Works'", [$tA]);
check('supplier pages load', $a->get('/suppliers')['status'] === 200 && $a->get("/suppliers/$sup")['status'] === 200 && $a->get("/suppliers/$sup/edit")['status'] === 200 && str_contains($a->get('/suppliers?q=shree')['body'], 'Shree Textiles'));

echo "Requisition\n";
$page = $a->get('/purchase/requisitions/create?low=1')['body'];
check('low-stock requisition is pre-filled with the table (reorder qty 12)', str_contains($page, 'Side Table') && str_contains($page, '"qty":12'));
$a->post('/purchase/requisitions', ['note' => 'Festival stock', 'lines' => [['variant_id' => $tbl, 'qty' => 12, 'note' => 'low'], ['variant_id' => $vel, 'qty' => 80]]], '/purchase/requisitions/create');
$req = (int)$val('SELECT id FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
check('requisition saved with 2 lines', $req > 0 && (int)$val('SELECT COUNT(*) FROM purchase_requisition_items WHERE requisition_id = ?', [$req]) === 2);
$a->post('/purchase/requisitions', ['lines' => [['variant_id' => $tbl, 'qty' => 1.5]]], '/purchase/requisitions/create');
check('decimal quantity for whole-number item refused', (int)$val('SELECT COUNT(*) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]) === 1);
$a->post("/purchase/requisitions/$req/convert", ['supplier_id' => $sup, 'warehouse_id' => $main], "/purchase/requisitions/$req");
$poFromReq = (int)$val("SELECT po_id FROM purchase_requisitions WHERE id = ?", [$req]);
check('converted into a draft PO', $poFromReq > 0 && $val('SELECT status FROM purchase_orders WHERE id = ?', [$poFromReq]) === 'draft' && $val('SELECT status FROM purchase_requisitions WHERE id = ?', [$req]) === 'converted');
check('PO took the item cost and tax as defaults', $near($val('SELECT unit_price FROM purchase_order_items WHERE po_id = ? AND variant_id = ?', [$poFromReq, $tbl]), 2500) && $near($val('SELECT tax_rate FROM purchase_order_items WHERE po_id = ? AND variant_id = ?', [$poFromReq, $tbl]), 12));
$a->post("/purchase/requisitions/$req/convert", ['supplier_id' => $sup, 'warehouse_id' => $main], "/purchase/requisitions/$req");
check('a converted requisition cannot be converted twice', $count('purchase_orders') === 1);
check('requisition pages load', $a->get('/purchase/requisitions')['status'] === 200 && $a->get("/purchase/requisitions/$req")['status'] === 200);

echo "Purchase orders\n";
$po = fn(array $lines, array $over = []) => $a->post('/purchase/orders', $over + ['supplier_id' => $sup, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => $lines], '/purchase/orders/create');
$n0 = $count('purchase_orders');
$po([['variant_id' => $vel, 'qty' => 100, 'unit_price' => 400, 'tax_rate' => 12], ['variant_id' => $tbl, 'qty' => 10, 'unit_price' => 2500, 'tax_rate' => 12]]);
$po1 = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('PO saved as draft with its number', $count('purchase_orders') === $n0 + 1 && $val('SELECT status FROM purchase_orders WHERE id = ?', [$po1]) === 'draft' && str_starts_with((string)$val('SELECT po_no FROM purchase_orders WHERE id = ?', [$po1]), 'PO-'));
check('PO totals: 65000 + 7800 tax = 72800', $near($val('SELECT subtotal FROM purchase_orders WHERE id = ?', [$po1]), 65000) && $near($val('SELECT tax_total FROM purchase_orders WHERE id = ?', [$po1]), 7800) && $near($val('SELECT total FROM purchase_orders WHERE id = ?', [$po1]), 72800));
$n = $count('purchase_orders');
$po([['variant_id' => $vel, 'qty' => 1, 'unit_price' => 1], ['variant_id' => $vel, 'qty' => 2, 'unit_price' => 1]]);
check('same item twice refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 0, 'unit_price' => 1]]);
check('zero quantity refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 2, 'unit_price' => -5]]);
check('negative price refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 2, 'unit_price' => 5, 'tax_rate' => 150]]);
check('tax above 100% refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 2, 'unit_price' => 5]], ['supplier_id' => 999999]);
check('unknown supplier refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 2, 'unit_price' => 5]], ['expected_date' => '2000-01-01']);
check('expected date before order date refused', $count('purchase_orders') === $n);
$po([['variant_id' => $tbl, 'qty' => 2, 'unit_price' => 5]], ['order_date' => '2026-02-30']);
check('impossible date refused', $count('purchase_orders') === $n);

$a->post("/purchase/orders/$po1", ['supplier_id' => $sup, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 100, 'unit_price' => 400, 'tax_rate' => 12], ['variant_id' => $tbl, 'qty' => 10, 'unit_price' => 2500, 'tax_rate' => 12]], 'notes' => 'edited'], "/purchase/orders/$po1/edit");
check('draft PO can be edited', $val('SELECT notes FROM purchase_orders WHERE id = ?', [$po1]) === 'edited');
$n = $ledger();
$a->post('/purchase/grns', ['po_id' => $po1, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'lines' => [['po_item_id' => 1, 'qty' => 1]]], "/purchase/orders/$po1");
check('cannot receive against a draft PO', $ledger() === $n && $count('grns') === 0);
check('PO pages load', $a->get('/purchase/orders')['status'] === 200 && $a->get("/purchase/orders/$po1")['status'] === 200 && $a->get("/purchase/orders/$po1/edit")['status'] === 200 && $a->get('/purchase/orders/create?supplier=' . $sup)['status'] === 200);

echo "Approval\n";
$a->post("/purchase/orders/$po1/submit", [], "/purchase/orders/$po1");
check('without approval setting a submitted PO is confirmed straight away', $val('SELECT status FROM purchase_orders WHERE id = ?', [$po1]) === 'approved' && $val('SELECT approved_by FROM purchase_orders WHERE id = ?', [$po1]) > 0);
$a->post("/purchase/orders/$po1", ['supplier_id' => $sup, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $tbl, 'qty' => 1, 'unit_price' => 1]]], "/purchase/orders/$po1");
check('an approved PO can no longer be edited', (int)$val('SELECT COUNT(*) FROM purchase_order_items WHERE po_id = ?', [$po1]) === 2);

$a->post('/purchase/orders/approval-setting', ['po_approval' => 1], '/purchase/orders');
check('approval can be switched on', $val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'po_approval'", [$tA]) === '1');
$po([['variant_id' => $tbl, 'qty' => 4, 'unit_price' => 2500, 'tax_rate' => 12]]);
$poA = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$a->post("/purchase/orders/$poA/submit", [], "/purchase/orders/$poA");
check('with approval on, submit waits for approval', $val('SELECT status FROM purchase_orders WHERE id = ?', [$poA]) === 'pending_approval');
$staffRole = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Staff'", [$tA]);
$a->post('/users', ['name' => 'Staff', 'email' => "staff-$sfx@test.local", 'password' => 'Password123', 'role_id' => $staffRole], '/users/create');
$s = new Client($base);
$s->post('/login', ['email' => "staff-$sfx@test.local", 'password' => 'Password123'], '/login');
$s->post("/purchase/orders/$poA/approve", [], '/purchase/orders');
check('staff cannot approve', $val('SELECT status FROM purchase_orders WHERE id = ?', [$poA]) === 'pending_approval');
$a->post("/purchase/orders/$poA/reject", [], "/purchase/orders/$poA");
check('approver can send it back to draft', $val('SELECT status FROM purchase_orders WHERE id = ?', [$poA]) === 'draft');
$a->post("/purchase/orders/$poA/submit", [], "/purchase/orders/$poA");
$a->post("/purchase/orders/$poA/approve", [], "/purchase/orders/$poA");
check('approver approves', $val('SELECT status FROM purchase_orders WHERE id = ?', [$poA]) === 'approved');
$a->post("/purchase/orders/$poA/cancel", [], "/purchase/orders/$poA");
check('an approved PO with no receipts can be cancelled', $val('SELECT status FROM purchase_orders WHERE id = ?', [$poA]) === 'cancelled');
$a->post('/purchase/orders/approval-setting', [], '/purchase/orders');
check('approval can be switched off', $val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'po_approval'", [$tA]) === '0');
$po([['variant_id' => $tbl, 'qty' => 1, 'unit_price' => 1]]);
$poD = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$a->post("/purchase/orders/$poD/delete", [], '/purchase/orders');
check('a draft PO can be deleted', !$val('SELECT 1 FROM purchase_orders WHERE id = ?', [$poD]));

echo "Receiving goods (rolls become batches, on racks)\n";
check('receive form loads for an approved PO', $a->get("/purchase/orders/$po1/receive")['status'] === 200 && str_contains($a->get("/purchase/orders/$po1/receive")['body'], 'Velvet'));
$items1 = DB::all('SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id', [$po1]);
$piVel = (int)$items1[0]['id'];
$piTbl = (int)$items1[1]['id'];
$grn = fn(int $poId, array $lines, array $over = []) => $a->post('/purchase/grns', $over + ['po_id' => $poId, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'lines' => $lines], "/purchase/orders/$poId/receive");

$n = $ledger(); $g0 = $count('grns');
$r = $grn($po1, [['po_item_id' => $piVel, 'qty' => 50, 'lot_no' => 'L1', 'location_id' => $B1], ['po_item_id' => $piTbl, 'qty' => 10, 'location_id' => $S1]]);
check('a rack of another warehouse stops the whole receipt', $ledger() === $n && $count('grns') === $g0 && (float)$val('SELECT qty_received FROM purchase_order_items WHERE id = ?', [$piVel]) === 0.0);
$grn($po1, [['po_item_id' => $piVel, 'qty' => 99999, 'lot_no' => 'X']]);
check('receiving far more than ordered is refused', $ledger() === $n);
$grn($po1, [['po_item_id' => $piTbl, 'qty' => 2.5]]);
check('whole-number item refuses decimals on receipt', $ledger() === $n);
$grn($po1, [['po_item_id' => 987654, 'qty' => 1]]);
check('a line that is not on the PO is refused', $ledger() === $n);
$grn($po1, [['po_item_id' => $piTbl, 'qty' => 0]]);
check('zero received refused', $ledger() === $n);

$grn($po1, [
    ['po_item_id' => $piVel, 'qty' => 50, 'lot_no' => 'L1', 'location_id' => $B1],
    ['po_item_id' => $piVel, 'qty' => 52, 'lot_no' => 'L2', 'location_id' => $A1],
    ['po_item_id' => $piTbl, 'qty' => 10, 'location_id' => $A1],
], ['supplier_ref' => 'CH-77', 'extra_cost' => 650, 'extra_cost_note' => 'Freight']);
$g1 = (int)$val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('receipt created and numbered', $g1 > 0 && str_starts_with((string)$val('SELECT grn_no FROM grns WHERE id = ?', [$g1]), 'GRN-'));
$rolls = DB::all('SELECT * FROM batches WHERE tenant_id = ? AND variant_id = ? ORDER BY id', [$tA, $vel]);
check('each roll became its own batch (50 m and 52 m)', count($rolls) === 2 && $near($rolls[0]['received_qty'], 50) && $near($rolls[1]['received_qty'], 52));
check('supplier lots are kept on the batches', $rolls[0]['supplier_lot'] === 'L1' && $rolls[1]['supplier_lot'] === 'L2');
check('rolls are on the racks chosen (B-01 / A-01)', $bal($vel, $main, (int)$rolls[0]['id'], $B1) === 50.0 && $bal($vel, $main, (int)$rolls[1]['id'], $A1) === 52.0);
check('table stock 10 on A-01', $bal($tbl, $main, 0, $A1) === 10.0);
check('PO fully received (102 m is within the 10% tolerance)', $val('SELECT status FROM purchase_orders WHERE id = ?', [$po1]) === 'received' && $near($val('SELECT qty_received FROM purchase_order_items WHERE id = ?', [$piVel]), 102));
$gi = DB::all('SELECT * FROM grn_items WHERE grn_id = ? ORDER BY id', [$g1]);
$spread = 0.0;
foreach ($gi as $row) $spread += ((float)$row['landed_unit_cost'] - (float)$row['unit_price']) * (float)$row['qty'];
check('freight 650 is spread into stock cost (by value)', $near($spread, 650, 0.05) && (float)$gi[0]['landed_unit_cost'] > 400 && $near($gi[2]['landed_unit_cost'] - 2500, 650 * 25000 / 65800 / 10, 0.01));
check('ledger rows are "purchase" with the landed cost and rack', (int)$val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND type = 'purchase' AND ref_type = 'grn' AND ref_id = ? AND unit_cost > 0", [$tA, $g1]) === 3 && (int)$val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND ref_id = ? AND location_id = ?", [$tA, $g1, $A1]) === 2);
check("batch cost is the landed cost", $near($rolls[0]['unit_cost'], $gi[0]['landed_unit_cost'], 0.01));
$n = $ledger();
$grn($po1, [['po_item_id' => $piTbl, 'qty' => 1]]);
check('a fully received PO accepts no more goods', $ledger() === $n);
check('receipt, stock-by-rack and batch pages show the new roll', $a->get("/purchase/grns/$g1")['status'] === 200 && str_contains($a->get("/purchase/grns/$g1")['body'], $rolls[0]['batch_no']) && str_contains($a->get('/stock/racks?q=L')['body'] . $a->get('/stock/batches')['body'], $rolls[1]['batch_no']) && $a->get('/purchase/grns')['status'] === 200);

echo "Partial receipts and closing\n";
$po([['variant_id' => $vel, 'qty' => 100, 'unit_price' => 410, 'tax_rate' => 12]]);
$po2 = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$a->post("/purchase/orders/$po2/submit", [], "/purchase/orders/$po2");
$pi2 = (int)$val('SELECT id FROM purchase_order_items WHERE po_id = ?', [$po2]);
$grn($po2, [['po_item_id' => $pi2, 'qty' => 40, 'lot_no' => 'M1', 'location_id' => $B1]]);
check('partial receipt → PO "partial", 60 still due', $val('SELECT status FROM purchase_orders WHERE id = ?', [$po2]) === 'partial' && $near($val('SELECT qty_received FROM purchase_order_items WHERE id = ?', [$pi2]), 40));
$n = $ledger();
$grn($po2, [['po_item_id' => $pi2, 'qty' => 67, 'lot_no' => 'M2']]);
check('over the 10% tolerance (67 > 60 + 10%) is refused', $ledger() === $n);
$a->post("/purchase/orders/$po2/cancel", [], "/purchase/orders/$po2");
check('a PO with receipts cannot be cancelled', $val('SELECT status FROM purchase_orders WHERE id = ?', [$po2]) === 'partial');
$a->post("/purchase/orders/$po2/close", [], "/purchase/orders/$po2");
check('a partly received PO can be closed', $val('SELECT status FROM purchase_orders WHERE id = ?', [$po2]) === 'closed');
$grn($po2, [['po_item_id' => $pi2, 'qty' => 5]]);
check('a closed PO accepts no more goods', $ledger() === $n);

echo "Receiving without a PO\n";
check('direct receipt form loads', $a->get('/purchase/grns/create')['status'] === 200);
$a->post('/purchase/grns', ['supplier_id' => $sup2, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'W-1', 'lines' => [['variant_id' => $tbl, 'qty' => 5, 'unit_price' => 2400, 'tax_rate' => 12, 'location_id' => $B1]]], '/purchase/grns/create');
$g2 = (int)$val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('direct receipt adds stock without a PO', $g2 !== $g1 && $val('SELECT po_id FROM grns WHERE id = ?', [$g2]) === null && $bal($tbl, $main, 0, $B1) === 5.0);
$n = $ledger();
$a->post('/purchase/grns', ['supplier_id' => $sup2, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'lines' => [['variant_id' => $tbl, 'qty' => 5, 'unit_price' => 2400], ['variant_id' => $vel, 'qty' => 5, 'unit_price' => 400, 'location_id' => $S1]]], '/purchase/grns/create');
check('a bad line in a direct receipt cancels the whole receipt', $ledger() === $n);

echo "Bills and payments\n";
$a->post("/purchase/grns/$g1/bill", [], "/purchase/grns/$g1");
$bill = (int)$val('SELECT id FROM purchase_bills WHERE grn_id = ?', [$g1]);
check('bill created from the receipt: goods 65800 + tax 7896 = 73696', $bill > 0 && $near($val('SELECT subtotal FROM purchase_bills WHERE id = ?', [$bill]), 65800) && $near($val('SELECT total FROM purchase_bills WHERE id = ?', [$bill]), 73696));
check("supplier's challan no. and 30-day due date carried over", $val('SELECT supplier_bill_no FROM purchase_bills WHERE id = ?', [$bill]) === 'CH-77' && $val('SELECT due_date FROM purchase_bills WHERE id = ?', [$bill]) === date('Y-m-d', strtotime('+30 days')));
$a->post("/purchase/grns/$g1/bill", [], "/purchase/grns/$g1");
check('one bill per receipt', (int)$val('SELECT COUNT(*) FROM purchase_bills WHERE grn_id = ?', [$g1]) === 1);
$pay = fn(int $id, array $d) => $a->post("/purchase/bills/$id/payments", $d + ['paid_on' => date('Y-m-d'), 'method' => 'bank'], "/purchase/bills/$id");
$pay($bill, ['amount' => 30000, 'reference' => 'UTR1']);
check('part payment → partly paid', $near($val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$bill]), 30000) && str_contains($a->get("/purchase/bills/$bill")['body'], 'Partly paid'));
$pay($bill, ['amount' => 99999]);
check('paying more than the balance is refused', $near($val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$bill]), 30000));
$pay($bill, ['amount' => 0]);
$pay($bill, ['amount' => 100, 'method' => 'barter']);
check('zero amount and unknown method refused', $near($val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$bill]), 30000));
check('supplier owes 43696', $near($owed($sup), 43696));
check('bills list: unpaid filter excludes it, partial includes it', !str_contains($a->get('/purchase/bills?status=unpaid')['body'], 'PB-0001') && str_contains($a->get('/purchase/bills?status=partial')['body'], $val('SELECT bill_no FROM purchase_bills WHERE id = ?', [$bill])));
$pay($bill, ['amount' => 43696]);
check('paid in full → Paid', str_contains($a->get("/purchase/bills/$bill")['body'], 'Paid') && $near($owed($sup), 0));
$payId = (int)$val('SELECT id FROM supplier_payments WHERE bill_id = ? ORDER BY id DESC LIMIT 1', [$bill]);
$a->post("/purchase/bills/$bill/payments/$payId/delete", [], "/purchase/bills/$bill");
check('removing a payment re-opens the balance', $near($val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$bill]), 30000));
$a->post("/purchase/bills/$bill", ['supplier_bill_no' => 'INV-9', 'bill_date' => date('Y-m-d', strtotime('-40 days')), 'due_date' => date('Y-m-d', strtotime('-10 days')), 'other_charges' => 500], "/purchase/bills/$bill/edit");
check('bill edited: other charges 500 added to the total', $near($val('SELECT total FROM purchase_bills WHERE id = ?', [$bill]), 74196) && $val('SELECT supplier_bill_no FROM purchase_bills WHERE id = ?', [$bill]) === 'INV-9');
check('overdue filter lists it', str_contains($a->get('/purchase/bills?status=overdue')['body'], $val('SELECT bill_no FROM purchase_bills WHERE id = ?', [$bill])));
check('bill pages load', $a->get('/purchase/bills')['status'] === 200 && $a->get("/purchase/bills/$bill/edit")['status'] === 200);

echo "Returns to the supplier\n";
check('return pages load', $a->get('/purchase/returns')['status'] === 200 && $a->get('/purchase/returns/create')['status'] === 200 && $a->get("/purchase/returns/create?grn=$g1")['status'] === 200);
$giVel1 = (int)$gi[0]['id'];
$giTbl = (int)$gi[2]['id'];
$ret = fn(int $g, array $lines, array $over = []) => $a->post('/purchase/returns', $over + ['grn_id' => $g, 'return_date' => date('Y-m-d'), 'reason' => 'damaged', 'lines' => $lines], "/purchase/returns/create?grn=$g");
$n = $ledger(); $r0 = $count('purchase_returns');
$ret($g1, [$giVel1 => ['qty' => 60, 'location_id' => $B1]]);
check('cannot return more than was received', $ledger() === $n && $count('purchase_returns') === $r0);
$ret($g1, [$giVel1 => ['qty' => 10, 'location_id' => $A1]]);
check('cannot take the roll from a rack it is not on', $ledger() === $n && $count('purchase_returns') === $r0);
$ret($g1, []);
check('a return needs at least one quantity', $count('purchase_returns') === $r0);
$ret($g1, [$giTbl => ['qty' => 1.5, 'location_id' => $A1]]);
check('whole-number item refuses decimals on return', $count('purchase_returns') === $r0);
$ret($g1, [$giVel1 => ['qty' => 10, 'location_id' => $B1], $giTbl => ['qty' => 3, 'location_id' => $A1]]);
$rt = (int)$val('SELECT MAX(id) FROM purchase_returns WHERE tenant_id = ?', [$tA]);
check('return posted and numbered', $count('purchase_returns') === $r0 + 1 && str_starts_with((string)$val('SELECT return_no FROM purchase_returns WHERE id = ?', [$rt]), 'PRT-'));
check('roll balance 50 → 40, table 10 → 7', $bal($vel, $main, (int)$rolls[0]['id'], $B1) === 40.0 && $bal($tbl, $main, 0, $A1) === 7.0);
check('return value = 10×400×1.12 + 3×2500×1.12 = 12880', $near($val('SELECT total FROM purchase_returns WHERE id = ?', [$rt]), 12880));
check('bill reduced by the return; linked to the bill', $near($val('SELECT returned_amount FROM purchase_bills WHERE id = ?', [$bill]), 12880) && (int)$val('SELECT bill_id FROM purchase_returns WHERE id = ?', [$rt]) === $bill);
check('returned quantities are tracked on the receipt', $near($val('SELECT qty_returned FROM grn_items WHERE id = ?', [$giVel1]), 10) && $near($val('SELECT qty_returned FROM grn_items WHERE id = ?', [$giTbl]), 3));
check('ledger rows are "purchase_return" (negative)', (int)$val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND type = 'purchase_return' AND qty_change < 0", [$tA]) === 2);
$ret($g1, [$giVel1 => ['qty' => 45, 'location_id' => $B1]]);
check('only what is left can be returned (40 left, 40 returnable → 45 refused)', $count('purchase_returns') === $r0 + 1);
// use up part of the roll elsewhere, then try to return more than is physically left
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'damage', 'lines' => [['variant_id' => $vel, 'qty' => -35, 'batch_id' => (int)$rolls[0]['id'], 'location_id' => $B1]]], '/stock/adjustments/create');
$ret($g1, [$giVel1 => ['qty' => 20, 'location_id' => $B1]]);
check('cannot return stock that is no longer there (5 m left)', $count('purchase_returns') === $r0 + 1 && $bal($vel, $main, (int)$rolls[0]['id'], $B1) === 5.0);
$ret($g2, [(int)$val('SELECT id FROM grn_items WHERE grn_id = ?', [$g2]) => ['qty' => 2, 'location_id' => $B1]]);
$rt2 = (int)$val('SELECT MAX(id) FROM purchase_returns WHERE tenant_id = ?', [$tA]);
check('return before billing is picked up when the bill is made later', $rt2 !== $rt && !$val('SELECT bill_id FROM purchase_returns WHERE id = ?', [$rt2]));
$a->post("/purchase/grns/$g2/bill", [], "/purchase/grns/$g2");
$bill2 = (int)$val('SELECT id FROM purchase_bills WHERE grn_id = ?', [$g2]);
check('later bill already deducts the earlier return (5×2400×1.12 − 2×2400×1.12)', $near($val('SELECT total FROM purchase_bills WHERE id = ?', [$bill2]), 13440) && $near($val('SELECT returned_amount FROM purchase_bills WHERE id = ?', [$bill2]), 5376) && (int)$val('SELECT bill_id FROM purchase_returns WHERE id = ?', [$rt2]) === $bill2);
$pay($bill2, ['amount' => 8064]);
check('and can be settled in full for the remaining 8064', str_contains($a->get("/purchase/bills/$bill2")['body'], 'Paid'));
check('return detail page loads', $a->get("/purchase/returns/$rt")['status'] === 200 && str_contains($a->get("/purchase/returns/$rt")['body'], 'damaged'));

echo "Stock stays consistent\n";
$bad = DB::all(
    'SELECT b.variant_id FROM stock_balances b
     LEFT JOIN (SELECT variant_id, warehouse_id, batch_id, location_id, SUM(qty_change) AS s FROM stock_ledger WHERE tenant_id = ? GROUP BY variant_id, warehouse_id, batch_id, location_id) l
       ON l.variant_id = b.variant_id AND l.warehouse_id = b.warehouse_id AND l.batch_id = b.batch_id AND l.location_id = b.location_id
     WHERE b.tenant_id = ? AND ABS(b.qty - COALESCE(l.s, 0)) > 0.0005', [$tA, $tA]);
check('every balance equals the sum of its ledger rows', !$bad);
check('supplier page shows what we owe', $a->get("/suppliers/$sup2")['status'] === 200);

echo "Permissions\n";
check('staff can view purchase lists and documents', $s->get('/purchase/orders')['status'] === 200 && $s->get("/purchase/orders/$po1")['status'] === 200 && $s->get('/purchase/bills')['status'] === 200 && $s->get('/suppliers')['status'] === 200 && $s->get("/purchase/grns/$g1")['status'] === 200);
check('staff cannot create or receive (403)', $s->get('/purchase/orders/create')['status'] === 403 && $s->get('/purchase/grns/create')['status'] === 403 && $s->get('/purchase/returns/create')['status'] === 403 && $s->get('/suppliers/create')['status'] === 403 && $s->get('/purchase/requisitions/create')['status'] === 403);
$n = $count('supplier_payments');
$s->post("/purchase/bills/$bill/payments", ['amount' => 1, 'paid_on' => date('Y-m-d'), 'method' => 'cash'], '/purchase/bills');
check('staff cannot record payments', $count('supplier_payments') === $n);
$s->post('/purchase/orders/approval-setting', ['po_approval' => 1], '/purchase/orders');
check('staff cannot change the approval setting', $val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'po_approval'", [$tA]) === '0');

echo "Tenant isolation\n";
check("company B cannot open A's supplier / PO / receipt / bill / requisition / return (404)",
    $b->get("/suppliers/$sup")['status'] === 404 && $b->get("/purchase/orders/$po1")['status'] === 404 && $b->get("/purchase/grns/$g1")['status'] === 404
    && $b->get("/purchase/bills/$bill")['status'] === 404 && $b->get("/purchase/requisitions/$req")['status'] === 404 && $b->get("/purchase/returns/$rt")['status'] === 404);
$bMain = (int)$val("SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1", [$tB]);
$nPo = (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tB]);
$b->post('/purchase/orders', ['supplier_id' => $sup, 'warehouse_id' => $bMain, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $tbl, 'qty' => 1, 'unit_price' => 1]]], '/purchase/orders/create');
check("B cannot create a PO with A's supplier or items", (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tB]) === $nPo);
$nL = $ledger();
$b->post('/purchase/grns', ['po_id' => $po2, 'warehouse_id' => $bMain, 'received_date' => date('Y-m-d'), 'lines' => [['po_item_id' => $pi2, 'qty' => 1]]], '/purchase/grns/create');
check("B cannot receive against A's PO", $ledger() === $nL && (int)$val('SELECT COUNT(*) FROM grns WHERE tenant_id = ?', [$tB]) === 0);
$b->post("/purchase/bills/$bill/payments", ['amount' => 1, 'paid_on' => date('Y-m-d'), 'method' => 'cash'], '/purchase/bills');
check("B cannot pay A's bill", $near($val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$bill]), 30000));
check("item lookup shows nothing of A to B", !str_contains($b->get('/lookup/items?q=velvet')['body'], 'Velvet') && str_contains($a->get('/lookup/items?q=velvet')['body'], 'Velvet'));
$b->post("/suppliers/$sup/delete", [], '/suppliers');
check("B cannot delete A's supplier", (bool)$val('SELECT 1 FROM suppliers WHERE id = ?', [$sup]));
$a->post("/suppliers/$sup/delete", [], '/suppliers');
check('a supplier with purchase history cannot be deleted', (bool)$val('SELECT 1 FROM suppliers WHERE id = ?', [$sup]));
$a->post('/suppliers', ['name' => 'Temp Supplier'], '/suppliers/create');
$tmp = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Temp Supplier'", [$tA]);
$a->post("/suppliers/$tmp/delete", [], '/suppliers');
check('an unused supplier can be deleted', !$val('SELECT 1 FROM suppliers WHERE id = ?', [$tmp]));

echo "Record pages (Zoho-style) for purchase documents\n";
$rp = fn(string $table, string $path, string $nofield) => (function () use ($a, $val, $tA, $table, $path) { $id = (int)$val("SELECT MAX(id) FROM `$table` WHERE tenant_id = ?", [$tA]); return [$id, $a->get("/$path/$id")['body']]; })();
[$id, $h] = $rp('purchase_orders', 'purchase/orders', '');
$t = preg_replace('/\s+/', ' ', strip_tags($h));
check('PO page: record header, summary (supplier, dates), sections, items, timeline tab and notes', str_contains($h, 'class="rec-top"') && str_contains($h, 'rec-summary') && str_contains($h, 'Purchase order information') && str_contains($h, 'id="items"') && str_contains($h, 'data-tab="recTimeline"') && str_contains($h, 'id="notes"'));
check('PO page: the action buttons are still there (print / edit / submit …)', str_contains($h, '/print') && preg_match('/Confirm order|Submit for approval|Approve|Receive goods/', $h) === 1);
check('PO page: supplier is a link inside the sections', preg_match('~<span>Supplier</span><b><a href="[^"]*suppliers/\d+"~', $h) === 1);
[$id, $h] = $rp('grns', 'purchase/grns', '');
check('GRN page: shell, sections (received on, challan), supplier / PO links, items', str_contains($h, 'class="rec-top"') && str_contains($h, 'Goods receipt information') && str_contains($h, 'id="items"') && str_contains($h, 'Received by'));
[$id, $h] = $rp('purchase_bills', 'purchase/bills', '');
check('bill page: shell, amounts card, payments and the payment form', str_contains($h, 'class="rec-top"') && str_contains($h, 'id="totals"') && str_contains($h, 'id="payments"') && str_contains($h, 'Balance due'));
[$id, $h] = $rp('purchase_returns', 'purchase/returns', '');
check('purchase return page: shell + items', str_contains($h, 'class="rec-top"') && str_contains($h, 'id="items"') && str_contains($h, 'Total (incl. tax)'));
[$id, $h] = $rp('purchase_requisitions', 'purchase/requisitions', '');
if ($id) check('requisition page: shell, status badge, items, layout link', str_contains($h, 'class="rec-top"') && str_contains($h, 'id="items"') && str_contains($h, 'Edit page layout'));
check('documents with a layout offer "Edit page layout" in the menu', str_contains($a->get('/purchase/orders/' . (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]))['body'], 'Edit page layout'));
$pid = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$a->post("/records/purchase_order/$pid/notes", ['body' => 'Chase the mill on Monday'], "/purchase/orders/$pid");
check('a note can be added on a purchase order and shows with the Timeline tab', str_contains(preg_replace('/\s+/', ' ', strip_tags($a->get("/purchase/orders/$pid")['body'])), 'Chase the mill on Monday'));
$b->post("/records/purchase_order/$pid/notes", ['body' => 'intruder'], '/suppliers');
check("another company cannot add a note to A's purchase order", !$val("SELECT 1 FROM record_notes WHERE body = 'intruder'"));
foreach (['purchase/orders', 'purchase/grns', 'purchase/bills', 'purchase/returns', 'purchase/requisitions'] as $pth) {
    $tb = ['purchase/orders' => 'purchase_orders', 'purchase/grns' => 'grns', 'purchase/bills' => 'purchase_bills', 'purchase/returns' => 'purchase_returns', 'purchase/requisitions' => 'purchase_requisitions'][$pth];
    $id = (int)$val("SELECT MAX(id) FROM `$tb` WHERE tenant_id = ?", [$tA]);
    if ($id) check("$pth/$id renders without notices and one h1", ($r = $a->get("/$pth/$id"))['status'] === 200 && !preg_match('/Warning:|Notice:|Fatal error|Deprecated:/', $r['body']) && substr_count($r['body'], '<h1') === 1);
}

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
