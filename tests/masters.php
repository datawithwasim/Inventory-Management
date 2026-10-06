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
check('supplier page lists current rates and keeps earlier ones behind the toggle', str_contains($sp, 'rl-old') && str_contains($sp, 'Show earlier rates') && str_contains($sp, 'id="rateModal"') && str_contains($sp, 'D-77') && str_contains($sp, 'RVW'));
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

echo "Purchase reports\n";
$txt = fn(string $html) => preg_replace('/\s+/', ' ', strip_tags($html));
$p = $txt($a->get('/reports/rate-comparison')['body']);
check('rate comparison lists suppliers side by side and marks the lowest', str_contains($p, 'Royal Velvet') && str_contains($p, 'Surat Mills') && str_contains($p, 'Delhi Traders') && str_contains($p, 'Lowest') && str_contains($p, '% ('));
check('rate comparison: a single-supplier item says so', str_contains($p, 'Only supplier'));
$p = $a->get("/reports/rate-comparison/export/csv?supplier=$s2")['body'];
check('rate comparison filtered to one supplier hides the others', str_contains($p, 'Delhi Traders') && !str_contains($p, 'Surat Mills'));
$p = $txt($a->get('/reports/rate-comparison?q=wallpaper')['body']);
check('rate comparison search works', str_contains($p, 'Floral Wallpaper') && !str_contains($p, 'Royal Velvet'));
$a->post("/suppliers/$s2/rates", ['variant_id' => $v1, 'rate' => '320', 'valid_from' => '2026-07-01'], "/suppliers/$s2");
$p = $txt($a->get("/reports/rate-history?supplier=$s2&q=RVW")['body']);
check('rate history shows first rate and the rise (290 → 320 = +10.3%)', str_contains($p, 'First rate') && str_contains($p, '+10.3%'));
check('rate history shows the closed period', str_contains($p, '30 Jun 2026') || str_contains($p, '2026-06-30') || str_contains($p, '30-06-2026') || str_contains($p, '30/06/2026'));
$p = $a->get('/reports/supplier-dues/export/csv?from=2026-01-01&to=2026-12-31')['body'];
check('supplier dues report opens; supplier with no bills and nothing owed is left out', str_contains($txt($a->get('/reports/supplier-dues')['body']), 'Supplier purchases') && !str_contains($p, 'Delhi Traders'));
// give Surat Mills a bill so it has dues
$wh = (int)DB::val('SELECT warehouse_id FROM grns WHERE id = ?', [$g]);
DB::run("INSERT INTO purchase_bills (tenant_id, bill_no, supplier_id, grn_id, bill_date, due_date, subtotal, tax_total, total, paid_amount, returned_amount) VALUES (?, 'PB-T1', ?, ?, '2026-04-02', '2026-04-20', 10000, 500, 10500, 2500, 0)", [$tA, $s1, $g]);
$p = $a->get('/reports/supplier-dues/export/csv?from=2026-01-01&to=2026-12-31')['body'];
check('supplier dues: purchased 10,500, owe 8,000, all overdue', str_contains($p, '"Surat Mills","Manufacturer / mill",Surat,1,10500,8000,8000'));
$a->post('/suppliers/' . $s1, ['name' => 'Surat Mills', 'credit_limit' => '50000', 'is_active' => 1], "/suppliers/$s1/edit");
check('credit limit use shown as 16%', str_contains($a->get('/reports/supplier-dues/export/csv?from=2026-01-01&to=2026-12-31')['body'], '16%'));
$a->post('/suppliers/' . $s1, ['name' => 'Surat Mills', 'credit_limit' => '5000', 'is_active' => 1], "/suppliers/$s1/edit");
$p = $txt($a->get('/reports/supplier-dues?from=2026-01-01&to=2026-12-31&status=due')['body']);
check('supplier dues: over the credit limit is flagged', str_contains($p, 'over limit'));
check('only-where-we-owe filter works and outside-period purchases show 0 bills', str_contains($txt($a->get('/reports/supplier-dues?from=2025-01-01&to=2025-02-01&status=due')['body']), '8,000.00'));
$p = $txt($a->get("/reports/price-paid?from=2026-01-01&to=2026-12-31&q=RVW")['body']);
check('price paid history shows the receipt, price and first purchase', str_contains($p, 'G-T1') && str_contains($p, '275.00') && str_contains($p, 'First purchase'));
DB::run('INSERT INTO grn_items (tenant_id, grn_id, variant_id, qty, unit_price, tax_rate) VALUES (?,?,?,?,?,?)', [$tA, $g, $v1, 10, 302.5, 5]);
$p = $txt($a->get("/reports/price-paid?from=2026-01-01&to=2026-12-31&q=RVW")['body']);
check('price paid history shows +10% against the previous purchase', str_contains($p, '+10%'));
foreach (['supplier-dues', 'rate-comparison', 'rate-history', 'price-paid'] as $slug) {
    $c = $a->get("/reports/$slug/export/csv?from=2026-01-01&to=2026-12-31");
    check("$slug exports to CSV", $c['status'] === 200 && substr_count($c['body'], "\n") >= 2);
    check("$slug is empty for another company", !str_contains($txt($b->get("/reports/$slug?from=2026-01-01&to=2026-12-31")['body']), 'Surat Mills'));
}

