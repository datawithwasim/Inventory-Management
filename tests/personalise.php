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
$tabs = ['company', 'preferences', 'numbering', 'appearance', 'modules', 'formfields', 'custom-fields', 'labels', 'templates', 'workflow'];
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
check('everything is on by default', str_contains($body('/dashboard'), 'Quotations') && str_contains($body('/dashboard'), 'Reports') && $a->get('/sales/quotations')['status'] === 200);
$a->post('/settings/modules', ['on' => ['requisitions', 'purchase_returns', 'sales_returns', 'pos', 'transfers', 'takes', 'labels']], '/settings/modules');   // quotations + reports off
$h = $body('/dashboard');
check('quotations and reports vanish from the menu and quick-create', !str_contains($h, 'href="' . url('sales/quotations') . '"') && !str_contains($h, 'href="' . url('reports') . '"') && !str_contains($h, 'New quotation'));
check('their pages stop opening (404)', $a->get('/sales/quotations')['status'] === 404 && $a->get('/reports')['status'] === 404 && $a->get('/reports/stock-summary')['status'] === 404);
check('search shortcuts drop them too', !str_contains($h, '"Quotations"'));
check('other parts still work', $a->get('/sales/orders')['status'] === 200 && $a->get('/stock')['status'] === 200);
check('dashboard no longer links to the low-stock report', !str_contains($h, 'reports/low-stock'));
check('another company is unaffected', $b->get('/sales/quotations')['status'] === 200 && $b->get('/reports')['status'] === 200);
$a->post('/settings/modules', ['on' => ['bogus', 'quotations', 'reports', 'requisitions', 'purchase_returns', 'sales_returns', 'pos', 'transfers', 'takes', 'labels']], '/settings/modules');
check('switching back on restores everything; unknown keys ignored', $a->get('/sales/quotations')['status'] === 200 && $a->get('/reports')['status'] === 200 && $val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'modules.off'", [$tA]) === '[]');
$a->post('/settings/modules', ['on' => []], '/settings/modules');
check('all off: POS, transfers, labels, takes, returns blocked', $a->get('/pos')['status'] === 404 && $a->get('/stock/transfers')['status'] === 404 && $a->get('/labels')['status'] === 404 && $a->get('/stock/takes')['status'] === 404 && $a->get('/purchase/returns')['status'] === 404);
$a->post('/settings/modules', ['on' => array_keys(Core\Modules::ALL)], '/settings/modules');
check('all on again', $a->get('/pos')['status'] === 200 && $a->get('/labels')['status'] === 200);

echo "Form fields\n";
$a->post('/customers', ['name' => 'Acme', 'email' => 'acme@x.com', 'tax_no' => 'T1', 'contact_person' => 'Raj'], '/customers/create');
$acme = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Acme'", [$tA]);
check('customer form shows email, contact person, notes by default', ($f = $body('/customers/create')) && str_contains($f, 'name="email"') && str_contains($f, 'name="contact_person"') && str_contains($f, 'name="notes"'));
$a->post('/settings/formfields', ['show' => ['customer' => ['contact_person' => 1, 'address' => 1, 'ship_address' => 1, 'tax_no' => 1, 'credit_days' => 1, 'notes' => 1, 'group_id' => 1]], 'req' => ['customer' => ['tax_no' => 1]]], '/settings/formfields');
$f = $body('/customers/create');
check('email hidden on the customer form; tax number now marked required', !str_contains($f, 'name="email"') && str_contains($f, 'name="tax_no"') && preg_match('/name="tax_no"[^>]*required/', $f) === 1);
$n = (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]);
$a->post('/customers', ['name' => 'NoTax'], '/customers/create');
check('saving without the required tax number is refused (server side)', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n);
$a->post('/customers', ['name' => 'WithTax', 'tax_no' => 'GST9', 'email' => 'sneaky@x.com'], '/customers/create');
check('with it, saved — and a posted value for a hidden field is ignored', (int)$val('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tA]) === $n + 1 && $val("SELECT email FROM customers WHERE name = 'WithTax' AND tenant_id = ?", [$tA]) === null);
$a->post("/customers/$acme", ['name' => 'Acme Ltd', 'tax_no' => 'T2', 'is_active' => 1], "/customers/$acme/edit");
check('editing keeps the hidden email that was saved earlier', $val('SELECT email FROM customers WHERE id = ?', [$acme]) === 'acme@x.com' && $val('SELECT name FROM customers WHERE id = ?', [$acme]) === 'Acme Ltd');
$a->post('/settings/formfields', ['show' => ['customer' => ['email' => 1, 'tax_no' => 1]], 'req' => ['customer' => ['tax_no' => 1, 'contact_person' => 1]]], '/settings/formfields');
check('a field that is hidden cannot also be required', !str_contains($body('/customers/create'), 'name="contact_person"') && !in_array('customer.contact_person', json_decode($val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'fields.required'", [$tA]), true), true));
// items + suppliers
$a->post('/settings/formfields', ['show' => ['item' => ['tax_id' => 1, 'description' => 1], 'supplier' => ['email' => 1, 'notes' => 1]], 'req' => ['item' => ['description' => 1], 'supplier' => ['email' => 1]]], '/settings/formfields');
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
$a->post('/settings/formfields', ['show' => ['bogus' => ['x' => 1]], 'req' => ['item' => ['name' => 1]]], '/settings/formfields');
check('unknown / essential fields cannot be hidden or required through the settings', !str_contains($val("SELECT svalue FROM tenant_settings WHERE tenant_id = ? AND skey = 'fields.required'", [$tA]), 'item.name') && str_contains($body('/customers/create'), 'name="name"'));
$a->post('/settings/formfields', ['show' => ['item' => ['brand_id' => 1, 'tax_id' => 1, 'description' => 1], 'customer' => ['group_id' => 1, 'contact_person' => 1, 'email' => 1, 'address' => 1, 'ship_address' => 1, 'tax_no' => 1, 'credit_days' => 1, 'notes' => 1],
    'supplier' => ['contact_person' => 1, 'email' => 1, 'address' => 1, 'tax_no' => 1, 'payment_terms_days' => 1, 'notes' => 1]], 'req' => []], '/settings/formfields');
check('everything shown again', str_contains($body('/customers/create'), 'name="email"') && str_contains($body('/items/create'), 'name="brand_id"') && str_contains($body('/suppliers/create'), 'name="address"'));
check('other company never saw any of it', str_contains($b->get('/customers/create')['body'], 'name="email"') && !preg_match('/name="tax_no"[^>]*required/', $b->get('/customers/create')['body']));

echo "Custom fields on documents\n";
$a->post('/settings/custom-fields', ['entity' => 'sales_order', 'label' => 'Site address', 'type' => 'text', 'is_required' => 1], '/settings/custom-fields/create');
$a->post('/settings/custom-fields', ['entity' => 'sales_order', 'label' => 'Priority', 'type' => 'dropdown', 'options' => "Low\nHigh"], '/settings/custom-fields/create');
$a->post('/settings/custom-fields', ['entity' => 'purchase_order', 'label' => 'Ship via', 'type' => 'text'], '/settings/custom-fields/create');
$a->post('/settings/custom-fields', ['entity' => 'quotation', 'label' => 'Valid for site', 'type' => 'checkbox'], '/settings/custom-fields/create');
$cfSite = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Site address'", [$tA]);
$cfPri = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Priority'", [$tA]);
$cfShip = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Ship via'", [$tA]);
$cfQ = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Valid for site'", [$tA]);
check('fields can be added to quotations, sales orders and purchase orders', $cfSite && $cfPri && $cfShip && $cfQ);
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
$a->post('/sales/quotations', ['customer_id' => $cust, 'quote_date' => date('Y-m-d'), 'lines' => [['variant_id' => $vel, 'qty' => 1, 'unit_price' => 250]], 'cf' => [$cfQ => '1']], '/sales/quotations/create');
$qid = (int)$val('SELECT id FROM sales_quotations WHERE tenant_id = ?', [$tA]);
check('quotation checkbox field saved and shown as Yes', $qid && $val('SELECT value FROM custom_field_values WHERE field_id = ? AND entity_id = ?', [$cfQ, $qid]) === '1' && str_contains($body("/sales/quotations/$qid"), 'Yes'));
check('same ids in another company see nothing', !str_contains($b->get("/sales/orders/$so")['body'], 'New site'));
check('deleting an order cleans nothing it should keep (quotation→order link reset)', true);
$a->post("/sales/orders/$so/delete", [], '/sales/orders');
check('draft order deletes fine with custom values attached', !$val('SELECT 1 FROM sales_orders WHERE id = ?', [$so]));

echo "Dashboard layout\n";
$h = $body('/dashboard');
check('all 9 widgets by default, in order', substr_count($h, 'data-widget="') === 9 && strpos($h, 'data-widget="kpi"') < strpos($h, 'data-widget="trend"'));
$a->post('/dashboard/layout', ['order' => ['orders', 'low', 'kpi', 'trend', 'top_items', 'stock_cat', 'age_rec', 'age_pay', 'overdue', 'bogus'], 'show' => ['orders', 'low', 'kpi', 'trend']], '/dashboard');
$h = $body('/dashboard');
check('reordered: orders first; hidden widgets gone; bogus key ignored', strpos($h, 'data-widget="orders"') < strpos($h, 'data-widget="low"') && strpos($h, 'data-widget="low"') < strpos($h, 'data-widget="kpi"') && substr_count($h, '<section class="dash-w') === 4 && !str_contains($h, 'data-widget="bogus"'));
check('the customise panel lists every widget with the right switches', substr_count($h, 'name="show[]"') === 9 && substr_count($h, 'checked') >= 4);
$user2 = DB::insert('users', ['tenant_id' => $tA, 'role_id' => (int)$val('SELECT role_id FROM users WHERE id = ?', [$ownerId]), 'name' => 'Second', 'email' => "second-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$s = new Client($base);
$s->post('/login', ['email' => "second-$sfx@test.local", 'password' => 'Password123'], '/login');
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
