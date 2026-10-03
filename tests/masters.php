<?php
declare(strict_types=1);

/** Supplier master, home-furnishing item attributes and supplier-wise rate lists.  php tests/masters.php http://127.0.0.1:8099 */
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
    $admin->post('/admin/tenants', ['name' => "$name $sfx", 'plan_id' => $pro, 'status' => 'active', 'subscription_ends_at' => '', 'owner_name' => $name, 'owner_email' => $email, 'owner_password' => 'Password123'], '/admin/tenants/create');
    $c = new Client($base);
    $c->post('/login', ['email' => $email, 'password' => 'Password123'], '/login');
    return [$c, (int)DB::val('SELECT id FROM tenants WHERE name = ?', ["$name $sfx"])];
};
[$a, $tA] = $mk('Mastr');
[$b, $tB] = $mk('Rival');
$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$unit = (int)$val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]);

echo "Supplier master\n";
$a->post('/suppliers', ['name' => 'Surat Mills', 'supplier_type' => 'manufacturer', 'phone' => '9999911111', 'tax_no' => '24ABCDE1234F1Z5', 'pan' => 'abcde1234f', 'city' => 'Surat', 'state' => 'Gujarat', 'pincode' => '395003',
    'ship_address' => 'Plot 4, Udhna', 'bank_name' => 'SBI', 'bank_account' => '1234567890', 'bank_ifsc' => 'sbin0001234', 'credit_limit' => '50000', 'lead_time_days' => '7', 'transport' => 'VRL', 'payment_terms_days' => '30',
    'contacts' => [['name' => 'Ramesh', 'role' => 'Dispatch', 'phone' => '9888800000', 'email' => 'r@x.com'], ['name' => '', 'role' => '', 'phone' => '', 'email' => ''], ['name' => 'Accounts', 'email' => 'acc@x.com']]], '/suppliers/create');
$s1 = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Surat Mills'", [$tA]);
$row = DB::one('SELECT * FROM suppliers WHERE id = ?', [$s1]);
echo ($s1 ? '' : "  (supplier not saved)\n");
check('every new supplier field is saved (type, GST/PAN, place, bank, limits, transport)', $s1 && $row['supplier_type'] === 'manufacturer' && $row['pan'] === 'ABCDE1234F' && $row['city'] === 'Surat' && $row['state'] === 'Gujarat' && $row['bank_ifsc'] === 'SBIN0001234'
    && (float)$row['credit_limit'] === 50000.0 && (int)$row['lead_time_days'] === 7 && $row['transport'] === 'VRL' && $row['ship_address'] === 'Plot 4, Udhna');
check('extra contacts saved, blank row skipped', (int)$val('SELECT COUNT(*) FROM supplier_contacts WHERE supplier_id = ?', [$s1]) === 2);
$show = $a->get("/suppliers/$s1")['body'];
check('supplier page shows type, place, bank, extra contacts and credit limit', str_contains($show, 'Manufacturer') && str_contains($show, 'Surat') && str_contains($show, 'SBI') && str_contains($show, 'Dispatch') && str_contains($show, 'Credit limit'));
$edit = $a->get("/suppliers/$s1/edit")['body'];
check('edit form shows saved values and the contact rows', str_contains($edit, 'value="Surat"') && str_contains($edit, 'value="Ramesh"') && str_contains($edit, 'name="bank_ifsc"'));
$a->post("/suppliers/$s1", ['name' => 'Surat Mills', 'supplier_type' => 'manufacturer', 'city' => 'Surat', 'is_active' => 1, 'contacts' => [['name' => 'Only One']]], "/suppliers/$s1/edit");
check('editing replaces the contact list', (int)$val('SELECT COUNT(*) FROM supplier_contacts WHERE supplier_id = ?', [$s1]) === 1);
$a->post('/suppliers', ['name' => 'Bad Type', 'supplier_type' => 'wizard'], '/suppliers/create');
$a->post('/suppliers', ['name' => 'Bad Limit', 'credit_limit' => '-5'], '/suppliers/create');
$a->post('/suppliers', ['name' => 'Bad Contact', 'contacts' => [['name' => 'X', 'email' => 'nope']]], '/suppliers/create');
check('bad type / negative limit / bad contact email are refused', !$val("SELECT 1 FROM suppliers WHERE tenant_id = ? AND name IN ('Bad Type','Bad Limit','Bad Contact')", [$tA]));
$a->post('/suppliers', ['name' => 'Delhi Traders', 'supplier_type' => 'trader'], '/suppliers/create');
$s2 = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Delhi Traders'", [$tA]);