echo "Items CSV\n";
$tpl = $a->get('/items/import/template')['body'];
check('item template has the new columns and a wallpaper / linen / carpet example', str_contains($tpl, 'item_type') && str_contains($tpl, 'design_no') && str_contains($tpl, 'colour') && str_contains($tpl, 'wallpaper') && str_contains($tpl, 'carpet') && str_contains($tpl, 'linen'));
$hd = "item_name,item_type,category,brand,unit,tax,hsn_code,design_no,composition,width,gsm,pattern,finish,track_batch,reorder_level,variant_name,colour,size,sku,barcode,cost_price,sale_price\n";
$up = function (string $body) use ($a) { $f = tempnam(sys_get_temp_dir(), 'it') . '.csv'; file_put_contents($f, $body); return $a->post('/items/import', ['file' => new CURLFile($f, 'text/csv', 'i.csv')], '/items/import'); };
$un = (int)$val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]);
$up($hd . "Silk Curtain,fabric,Fabric,,Meter,,5407,SC-1,Silk blend,48 in,150,Plain,Satin,,5,Gold,Gold,,SILK-G,,500,800\nSilk Curtain,,,,,,,,,,,,,,,Ivory,Ivory,,SILK-I,,500,800\nSea Wallpaper,wallpaper,,,Meter,,4814,WP-9,Vinyl,53 cm,,Waves,Textured,no,,,Blue,10 m roll,,,900,1500\nQueen Sheet,linen,,,Meter,,6302,BS-1,Cotton,,200 TC,Printed,,,,Queen,Pink,Queen,,,700,1200\n");
$si = DB::one("SELECT * FROM items WHERE tenant_id = ? AND name = 'Silk Curtain'", [$tA]);
check('import keeps type and attributes; blank track_batch on a fabric means roll-tracked', $si && $si['item_type'] === 'fabric' && $si['hsn_code'] === '5407' && $si['design_no'] === 'SC-1' && $si['composition'] === 'Silk blend' && $si['width'] === '48 in' && (int)$si['track_batch'] === 1);
check('both variants imported with their colours', (int)$val('SELECT COUNT(*) FROM item_variants WHERE item_id = ? AND colour IN (?, ?)', [$si['id'], 'Gold', 'Ivory']) === 2);
$wp = DB::one("SELECT * FROM items WHERE tenant_id = ? AND name = 'Sea Wallpaper'", [$tA]);
check('an explicit "no" stays untracked; wallpaper size on variant', (int)$wp['track_batch'] === 0 && $wp['item_type'] === 'wallpaper' && $val('SELECT size FROM item_variants WHERE item_id = ?', [$wp['id']]) === '10 m roll');
check('linen is not roll-tracked by default', (int)$val("SELECT track_batch FROM items WHERE tenant_id = ? AND name = 'Queen Sheet'", [$tA]) === 0);
$n = (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]);
$up($hd . "Nice One,fabric,,,Meter,,,,,,,,,,,,,,,,1,2\nBad Type,unicorn,,,Meter,,,,,,,,,,,,,,,,1,2\n");
check('an unknown item_type rejects the whole file', (int)$val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$tA]) === $n);
$up($hd . "Long Attr,fabric,,,Meter,,,,," . str_repeat('9', 50) . ",,,,,,,,,,,1,2\n");
check('over-long width rejects the file', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Long Attr'", [$tA]));
$exp = $a->get('/items/export')['body'];
check('export includes the new columns and values', str_contains($exp, 'item_type') && str_contains($exp, 'SC-1') && str_contains($exp, 'Gold') && str_contains($exp, '10 m roll'));
$re = tempnam(sys_get_temp_dir(), 'rx') . '.csv'; file_put_contents($re, $exp);
$b->post('/items/import', ['file' => new CURLFile($re, 'text/csv', 'e.csv')], '/items/import');
check("another company can import A's exported file as its own (round trip)", (int)$val("SELECT COUNT(*) FROM items WHERE tenant_id = ? AND name = 'Silk Curtain'", [$tB]) === 1 && (int)$val("SELECT COUNT(*) FROM items WHERE tenant_id = ? AND item_type = 'fabric' AND design_no = 'SC-1'", [$tB]) === 1);

echo "Rate lists page (menu)\n";
$mh = $a->get('/dashboard')['body'];
check('"Rate lists" is in the Purchase menu, right after Suppliers', preg_match('~href="[^"]*/suppliers"[^>]*>.*?Suppliers.*?href="[^"]*/purchase/rates"[^>]*>.*?Rate lists~s', $mh) === 1);
$rl = $a->get('/purchase/rates');
$rt = preg_replace('/\s+/', ' ', strip_tags($rl['body']));
check('the page lists rates of every supplier with item, supplier, net rate and a Lowest badge', $rl['status'] === 200 && str_contains($rt, 'Royal Velvet') && str_contains($rt, 'Surat Mills') && str_contains($rt, 'Delhi Traders') && str_contains($rt, 'Lowest'));
check('stat cards show current rates / suppliers / items', str_contains($rl['body'], 'Current rates') && str_contains($rl['body'], 'Earlier rates kept'));
$only = $a->get("/purchase/rates?supplier=$s2")['body'];
check('filtering by supplier shows only that supplier', str_contains($only, 'Delhi Traders') && !preg_match('~<td><a href="[^"]*suppliers/' . $s1 . '#rates">~', $only));
check('search by item name / SKU / design works and an empty result says so', str_contains($a->get('/purchase/rates?q=RVW')['body'], 'Royal Velvet') && str_contains($a->get('/purchase/rates?q=zzzzqq')['body'], 'Nothing matches these filters'));
$allr = $a->get('/purchase/rates?show=all')['body']; $cur = $a->get('/purchase/rates')['body'];
check('"With earlier rates" adds the older rows that "Current" hides', substr_count($allr, 'class="rl-old"') > substr_count($cur, 'class="rl-old"') && substr_count($cur, 'class="rl-old"') === 0);
$vv = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'WP1'", [$tA]);
$ra = $a->post("/suppliers/$s2/rates", ['variant_id' => $vv, 'rate' => '777', 'valid_from' => '2026-02-01', 'return' => 'purchase/rates?show=all'], '/purchase/rates');
check('adding from this page returns to it (same filters)', $ra['status'] === 302 && str_contains((string)$ra['location'], 'purchase/rates?show=all') && (float)$val('SELECT rate FROM supplier_rates WHERE supplier_id = ? AND variant_id = ?', [$s2, $vv]) === 777.0);
$rid2 = (int)$val('SELECT id FROM supplier_rates WHERE supplier_id = ? AND variant_id = ?', [$s2, $vv]);
$rd = $a->post("/suppliers/$s2/rates/$rid2/delete", ['return' => 'purchase/rates'], '/purchase/rates');
check('deleting from this page also returns to it', $rd['status'] === 302 && str_contains((string)$rd['location'], 'purchase/rates') && !$val('SELECT 1 FROM supplier_rates WHERE id = ?', [$rid2]));
$rx = $a->post("/suppliers/$s2/rates", ['variant_id' => $vv, 'rate' => '5', 'return' => 'https://evil.example/x'], '/purchase/rates');
check('an off-site return address is ignored (falls back to the supplier page)', str_contains((string)$rx['location'], "suppliers/$s2") && !str_contains((string)$rx['location'], 'evil'));
$a->post("/suppliers/$s2/rates/" . (int)$val('SELECT id FROM supplier_rates WHERE supplier_id = ? AND variant_id = ?', [$s2, $vv]) . '/delete', [], '/purchase/rates');
check("another company's rate list is empty of A's rates", !str_contains($txt2 = preg_replace('/\s+/', ' ', strip_tags($b->get('/purchase/rates')['body'])), 'Surat Mills') && !str_contains($txt2, 'Royal Velvet'));
$view = new Client($base);
$rid = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'RateView ' . $sfx]); DB::insert('role_permissions', ['role_id' => $rid, 'permission' => 'suppliers.view']);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $rid, 'name' => 'RV', 'email' => "rv-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$view->post('/login', ['email' => "rv-$sfx@test.local", 'password' => 'Password123'], '/login');
$vb = $view->get('/purchase/rates')['body'];
check('a view-only user can read the list but gets no Add / Revise / Remove controls', str_contains($vb, 'Supplier rate lists') && !str_contains($vb, 'data-rate-new') && !str_contains($vb, 'data-rate-revise') && !str_contains($vb, 'id="rateModal"'));
$noPerm = new Client($base);
$r2 = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'NoSup ' . $sfx]); DB::insert('role_permissions', ['role_id' => $r2, 'permission' => 'customers.view']);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $r2, 'name' => 'NS', 'email' => "ns-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$noPerm->post('/login', ['email' => "ns-$sfx@test.local", 'password' => 'Password123'], '/login');
check('a user without supplier permission gets 403 and no menu entry', $noPerm->get('/purchase/rates')['status'] === 403 && !str_contains($noPerm->get('/dashboard')['body'], 'Rate lists'));

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
