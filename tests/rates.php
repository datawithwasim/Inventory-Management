<?php
declare(strict_types=1);

/**
 * Roll / cut / MOQ rates, supplier origin (domestic / imported / market), currencies and purchase orders in a foreign currency.
 *   php tests/rates.php http://127.0.0.1:8099        # against a throw-away database
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
[$a, $tA] = $mk('Ratesco');
[$b, $tB] = $mk('Elsewhere');
$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$near = fn($x, $y, $eps = 0.011) => abs((float)$x - (float)$y) < $eps;
$unit = (int)$val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$tA, 'Meter']);
$main = (int)$val('SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1', [$tA]);
$a->post('/items', ['name' => 'Velvet', 'unit_id' => $unit, 'track_batch' => 0, 'variants' => [['sku' => 'VEL', 'cost_price' => 400]]], '/items/create');
$vel = (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'VEL'", [$tA]);
$sup = fn(string $n) => (int)$val('SELECT id FROM suppliers WHERE tenant_id = ? AND name = ?', [$GLOBALS['tA'], $n]);

echo "Supplier origin\n";
$a->post('/suppliers', ['name' => 'Surat Mills'], '/suppliers/create');
check('a supplier is domestic (rupees) unless said otherwise', $val('SELECT origin FROM suppliers WHERE id = ?', [$sup('Surat Mills')]) === 'domestic' && $val('SELECT currency FROM suppliers WHERE id = ?', [$sup('Surat Mills')]) === 'INR');
$a->post('/suppliers', ['name' => 'Chandni Chowk Trader', 'origin' => 'market'], '/suppliers/create');
check('a market supplier quotes in rupees', $val('SELECT origin FROM suppliers WHERE id = ?', [$sup('Chandni Chowk Trader')]) === 'market' && $val('SELECT currency FROM suppliers WHERE id = ?', [$sup('Chandni Chowk Trader')]) === 'INR');
$a->post('/suppliers', ['name' => 'Bad Import', 'origin' => 'imported'], '/suppliers/create');
check('an imported supplier needs a currency', $sup('Bad Import') === 0);
$a->post('/suppliers', ['name' => 'Bad Import', 'origin' => 'imported', 'currency' => 'XYZ'], '/suppliers/create');
check('an unknown currency is refused', $sup('Bad Import') === 0);
$a->post('/suppliers', ['name' => 'Bad Origin', 'origin' => 'moon'], '/suppliers/create');
check('an unknown origin is refused', $sup('Bad Origin') === 0);
$a->post('/suppliers', ['name' => 'Milano Textiles', 'origin' => 'imported', 'currency' => 'EUR'], '/suppliers/create');
$a->post('/suppliers', ['name' => 'Shanghai Weave', 'origin' => 'imported', 'currency' => 'USD'], '/suppliers/create');
check('imported suppliers keep their currency', $val('SELECT currency FROM suppliers WHERE id = ?', [$sup('Milano Textiles')]) === 'EUR' && $val('SELECT currency FROM suppliers WHERE id = ?', [$sup('Shanghai Weave')]) === 'USD');
$a->post('/suppliers', ['name' => 'Home Again', 'origin' => 'domestic', 'currency' => 'USD'], '/suppliers/create');
check('a domestic supplier ignores a currency and stays in rupees', $val('SELECT currency FROM suppliers WHERE id = ?', [$sup('Home Again')]) === 'INR');
[$dom, $mkt, $eur, $usd] = [$sup('Surat Mills'), $sup('Chandni Chowk Trader'), $sup('Milano Textiles'), $sup('Shanghai Weave')];

echo "Exchange rates\n";
$defaults = (int)$val('SELECT COUNT(*) FROM currencies WHERE tenant_id = ?', [$tA]);
check('a company starts with the usual currencies', $defaults >= 7 && $val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tA]) > 0);
$a->post('/purchase/currencies', ['rows' => [['code' => 'USD', 'rate_to_inr' => '85.5', 'is_active' => 1]]], '/purchase/rates');
check('the exchange rate can be changed', $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tA]), 85.5));
$a->post('/purchase/currencies', ['rows' => [['code' => 'USD', 'rate_to_inr' => '0', 'is_active' => 1]]], '/purchase/rates');
check('a zero rate is refused', $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tA]), 85.5));
$a->post('/purchase/currencies', ['rows' => [['code' => 'USD', 'rate_to_inr' => 'abc', 'is_active' => 1]]], '/purchase/rates');
check('a rate that is not a number is refused', $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tA]), 85.5));
$a->post('/purchase/currencies', ['rows' => [['code' => 'INR', 'rate_to_inr' => '5', 'is_active' => 1]]], '/purchase/rates');
check('rupees stay at 1', $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'INR'", [$tA]), 1));
$a->post('/purchase/currencies', ['new' => ['code' => 'chf', 'name' => 'Swiss franc', 'symbol' => 'CHF', 'rate_to_inr' => '95']], '/purchase/rates');
check('another currency can be added', $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'CHF'", [$tA]), 95));
$a->post('/purchase/currencies', ['new' => ['code' => 'CHF', 'rate_to_inr' => '96']], '/purchase/rates');
check('the same currency twice is refused', (int)$val("SELECT COUNT(*) FROM currencies WHERE tenant_id = ? AND code = 'CHF'", [$tA]) === 1);
$a->post('/purchase/currencies', ['new' => ['code' => 'TOOLONG', 'rate_to_inr' => '96']], '/purchase/rates');
check('a code must be three letters', (int)$val('SELECT COUNT(*) FROM currencies WHERE tenant_id = ?', [$tA]) === $defaults + 1);
check('another company has its own exchange rates', (int)$val("SELECT COUNT(*) FROM currencies WHERE tenant_id = ? AND code = 'CHF'", [$tB]) === 0 && $b->get('/purchase/rates')['status'] === 200 && $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tB]), 83));
$b->post('/purchase/currencies', ['rows' => [['code' => 'USD', 'rate_to_inr' => '1', 'is_active' => 1]]], '/purchase/rates');
check("one company cannot change another's rate", $near($val("SELECT rate_to_inr FROM currencies WHERE tenant_id = ? AND code = 'USD'", [$tA]), 85.5));

echo "Roll / cut / MOQ rates\n";
$rate = fn(int $s, array $d) => $a->post("/suppliers/$s/rates", $d + ['variant_id' => $vel], "/suppliers/$s");
$rate($dom, ['roll_rate' => 300, 'cut_rate' => 330, 'moq_rate' => 280, 'min_qty' => 100, 'discount_pct' => 10, 'lead_time_days' => 7]);
$r = DB::one('SELECT * FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ?', [$tA, $dom]);
check('roll, cut and MOQ rates are stored with the MOQ quantity', $r && $near($r['rate'], 300) && $near($r['cut_rate'], 330) && $near($r['moq_rate'], 280) && $near($r['min_qty'], 100) && $r['currency'] === 'INR');
$rate($mkt, ['rate' => 310]);
$rm = DB::one('SELECT * FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ?', [$tA, $mkt]);
check('cut and MOQ rates are optional (the older "rate" field still means the roll rate)', $rm && $near($rm['rate'], 310) && $rm['cut_rate'] === null && $rm['moq_rate'] === null);
$n = (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE tenant_id = ?', [$tA]);
$rate($eur, ['roll_rate' => 'abc']);
$rate($eur, ['roll_rate' => 10, 'cut_rate' => -1]);
$rate($eur, ['roll_rate' => 10, 'moq_rate' => 9]);
check('a bad roll rate, a negative cut rate, or an MOQ rate without its MOQ quantity is refused', (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE tenant_id = ?', [$tA]) === $n);
$rate($eur, ['roll_rate' => 4, 'cut_rate' => 4.5, 'moq_rate' => 3.6, 'min_qty' => 200]);
$rate($usd, ['roll_rate' => 3.2, 'cut_rate' => 3.5]);
check('rates of an imported supplier are kept in its currency', $val('SELECT currency FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ?', [$tA, $eur]) === 'EUR' && $val('SELECT currency FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ?', [$tA, $usd]) === 'USD');
$j = json_decode($a->get('/api/v1/purchase/rates')['body'], true)['data'] ?? [];
$rows = []; foreach ($j['rows'] ?? [] as $x) $rows[$x['supplier_id']] = $x;
check('the rate list shows roll / cut / MOQ after the discount', isset($rows[$dom]) && $near($rows[$dom]['nets']['roll'], 270) && $near($rows[$dom]['nets']['cut'], 297) && $near($rows[$dom]['nets']['moq'], 252));
check('MOQ rate stays empty when no MOQ quantity is set', isset($rows[$mkt]) && $rows[$mkt]['nets']['moq'] === null && $rows[$mkt]['nets']['cut'] === null);
check('the lowest rate compares in rupees (3.2 USD x 85.5 = 273.6 beats 4 EUR x 90 = 360)', isset($rows[$usd]) && $near($rows[$usd]['net_inr'], 273.6) && $near($rows[$eur]['net_inr'], 360) && $near($j['low'][$vel] ?? 0, 270));
check('the list carries the company currencies and each supplier origin', count($j['currencies'] ?? []) >= 8 && in_array('imported', array_column($j['suppliers'] ?? [], 'origin'), true));
$csv = $a->get("/suppliers/$dom/rates/template");
check('the CSV template has the roll / cut / MOQ columns', str_contains($csv['body'], 'roll_rate,cut_rate,moq_rate,min_qty'));
$tmp = tempnam(sys_get_temp_dir(), 'csv'); file_put_contents($tmp, "sku,roll_rate,cut_rate,moq_rate,min_qty,valid_from\nVEL,320,350,300,50," . date('Y-m-d', strtotime('+1 day')) . "\n");
$a->post("/suppliers/$dom/rates/import", ['file' => new CURLFile($tmp, 'text/csv', 'r.csv')], "/suppliers/$dom");
check('the CSV import reads the three rates', (int)$val('SELECT COUNT(*) FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ? AND cut_rate = 350 AND moq_rate = 300', [$tA, $dom]) === 1);
check('the supplier, supplier item and item pages load with the new rate columns', $a->get("/suppliers/$dom")['status'] === 200 && $a->get("/suppliers/$eur")['status'] === 200 && $a->get('/purchase/rates')['status'] === 200 && $a->get('/reports/rate-comparison')['status'] === 200 && $a->get('/reports/rate-history')['status'] === 200);

echo "Picking an item on a purchase order\n";
$l = json_decode($a->get("/lookup/items?q=VEL&supplier=$dom")['body'], true);
$row = $l['rows'][0] ?? $l[0] ?? [];
check('the item picker brings all three rates and the currency', isset($row['rates']) && $near($row['rates']['roll'], 270) && $near($row['rates']['cut'], 297) && $near($row['rates']['moq'], 252) && $row['rates']['currency'] === 'INR' && $near($row['rates']['min_qty'], 100));
$l = json_decode($a->get("/lookup/items?q=VEL&supplier=$eur")['body'], true);
$row = $l['rows'][0] ?? $l[0] ?? [];
check('an imported supplier brings its currency', ($row['rates']['currency'] ?? '') === 'EUR');
check('the rate note names each rate', isset($row['rate_note']) && str_contains($row['rate_note'], 'Roll') && str_contains($row['rate_note'], 'Cut') && str_contains($row['rate_note'], 'MOQ'));
foreach ([[150, 4.0], [250, 3.6]] as [$q, $want]) {
    $a->post('/purchase/requisitions', ['lines' => [['variant_id' => $vel, 'qty' => $q]]], '/purchase/requisitions/create');
    $rq = (int)$val('SELECT MAX(id) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
    $a->post("/purchase/requisitions/$rq/convert", ['supplier_id' => $eur, 'warehouse_id' => $main], "/purchase/requisitions/$rq");
    $pp = (int)$val('SELECT po_id FROM purchase_requisitions WHERE id = ?', [$rq]);
    check("a requisition for $q m priced at the " . ($q >= 200 ? 'MOQ' : 'roll') . " rate in euros", $pp > 0 && $val('SELECT currency FROM purchase_orders WHERE id = ?', [$pp]) === 'EUR' && $near($val('SELECT unit_price FROM purchase_order_items WHERE po_id = ?', [$pp]), $want));
}

echo "Purchase order in a foreign currency\n";
$po = fn(int $s, array $lines, array $over = []) => $a->post('/purchase/orders', $over + ['supplier_id' => $s, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => $lines], '/purchase/orders/create');
$po($usd, [['variant_id' => $vel, 'qty' => 100, 'unit_price' => 3.2, 'tax_rate' => 0, 'rate_kind' => 'roll']]);
$p1 = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$o = DB::one('SELECT * FROM purchase_orders WHERE id = ?', [$p1]);
check('an order to an imported supplier is in its currency at the current exchange rate', $o['currency'] === 'USD' && $near($o['exchange_rate'], 85.5) && $near($o['total'], 320));
check('the line remembers which rate it was priced at', $val('SELECT rate_kind FROM purchase_order_items WHERE po_id = ?', [$p1]) === 'roll');
$po($usd, [['variant_id' => $vel, 'qty' => 10, 'unit_price' => 3.2]], ['exchange_rate' => '90']);
$p2 = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('the exchange rate can be set on the order', $near($val('SELECT exchange_rate FROM purchase_orders WHERE id = ?', [$p2]), 90));
$n = (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$po($usd, [['variant_id' => $vel, 'qty' => 10, 'unit_price' => 3.2]], ['exchange_rate' => '-4']);
$po($usd, [['variant_id' => $vel, 'qty' => 10, 'unit_price' => 3.2]], ['exchange_rate' => 'x']);
check('a bad exchange rate is refused', (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tA]) === $n);
$po($dom, [['variant_id' => $vel, 'qty' => 10, 'unit_price' => 270, 'rate_kind' => 'weird']], ['exchange_rate' => '90']);
$p3 = (int)$val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('an order to a domestic supplier is in rupees whatever rate is posted', $val('SELECT currency FROM purchase_orders WHERE id = ?', [$p3]) === 'INR' && $near($val('SELECT exchange_rate FROM purchase_orders WHERE id = ?', [$p3]), 1) && $val('SELECT rate_kind FROM purchase_order_items WHERE po_id = ?', [$p3]) === null);
$a->post('/purchase/currencies', ['rows' => [['code' => 'USD', 'rate_to_inr' => '88', 'is_active' => 1]]], '/purchase/rates');
check('changing the exchange rate later leaves existing orders alone', $near($val('SELECT exchange_rate FROM purchase_orders WHERE id = ?', [$p1]), 85.5));
$po($usd, [['variant_id' => $vel, 'qty' => 1, 'unit_price' => 1]]);
check('a new order uses the new rate', $near($val('SELECT exchange_rate FROM purchase_orders WHERE id = (SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?)', [$tA]), 88));
$h = json_decode($a->get("/api/v1/purchase/orders/$p1")['body'], true)['data'] ?? [];
check('the order page carries its currency and exchange rate', ($h['po']['currency'] ?? '') === 'USD' && $near($h['po']['exchange_rate'] ?? 0, 85.5) && $a->get('/purchase/orders')['status'] === 200);
check('the order prints', $a->get("/purchase/orders/$p1/print")['status'] === 200);

echo "Receiving a foreign-currency order\n";
$a->post("/purchase/orders/$p1/submit", [], "/purchase/orders/$p1");
$pi = (int)$val('SELECT id FROM purchase_order_items WHERE po_id = ?', [$p1]);
$a->post('/purchase/grns', ['po_id' => $p1, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'lines' => [['po_item_id' => $pi, 'qty' => 100]]], "/purchase/orders/$p1/receive");
$g = (int)$val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('the receipt is in rupees at the order\'s exchange rate (3.2 USD x 85.5 = 273.6)', $g > 0 && $near($val('SELECT unit_price FROM grn_items WHERE grn_id = ?', [$g]), 273.6));
check('the stock cost is in rupees', $near($val('SELECT unit_cost FROM stock_ledger WHERE tenant_id = ? AND variant_id = ? ORDER BY id DESC LIMIT 1', [$tA, $vel]), 273.6));
$a->post("/purchase/grns/$g/bill", [], "/purchase/grns/$g");
$bill = (int)$val('SELECT id FROM purchase_bills WHERE grn_id = ?', [$g]);
check('the bill is in rupees: 100 x 273.6 = 27360', $bill > 0 && $near($val('SELECT subtotal FROM purchase_bills WHERE id = ?', [$bill]), 27360));
$a->post("/purchase/orders/$p2/submit", [], "/purchase/orders/$p2");
$open = json_decode($a->get("/api/v1/purchase/grns/open-pos?supplier=$usd")['body'], true) ?? [];
$ol = $open['pos'][0]['lines'][0] ?? $open[0]['lines'][0] ?? [];
check('the receipt form offers the order price in rupees (3.2 USD x 90 = 288) and the order price', $near($ol['unit_price'] ?? 0, 288) && ($ol['po_currency'] ?? '') === 'USD');

echo "Reports\n";
$rc = json_encode(json_decode($a->get('/api/v1/reports/rate-comparison')['body'], true));
check('the rate comparison lists the foreign rate and its rupee value', str_contains($rc, 'USD') && str_contains($rc, '281.6'));
$other = json_decode($b->get('/api/v1/purchase/rates')['body'], true)['data'] ?? [];
check('another company sees none of these rates', count($other['rows'] ?? []) === 0);

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
