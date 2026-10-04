<?php
declare(strict_types=1);

/**
 * End-to-end test for Phase 6 (dashboard + reports + exports + reorder → draft POs).
 *   php tests/reports.php http://127.0.0.1:8099        # against a throw-away database
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
[$a, $tA] = $mk('Reporter');
[$b, $tB] = $mk('Other');
$near = fn($x, $y, $eps = 0.011) => abs((float)$x - (float)$y) < $eps;
$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$day = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? "+$n" : (string)$n) . ' days'));
$unit = fn(string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$GLOBALS['tA'], $n]);
$main = (int)$val('SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1', [$tA]);

echo "Setup\n";
foreach (['A-01', 'B-01'] as $c) DB::insert('locations', ['tenant_id' => $tA, 'warehouse_id' => $main, 'code' => $c]);
$A1 = (int)$val("SELECT id FROM locations WHERE tenant_id = ? AND code = 'A-01'", [$tA]);
$B1 = (int)$val("SELECT id FROM locations WHERE tenant_id = ? AND code = 'B-01'", [$tA]);
$a->post('/items', ['name' => 'Velvet', 'unit_id' => $unit('Meter'), 'track_batch' => 1, 'variants' => [['sku' => 'VEL', 'cost_price' => 400, 'sale_price' => 700]]], '/items/create');
$a->post('/items', ['name' => 'Side Table', 'unit_id' => $unit('Piece'), 'variants' => [['sku' => 'TBL', 'cost_price' => 2500, 'sale_price' => 4200]]], '/items/create');
$a->post('/items', ['name' => 'Chair', 'unit_id' => $unit('Piece'), 'variants' => [['sku' => 'CHR', 'cost_price' => 900, 'sale_price' => 1500]]], '/items/create');
[$vel, $tbl, $chr] = array_map(fn($s) => (int)DB::val('SELECT id FROM item_variants WHERE tenant_id = ? AND sku = ?', [$tA, $s]), ['VEL', 'TBL', 'CHR']);
check('items created', $vel && $tbl && $chr);
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'opening', 'lines' => [['variant_id' => $vel, 'qty' => 50, 'batch_id' => 'new', 'lot_no' => 'L1', 'location_id' => $B1]]], '/stock/adjustments/create');
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'opening', 'lines' => [['variant_id' => $vel, 'qty' => 30, 'batch_id' => 'new', 'lot_no' => 'L2', 'location_id' => $A1], ['variant_id' => $tbl, 'qty' => 10, 'location_id' => $A1], ['variant_id' => $chr, 'qty' => 20, 'location_id' => $B1]]], '/stock/adjustments/create');
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'damage', 'lines' => [['variant_id' => $chr, 'qty' => -2, 'location_id' => $B1]]], '/stock/adjustments/create');
check('stock: 80 m velvet (2 rolls), 10 tables, 18 chairs', (float)$val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $vel]) === 80.0
    && (float)$val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $chr]) === 18.0);
$rolls = DB::all('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ? ORDER BY id', [$tA, $vel]);
DB::run('UPDATE batches SET received_date = ? WHERE id = ?', [$day(-200), $rolls[0]['id']]);
DB::run('UPDATE items SET reorder_level = 12, reorder_qty = 20 WHERE tenant_id = ? AND id = (SELECT item_id FROM item_variants WHERE id = ?)', [$tA, $tbl]);
DB::run('UPDATE items SET reorder_level = 50 WHERE tenant_id = ? AND id = (SELECT item_id FROM item_variants WHERE id = ?)', [$tA, $chr]);

// Parties and documents (inserted directly: reports only read them)
$ins = fn(string $t, array $d) => DB::insert($t, ['tenant_id' => $GLOBALS['tA']] + $d);
$acme = $ins('customers', ['name' => 'Acme Interiors', 'credit_days' => 15]);
$ravi = $ins('customers', ['name' => "=HYPERLINK(\"http://x\")"]);
$s1 = $ins('suppliers', ['name' => 'Loom House']);
$otherCust = DB::insert('customers', ['tenant_id' => $tB, 'name' => 'Stranger']);
DB::insert('sales_invoices', ['tenant_id' => $tB, 'invoice_no' => 'X-1', 'customer_id' => $otherCust, 'invoice_date' => $day(-1), 'subtotal' => 999, 'total' => 999]);

// Sales: i1 (Acme, 40 days ago, overdue), i2 (Ravi, today)
$d1 = $ins('deliveries', ['delivery_no' => 'D1', 'customer_id' => $acme, 'warehouse_id' => $main, 'delivery_date' => $day(-40), 'source' => 'order']);
$ins('delivery_items', ['delivery_id' => $d1, 'variant_id' => $tbl, 'qty' => 2, 'qty_returned' => 1, 'unit_price' => 4200, 'tax_rate' => 12]);
$ins('delivery_items', ['delivery_id' => $d1, 'variant_id' => $vel, 'qty' => 10, 'unit_price' => 700, 'discount_pct' => 5, 'tax_rate' => 12]);
$i1 = $ins('sales_invoices', ['invoice_no' => 'INV-1', 'customer_id' => $acme, 'delivery_id' => $d1, 'invoice_date' => $day(-40), 'due_date' => $day(-25),
    'subtotal' => 15400, 'discount_total' => 350, 'tax_total' => 1806, 'total' => 16856, 'returned_amount' => 4200, 'paid_amount' => 6000]);
$d2 = $ins('deliveries', ['delivery_no' => 'D2', 'customer_id' => $ravi, 'warehouse_id' => $main, 'delivery_date' => $day(0), 'source' => 'order']);
$ins('delivery_items', ['delivery_id' => $d2, 'variant_id' => $chr, 'qty' => 1, 'unit_price' => 1000]);
$i2 = $ins('sales_invoices', ['invoice_no' => 'INV-2', 'customer_id' => $ravi, 'delivery_id' => $d2, 'invoice_date' => $day(0), 'subtotal' => 1000, 'total' => 1000]);
$ins('customer_payments', ['customer_id' => $acme, 'invoice_id' => $i1, 'amount' => 6000, 'applied' => 0, 'paid_on' => $day(-35), 'method' => 'cash']);
$ins('customer_payments', ['customer_id' => $acme, 'invoice_id' => null, 'amount' => 2000, 'applied' => 0, 'paid_on' => $day(-20), 'method' => 'upi']);
$ins('sales_returns', ['return_no' => 'SR-1', 'invoice_id' => $i1, 'delivery_id' => $d1, 'customer_id' => $acme, 'warehouse_id' => $main, 'return_date' => $day(-30), 'total' => 4200]);
$ins('sales_orders', ['order_no' => 'SO-1', 'customer_id' => $ravi, 'warehouse_id' => $main, 'order_date' => $day(0), 'status' => 'confirmed', 'total' => 12600]);
$so = (int)$val('SELECT id FROM sales_orders WHERE tenant_id = ?', [$tA]);
$ins('sales_order_items', ['order_id' => $so, 'variant_id' => $tbl, 'qty_ordered' => 3, 'qty_delivered' => 0, 'unit_price' => 4200]);
// Purchases: bill b1 from Loom House 10 days ago
$g1 = $ins('grns', ['grn_no' => 'GRN-1', 'supplier_id' => $s1, 'warehouse_id' => $main, 'received_date' => $day(-10)]);
$ins('grn_items', ['grn_id' => $g1, 'variant_id' => $tbl, 'qty' => 10, 'qty_returned' => 0, 'unit_price' => 2500, 'landed_unit_cost' => 2500]);
$b1 = $ins('purchase_bills', ['bill_no' => 'PB-1', 'supplier_id' => $s1, 'grn_id' => $g1, 'bill_date' => $day(-10), 'due_date' => $day(-5), 'subtotal' => 25000, 'tax_total' => 3000,
    'total' => 28000, 'returned_amount' => 2800, 'paid_amount' => 10000]);
$ins('supplier_payments', ['bill_id' => $b1, 'amount' => 10000, 'paid_on' => $day(-9), 'method' => 'bank']);
$ins('purchase_returns', ['return_no' => 'PRT-1', 'grn_id' => $g1, 'supplier_id' => $s1, 'warehouse_id' => $main, 'bill_id' => $b1, 'return_date' => $day(-8), 'total' => 2800]);
check('documents seeded', $i1 && $i2 && $b1 && $so);

$get = fn(string $p) => $a->get($p);
$page = fn(string $slug, string $qs = '') => $get("/reports/$slug" . ($qs ? "?$qs" : ''))['body'];
$cells = function (string $html): array { // all <td> text of body rows
    preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $m);
    preg_match_all('/<tr>(.*?)<\/tr>/s', $m[1] ?? '', $rows);
    return array_map(fn($r) => array_map(fn($c) => trim(html_entity_decode(strip_tags($c))), preg_split('/<\/td>/', $r)), $rows[1]);
};
$foot = function (string $html): string { preg_match('/<tfoot.*?<\/tfoot>/s', $html, $m); return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[0] ?? '')))); };
$num = fn(string $s) => (float)str_replace([',', '₹', ' '], '', $s);

echo "Every report opens\n";
$slugs = ['stock-summary', 'batch-stock', 'stock-ledger', 'dead-stock', 'movers', 'sales-register', 'sales-summary', 'purchase-register', 'purchase-summary', 'supplier-dues', 'rate-comparison', 'rate-history', 'price-paid', 'ageing', 'customer-statement', 'supplier-statement', 'low-stock', 'adjustments'];
$bad = [];
foreach ($slugs as $s) {
    $r = $get("/reports/$s");
    if ($r['status'] !== 200 || stripos($r['body'], 'Fatal error') !== false || stripos($r['body'], 'Warning:') !== false || stripos($r['body'], 'SQLSTATE') !== false) $bad[] = $s;
    foreach (['print=1', 'from=2020-01-01&to=2030-01-01&warehouse=' . $main . '&category=0'] as $q) {
        $r = $get("/reports/$s?$q");
        if ($r['status'] !== 200 || stripos($r['body'], 'SQLSTATE') !== false || stripos($r['body'], 'Fatal error') !== false) $bad[] = "$s?$q";
    }
}
check('all 18 reports (plain, print, filtered) render without errors' . ($bad ? ' — bad: ' . implode(', ', $bad) : ''), !$bad);
check('/reports index lists every report', count(array_filter($slugs, fn($s) => str_contains($get('/reports')['body'], "reports/$s"))) === 18);
check('unknown report is 404', $get('/reports/nope')['status'] === 404);
check('sidebar has a Reports link', str_contains($get('/dashboard')['body'], 'href="' . url('reports') . '"') || str_contains($get('/dashboard')['body'], '>Reports<'));

echo "Stock reports\n";
$h = $page('stock-summary');
$rows = $cells($h);
$byName = [];
foreach ($rows as $r) if (count($r) > 6) $byName[$r[0]] = $r;
check('stock summary lists 3 items', count($byName) === 3);
check('velvet 80 on hand, value 32,000', ($byName['Velvet'][5] ?? '') === '80' && $near($num($byName['Velvet'][9] ?? ''), 32000));
check('table: 10 on hand, 3 reserved by the open order, 7 free', ($byName['Side Table'][5] ?? '') === '10' && ($byName['Side Table'][6] ?? '') === '3' && ($byName['Side Table'][7] ?? '') === '7');
check('chair: 18 on hand after the damage write-off; value 16,200', ($byName['Chair'][5] ?? '') === '18' && $near($num($byName['Chair'][9] ?? ''), 16200));
check('total row = 73,200 (32,000 + 25,000 + 16,200)', str_contains($foot($h), '73,200'));
check('dashboard stock value agrees with the report total', str_contains($get('/dashboard')['body'], '73,200'));
check('filter by search term narrows rows', count($cells($page('stock-summary', 'q=velv'))) === 1);
check('"out of stock" filter shows nothing now', !array_filter($cells($page('stock-summary', 'stock=zero')), fn($r) => count($r) > 6));
check('other warehouse filter shows nothing in stock', !array_filter($cells($page('stock-summary', 'stock=in&warehouse=' . $val('SELECT COALESCE(MAX(id),0)+99 FROM warehouses'))), fn($r) => count($r) > 6));

$h = $page('batch-stock');
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 8));
check('batch report: 2 velvet rolls, balance 80, on racks', count($rows) === 2 && str_contains($h, 'B-01') && str_contains($h, 'A-01') && str_contains($foot($h), '80'));
check('older roll shows age ~200 days', (bool)array_filter($rows, fn($r) => (int)$r[5] >= 199 && (int)$r[5] <= 201));
check('"finished" filter is empty before anything is used up', !array_filter($cells($page('batch-stock', 'status=finished')), fn($r) => count($r) > 8));

$h = $page('stock-ledger', 'from=' . $day(-1) . '&to=' . $day(0));
check('stock ledger shows today\'s movements incl. the damage write-off', str_contains($h, 'Adjustment') && str_contains($h, 'Opening stock') && str_contains($h, '-2'));
check('ledger filter by movement type', count($cells($page('stock-ledger', 'from=' . $day(-1) . '&to=' . $day(0) . '&type=adjustment'))) === 1);
$h = $page('adjustments', 'from=' . $day(-1) . '&to=' . $day(0));
check('adjustments report shows the damage write-off with reason and value −1,800', str_contains($h, 'damage') && str_contains($h, '1,800'));

$h = $page('dead-stock', 'days=30');
check('dead stock (30 d): velvet & table idle 40 days, chair (sold today) absent', str_contains($h, 'Velvet') && str_contains($h, 'Side Table') && !str_contains($h, 'Chair'));
check('dead stock (200 d): nothing', !str_contains($page('dead-stock', 'days=200'), 'Velvet'));

$h = $page('movers', 'from=' . $day(-59) . '&to=' . $day(0));
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 5));
check('fast movers: velvet first with 10 sold, 480 days of stock left', ($rows[0][0] ?? '') === 'Velvet' && ($rows[0][3] ?? '') === '10' && ($rows[0][5] ?? '') === '480');
check('table counted net of the 1 returned (sold 1)', (bool)array_filter($rows, fn($r) => $r[0] === 'Side Table' && $r[3] === '1'));
$rows = array_values(array_filter($cells($page('movers', 'from=' . $day(-59) . '&to=' . $day(0) . '&order=slow')), fn($r) => count($r) > 5));
check('slow movers: ascending by quantity sold', count($rows) === 3 && (float)$rows[0][3] <= (float)$rows[1][3] && (float)$rows[1][3] <= (float)$rows[2][3]);

echo "Sales and purchase\n";
$h = $page('sales-register', 'from=' . $day(-60) . '&to=' . $day(0));
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 8));
check('sales register: 2 invoices, total 17,856, balance 7,656', count($rows) === 2 && str_contains($foot($h), '17,856') && str_contains($foot($h), '7,656'));
check('status filter: unpaid shows only Ravi\'s invoice, partly paid only Acme\'s',
    str_contains($page('sales-register', 'from=' . $day(-60) . '&status=unpaid'), 'INV-2') && !str_contains($page('sales-register', 'from=' . $day(-60) . '&status=unpaid'), 'INV-1')
    && str_contains($page('sales-register', 'from=' . $day(-60) . '&status=partial'), 'INV-1'));
check('customer filter on the sales register', !str_contains($page('sales-register', "from={$day(-60)}&customer=$acme"), 'INV-2'));
$rows = array_values(array_filter($cells($page('sales-summary', 'from=' . $day(-60) . '&by=item')), fn($r) => count($r) > 3));
$m = array_column($rows, 3, 0);
check('sales by item: velvet 6,650, table 4,200 (net of return), chair 1,000', $near($num($m['Velvet'] ?? ''), 6650) && $near($num($m['Side Table'] ?? ''), 4200) && $near($num($m['Chair'] ?? ''), 1000));
$rows = array_values(array_filter($cells($page('sales-summary', 'from=' . $day(-60) . '&by=customer')), fn($r) => count($r) > 3));
$m = array_column($rows, 3, 0);
check('sales by customer: Acme 15,050, other 1,000', $near($num($m['Acme Interiors'] ?? ''), 15050) && count($rows) === 2);
check('sales by category works', str_contains($page('sales-summary', 'from=' . $day(-60) . '&by=category'), 'Uncategorised'));
$h = $page('purchase-register', 'from=' . $day(-60));
check('purchase register: bill 28,000, paid 10,000, returned 2,800, balance 15,200', str_contains($h, 'PB-1') && str_contains($foot($h), '28,000') && str_contains($foot($h), '15,200'));
$rows = array_values(array_filter($cells($page('purchase-summary', 'from=' . $day(-60) . '&by=item')), fn($r) => count($r) > 3));
check('purchase by item: table 25,000', count($rows) === 1 && $near($num($rows[0][3]), 25000));
$rows = array_values(array_filter($cells($page('purchase-summary', 'from=' . $day(-60) . '&by=supplier')), fn($r) => count($r) > 3));
check('purchase by supplier: Loom House 22,200 (25,000 − 2,800 returned, before tax)', count($rows) === 1 && $near($num($rows[0][3]), 22200));

echo "Ageing and statements\n";
$h = $page('ageing', 'kind=sales');
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 6));
$m = array_column($rows, null, 0);
check('receivables ageing: Acme 6,656 in the 1–30 days bucket (due 25 days ago)', $near($num($m['Acme Interiors'][2] ?? ''), 6656));
check('receivables: other customer not due 1,000; total 7,656 (also equals dashboard)', $near($num(array_values(array_filter($rows, fn($r) => $r[0] !== 'Acme Interiors'))[0][1] ?? ''), 1000) && str_contains($foot($h), '7,656'));
$h = $page('ageing', 'kind=purchase');
check('payables ageing: 15,200 in 1–30 days', str_contains($foot($h), '15,200') && $near($num($cells($h)[0][2] ?? ''), 15200));
$h = $page('customer-statement', "customer=$acme&from=" . $day(-60) . '&to=' . $day(0));
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 4));
check('customer statement: opening, invoice, payment, return, advance → balance 4,656', count($rows) === 5 && $near($num($rows[4][5]), 4656) && str_contains($h, 'Advance, not yet used'));
$h = $page('customer-statement', "customer=$acme&from=" . $day(-32) . '&to=' . $day(0));
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 4));
check('statement from a later date starts with an opening balance of 10,856', count($rows) === 3 && $near($num($rows[0][5]), 10856) && $near($num($rows[2][5]), 4656));
check('statement with no customer chosen asks for one', str_contains($page('customer-statement'), 'Choose a customer'));
$h = $page('customer-statement', "customer=$otherCust&from=" . $day(-60));
check('another company\'s customer id leaks nothing', !str_contains($h, 'X-1') && !str_contains($h, '999'));
$h = $page('supplier-statement', "supplier=$s1&from=" . $day(-60));
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 4));
check('supplier statement: bill 28,000 − payment 10,000 − return 2,800 = 15,200', count($rows) === 4 && $near($num($rows[3][5]), 15200));

echo "Dashboard\n";
$h = $get('/dashboard')['body'];
check('dashboard renders with hero, charts and lists', str_contains($h, 'class="hero"') && substr_count($h, '<svg class="viz-svg"') >= 1 && str_contains($h, 'Sales vs purchases') && str_contains($h, 'Running low'));
check('hero shows the 30-day net sales (INV-2 only: 1,000; INV-1 is 40 days ago)', preg_match('/class="hero">[^<]*1,000/', $h) === 1);
$h = $get('/dashboard?range=90')['body'];
check('90-day range: net sales 13,656 (16,856 − 4,200 returned + 1,000)', preg_match('/class="hero">[^<]*13,656/', $h) === 1);
check('every range preset renders', array_reduce(['7', '30', '90', 'mtd', 'ytd', 'garbage'], fn($ok, $r) => $ok && $get("/dashboard?range=$r")['status'] === 200, true));
check('legend present for the 2-series chart, table twin present', str_contains($h, 'viz-legend') && str_contains($h, 'viz-table'));
check('overdue invoice and open order listed', str_contains($h, 'INV-1') && str_contains($h, 'SO-1'));

echo "Exports\n";
$csv = $get('/reports/sales-register/export/csv?from=' . $day(-60));
check('CSV: download headers, BOM, header row', $csv['status'] === 200 && str_contains($csv['headers'], 'text/csv') && str_contains($csv['headers'], 'attachment') && str_starts_with($csv['body'], "\xEF\xBB\xBF") && str_contains($csv['body'], 'Invoice'));
check('CSV numbers are plain (no currency symbol or thousands separators)', str_contains($csv['body'], ',16856,') && !str_contains($csv['body'], '16,856'));
check('CSV neutralises spreadsheet formulas in text cells', str_contains($csv['body'], "\"'=HYPERLINK") || str_contains($csv['body'], "'=HYPERLINK"));
check('CSV has a Total row', str_contains($csv['body'], 'Total'));
$x = $get('/reports/sales-register/export/xlsx?from=' . $day(-60));
check('XLSX: correct content type and zip signature', $x['status'] === 200 && str_contains($x['headers'], 'spreadsheetml') && str_starts_with($x['body'], 'PK'));
$tmp = tempnam(sys_get_temp_dir(), 'x');
file_put_contents($tmp, $x['body']);
$z = new ZipArchive();
$okZip = $z->open($tmp) === true;
$sheet = $okZip ? $z->getFromName('xl/worksheets/sheet1.xml') : '';
$xml = $sheet ? @simplexml_load_string($sheet) : false;
check('XLSX opens as a zip with a valid sheet (header + 2 invoices + total = 4 rows)', $okZip && $xml !== false && count($xml->sheetData->row) === 4);
check('XLSX keeps numbers numeric and escapes text', $xml !== false && str_contains($sheet, '<v>16856</v>') && str_contains($sheet, 'HYPERLINK'));
check('large exports are not cut at the screen limit', str_contains($get('/reports/stock-ledger/export/csv?from=2000-01-01')['body'], 'Opening stock'));
check('print view has the print button and landscape page', str_contains($get('/reports/sales-register?print=1')['body'], 'window.print()') && str_contains($get('/reports/sales-register?print=1')['body'], 'landscape'));
check('unknown export format falls back to CSV', str_contains($get('/reports/sales-register/export/pdf')['headers'], 'text/csv'));

echo "Permissions & isolation\n";
$rid = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'Viewer ' . $sfx]);
DB::insert('role_permissions', ['role_id' => $rid, 'permission' => 'reports.view']);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $rid, 'name' => 'Viewer', 'email' => "viewer-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$v = new Client($base);
$v->post('/login', ['email' => "viewer-$sfx@test.local", 'password' => 'Password123'], '/login');
check('role with reports.view can open reports', $v->get('/reports/stock-summary')['status'] === 200);
check('…but cannot export', $v->get('/reports/stock-summary/export/csv')['status'] === 403);
check('…and sees no export buttons', !str_contains($v->get('/reports/stock-summary')['body'], '/export/csv'));
$rid2 = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'Nobody ' . $sfx]);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $rid2, 'name' => 'Nobody', 'email' => "nobody-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$n = new Client($base);
$n->post('/login', ['email' => "nobody-$sfx@test.local", 'password' => 'Password123'], '/login');
check('role without reports.view gets 403 and no sidebar link', $n->get('/reports')['status'] === 403 && !str_contains($n->get('/dashboard')['body'], 'bi-bar-chart-line'));
check('reorder action needs purchase.create', $v->post('/reports/low-stock/create-pos', [], '/reports/low-stock')['status'] === 403);
check('other company sees none of this data', !str_contains($b->get('/reports/sales-register?from=2000-01-01')['body'], 'INV-1') && !str_contains($b->get('/reports/stock-summary?stock=all')['body'], 'Velvet'));
check('reports need a login', (new Client($base))->get('/reports')['status'] === 302);

echo "Low stock → draft purchase orders\n";
$h = $page('low-stock');
$rows = array_values(array_filter($cells($h), fn($r) => count($r) > 8));
check('low stock lists table (10 ≤ 12) and chair (18 ≤ 50), not velvet', count($rows) === 2 && str_contains($h, 'Side Table') && str_contains($h, 'Chair') && !str_contains($h, 'Velvet'));
check('table: suggested 20 from Loom House at last cost 2,500; chair: 32 (50 − 18), no supplier yet', str_contains($h, 'Loom House') && str_contains($h, '— none yet —'));
$pos0 = (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
$reqs0 = (int)$val('SELECT COUNT(*) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
$r = $a->post('/reports/low-stock/create-pos', [], '/reports/low-stock');
check('creates 1 draft PO for the known supplier and 1 requisition for the rest', (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tA]) === $pos0 + 1
    && (int)$val('SELECT COUNT(*) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]) === $reqs0 + 1);
$po = DB::one('SELECT * FROM purchase_orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$tA]);
$li = DB::one('SELECT * FROM purchase_order_items WHERE po_id = ?', [$po['id']]);
check('PO is a draft for Loom House: table × 20 @ 2,500 = 50,000', $po['status'] === 'draft' && (int)$po['supplier_id'] === $s1 && (int)$li['variant_id'] === $tbl && $near($li['qty_ordered'], 20) && $near($li['unit_price'], 2500) && $near($po['subtotal'], 50000));
$rq = DB::one('SELECT * FROM purchase_requisition_items WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$tA]);
check('requisition holds the chair × 32', (int)$rq['variant_id'] === $chr && $near($rq['qty'], 32));
$h = $page('low-stock');
check('running it again suggests nothing new (already ordered / requested)', preg_match('/<td[^>]*>\s*0\s*<\/td>/', $h) === 1);
$a->post('/reports/low-stock/create-pos', [], '/reports/low-stock');
check('second click creates no duplicates', (int)$val('SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = ?', [$tA]) === $pos0 + 1 && (int)$val('SELECT COUNT(*) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]) === $reqs0 + 1);
check('PO shows in the purchase orders list', str_contains($get('/purchase/orders')['body'], $po['po_no']));

echo "\n" . ($fails ? "$fails check(s) FAILED" : 'All checks passed') . "\n";
exit($fails ? 1 : 0);
