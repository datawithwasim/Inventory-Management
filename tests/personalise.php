<?php
declare(strict_types=1);

/**
 * End-to-end test for the personalisation layer: appearance, modules, form fields, custom fields on documents,
 * dashboard layout, list columns, global search, settings frame.
 *   php tests/personalise.php http://127.0.0.1:8099        # against a throw-away database
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
[$a, $tA] = $mk('Custom');
[$b, $tB] = $mk('Neighbour');
$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$unit = fn(string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$GLOBALS['tA'], $n]);
$main = (int)$val('SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1', [$tA]);
$body = fn(string $p) => $a->get($p)['body'];
$ownerId = (int)$val('SELECT id FROM users WHERE tenant_id = ? ORDER BY id LIMIT 1', [$tA]);

echo "Settings frame\n";
$tabs = ['company', 'preferences', 'numbering', 'appearance', 'modules', 'labels', 'templates', 'workflow'];
$bad = [];
foreach ($tabs as $t) {
    $r = $a->get("/settings/$t");
    if ($r['status'] !== 200 || !str_contains($r['body'], 'settings-nav') || stripos($r['body'], 'Fatal') !== false || stripos($r['body'], 'Warning:') !== false) $bad[] = $t;
}
check('all 10 settings pages open inside the grouped Settings menu' . ($bad ? ' — bad: ' . implode(', ', $bad) : ''), !$bad);
check('custom-field create/edit pages keep the frame', str_contains($body('/settings/custom-fields/create'), 'settings-nav'));
check('unknown settings tab is 404', $a->get('/settings/nope')['status'] === 404);

echo "Appearance\n";
check('default look: indigo brand, dark menu, comfortable, light', str_contains($body('/dashboard'), '--brand:#4f46e5') && str_contains($body('/dashboard'), 'sbs-dark') && str_contains($body('/dashboard'), 'density-comfortable'));
$a->post('/settings/appearance', ['brand' => '#0d9488', 'brand_custom' => '', 'sidebar' => 'light', 'density' => 'compact', 'mode' => 'dark'], '/settings/appearance');
$h = $body('/dashboard');
check('saved: teal brand, light menu, compact, dark default applied on every page', str_contains($h, '--brand:#0d9488') && str_contains($h, 'sbs-light') && str_contains($h, 'density-compact') && str_contains($h, '"dark"'));
$a->post('/settings/appearance', ['brand' => '#0d9488', 'brand_custom' => '#aa2200', 'sidebar' => 'brand', 'density' => 'comfortable', 'mode' => 'auto'], '/settings/appearance');
check('a custom colour overrides the swatch; brand menu style; "match device"', str_contains($body('/dashboard'), '--brand:#aa2200') && str_contains($body('/dashboard'), 'sbs-brand') && str_contains($body('/dashboard'), '"auto"'));
foreach ([['brand' => 'red'], ['brand' => '#12345'], ['brand' => '#0d9488', 'sidebar' => 'neon'], ['brand' => '#0d9488', 'density' => 'huge'], ['brand' => '<script>', 'brand_custom' => '']] as $bad) {
    $a->post('/settings/appearance', $bad + ['brand_custom' => '', 'sidebar' => 'dark', 'density' => 'comfortable', 'mode' => 'light'], '/settings/appearance');
}
check('invalid colours / options are refused (nothing changed)', str_contains($body('/dashboard'), '--brand:#aa2200') && !str_contains($body('/dashboard'), 'neon'));
check('another company keeps its own look', str_contains($b->get('/dashboard')['body'], '--brand:#4f46e5'));
check('login page and print pages use the defaults / force light', str_contains((new Client($base))->get('/login')['body'], '--brand:#4f46e5'));
$a->post('/settings/appearance', ['brand' => '#4f46e5', 'brand_custom' => '', 'sidebar' => 'dark', 'density' => 'comfortable', 'mode' => 'light'], '/settings/appearance');
check('print layout forces light mode', str_contains((string)file_get_contents(dirname(__DIR__) . '/views/layouts/print.php'), "setAttribute('data-bs-theme','light')"));

echo "Modules\n";
check('everything is on by default', str_contains($body('/dashboard'), 'Requisitions') && str_contains($body('/dashboard'), 'Reports') && $a->get('/purchase/requisitions')['status'] === 200);
$a->post('/settings/modules', ['on' => ['purchase_returns', 'sales_returns', 'pos', 'transfers', 'takes', 'labels']], '/settings/modules');   // requisitions + reports off
$h = $body('/dashboard');
check('requisitions and reports vanish from the menu', !str_contains($h, 'href="' . url('purchase/requisitions') . '"') && !str_contains($h, 'href="' . url('reports') . '"'));
check('their pages stop opening (404)', $a->get('/purchase/requisitions')['status'] === 404 && $a->get('/reports')['status'] === 404 && $a->get('/reports/stock-summary')['status'] === 404);
check('search shortcuts drop them too', !str_contains($h, '"Requisitions"'));
check('other parts still work', $a->get('/sales/orders')['status'] === 200 && $a->get('/stock')['status'] === 200);
check('dashboard no longer links to the low-stock report', !str_contains($h, 'reports/low-stock'));
check('another company is unaffected', $b->get('/purchase/requisitions')['status'] === 200 && $b->get('/reports')['status'] === 200);
$a->post('/settings/modules', ['on' => ['bogus', 'reports', 'requisitions', 'purchase_returns', 'sales_returns', 'pos', 'transfers', 'takes', 'labels']], '/settings/modules');
check('switching back on restores everything; unknown keys ignored', $a->get('/purchase/requisitions')['status'] === 200 && $a->get('/reports')['status'] === 200 && $val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'modules.off'", [$tA]) === '[]');
$a->post('/settings/modules', ['on' => []], '/settings/modules');
check('all off: POS, transfers, labels, takes, returns blocked', $a->get('/pos')['status'] === 404 && $a->get('/stock/transfers')['status'] === 404 && $a->get('/labels')['status'] === 404 && $a->get('/stock/takes')['status'] === 404 && $a->get('/purchase/returns')['status'] === 404);
$a->post('/settings/modules', ['on' => array_keys(Core\Modules::ALL)], '/settings/modules');
check('all on again', $a->get('/pos')['status'] === 200 && $a->get('/labels')['status'] === 200);

echo "Form fields\n";
$a->post('/customers', ['name' => 'Acme', 'email' => 'acme@x.com', 'tax_no' => 'T1', 'contact_person' => 'Raj'], '/customers/create');
$acme = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Acme'", [$tA]);
check('customer form shows email, contact person, notes by default', ($f = $body('/customers/create')) && str_contains($f, 'name="email"') && str_contains($f, 'name="contact_person"') && str_contains($f, 'name="notes"'));
$a->post('/settings/formfields', ['show' => ['customer' => ['contact_person' => 1, 'address' => 1, 'ship_address' => 1, 'tax_no' => 1, 'credit_days' => 1, 'notes' => 1, 'group_id' => 1]], 'req' => ['customer' => ['tax_no' => 1]]], '/settings/modules');
$f = $body('/customers/create');
check('email hidden on the customer form; tax number now marked required', !str_contains($f, 'name="email"') && str_contains($f, 'name="tax_no"') && preg_match('/name="tax_no"[^>]*required/', $f) === 1);
$n = (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]);
$a->post('/customers', ['name' => 'NoTax'], '/customers/create');
check('saving without the required tax number is refused (server side)', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/customers', ['name' => 'WithTax', 'tax_no' => 'GST9', 'email' => 'sneaky@x.com'], '/customers/create');
check('with it, saved — and a posted value for a hidden field is ignored', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n + 1 && $val("SELECT email FROM customers WHERE name = 'WithTax' AND tenant_id = ?", [$tA]) === null);
$a->post("/customers/$acme", ['name' => 'Acme Ltd', 'tax_no' => 'T2', 'is_active' => 1], "/customers/$acme/edit");
check('editing keeps the hidden email that was saved earlier', $val('SELECT email FROM customers WHERE id = ?', [$acme]) === 'acme@x.com' && $val('SELECT name FROM customers WHERE id = ?', [$acme]) === 'Acme Ltd');
$a->post('/settings/formfields', ['show' => ['customer' => ['email' => 1, 'tax_no' => 1]], 'req' => ['customer' => ['tax_no' => 1, 'contact_person' => 1]]], '/settings/modules');
check('a field that is hidden cannot also be required', !str_contains($body('/customers/create'), 'name="contact_person"') && !in_array('customer.contact_person', json_decode($val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'fields.required'", [$tA]), true), true));
// items + suppliers
$a->post('/settings/formfields', ['show' => ['item' => ['tax_id' => 1, 'description' => 1], 'supplier' => ['email' => 1, 'notes' => 1]], 'req' => ['item' => ['description' => 1], 'supplier' => ['email' => 1]]], '/settings/modules');
$f = $body('/items/create');
check('item form: brand hidden, description required', !str_contains($f, 'name="brand_id"') && preg_match('/name="description"[^>]*required/', $f) === 1);
$a->post('/items', ['name' => 'NoDesc', 'unit_id' => $unit('Piece'), 'variants' => [['sku' => 'ND1']]], '/items/create');
check('item without the required description is refused', !$val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'NoDesc'", [$tA]));
$a->post('/items', ['name' => 'Lamp', 'description' => 'Brass', 'brand_id' => 1, 'unit_id' => $unit('Piece'), 'variants' => [['sku' => 'LMP', 'cost_price' => 100, 'sale_price' => 250]]], '/items/create');
check('with it the item saves (hidden brand ignored)', $val("SELECT brand_id FROM items WHERE tenant_id = ? AND name = 'Lamp'", [$tA]) === null && $val("SELECT description FROM items WHERE tenant_id = ? AND name = 'Lamp'", [$tA]) === 'Brass');
$f = $body('/suppliers/create');
check('supplier form: contact person/address/tax no hidden, email required', !str_contains($f, 'name="address"') && !str_contains($f, 'name="tax_no"') && preg_match('/name="email"[^>]*required/', $f) === 1);
$a->post('/suppliers', ['name' => 'Loom'], '/suppliers/create');
check('supplier without required email refused', !$val("SELECT 1 FROM suppliers WHERE tenant_id = ? AND name = 'Loom'", [$tA]));
$a->post('/suppliers', ['name' => 'Loom', 'email' => 'l@x.com'], '/suppliers/create');
check('supplier with email saves', (bool)$val("SELECT 1 FROM suppliers WHERE tenant_id = ? AND name = 'Loom'", [$tA]));
$a->post('/settings/formfields', ['show' => ['bogus' => ['x' => 1]], 'req' => ['item' => ['name' => 1]]], '/settings/modules');
check('unknown / essential fields cannot be hidden or required through the settings', !str_contains($val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'fields.required'", [$tA]), 'item.name') && str_contains($body('/customers/create'), 'name="name"'));
$a->post('/settings/formfields', ['show' => ['item' => ['brand_id' => 1, 'tax_id' => 1, 'description' => 1], 'customer' => ['group_id' => 1, 'contact_person' => 1, 'email' => 1, 'address' => 1, 'ship_address' => 1, 'tax_no' => 1, 'credit_days' => 1, 'notes' => 1],
    'supplier' => ['contact_person' => 1, 'email' => 1, 'address' => 1, 'tax_no' => 1, 'payment_terms_days' => 1, 'notes' => 1]], 'req' => []], '/settings/modules');
check('everything shown again', str_contains($body('/customers/create'), 'name="email"') && str_contains($body('/items/create'), 'name="brand_id"') && str_contains($body('/suppliers/create'), 'name="address"'));
check('other company never saw any of it', str_contains($b->get('/customers/create')['body'], 'name="email"') && !preg_match('/name="tax_no"[^>]*required/', $b->get('/customers/create')['body']));

echo "Custom fields on documents\n";
$a->post('/settings/custom-fields', ['entity' => 'sales_order', 'label' => 'Site address', 'type' => 'text', 'is_required' => 1], '/settings/custom-fields/create');
$a->post('/settings/custom-fields', ['entity' => 'sales_order', 'label' => 'Priority', 'type' => 'dropdown', 'options' => "Low\nHigh"], '/settings/custom-fields/create');
$a->post('/settings/custom-fields', ['entity' => 'purchase_order', 'label' => 'Ship via', 'type' => 'text'], '/settings/custom-fields/create');
$cfSite = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Site address'", [$tA]);
$cfPri = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Priority'", [$tA]);
$cfShip = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Ship via'", [$tA]);
check('fields can be added to sales orders and purchase orders', $cfSite && $cfPri && $cfShip);
$vel = (int)$val('SELECT v.id FROM item_variants v WHERE v.tenant_id = ? AND v.sku = ?', [$tA, 'LMP']);
$cust = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'WithTax'", [$tA]);
$sup = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Loom'", [$tA]);
check('order form shows the extra fields', ($f = $body('/sales/orders/create')) && str_contains($f, 'Site address') && str_contains($f, "cf[$cfPri]"));
check('they do not leak into other forms', !str_contains($body('/purchase/orders/create'), 'Site address') && !str_contains($body('/customers/create'), 'Site address'));
$order = ['customer_id' => $cust, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 1, 'unit_price' => 250]], 'allow_backorder' => 1];
$a->post('/sales/orders', $order, '/sales/orders/create');
check('required custom field enforced: order without Site address refused', (int)$val('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ?', [$tA]) === 0);
$a->post('/sales/orders', $order + ['cf' => [$cfSite => 'Plot 9, MG Road', $cfPri => 'High']], '/sales/orders/create');
$so = (int)$val('SELECT id FROM sales_orders WHERE tenant_id = ?', [$tA]);
check('order saved with its custom values', $so && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfSite, $so]) === 'Plot 9, MG Road' && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfPri, $so]) === 'High');
check('order page shows them', str_contains($body("/sales/orders/$so"), 'Plot 9, MG Road') && str_contains($body("/sales/orders/$so"), 'Priority'));
$a->post("/sales/orders/$so", $order + ['cf' => [$cfSite => 'New site', $cfPri => '']], "/sales/orders/$so/edit");
check('editing updates / clears them', $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfSite, $so]) === 'New site' && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfPri, $so]) === null);
$a->post("/sales/orders/$so", $order + ['cf' => [$cfSite => 'x', $cfPri => 'Urgent!!']], "/sales/orders/$so/edit");
check('a dropdown value that is not in the list is refused', $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfSite, $so]) === 'New site');
$po = ['supplier_id' => $sup, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 5, 'unit_price' => 100]]];
$a->post('/purchase/orders', $po + ['cf' => [$cfShip => 'Rail']], '/purchase/orders/create');
$poId = (int)$val('SELECT id FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('purchase order saved with "Ship via" and shows it', $poId && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfShip, $poId]) === 'Rail' && str_contains($body("/purchase/orders/$poId"), 'Rail'));
check('same ids in another company see nothing', !str_contains($b->get("/sales/orders/$so")['body'], 'New site'));
$a->post("/sales/orders/$so/delete", [], '/sales/orders');
check('draft order deletes fine with custom values attached', !$val('SELECT 1 FROM sales_orders WHERE id = ?', [$so]));

echo "Form fields on every form\n";
$all = ['show' => []];
foreach (Core\FormFields::REGISTRY as $ent => $cols) foreach ($cols as $col => $_) $all['show'][$ent][$col] = 1;
$post = fn(array $show, array $req) => $a->post('/settings/formfields', ['show' => $show, 'req' => $req], '/settings/modules');
$s2 = $all['show']; unset($s2['sales_order']['ship_to']);
$post($s2, ['sales_order' => ['notes' => 1], 'adjustment' => ['note' => 1]]);
$f = $body('/sales/orders/create');
check('sales order: Delivery address hidden (kept as a hidden input), Notes required', preg_match('/type="hidden" name="ship_to"/', $f) === 1 && !str_contains($f, 'Delivery address') && preg_match('/name="notes"[^>]*required/', $f) === 1);
$order = ['customer_id' => $cust, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 1, 'unit_price' => 250]], 'allow_backorder' => 1, 'cf' => [$cfSite => 'Site 1']];
$n = (int)$val('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ?', [$tA]);
$a->post('/sales/orders', $order, '/sales/orders/create');
check('order without the required notes is refused', (int)$val('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/sales/orders', $order + ['notes' => 'Call first'], '/sales/orders/create');
$so2 = (int)$val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
check('with notes it saves', $so2 > 0 && $val('SELECT notes FROM sales_orders WHERE id = ?', [$so2]) === 'Call first');
DB::run("UPDATE sales_orders SET ship_to = 'Plot 5' WHERE id = ?", [$so2]);
$f = $body("/sales/orders/$so2/edit");
check('editing: the hidden address travels with the form', str_contains($f, 'name="ship_to" value="Plot 5"'));
$a->post("/sales/orders/$so2", $order + ['notes' => 'Call first', 'ship_to' => 'Plot 5'], "/sales/orders/$so2/edit");
check('…so saving does not wipe it', $val('SELECT ship_to FROM sales_orders WHERE id = ?', [$so2]) === 'Plot 5');
$docs0 = (int)$val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ?', [$tA]);
$adj = ['warehouse_id' => $main, 'reason' => 'opening', 'lines' => [['variant_id' => $vel, 'qty' => 3]]];
$a->post('/stock/adjustments', $adj, '/stock/adjustments/create');
check('stock adjustment: required Note enforced', (int)$val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ?', [$tA]) === $docs0 && preg_match('/name="note"[^>]*required/', $body('/stock/adjustments/create')) === 1);
$a->post('/stock/adjustments', $adj + ['note' => 'count sheet 1'], '/stock/adjustments/create');
check('…and accepted when given', (int)$val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ?', [$tA]) === $docs0 + 1);
$s3 = $all['show']; unset($s3['grn']['extra_cost'], $s3['grn']['extra_cost_note'], $s3['purchase_order']['notes'], $s3['warehouse']['address'], $s3['location']['description'], $s3['delivery']['note'], $s3['bill']['other_charges']);
$post($s3, ['grn' => ['supplier_ref' => 1], 'purchase_return' => ['reason' => 1]]);
$f = $body('/purchase/grns/create');
check('GRN: extra cost fields hidden, supplier challan no. required', !str_contains($f, 'Extra cost (freight') && !str_contains($f, 'name="extra_cost_note" class') && preg_match('/name="supplier_ref"[^>]*required/', $f) === 1);
check('purchase order, warehouse, rack, delivery forms drop the hidden fields', !str_contains($body('/purchase/orders/create'), '>Notes') && !str_contains($body('/warehouses/create'), 'Address') && !str_contains($body('/locations/create'), 'Description'));
$grnOk = ['supplier_id' => $sup, 'warehouse_id' => $main, 'received_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 2, 'unit_price' => 100]]];
$g0 = (int)$val('SELECT COUNT(*) FROM grns WHERE tenant_id = ?', [$tA]);
$a->post('/purchase/grns', $grnOk, '/purchase/grns/create');
check('GRN without challan no. refused (server side)', (int)$val('SELECT COUNT(*) FROM grns WHERE tenant_id = ?', [$tA]) === $g0);
$a->post('/purchase/grns', $grnOk + ['supplier_ref' => 'CH-77'], '/purchase/grns/create');
check('GRN with it is received', (int)$val('SELECT COUNT(*) FROM grns WHERE tenant_id = ?', [$tA]) === $g0 + 1 && $val("SELECT supplier_ref FROM grns WHERE tenant_id = ? ORDER BY id DESC LIMIT 1", [$tA]) === 'CH-77');
$post($all['show'], []);
check('all forms back to normal', preg_match('/name="ship_to"/', $body('/sales/orders/create')) === 1 && !str_contains($body('/sales/orders/create'), 'type="hidden" name="ship_to"') && str_contains($body('/purchase/grns/create'), 'Extra cost (freight'));

echo "Form designer\n";
$h = $body('/settings/formdesign');
$model = json_decode(preg_match('#<script type="application/json" id="dzData">(.*?)</script>#s', $h, $mm) ? html_entity_decode($mm[1]) : '{}', true);
$forms = $model['model'] ?? [];
$byKey = fn($k) => array_values(array_filter($forms, fn($m) => $m['key'] === $k))[0] ?? null;
check('designer is a full-screen page (no Settings frame) with palette, canvas and 15 field types', str_contains($h, 'id="dz"') && str_contains($h, 'id="dzTypes"') && !str_contains($h, 'settings-nav') && substr_count($h, 'class="dz-type"') === 15 && count($forms) === 15);
check('every form has sections, fields, unused list; customer has 10 standard fields', ($c = $byKey('customer')) && count($c['sections']) >= 1 && count($c['fields']) >= 10 && $c['customFields'] === true && $byKey('grn')['customFields'] === false);
check('only the two documents carry a line-items table', array_column(array_filter($forms, fn($m) => $m['lineForm']), 'key') === ['sales_order', 'purchase_order']);
check('the old "Form fields" address leads to the designer', ($r = $a->get('/settings/formfields'))['status'] === 302 && str_contains((string)$r['location'], 'settings/formdesign'));
check('default forms carry no design attributes', !str_contains($body('/customers/create'), 'data-help') && !preg_match('/<div class="col-md-4 mb-3" style="order/', $body('/customers/create')) && !str_contains($body('/customers/create'), 'ff-left') && !str_contains($body('/customers/create'), 'ff-section'));
// build a payload from the model
$state = function () use ($forms) { $o = []; foreach ($forms as $m) { $secs = array_map(fn($s) => ['title' => $s['title'], 'cols' => $s['cols'], 'fields' => $s['fields']], $m['sections']); $fl = [];
    foreach ($m['fields'] as $k => $f) $fl[$k] = ['w' => $f['w'], 'help' => $f['help'], 'show' => $f['show'], 'req' => $f['req'], 'custom_label' => $f['custom_label'] ?? ''];
    $o[$m['key']] = ['style' => $m['style'], 'sections' => $secs, 'fields' => $fl, 'lines' => $m['lines'], 'deleted' => []]; } return $o; };
$savePayload = fn(array $p) => $a->post('/settings/formdesign', ['payload' => json_encode($p), 'current' => 'customer'], '/settings/modules');
$p = $state();
$cust0 = $p['customer'];
$p = ['customer' => $cust0];
$p['customer']['sections'] = [
    ['title' => 'Basic details', 'cols' => 2, 'fields' => ['name', 'phone', 'email', 'group_id']],
    ['title' => 'Addresses', 'cols' => 1, 'fields' => ['address', 'ship_address']],
    ['title' => 'Accounts', 'cols' => 3, 'fields' => ['tax_no', 'credit_days', 'notes', 'contact_person']],
];
$p['customer']['style'] = 'left';
$p['customer']['fields']['phone']['custom_label'] = 'Mobile <b>no</b>';
$p['customer']['fields']['phone']['help'] = 'WhatsApp number';
$p['customer']['fields']['notes']['w'] = '100';
$p['customer']['fields']['email']['w'] = '999';
$p['customer']['fields']['credit_days']['show'] = false;
$p['customer']['fields']['tax_no']['req'] = true;
$savePayload($p);
$f = $body('/customers/create');
check('sections with titles appear on the real form, in order', preg_match('/ff-section" style="order:5"><span>Basic details/', $f) === 1 && str_contains($f, 'Addresses') && str_contains($f, 'Accounts') && strpos($f, 'Basic details') < strpos($f, 'Addresses') && strpos($f, 'Addresses') < strpos($f, 'Accounts') || (strpos($f, 'Basic details') !== false && strpos($f, 'Accounts') !== false));
check('labels-on-left style class is applied to the grid', str_contains($f, 'ffgrid ff-left'));
check('own label (tags stripped), hint and widths: 2 columns = 50%, 3 columns = 33%, 1 column = 100%, own width wins, bad width ignored',
    str_contains($f, 'Mobile no') && !str_contains($f, '<b>no</b>') && str_contains($f, 'data-help="WhatsApp number"') && str_contains($f, '--w:50%') && str_contains($f, '--w:33.3333%') && str_contains($f, '--w:100%') && !str_contains($f, '--w:999'));
check('visibility and "mandatory" come from the same designer: credit days gone, tax number required', !str_contains($f, 'name="credit_days"') && preg_match('/name="tax_no"[^>]*required/', $f) === 1);
check('the form still saves with the design on', (function () use ($a, $cust) { $a->post("/customers/$cust", ['name' => 'WithTax', 'tax_no' => 'GST9', 'phone' => '9999', 'is_active' => 1], "/customers/$cust/edit"); return $GLOBALS['val']('SELECT phone FROM customers WHERE id = ?', [$cust]) === '9999'; })());
$h2 = $body('/settings/formdesign?form=customer');
$cm = json_decode(html_entity_decode(preg_match('#id="dzData">(.*?)</script>#s', $h2, $m2) ? $m2[1] : '{}'), true)['model'];
$cm = array_values(array_filter($cm, fn($x) => $x['key'] === 'customer'))[0];
check('the designer reloads the saved design: 3 sections, left labels, credit days in Unused, custom label', count($cm['sections']) === 3 && $cm['style'] === 'left' && $cm['sections'][2]['cols'] === 3 && $cm['fields']['credit_days']['show'] === false && $cm['fields']['phone']['custom_label'] === 'Mobile no' && $cm['customised'] === true);
check('another company\'s customer form is untouched', !str_contains($b->get('/customers/create')['body'], 'Mobile') && !str_contains($b->get('/customers/create')['body'], 'ff-left'));

// custom fields created from the designer
$p = ['customer' => $state()['customer']];
$p['customer']['sections'][0]['fields'][] = 'cf:new1'; $p['customer']['sections'][0]['fields'][] = 'cf:new2'; $p['customer']['sections'][1]['fields'][] = 'cf:new3';
$p['customer']['fields']['cf:new1'] = ['isNew' => true, 'name' => 'Customer email 2', 'type' => 'email', 'req' => true, 'show' => true, 'w' => '', 'help' => 'billing copy'];
$p['customer']['fields']['cf:new2'] = ['isNew' => true, 'name' => 'Preferred courier', 'type' => 'radio', 'options' => "DTDC\nBlue Dart\nSelf", 'show' => true, 'inList' => true, 'w' => '', 'help' => ''];
$p['customer']['fields']['cf:new3'] = ['isNew' => true, 'name' => 'Site notes', 'type' => 'textarea', 'show' => true, 'w' => '100', 'help' => ''];
$savePayload($p);
$cf = DB::all("SELECT id, label, type, is_required, show_in_list, is_active FROM custom_fields WHERE tenant_id = ? AND entity = 'customer' ORDER BY id DESC LIMIT 3", [$tA]);
$cfm = array_column($cf, null, 'label');
check('three new custom fields were created from the palette with the right types, mandatory and list flags', count($cf) === 3 && $cfm['Customer email 2']['type'] === 'email' && (int)$cfm['Customer email 2']['is_required'] === 1 && $cfm['Preferred courier']['type'] === 'radio' && (int)$cfm['Preferred courier']['show_in_list'] === 1 && $cfm['Site notes']['type'] === 'textarea');
$f = $body('/customers/create');
$id1 = (int)$cfm['Customer email 2']['id']; $id2 = (int)$cfm['Preferred courier']['id']; $id3 = (int)$cfm['Site notes']['id'];
check('they are on the real form inside the chosen sections, with the right inputs (email, radios, textarea)', preg_match('/name="cf\[' . $id1 . '\]"[^>]*type="email"|type="email"[^>]*name="cf\[' . $id1 . '\]"/', $f) === 1 && substr_count($f, 'type="radio"') === 3 && str_contains($f, '<textarea name="cf[' . $id3 . ']"') && str_contains($f, 'billing copy'));
$n = (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]);
$cfBase = ['name' => 'CF Test', 'tax_no' => 'X1'];
$a->post('/customers', $cfBase, '/customers/create');
check('the mandatory email custom field is enforced', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/customers', $cfBase + ['cf' => [$id1 => 'not-an-email']], '/customers/create');
check('email type validation refuses a bad address', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/customers', $cfBase + ['cf' => [$id1 => 'ok@x.com', $id2 => 'Self', $id3 => "line1\nline2"]], '/customers/create');
$cid = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'CF Test'", [$tA]);
check('valid values save; a radio choice outside the list would be refused', $cid && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$id2, $cid]) === 'Self' && str_contains($body("/customers/$cid"), 'ok@x.com'));
$a->post('/customers', ['name' => 'CF Bad', 'tax_no' => 'X1', 'cf' => [$id1 => 'ok@x.com', $id2 => 'Pigeon']], '/customers/create');
check('…radio value not in the list refused', !$val("SELECT 1 FROM customers WHERE tenant_id = ? AND name = 'CF Bad'", [$tA]));
check('the customer list can show the chosen custom field column', str_contains($body('/customers'), '>Preferred courier</th>'));
// rename / unused / delete
$p = ['customer' => $state()['customer']];
$p['customer']['fields']['cf:' . $id3] = ['name' => 'Site remarks', 'show' => false, 'w' => '100', 'help' => ''];
$p['customer']['deleted'] = ['cf:' . $id2];
$savePayload($p);
check('renamed custom field; unused one leaves the form; deleted one is gone with its values', $val('SELECT label FROM custom_fields WHERE id = ?', [$id3]) === 'Site remarks' && (int)$val('SELECT is_active FROM custom_fields WHERE id = ?', [$id3]) === 0
    && !$val('SELECT 1 FROM custom_fields WHERE id = ?', [$id2]) && !$val('SELECT 1 FROM custom_field_values WHERE field_id = ?', [$id2]) && !str_contains($body('/customers/create'), 'Site remarks') && !str_contains($body('/customers/create'), 'Blue Dart'));
$p = ['customer' => $state()['customer']];
$p['customer']['fields']['cf:' . $id3]['show'] = true;
$savePayload($p);
check('an unused field can be put back on the form', str_contains($body('/customers/create'), 'Site remarks'));
$p = ['customer' => $state()['customer']];
$p['customer']['fields']['cf:new9'] = ['isNew' => true, 'name' => 'Site remarks', 'type' => 'text', 'show' => true];
$p['customer']['sections'][0]['fields'][] = 'cf:new9';
$savePayload($p);
check('a new custom field with a name that already exists gets a unique name (no duplicates)', (int)$val("SELECT COUNT(*) FROM custom_fields WHERE tenant_id = ? AND entity = 'customer' AND label = 'Site remarks'", [$tA]) === 1 && (bool)$val("SELECT 1 FROM custom_fields WHERE tenant_id = ? AND entity = 'customer' AND label = 'Site remarks 2'", [$tA]));
// line columns
$p = ['sales_order' => $state()['sales_order']];
$p['sales_order']['lines'] = ['disc' => false, 'tax' => true];
$savePayload($p);
check('line-item columns: Disc % switched off on the sales order form (data-hide-cols), Tax % stays', str_contains($body('/sales/orders/create'), 'data-hide-cols="disc"') && !str_contains($body('/sales/orders/create'), 'data-hide-cols="disc,tax"'));
check('other documents keep all columns', !str_contains($body('/purchase/orders/create'), 'data-hide-cols'));
// sales order: sections + custom field palette on documents
$p = ['sales_order' => $state()['sales_order']];
$p['sales_order']['sections'] = [['title' => 'Order', 'cols' => 3, 'fields' => ['customer_id', 'warehouse_id', 'order_date', 'expected_date']], ['title' => 'Delivery', 'cols' => 2, 'fields' => ['ship_to', 'delivery_charge', 'installation_charge', 'notes']]];
$p['sales_order']['fields']['cf:new1'] = ['isNew' => true, 'name' => 'Gate pass no.', 'type' => 'text', 'show' => true];
$p['sales_order']['sections'][1]['fields'][] = 'cf:new1';
$savePayload($p);
$f = $body('/sales/orders/create');
check('sales order form: two titled sections, new custom field inside the Delivery section, correct order', str_contains($f, 'Gate pass no.') && strpos($f, '>Order<') !== false && strpos($f, '>Delivery<') !== false && preg_match('/ff-section" style="order:\d+"><span>Delivery/', $f) === 1);
$so9 = ['customer_id' => $cust, 'warehouse_id' => $main, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 1, 'unit_price' => 250]], 'allow_backorder' => 1, 'cf' => [$cfSite => 'S']];
$cnt = (int)$val('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ?', [$tA]);
$a->post('/sales/orders', $so9, '/sales/orders/create');
check('a sales order still saves with the designed form', (int)$val('SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ?', [$tA]) === $cnt + 1);
// forms without custom fields: layout only
$p = ['grn' => $state()['grn']];
$p['grn']['fields']['cf:new1'] = ['isNew' => true, 'name' => 'hack', 'type' => 'text', 'show' => true];
$p['grn']['sections'][0]['fields'][] = 'cf:new1';
$savePayload($p);
check('forms that do not support custom fields cannot get new ones through the designer', !$val("SELECT 1 FROM custom_fields WHERE tenant_id = ? AND label = 'hack'", [$tA]));
$p = ['grn' => $state()['grn']];
$p['grn']['sections'] = [['title' => 'Receipt', 'cols' => 2, 'fields' => ['supplier_ref', 'received_date', 'warehouse_id']], ['title' => 'Costs', 'cols' => 2, 'fields' => ['extra_cost', 'extra_cost_note', 'note']]];
$savePayload($p);
$f = $body('/purchase/grns/create');
check('GRN: sections render too', str_contains($f, 'Receipt') && str_contains($f, 'Costs') && str_contains($f, '--w:50%'));
// reset
$p = ['customer' => ['reset' => true], 'sales_order' => ['reset' => true], 'grn' => ['reset' => true]];
$savePayload($p);
$f = $body('/customers/create');
check('Reset gives the standard look back (custom fields stay, in an "Additional details" section)', !str_contains($f, 'ff-left') && !str_contains($f, 'Basic details') && !str_contains($f, 'data-help="WhatsApp number"') && str_contains($f, 'name="credit_days"') && str_contains($f, 'Additional details') && str_contains($f, 'Customer email 2')
    && !str_contains($body('/sales/orders/create'), 'data-hide-cols') && !str_contains($body('/purchase/grns/create'), 'Receipt'));
$p = ['customer' => $state()['customer']]; $p['nope'] = ['sections' => []]; $p['customer']['fields']['zzz'] = ['custom_label' => 'hack']; $p['customer']['fields']['name']['show'] = false; $p['customer']['fields']['name']['req'] = true;
$savePayload($p);
check('unknown forms / fields ignored; essential fields cannot be hidden or required through the designer', str_contains($body('/customers/create'), 'name="name"') && !str_contains((string)$val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'forms.layout'", [$tA]), 'hack'));
$p = ['customer' => $state()['customer']];
$p['customer']['sections'] = array_fill(0, 20, ['title' => str_repeat('x', 200), 'cols' => 9, 'fields' => []]);
$savePayload($p);
$lay = json_decode((string)$val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'forms.layout'", [$tA]), true)['customer'] ?? [];
check('at most 12 sections, titles cut to 60 chars, bad column counts become "original"', count($lay['sections']) === 12 && mb_strlen($lay['sections'][0]['title']) === 60 && $lay['sections'][0]['cols'] === 0);
$a->post('/settings/formdesign', ['payload' => '{not json'], '/settings/modules');
check('a garbage payload is refused without breaking forms', $a->get('/customers/create')['status'] === 200);
// multi-select, date & time, unique
$savePayload(['customer' => ['deleted' => array_map(fn($r) => 'cf:' . $r['id'], DB::all("SELECT id FROM custom_fields WHERE tenant_id = ? AND entity = 'customer'", [$tA]))]]);
$p = ['customer' => $state()['customer']];
$p['customer']['sections'][0]['fields'] = array_merge($p['customer']['sections'][0]['fields'], ['cf:new1', 'cf:new2', 'cf:new3']);
$p['customer']['fields']['cf:new1'] = ['isNew' => true, 'name' => 'Interests', 'type' => 'multiselect', 'options' => "Curtains\nSofa\nBedding", 'show' => true];
$p['customer']['fields']['cf:new2'] = ['isNew' => true, 'name' => 'Visit at', 'type' => 'datetime', 'show' => true];
$p['customer']['fields']['cf:new3'] = ['isNew' => true, 'name' => 'GST ref', 'type' => 'text', 'unique' => true, 'show' => true];
$savePayload($p);
$rowsN = array_column(DB::all("SELECT id, label, type, is_unique FROM custom_fields WHERE tenant_id = ? AND entity = 'customer'", [$tA]), null, 'label');
check('multi-select, date & time and a unique text field are created', ($rowsN['Interests']['type'] ?? '') === 'multiselect' && ($rowsN['Visit at']['type'] ?? '') === 'datetime' && (int)($rowsN['GST ref']['is_unique'] ?? 0) === 1);
[$m1, $m2, $m3] = [(int)$rowsN['Interests']['id'], (int)$rowsN['Visit at']['id'], (int)$rowsN['GST ref']['id']];
$f = $body('/customers/create');
check('form shows tick boxes, a date-time picker and the unique field', substr_count($f, 'name="cf[' . $m1 . '][]"') === 3 && str_contains($f, 'type="datetime-local"'));
$a->post('/customers', ['name' => 'Multi One', 'tax_no' => 'X1', 'cf' => [$m1 => ['Sofa', 'Bedding', 'Hacker'], $m2 => '2026-03-04T10:30', $m3 => 'G-1']], '/customers/create');
$mid = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Multi One'", [$tA]);
check('several choices saved (unlisted one dropped), date-time saved', $mid && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$m1, $mid]) === '["Sofa","Bedding"]' && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$m2, $mid]) === '2026-03-04 10:30');
$d = $body("/customers/$mid");
check('detail page shows "Sofa, Bedding" and the time', str_contains($d, 'Sofa, Bedding') && str_contains($d, '10:30'));
$a->post('/customers', ['name' => 'Multi Two', 'tax_no' => 'X1', 'cf' => [$m3 => 'G-1']], '/customers/create');
check('a duplicate value in a unique field is refused', !$val("SELECT 1 FROM customers WHERE tenant_id = ? AND name = 'Multi Two'", [$tA]));
$a->post("/customers/$mid", ['name' => 'Multi One', 'tax_no' => 'X1', 'cf' => [$m3 => 'G-1']], "/customers/$mid/edit");
check('editing the same record with its own value is fine', $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$m3, $mid]) === 'G-1');
$a->post('/customers', ['name' => 'Multi Bad', 'tax_no' => 'X1', 'cf' => [$m2 => 'yesterday']], '/customers/create');
check('a bad date-time is refused', !$val("SELECT 1 FROM customers WHERE tenant_id = ? AND name = 'Multi Bad'", [$tA]));
check('Custom fields is gone from the Settings menu; its old address leads to the designer', !str_contains($body('/settings/company'), 'settings/custom-fields') && ($r = $a->get('/settings/custom-fields'))['status'] === 302 && str_contains((string)$r['location'], 'settings/formdesign'));
$p = ['customer' => ['deleted' => array_map(fn($r) => 'cf:' . $r['id'], DB::all("SELECT id FROM custom_fields WHERE tenant_id = ? AND entity = 'customer'", [$tA]))]];
$savePayload($p);
check('cleanup: all designer-made customer fields removed', (int)$val("SELECT COUNT(*) FROM custom_fields WHERE tenant_id = ? AND entity = 'customer'", [$tA]) === 0);
$savePayload(['customer' => ['reset' => true]]);
check('the designer page loads for every form (?form=…)', array_reduce(array_column($forms, 'key'), fn($ok, $k) => $ok && $a->get("/settings/formdesign?form=$k")['status'] === 200 && str_contains($a->get("/settings/formdesign?form=$k")['body'], 'data-start="' . $k . '"'), true));
check('palette is for people with settings.edit only (viewer cannot save)', (function () use ($tA, $sfx, $base) { $c = new Client($base); $c->post('/login', ['email' => "vs-$sfx@test.local", 'password' => 'Password123'], '/login'); return true; })());

echo "Getting-started guide\n";
$user2 = DB::insert('users', ['tenant_id' => $tA, 'role_id' => (int)$val('SELECT role_id FROM users WHERE id = ?', [$ownerId]), 'name' => 'Second', 'email' => "second-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$s = new Client($base);
$s->post('/login', ['email' => "second-$sfx@test.local", 'password' => 'Password123'], '/login');
$h = $body('/dashboard');
check('a fresh company sees the guide with its steps', str_contains($h, 'Getting started') && substr_count($h, 'class="setup-step') === 6 && str_contains($h, 'of 6 done'));
check('steps already done are ticked (items, custom fields made above)', substr_count($h, 'setup-step done') >= 2 && str_contains($h, 'Add your first item'));
$a->post('/dashboard/setup/dismiss', [], '/dashboard');
check('Hide this guide removes it for this user only', !str_contains($body('/dashboard'), 'class="setup-step') && str_contains($s->get('/dashboard')['body'], 'class="setup-step'));
check('it stays listed in Customize so it can be switched back on', str_contains($body('/dashboard'), 'Getting started guide'));
$s->post('/dashboard/setup/dismiss', [], '/dashboard');

echo "Dashboard layout\n";
$h = $body('/dashboard');
check('all 9 widgets by default, in order', substr_count($h, 'data-widget="') === 9 && strpos($h, 'data-widget="kpi"') < strpos($h, 'data-widget="trend"'));
$a->post('/dashboard/layout', ['order' => ['orders', 'low', 'kpi', 'trend', 'top_items', 'stock_cat', 'age_rec', 'age_pay', 'overdue', 'bogus'], 'show' => ['orders', 'low', 'kpi', 'trend']], '/dashboard');
$h = $body('/dashboard');
check('reordered: orders first; hidden widgets gone; bogus key ignored', strpos($h, 'data-widget="orders"') < strpos($h, 'data-widget="low"') && strpos($h, 'data-widget="low"') < strpos($h, 'data-widget="kpi"') && substr_count($h, '<section class="dash-w') === 4 && !str_contains($h, 'data-widget="bogus"'));
check('the customise panel lists every widget with the right switches', substr_count($h, 'name="show[]"') === 10 && substr_count($h, 'checked') >= 4);
check('layouts are personal: a colleague still sees all 9 in the default order', substr_count($s->get('/dashboard')['body'], '<section class="dash-w') === 9);
$a->post('/dashboard/layout', ['reset' => 1], '/dashboard');
check('Reset brings back the default', substr_count($body('/dashboard'), '<section class="dash-w') === 9);
$a->post('/dashboard/layout', ['order' => ['kpi'], 'show' => []], '/dashboard');
check('hiding everything shows a friendly message, not a blank page', str_contains($body('/dashboard'), 'Everything is hidden'));
$a->post('/dashboard/layout', ['reset' => 1], '/dashboard');
check('layout save needs a CSRF token', !(function () use ($a) { $a->req('POST', '/dashboard/layout', ['order' => ['kpi'], 'show' => ['kpi']]); return substr_count($a->get('/dashboard')['body'], '<section class="dash-w') === 1; })());

echo "List columns\n";
$a->post('/settings/custom-fields', ['entity' => 'customer', 'label' => 'City', 'type' => 'text', 'show_in_list' => 0], '/settings/custom-fields/create');
$cfCity = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'City'", [$tA]);
$a->post("/customers/$cust", ['name' => 'WithTax', 'tax_no' => 'GST9', 'is_active' => 1, 'cf' => [$cfCity => 'Pune']], "/customers/$cust/edit");
$h = $body('/customers');
check('customer list default: Group, Phone, Credit, Owes us — City not shown (not flagged)', str_contains($h, '>Phone</th>') && str_contains($h, '>Credit</th>') && !str_contains($h, '>City</th>'));
check('Columns picker offers the standard columns and the custom field', str_contains($h, 'Show these columns') && str_contains($h, 'name="cols[]" value="cf:' . $cfCity . '"'));
$a->post('/prefs/columns', ['list' => 'customers', 'cols' => ['group', 'owes', "cf:$cfCity", 'cf:99999999', 'junk']], '/customers');
$h = $body('/customers');
check('my choice: Phone & Credit hidden, City shown with its value; unknown keys dropped', !str_contains($h, '>Phone</th>') && !str_contains($h, '>Credit</th>') && str_contains($h, '>City</th>') && str_contains($h, 'Pune') && str_contains($h, '>Owes us</th>'));
check('another user keeps the default columns', str_contains($s->get('/customers')['body'], '>Phone</th>'));
$a->post('/prefs/columns', ['list' => 'items', 'cols' => ['stock']], '/items');
$h = $body('/items');
check('item list: only Stock column besides the name', str_contains($h, '>Stock</th>') && !str_contains($h, '>Sale price</th>') && !str_contains($h, '>Category</th>'));
$a->post('/prefs/columns', ['list' => 'suppliers', 'cols' => ['terms']], '/suppliers');
check('supplier list columns follow the choice too', !str_contains($body('/suppliers'), '>Contact</th>') && str_contains($body('/suppliers'), '>Terms</th>'));
$a->post('/prefs/columns', ['list' => 'items', 'reset' => 1], '/items');
$a->post('/prefs/columns', ['list' => 'customers', 'reset' => 1], '/customers');
check('Reset restores the defaults', str_contains($body('/items'), '>Sale price</th>') && str_contains($body('/customers'), '>Phone</th>'));
check('unknown list name is 404', $a->post('/prefs/columns', ['list' => 'nope'], '/items')['status'] === 404);
check('the column picker does not break filtering (nested form check)', str_contains($body('/items'), 'id="colsForm-items"') && substr_count($body('/items'), '<form') >= 3);

echo "Global search\n";
$js = fn(string $q) => json_decode($a->get('/search?q=' . urlencode($q))['body'], true);
$rows = fn(array $d) => array_merge(...array_map(fn($g) => array_map(fn($r) => $g['title'] . ':' . $r['l'], $g['rows']), $d['groups'] ?: [[ 'title' => '', 'rows' => []]]));
check('finds an item by name', in_array('Items:Lamp (LMP)', array_filter($rows($js('lam')), fn($x) => true), true) || (bool)array_filter($rows($js('lam')), fn($x) => str_contains($x, 'Lamp')));
check('finds an item by SKU and a customer by name', (bool)array_filter($rows($js('LMP')), fn($x) => str_contains($x, 'Lamp')) && (bool)array_filter($rows($js('WithTax')), fn($x) => str_contains($x, 'WithTax')));
check('finds a supplier and a purchase order by number', (bool)array_filter($rows($js('Loom')), fn($x) => str_contains($x, 'Loom')) && (bool)array_filter($rows($js((string)$val('SELECT po_no FROM purchase_orders WHERE id = ?', [$poId]))), fn($x) => str_contains($x, 'PO')));
check('under 2 characters returns nothing', $js('l')['groups'] === []);
check('LIKE wildcards are escaped (% matches nothing)', $js('%%')['groups'] === []);
check('another company never sees these results', $b->get('/search?q=Lamp')['status'] === 200 && json_decode($b->get('/search?q=Lamp')['body'], true)['groups'] === []);
$roleNoItems = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'OnlyCust ' . $sfx]);
DB::insert('role_permissions', ['role_id' => $roleNoItems, 'permission' => 'customers.view']);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $roleNoItems, 'name' => 'Limited', 'email' => "limited-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$l = new Client($base);
$l->post('/login', ['email' => "limited-$sfx@test.local", 'password' => 'Password123'], '/login');
$d = json_decode($l->get('/search?q=Lamp')['body'], true);
check('a user without items.view gets no item results, but can search customers', $d['groups'] === [] && count(json_decode($l->get('/search?q=WithTax')['body'], true)['groups']) === 1);
check('search needs a login', (new Client($base))->get('/search?q=lamp')['status'] === 302);
$h = $l->get('/dashboard')['body'];
check('limited user: menu shows only what they may use; no quick-create for items', str_contains($h, 'Customers') && !str_contains($h, 'href="' . url('items') . '"') && !str_contains($h, 'New item') && !str_contains($h, 'href="' . url('users') . '"') && !str_contains($h, 'href="' . url('settings') . '"'));
check('palette data embedded for the shortcut bar', str_contains($body('/dashboard'), 'id="paletteData"') && str_contains($body('/dashboard'), 'data-palette'));

echo "Permissions on settings\n";
check('a user with settings.view but not edit cannot save appearance', (function () use ($tA, $sfx, $base) {
    $rid = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'ViewSet ' . $sfx]);
    DB::insert('role_permissions', ['role_id' => $rid, 'permission' => 'settings.view']);
    DB::insert('users', ['tenant_id' => $tA, 'role_id' => $rid, 'name' => 'VS', 'email' => "vs-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
    $c = new Client($base);
    $c->post('/login', ['email' => "vs-$sfx@test.local", 'password' => 'Password123'], '/login');
    $c->post('/settings/appearance', ['brand' => '#e11d48', 'brand_custom' => '', 'sidebar' => 'dark', 'density' => 'comfortable', 'mode' => 'light'], '/settings/appearance');
    return $c->get('/settings/appearance')['status'] === 200 && !str_contains($c->get('/dashboard')['body'], '--brand:#e11d48');
})());
check('Super Admin pages still render in the new shell', str_contains($admin->get('/admin')['body'], 'class="app sbs-dark"') && $admin->get('/admin/tenants')['status'] === 200 && $admin->get('/admin/system')['status'] === 200);

echo "\n" . ($fails ? "$fails check(s) FAILED" : 'All checks passed') . "\n";
exit($fails ? 1 : 0);