echo "Item master\n";
$a->post('/items', ['name' => 'Royal Velvet', 'item_type' => 'fabric', 'unit_id' => $unit, 'hsn_code' => '5407', 'design_no' => 'RV-12', 'composition' => '100% polyester', 'width' => '54 in', 'gsm' => '280', 'pattern' => 'Plain', 'finish' => 'Soft',
    'track_batch' => 1, 'variants' => [['name' => 'Wine', 'colour' => 'Wine', 'sku' => 'RVW', 'cost_price' => 300], ['name' => 'Teal', 'colour' => 'Teal', 'size' => '54 in', 'sku' => 'RVT', 'cost_price' => 310]]], '/items/create');
$i1 = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Royal Velvet'", [$tA]);
$it = DB::one('SELECT * FROM items WHERE id = ?', [$i1]);
check('item type and fabric attributes saved', $i1 && $it['item_type'] === 'fabric' && $it['hsn_code'] === '5407' && $it['design_no'] === 'RV-12' && $it['composition'] === '100% polyester' && $it['width'] === '54 in' && $it['gsm'] === '280');
check('variant colour and size saved', $val("SELECT colour FROM item_variants WHERE tenant_id = ? AND sku = 'RVT'", [$tA]) === 'Teal' && $val("SELECT size FROM item_variants WHERE tenant_id = ? AND sku = 'RVT'", [$tA]) === '54 in');
$ish = $a->get("/items/$i1")['body'];
check('item page shows type, design no., composition and colours', str_contains($ish, 'Fabric') && str_contains($ish, 'RV-12') && str_contains($ish, '100% polyester') && str_contains($ish, 'Wine'));
$f = $a->get('/items/create')['body'];
check('form has the product-type picker with all five kinds and the attribute fields', substr_count($f, '<option value="wallpaper"') === 1 && str_contains($f, 'value="carpet"') && str_contains($f, 'value="linen"') && str_contains($f, 'name="gsm"') && str_contains($f, 'name="variants[0][colour]"'));
check('items can be found by design number or colour', str_contains($a->get('/items?q=RV-12')['body'], 'Royal Velvet') && str_contains($a->get('/items?q=Teal')['body'], 'Royal Velvet'));
$a->post('/items', ['name' => 'Bad Type Item', 'item_type' => 'unicorn', 'unit_id' => $unit, 'variants' => [['sku' => 'BT1']]], '/items/create');
check('an unknown product type is refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Bad Type Item'", [$tA]));
$a->post('/items', ['name' => 'Floral Wallpaper', 'item_type' => 'wallpaper', 'unit_id' => $unit, 'width' => '53 cm', 'variants' => [['sku' => 'WP1', 'cost_price' => 900]]], '/items/create');
check('a wallpaper saves too', $val("SELECT item_type FROM items WHERE tenant_id = ? AND name = 'Floral Wallpaper'", [$tA]) === 'wallpaper');
check('item form can hide an attribute through the designer settings', (function () use ($a, $tA) {
    $a->post('/settings/formdesign', ['payload' => json_encode(['item' => ['fields' => ['pattern' => ['show' => false, 'w' => '', 'help' => '']], 'sections' => [['title' => '', 'cols' => 0, 'fields' => ['name']]]]])], '/settings/modules');
    $ok = !str_contains($a->get('/items/create')['body'], 'name="pattern"');
    $a->post('/settings/formdesign', ['payload' => json_encode(['item' => ['reset' => true]])], '/settings/modules');
    return $ok && str_contains($a->get('/items/create')['body'], 'name="pattern"');
})());

echo "Supplier rate list\n";
$v1 = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'RVW'", [$tA]);
$v2 = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'RVT'", [$tA]);
$a->post("/suppliers/$s1/rates", ['variant_id' => $v1, 'rate' => '280', 'discount_pct' => '5', 'min_qty' => '100', 'valid_from' => '2026-01-01', 'supplier_code' => 'D-77'], "/suppliers/$s1");
$a->post("/suppliers/$s2/rates", ['variant_id' => $v1, 'rate' => '290', 'valid_from' => '2026-01-01'], "/suppliers/$s2");
check('rates saved per supplier (same item, two suppliers)', (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE tenant_id = ? AND variant_id = ?', [$tA, $v1]) === 2);
$cur = App\Models\Purchase::class;
check('net rate = rate less discount', abs(280 * 0.95 - 266.0) < 0.001 && (float)$val('SELECT ROUND(rate * (1 - discount_pct / 100), 2) FROM supplier_rates WHERE supplier_id = ?', [$s1]) === 266.0);
$a->post("/suppliers/$s1/rates", ['variant_id' => $v1, 'rate' => '300', 'valid_from' => '2026-06-01'], "/suppliers/$s1");
$rows = DB::all('SELECT rate, valid_from, valid_to FROM supplier_rates WHERE supplier_id = ? AND variant_id = ? ORDER BY valid_from', [$s1, $v1]);
check('a newer rate closes the older one (history kept)', count($rows) === 2 && $rows[0]['valid_to'] === '2026-05-31' && $rows[1]['valid_to'] === null);
$a->post("/suppliers/$s1/rates", ['variant_id' => $v1, 'rate' => '305', 'valid_from' => '2026-06-01'], "/suppliers/$s1");
check('same item + same start date replaces instead of duplicating', (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE supplier_id = ? AND variant_id = ?', [$s1, $v1]) === 2 && (float)$val('SELECT rate FROM supplier_rates WHERE supplier_id = ? AND variant_id = ? AND valid_from = ?', [$s1, $v1, '2026-06-01']) === 305.0);
$sp = $a->get("/suppliers/$s1")['body'];
check('supplier page lists current and history rates', str_contains($sp, 'Current') && str_contains($sp, 'History') && str_contains($sp, 'D-77') && str_contains($sp, 'RVW'));
foreach ([['rate' => '-1'], ['rate' => 'abc'], ['rate' => '10', 'discount_pct' => '120'], ['rate' => '10', 'valid_from' => '2026-02-30'], ['rate' => '10', 'valid_from' => '2026-05-01', 'valid_to' => '2026-04-01'], ['rate' => '10', 'min_qty' => '-3'], ['rate' => '10', 'lead_time_days' => '999']] as $bad) {
    $a->post("/suppliers/$s1/rates", $bad + ['variant_id' => $v2, 'valid_from' => '2026-03-01'], "/suppliers/$s1");
}
check('bad rates (negative, text, discount > 100, bad dates, negative min qty, huge lead time) are refused', !$val('SELECT 1 FROM supplier_rates WHERE variant_id = ?', [$v2]));
$a->post("/suppliers/$s1/rates", ['variant_id' => 999999, 'rate' => '5'], "/suppliers/$s1");
check('a made-up item is refused', !$val('SELECT 1 FROM supplier_rates WHERE variant_id = 999999'));
$b->post("/suppliers/$s1/rates", ['variant_id' => $v2, 'rate' => '1'], '/suppliers');
check("another company cannot touch A's supplier rates", !$val('SELECT 1 FROM supplier_rates WHERE variant_id = ?', [$v2]));
$rid = (int)$val('SELECT id FROM supplier_rates WHERE supplier_id = ? ORDER BY id LIMIT 1', [$s1]);
$b->post("/suppliers/$s1/rates/$rid/delete", [], '/suppliers');
check("…or delete them", (bool)$val('SELECT 1 FROM supplier_rates WHERE id = ?', [$rid]));

// lookup used by the purchase forms
$today = date('Y-m-d');
$l = json_decode($a->get("/lookup/items?q=RVW&supplier=$s1")['body'], true);
check('item lookup carries the supplier rate (net of discount) for the chosen supplier', ($l[0]['rate'] ?? null) !== null && (float)$l[0]['rate'] === 305.0 && str_contains((string)($l[0]['rate_note'] ?? ''), 'Supplier rate'));
$l2 = json_decode($a->get("/lookup/items?q=RVW&supplier=$s2")['body'], true);
check('a different supplier gives its own rate', (float)($l2[0]['rate'] ?? 0) === 290.0);
$l3 = json_decode($a->get('/lookup/items?q=RVW')['body'], true);
check('without a supplier there is no rate (item cost is used)', !isset($l3[0]['rate']));
$l4 = json_decode($a->get("/lookup/items?variant=$v1&supplier=$s1")['body'], true);
check('lookup by variant id works (used when the supplier changes)', count($l4) === 1 && (float)$l4[0]['rate'] === 305.0);
$l5 = json_decode($b->get("/lookup/items?variant=$v1&supplier=$s1")['body'], true);
check("another company sees nothing of A's items or rates", $l5 === []);
// discount applied
$a->post("/suppliers/$s2/rates", ['variant_id' => $v2, 'rate' => '200', 'discount_pct' => '10', 'valid_from' => '2026-01-01'], "/suppliers/$s2");
$l6 = json_decode($a->get("/lookup/items?variant=$v2&supplier=$s2")['body'], true);
check('discount is applied in the lookup (200 less 10% = 180)', (float)($l6[0]['rate'] ?? 0) === 180.0);
// last paid
DB::run("INSERT INTO grns (tenant_id, grn_no, supplier_id, warehouse_id, received_date) SELECT ?, 'G-T1', ?, id, '2026-04-01' FROM warehouses WHERE tenant_id = ? LIMIT 1", [$tA, $s1, $tA]);
$g = (int)DB::val("SELECT id FROM grns WHERE tenant_id = ? AND grn_no = 'G-T1'", [$tA]);
$cols = DB::all('SHOW COLUMNS FROM grn_items');
DB::run('INSERT INTO grn_items (tenant_id, grn_id, variant_id, qty, unit_price, tax_rate) VALUES (?,?,?,?,?,?)', [$tA, $g, $v1, 40, 275, 5]);
$l7 = json_decode($a->get("/lookup/items?variant=$v1&supplier=$s1")['body'], true);
check('lookup also reports what we last paid this supplier', (float)($l7[0]['last_paid'] ?? 0) === 275.0 && str_contains((string)($l7[0]['last_note'] ?? ''), 'Last bought'));

// item page comparison
$ish = $a->get("/items/$i1")['body'];
check('item page compares suppliers and flags the lowest', str_contains($ish, 'Supplier rates') && str_contains($ish, 'Surat Mills') && str_contains($ish, 'Delhi Traders') && str_contains($ish, 'Lowest'));

// CSV
$tpl = $a->get("/suppliers/$s1/rates/template");
check('CSV template downloads', $tpl['status'] === 200 && str_contains($tpl['body'], 'sku,rate,discount_pct'));
$csv = tempnam(sys_get_temp_dir(), 'rt') . '.csv';
file_put_contents($csv, "sku,rate,discount_pct,min_qty,lead_time_days,supplier_code,valid_from\nRVT,310,2,50,5,TT-9,2026-02-01\nNOPE,1,,,,,\nRVW,abc,,,,,\nWP1,880,,,,,\n");
$a->post("/suppliers/$s1/rates/import", ['file' => new CURLFile($csv, 'text/csv', 'r.csv')], "/suppliers/$s1");
check('CSV import adds the good rows (2) and skips unknown SKU / bad rate', (float)$val('SELECT rate FROM supplier_rates WHERE supplier_id = ? AND variant_id = ?', [$s1, $v2]) === 310.0
    && (float)$val("SELECT r.rate FROM supplier_rates r JOIN item_variants v ON v.id = r.variant_id WHERE r.supplier_id = ? AND v.sku = 'WP1'", [$s1]) === 880.0 && (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE supplier_id = ?', [$s1]) === 4);
$noHead = tempnam(sys_get_temp_dir(), 'rt') . '.csv'; file_put_contents($noHead, "foo,bar\n1,2\n");
$a->post("/suppliers/$s1/rates/import", ['file' => new CURLFile($noHead, 'text/csv', 'x.csv')], "/suppliers/$s1");
check('a CSV without sku/rate columns is refused', (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE supplier_id = ?', [$s1]) === 4);

// delete
$a->post("/suppliers/$s1/rates/$rid/delete", [], "/suppliers/$s1");
check('a rate can be removed', !$val('SELECT 1 FROM supplier_rates WHERE id = ?', [$rid]));
check('old address of the custom-fields page and settings still work for the owner', $a->get('/settings/formdesign?form=supplier')['status'] === 200);
$d = $a->get('/settings/formdesign?form=supplier')['body'];
check('the designer knows the new supplier and item fields', str_contains($d, 'bank_ifsc') && str_contains($d, 'credit_limit') && str_contains($a->get('/settings/formdesign?form=item')['body'], 'design_no'));

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
