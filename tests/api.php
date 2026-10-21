<?php
declare(strict_types=1);

/** JSON API mode (the React app's backend bridge).  php tests/api.php */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\DB;

/** Small JSON client: keeps the session cookie and the CSRF token the way the React app does. */
final class J
{
    private string $jar;
    public string $csrf = '';
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'jj'); }
    public function call(string $method, string $path, ?array $body = null, bool $csrf = true): array
    {
        $ch = curl_init($this->base . '/api/v1' . $path);
        $h = ['Accept: application/json'];
        if ($csrf && $this->csrf !== '') $h[] = 'X-CSRF-Token: ' . $this->csrf;
        if ($body !== null) $h[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $h,
            CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null]);
        $raw = (string)curl_exec($ch);
        $st = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $st, 'json' => json_decode($raw, true), 'raw' => $raw];
    }
    /** A multipart form (file upload), the way a browser sends it. */
    public function form(string $path, array $fields): array
    {
        $ch = curl_init($this->base . '/api/v1' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $this->csrf], CURLOPT_POSTFIELDS => $fields]);
        $raw = (string)curl_exec($ch);
        $st = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $st, 'json' => json_decode($raw, true), 'raw' => $raw];
    }
    public function session(): array { $r = $this->call('GET', '/session'); $this->csrf = (string)($r['json']['csrf'] ?? ''); return $r; }
}

$sfx = bin2hex(random_bytes(3));
$sa = config('superadmin');
$admin = new Client($base);
$admin->post('/admin/login', ['email' => $sa['email'], 'password' => $sa['password']], '/admin/login');
$pro = (int)DB::val("SELECT id FROM plans WHERE slug = 'pro'");
$mk = function (string $name) use ($admin, $pro, $sfx): array {
    $email = strtolower($name) . "-$sfx@test.local";
    $admin->post('/admin/tenants', ['name' => "$name $sfx", 'plan_id' => $pro, 'status' => 'active', 'subscription_ends_at' => '', 'owner_name' => $name, 'owner_email' => $email, 'owner_password' => 'Password123'], '/admin/tenants/create');
    return [$email, (int)DB::val('SELECT id FROM tenants WHERE name = ?', ["$name $sfx"])];
};
[$emA, $tA] = $mk('Apia');
[$emB, $tB] = $mk('Apib');

echo "Session and sign-in\n";
$a = new J($base);
$s = $a->session();
check('signed out: the session carries the product colours, never a company\'s', str_contains((string)($s['json']['themeCss'] ?? ''), '--brand:#4f46e5'));
check('signed out: the session call answers JSON with a CSRF token and no user', $s['status'] === 200 && ($s['json']['authenticated'] ?? null) === false && strlen($a->csrf) >= 32 && !isset($s['json']['user']));
$r = $a->call('GET', '/customers');
check('a protected route answers 401 JSON instead of redirecting to the login page', $r['status'] === 401 && ($r['json']['ok'] ?? null) === false);
$r = $a->call('POST', '/login', ['email' => $emA, 'password' => 'Password123'], false);
check('a POST without the CSRF header is refused with 419', $r['status'] === 419);
$r = $a->call('POST', '/login', ['email' => $emA, 'password' => 'wrong']);
check('a wrong password is a 422 with the message', $r['status'] === 422 && ($r['json']['ok'] ?? null) === false && str_contains(json_encode($r['json']['messages'] ?? []), 'password') || ($r['status'] === 422 && !empty($r['json']['messages'])));
$r = $a->call('POST', '/login', ['email' => $emA, 'password' => 'Password123']);
check('the right password signs in (ok + where the page would have gone)', $r['status'] === 200 && ($r['json']['ok'] ?? null) === true && ($r['json']['redirect'] ?? '') === 'dashboard');
$s = $a->session(); $j = $s['json'];
check('the session now carries the user, company, permissions, menu, terms and theme', ($j['authenticated'] ?? false) === true && ($j['user']['owner'] ?? false) === true && ($j['perms'][0] ?? '') === '*' && !empty($j['menu']['groups']['purchase']) && ($j['terms']['batch'][0] ?? '') === 'Roll' && ($j['theme']['brand'] ?? '') !== '' && ($j['tenant']['name'] ?? '') === "Apia $sfx");
check('no password hash or token anywhere in the session payload', !preg_match('/password|token_hash|\$2y\$/i', json_encode(array_diff_key($j, ['csrf' => 1]))));

echo "Pages as data\n";
$r = $a->call('POST', '/customers', ['name' => 'Rani Textiles', 'phone' => '999', 'credit_days' => '30']);
$cid = (int)DB::val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Rani Textiles'", [$tA]);
check('saving a customer through the API runs the page\'s own validation and saves; the reply says where to go next', $r['status'] === 200 && $cid > 0 && ($r['json']['redirect'] ?? '') === "customers/$cid" && in_array('success', array_column($r['json']['messages'] ?? [], 'type'), true));
$r = $a->call('POST', '/customers', ['name' => '']);
check('a refused form is a 422 with the reason, nothing saved', $r['status'] === 422 && ($r['json']['ok'] ?? null) === false && str_contains(json_encode($r['json']['messages']), 'required'));
$r = $a->call('POST', '/customers', ['name' => 'Rani Textiles']);
check('duplicate name is refused the same way', $r['status'] === 422 && (int)DB::val("SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND name = 'Rani Textiles'", [$tA]) === 1);
$r = $a->call('GET', '/customers');
check('the customer list returns its rows and column choices as JSON', $r['status'] === 200 && ($r['json']['view'] ?? '') === 'app/customers/index' && in_array('Rani Textiles', array_column($r['json']['data']['rows'] ?? [], 'name'), true) && isset($r['json']['data']['cols']));
$r = $a->call('GET', "/customers/$cid");
check('the customer page returns its data (balance, orders, invoices)', $r['status'] === 200 && ($r['json']['data']['c']['name'] ?? '') === 'Rani Textiles' && array_key_exists('owed', $r['json']['data']));
$r = $a->call('GET', "/record/customer/$cid");
$sec = $r['json']['record']['sections'] ?? [];
$vals = [];
foreach ($sec as $x) foreach ($x['items'] as $it) $vals[$it['label']] = $it['text'];
foreach (($r['json']['record']['summary'] ?? []) as $it) $vals[$it['label']] = $it['text'];
check('the designed record page as data: summary + sections with the values', $r['status'] === 200 && ($vals['Phone'] ?? '') === '999' && ($vals['Credit days'] ?? '') === '30 days' || ($vals['Credit days'] ?? '') === '30');
check('and the timeline says who created it', ($r['json']['timeline'][0]['verb'] ?? '') === 'created');
$r = $a->call('GET', '/schema/customer');
check('the form schema lists sections, typed fields, required rules', $r['status'] === 200 && ($r['json']['schema']['fields']['email']['type'] ?? '') === 'email' && ($r['json']['schema']['fields']['group_id']['source'] ?? '') === 'customer_groups' && !empty($r['json']['schema']['sections']));
$r = $a->call('GET', '/options/units');
check('pick lists come as {id, name}', $r['status'] === 200 && is_array($r['json']['options']) && isset($r['json']['options'][0]['id']));
check('an unknown pick list or schema is a 404 JSON', $a->call('GET', '/options/passwords')['status'] === 404 && $a->call('GET', '/schema/nothing')['status'] === 404);
$r = $a->call('POST', "/records/customer/$cid/notes", ['body' => 'Called today']);
check('adding a note works through the API', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM record_notes WHERE tenant_id = ? AND entity = ? AND entity_id = ?', [$tA, 'customer', $cid]) === 1);

echo "Custom fields on more forms\n";
$ents = ['grn', 'bill', 'delivery', 'invoice', 'purchase_return', 'sales_return', 'requisition', 'adjustment', 'transfer', 'stocktake', 'warehouse'];
$mm = $a->call('GET', '/settings/formdesign')['json']['data']['model'] ?? [];
$payload = []; $can = 0;
foreach ($mm as $f) {
    if (!in_array($f['key'], $ents, true)) continue;
    if (!empty($f['customFields'])) $can++;
    $secs = array_map(fn($sec) => ['title' => $sec['title'], 'cols' => $sec['cols'], 'fields' => $sec['fields']], $f['sections']);
    $secs[0]['fields'][] = 'cf:newR';
    $payload[$f['key']] = ['style' => $f['style'], 'lines' => $f['lines'] ?? [], 'deleted' => [], 'sections' => $secs,
        'fields' => ['cf:newR' => ['w' => '', 'help' => '', 'show' => true, 'req' => false, 'name' => 'Ref code', 'type' => 'text', 'options' => '', 'inList' => false, 'unique' => false, 'isNew' => true]]];
}
check('goods receipts, bills, deliveries, invoices, returns, requisitions, stock documents and warehouses all accept the company\'s own fields', $can === count($ents));
$a->call('POST', '/settings/formdesign', ['payload' => json_encode($payload), 'current' => 'grn', 'close' => '', 'return' => '']);
$CF = [];
foreach ($ents as $e) $CF[$e] = (int)DB::val("SELECT id FROM custom_fields WHERE tenant_id = ? AND entity = ? AND label = 'Ref code'", [$tA, $e]);
check('a field was created on each of them', count(array_filter($CF)) === count($ents));

echo "Suppliers and supplier items through the API\n";
$r = $a->call('POST', '/suppliers', ['name' => 'Api Mills', 'payment_terms_days' => '15', 'contacts' => [['name' => 'Ravi', 'phone' => '555 1234', 'role' => 'Sales', 'email' => '']]]);
$sid = (int)DB::val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Api Mills'", [$tA]);
check('a supplier with an extra contact person saves from JSON', $r['status'] === 200 && $sid > 0 && (int)DB::val('SELECT COUNT(*) FROM supplier_contacts WHERE tenant_id = ? AND supplier_id = ?', [$tA, $sid]) === 1);
$r = $a->call('GET', "/suppliers/$sid");
check('the supplier page data has contacts, rates and the items supplied', $r['status'] === 200 && ($r['json']['data']['contacts'][0]['name'] ?? '') === 'Ravi' && array_key_exists('rates', $r['json']['data']) && array_key_exists('products', $r['json']['data']));
$a->call('POST', '/items', ['name' => 'Api Velvet', 'unit_id' => (int)DB::val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]), 'variants' => [['sku' => 'APV', 'cost_price' => 100, 'sale_price' => 200]]]);
$vid = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'APV'", [$tA]);
$r = $a->call('POST', '/purchase/supplier-items', ['supplier_id' => $sid, 'supplier_name' => 'Royal Maroon', 'supplier_code' => 'RM-1', 'variant_id' => $vid, 'min_order_qty' => '50', 'is_preferred' => 1]);
$siId = (int)DB::val("SELECT id FROM supplier_items WHERE tenant_id = ? AND supplier_code = 'RM-1'", [$tA]);
check('a supplier item links to our variant through the API', $siId > 0 && ($r['json']['redirect'] ?? '') === "purchase/supplier-items/$siId");
$a->call('POST', "/suppliers/$sid/rates", ['variant_id' => $vid, 'rate' => '120', 'discount_pct' => '10', 'return' => "purchase/supplier-items/$siId"]);
$r = $a->call('GET', "/record/supplier_item/$siId");
$vals = [];
foreach (array_merge($r['json']['record']['summary'] ?? [], ...array_column($r['json']['record']['sections'] ?? [], 'items')) as $it) $vals[$it['label']] = $it;
check('the supplier item record page has its record-only facts (current rate after discount) and a link to our item', ($vals['Current rate']['text'] ?? '') !== '' && str_contains($vals['Current rate']['text'], '108') && str_contains((string)($vals['Our item']['href'] ?? ''), 'items/'));
$r = $a->call('GET', '/schema/supplier_item');
check('the supplier item schema types our-item as a searchable item picker', ($r['json']['schema']['fields']['variant_id']['type'] ?? '') === 'lookup' && ($r['json']['schema']['fields']['variant_id']['source'] ?? '') === 'item' && ($r['json']['schema']['fields']['supplier_id']['source'] ?? '') === 'supplier');
$r = $a->call('GET', '/columns/supplier_items');
check('column choices come for a list', $r['status'] === 200 && isset($r['json']['options']['our_item']) && is_array($r['json']['visible']));

echo "Items through the API\n";
$unit = (int)DB::val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]);
$r = $a->call('POST', '/items', ['name' => 'Api Silk', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'reorder_level' => '20',
    'sku' => 'ASK-R', 'cost_price' => '300', 'sale_price' => '450', 'cf' => (object)[]]);
$iid = (int)DB::val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Api Silk'", [$tA]);
check('an item saves from JSON with its one SKU and price (roll tracking on)', $r['status'] === 200 && $iid > 0 && (int)DB::val('SELECT COUNT(*) FROM item_variants WHERE item_id = ?', [$iid]) === 1 && (float)DB::val('SELECT sale_price FROM item_variants WHERE item_id = ?', [$iid]) === 450.0 && (int)DB::val('SELECT track_batch FROM items WHERE id = ?', [$iid]) === 1);
$r = $a->call('POST', '/items', ['name' => 'Api Silk 2', 'unit_id' => $unit, 'sku' => 'ASK-R']);
check('a duplicate SKU is refused with the reason', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'ASK-R') && !DB::val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Api Silk 2'", [$tA]));
$r = $a->call('GET', "/items/$iid");
check('the item page data has its SKU row with stock, supplier rates and history', $r['status'] === 200 && count($r['json']['data']['variants']) === 1 && array_key_exists('rates', $r['json']['data']) && array_key_exists('history', $r['json']['data']) && isset($r['json']['data']['variants'][0]['batches']));
$vA = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'ASK-R'", [$tA]);
$r = $a->call('POST', '/items', ['name' => 'Api Set', 'unit_id' => $unit, 'is_bundle' => 1, 'variants' => [['sku' => 'ASET', 'sale_price' => '900']], 'components' => [['variant_id' => $vA, 'qty' => '2']]]);
$bid = (int)DB::val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Api Set'", [$tA]);
check('a bundle with a component saves', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM bundle_components WHERE bundle_item_id = ?', [$bid]) === 1);
$r = $a->call('GET', "/items/$iid/edit");
check('the edit form data carries what the form needs (item, variants, lock flag, pickable items)', ($r['json']['data']['item']['name'] ?? '') === 'Api Silk' && count($r['json']['data']['variants']) === 1 && array_key_exists('locked', $r['json']['data']) && is_array($r['json']['data']['pickable']));
$r = $a->call('GET', '/items?q=Silk&low=');
check('the item list filters by search and pages', in_array('Api Silk', array_column($r['json']['data']['rows'], 'name'), true) && isset($r['json']['data']['pages']));
check('the session names the stock movement types for the history table', ($a->session()['json']['stockTypes']['purchase'] ?? '') !== '');
$r = $a->call('POST', "/items/$iid/delete");
check('an item used inside a bundle cannot be deleted (and the reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM items WHERE id = ?', [$iid]) && str_contains(json_encode($r['json']['messages']), 'bundle'));
$a->call('POST', "/items/$bid/delete");
$r = $a->call('POST', "/items/$iid/delete");
check('an item without history, once out of the bundle, can be deleted', $r['status'] === 200 && !DB::val('SELECT 1 FROM items WHERE id = ?', [$iid]));

echo "Purchase documents through the API\n";
$wh = (int)DB::val('SELECT id FROM warehouses WHERE tenant_id = ? LIMIT 1', [$tA]);
$v2 = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'APV'", [$tA]);
$r = $a->call('POST', '/purchase/orders', ['supplier_id' => $sid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $v2, 'qty' => '10', 'unit_price' => '100', 'tax_rate' => '5']]]);
$poId = (int)DB::val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('a purchase order saves as a draft from JSON lines (totals worked out by the server)', $r['status'] === 200 && (float)DB::val('SELECT total FROM purchase_orders WHERE id = ?', [$poId]) === 1050.0 && DB::val('SELECT status FROM purchase_orders WHERE id = ?', [$poId]) === 'draft');
$r = $a->call('POST', '/purchase/orders', ['supplier_id' => $sid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => []]);
check('a PO with no items is refused', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'at least one item'));
$r = $a->call('GET', "/purchase/orders/$poId");
check('the PO page data carries items, remaining quantity, approval setting and receipts', $r['status'] === 200 && count($r['json']['data']['items']) === 1 && (float)$r['json']['data']['remaining'] === 10.0 && array_key_exists('approval', $r['json']['data']));
$a->call('POST', "/purchase/orders/$poId/submit");
check('confirming moves it to approved (no approval needed on this company)', DB::val('SELECT status FROM purchase_orders WHERE id = ?', [$poId]) === 'approved');
$r = $a->call('GET', "/purchase/orders/$poId/receive");
$ol = $r['json']['data']['oldLines'] ?? [];
check('the receive form data lists the open lines with what is still due', count($ol) === 1 && (float)$ol[0]['left'] === 10.0 && isset($r['json']['data']['racks']));
$r = $a->call('POST', '/purchase/grns', ['cf' => [$CF['grn'] => 'cf-grn'], 'po_id' => $poId, 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'CH-1', 'lines' => [['po_item_id' => $ol[0]['po_item_id'], 'qty' => '10', 'location_id' => '0', 'lot_no' => '']]]);
$grnId = (int)DB::val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('goods are received against the PO: stock goes up and the PO is received', $r['status'] === 200 && (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $v2]) === 10.0 && DB::val('SELECT status FROM purchase_orders WHERE id = ?', [$poId]) === 'received');
$r = $a->call('POST', "/purchase/grns/$grnId/bill");
$billId = (int)DB::val('SELECT id FROM purchase_bills WHERE tenant_id = ? AND grn_id = ?', [$tA, $grnId]);
check('a bill is created from the receipt', $billId > 0 && (float)DB::val('SELECT total FROM purchase_bills WHERE id = ?', [$billId]) === 1050.0);
$r = $a->call('POST', "/purchase/bills/$billId/payments", ['amount' => '5000', 'paid_on' => date('Y-m-d'), 'method' => 'cash']);
check('paying more than the balance is refused', $r['status'] === 422 && (float)DB::val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$billId]) === 0.0);
$a->call('POST', "/purchase/bills/$billId/payments", ['amount' => '1050', 'paid_on' => date('Y-m-d'), 'method' => 'bank', 'reference' => 'UTR9']);
check('a payment is recorded and the bill is settled', (float)DB::val('SELECT paid_amount FROM purchase_bills WHERE id = ?', [$billId]) === 1050.0);
$r = $a->call('GET', "/purchase/bills/$billId");
check('the bill page data has items, payments and the payment state', ($r['json']['data']['status'] ?? '') === 'paid' && count($r['json']['data']['payments']) === 1 && count($r['json']['data']['items']) === 1);
$r = $a->call('GET', "/purchase/returns/create?grn=$grnId");
$gi = $r['json']['data']['items'][0] ?? null;
check('the return form data lists what can go back and where it sits', $gi && (float)$gi['returnable'] === 10.0 && isset($gi['placements']));
$r = $a->call('POST', '/purchase/returns', ['cf' => [$CF['purchase_return'] => 'cf-purchase_return'], 'grn_id' => $grnId, 'return_date' => date('Y-m-d'), 'reason' => 'Damaged', 'lines' => [(string)$gi['id'] => ['qty' => '4', 'location_id' => '0']]]);
check('a return takes stock out and credits the bill', $r['status'] === 200 && (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $v2]) === 6.0 && (float)DB::val('SELECT returned_amount FROM purchase_bills WHERE id = ?', [$billId]) === 420.0);
$r = $a->call('POST', '/purchase/returns', ['grn_id' => $grnId, 'return_date' => date('Y-m-d'), 'lines' => [(string)$gi['id'] => ['qty' => '99']]]);
check('returning more than was received is refused', $r['status'] === 422);
$r = $a->call('POST', '/purchase/requisitions', ['cf' => [$CF['requisition'] => 'cf-requisition'], 'note' => 'Need silk', 'lines' => [['variant_id' => $v2, 'qty' => '20', 'note' => 'urgent']]]);
$reqId = (int)DB::val('SELECT MAX(id) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
check('a requisition saves and turns into a draft PO', $r['status'] === 200 && $reqId > 0);
$r = $a->call('POST', "/purchase/requisitions/$reqId/convert", ['supplier_id' => $sid, 'warehouse_id' => $wh]);
check('converting makes a draft purchase order and closes the requisition', $r['status'] === 200 && DB::val('SELECT status FROM purchase_requisitions WHERE id = ?', [$reqId]) === 'converted');
$rec = $a->call('GET', "/record/purchase_order/$poId");
$vals = [];
foreach (array_merge($rec['json']['record']['summary'] ?? [], ...array_column($rec['json']['record']['sections'] ?? [], 'items')) as $it) $vals[$it['label']] = $it['text'];
check('the PO record page has its record-only facts (created by, total)', ($vals['Created by'] ?? '') !== '' && str_contains($vals['Total'] ?? '', '1,050'));
check('the GRN and bill record pages link their supplier, order and bill', str_contains(json_encode($a->call('GET', "/record/grn/$grnId")['json']), 'purchase\\/bills\\/' . $billId) && str_contains(json_encode($a->call('GET', "/record/bill/$billId")['json']), 'purchase\\/grns\\/' . $grnId));
check('lists answer with rows and paging', count($a->call('GET', '/purchase/orders')['json']['data']['rows']) >= 2 && isset($a->call('GET', '/purchase/bills?status=paid')['json']['data']['totalDue']));

echo "Items and their supplier items\n";
$itemOf = fn(string $name) => (int)DB::val('SELECT id FROM items WHERE tenant_id = ? AND name = ?', [$tA, $name]);
$vOf = fn(string $sku) => (int)DB::val('SELECT id FROM item_variants WHERE tenant_id = ? AND sku = ?', [$tA, $sku]);
$r = $a->call('POST', '/items/' . $itemOf('Api Velvet') . '/delete');
check('an item that suppliers are linked to cannot be deleted (it would silently drop the supplier items and rates)', $r['status'] === 422 && DB::val('SELECT 1 FROM items WHERE tenant_id = ? AND name = ?', [$tA, 'Api Velvet']) && (int)DB::val('SELECT COUNT(*) FROM supplier_items WHERE variant_id = ?', [$vid]) === 1 && str_contains(json_encode($r['json']['messages']), 'supplier'));
$r = $a->call('POST', '/items', ['name' => 'Two SKUs', 'unit_id' => $unit, 'variants' => [['sku' => 'TS-1'], ['sku' => 'TS-2']], 'cf' => (object)[]]);
check('two SKUs on one item are refused: every colour, design or size is its own item', $r['status'] === 422 && !DB::val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Two SKUs'", [$tA]) && str_contains(json_encode($r['json']['messages']), 'separate item'));
$r = $a->call('POST', '/items', ['name' => 'Api Linked', 'unit_id' => $unit, 'sku' => 'LK-A', 'sale_price' => '10', 'cf' => (object)[]]);
$lk = $itemOf('Api Linked');
$a->call('POST', '/purchase/supplier-items', ['supplier_id' => $sid, 'supplier_name' => 'Their A', 'supplier_code' => 'LKA', 'variant_id' => $vOf('LK-A'), 'design_no' => 'D-9', 'composition' => 'Cotton', 'width' => '48 in', 'gsm' => '120', 'pattern' => 'Check', 'finish' => 'Matt']);
$sir = DB::one("SELECT * FROM supplier_items WHERE supplier_code = 'LKA'");
check('the details live on the supplier item (design, composition, width, GSM, pattern, finish)', $sir && $sir['design_no'] === 'D-9' && $sir['composition'] === 'Cotton' && $sir['width'] === '48 in' && $sir['gsm'] === '120' && $sir['pattern'] === 'Check' && $sir['finish'] === 'Matt');
$rr = $a->call('GET', "/items/$lk")['json']['data'];
check('the item page shows them through the tagged supplier item', ($rr['rates'][0]['design_no'] ?? '') === 'D-9' && ($rr['rates'][0]['composition'] ?? '') === 'Cotton');
check('the item list finds an item by its supplier item\'s code or design number', in_array('Api Linked', array_column($a->call('GET', '/items?q=LKA')['json']['data']['rows'], 'name'), true) && in_array('Api Linked', array_column($a->call('GET', '/items?q=D-9')['json']['data']['rows'], 'name'), true));
$r = $a->call('GET', '/schema/supplier_item');
check('the supplier item form offers those fields (and the item form no longer does)', isset($r['json']['schema']['fields']['design_no']) && isset($r['json']['schema']['fields']['finish']) && !isset($a->call('GET', '/schema/item')['json']['schema']['fields']['design_no']));

// reordering uses the supplier link: preferred supplier, its rate and its minimum order quantity
$a->call('POST', '/items', ['name' => 'Api Reorder', 'unit_id' => $unit, 'reorder_level' => '10', 'reorder_qty' => '12', 'variants' => [['sku' => 'APR', 'cost_price' => '100']], 'cf' => (object)[]]);
$rq = $vOf('APR'); $rqItem = $itemOf('Api Reorder');
$a->call('POST', '/suppliers', ['name' => 'Api Other Mills']); $sid2 = (int)DB::val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Api Other Mills'", [$tA]);
$a->call('POST', '/purchase/supplier-items', ['supplier_id' => $sid, 'supplier_name' => 'AR', 'supplier_code' => 'AR-1', 'variant_id' => $rq, 'min_order_qty' => '50', 'is_preferred' => 1]);
$a->call('POST', '/purchase/supplier-items', ['supplier_id' => $sid2, 'supplier_code' => 'AR-2', 'variant_id' => $rq]);
$a->call('POST', "/suppliers/$sid/rates", ['variant_id' => $rq, 'rate' => '120', 'discount_pct' => '10']);
$low = fn() => array_values(array_filter($a->call('GET', '/reports/low-stock')['json']['data']['rows'], fn($x) => $x['item'] === 'Api Reorder'))[0] ?? [];
$row = $low();
check('low stock suggests the preferred supplier, at its current rate, and never less than its minimum order', ($row['supplier'] ?? '') === 'Api Mills' && (float)($row['cost'] ?? 0) === 108.0 && (float)($row['suggest'] ?? 0) === 50.0);
$a->call('POST', '/reports/low-stock/create-pos', ['pick' => [$rqItem]]);
$poL = (int)DB::val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
check('and the draft purchase order made from it carries that supplier, rate and quantity', (int)DB::val('SELECT supplier_id FROM purchase_orders WHERE id = ?', [$poL]) === $sid && (float)DB::val('SELECT unit_price FROM purchase_order_items WHERE po_id = ?', [$poL]) === 108.0 && (float)DB::val('SELECT qty_ordered FROM purchase_order_items WHERE po_id = ?', [$poL]) === 50.0);
$a->call('POST', '/purchase/requisitions', ['note' => 'Reorder', 'lines' => [['variant_id' => $rq, 'qty' => '5']]]);
$rqId = (int)DB::val('SELECT MAX(id) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
$a->call('POST', "/purchase/requisitions/$rqId/convert", ['supplier_id' => $sid, 'warehouse_id' => $wh]);
$poR = (int)DB::val('SELECT po_id FROM purchase_requisitions WHERE id = ?', [$rqId]);
check('turning a requisition into a PO prices the lines from that supplier\'s rate list, not the item\'s default cost', (float)DB::val('SELECT unit_price FROM purchase_order_items WHERE po_id = ?', [$poR]) === 108.0);
$rqId2 = 0; $a->call('POST', '/purchase/requisitions', ['note' => 'Other', 'lines' => [['variant_id' => $rq, 'qty' => '5']]]); $rqId2 = (int)DB::val('SELECT MAX(id) FROM purchase_requisitions WHERE tenant_id = ?', [$tA]);
$a->call('POST', "/purchase/requisitions/$rqId2/convert", ['supplier_id' => $sid2, 'warehouse_id' => $wh]);
check('a supplier with no rate falls back to the item\'s default cost', (float)DB::val('SELECT unit_price FROM purchase_order_items WHERE po_id = ?', [(int)DB::val('SELECT po_id FROM purchase_requisitions WHERE id = ?', [$rqId2])]) === 100.0);

echo "Warehouses, racks and stock through the API\n";
$r = $a->call('POST', '/warehouses', ['cf' => [$CF['warehouse'] => 'cf-warehouse'], 'name' => 'Api Godown', 'code' => 'apg', 'address' => 'Plot 4']);
$wh2 = (int)DB::val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Api Godown'", [$tA]);
check('a warehouse saves from JSON (code upper-cased)', $r['status'] === 200 && $wh2 > 0 && DB::val('SELECT code FROM warehouses WHERE id = ?', [$wh2]) === 'APG');
$r = $a->call('POST', '/warehouses', ['name' => 'Api Godown', 'code' => 'X']);
check('a duplicate warehouse name is refused', $r['status'] === 422);
$r = $a->call('GET', '/warehouses');
check('the warehouse list returns total stock per warehouse', $r['status'] === 200 && in_array('Api Godown', array_column($r['json']['data']['rows'], 'name'), true) && array_key_exists('total_qty', $r['json']['data']['rows'][0]));
$r = $a->call('POST', '/locations', ['warehouse_id' => $wh, 'mode' => 'range', 'prefix' => 'ap-', 'from' => '1', 'to' => '3', 'pad' => '2']);
check('a range of racks is created', $r['status'] === 200 && (int)DB::val("SELECT COUNT(*) FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code LIKE 'AP-0%'", [$tA, $wh]) === 3);
$r = $a->call('POST', '/locations', ['warehouse_id' => $wh2, 'mode' => 'single', 'code' => 'b-1', 'description' => 'Back row']);
$rack2 = (int)DB::val("SELECT id FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = 'B-1'", [$tA, $wh2]);
$rack1 = (int)DB::val("SELECT id FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = 'AP-01'", [$tA, $wh]);
check('a single rack is created', $r['status'] === 200 && $rack2 > 0 && $rack1 > 0);
$r = $a->call('GET', "/locations?warehouse=$wh2");
check('the rack list filters by warehouse', count($r['json']['data']['rows']) === 1 && isset($r['json']['data']['unassigned']));
$r = $a->call('GET', "/record/location/$rack2");
$vals = [];
foreach (array_merge($r['json']['record']['summary'] ?? [], ...array_column($r['json']['record']['sections'] ?? [], 'items')) as $it) $vals[$it['label']] = $it;
check('the rack record page links its warehouse', str_contains(json_encode($r['json']), 'warehouses\\/' . $wh2));

$r = $a->call('POST', '/items', ['name' => 'Api Roll', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'variants' => [['name' => '', 'sku' => 'ARL-1', 'cost_price' => '200', 'sale_price' => '300']], 'cf' => (object)[]]);
$rv = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'ARL-1'", [$tA]);
$r = $a->call('GET', '/stock/lookup?q=ARL-1');
check('the stock item search returns roll flags and the unit', ($r['json'][0]['track_batch'] ?? null) !== null && ($r['json'][0]['unit'] ?? '') !== '' && (int)$r['json'][0]['id'] === $rv);
$r = $a->call('GET', '/stock/adjustments/create');
check('the adjustment form data has warehouses, reasons and racks per warehouse', count($r['json']['data']['warehouses']) >= 2 && isset($r['json']['data']['reasons']['opening']) && isset($r['json']['data']['racks'][$wh]));
check('the reasons are also a pick list and the schema types reason as one', count($a->call('GET', '/options/stock_reasons')['json']['options']) === 7 && ($a->call('GET', '/schema/adjustment')['json']['schema']['fields']['reason']['source'] ?? '') === 'stock_reasons');
$r = $a->call('POST', '/stock/adjustments', ['cf' => [$CF['adjustment'] => 'cf-adjustment'], 'warehouse_id' => $wh, 'reason' => 'opening', 'note' => 'API opening', 'lines' => [['variant_id' => $rv, 'batch_id' => 'new', 'lot_no' => 'L7', 'location_id' => $rack1, 'qty' => '50', 'unit_cost' => '']]]);
$adjId = (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'adjustment'", [$tA]);
$bid2 = (int)DB::val('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rv]);
check('an opening adjustment creates a roll on the rack', $r['status'] === 200 && $adjId > 0 && $bid2 > 0 && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND location_id = ?', [$tA, $bid2, $rack1]) === 50.0);
$r = $a->call('POST', '/stock/adjustments', ['warehouse_id' => $wh, 'reason' => 'damage', 'lines' => [['variant_id' => $rv, 'batch_id' => $bid2, 'location_id' => $rack1, 'qty' => '-500']]]);
check('taking out more than the roll holds is refused and nothing is posted', $r['status'] === 422 && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND batch_id = ?', [$tA, $bid2]) === 50.0);
$r = $a->call('POST', '/stock/adjustments', ['warehouse_id' => $wh, 'reason' => '', 'lines' => [['variant_id' => $rv, 'qty' => '1']]]);
check('a missing reason is refused', $r['status'] === 422);
$r = $a->call('GET', "/stock/batch-options?variant=$rv&warehouse=$wh&in_stock=1");
check('the roll options list the roll with its balance', (int)($r['json'][0]['id'] ?? 0) === $bid2 && (float)$r['json'][0]['balance'] === 50.0);
$r = $a->call('GET', "/stock/placement?variant=$rv&warehouse=$wh&batch=$bid2");
check('the placement hint says which rack holds it', (int)($r['json'][0]['location_id'] ?? 0) === $rack1 && ($r['json'][0]['code'] ?? '') === 'AP-01');
$r = $a->call('GET', "/stock/adjustments/$adjId");
check('the adjustment page data has the document and its lines', ($r['json']['data']['doc']['doc_no'] ?? '') !== '' && count($r['json']['data']['lines']) === 1 && ($r['json']['data']['lines'][0]['rack'] ?? '') === 'AP-01');
$rec = $a->call('GET', "/record/adjustment/$adjId");
check('the adjustment record page shows the reason as a label and who/when as facts', str_contains(json_encode($rec['json']), 'Opening stock') && str_contains(json_encode($rec['json']), '"By"'));

$bal = fn(int $w, int $loc = -1) => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ?' . ($loc >= 0 ? ' AND location_id = ' . $loc : ''), [$tA, $bid2, $w]);
$r = $a->call('POST', '/stock/transfers', ['cf' => [$CF['transfer'] => 'cf-transfer'], 'warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'transport' => 'Shree Travels', 'vehicle_no' => 'MH12AB1234', 'lr_no' => 'LR-77', 'expected_date' => date('Y-m-d', strtotime('+2 days')),
    'lines' => [['variant_id' => $rv, 'batch_id' => $bid2, 'location_id' => $rack1, 'to_location_id' => $rack2, 'qty' => '20']]]);
$trfId = (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer'", [$tA]);
check('a transfer between warehouses is a dispatch: it leaves the sender and is NOT yet in the other warehouse', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$trfId]) === 'in_transit' && $bal($wh) === 30.0 && $bal($wh2) === 0.0);
check('the dispatch keeps transport details and only an "out" ledger row exists', DB::val('SELECT transport FROM stock_docs WHERE id = ?', [$trfId]) === 'Shree Travels' && DB::val('SELECT lr_no FROM stock_docs WHERE id = ?', [$trfId]) === 'LR-77'
    && (int)DB::val("SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND ref_type = 'stock_doc' AND ref_id = ? AND type = 'transfer_out'", [$tA, $trfId]) === 1 && (int)DB::val("SELECT COUNT(*) FROM stock_ledger WHERE ref_type = 'stock_doc' AND ref_id = ? AND type = 'transfer_in'", [$trfId]) === 0);
$r = $a->call('GET', "/stock?q=ARL-1");
check('stock on hand shows 30 and the 20 m on the road separately', (float)$r['json']['data']['rows'][0]['qty'] === 30.0 && (float)$r['json']['data']['rows'][0]['in_transit'] === 20.0 && (float)$r['json']['data']['transitTotal'] === 20.0);
$r = $a->call('GET', "/stock/transfers/$trfId");
$tl = $r['json']['data']['lines'][0] ?? [];
check('the transfer page data tracks sent / received / on the road with the status', ($r['json']['data']['between'] ?? false) === true && ($r['json']['data']['open'] ?? false) === true && (float)$r['json']['data']['sum']['pending'] === 20.0 && (float)$tl['pending'] === 20.0 && count($r['json']['data']['events']) === 1 && $r['json']['data']['events'][0]['event'] === 'dispatch');
$lid = (string)$tl['id'];
$r = $a->call('POST', "/stock/transfers/$trfId/receive", ['lines' => [$lid => ['qty' => '25', 'location_id' => $rack2]]]);
check('receiving more than what is on the road is refused', $r['status'] === 422 && $bal($wh2) === 0.0);
$r = $a->call('POST', "/stock/transfers/$trfId/receive", ['lines' => [$lid => ['qty' => '0']]]);
check('receiving nothing is refused', $r['status'] === 422);
$r = $a->call('POST', "/stock/transfers/$trfId/receive", ['lines' => [$lid => ['qty' => '5', 'location_id' => $rack1]]]);
check('a rack of the SENDING warehouse cannot be used to receive', $r['status'] === 422 && $bal($wh2) === 0.0);
$r = $a->call('POST', "/stock/transfers/$trfId/receive", ['note' => 'First lot', 'lines' => [$lid => ['qty' => '15', 'location_id' => $rack2, 'note' => 'one roll end wet']]]);
check('a partial receipt puts that part on the rack and the status becomes "partly received"', $r['status'] === 200 && $bal($wh2, $rack2) === 15.0 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$trfId]) === 'partial');
check('the same roll now exists in both warehouses (identity kept)', $bal($wh) === 30.0 && (int)DB::val('SELECT COUNT(DISTINCT batch_id) FROM stock_balances WHERE batch_id = ? AND qty > 0', [$bid2]) === 1);
$r = $a->call('POST', "/stock/transfers/$trfId/cancel");
check('a transfer that was partly received cannot be cancelled', $r['status'] === 422 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$trfId]) === 'partial');
$r = $a->call('POST', "/stock/transfers/$trfId/receive", ['lines' => [$lid => ['qty' => '5']]]);
check('the rest is received later, on the rack suggested at dispatch when none is said; the transfer is complete', $r['status'] === 200 && $bal($wh2, $rack2) === 20.0 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$trfId]) === 'received' && DB::val('SELECT received_at FROM stock_docs WHERE id = ?', [$trfId]) !== null);
check('a finished transfer cannot be received again', $a->call('POST', "/stock/transfers/$trfId/receive", ['lines' => [$lid => ['qty' => '1']]])['status'] === 422);
$r = $a->call('GET', "/stock/transfers/$trfId");
check('the history lists the dispatch and both receipts with who and where', count($r['json']['data']['events']) === 3 && $r['json']['data']['events'][1]['event'] === 'receive' && ($r['json']['data']['events'][1]['rack'] ?? '') === 'B-1' && ($r['json']['data']['events'][1]['user_name'] ?? '') !== '' && $r['json']['data']['events'][1]['note'] === 'one roll end wet');
$r = $a->call('GET', "/stock/transfers/$trfId/challan");
check('the dispatch challan (paper that travels with the goods) has its data: both warehouses, transport and the lines', $r['status'] === 200 && ($r['json']['view'] ?? '') === 'app/print/transfer_challan' && ($r['json']['data']['doc']['lr_no'] ?? '') === 'LR-77' && ($r['json']['data']['to']['name'] ?? '') === 'Api Godown' && count($r['json']['data']['lines']) === 1);
$r = $a->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh, 'lines' => [['variant_id' => $rv, 'batch_id' => $bid2, 'location_id' => $rack1, 'to_location_id' => $rack1, 'qty' => '1']]]);
check('moving to the very same place is refused', $r['status'] === 422);
$trfId = (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer'", [$tA]);
$r = $a->call('GET', "/stock/transfers/$trfId");
check('the transfer page data names both warehouses and racks', ($r['json']['data']['doc']['to_warehouse'] ?? '') === 'Api Godown' && ($r['json']['data']['lines'][0]['to_rack'] ?? '') === 'B-1');

$r = $a->call('GET', '/stock?q=ARL-1');
check('the stock overview has the item with its rack split and value', $r['status'] === 200 && count($r['json']['data']['rows']) === 1 && (float)$r['json']['data']['rows'][0]['qty'] === 50.0 && count($r['json']['data']['rows'][0]['racks']) === 2 && (float)$r['json']['data']['totalValue'] > 0);
$r = $a->call('GET', "/stock/racks?warehouse=$wh2");
check('stock by rack filters by warehouse', count($r['json']['data']['rows']) === 1 && $r['json']['data']['rows'][0]['rack'] === 'B-1');
$r = $a->call('GET', '/stock/batches?q=ARL-1');
check('the roll list shows the roll', count($r['json']['data']['rows']) === 1 && (float)$r['json']['data']['rows'][0]['balance'] === 50.0);
$r = $a->call('GET', "/stock/batches/$bid2");
check('the roll page has where it is and its history', count($r['json']['data']['where']) === 2 && count($r['json']['data']['history']) >= 3 && (float)$r['json']['data']['balance'] === 50.0);
$r = $a->call('GET', '/stock/ledger?type=transfer_out');
check('the ledger filters by movement type', $r['status'] === 200 && count($r['json']['data']['rows']) >= 1 && isset($r['json']['data']['types']['transfer_out']));

$r = $a->call('POST', '/stock/takes', ['cf' => [$CF['stocktake'] => 'cf-stocktake'], 'warehouse_id' => $wh2, 'note' => 'API count']);
$takeId = (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake'", [$tA]);
$r = $a->call('GET', "/stock/takes/$takeId");
$tl = $r['json']['data']['lines'] ?? [];
check('a stock-take lists what the system holds in the warehouse', $takeId > 0 && count($tl) === 1 && (float)$tl[0]['expected_qty'] === 20.0);
$r = $a->call('POST', "/stock/takes/$takeId", ['action' => 'save', 'counts' => [(string)$tl[0]['id'] => '18']]);
check('saving counts keeps the draft and leaves stock alone', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$takeId]) === 'draft' && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND warehouse_id = ?', [$tA, $wh2]) === 20.0);
$r = $a->call('POST', "/stock/takes/$takeId", ['action' => 'post', 'counts' => [(string)$tl[0]['id'] => '18']]);
check('posting corrects stock to the counted quantity', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$takeId]) === 'posted' && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE tenant_id = ? AND warehouse_id = ?', [$tA, $wh2]) === 18.0);
$r = $a->call('POST', "/stock/takes/$takeId/delete");
check('a posted stock-take cannot be deleted', $r['status'] === 422 && DB::val('SELECT 1 FROM stock_docs WHERE id = ?', [$takeId]));
$rec = $a->call('GET', "/record/stocktake/$takeId");
check('the stock-take record page has its started date and warehouse', $rec['status'] === 200 && str_contains(json_encode($rec['json']), 'Api Godown') && str_contains(json_encode($rec['json']), 'Started'));
$r = $a->call('POST', '/stock/takes', ['warehouse_id' => $wh2]);
$take2 = (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake'", [$tA]);
$r = $a->call('POST', "/stock/takes/$take2/delete");
check('a draft stock-take can be deleted', $r['status'] === 200 && !DB::val('SELECT 1 FROM stock_docs WHERE id = ?', [$take2]));
$r = $a->call('POST', "/warehouses/$wh2/delete");
check('a warehouse with stock history cannot be deleted (the reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM warehouses WHERE id = ?', [$wh2]));
$r = $a->call('POST', "/locations/$rack2/delete");
check('a rack holding stock cannot be deleted', $r['status'] === 422 && DB::val('SELECT 1 FROM locations WHERE id = ?', [$rack2]));
$r = $a->call('POST', "/warehouses/$wh2/default");
check('a warehouse can be made the default', $r['status'] === 200 && (int)DB::val('SELECT is_default FROM warehouses WHERE id = ?', [$wh2]) === 1);
$a->call('POST', "/warehouses/$wh/default");

echo "Dashboard through the API\n";
$r = $a->call('GET', '/dashboard?range=90')['json']['data'];
check('the dashboard hands over its widgets drawn like the classic page (charts, ageing bars, lists), the period buttons and the layout', str_contains($r['html']['trend'], 'viz-svg') && str_contains($r['html']['kpi'], 'Net sales') && isset($r['html']['age_rec']) && $r['ranges']['90'] === '90 days' && $r['p']['key'] === '90' && $r['spans']['trend'] === 12 && !isset($r['html']['setup']) && isset($r['layout']['order']));
$a->call('POST', '/dashboard/layout', ['order' => ['trend', 'kpi', 'bogus'], 'show' => ['trend', 'kpi']]);
$lay = $a->call('GET', '/dashboard')['json']['data']['layout'];
check('the dashboard can be customised: order and hidden widgets are kept per user, unknown ones ignored, and reset brings the default back', array_slice(array_values(array_diff($lay['order'], ['setup'])), 0, 2) === ['trend', 'kpi'] && in_array('low', $lay['hidden'], true) && !in_array('bogus', $lay['order'], true));
$a->call('POST', '/dashboard/layout', ['reset' => 1]);
check('reset restores the standard layout', $a->call('GET', '/dashboard')['json']['data']['layout']['hidden'] === []);
$cls = new Client($base); $cls->post('/login', ['email' => $emA, 'password' => 'Password123'], '/login'); $dh = $cls->get('/dashboard')['body'];
check('the classic dashboard page still draws completely (layout, charts, customize panel)', str_contains($dh, 'class="dash-grid"') && str_contains($dh, 'viz-svg') && str_contains($dh, 'Customize dashboard') && str_contains($dh, 'rel="icon"'));

echo "Sales documents through the API\n";
check('the session names the sales order statuses', ($a->session()['json']['soStatus']['confirmed'][0] ?? '') === 'Confirmed');
$r = $a->call('GET', '/sales/orders/create');
check('the order form data has customers and warehouses', $r['status'] === 200 && count($r['json']['data']['customers']) >= 1 && count($r['json']['data']['warehouses']) >= 2);
$r = $a->call('GET', "/lookup/sale-items?q=ARL-1&customer=$cid&warehouse=$wh");
check('the sale item search returns price, tax, free stock and roll flags', (int)($r['json'][0]['id'] ?? 0) === $rv && (float)$r['json'][0]['price'] === 300.0 && (float)$r['json'][0]['available'] === 30.0 && (int)$r['json'][0]['track_batch'] === 1);
$r = $a->call('POST', '/sales/orders', ['customer_id' => $cid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'delivery_charge' => '100', 'notes' => 'API order',
    'lines' => [['variant_id' => $rv, 'qty' => '10', 'unit_price' => '300', 'discount_pct' => '10', 'tax_rate' => '5'], ['variant_id' => $v2, 'qty' => '2', 'unit_price' => '150', 'discount_pct' => '', 'tax_rate' => '0']]]);
$soId = (int)DB::val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
check('a sales order saves as a draft with totals worked out by the server', $r['status'] === 200 && $soId > 0 && DB::val('SELECT status FROM sales_orders WHERE id = ?', [$soId]) === 'draft' && (float)DB::val('SELECT total FROM sales_orders WHERE id = ?', [$soId]) === 3235.0);
$r = $a->call('POST', '/sales/orders', ['customer_id' => $cid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => []]);
check('an order with no items is refused', $r['status'] === 422);
$r = $a->call('GET', "/sales/orders/$soId/edit");
check('the edit form data carries the lines back', count($r['json']['data']['oldLines'] ?? []) === 2 && (float)$r['json']['data']['oldLines'][0]['discount_pct'] === 10.0);
$r = $a->call('GET', "/sales/orders/$soId");
check('the order page data has items with free stock, deliveries and advances', count($r['json']['data']['items']) === 2 && isset($r['json']['data']['items'][0]['free']) && is_array($r['json']['data']['advances']) && (float)$r['json']['data']['remaining'] === 12.0);
$r = $a->call('POST', "/sales/orders/$soId/advance", ['amount' => '500', 'paid_on' => date('Y-m-d'), 'method' => 'cash', 'reference' => 'ADV1']);
check('an advance is recorded on the order', $r['status'] === 200 && (float)DB::val('SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE order_id = ? AND invoice_id IS NULL', [$soId]) === 500.0);
$r = $a->call('POST', "/sales/orders/$soId/confirm");
check('confirming reserves the stock (order is confirmed)', $r['status'] === 200 && DB::val('SELECT status FROM sales_orders WHERE id = ?', [$soId]) === 'confirmed');
$r = $a->call('GET', "/sales/orders/$soId/deliver");
$dl = $r['json']['data']['oldLines'] ?? [];
$byV = []; foreach ($dl as $l) $byV[(int)$l['variant_id']] = $l;
check('the delivery form suggests the roll and rack for each line', count($dl) >= 2 && (int)$byV[$rv]['batch_id'] === $bid2 && (int)$byV[$rv]['location_id'] === $rack1 && (float)$byV[$rv]['left'] === 10.0 && !empty($byV[$rv]['order_item_id']));
$r = $a->call('POST', '/sales/deliveries', ['cf' => [$CF['delivery'] => 'cf-delivery'], 'order_id' => $soId, 'delivery_date' => date('Y-m-d'), 'make_invoice' => 1, 'lines' => [
    ['order_item_id' => $byV[$rv]['order_item_id'], 'variant_id' => $rv, 'qty' => '4', 'batch_id' => $bid2, 'location_id' => $rack1],
    ['order_item_id' => $byV[$v2]['order_item_id'], 'variant_id' => $v2, 'qty' => '0', 'batch_id' => '0', 'location_id' => '0']]]);
$dlv1 = (int)DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$tA]);
$inv1 = (int)DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? AND delivery_id = ?', [$tA, $dlv1]);
check('a part delivery takes the roll quantity out and makes an invoice', $r['status'] === 200 && $inv1 > 0 && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ? AND location_id = ?', [$tA, $bid2, $wh, $rack1]) === 26.0 && DB::val('SELECT status FROM sales_orders WHERE id = ?', [$soId]) === 'partial');
check('the 500 advance went onto that invoice', (float)DB::val("SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE invoice_id = ? AND method = 'advance'", [$inv1]) > 0);
$r = $a->call('POST', '/sales/deliveries', ['order_id' => $soId, 'delivery_date' => date('Y-m-d'), 'lines' => [['order_item_id' => $byV[$rv]['order_item_id'], 'variant_id' => $rv, 'qty' => '99', 'batch_id' => $bid2, 'location_id' => $rack1]]]);
check('delivering more than is due is refused', $r['status'] === 422);
$r = $a->call('POST', '/sales/deliveries', ['order_id' => $soId, 'delivery_date' => date('Y-m-d'), 'lines' => [['order_item_id' => $byV[$rv]['order_item_id'], 'variant_id' => $rv, 'qty' => '1', 'batch_id' => '0', 'location_id' => '0']]]);
check('a roll item without a roll chosen is refused', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'roll'));
$r = $a->call('POST', '/sales/deliveries', ['order_id' => $soId, 'delivery_date' => date('Y-m-d'), 'make_invoice' => 1, 'lines' => [
    ['order_item_id' => $byV[$rv]['order_item_id'], 'variant_id' => $rv, 'qty' => '6', 'batch_id' => $bid2, 'location_id' => $rack1],
    ['order_item_id' => $byV[$v2]['order_item_id'], 'variant_id' => $v2, 'qty' => '2', 'batch_id' => '0', 'location_id' => '0']]]);
$dlv2 = (int)DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$tA]);
check('the rest is delivered and the order is delivered', $r['status'] === 200 && $dlv2 !== $dlv1 && DB::val('SELECT status FROM sales_orders WHERE id = ?', [$soId]) === 'delivered' && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ? AND location_id = ?', [$tA, $bid2, $wh, $rack1]) === 20.0);
$r = $a->call('GET', "/sales/deliveries/$dlv1");
check('the delivery page data lists the roll and rack of each line', ($r['json']['data']['items'][0]['batch_no'] ?? '') !== '' && ($r['json']['data']['items'][0]['rack'] ?? '') === 'AP-01' && ($r['json']['data']['d']['invoice_no'] ?? '') !== '');
$rec = $a->call('GET', "/record/delivery/$dlv1");
check('the delivery record page links customer, order and invoice', str_contains(json_encode($rec['json']), 'sales\\/orders\\/' . $soId) && str_contains(json_encode($rec['json']), 'sales\\/invoices\\/' . $inv1) && str_contains(json_encode($rec['json']), 'customers\\/' . $cid));

$r = $a->call('GET', "/sales/invoices/$inv1");
$due1 = (float)($r['json']['data']['due'] ?? -1);
check('the invoice page data has lines, payments and the amount due', count($r['json']['data']['items']) >= 1 && $due1 > 0 && isset($r['json']['data']['returns']));
$r = $a->call('POST', "/sales/invoices/$inv1/payments", ['amount' => (string)($due1 + 100), 'paid_on' => date('Y-m-d'), 'method' => 'cash']);
check('receiving more than the balance is refused', $r['status'] === 422);
$r = $a->call('POST', "/sales/invoices/$inv1/payments", ['amount' => '100', 'paid_on' => date('Y-m-d'), 'method' => 'upi', 'reference' => 'UTR5']);
$payId = (int)DB::val("SELECT MAX(id) FROM customer_payments WHERE invoice_id = ? AND method = 'upi'", [$inv1]);
check('a payment is recorded on the invoice', $r['status'] === 200 && $payId > 0 && (float)DB::val('SELECT paid_amount FROM sales_invoices WHERE id = ?', [$inv1]) > 100);
$a->call('POST', "/sales/invoices/$inv1/payments/$payId/delete");
check('a payment can be removed again', !DB::val('SELECT 1 FROM customer_payments WHERE id = ?', [$payId]));
$r = $a->call('GET', "/sales/invoices/$inv1/edit");
check('the invoice edit form data comes back', ($r['json']['data']['i']['invoice_no'] ?? '') !== '');
$r = $a->call('POST', "/sales/invoices/$inv1", ['invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+15 days')), 'delivery_charge' => '150', 'installation_charge' => '0', 'notes' => 'API note']);
check('invoice details save and the total follows the charges', $r['status'] === 200 && (float)DB::val('SELECT delivery_charge FROM sales_invoices WHERE id = ?', [$inv1]) === 150.0 && DB::val('SELECT notes FROM sales_invoices WHERE id = ?', [$inv1]) === 'API note');
$rec = $a->call('GET', "/record/invoice/$inv1");
check('the invoice record page has customer, delivery and balance facts', str_contains(json_encode($rec['json']), 'sales\\/deliveries\\/' . $dlv1) && str_contains(json_encode($rec['json']), 'Balance due'));
$r = $a->call('GET', '/sales/invoices?status=unpaid');
check('the invoice list filters and totals the balance due', $r['status'] === 200 && isset($r['json']['data']['totalDue']) && (float)$r['json']['data']['totalDue'] > 0);

$r = $a->call('GET', "/sales/returns/create?invoice=$inv1");
$ri = $r['json']['data']['items'][0] ?? null;
check('the sales return form data lists what can come back and the racks', $ri && (float)$ri['returnable'] === 4.0 && isset($r['json']['data']['racks'][$wh]));
check('the invoice pick list answers without an invoice chosen', count($a->call('GET', '/sales/returns/create')['json']['data']['rows']) >= 2);
$r = $a->call('POST', '/sales/returns', ['cf' => [$CF['sales_return'] => 'cf-sales_return'], 'invoice_id' => $inv1, 'return_date' => date('Y-m-d'), 'reason' => 'Wrong shade', 'lines' => [(string)$ri['id'] => ['qty' => '2', 'location_id' => $rack1, 'restock' => 1]]]);
$srId = (int)DB::val('SELECT MAX(id) FROM sales_returns WHERE tenant_id = ?', [$tA]);
check('a return puts the goods back on the rack and credits the invoice', $r['status'] === 200 && $srId > 0 && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ? AND location_id = ?', [$tA, $bid2, $wh, $rack1]) === 22.0 && (float)DB::val('SELECT returned_amount FROM sales_invoices WHERE id = ?', [$inv1]) > 0);
$r = $a->call('POST', '/sales/returns', ['invoice_id' => $inv1, 'return_date' => date('Y-m-d'), 'lines' => [(string)$ri['id'] => ['qty' => '50', 'location_id' => '0', 'restock' => 1]]]);
check('returning more than was sold is refused', $r['status'] === 422);
$r = $a->call('GET', "/sales/returns/$srId");
check('the return page data has its lines', count($r['json']['data']['items']) === 1 && (float)$r['json']['data']['r']['total'] > 0);
$rec = $a->call('GET', "/record/sales_return/$srId");
check('the return record page links its invoice and shows the credit', str_contains(json_encode($rec['json']), 'sales\\/invoices\\/' . $inv1) && str_contains(json_encode($rec['json']), 'Total credit'));
check('the sales order record page has created-by and total', str_contains(json_encode($a->call('GET', "/record/sales_order/$soId")['json']), 'Created by'));

$r = $a->call('POST', '/sales/deliveries', ['customer_id' => $cid, 'warehouse_id' => $wh, 'delivery_date' => date('Y-m-d'), 'make_invoice' => 1, 'lines' => [['variant_id' => $v2, 'qty' => '1', 'unit_price' => '150', 'discount_pct' => '', 'tax_rate' => '0', 'batch_id' => '0', 'location_id' => '0']]]);
$dlv3 = (int)DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$tA]);
check('goods can be delivered without an order (and invoiced)', $r['status'] === 200 && $dlv3 > $dlv2 && DB::val('SELECT order_id FROM deliveries WHERE id = ?', [$dlv3]) === null && DB::val('SELECT 1 FROM sales_invoices WHERE delivery_id = ?', [$dlv3]) !== null);
$r = $a->call('GET', '/sales/deliveries/create');
check('the direct delivery form data has customers and warehouses', count($r['json']['data']['customers']) >= 1 && count($r['json']['data']['warehouses']) >= 2);
check('lists answer with rows: orders, deliveries, returns', count($a->call('GET', '/sales/orders?status=delivered')['json']['data']['rows']) >= 1 && count($a->call('GET', '/sales/deliveries')['json']['data']['rows']) >= 3 && count($a->call('GET', '/sales/returns')['json']['data']['rows']) >= 1);
$a->call('POST', '/sales/orders', ['customer_id' => $cid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => [['variant_id' => $v2, 'qty' => '1', 'unit_price' => '150']]]);
$so2 = (int)DB::val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
$r = $a->call('POST', "/sales/orders/$so2/delete");
check('a draft order can be deleted', $r['status'] === 200 && !DB::val('SELECT 1 FROM sales_orders WHERE id = ?', [$so2]));
$r = $a->call('POST', "/sales/orders/$soId/delete");
check('a delivered order cannot be deleted (reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM sales_orders WHERE id = ?', [$soId]));

echo "Reports through the API\n";
$r = $a->call('GET', '/reports');
check('the report list comes grouped, with each report\'s title and description', $r['status'] === 200 && isset($r['json']['data']['groups']['stock']['stock-summary']['title']) && isset($r['json']['data']['names']['sales']) && !str_contains($r['raw'], '"run"'));
$r = $a->call('GET', '/reports/stock-summary?q=ARL-1');
$srow = $r['json']['data']['rows'][0] ?? [];
check('a report returns its definition, the filters in force, rows and totals', $r['status'] === 200 && isset($r['json']['data']['def']['columns']['on_hand']) && ($srow['sku'] ?? '') === 'ARL-1' && (float)$srow['on_hand'] > 0 && isset($r['json']['data']['lists']['warehouse']) && ($r['json']['data']['f']['q'] ?? '') === 'ARL-1');
check('the sales register for the period includes today\'s sales', count($a->call('GET', '/reports/sales-register?from=' . date('Y-m-d', strtotime('-1 day')) . '&to=' . date('Y-m-d'))['json']['data']['rows'] ?? []) >= 1);
$r = $a->call('GET', '/reports/customer-statement');
check('a statement asks for its customer first (no rows until chosen)', $r['status'] === 200 && ($r['json']['data']['def']['needs'] ?? '') === 'customer' && $r['json']['data']['rows'] === []);
$r = $a->call('GET', "/reports/customer-statement?customer=$cid");
check('and lists the customer\'s invoices and payments once chosen', count($r['json']['data']['rows']) >= 1);
check('an unknown report is a 404 JSON', $a->call('GET', '/reports/not-a-report')['status'] === 404);
$ex = $a->call('GET', '/reports/stock-summary/export/csv?q=ARL-1');
check('the CSV export is still the plain download', $ex['status'] === 200 && str_contains($ex['raw'], 'ARL-1') && $ex['json'] === null);
$r = $a->call('POST', '/reports/low-stock/create-pos', ['pick' => []]);
check('creating orders from low stock with nothing low explains itself', $r['status'] === 200 && str_contains(json_encode($r['json']['messages'] ?? []), 'Nothing'));

echo "Transfers between warehouses: in transit, shortage, cancel, permissions\n";
$a->call('POST', '/items', ['name' => 'Api Roll Two', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'reorder_level' => '100', 'variants' => [['name' => '', 'sku' => 'ARL-2', 'cost_price' => '150', 'sale_price' => '250']], 'cf' => (object)[]]);
$rv2 = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'ARL-2'", [$tA]);
$itm2 = (int)DB::val('SELECT item_id FROM item_variants WHERE id = ?', [$rv2]);
$a->call('POST', '/stock/adjustments', ['warehouse_id' => $wh, 'reason' => 'opening', 'lines' => [['variant_id' => $rv2, 'batch_id' => 'new', 'lot_no' => 'T2', 'location_id' => $rack1, 'qty' => '40']]]);
$rb2 = (int)DB::val('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rv2]);
$sendT = fn(array $extra = [], string $qty = '40', int $from = 0, int $to = 0, int $rack = 0) => $a->call('POST', '/stock/transfers', $extra + ['warehouse_id' => $from ?: $wh, 'to_warehouse_id' => $to ?: $wh2,
    'lines' => [['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack ?: $rack1, 'qty' => $qty]]]);
$bw = fn(int $w) => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ?', [$tA, $rb2, $w]);
$lastT = fn() => (int)DB::val("SELECT MAX(id) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer'", [$tA]);

check('a past "expected arrival" date is refused', $sendT(['expected_date' => date('Y-m-d', strtotime('-1 day'))])['status'] === 422 && $bw($wh) === 40.0);
check('the same roll listed twice on one dispatch is refused', $a->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'lines' => [['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack1, 'qty' => '5'], ['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack1, 'qty' => '5']]])['status'] === 422 && $bw($wh) === 40.0);
$sendT(['transport' => 'Own truck']); $t2 = $lastT();
check('the whole roll is dispatched: nothing left in the sender, nothing yet in the receiver', $bw($wh) === 0.0 && $bw($wh2) === 0.0 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t2]) === 'in_transit');
$r = $a->call('GET', '/stock/batches?q=ARL-2');
check('a roll on the road is listed as "In transit" (not "Finished") in the default roll list', ($r['json']['data']['rows'][0]['batch_no'] ?? '') !== '' && (float)$r['json']['data']['rows'][0]['balance'] === 0.0 && (float)$r['json']['data']['rows'][0]['transit'] === 40.0);
check('"Finished" does not list it but "In transit" does', count($a->call('GET', '/stock/batches?q=ARL-2&status=finished')['json']['data']['rows']) === 0 && count($a->call('GET', '/stock/batches?q=ARL-2&status=transit')['json']['data']['rows']) === 1);
$r = $a->call('GET', "/stock/batches/$rb2");
check('the roll page shows where it is: on the road to the other warehouse, with the transfer', count($r['json']['data']['transit']) === 1 && (float)$r['json']['data']['transit'][0]['qty'] === 40.0 && $r['json']['data']['transit'][0]['to_warehouse'] === 'Api Godown');
$r = $a->call('GET', "/items/$itm2");
check('the item page shows the quantity in transit on the variant and the roll', (float)$r['json']['data']['variants'][0]['in_transit'] === 40.0 && (float)$r['json']['data']['variants'][0]['batches'][0]['in_transit'] === 40.0);
$r = $a->call('GET', '/reports/batch-stock?q=ARL-2');
check('the roll report says "In transit" too', ($r['json']['data']['rows'][0]['status'] ?? '') === 'In transit');
$r = $a->call('GET', '/reports/low-stock');
$low = array_values(array_filter($r['json']['data']['rows'], fn($x) => $x['sku'] === 'ARL-2'))[0] ?? null;
check('low stock counts goods on the road as still owned (40 on hand, not 0)', $low && (float)$low['on_hand'] === 40.0);
$r = $a->call('GET', '/reports/transfers?status=open');
check('the transfers report lists what is on the road with days and status', count($r['json']['data']['rows']) >= 1 && ($r['json']['data']['rows'][0]['status'] ?? '') === 'In transit' && (float)$r['json']['data']['rows'][0]['pending'] > 0);
check('the dashboard counts transfers on the road', (int)$a->call('GET', '/dashboard')['json']['data']['transitOpen'] >= 1);
check('the transfer list filters and counts by status', count($a->call('GET', '/stock/transfers?status=open')['json']['data']['rows']) >= 1 && (int)$a->call('GET', '/stock/transfers')['json']['data']['counts']['open'] >= 1);
$a->call('POST', "/stock/transfers/$t2/cancel");
check('cancelling a dispatch puts every roll back on the rack it left', DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t2]) === 'cancelled' && $bw($wh) === 40.0 && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND location_id = ?', [$tA, $rb2, $rack1]) === 40.0
    && (int)DB::val("SELECT COUNT(*) FROM stock_ledger WHERE ref_type = 'stock_doc' AND ref_id = ? AND type = 'transfer_return'", [$t2]) === 1);
check('a cancelled transfer cannot be cancelled or received again', $a->call('POST', "/stock/transfers/$t2/cancel")['status'] === 422 && $a->call('POST', "/stock/transfers/$t2/receive", ['lines' => [(string)DB::val('SELECT id FROM stock_doc_lines WHERE doc_id = ?', [$t2]) => ['qty' => '1']]])['status'] === 422);

$sendT(['expected_date' => date('Y-m-d', strtotime('+3 days'))]);
$t3 = $lastT(); $l3 = (string)DB::val('SELECT id FROM stock_doc_lines WHERE doc_id = ?', [$t3]);
$a->call('POST', "/stock/transfers/$t3/receive", ['lines' => [$l3 => ['qty' => '25', 'location_id' => $rack2]]]);
check('25 of 40 arrive: 15 are still unaccounted for and the status is "partly received"', $bw($wh2) === 25.0 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t3]) === 'partial');
check('settling more than is unaccounted for is refused', $a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '16', 'action' => 'lost']]])['status'] === 422);
check('settling without saying what happened is refused', $a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '5']]])['status'] === 422);
$a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '5', 'action' => 'lost', 'note' => 'Roll end lost at the transporter']]]);
check('writing off 5 as lost changes no stock but is recorded; 10 are still unaccounted', $bw($wh) === 0.0 && $bw($wh2) === 25.0 && (float)DB::val('SELECT qty_lost FROM stock_doc_lines WHERE id = ?', [$l3]) === 5.0 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t3]) === 'partial');
$a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '3', 'action' => 'return', 'location_id' => $rack1]]]);
check('sending 3 back adds them to the sender\'s rack again', $bw($wh) === 3.0 && (float)DB::val('SELECT qty FROM stock_balances WHERE tenant_id = ? AND batch_id = ? AND warehouse_id = ? AND location_id = ?', [$tA, $rb2, $wh, $rack1]) === 3.0);
$r = $a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '2', 'action' => 'return', 'location_id' => $rack2]]]);
check('"send back" onto a rack of the OTHER warehouse is refused', $r['status'] === 422 && $bw($wh) === 3.0);
$a->call('POST', "/stock/transfers/$t3/settle", ['lines' => [$l3 => ['qty' => '7', 'action' => 'lost']]]);
check('once every metre is accounted for the transfer closes "received with a shortage"', DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t3]) === 'received_short' && (float)DB::val('SELECT qty - qty_received - qty_lost - qty_returned FROM stock_doc_lines WHERE id = ?', [$l3]) === 0.0);
$ev = $a->call('GET', "/stock/transfers/$t3")['json']['data']['events'];
check('every step is in the history: dispatch, receipt, two write-offs, one send-back', array_count_values(array_column($ev, 'event')) === ['dispatch' => 1, 'receive' => 1, 'lost' => 2, 'returned' => 1]);
$bad = DB::all('SELECT b.variant_id FROM stock_balances b LEFT JOIN (SELECT variant_id, warehouse_id, batch_id, location_id, SUM(qty_change) AS s FROM stock_ledger WHERE tenant_id = ? GROUP BY variant_id, warehouse_id, batch_id, location_id) l ON l.variant_id = b.variant_id AND l.warehouse_id = b.warehouse_id AND l.batch_id = b.batch_id AND l.location_id = b.location_id WHERE b.tenant_id = ? AND ABS(b.qty - COALESCE(l.s,0)) > 0.0005', [$tA, $tA]);
check('after all of that the ledger still equals the balances everywhere', !$bad);
$r = $a->call('GET', '/reports/transfers?status=short');
check('the shortage report shows what was lost and sent back', ($r['json']['data']['rows'][0]['doc_no'] ?? '') !== '' && (float)$r['json']['data']['rows'][0]['lost'] === 12.0 && (float)$r['json']['data']['rows'][0]['returned'] === 3.0 && str_contains($r['json']['data']['rows'][0]['status'], 'shortage'));
$r = $a->call('GET', '/stock/ledger?type=transfer_return');
check('"sent back" is a movement type of its own in the ledger', isset($r['json']['data']['types']['transfer_return']) && count($r['json']['data']['rows']) >= 2);

// inside one warehouse a move is instant, with nothing to receive
$a->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh, 'lines' => [['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack1, 'to_location_id' => (int)DB::val("SELECT id FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND code = 'AP-02'", [$tA, $wh]), 'qty' => '1']]]);
$t5 = $lastT();
check('moving between racks of one warehouse stays instant (status "moved", nothing to receive)', DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t5]) === 'posted' && (float)DB::val('SELECT qty_received FROM stock_doc_lines WHERE doc_id = ?', [$t5]) === 1.0 && $a->call('POST', "/stock/transfers/$t5/receive", ['lines' => []])['status'] === 422);

// who may do what
$a->call('POST', '/roles', ['name' => 'Dispatcher ' . $sfx, 'perms' => ['stock.view', 'stock.transfer', 'items.view']]);
$a->call('POST', '/roles', ['name' => 'Receiver ' . $sfx, 'perms' => ['stock.view', 'stock.receive', 'items.view']]);
$rDis = (int)DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = ?', [$tA, 'Dispatcher ' . $sfx]); $rRec = (int)DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = ?', [$tA, 'Receiver ' . $sfx]);
foreach ([['dis', $rDis], ['rec', $rRec]] as [$k, $rid]) $a->call('POST', '/users', ['name' => ucfirst($k) . ' User', 'email' => "$k-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rid, 'warehouse_id' => '0']);
$dis = new J($base); $dis->session(); $dis->call('POST', '/login', ['email' => "dis-$sfx@test.local", 'password' => 'Password123']); $dis->session();
$rec = new J($base); $rec->session(); $rec->call('POST', '/login', ['email' => "rec-$sfx@test.local", 'password' => 'Password123']); $rec->session();
$r = $dis->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'expected_date' => date('Y-m-d', strtotime('+1 day')), 'lines' => [['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack1, 'qty' => '2']]]);
$t6 = $lastT(); $l6 = (string)DB::val('SELECT id FROM stock_doc_lines WHERE doc_id = ?', [$t6]);
check('a dispatcher can dispatch', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t6]) === 'in_transit');
check('but cannot receive or settle (403)', $dis->call('POST', "/stock/transfers/$t6/receive", ['lines' => [$l6 => ['qty' => '2']]])['status'] === 403 && $dis->call('POST', "/stock/transfers/$t6/settle", ['lines' => [$l6 => ['qty' => '2', 'action' => 'lost']]])['status'] === 403);
check('a receiver cannot dispatch, cancel or settle (403)', $rec->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'lines' => [['variant_id' => $rv2, 'batch_id' => $rb2, 'location_id' => $rack1, 'qty' => '1']]])['status'] === 403
    && $rec->call('POST', "/stock/transfers/$t6/cancel")['status'] === 403 && $rec->call('POST', "/stock/transfers/$t6/settle", ['lines' => [$l6 => ['qty' => '1', 'action' => 'lost']]])['status'] === 403);
DB::run('UPDATE stock_docs SET expected_date = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $t6]);
check('a transfer past its expected date shows as overdue in the list, the counts and the dashboard', count($a->call('GET', '/stock/transfers?status=overdue')['json']['data']['rows']) >= 1 && (int)$a->call('GET', '/stock/transfers')['json']['data']['counts']['overdue'] >= 1 && (int)$a->call('GET', '/dashboard')['json']['data']['transitLate'] >= 1 && count($a->call('GET', '/reports/transfers?status=overdue')['json']['data']['rows']) >= 1);
$r = $rec->call('POST', "/stock/transfers/$t6/receive", ['lines' => [$l6 => ['qty' => '2', 'location_id' => $rack2]]]);
check('the receiver confirms it: the transfer closes and the name of who received it is kept', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$t6]) === 'received' && (string)DB::val('SELECT u.name FROM stock_docs d JOIN users u ON u.id = d.received_by WHERE d.id = ?', [$t6]) === 'Rec User');
check('the role form offers a "receive" permission on Stock', in_array('receive', $a->call('GET', '/roles/create')['json']['data']['modules']['stock']['actions'], true));

echo "Warehouse-wise users: every person works at one warehouse\n";
$rIn = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Warehouse Incharge'", [$tA]); $rOp = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Warehouse Operator'", [$tA]);
check('the Warehouse Incharge and Warehouse Operator roles exist and are tied to a warehouse', $rIn > 0 && $rOp > 0 && (int)DB::val('SELECT COUNT(*) FROM roles WHERE id IN (?, ?) AND warehouse_bound = 1', [$rIn, $rOp]) === 2);
$a->call('POST', '/warehouses', ['name' => 'Api Third', 'code' => 'AP3']);
$wh3 = (int)DB::val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Api Third'", [$tA]);
$a->call('POST', '/locations', ['warehouse_id' => $wh3, 'mode' => 'single', 'code' => 'T-1']);
$mk = fn(string $k, int $role, string $w) => $a->call('POST', '/users', ['name' => "WH $k", 'email' => "wh$k-$sfx@test.local", 'password' => 'Password123', 'role_id' => $role, 'warehouse_id' => $w]);
check('a user must be given a warehouse (or "all warehouses")', $a->call('POST', '/users', ['name' => 'No WH', 'email' => "nowh-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rOp])['status'] === 422 && !DB::val('SELECT 1 FROM users WHERE email = ?', ["nowh-$sfx@test.local"]));
check('an operator or incharge cannot be "all warehouses"', $mk('x', $rOp, '0')['status'] === 422 && $mk('x', $rIn, '0')['status'] === 422);
check('and the warehouse must be a real one', $mk('x', $rOp, '999999')['status'] === 422);
$mk('in', $rIn, (string)$wh); $mk('op2', $rOp, (string)$wh2); $mk('op3', $rOp, (string)$wh3);
check('operators and incharges are saved with their warehouse', (int)DB::val('SELECT warehouse_id FROM users WHERE email = ?', ["whin-$sfx@test.local"]) === $wh && (int)DB::val('SELECT warehouse_id FROM users WHERE email = ?', ["whop2-$sfx@test.local"]) === $wh2);
$login = function (string $k) use ($base, $sfx): J { $c = new J($base); $c->session(); $c->call('POST', '/login', ['email' => "wh$k-$sfx@test.local", 'password' => 'Password123']); $c->session(); return $c; };
$wIn = $login('in'); $wOp2 = $login('op2'); $wOp3 = $login('op3');
check('the session says where the person works', ($wIn->session()['json']['user']['warehouse']['id'] ?? 0) === $wh && array_key_exists('warehouse', $a->session()['json']['user']) && $a->session()['json']['user']['warehouse'] === null);
$names = fn(array $r) => array_column($r['json']['options'] ?? [], 'name');
check('the warehouse pick-lists show only their own warehouse; transfers can still go to any warehouse', count($wOp2->call('GET', '/options/warehouses')['json']['options']) === 1 && count($wOp2->call('GET', '/options/all_warehouses')['json']['options']) >= 3 && count($a->call('GET', '/options/warehouses')['json']['options']) >= 3);

$a->call('POST', '/stock/adjustments', ['warehouse_id' => $wh, 'reason' => 'opening', 'lines' => [['variant_id' => $rv2, 'batch_id' => 'new', 'lot_no' => 'WW', 'location_id' => $rack1, 'qty' => '10']]]);
$rw = (int)DB::val('SELECT MAX(id) FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rv2]);
$a->call('POST', '/stock/adjustments', ['warehouse_id' => $wh2, 'reason' => 'opening', 'lines' => [['variant_id' => $rv2, 'batch_id' => 'new', 'lot_no' => 'W2', 'location_id' => $rack2, 'qty' => '7']]]);
$rw2 = (int)DB::val('SELECT MAX(id) FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rv2]);
$adj = fn(J $c, int $w, int $rack) => $c->call('POST', '/stock/adjustments', ['warehouse_id' => $w, 'reason' => 'correction', 'lines' => [['variant_id' => $rv2, 'batch_id' => 'new', 'lot_no' => 'ZZ', 'location_id' => $rack, 'qty' => '1']]]);
$before = (int)DB::val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ?', [$tA]);
check('an incharge can add stock at their warehouse but not at another', $adj($wIn, $wh, $rack1)['status'] === 200 && $adj($wIn, $wh2, $rack2)['status'] === 422 && (int)DB::val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ?', [$tA]) === $before + 1);
check('and cannot send out stock that sits in another warehouse', $wOp2->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'lines' => [['variant_id' => $rv2, 'batch_id' => $rw, 'location_id' => $rack1, 'qty' => '1']]])['status'] === 422);

$r = $wIn->call('POST', '/stock/transfers', ['warehouse_id' => $wh, 'to_warehouse_id' => $wh2, 'lines' => [['variant_id' => $rv2, 'batch_id' => $rw, 'location_id' => $rack1, 'qty' => '3']]]);
$tw = $lastT(); $lw = (string)DB::val('SELECT id FROM stock_doc_lines WHERE doc_id = ?', [$tw]);
check('the incharge dispatches from their warehouse to another', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$tw]) === 'in_transit');
$sh = fn(J $c) => $c->call('GET', "/stock/transfers/$tw");
check('the sending side sees it but may not receive it', ($sh($wIn)['json']['data']['can']['receive'] ?? true) === false && ($sh($wIn)['json']['data']['can']['cancel'] ?? false) === true
    && $wIn->call('POST', "/stock/transfers/$tw/receive", ['lines' => [$lw => ['qty' => '3']]])['status'] === 422 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$tw]) === 'in_transit');
check('a third warehouse cannot see it at all (not in the list, 404 on the page)', $sh($wOp3)['status'] === 404 && !in_array($tw, array_map('intval', array_column($wOp3->call('GET', '/stock/transfers')['json']['data']['rows'], 'id')), true)
    && $wOp3->call('POST', "/stock/transfers/$tw/receive", ['lines' => [$lw => ['qty' => '3']]])['status'] === 404);
check('only the sender can cancel; the receiver is refused', ($sh($wOp2)['json']['data']['can']['cancel'] ?? true) === false && $wOp2->call('POST', "/stock/transfers/$tw/cancel")['status'] === 422 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$tw]) === 'in_transit');
check('the receiving warehouse sees it as incoming and may receive', ($sh($wOp2)['json']['data']['can']['receive'] ?? false) === true && in_array($tw, array_map('intval', array_column($wOp2->call('GET', '/stock/transfers')['json']['data']['rows'], 'id')), true));
check('and cannot put it on a rack of another warehouse', $wOp2->call('POST', "/stock/transfers/$tw/receive", ['lines' => [$lw => ['qty' => '2', 'location_id' => $rack1]]])['status'] === 422);
$r = $wOp2->call('POST', "/stock/transfers/$tw/receive", ['lines' => [$lw => ['qty' => '2', 'location_id' => $rack2]]]);
check('the receiver books what arrived: partly received', $r['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$tw]) === 'partial');
check('only someone who can adjust stock settles the shortage (an operator cannot; the incharge of the sender can)',
    $wOp2->call('POST', "/stock/transfers/$tw/settle", ['lines' => [$lw => ['qty' => '1', 'action' => 'lost']]])['status'] === 403
    && $wIn->call('POST', "/stock/transfers/$tw/settle", ['lines' => [$lw => ['qty' => '1', 'action' => 'lost']]])['status'] === 200 && DB::val('SELECT status FROM stock_docs WHERE id = ?', [$tw]) === 'received_short');

$whOf = fn(array $rows, string $key) => array_unique(array_map(fn($x) => (string)($x[$key] ?? ''), $rows));
$w2name = (string)DB::val('SELECT name FROM warehouses WHERE id = ?', [$wh2]);
$st = $wOp2->call('GET', "/stock?warehouse=$wh")['json']['data'];
check('the stock list is always held to their warehouse, whatever the filter says', $st['wh'] === $wh2 && count($st['warehouses']) === 1 && !array_filter($st['rows'], fn($x) => array_filter($x['racks'], fn($k) => $k['warehouse'] !== $w2name)));
$rk = $wOp2->call('GET', "/stock/racks?warehouse=$wh")['json']['data'];
check('stock by rack and the ledger show their warehouse only', $rk['rows'] && $whOf($rk['rows'], 'warehouse') === [$w2name] && $whOf($wOp2->call('GET', "/stock/ledger?warehouse=$wh")['json']['data']['rows'], 'warehouse') === [$w2name]);
check('the roll list only has rolls that were in their warehouse or are on the way to it', in_array($rw2, array_map('intval', array_column($wOp2->call('GET', '/stock/batches?status=all')['json']['data']['rows'], 'id')), true)
    && !in_array($rb2, array_map('intval', array_column($wOp3->call('GET', '/stock/batches?status=all')['json']['data']['rows'], 'id')), true));
check('a roll that never touched their warehouse is a 404, and the history of a shared roll shows their side only',
    $wOp3->call('GET', "/stock/batches/$rw")['status'] === 404 && $wOp2->call('GET', "/stock/batches/$rw")['status'] === 200
    && $whOf($wOp2->call('GET', "/stock/batches/$rw")['json']['data']['history'], 'warehouse') === [$w2name]);
check('roll labels follow the same rule: a warehouse user prints only rolls that were in their warehouse', empty($wOp3->call('GET', "/labels/data?type=rolls&ids=$rw")['json']['values']) && isset($wOp2->call('GET', "/labels/data?type=rolls&ids=$rw")['json']['values'][(string)$rw])
    && !in_array($rw, array_map('intval', array_column($wOp3->call('GET', '/labels?type=rolls')['json']['data']['rows'], 'id')), true) && in_array($rw2, array_map('intval', array_column($wOp2->call('GET', '/labels?type=rolls')['json']['data']['rows'], 'id')), true));
check('racks can be listed and shown for their warehouse only', $wOp2->call('GET', '/locations')['status'] === 200 && $whOf($wOp2->call('GET', "/locations?warehouse=$wh")['json']['data']['rows'], 'warehouse') === [$w2name] && $wOp2->call('GET', "/locations/$rack1")['status'] === 404);
check('the warehouse list shows only theirs; head-office jobs are refused', count($wOp2->call('GET', '/warehouses')['json']['data']['rows']) === 1 && $wOp2->call('GET', "/warehouses/$wh")['status'] === 404
    && $wIn->call('POST', '/warehouses', ['name' => 'Sneaky', 'code' => 'SN'])['status'] === 422 && $wIn->call('POST', "/warehouses/$wh/default")['status'] === 422);
check('the item page counts their warehouse only', array_sum(array_map(fn($v) => (float)$v['total'], $wOp2->call('GET', "/items/$itm2")['json']['data']['variants'])) === (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ?', [$tA, $rv2, $wh2]));

$d = $wOp2->call('GET', '/dashboard')['json']['data'];
check('the dashboard is theirs: warehouse stock and transfers, no company money figures', $d['scoped'] === $w2name && (float)$d['salesNow'] === 0.0 && (float)$d['receivable'] === 0.0 && $d['overdue'] === []);
$g = $wIn->call('GET', '/reports')['json']['data']['groups'];
check('reports: only the ones that have a warehouse filter', isset($g['stock']['stock-summary']) && !isset($g['sales']) && $wIn->call('GET', '/reports/sales-register')['status'] === 404 && $wIn->call('GET', '/reports/low-stock')['status'] === 404);
$rs = $wIn->call('GET', "/reports/stock-summary?warehouse=$wh2")['json']['data'];
check('and a warehouse report is held to their warehouse whatever the filter says', ($rs['f']['warehouse'] ?? 0) === $wh && count($rs['lists']['warehouse']) === 1);

$a->call('POST', '/roles', ['name' => 'Site lead ' . $sfx, 'perms' => ['users.view', 'users.create', 'users.edit', 'stock.view'], 'warehouse_bound' => '1']);
$rLead = (int)DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = ?', [$tA, 'Site lead ' . $sfx]);
check('a role can be marked "warehouse staff"', (int)DB::val('SELECT warehouse_bound FROM roles WHERE id = ?', [$rLead]) === 1);
$mk('lead', $rLead, (string)$wh3); $lead = $login('lead');
$r = $lead->call('POST', '/users', ['name' => 'By Lead', 'email' => "bylead-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rOp, 'warehouse_id' => '0']);
check('a warehouse user who adds people can only add them to their own warehouse (and only warehouse roles)', $r['status'] === 200 && (int)DB::val('SELECT warehouse_id FROM users WHERE email = ?', ["bylead-$sfx@test.local"]) === $wh3
    && $lead->call('POST', '/users', ['name' => 'By Lead 2', 'email' => "bylead2-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rDis, 'warehouse_id' => (string)$wh3])['status'] === 403);
$ul = $lead->call('GET', '/users')['json']['data']['users'];
check('and sees only the people of that warehouse', $ul && count(array_unique(array_column($ul, 'warehouse'))) === 1 && !in_array("rec-$sfx@test.local", array_column($ul, 'email'), true));
check('a warehouse with people cannot be deleted or made inactive', $a->call('POST', "/warehouses/$wh3/delete")['status'] === 422 && $a->call('POST', "/warehouses/$wh3", ['name' => 'Api Third', 'code' => 'AP3'])['status'] === 422 && DB::val('SELECT is_active FROM warehouses WHERE id = ?', [$wh3]) == 1);
check('the user list and form show where people work', in_array('Api Third', array_column($a->call('GET', '/users')['json']['data']['users'], 'warehouse'), true) && count($a->call('GET', '/users/create')['json']['data']['warehouses']) >= 3);
check('the owner (not tied to any warehouse) still works across all of them', $a->call('GET', '/stock/batches?status=all')['status'] === 200 && count($a->call('GET', '/warehouses')['json']['data']['rows']) >= 3);

echo "Barcode label designer\n";
$lb = $a->call('GET', '/labels')['json']['data'];
check('the label page data has rows, ready-made templates and what the user may do with them', isset($lb['rows']) && count($lb['templates']) >= 6 && ($lb['can']['create'] ?? false) === true && isset($lb['categories']));
$r = $a->call('GET', '/labels/data?type=items&ids=' . $vOf('APR'));
check('a label\'s values come back resolved (SKU, price, preferred supplier, its details)', ($r['json']['values'][(string)$vOf('APR')]['sku'] ?? '') === 'APR' && ($r['json']['values'][(string)$vOf('APR')]['supplier'] ?? '') === 'Api Mills' && ($r['json']['values'][(string)$vOf('APR')]['code'] ?? '') === 'APR' && isset($r['json']['sample']['design_no']));
$r = $a->call('GET', '/labels/data?type=items&ids=' . $vOf('LK-A'));
check('and the supplier item\'s design, composition and width are available as label fields', ($r['json']['values'][(string)$vOf('LK-A')]['design_no'] ?? '') === 'D-9' && ($r['json']['values'][(string)$vOf('LK-A')]['width'] ?? '') === '48 in');
$svg = fn(string $q) => $a->call('GET', "/labels/barcode?$q")['raw'];
check('barcodes come as pictures: Code 128, EAN-13, Code 39, QR; a bad value gives a visible note', str_starts_with($svg('sym=code128&value=ABC-1'), '<svg') && str_contains($svg('sym=ean13&value=890123456789'), 'EAN-13') && str_contains($svg('sym=code39&value=abc'), 'Code 39') && str_contains($svg('sym=qr&value=hello'), 'QR code') && str_contains($svg('sym=ean13&value=12'), 'bc-bad'));

$tplBody = ['name' => 'Api label', 'for' => 'items', 'w' => 60, 'h' => 30, 'page' => ['mode' => 'roll', 'cols' => 1], 'elements' => [
    ['id' => 'e1', 'type' => 'text', 'x' => 2, 'y' => 2, 'w' => 56, 'h' => 8, 'text' => '{item_name}', 'size' => 9, 'bold' => true, 'font' => 'Comic Sans', 'color' => 'red', 'fit' => true],
    ['id' => 'e2', 'type' => 'barcode', 'x' => 2, 'y' => 12, 'w' => 56, 'h' => 14, 'sym' => 'ean13', 'value' => '{barcode}', 'showtext' => true],
    ['id' => 'e3', 'type' => 'qr', 'x' => 40, 'y' => 2, 'w' => 10, 'h' => 10, 'value' => '{sku}'],
    ['id' => 'e4', 'type' => 'script', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5],
]];
$r = $a->call('POST', '/labels/templates', $tplBody);
$tid = (int)($r['json']['id'] ?? 0);
check('a designed template saves', $r['status'] === 200 && $tid > 0 && (int)DB::val('SELECT COUNT(*) FROM label_templates WHERE tenant_id = ?', [$tA]) === 1);
$t = $a->call('GET', "/labels/templates/$tid/edit")['json']['data']['template'];
check('unknown element kinds are dropped; a bad font and a bad colour fall back; positions and sizes are kept', count($t['elements']) === 3 && $t['elements'][0]['font'] === 'Arial' && $t['elements'][0]['color'] === '#000000' && $t['elements'][0]['bold'] === true && $t['elements'][1]['sym'] === 'ean13' && (float)$t['w'] === 60.0);
check('the same name twice is refused', $a->call('POST', '/labels/templates', $tplBody)['status'] === 422);
check('a label that is too small, or a name that is empty, is refused', $a->call('POST', '/labels/templates', ['name' => 'Tiny', 'w' => 5, 'h' => 5] + $tplBody)['status'] === 422 && $a->call('POST', '/labels/templates', ['name' => ''] + $tplBody)['status'] === 422);
$r = $a->call('POST', '/labels/templates', ['name' => 'Too wide', 'page' => ['mode' => 'sheet', 'paper' => 'A4', 'cols' => 4, 'rows' => 8]] + $tplBody);
check('a sheet layout that does not fit the paper is refused, with the reason', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'do not fit'));
$r = $a->call('POST', '/labels/templates', ['name' => 'Too many', 'elements' => array_fill(0, 41, ['type' => 'text', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'text' => 'x'])] + $tplBody);
check('too many elements is refused', $r['status'] === 422);
$tplBody['name'] = 'Api label (edited)'; $tplBody['w'] = 62;
$r = $a->call('POST', "/labels/templates/$tid", $tplBody);
check('a template can be edited', $r['status'] === 200 && (string)DB::val('SELECT name FROM label_templates WHERE id = ?', [$tid]) === 'Api label (edited)' && (float)DB::val('SELECT width_mm FROM label_templates WHERE id = ?', [$tid]) === 62.0);
$a->call('POST', "/labels/templates/$tid/default");
$dflt = $a->call('GET', '/labels')['json']['data'];
check('a template can be made the default for its kind', $dflt['default'] === (string)$tid && (int)DB::val('SELECT is_default FROM label_templates WHERE id = ?', [$tid]) === 1 && $a->call('GET', '/labels?type=rolls')['json']['data']['default'] !== (string)$tid);
$b2 = $a->call('GET', '/labels/templates/create?copy=b:roll-100x50')['json']['data']['template'];
check('a ready-made template opens as a copy to customise (no id, own name, same elements)', $b2['id'] === null && str_starts_with($b2['name'], 'Copy of') && count($b2['elements']) >= 6 && $a->call('GET', '/labels/templates/b:item-50x25/edit')['status'] === 200);
check('ready-made templates cannot be changed or deleted', $a->call('POST', '/labels/templates/b:item-50x25', $tplBody)['status'] === 404 && $a->call('POST', '/labels/templates/b:item-50x25/delete')['status'] === 404);
$h = new Client($base); $h->post('/login', ['email' => $emA, 'password' => 'Password123'], '/login');
$pr = $h->get("/labels/print?type=items&template=$tid&n[{$vOf('APR')}]=2");
check('the printout uses the saved template: its size, text and a barcode that is the right kind', $pr['status'] === 200 && substr_count($pr['body'], 'class="lbl') === 2 && str_contains($pr['body'], 'size: 62mm 30mm') && str_contains($pr['body'], 'Api Reorder') && str_contains($pr['body'], 'aria-label="QR code"'));
check('a template of another company cannot be opened or printed', (function () use ($tid, $base, $emB) { $x = new J($base); $x->session(); $x->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $x->session(); return $x->call('GET', "/labels/templates/$tid/edit")['status'] === 404 && $x->call('POST', "/labels/templates/$tid", ['name' => 'x', 'w' => 50, 'h' => 25])['status'] === 404; })());
$a->call('POST', '/roles', ['name' => 'Label viewer ' . $sfx, 'perms' => ['labels.view', 'stock.view']]);
$rLv = (int)DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = ?', [$tA, 'Label viewer ' . $sfx]);
$a->call('POST', '/users', ['name' => 'Lv', 'email' => "lv-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rLv, 'warehouse_id' => '0']);
$lv = new J($base); $lv->session(); $lv->call('POST', '/login', ['email' => "lv-$sfx@test.local", 'password' => 'Password123']); $lv->session();
check('someone who may only print labels can open and print, but cannot design, edit or delete', $lv->call('GET', '/labels')['status'] === 200 && $lv->call('GET', "/labels/templates/$tid/edit")['status'] === 200
    && $lv->call('GET', '/labels/templates/create')['status'] === 403 && $lv->call('POST', '/labels/templates', $tplBody)['status'] === 403 && $lv->call('POST', "/labels/templates/$tid", $tplBody)['status'] === 403 && $lv->call('POST', "/labels/templates/$tid/delete")['status'] === 403);
check('the role form offers the new "Barcode labels" permission', isset($a->call('GET', '/roles/create')['json']['data']['modules']['labels']));
$a->call('POST', "/labels/templates/$tid/delete");
check('a template can be deleted', !DB::val('SELECT 1 FROM label_templates WHERE id = ?', [$tid]));

echo "Settings, users, roles, masters and profile through the API\n";
$r = $a->call('GET', '/settings/company');
check('the company tab data carries the saved values', $r['status'] === 200 && isset($r['json']['data']['values']['legal_name']) && ($r['json']['data']['values']['name'] ?? '') !== '');
$r = $a->call('POST', '/settings/company', ['name' => 'Apia Home Furnishing', 'legal_name' => 'Apia HF Pvt', 'address' => '12 Mill Road', 'phone' => '999', 'email' => 'bad-email', 'website' => '', 'tax_no' => '', 'bank' => '']);
check('an invalid company email is refused', $r['status'] === 422);
$r = $a->call('POST', '/settings/company', ['name' => 'Apia Home Furnishing', 'legal_name' => 'Apia HF Pvt', 'address' => '12 Mill Road', 'phone' => '999', 'email' => 'hello@apia.test', 'website' => '', 'tax_no' => 'GST1', 'bank' => '']);
check('the company profile saves', $r['status'] === 200 && (string)DB::val('SELECT name FROM tenants WHERE id = ?', [$tA]) === 'Apia Home Furnishing' && ($a->call('GET', '/settings/company')['json']['data']['values']['tax_no'] ?? '') === 'GST1');
$png = tempnam(sys_get_temp_dir(), 'lg') . '.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$r = $a->form('/settings/company', ['name' => 'Apia Home Furnishing', 'legal_name' => '', 'address' => '', 'phone' => '', 'email' => '', 'website' => '', 'tax_no' => 'GST1', 'bank' => '', 'logo' => new CURLFile($png, 'image/png', 'logo.png')]);
check('a logo uploaded as a multipart form is stored', $r['status'] === 200 && str_contains((string)($a->call('GET', '/settings/company')['json']['data']['values']['logo'] ?? ''), 'company/logo'));
$a->form('/settings/company', ['name' => 'Apia Home Furnishing', 'tax_no' => 'GST1', 'remove_logo' => '1']);
check('and it can be removed again', ($a->call('GET', '/settings/company')['json']['data']['values']['logo'] ?? null) === null);
$r = $a->call('POST', '/settings/preferences', ['symbol' => '₹', 'position' => 'before', 'decimals' => '9', 'grouping' => 'indian', 'date_format' => 'Y-m-d']);
check('an impossible number of decimals is refused', $r['status'] === 422);
$a->call('POST', '/settings/preferences', ['symbol' => 'Rs', 'position' => 'after', 'decimals' => '1', 'grouping' => 'indian', 'date_format' => 'd/m/Y']);
check('currency and dates save and the session carries the new formats', ($a->session()['json']['fmt']['symbol'] ?? '') === 'Rs' && ($a->session()['json']['fmt']['position'] ?? '') === 'after' && (string)($a->session()['json']['fmt']['decimals'] ?? '') === '1');
$a->call('POST', '/settings/preferences', ['symbol' => '', 'position' => 'before', 'decimals' => '2', 'grouping' => 'intl', 'date_format' => 'Y-m-d']);
$r = $a->call('POST', '/settings/numbering', ['num' => ['SO' => ['prefix' => 'bad prefix!', 'pad' => '4']]]);
check('a bad document prefix is refused', $r['status'] === 422);
$docs = $a->call('GET', '/settings/numbering')['json']['data']['docs'];
$num = []; foreach ($docs as $k => $x) $num[$k] = ['prefix' => $x['cfg']['prefix'], 'pad' => $x['cfg']['pad'], 'year' => $x['cfg']['year'] ? 1 : ''];
$num['SO'] = ['prefix' => 'ORD', 'pad' => '5', 'year' => ''];
$a->call('POST', '/settings/numbering', ['num' => $num]);
check('document numbering saves and shows how the next number looks', ($a->call('GET', '/settings/numbering')['json']['data']['docs']['SO']['sample'] ?? '') === sprintf('ORD-%05d', $docs['SO']['next']));
$num['SO'] = ['prefix' => 'SO', 'pad' => '4', 'year' => '']; $a->call('POST', '/settings/numbering', ['num' => $num]);
$r = $a->call('POST', '/settings/workflow', ['po_approval' => '', 'negative_stock' => '', 'require_rack' => '1', 'shade_rule' => 'block', 'max_discount' => '150']);
check('a discount limit above 100 is refused', $r['status'] === 422);
$a->call('POST', '/settings/workflow', ['po_approval' => '', 'negative_stock' => '', 'require_rack' => '', 'shade_rule' => 'block', 'max_discount' => '25']);
check('workflow rules save and come back', ($a->call('GET', '/settings/workflow')['json']['data']['values']['shade_rule'] ?? '') === 'block' && (float)$a->call('GET', '/settings/workflow')['json']['data']['values']['max_discount'] === 25.0);
$a->call('POST', '/settings/workflow', ['po_approval' => '', 'negative_stock' => '', 'require_rack' => '', 'shade_rule' => 'warn', 'max_discount' => '0']);
$a->call('POST', '/settings/labels', ['label' => ['supplier' => ['Vendor', 'Vendors']]]);
check('own words for screens save and the session uses them', ($a->session()['json']['terms']['supplier'][0] ?? '') === 'Vendor' && ($a->session()['json']['terms']['customer'][0] ?? '') === 'Customer');
$a->call('POST', '/settings/labels', ['label' => []]);
check('empty words go back to the standard ones', ($a->session()['json']['terms']['supplier'][0] ?? '') === 'Supplier');
$r = $a->call('POST', '/settings/appearance', ['brand' => 'red', 'sidebar' => 'dark', 'density' => 'compact', 'mode' => 'light']);
check('a made-up brand colour is refused', $r['status'] === 422);
$a->call('POST', '/settings/appearance', ['brand' => '#0d9488', 'sidebar' => 'light', 'density' => 'compact', 'mode' => 'dark']);
check('appearance saves and the session carries the theme', ($a->session()['json']['theme']['brand'] ?? '') === '#0d9488' && ($a->session()['json']['theme']['density'] ?? '') === 'compact');
$a->call('POST', '/settings/appearance', ['brand' => '#4f46e5', 'sidebar' => 'dark', 'density' => 'comfortable', 'mode' => 'light']);
$mods = $a->call('GET', '/settings/modules')['json']['data'];
check('the modules tab lists the switchable parts', isset($mods['modules']['requisitions']) && !isset($mods['modules']['pos']) && is_array($mods['off']));
$menuPaths = fn() => array_column(array_merge(...array_column(array_values($a->session()['json']['menu']['groups'] ?? []), 'items')), 'path');
$posOn = in_array('/purchase/requisitions', $menuPaths(), true);
$a->call('POST', '/settings/modules', ['on' => array_values(array_diff(array_keys($mods['modules']), ['requisitions']))]);
check('switching a part off hides its menu entry', $posOn && !in_array('/purchase/requisitions', $menuPaths(), true));
$a->call('POST', '/settings/modules', ['on' => array_keys($mods['modules'])]);
check('and everything switches back on', ($a->call('GET', '/settings/modules')['json']['data']['off'] ?? ['x']) === []);
$r = $a->call('GET', '/settings/templates?doc=delivery_note');
check('the print template tab lists the fields of the chosen document', ($r['json']['data']['doc'] ?? '') === 'delivery_note' && isset($r['json']['data']['fields']['accent']) && isset($r['json']['data']['docs']['invoice']) && !isset($r['json']['data']['docs']['receipt']));
$r = $a->call('POST', '/settings/templates', ['doc' => 'invoice', 'title' => 'TAX INVOICE', 'show_logo' => 1, 'show_company' => 1, 'accent' => 'blue']);
check('a bad accent colour is refused', $r['status'] === 422);
$a->call('POST', '/settings/templates', ['doc' => 'invoice', 'title' => 'TAX INVOICE', 'show_logo' => 1, 'show_company' => 1, 'accent' => '#112233']);
check('a print template saves', ($a->call('GET', '/settings/templates?doc=invoice')['json']['data']['values']['title'] ?? '') === 'TAX INVOICE');
check('an unknown settings tab is a 404 JSON', $a->call('GET', '/settings/nothing')['status'] === 404);

$r = $a->call('GET', '/masters/categories');
check('the master list comes with its definition and the other tabs', isset($r['json']['data']['def']['fields']) && isset($r['json']['data']['tabs']['units']));
$a->call('POST', '/masters/categories', ['name' => 'Curtains']);
$catId = (int)DB::val("SELECT id FROM categories WHERE tenant_id = ? AND name = 'Curtains'", [$tA]);
check('a category is added', $catId > 0);
check('a duplicate name is refused', $a->call('POST', '/masters/categories', ['name' => 'Curtains'])['status'] === 422);
$a->call('POST', '/masters/units', ['name' => 'Dozen', 'short_name' => 'doz', 'allow_decimal' => '0']);
check('a unit saves with its decimals switch', (int)DB::val("SELECT allow_decimal FROM units WHERE tenant_id = ? AND name = 'Dozen'", [$tA]) === 0);
$a->call('POST', '/masters/units/' . (int)DB::val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Dozen'", [$tA]), ['name' => 'Dozen', 'short_name' => 'doz', 'allow_decimal' => '1']);
check('and it can be changed', (int)DB::val("SELECT allow_decimal FROM units WHERE tenant_id = ? AND name = 'Dozen'", [$tA]) === 1);
$r = $a->call('POST', "/masters/units/$unit/delete");
check('a unit in use cannot be deleted (reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM units WHERE id = ?', [$unit]));
$a->call('POST', "/masters/categories/$catId/delete");
check('an unused category can be deleted', !DB::val('SELECT 1 FROM categories WHERE id = ?', [$catId]));
check('an unknown master type is a 404 JSON', $a->call('GET', '/masters/nothing')['status'] === 404);

$r = $a->call('GET', '/roles/create');
check('the role form lists modules with their possible actions', isset($r['json']['data']['modules']['items']['actions']) && $r['json']['data']['granted'] === []);
$a->call('POST', '/roles', ['name' => 'Counter staff', 'perms' => ['items.view', 'sales.view', 'sales.create']]);
$roleId = (int)DB::val("SELECT id FROM roles WHERE tenant_id = ? AND name = 'Counter staff'", [$tA]);
check('a role is created with its permissions', $roleId > 0 && (int)DB::val('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?', [$roleId]) === 3);
check('its permissions come back on the edit form', count($a->call('GET', "/roles/$roleId/edit")['json']['data']['granted']) === 3);
check('a duplicate role name is refused', $a->call('POST', '/roles', ['name' => 'Counter staff', 'perms' => []])['status'] === 422);
$r = $a->call('POST', '/users', ['name' => 'Sita', 'email' => 'sita-' . $sfx . '@test.local', 'password' => 'short', 'role_id' => $roleId, 'warehouse_id' => '0']);
check('a short password for a new user is refused', $r['status'] === 422);
$a->call('POST', '/users', ['name' => 'Sita', 'email' => 'sita-' . $sfx . '@test.local', 'password' => 'Password123', 'role_id' => $roleId, 'warehouse_id' => '0']);
$uid = (int)DB::val("SELECT id FROM users WHERE tenant_id = ? AND email = ?", [$tA, 'sita-' . $sfx . '@test.local']);
check('a user is added on that role', $uid > 0 && (int)DB::val('SELECT role_id FROM users WHERE id = ?', [$uid]) === $roleId);
check('a duplicate email is refused', $a->call('POST', '/users', ['name' => 'Sita 2', 'email' => 'sita-' . $sfx . '@test.local', 'password' => 'Password123', 'role_id' => $roleId, 'warehouse_id' => '0'])['status'] === 422);
$r = $a->call('GET', "/users/$uid/edit");
check('the user form data has the user and the roles they may be given', ($r['json']['data']['user']['name'] ?? '') === 'Sita' && count($r['json']['data']['roles']) >= 2 && !str_contains($r['raw'], 'password_hash'));
check('the user list shows role names', in_array('Counter staff', array_column($a->call('GET', '/users')['json']['data']['users'], 'role_name'), true));
$a->call('POST', "/users/$uid/toggle");
check('a user can be disabled', (int)DB::val('SELECT is_active FROM users WHERE id = ?', [$uid]) === 0);
$myId = (int)DB::val('SELECT id FROM users WHERE tenant_id = ? ORDER BY id LIMIT 1', [$tA]);
check('you cannot disable or delete yourself', $a->call('POST', "/users/$myId/toggle")['status'] === 422 && $a->call('POST', "/users/$myId/delete")['status'] === 422);
$r = $a->call('POST', "/roles/$roleId/delete");
check('a role with users cannot be deleted (reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM roles WHERE id = ?', [$roleId]));
$a->call('POST', "/users/$uid/delete");
$a->call('POST', "/roles/$roleId/delete");
check('after the user is gone the role can be deleted', !DB::val('SELECT 1 FROM users WHERE id = ?', [$uid]) && !DB::val('SELECT 1 FROM roles WHERE id = ?', [$roleId]));

$r = $a->call('GET', '/profile');
check('the profile data has name and email but never the password hash', ($r['json']['data']['me']['email'] ?? '') !== '' && !str_contains($r['raw'], 'password_hash'));
check('a wrong current password is refused', $a->call('POST', '/profile/password', ['current_password' => 'nope', 'password' => 'NewPassword1', 'password_confirm' => 'NewPassword1'])['status'] === 422);
check('a mismatched confirmation is refused', $a->call('POST', '/profile/password', ['current_password' => 'Password123', 'password' => 'NewPassword1', 'password_confirm' => 'Different1'])['status'] === 422);
$a->call('POST', '/profile', ['name' => 'Apia Owner', 'email' => (string)DB::val('SELECT email FROM users WHERE id = ?', [$myId])]);
check('the profile name saves', (string)DB::val('SELECT name FROM users WHERE id = ?', [$myId]) === 'Apia Owner');

echo "Super admin through the API\n";
$sx = new J($base); $r = $sx->call('GET', '/admin/session'); $sx->csrf = (string)$r['json']['csrf'];
check('signed out, the admin session says so (and hands out a CSRF token)', $r['status'] === 200 && $r['json']['authenticated'] === false && $sx->csrf !== '');
check('admin pages answer 401 JSON to someone not signed in as admin', $sx->call('GET', '/admin/tenants')['status'] === 401 && $a->call('GET', '/admin/tenants')['status'] === 401);
$r = $sx->call('POST', '/admin/login', ['email' => $sa['email'], 'password' => 'wrong-password']);
check('a wrong admin password is refused', $r['status'] === 422 && ($r['json']['ok'] ?? true) === false);
$r = $sx->call('POST', '/admin/login', ['email' => $sa['email'], 'password' => $sa['password']]);
$r2 = $sx->call('GET', '/admin/session');
check('the Super Admin signs in and the session shows who and how many updates wait', $r['status'] === 200 && $r2['json']['authenticated'] === true && ($r2['json']['admin']['name'] ?? '') !== '' && isset($r2['json']['pending']));
$r = $sx->call('GET', '/admin');
check('the dashboard has company counts, users and the newest companies', isset($r['json']['data']['counts']['total']) && isset($r['json']['data']['users']) && is_array($r['json']['data']['recent']));
$r = $sx->call('GET', '/admin/plans');
$planRows = $r['json']['data']['plans'] ?? [];
check('the plan list has limits and how many companies use each', count($planRows) >= 1 && isset($planRows[0]['tenant_count']) && isset($planRows[0]['max_users']));
$r = $sx->call('POST', '/admin/plans', ['name' => 'API Plan ' . $sfx, 'max_users' => '3', 'max_items' => '100', 'max_warehouses' => '2', 'price' => '499', 'trial_days' => '14', 'is_active' => 1]);
$planId = (int)DB::val("SELECT id FROM plans WHERE name = ?", ['API Plan ' . $sfx]);
check('a plan is created', $r['status'] === 200 && $planId > 0 && (int)DB::val('SELECT max_users FROM plans WHERE id = ?', [$planId]) === 3);
check('a plan without a name is refused', $sx->call('POST', '/admin/plans', ['name' => '', 'max_users' => '1', 'max_items' => '1', 'max_warehouses' => '1', 'price' => '1', 'trial_days' => '1'])['status'] === 422);
$sx->call('POST', "/admin/plans/$planId", ['name' => 'API Plan ' . $sfx, 'max_users' => '5', 'max_items' => '100', 'max_warehouses' => '2', 'price' => '599', 'trial_days' => '14', 'is_active' => '']);
check('a plan can be changed and switched off', (int)DB::val('SELECT max_users FROM plans WHERE id = ?', [$planId]) === 5 && (int)DB::val('SELECT is_active FROM plans WHERE id = ?', [$planId]) === 0);
check('the plan edit form data comes back', ($sx->call('GET', "/admin/plans/$planId/edit")['json']['data']['plan']['name'] ?? '') === 'API Plan ' . $sfx);
$sx->call('POST', "/admin/plans/$planId", ['name' => 'API Plan ' . $sfx, 'max_users' => '5', 'max_items' => '100', 'max_warehouses' => '2', 'price' => '599', 'trial_days' => '14', 'is_active' => 1]);
$ownerEmail = 'adm-' . $sfx . '@test.local';
$r = $sx->call('POST', '/admin/tenants', ['name' => 'Admin Made Co ' . $sfx, 'plan_id' => $planId, 'status' => 'trial', 'owner_name' => 'Gita', 'owner_email' => $ownerEmail, 'owner_password' => 'short']);
check('a company with a short owner password is refused', $r['status'] === 422);
$r = $sx->call('POST', '/admin/tenants', ['name' => 'Admin Made Co ' . $sfx, 'plan_id' => $planId, 'status' => 'active', 'subscription_ends_at' => date('Y-m-d', strtotime('+30 days')), 'owner_name' => 'Gita', 'owner_email' => $ownerEmail, 'owner_password' => 'Password123']);
$tNew = (int)DB::val('SELECT id FROM tenants WHERE name = ?', ['Admin Made Co ' . $sfx]);
check('a company is created with its owner, roles and a walk-in customer', $r['status'] === 200 && $tNew > 0 && (int)DB::val('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND email = ?', [$tNew, $ownerEmail]) === 1 && (int)DB::val('SELECT COUNT(*) FROM roles WHERE tenant_id = ?', [$tNew]) >= 3);
check('the same owner email cannot be used twice', $sx->call('POST', '/admin/tenants', ['name' => 'Another ' . $sfx, 'plan_id' => $planId, 'owner_name' => 'X', 'owner_email' => $ownerEmail, 'owner_password' => 'Password123'])['status'] === 422);
$r = $sx->call('GET', '/admin/tenants');
$mine = array_values(array_filter($r['json']['data']['tenants'], fn($t) => (int)$t['id'] === $tNew))[0] ?? null;
check('the company list shows plan, status and user count', $mine && $mine['plan_name'] === 'API Plan ' . $sfx && (int)$mine['user_count'] === 1 && $mine['status'] === 'active' && !str_contains($r['raw'], 'password_hash'));
$r = $sx->call('GET', "/admin/tenants/$tNew/edit");
check('the company form data has its users and the plans', ($r['json']['data']['users'][0]['email'] ?? '') === $ownerEmail && count($r['json']['data']['plans']) >= 1);
$sx->call('POST', "/admin/tenants/$tNew", ['name' => 'Admin Made Co ' . $sfx . ' Ltd', 'plan_id' => $planId, 'status' => 'trial', 'trial_ends_at' => date('Y-m-d', strtotime('+10 days')), 'subscription_ends_at' => '']);
check('a company can be edited (name, status, dates)', DB::val('SELECT name FROM tenants WHERE id = ?', [$tNew]) === 'Admin Made Co ' . $sfx . ' Ltd' && DB::val('SELECT status FROM tenants WHERE id = ?', [$tNew]) === 'trial');
$sx->call('POST', "/admin/tenants/$tNew/status");
check('suspend switches a company off', DB::val('SELECT status FROM tenants WHERE id = ?', [$tNew]) === 'suspended');
$r = $sx->call('POST', "/admin/tenants/$tNew/impersonate");
check('a suspended company cannot be opened as its owner', $r['status'] === 422 && !($sx->call('GET', '/session')['json']['authenticated'] ?? false));
$sx->call('POST', "/admin/tenants/$tNew/status");
check('activate switches it back on', DB::val('SELECT status FROM tenants WHERE id = ?', [$tNew]) === 'active');
$r = $sx->call('POST', "/admin/tenants/$tNew/impersonate");
$ses = $sx->call('GET', '/session');
check('"Open as owner" signs the admin into that company in support mode', $r['status'] === 200 && ($ses['json']['authenticated'] ?? false) === true && ($ses['json']['supportMode'] ?? false) === true && ($ses['json']['tenant']['name'] ?? '') === 'Admin Made Co ' . $sfx . ' Ltd');
check('and the support session can use the company app', $sx->call('GET', '/customers')['status'] === 200);
$sx->call('POST', '/impersonate/stop');
check('stopping support mode returns to the admin area', !($sx->call('GET', '/session')['json']['authenticated'] ?? false) && ($sx->call('GET', '/admin/session')['json']['authenticated'] ?? false) === true);
$r = $sx->call('GET', '/admin/system');
check('the system page lists pending and applied updates and the environment', is_array($r['json']['data']['pending']) && count($r['json']['data']['applied']) >= 10 && isset($r['json']['data']['info']['PHP']));
$r = $sx->call('POST', '/admin/system/migrate');
check('running updates when none are waiting says so', $r['status'] === 200 && str_contains(json_encode($r['json']['messages']), 'up to date'));
$bk = $sx->call('POST', '/admin/system/backup', []);
check('the backup is a plain .sql download with every table', $bk['status'] === 200 && str_contains($bk['raw'], 'CREATE TABLE') && $bk['json'] === null);
$r = $sx->call('GET', '/admin/audit');
$acts = array_column($r['json']['data']['logs'], 'action');
check('the audit log shows what happened (company created, support mode)', in_array('tenant_create', $acts, true) && in_array('impersonate_start', $acts, true) && in_array('db_backup', $acts, true));
$sx->call('POST', '/admin/logout');
check('admin sign-out ends the admin session', ($sx->call('GET', '/admin/session')['json']['authenticated'] ?? true) === false && $sx->call('GET', '/admin/plans')['status'] === 401);
// forgotten Super Admin password (a throw-away admin, so the real one is untouched)
$fe = "fa-$sfx@test.local";
DB::insert('super_admins', ['name' => 'Forgetful', 'email' => $fe, 'password_hash' => password_hash('OldPassword1', PASSWORD_DEFAULT)]);
$fx = new J($base); $r = $fx->call('GET', '/admin/session'); $fx->csrf = (string)$r['json']['csrf'];
@unlink(ROOT . '/storage/logs/mail.log');
$unknown = $fx->call('POST', '/admin/forgot-password', ['email' => "nobody-$sfx@test.local"]);
$known = $fx->call('POST', '/admin/forgot-password', ['email' => $fe]);
$mailLog = (string)@file_get_contents(ROOT . '/storage/logs/mail.log');
check('"Forgot password" gives the same answer for any address, and mails a link only to a real Super Admin', $unknown['status'] === 200 && $known['status'] === 200 && ($unknown['json']['messages'] ?? null) === ($known['json']['messages'] ?? 0)
    && !str_contains($mailLog, "nobody-$sfx") && preg_match('~admin/reset-password/([a-f0-9]{64})~', $mailLog, $mt) === 1);
$tok = $mt[1] ?? 'x';
check('a company-user reset page does not accept an admin token (and the reverse)', (new Client($base))->get("/reset-password/$tok")['status'] !== 200 || str_contains((new Client($base))->get("/reset-password/$tok")['body'], 'invalid'));
check('a wrong confirmation or a short password is refused and changes nothing', $fx->call('POST', "/admin/reset-password/$tok", ['password' => 'NewPassword1', 'password_confirm' => 'Different1'])['status'] === 422
    && $fx->call('POST', "/admin/reset-password/$tok", ['password' => 'short', 'password_confirm' => 'short'])['status'] === 422 && password_verify('OldPassword1', (string)DB::val('SELECT password_hash FROM super_admins WHERE email = ?', [$fe])));
$r = $fx->call('POST', "/admin/reset-password/$tok", ['password' => 'NewPassword1', 'password_confirm' => 'NewPassword1']);
check('the link sets the new password', $r['status'] === 200 && password_verify('NewPassword1', (string)DB::val('SELECT password_hash FROM super_admins WHERE email = ?', [$fe])));
check('the link works once only', $fx->call('POST', "/admin/reset-password/$tok", ['password' => 'Another123', 'password_confirm' => 'Another123'])['status'] === 422 && password_verify('NewPassword1', (string)DB::val('SELECT password_hash FROM super_admins WHERE email = ?', [$fe])));
check('and the new password signs in (the old one does not)', $fx->call('POST', '/admin/login', ['email' => $fe, 'password' => 'OldPassword1'])['status'] === 422 && $fx->call('POST', '/admin/login', ['email' => $fe, 'password' => 'NewPassword1'])['status'] === 200);
DB::run('DELETE FROM super_admins WHERE email = ?', [$fe]); DB::run('DELETE FROM password_resets WHERE email = ?', ['admin:' . $fe]);

$ownerClient = new J($base); $ownerClient->session();
$r = $ownerClient->call('POST', '/login', ['email' => $ownerEmail, 'password' => 'Password123']);
check('the new company\'s owner can sign in', $r['status'] === 200 && ($ownerClient->session()['json']['authenticated'] ?? false) === true);

echo "Isolation and secrets\n";
$b = new J($base); $b->session(); $b->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $b->session();
check('another company gets a 404 for it, on the page data and the record data', $b->call('GET', "/customers/$cid")['status'] === 404 && $b->call('GET', "/record/customer/$cid")['status'] === 404);
check('and cannot change it', $b->call('POST', "/customers/$cid", ['name' => 'Hacked'])['status'] === 404 && (string)DB::val('SELECT name FROM customers WHERE id = ?', [$cid]) === 'Rani Textiles');
$r = $a->call('GET', '/users');
check('user lists never include password hashes', $r['status'] === 200 && !str_contains($r['raw'], 'password_hash') && !str_contains($r['raw'], '$2y$'));
$a->call('POST', '/logout', []);
check('signing out ends the session', $a->call('GET', '/customers')['status'] === 401);
check('a plain page request still works and is still HTML (nothing changed for the old UI)', (new Client($base))->get('/login')['status'] === 200 && str_contains((new Client($base))->get('/login')['body'], '<form'));

echo "Screens that used to be classic-only\n";
$c = new J($base); $c->session(); $c->call('POST', '/login', ['email' => $emA, 'password' => 'Password123']); $c->session();
// company password reset
@unlink(ROOT . '/storage/logs/mail.log');
$fx = new J($base); $fx->session();
$fx->call('POST', '/forgot-password', ['email' => $emA]);
$mailLog = (string)@file_get_contents(ROOT . '/storage/logs/mail.log');
check('company "forgot password" mails a link; the reset page answers JSON for a good token and 4xx/redirect for a bad one', preg_match('~/reset-password/([a-f0-9]{64})~', $mailLog, $mt) === 1
    && ($fx->call('GET', "/reset-password/{$mt[1]}")['json']['view'] ?? '') === 'auth/reset' && !($fx->call('GET', '/reset-password/' . str_repeat('0', 64))['json']['ok'] ?? true));
$r = $fx->call('POST', "/reset-password/{$mt[1]}", ['password' => 'Password123', 'password_confirm' => 'Different1']);
check('a mismatch is refused', $r['status'] === 422);
$r = $fx->call('POST', "/reset-password/{$mt[1]}", ['password' => 'Password123', 'password_confirm' => 'Password123']);
check('the reset goes through and the link then stops working', $r['status'] === 200 && $fx->call('POST', "/reset-password/{$mt[1]}", ['password' => 'Password123', 'password_confirm' => 'Password123'])['status'] === 422);

// customer groups
$r = $c->call('POST', '/customers/groups', ['name' => 'Dealer', 'discount_pct' => '7.5']);
$gid = (int)DB::val('SELECT id FROM customer_groups WHERE tenant_id = ? AND name = ?', [$tA, 'Dealer']);
check('a customer group is created (and a duplicate name refused)', $r['status'] === 200 && $gid > 0 && $c->call('POST', '/customers/groups', ['name' => 'Dealer', 'discount_pct' => '1'])['status'] === 422);
$g = $c->call('GET', "/customers/groups/$gid/edit");
check('the group page gives the row and its prices; the list counts it', ($g['json']['data']['row']['name'] ?? '') === 'Dealer' && is_array($g['json']['data']['prices'] ?? null)
    && in_array('Dealer', array_column($c->call('GET', '/customers/groups')['json']['data']['rows'] ?? [], 'name'), true));
$vid = (int)DB::val('SELECT v.id FROM item_variants v WHERE v.tenant_id = ? LIMIT 1', [$tA]);
if ($vid) {
    $c->call('POST', "/customers/groups/$gid/prices", ['variant_id' => $vid, 'price' => '99']);
    check('a special price is saved and removable', (float)DB::val('SELECT price FROM group_prices WHERE group_id = ? AND variant_id = ?', [$gid, $vid]) === 99.0);
    $pid = (int)DB::val('SELECT id FROM group_prices WHERE group_id = ?', [$gid]);
    $c->call('POST', "/customers/groups/$gid/prices/$pid/delete", []);
    check('...removed', (int)DB::val('SELECT COUNT(*) FROM group_prices WHERE group_id = ?', [$gid]) === 0);
}
check('another company cannot open or delete the group', ($b2 = new J($base)) && $b2->session() !== null && $b2->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']) !== null && $b2->session() !== null
    && $b2->call('GET', "/customers/groups/$gid/edit")['status'] === 404 && $b2->call('POST', "/customers/groups/$gid/delete", [])['status'] === 404);
$c->call('POST', "/customers/groups/$gid/delete", []);
check('the group is deleted', !DB::val('SELECT 1 FROM customer_groups WHERE id = ?', [$gid]));

// rate lists page data, items import page data, search
$r = $c->call('GET', '/purchase/rates');
check('the rate list page gives rows, stats and suppliers as data', $r['status'] === 200 && isset($r['json']['data']['rows'], $r['json']['data']['stats']['current'], $r['json']['data']['suppliers']));
$r = $c->call('GET', '/items/import');
check('the items import page lists the file columns', in_array('item_name', $r['json']['data']['columns'] ?? [], true));
$r = $c->call('GET', '/search?q=Apia');
check('search answers JSON groups', $r['status'] === 200 && is_array($r['json']['groups'] ?? null));
$r = $c->call('GET', '/search?q=a');
check('search needs two letters', $r['status'] === 200 && ($r['json']['groups'] ?? 1) === []);

// page layout designer: data and save over JSON
$r = $c->call('GET', '/settings/formdesign');
$m = $r['json']['data']['model'] ?? [];
check('the layout designer gives every form, the field types and the widths', $r['status'] === 200 && count($m) >= 10 && isset($r['json']['data']['types']['text'], $r['json']['data']['widths']['50']));
$cust = null; foreach ($m as $f) if ($f['key'] === 'customer') $cust = $f;
$payload = [$cust['key'] => ['style' => $cust['style'], 'lines' => $cust['lines'] ?? [], 'deleted' => [],
    'sections' => array_map(fn($sec) => ['title' => $sec['title'], 'cols' => $sec['cols'], 'fields' => array_merge($sec['fields'], ['cf:new1'])], array_slice($cust['sections'], 0, 1)),
    'fields' => ['cf:new1' => ['w' => '', 'help' => '', 'show' => true, 'req' => false, 'name' => 'Colour code', 'type' => 'text', 'options' => '', 'inList' => false, 'unique' => false, 'isNew' => true]]]];
$r = $c->call('POST', '/settings/formdesign', ['payload' => json_encode($payload), 'current' => 'customer', 'close' => '', 'return' => '']);
check('a new field added in the designer is saved (JSON answer, no redirect crash)', $r['status'] === 200 && (int)DB::val("SELECT COUNT(*) FROM custom_fields WHERE tenant_id = ? AND label = 'Colour code'", [$tA]) === 1);
check('the custom-fields screens now lead to the designer', (string)(new Client($base))->get('/settings/custom-fields')['status'] !== '500');
DB::run("DELETE FROM custom_fields WHERE tenant_id = ? AND label = 'Colour code'", [$tA]);

echo "Custom fields: saved on each form\n";
$cfv = fn(string $e, int $id) => (string)DB::val('SELECT value FROM custom_field_values WHERE tenant_id = ? AND field_id = ? AND entity_id = ?', [$tA, $CF[$e], $id]);
$maxId = fn(string $table, string $extra = '') => (int)DB::val("SELECT MAX(id) FROM $table WHERE tenant_id = ? $extra", [$tA]);
check('a goods receipt keeps its field', $cfv('grn', $grnId) === 'cf-grn');
check('a purchase return keeps its field', $cfv('purchase_return', $maxId('purchase_returns')) === 'cf-purchase_return');
check('a requisition keeps its field', $cfv('requisition', (int)DB::val('SELECT id FROM purchase_requisitions WHERE tenant_id = ? AND note = ?', [$tA, 'Need silk'])) === 'cf-requisition');
check('a warehouse keeps its field', $cfv('warehouse', (int)DB::val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Api Godown'", [$tA])) === 'cf-warehouse');
check('an adjustment keeps its field', $cfv('adjustment', (int)DB::val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'adjustment' AND note = 'API opening'", [$tA])) === 'cf-adjustment');
check('a transfer keeps its field', $cfv('transfer', (int)DB::val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND lr_no = 'LR-77'", [$tA])) === 'cf-transfer');
check('a stock-take keeps its field', $cfv('stocktake', (int)DB::val("SELECT id FROM stock_docs WHERE tenant_id = ? AND type = 'stocktake' AND note = 'API count'", [$tA])) === 'cf-stocktake');
check('a delivery keeps its field', $cfv('delivery', (int)DB::val('SELECT MIN(id) FROM deliveries WHERE tenant_id = ? AND order_id = ?', [$tA, $soId])) === 'cf-delivery');
check('a sales return keeps its field', $cfv('sales_return', $maxId('sales_returns')) === 'cf-sales_return');
$r = $c->call('GET', "/purchase/bills/$billId/edit");
check('the bill edit form carries the saved values of the company\'s fields (empty so far)', $r['status'] === 200 && array_key_exists('cfValues', $r['json']['data'] ?? []));
$r = $c->call('POST', "/purchase/bills/$billId", ['supplier_bill_no' => 'SB-1', 'bill_date' => date('Y-m-d'), 'due_date' => '', 'other_charges' => '', 'notes' => '', 'cf' => [$CF['bill'] => 'cf-bill']]);
check('a bill takes its field when it is edited', $r['status'] === 200 && $cfv('bill', $billId) === 'cf-bill' && ($c->call('GET', "/purchase/bills/$billId/edit")['json']['data']['cfValues'][$CF['bill']] ?? '') === 'cf-bill');
$r = $c->call('POST', "/sales/invoices/$inv1", ['invoice_date' => date('Y-m-d'), 'due_date' => '', 'delivery_charge' => '', 'installation_charge' => '', 'notes' => '', 'cf' => [$CF['invoice'] => 'cf-invoice']]);
check('an invoice takes its field when it is edited', $r['status'] === 200 && $cfv('invoice', $inv1) === 'cf-invoice');
$r = $c->call('POST', '/warehouses/' . (int)DB::val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Api Godown'", [$tA]), ['name' => 'Api Godown', 'code' => 'apg', 'address' => 'Plot 4', 'is_active' => 1, 'cf' => [$CF['warehouse'] => 'cf-wh-2']]);
check('a warehouse takes a changed value', $r['status'] === 200 && $cfv('warehouse', (int)DB::val("SELECT id FROM warehouses WHERE tenant_id = ? AND name = 'Api Godown'", [$tA])) === 'cf-wh-2');
$rec = json_encode($c->call('GET', "/record/grn/$grnId")['json']);
check('the receipt\'s record page shows the field and its value', str_contains($rec, 'Ref code') && str_contains($rec, 'cf-grn'));

echo "Lookup fields\n";
$bl = new J($base); $bl->session(); $bl->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $bl->session();
$r = $c->call('GET', '/lookup/find/supplier?q=Api');
check('a lookup searches records by what is typed', $r['status'] === 200 && in_array('Api Mills', array_column($r['json']['rows'] ?? [], 'label'), true));
$r = $c->call('GET', "/lookup/find/supplier?id=$sid");
check('a saved value is resolved to its name by id', ($r['json']['rows'][0]['label'] ?? '') === 'Api Mills');
check('an unknown kind of record is a 404 and another company\'s record is never found', $c->call('GET', '/lookup/find/passwords')['status'] === 404 && ($bl->call('GET', "/lookup/find/supplier?id=$sid")['json']['rows'] ?? []) === []);
$m2 = $c->call('GET', '/settings/formdesign')['json']['data'] ?? [];
check('the designer offers the Lookup type and the kinds of record it can point to', isset($m2['types']['lookup']) && isset($m2['lookups']['item'], $m2['lookups']['supplier'], $m2['lookups']['rack']));
$cust2 = null; foreach ($m2['model'] ?? [] as $f) if ($f['key'] === 'customer') $cust2 = $f;
$payload = [$cust2['key'] => ['style' => $cust2['style'], 'lines' => $cust2['lines'] ?? [], 'deleted' => [],
    'sections' => array_map(fn($sec) => ['title' => $sec['title'], 'cols' => $sec['cols'], 'fields' => array_merge($sec['fields'], ['cf:newL'])], array_slice($cust2['sections'], 0, 1)),
    'fields' => ['cf:newL' => ['w' => '', 'help' => '', 'show' => true, 'req' => false, 'name' => 'Reference supplier', 'type' => 'lookup', 'options' => 'supplier', 'inList' => true, 'unique' => false, 'isNew' => true]]]];
$c->call('POST', '/settings/formdesign', ['payload' => json_encode($payload), 'current' => 'customer', 'close' => '', 'return' => '']);
$lf = DB::one("SELECT id, type, options FROM custom_fields WHERE tenant_id = ? AND label = 'Reference supplier'", [$tA]);
check('a Lookup field is created with its kind of record', $lf && $lf['type'] === 'lookup' && $lf['options'] === 'supplier');
$sch = $c->call('GET', '/schema/customer')['json']['schema']['fields'] ?? [];
check('the customer form carries it as a lookup with its source', ($sch['cf:' . $lf['id']]['type'] ?? '') === 'lookup' && ($sch['cf:' . $lf['id']]['source'] ?? '') === 'supplier');
$r = $c->call('POST', '/customers', ['name' => 'Lookup Buyer', 'cf' => [$lf['id'] => (string)$sid]]);
$cL = (int)DB::val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Lookup Buyer'", [$tA]);
check('a customer saves with the record it points to', $r['status'] === 200 && $cL > 0 && DB::val('SELECT value FROM custom_field_values WHERE entity_id = ? AND field_id = ?', [$cL, $lf['id']]) === (string)$sid);
$r = $c->call('POST', '/customers', ['name' => 'Lookup Bad', 'cf' => [$lf['id'] => '99999999']]);
check('a record that does not exist is refused', $r['status'] === 422 && (int)DB::val("SELECT COUNT(*) FROM customers WHERE tenant_id = ? AND name = 'Lookup Bad'", [$tA]) === 0);
$sidB = (int)DB::val('SELECT id FROM suppliers WHERE tenant_id <> ? LIMIT 1', [$tA]);
if ($sidB) { $r = $c->call('POST', '/customers', ['name' => 'Lookup Bad', 'cf' => [$lf['id'] => (string)$sidB]]); check('another company\'s record cannot be chosen', $r['status'] === 422); }
$rec = json_encode($c->call('GET', "/record/customer/$cL")['json']);
check('the record page shows the name and links to it', str_contains($rec, 'Api Mills') && str_contains($rec, 'suppliers\\/' . $sid));
$lst = $c->call('GET', '/customers?q=Lookup')['json']['data'] ?? [];
check('the customers list shows the name, not the number', ($lst['rows'][0]['cf'][$lf['id']] ?? '') === 'Api Mills');
$sch2 = $c->call('GET', '/schema/sales_order')['json']['schema']['fields'] ?? [];
$sch3 = $c->call('GET', '/schema/supplier_item')['json']['schema']['fields'] ?? [];
check('the supplier / customer pickers on the standard forms are searchable lookups from the same registry', ($sch2['customer_id']['type'] ?? '') === 'lookup' && ($sch2['customer_id']['source'] ?? '') === 'customer' && ($sch3['supplier_id']['source'] ?? '') === 'supplier' && ($sch3['variant_id']['source'] ?? '') === 'item' && ($sch2['warehouse_id']['type'] ?? '') === 'select');
DB::run('DELETE FROM custom_field_values WHERE field_id = ?', [$lf['id']]); DB::run('DELETE FROM custom_fields WHERE id = ?', [$lf['id']]);
DB::run("DELETE FROM customers WHERE tenant_id = ? AND name = 'Lookup Buyer'", [$tA]);

echo "Items-table columns (line grids)\n";
$g = $c->call('GET', '/schema/purchase_order')['json']['schema']['grid'] ?? [];
check('a purchase order\'s items table is data: item, qty, unit price, tax, line total, in that order, all shown', array_column($g, 'key') === ['item', 'qty', 'price', 'tax', 'total'] && !in_array(false, array_column($g, 'show'), true) && $g[0]['locked'] === true && $g[2]['locked'] === false);
foreach (['sales_order' => ['item', 'qty', 'price', 'disc', 'tax', 'total'], 'grn' => ['item', 'billed', 'received', 'rack', 'price', 'tax'], 'adjustment' => ['item', 'batch', 'rack', 'qty', 'cost'], 'transfer' => ['item', 'batch', 'from_rack', 'to_rack', 'qty']] as $ent => $keys) {
    check("the $ent items table has its columns as data", array_column($c->call('GET', "/schema/$ent")['json']['schema']['grid'] ?? [], 'key') === $keys);
}
check('a form without an items table has none', ($c->call('GET', '/schema/customer')['json']['schema']['grid'] ?? []) === []);
$mm = $c->call('GET', '/settings/formdesign')['json']['data']['model'] ?? [];
$po = null; foreach ($mm as $f) if ($f['key'] === 'purchase_order') $po = $f;
check('the designer model says which forms have an items table and what the standard order is', $po && $po['gridForm'] === true && $po['gridDefault'] === ['item', 'qty', 'price', 'tax', 'total'] && count($po['grid']) === 5);
$design = fn(array $grid) => $c->call('POST', '/settings/formdesign', ['payload' => json_encode(['purchase_order' => ['style' => $po['style'], 'lines' => $po['lines'], 'deleted' => [], 'grid' => $grid,
    'sections' => array_map(fn($sec) => ['title' => $sec['title'], 'cols' => $sec['cols'], 'fields' => $sec['fields']], $po['sections']), 'fields' => (object)[]]]), 'current' => 'purchase_order', 'close' => '', 'return' => '']);
$design([['key' => 'item', 'show' => false, 'label' => 'Material'], ['key' => 'price', 'show' => true, 'label' => 'Rate'], ['key' => 'qty', 'show' => true, 'label' => ''], ['key' => 'tax', 'show' => false, 'label' => ''], ['key' => 'total', 'show' => true, 'label' => ''], ['key' => 'bogus', 'show' => true, 'label' => 'x']]);
$g = $c->call('GET', '/schema/purchase_order')['json']['schema'];
$byK = array_column($g['grid'], null, 'key');
check('the company\'s order, own names and hidden columns are kept; a column that cannot be hidden stays; an unknown one is dropped', array_column($g['grid'], 'key') === ['item', 'price', 'qty', 'tax', 'total'] && $byK['item']['show'] === true && $byK['item']['label'] === 'Material' && $byK['price']['label'] === 'Rate' && $byK['tax']['show'] === false && $byK['qty']['label'] === 'Qty');
check('hiding the tax column also switches off the older tax switch (the classic pages read it)', $g['lines']['tax'] === false);
$design(array_map(fn($x) => ['key' => $x['key'], 'show' => true, 'label' => str_repeat('é', 60)], $po['grid']));
$byK = array_column($c->call('GET', '/schema/purchase_order')['json']['schema']['grid'], null, 'key');
check('a column name is cut to 40 characters', mb_strlen($byK['item']['label']) === 40);
$design(array_map(fn($x) => ['key' => $x['key'], 'show' => true, 'label' => $x['def']], $po['grid']));
$g = $c->call('GET', '/schema/purchase_order')['json']['schema'];
check('naming a column back to its standard name stores no own name, and the tax switch is on again', $g['lines']['tax'] === true && array_column($g['grid'], 'label') === array_column($po['grid'], 'def'));
$bl2 = $bl->call('GET', '/schema/purchase_order')['json']['schema']['grid'] ?? [];
check('another company is not affected by these choices', array_column($bl2, 'key') === ['item', 'qty', 'price', 'tax', 'total'] && array_column($bl2, 'label') !== ['Material', 'Rate']);
foreach (['purchase_order' => ['item', 'their', 'ordered', 'received', 'due', 'price', 'tax', 'total'], 'grn' => ['item', 'their', 'po', 'batch', 'rack', 'qty', 'returned', 'price', 'cost'], 'bill' => ['item', 'their', 'qty', 'price', 'tax', 'amount'],
    'purchase_return' => ['item', 'batch', 'rack', 'qty', 'price', 'value'], 'requisition' => ['item', 'qty', 'note'], 'sales_order' => ['item', 'ordered', 'delivered', 'price', 'disc', 'tax', 'total'],
    'delivery' => ['item', 'batch', 'rack', 'qty', 'price', 'total'], 'invoice' => ['item', 'batch', 'rack', 'qty', 'price', 'total'], 'sales_return' => ['item', 'batch', 'back', 'qty', 'credit']] as $ent => $keys) {
    check("the detail page table of $ent has its columns as data", array_column($c->call('GET', "/schema/$ent")['json']['schema']['viewgrid'] ?? [], 'key') === $keys);
}
check('a form with no detail table has none', ($c->call('GET', '/schema/customer')['json']['schema']['viewgrid'] ?? []) === []);
$mm2 = $c->call('GET', '/settings/formdesign')['json']['data']['model'] ?? [];
$grn = null; foreach ($mm2 as $f) if ($f['key'] === 'grn') $grn = $f;
check('the designer model carries the detail table of a goods receipt and its standard order', $grn && $grn['viewgridForm'] === true && $grn['viewgridDefault'][0] === 'item' && count($grn['viewgrid']) === 9);
$vg = array_map(fn($x) => ['key' => $x['key'], 'show' => $x['key'] !== 'cost', 'label' => $x['key'] === 'qty' ? 'Length' : ''], $grn['viewgrid']);
$c->call('POST', '/settings/formdesign', ['payload' => json_encode(['grn' => ['style' => $grn['style'], 'lines' => $grn['lines'], 'deleted' => [], 'viewgrid' => $vg, 'sections' => array_map(fn($sec) => ['title' => $sec['title'], 'cols' => $sec['cols'], 'fields' => $sec['fields']], $grn['sections']), 'fields' => (object)[]]]), 'current' => 'grn', 'close' => '', 'return' => '']);
$by = array_column($c->call('GET', '/schema/grn')['json']['schema']['viewgrid'], null, 'key');
check('the company can rename and hide a column of the detail table; the form\'s own table is untouched', $by['qty']['label'] === 'Length' && $by['cost']['show'] === false && $by['item']['show'] === true
    && array_column($c->call('GET', '/schema/grn')['json']['schema']['grid'], 'key') === ['item', 'billed', 'received', 'rack', 'price', 'tax']);
$c->call('POST', '/settings/formdesign', ['payload' => json_encode(['grn' => ['reset' => true, 'style' => 'top', 'lines' => ['disc' => true, 'tax' => true], 'deleted' => [], 'sections' => [], 'fields' => (object)[]]]), 'current' => 'grn', 'close' => '', 'return' => '']);
check('resetting the form puts the detail table back', ($c->call('GET', '/schema/grn')['json']['schema']['viewgrid'][5]['label'] ?? '') === 'Qty');
// put the form back the way it was
$c->call('POST', '/settings/formdesign', ['payload' => json_encode(['purchase_order' => ['reset' => true, 'style' => 'top', 'lines' => ['disc' => true, 'tax' => true], 'deleted' => [], 'sections' => [], 'fields' => (object)[]]]), 'current' => 'purchase_order', 'close' => '', 'return' => '']);

echo "Goods receipt: rolls against a bill\n";
$r = $c->call('POST', '/items', ['name' => 'Roll Test Fabric', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'variants' => [['name' => '', 'sku' => 'RTF-1', 'cost_price' => '100', 'sale_price' => '150']], 'cf' => (object)[]]);
$rtf = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'RTF-1'", [$tA]);
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'BILL-77', 'lines' => [
    ['variant_id' => $rtf, 'qty' => '50', 'unit_price' => '100', 'tax_rate' => '5', 'location_id' => '0', 'lot_no' => 'R-1', 'billed_qty' => '200'],
    ['variant_id' => $rtf, 'qty' => '50', 'unit_price' => '100', 'tax_rate' => '5', 'location_id' => '0', 'lot_no' => 'R-2', 'billed_qty' => ''],
    ['variant_id' => $rtf, 'qty' => '50', 'unit_price' => '100', 'tax_rate' => '5', 'location_id' => '0', 'lot_no' => 'R-3', 'billed_qty' => ''],
    ['variant_id' => $rtf, 'qty' => '48.5', 'unit_price' => '100', 'tax_rate' => '5', 'location_id' => '0', 'lot_no' => 'R-4', 'billed_qty' => '']]]);
$gR = (int)DB::val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('a bill of 200 m arrives as four rolls: one batch is created for each roll and the stock is what really arrived', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM grn_items WHERE grn_id = ? AND batch_id > 0', [$gR]) === 4
    && (int)DB::val('SELECT COUNT(DISTINCT batch_id) FROM grn_items WHERE grn_id = ?', [$gR]) === 4 && (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]) === 198.5
    && (int)DB::val('SELECT COUNT(*) FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]) === 4);
check('each roll keeps its own length and the supplier\'s roll no.; the batches are numbered one after another', DB::all('SELECT b.batch_no, b.received_qty, b.supplier_lot FROM batches b WHERE b.tenant_id = ? AND b.variant_id = ? ORDER BY b.id', [$tA, $rtf])
    === [['batch_no' => 'RTF-1-B0001', 'received_qty' => '50.000', 'supplier_lot' => 'R-1'], ['batch_no' => 'RTF-1-B0002', 'received_qty' => '50.000', 'supplier_lot' => 'R-2'], ['batch_no' => 'RTF-1-B0003', 'received_qty' => '50.000', 'supplier_lot' => 'R-3'], ['batch_no' => 'RTF-1-B0004', 'received_qty' => '48.500', 'supplier_lot' => 'R-4']]);
$r = $c->call('GET', "/purchase/grns/$gR");
$rows = $r['json']['data']['items'] ?? [];
check('the receipt remembers the billed quantity on the first roll only (so billed against received can be shown)', count($rows) === 4 && (float)$rows[0]['billed_qty'] === 200.0 && $rows[1]['billed_qty'] === null && $rows[3]['billed_qty'] === null);
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'lines' => [['variant_id' => $rtf, 'qty' => '5', 'unit_price' => '100', 'billed_qty' => 'abc']]]);
check('a billed quantity that is not a number is refused', $r['status'] === 422);
$big = []; for ($i = 0; $i < 120; $i++) $big[] = ['variant_id' => $rtf, 'qty' => '1', 'unit_price' => '100', 'location_id' => '0', 'billed_qty' => $i === 0 ? '120' : ''];
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'lines' => $big]);
check('a truck with more than 100 rolls is received in one go (up to 500 lines)', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM batches WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]) === 124);
$lab = $c->call('GET', "/labels?type=rolls&grn=$gR");
check('the roll labels of that receipt are ready to print, one per roll', count($lab['json']['data']['rows'] ?? []) === 4);
// leave no trace for the sections that follow
DB::run('DELETE FROM grn_items WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]);

echo "Goods receipt: several purchase orders on one supplier invoice\n";
$mkPo = function (int $supplier, array $lines) use ($c, $wh, $tA): int {
    $c->call('POST', '/purchase/orders', ['supplier_id' => $supplier, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => $lines]);
    $id = (int)DB::val('SELECT MAX(id) FROM purchase_orders WHERE tenant_id = ?', [$tA]);
    $c->call('POST', "/purchase/orders/$id/submit");
    return $id;
};
$po1 = $mkPo($sid, [['variant_id' => $rtf, 'qty' => '100', 'unit_price' => '100', 'tax_rate' => '5']]);
$po2 = $mkPo($sid, [['variant_id' => $rtf, 'qty' => '60', 'unit_price' => '110', 'tax_rate' => '5'], ['variant_id' => $v2, 'qty' => '20', 'unit_price' => '50', 'tax_rate' => '5']]);
$c->call('POST', '/suppliers', ['name' => 'Other Mills']);
$sid2 = (int)DB::val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Other Mills'", [$tA]);
$po3 = $mkPo($sid2, [['variant_id' => $rtf, 'qty' => '10', 'unit_price' => '100', 'tax_rate' => '5']]);
$r = $c->call('GET', "/purchase/grns/open-pos?supplier=$sid");
$open = $r['json']['pos'] ?? [];
check('the receiving screen lists the supplier\'s open orders with what is due (and not another supplier\'s)', $r['status'] === 200 && count($open) >= 2 && in_array($po1, array_column($open, 'id')) && in_array($po2, array_column($open, 'id')) && !in_array($po3, array_column($open, 'id')));
$ol1 = []; $ol2 = [];
foreach ($open as $o) { if ((int)$o['id'] === $po1) $ol1 = $o['lines']; if ((int)$o['id'] === $po2) $ol2 = $o['lines']; }
check('each open order comes with its due lines and its number', count($ol1) === 1 && count($ol2) === 2 && $ol1[0]['po_no'] !== '' && (float)$ol2[0]['left'] === 60.0);
$before = (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]);
// the supplier's one invoice: 160 m (100 + 60) as 3 rolls of 50 + one of 10 ... and 20 of the other item
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'po_ids' => [$po1, $po2, $po3], 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'INV-ALL', 'lines' => [['po_item_id' => $ol1[0]['po_item_id'], 'qty' => '10']]]);
check('orders of different suppliers cannot share one receipt', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'same'));
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'po_ids' => [$po1, $po2], 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'INV-ALL', 'lines' => [['po_item_id' => 999999, 'qty' => '10']]]);
check('a line that is on none of the chosen orders is refused', $r['status'] === 422);
$r = $c->call('POST', '/purchase/grns', ['supplier_id' => $sid, 'po_ids' => [$po1, $po2], 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'supplier_ref' => 'INV-ALL', 'lines' => [
    ['po_item_id' => $ol1[0]['po_item_id'], 'qty' => '50', 'lot_no' => 'M-1', 'billed_qty' => '100'], ['po_item_id' => $ol1[0]['po_item_id'], 'qty' => '50', 'lot_no' => 'M-2'],
    ['po_item_id' => $ol2[0]['po_item_id'], 'qty' => '30', 'lot_no' => 'M-3', 'billed_qty' => '60'], ['po_item_id' => $ol2[0]['po_item_id'], 'qty' => '30', 'lot_no' => 'M-4'],
    ['po_item_id' => $ol2[1]['po_item_id'], 'qty' => '20']]]);
$gM = (int)DB::val('SELECT MAX(id) FROM grns WHERE tenant_id = ?', [$tA]);
check('one invoice for two orders is received in one receipt: all rolls are in stock', $r['status'] === 200 && (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]) === $before + 160.0
    && (int)DB::val('SELECT COUNT(*) FROM grn_items WHERE grn_id = ?', [$gM]) === 5 && DB::val('SELECT supplier_ref FROM grns WHERE id = ?', [$gM]) === 'INV-ALL');
check('both orders are marked received and each order\'s received quantity went up by its own lines', DB::val('SELECT status FROM purchase_orders WHERE id = ?', [$po1]) === 'received' && DB::val('SELECT status FROM purchase_orders WHERE id = ?', [$po2]) === 'received'
    && (float)DB::val('SELECT qty_received FROM purchase_order_items WHERE id = ?', [$ol1[0]['po_item_id']]) === 100.0 && (float)DB::val('SELECT qty_received FROM purchase_order_items WHERE id = ?', [$ol2[1]['po_item_id']]) === 20.0);
check('the receipt knows both orders', (int)DB::val('SELECT COUNT(*) FROM grn_pos WHERE grn_id = ?', [$gM]) === 2);
$r = $c->call('GET', "/purchase/grns/$gM");
check('the receipt page lists both orders and tags each line with its order', count($r['json']['data']['pos'] ?? []) === 2 && ($r['json']['data']['items'][0]['po_no'] ?? '') !== '' && ($r['json']['data']['items'][2]['po_no'] ?? '') !== ($r['json']['data']['items'][0]['po_no'] ?? ''));
$r = $c->call('GET', '/purchase/grns');
$row = null; foreach ($r['json']['data']['rows'] ?? [] as $x) if ((int)$x['id'] === $gM) $row = $x;
check('the receipts list shows both orders on the one receipt', $row && substr_count((string)$row['po_list'], ':') === 2);
$r = $c->call('GET', "/purchase/orders/$po2");
check('each order lists the shared receipt, and an order with goods received cannot be cancelled', count($r['json']['data']['grns'] ?? []) === 1 && $c->call('POST', "/purchase/orders/$po2/cancel")['status'] !== 200 + 1000);
$r = $c->call('GET', "/record/grn/$gM");
check('the receipt\'s record page links both orders', substr_count(json_encode($r['json']), '"links"') >= 1 && substr_count(json_encode($r['json']), 'purchase\\/orders\\/') >= 3);
$r = $c->call('POST', "/purchase/grns/$gM/bill");
check('one bill is made for the whole invoice', (int)DB::val('SELECT COUNT(*) FROM purchase_bills WHERE tenant_id = ? AND grn_id = ?', [$tA, $gM]) === 1);
// a receipt for one order still works as before (po_id from the order page)
$po4 = $mkPo($sid, [['variant_id' => $rtf, 'qty' => '10', 'unit_price' => '100', 'tax_rate' => '5']]);
$ol4 = $c->call('GET', "/purchase/orders/$po4/receive")['json']['data']['oldLines'] ?? [];
$r = $c->call('POST', '/purchase/grns', ['po_id' => $po4, 'warehouse_id' => $wh, 'received_date' => date('Y-m-d'), 'lines' => [['po_item_id' => $ol4[0]['po_item_id'], 'qty' => '10']]]);
check('receiving a single order from its own page still works and is recorded once', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM grn_pos WHERE po_id = ?', [$po4]) === 1);
DB::run('DELETE FROM grn_items WHERE tenant_id = ? AND variant_id = ?', [$tA, $rtf]);

echo "Catalogues and products\n";
$bq = new J($base); $bq->session(); $bq->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $bq->session();
$itemRv = (string)DB::val('SELECT i.name FROM items i JOIN item_variants v ON v.item_id = i.id WHERE v.id = ?', [$rv]);
$itemV2 = (string)DB::val('SELECT i.name FROM items i JOIN item_variants v ON v.item_id = i.id WHERE v.id = ?', [$v2]);
$r = $c->call('POST', '/catalogues', ['name' => 'CARAVAN', 'notes' => 'Showroom book']);
$catA = (int)DB::val('SELECT id FROM catalogues WHERE tenant_id = ? AND name = ?', [$tA, 'CARAVAN']);
check('a catalogue is created; the same name twice is refused', $r['status'] === 200 && $catA > 0 && $c->call('POST', '/catalogues', ['name' => 'CARAVAN'])['status'] === 422 && $c->call('POST', '/catalogues', ['name' => ''])['status'] === 422);
$csv = function (string $text) { $f = tempnam(sys_get_temp_dir(), 'pc'); file_put_contents($f, $text); return new CURLFile($f, 'text/csv', 'p.csv'); };
$before = (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tA]);
$r = $c->form('/products/import', ['file' => $csv("Catalogue Name,Product Name,Item Name,Price\nCARAVAN,CARAVAN-1,\"$itemRv\",\nCARAVAN,CARAVAN-2,\"$itemV2\",500\nSECOND,SECOND-9,\"$itemRv\",450\n,Plain Product,\"$itemRv\",300\nCARAVAN,CARAVAN-3,No Such Item,100\n")]);
check('an import with an unknown item is refused as a whole: nothing is added and the row is named', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'Row 6') && (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tA]) === $before);
$r = $c->form('/products/import', ['file' => $csv("catalogue name,product name,item name,price\nCARAVAN,CARAVAN-1,\"$itemRv\",\nCARAVAN,CARAVAN-2,\"$itemV2\",500\nSECOND,SECOND-9,\"$itemRv\",450\n,Plain Product,\"$itemRv\",300\nCARAVAN,CARAVAN-2,\"$itemV2\",500\n")]);
check('a file that lists a product twice is refused', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'twice'));
$r = $c->form('/products/import', ['file' => $csv("Catalogue Name,Product Name,Item Name,Price\nCARAVAN,CARAVAN-1,\"$itemRv\",\nCARAVAN,CARAVAN-2,\"$itemV2\",500\nSECOND,SECOND-9,\"$itemRv\",450\n,Plain Product,\"$itemRv\",300\n")]);
$p1 = (int)DB::val('SELECT id FROM products WHERE tenant_id = ? AND name = ?', [$tA, 'CARAVAN-1']); $p9 = (int)DB::val('SELECT id FROM products WHERE tenant_id = ? AND name = ?', [$tA, 'SECOND-9']);
check('the sheet imports: catalogues are created, products land in them, the same item sits in two catalogues, one product has no catalogue',
    $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tA]) === $before + 4 && (int)DB::val('SELECT COUNT(*) FROM catalogues WHERE tenant_id = ?', [$tA]) === 2
    && (int)DB::val('SELECT variant_id FROM products WHERE id = ?', [$p1]) === $rv && (int)DB::val('SELECT variant_id FROM products WHERE id = ?', [$p9]) === $rv && DB::val('SELECT catalogue_id FROM products WHERE tenant_id = ? AND name = ?', [$tA, 'Plain Product']) === null);
check('a product without a price takes the item\'s sale price', (float)DB::val('SELECT price FROM products WHERE id = ?', [$p1]) === (float)DB::val('SELECT sale_price FROM item_variants WHERE id = ?', [$rv]));
$r = $c->form('/products/import', ['file' => $csv("Catalogue Name,Product Name,Item Name,Price\nCARAVAN,CARAVAN-1,\"$itemRv\",450\n")]);
check('importing a name again updates that product instead of adding another', $r['status'] === 200 && (float)DB::val('SELECT price FROM products WHERE id = ?', [$p1]) === 450.0 && (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ? AND name = ?', [$tA, 'CARAVAN-1']) === 1);
$r = $c->call('GET', '/products?catalogue=none');
check('the product list filters by "no catalogue"', count($r['json']['data']['rows'] ?? []) === 1 && ($r['json']['data']['rows'][0]['name'] ?? '') === 'Plain Product');
$r = $c->call('GET', "/products?catalogue=$catA");
check('and by catalogue, with the item and its stock', count($r['json']['data']['rows']) === 2 && isset($r['json']['data']['rows'][0]['stock'], $r['json']['data']['rows'][0]['item_name']));
$r = $c->call('GET', '/catalogues');
check('the catalogue list counts the products in each', in_array(2, array_map('intval', array_column($r['json']['data']['rows'], 'products')), true) && ($r['json']['data']['loose'] ?? -1) === 1);

$r = $c->call('GET', "/lookup/sale-items?q=" . rawurlencode('caravan 1') . "&customer=$cid&warehouse=$wh");
check('"caravan 1" finds CARAVAN-1 with its own price, its item and the free stock', ($r['json'][0]['product_name'] ?? '') === 'CARAVAN-1' && (int)($r['json'][0]['product_id'] ?? 0) === $p1 && (int)$r['json'][0]['id'] === $rv && (float)$r['json'][0]['price'] === 450.0 && isset($r['json'][0]['available']) && ($r['json'][0]['catalogue'] ?? '') === 'CARAVAN');
$r = $c->call('GET', "/lookup/sale-items?q=" . rawurlencode($itemRv) . "&customer=$cid&warehouse=$wh");
$names = array_column($r['json'], 'product_name');
check('searching by the item\'s name lists its products, not the bare item (the same item is in two catalogues)', in_array('CARAVAN-1', $names, true) && in_array('SECOND-9', $names, true) && !array_filter($r['json'], fn($x) => !isset($x['product_id']) && (int)$x['id'] === $rv));
$r = $c->call('GET', "/lookup/sale-items?q=" . rawurlencode($itemRv) . "&mode=item");
check('?mode=item still gives plain items (price lists use it)', (int)($r['json'][0]['id'] ?? 0) === $rv && !isset($r['json'][0]['product_id']));
$c->call('POST', "/catalogues/$catA", ['name' => 'CARAVAN', 'is_active' => 0]);
$r = $c->call('GET', "/lookup/sale-items?q=" . rawurlencode('caravan-2') . "&customer=$cid");
check('an inactive catalogue hides its products from selling', !array_filter($r['json'], fn($x) => ($x['product_name'] ?? '') === 'CARAVAN-2'));
$c->call('POST', "/catalogues/$catA", ['name' => 'CARAVAN', 'is_active' => 1]);

$r = $c->call('POST', '/sales/orders', ['customer_id' => $cid, 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'lines' => [
    ['variant_id' => $rv, 'product_id' => $p1, 'qty' => '2', 'unit_price' => '450', 'discount_pct' => '', 'tax_rate' => '0'], ['variant_id' => $v2, 'product_id' => $p1, 'qty' => '1', 'unit_price' => '150', 'discount_pct' => '', 'tax_rate' => '0']]]);
$soP = (int)DB::val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
check('an order line remembers its product; a product that does not belong to that item is ignored', $r['status'] === 200 && (int)DB::val('SELECT product_id FROM sales_order_items WHERE order_id = ? AND variant_id = ?', [$soP, $rv]) === $p1
    && DB::val('SELECT product_id FROM sales_order_items WHERE order_id = ? AND variant_id = ?', [$soP, $v2]) === null);
$r = $c->call('GET', "/sales/orders/$soP");
check('the order page names the line by its product (and still has the item)', ($r['json']['data']['items'][0]['product_name'] ?? '') === 'CARAVAN-1' && ($r['json']['data']['items'][0]['item_name'] ?? '') === $itemRv && array_key_exists('product_name', $r['json']['data']['items'][1]) && $r['json']['data']['items'][1]['product_name'] === null);
$r = $c->call('GET', "/sales/orders/$soP/edit");
check('editing the order brings the product back', (int)($r['json']['data']['oldLines'][0]['product_id'] ?? 0) === $p1 && str_contains((string)$r['json']['data']['oldLines'][0]['label'], 'CARAVAN-1'));
$r = $c->call('POST', '/sales/deliveries', ['customer_id' => $cid, 'warehouse_id' => $wh, 'delivery_date' => date('Y-m-d'), 'make_invoice' => 1, 'lines' => [
    ['variant_id' => $rv, 'product_id' => $p9, 'qty' => '1', 'batch_id' => $bid2, 'location_id' => $rack1, 'unit_price' => '450', 'discount_pct' => '', 'tax_rate' => '0']]]);
$dlvP = (int)DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$tA]); $invP = (int)DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? AND delivery_id = ?', [$tA, $dlvP]);
check('a direct sale by product: stock leaves the item, the delivery line keeps the product', $r['status'] === 200 && $invP > 0 && (int)DB::val('SELECT product_id FROM delivery_items WHERE delivery_id = ? AND parent_id IS NULL', [$dlvP]) === $p9);
$r = $c->call('GET', "/sales/invoices/$invP/print");
check('the invoice and its printout name the line by product', ($r['json']['data']['items'][0]['product_name'] ?? '') === 'SECOND-9' && ($c->call('GET', "/sales/invoices/$invP")['json']['data']['items'][0]['product_name'] ?? '') === 'SECOND-9');
$vch = $c->call('GET', "/sales/deliveries/$dlvP/voucher");
check('the warehouse voucher still says the item (the cutter works by item)', str_contains(json_encode($vch['json']['data']['lines'] ?? []), $itemRv) && !str_contains(json_encode($vch['json']['data']['lines'] ?? []), 'SECOND-9'));
$rep = $c->call('GET', '/reports/sales-summary?by=catalogue&from=' . date('Y-m-01') . '&to=' . date('Y-m-t'));
check('the sales summary can be grouped by catalogue and by product', in_array('SECOND', array_column($rep['json']['data']['rows'] ?? [], 'name'), true) && in_array('SECOND-9', array_column($c->call('GET', '/reports/sales-summary?by=product&from=' . date('Y-m-01') . '&to=' . date('Y-m-t'))['json']['data']['rows'] ?? [], 'name'), true));
$r = $c->call('POST', "/products/$p9/delete");
check('a product on a sales document cannot be deleted (it can be made inactive)', $r['status'] === 422 && (int)DB::val('SELECT COUNT(*) FROM products WHERE id = ?', [$p9]) === 1);
$r = $c->call('GET', "/items/" . (int)DB::val('SELECT item_id FROM item_variants WHERE id = ?', [$rv]));
check('the item page lists the products it is sold as', count($r['json']['data']['products'] ?? []) === 3);
check('a catalogue that still has products cannot be deleted', $c->call('POST', "/catalogues/$catA/delete")['status'] === 422);
$pPlain = (int)DB::val('SELECT id FROM products WHERE tenant_id = ? AND name = ?', [$tA, 'Plain Product']);
$r = $c->call('POST', "/products/$pPlain", ['name' => 'Plain Product', 'catalogue_id' => '', 'variant_id' => $v2, 'price' => '320', 'is_active' => 1]);
check('a product can be moved to another item and repriced', $r['status'] === 200 && (int)DB::val('SELECT variant_id FROM products WHERE id = ?', [$pPlain]) === $v2 && (float)DB::val('SELECT price FROM products WHERE id = ?', [$pPlain]) === 320.0);
$r = $c->call('POST', '/products', ['name' => 'CARAVAN-1', 'variant_id' => $rv, 'price' => '1']);
check('a duplicate product name, a missing item and a bad price are refused', $r['status'] === 422 && $c->call('POST', '/products', ['name' => 'X1', 'price' => '1'])['status'] === 422 && $c->call('POST', '/products', ['name' => 'X1', 'variant_id' => $rv, 'price' => '-5'])['status'] === 422);
$r = $c->call('POST', "/products/$pPlain/delete");
check('an unused product is deleted', $r['status'] === 200 && !DB::val('SELECT 1 FROM products WHERE id = ?', [$pPlain]));
check('another company cannot see, change or delete them', $bq->call('GET', "/products/$p1/edit")['status'] === 404 && $bq->call('POST', "/products/$p1", ['name' => 'Hack', 'variant_id' => $rv, 'price' => '1'])['status'] === 404
    && $bq->call('POST', "/products/$p1/delete")['status'] === 404 && $bq->call('GET', "/catalogues/$catA")['status'] === 404 && $bq->call('POST', "/catalogues/$catA/delete")['status'] === 404
    && !array_filter($bq->call('GET', '/products')['json']['data']['rows'] ?? [], fn($x) => str_starts_with((string)$x['name'], 'CARAVAN')));
$r = $c->call('GET', '/search?q=CARAVAN');
check('search finds products', in_array('Products', array_column($r['json']['groups'] ?? [], 'title'), true));
// no one is left with the test data
DB::run('UPDATE delivery_items SET product_id = NULL WHERE tenant_id = ?', [$tA]); DB::run('UPDATE sales_order_items SET product_id = NULL WHERE tenant_id = ?', [$tA]);
DB::run('DELETE FROM products WHERE tenant_id = ?', [$tA]); DB::run('DELETE FROM catalogues WHERE tenant_id = ?', [$tA]);

echo "Gutt numbers: known by a number first, named later\n";
$r = $c->call('POST', '/catalogues', ['code' => 'G-245']);
$cg = (int)DB::val("SELECT id FROM catalogues WHERE tenant_id = ? AND code = 'G-245'", [$tA]);
$row = DB::one('SELECT name, code, named FROM catalogues WHERE id = ?', [$cg]);
check('a catalogue can start as just a gutt number: it is called by the number and marked "name pending"', $r['status'] === 200 && $row['name'] === 'G-245' && (int)$row['named'] === 0);
check('the same gutt number twice, or neither number nor name, is refused', $c->call('POST', '/catalogues', ['code' => 'G-245'])['status'] === 422 && $c->call('POST', '/catalogues', ['notes' => 'x'])['status'] === 422);
$r = $c->call('POST', '/products', ['code' => '1', 'catalogue_id' => $cg, 'variant_id' => $rv, 'price' => '450']);
$pg1 = (int)DB::val("SELECT id FROM products WHERE tenant_id = ? AND catalogue_id = ? AND code = '1'", [$tA, $cg]);
$prow = DB::one('SELECT name, named FROM products WHERE id = ?', [$pg1]);
check('a design can start as just a gutt number: it is called "catalogue-number" until named', $r['status'] === 200 && $prow['name'] === 'G-245-1' && (int)$prow['named'] === 0);
check('the same gutt number twice in one catalogue is refused, in another catalogue it is fine', $c->call('POST', '/products', ['code' => '1', 'catalogue_id' => $cg, 'variant_id' => $rv, 'price' => '1'])['status'] === 422
    && $c->call('POST', '/catalogues', ['code' => 'G-246'])['status'] === 200 && $c->call('POST', '/products', ['code' => '1', 'catalogue_id' => (int)DB::val("SELECT id FROM catalogues WHERE tenant_id = ? AND code = 'G-246'", [$tA]), 'variant_id' => $rv, 'price' => '1'])['status'] === 200);
$r = $c->call('POST', "/catalogues/$cg", ['code' => 'G-245', 'name' => 'MAHARANI', 'is_active' => 1]);
$row = DB::one('SELECT name, code, named FROM catalogues WHERE id = ?', [$cg]);
check('naming it later keeps the gutt number and clears "name pending"', $r['status'] === 200 && $row['name'] === 'MAHARANI' && $row['code'] === 'G-245' && (int)$row['named'] === 1);
$r = $c->call('POST', "/products/$pg1", ['code' => '1', 'name' => 'MAHARANI Rose', 'catalogue_id' => $cg, 'variant_id' => $rv, 'price' => '450', 'is_active' => 1]);
check('a design is named later the same way', $r['status'] === 200 && DB::val('SELECT name FROM products WHERE id = ?', [$pg1]) === 'MAHARANI Rose' && (int)DB::val('SELECT named FROM products WHERE id = ?', [$pg1]) === 1);
$r = $c->call('POST', "/products/$pg1", ['code' => '1', 'name' => 'MAHARANI Rose', 'catalogue_id' => $cg, 'variant_id' => $rv, 'price' => '460', 'is_active' => 1]);
check('saving a named design again keeps it named', (int)DB::val('SELECT named FROM products WHERE id = ?', [$pg1]) === 1);
$r = $c->call('GET', '/catalogues?q=G-245');
check('catalogues are found by gutt number', count($r['json']['data']['rows'] ?? []) === 1 && ($r['json']['data']['rows'][0]['code'] ?? '') === 'G-245');
$r = $c->call('GET', '/products?q=MAHARANI');
check('products are found by name, and by gutt number inside the catalogue', ($r['json']['data']['total'] ?? 0) >= 1 && ($c->call('GET', '/lookup/find/product?q=1')['json']['rows'][0]['sub'] ?? '') !== '');
$csv = function (string $text) { $f = tempnam(sys_get_temp_dir(), 'pg'); file_put_contents($f, $text); return new CURLFile($f, 'text/csv', 'p.csv'); };
$r = $c->form('/products/import', ['file' => $csv("Catalogue Name,Item Name,Gutt No,Price\nG-300,$itemRv,5,200\nG-300,$itemRv,6,\n")]);
$cn = (int)DB::val("SELECT id FROM catalogues WHERE tenant_id = ? AND name = 'G-300'", [$tA]);
check('a sheet with only gutt numbers imports: the designs are called catalogue-number and are marked "name pending"', $cn > 0 && (int)DB::val('SELECT COUNT(*) FROM products WHERE catalogue_id = ? AND named = 0', [$cn]) === 2 && DB::val('SELECT name FROM products WHERE catalogue_id = ? AND code = ?', [$cn, '5']) === 'G-300-5');
DB::run('DELETE FROM products WHERE tenant_id = ? AND (catalogue_id = ? OR catalogue_id = ?)', [$tA, $cg, $cn]);
DB::run("DELETE FROM products WHERE tenant_id = ? AND catalogue_id IN (SELECT id FROM catalogues WHERE tenant_id = ? AND code = 'G-246')", [$tA, $tA]); DB::run("DELETE FROM catalogues WHERE tenant_id = ? AND code = 'G-246'", [$tA]);

echo "Catalogue launches\n";
$c->call('POST', '/items', ['name' => 'Launch Fabric', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'variants' => [['name' => '', 'sku' => 'LF-1', 'cost_price' => '80', 'sale_price' => '100']], 'cf' => (object)[]]);
$lf = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'LF-1'", [$tA]);
$c->call('POST', '/stock/adjustments', ['warehouse_id' => $wh, 'reason' => 'opening', 'lines' => [['variant_id' => $lf, 'batch_id' => 'new', 'lot_no' => 'LR-1', 'location_id' => '0', 'qty' => '50', 'unit_cost' => '80'], ['variant_id' => $lf, 'batch_id' => 'new', 'lot_no' => 'LR-2', 'location_id' => '0', 'qty' => '50', 'unit_cost' => '80']]]);
$lb = array_column(DB::all('SELECT id FROM batches WHERE tenant_id = ? AND variant_id = ? ORDER BY id', [$tA, $lf]), 'id');
$lstock = fn() => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ?', [$tA, $lf]);
$c->call('POST', '/catalogues', ['code' => 'G-LAUNCH']);
$cl = (int)DB::val("SELECT id FROM catalogues WHERE tenant_id = ? AND code = 'G-LAUNCH'", [$tA]);
check('there are two rolls of 50 m to launch with', count($lb) === 2 && $lstock() === 100.0);
$r = $c->call('POST', '/launches', ['catalogue_id' => $cl, 'warehouse_id' => $wh, 'ship_to' => 'Print House, Surat', 'note' => 'Diwali book', 'sizes' => [['size_name' => 'Large', 'qty' => '100'], ['size_name' => 'Small', 'qty' => '200']]]);
$L = (int)DB::val('SELECT MAX(id) FROM catalogue_launches WHERE tenant_id = ?', [$tA]);
$lrow = DB::one('SELECT * FROM catalogue_launches WHERE id = ?', [$L]);
check('a launch is saved as a draft with its sizes (the fabric is asked for with a sales order)', $r['status'] === 200 && $lrow['status'] === 'draft' && str_starts_with($lrow['launch_no'], 'CL-') && (int)DB::val('SELECT COUNT(*) FROM launch_sizes WHERE launch_id = ?', [$L]) === 2);
check('a launch needs a catalogue and a warehouse; a size needs a name; the same size twice is refused', $c->call('POST', '/launches', ['warehouse_id' => $wh])['status'] === 422
    && $c->call('POST', '/launches', ['catalogue_id' => $cl, 'warehouse_id' => $wh, 'sizes' => [['size_name' => '', 'qty' => '5']]])['status'] === 422
    && $c->call('POST', '/launches', ['catalogue_id' => $cl, 'warehouse_id' => $wh, 'sizes' => [['size_name' => 'Large', 'qty' => '5'], ['size_name' => 'large', 'qty' => '5']]])['status'] === 422);
$c->call('POST', '/launches', ['catalogue_id' => $cl, 'warehouse_id' => $wh]);
$Lempty = (int)DB::val('SELECT MAX(id) FROM catalogue_launches WHERE tenant_id = ?', [$tA]);
check('a launch with no fabric order yet can be cancelled', $c->call('POST', "/launches/$Lempty/cancel")['status'] === 200 && DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$Lempty]) === 'cancelled');
// the fabric request is a sales order, like any order a warehouse receives
$r = $c->call('GET', "/sales/orders/create?launch=$L");
$ln = $r['json']['data']['launch'] ?? null;
check('the sales order form for a launch comes addressed to the catalogue department with the place the fabric must go to', $ln && (int)$ln['id'] === $L && $ln['ship_to'] === 'Print House, Surat' && (int)$ln['customer_id'] > 0 && DB::val('SELECT name FROM customers WHERE id = ?', [$ln['customer_id']]) === 'Catalogue Department');
$r = $c->call('POST', '/sales/orders', ['customer_id' => $ln['customer_id'], 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'ship_to' => 'Print House, Surat', 'launch_id' => $L, 'lines' => [['variant_id' => $lf, 'qty' => '60', 'unit_price' => '0', 'discount_pct' => '', 'tax_rate' => '']]]);
$so = (int)DB::val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
check('the fabric order is saved as a sales order tied to the launch; while it is a draft the launch is still a draft', $r['status'] === 200 && (int)DB::val('SELECT launch_id FROM sales_orders WHERE id = ?', [$so]) === $L && DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'draft');
check('an order cannot be tied to a launch that does not exist, or to another company\'s', $c->call('POST', '/sales/orders', ['customer_id' => $ln['customer_id'], 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'launch_id' => 99999999, 'lines' => [['variant_id' => $lf, 'qty' => '1', 'unit_price' => '0']]])['status'] === 422);
$c->call('POST', "/sales/orders/$so/confirm");
check('confirming the order sends it to the warehouse: the launch is "ordered"', DB::val('SELECT status FROM sales_orders WHERE id = ?', [$so]) === 'confirmed' && DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'ordered');
$lst = $c->call('GET', '/sales/orders')['json']['data']['rows'] ?? [];
$mine = array_values(array_filter($lst, fn($x) => (int)$x['id'] === $so))[0] ?? [];
check('the warehouse finds it among its sales orders, marked as fabric for the launch', ($mine['launch_no'] ?? '') === $lrow['launch_no'] && $c->call('GET', "/sales/orders/$so")['json']['data']['launch']['launch_no'] === $lrow['launch_no']);
check('another company cannot see the launch', $bl->call('GET', "/launches/$L")['status'] === 404);
// the warehouse delivers it, roll by roll
$items = $c->call('GET', "/sales/orders/$so")['json']['data']['items'];
$oi = $items[0]['id'];
$c->call('POST', '/sales/deliveries', ['order_id' => $so, 'delivery_date' => date('Y-m-d'), 'lines' => [['order_item_id' => $oi, 'variant_id' => $lf, 'qty' => '40', 'batch_id' => $lb[0], 'location_id' => '0']]]);
$d1 = (int)DB::val('SELECT MAX(id) FROM deliveries WHERE tenant_id = ?', [$tA]);
$r = $c->call('GET', "/launches/$L");
$fab = $r['json']['data']['fabric'][0] ?? [];
check('delivering 40 m from the sales order takes it out of stock, makes the launch "partly delivered" and counts the cost into the launch', $lstock() === 60.0 && DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'partial'
    && (float)$fab['qty_ordered'] === 60.0 && (float)$fab['qty_issued'] === 40.0 && abs((float)$fab['value_issued'] - 3200.0) < 0.005);
$c->call('POST', '/sales/deliveries', ['order_id' => $so, 'delivery_date' => date('Y-m-d'), 'lines' => [['order_item_id' => $oi, 'variant_id' => $lf, 'qty' => '20', 'batch_id' => $lb[1], 'location_id' => '0']]]);
check('when everything asked for has been delivered the launch is "fabric delivered"', DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'dispatched' && $lstock() === 40.0);
// wrong fabric: it comes back as an ordinary sales return of that delivery
$c->call('POST', "/sales/deliveries/$d1/invoice");
$inv = (int)DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? AND delivery_id = ?', [$tA, $d1]);
$ri2 = DB::one('SELECT id FROM delivery_items WHERE delivery_id = ?', [$d1]);
$c->call('POST', '/sales/returns', ['invoice_id' => $inv, 'return_date' => date('Y-m-d'), 'reason' => 'wrong shade', 'lines' => [(string)$ri2['id'] => ['qty' => '10', 'location_id' => '0', 'restock' => 1]]]);
$r = $c->call('GET', "/launches/$L");
$fab = $r['json']['data']['fabric'][0] ?? [];
check('wrong fabric sent back with a sales return goes into stock and is taken off the launch\'s cost (and still shows on it as "came back")', $lstock() === 50.0 && (float)$fab['qty_returned'] === 10.0 && abs((float)$fab['value_issued'] - 4000.0) < 0.005);
// the right fabric is asked for with another sales order for the same launch
$c->call('POST', '/sales/orders', ['customer_id' => $ln['customer_id'], 'warehouse_id' => $wh, 'order_date' => date('Y-m-d'), 'ship_to' => 'Print House, Surat', 'launch_id' => $L, 'lines' => [['variant_id' => $lf, 'qty' => '10', 'unit_price' => '0']]]);
$so2 = (int)DB::val('SELECT MAX(id) FROM sales_orders WHERE tenant_id = ?', [$tA]);
$c->call('POST', "/sales/orders/$so2/confirm");
check('a second fabric order for the same launch makes it "partly delivered" again', DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'ordered' || DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'partial');
$oi2 = $c->call('GET', "/sales/orders/$so2")['json']['data']['items'][0]['id'];
$c->call('POST', '/sales/deliveries', ['order_id' => $so2, 'delivery_date' => date('Y-m-d'), 'lines' => [['order_item_id' => $oi2, 'variant_id' => $lf, 'qty' => '10', 'batch_id' => $lb[1], 'location_id' => '0']]]);
$r = $c->call('GET', "/launches/$L");
check('a launch with fabric orders cannot be cancelled', $c->call('POST', "/launches/$L/cancel")['status'] === 422);
// the printed books arrive
$sz = array_column(DB::all('SELECT id, size_name FROM launch_sizes WHERE launch_id = ? ORDER BY id', [$L]), 'id', 'size_name');
check('books cannot be received without a count, or against a size that is not on the launch', $c->call('POST', "/launches/$L/receive", ['warehouse_id' => $wh, 'lines' => []])['status'] === 422
    && $c->call('POST', "/launches/$L/receive", ['warehouse_id' => $wh, 'lines' => [['size_id' => 99999999, 'qty' => '5']]])['status'] === 422);
$r = $c->call('POST', "/launches/$L/receive", ['warehouse_id' => $wh2, 'extra_cost' => '1200', 'lines' => [['size_id' => $sz['Large'], 'qty' => '90', 'location_id' => '0'], ['size_id' => $sz['Small'], 'qty' => '180', 'location_id' => '0']]]);
$vL = (int)DB::val('SELECT variant_id FROM launch_sizes WHERE id = ?', [$sz['Large']]); $vS = (int)DB::val('SELECT variant_id FROM launch_sizes WHERE id = ?', [$sz['Small']]);
$it = DB::one('SELECT i.name, i.item_type, v.size, v.cost_price FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.id = ?', [$vL]);
check('receiving the books makes an item for each size (named after the catalogue) and puts the copies into stock where they were received', $r['status'] === 200 && $vL > 0 && $vS > 0 && $vL !== $vS && $it['item_type'] === 'catalogue' && $it['size'] === 'Large' && str_contains($it['name'], 'G-LAUNCH')
    && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE variant_id = ? AND warehouse_id = ?', [$vL, $wh2]) === 90.0 && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE variant_id = ? AND warehouse_id = ?', [$vS, $wh2]) === 180.0);
check('a copy costs the fabric that went into it plus the extra cost, shared over all copies: (4800 + 1200) / 270', abs((float)$it['cost_price'] - 22.22) < 0.005 && abs((float)DB::val("SELECT unit_cost FROM stock_ledger WHERE variant_id = ? AND type = 'launch_receipt'", [$vL]) - 22.22) < 0.005);
check('the launch is marked received and remembers what has been counted into cost', DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'received' && (float)DB::val('SELECT fabric_absorbed FROM catalogue_launches WHERE id = ?', [$L]) === 4800.0 && (float)DB::val('SELECT extra_cost FROM catalogue_launches WHERE id = ?', [$L]) === 1200.0
    && (int)DB::val('SELECT SUM(qty_received) FROM launch_sizes WHERE launch_id = ?', [$L]) === 270);
$c->call('POST', "/launches/$L/receive", ['warehouse_id' => $wh2, 'extra_cost' => '30', 'lines' => [['size_id' => $sz['Large'], 'qty' => '10']]]);
check('a later delivery of more copies only carries its own extra cost (the fabric is already counted) and reuses the size\'s item', (int)DB::val('SELECT variant_id FROM launch_sizes WHERE id = ?', [$sz['Large']]) === $vL && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE variant_id = ? AND warehouse_id = ?', [$vL, $wh2]) === 100.0
    && abs((float)DB::val('SELECT cost_price FROM item_variants WHERE id = ?', [$vL]) - 3.0) < 0.005);
$r = $c->call('POST', '/stock/adjustments', ['warehouse_id' => $wh2, 'reason' => 'given_free', 'note' => 'To dealer Shah Furnishings', 'lines' => [['variant_id' => $vL, 'batch_id' => '', 'location_id' => '0', 'qty' => '-25']]]);
check('catalogues are given free to showrooms and dealers with a stock adjustment (reason "Given free")', $r['status'] === 200 && (float)DB::val('SELECT SUM(qty) FROM stock_balances WHERE variant_id = ? AND warehouse_id = ?', [$vL, $wh2]) === 75.0);
$r = $c->call('GET', "/launches/$L");
check('the launch page shows the books received', $r['status'] === 200 && count($r['json']['data']['docs']) === 2);
check('the launch can be closed once its books are in', $c->call('POST', "/launches/$L/close")['status'] === 200 && DB::val('SELECT status FROM catalogue_launches WHERE id = ?', [$L]) === 'closed' && $c->call('POST', "/launches/$L/receive", ['warehouse_id' => $wh2, 'lines' => [['size_id' => $sz['Large'], 'qty' => '1']]])['status'] === 422);
$r = $c->call('GET', '/launches?status=closed');
check('the launch list shows it with fabric and copies', $r['status'] === 200 && ($r['json']['data']['rows'][0]['launch_no'] ?? '') === $lrow['launch_no'] && (int)$r['json']['data']['rows'][0]['copies_received'] === 280 && (float)$r['json']['data']['rows'][0]['fabric_ordered'] === 70.0);
$rp = $c->call('GET', '/reports/catalogue-launches?from=' . date('Y-m-d', strtotime('-1 day')) . '&to=' . date('Y-m-d'))['json']['data']['rows'] ?? [];
$rpr = array_values(array_filter($rp, fn($x) => $x['no'] === $lrow['launch_no']))[0] ?? [];
check('the report of launch costs shows fabric, extra cost, books received and the cost of one book', $rpr && abs((float)$rpr['fabric_cost'] - 4800.0) < 0.005 && (int)$rpr['books'] === 280 && abs((float)$rpr['total'] - 6030.0) < 0.005 && abs((float)$rpr['per_book'] - 21.54) < 0.01 && $rpr['gutt'] === 'G-LAUNCH');
$c->call('POST', '/roles', ['name' => 'Viewer Only ' . $sfx, 'perms' => ['items.view']]);
$rVo = (int)DB::val('SELECT id FROM roles WHERE tenant_id = ? AND name = ?', [$tA, 'Viewer Only ' . $sfx]);
$c->call('POST', '/users', ['name' => 'Viewer Only', 'email' => "vo-$sfx@test.local", 'password' => 'Password123', 'role_id' => $rVo, 'warehouse_id' => '0']);
$vj = new J($base); $vj->session(); $vj->call('POST', '/login', ['email' => "vo-$sfx@test.local", 'password' => 'Password123']); $vj->session();
check('someone with no catalogue, sales or stock-receiving permission cannot open launches', $vj->call('GET', '/launches')['status'] === 403 && $vj->call('GET', "/launches/$L")['status'] === 403);
DB::run('DELETE FROM stock_balances WHERE tenant_id = ? AND variant_id IN (?, ?, ?)', [$tA, $lf, $vL, $vS]);

echo "Print templates\n";
$r = $c->call('GET', '/settings/templates/design?doc=invoice');
$cfg = $r['json']['data']['cfg'] ?? [];
check('the designer gives the vocabulary of the document and the Standard layout to start from', $r['status'] === 200 && isset($r['json']['data']['spec']['columns']['item'], $r['json']['data']['spec']['totals']['balance']) && ($cfg['v'] ?? 0) === 2 && count($cfg['blocks'] ?? []) >= 6 && !empty($r['json']['data']['head']['name']));
$mine = $cfg; $mine['page']['size'] = 'Nonsense'; $mine['page']['margin'] = 999; $mine['page']['accent'] = 'red'; $mine['header']['title'] = 'GST INVOICE';
foreach ($mine['blocks'] as &$bk) if ($bk['type'] === 'items') { $bk['columns'][] = ['k' => 'bogus', 'label' => 'x', 'show' => 1]; foreach ($bk['columns'] as &$col) if ($col['k'] === 'hsn') $col['show'] = 1; unset($col); }
unset($bk);
$mine['blocks'][] = ['id' => 'text:abc123', 'type' => 'text', 'show' => 1, 'title' => 'Jurisdiction', 'text' => 'Surat only', 'align' => 'center', 'boxed' => 1, 'size' => 9];
$mine['blocks'][] = ['id' => 'evil', 'type' => 'items', 'show' => 1];
$r = $c->call('POST', '/settings/templates/save', ['doc' => 'invoice', 'id' => '', 'name' => 'Premium', 'cfg' => $mine, 'make_default' => '']);
$ptid = (int)DB::val('SELECT id FROM print_templates WHERE tenant_id = ? AND name = ?', [$tA, 'Premium']);
check('a template is saved', $r['status'] === 200 && $ptid > 0);
$saved = json_decode((string)DB::val('SELECT config FROM print_templates WHERE id = ?', [$ptid]), true);
$itemsBlock = null; foreach ($saved['blocks'] as $bk) if ($bk['type'] === 'items') $itemsBlock = $bk;
check('what is saved is checked: bad paper, margin and colour fall back, unknown columns and sections are dropped, own text and the HSN column stay',
    $saved['page']['size'] === 'A4' && (float)$saved['page']['margin'] === 40.0 && $saved['page']['accent'] === '#1f2937' && $saved['header']['title'] === 'GST INVOICE'
    && !in_array('bogus', array_column($itemsBlock['columns'], 'k'), true) && !in_array('evil', array_column($saved['blocks'], 'id'), true) && in_array('text:abc123', array_column($saved['blocks'], 'id'), true)
    && (bool)array_filter($itemsBlock['columns'], fn($x) => $x['k'] === 'hsn' && $x['show'] === 1));
check('the same name twice, an empty name and the name "Standard" are refused', $c->call('POST', '/settings/templates/save', ['doc' => 'invoice', 'name' => 'Premium', 'cfg' => $mine])['status'] === 422
    && $c->call('POST', '/settings/templates/save', ['doc' => 'invoice', 'name' => '', 'cfg' => $mine])['status'] === 422 && $c->call('POST', '/settings/templates/save', ['doc' => 'invoice', 'name' => 'standard', 'cfg' => $mine])['status'] === 422);
$invId = (int)DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? LIMIT 1', [$tA]);
$pr = $invId ? $c->call('GET', "/sales/invoices/$invId/print") : null;
check('until one is chosen, printing uses Standard (which follows the older simple options)', !$pr || (($pr['json']['data']['design']['id'] ?? '') === 'std' && count($pr['json']['data']['design']['list'] ?? []) === 2));
$c->call('POST', "/settings/templates/$ptid/default", ['doc' => 'invoice']);
$pr = $invId ? $c->call('GET', "/sales/invoices/$invId/print") : null;
check('the default template is the one printing uses; ?tpl= picks another', !$pr || (($pr['json']['data']['design']['id'] ?? '') === (string)$ptid && ($pr['json']['data']['design']['cfg']['header']['title'] ?? '') === 'GST INVOICE'
    && ($c->call('GET', "/sales/invoices/$invId/print?tpl=std")['json']['data']['design']['id'] ?? '') === 'std'));
$r = $c->call('POST', "/settings/templates/$ptid/duplicate", ['doc' => 'invoice']);
check('a template can be copied (and Standard too)', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM print_templates WHERE tenant_id = ?', [$tA]) === 2
    && $c->call('POST', '/settings/templates/std/duplicate', ['doc' => 'invoice'])['status'] === 200);
$b3 = new J($base); $b3->session(); $b3->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $b3->session();
check('another company cannot see, edit, default or delete it', $b3->call('GET', "/settings/templates/design?doc=invoice&id=$ptid")['status'] === 404
    && $b3->call('POST', '/settings/templates/save', ['doc' => 'invoice', 'id' => $ptid, 'name' => 'Hack', 'cfg' => $mine])['status'] === 404
    && $b3->call('POST', "/settings/templates/$ptid/default", ['doc' => 'invoice'])['status'] === 404 && $b3->call('POST', "/settings/templates/$ptid/delete", ['doc' => 'invoice'])['status'] === 404
    && (string)DB::val('SELECT name FROM print_templates WHERE id = ?', [$ptid]) === 'Premium');
$c->call('POST', "/settings/templates/$ptid/delete", ['doc' => 'invoice']);
$pr = $invId ? $c->call('GET', "/sales/invoices/$invId/print") : null;
check('deleting the default hands printing back to Standard', !$pr || ($pr['json']['data']['design']['id'] ?? '') === 'std');
check('a document with no total row (delivery note) has no totals section to design', !array_filter($c->call('GET', '/settings/templates/design?doc=delivery_note')['json']['data']['cfg']['blocks'] ?? [], fn($x) => $x['type'] === 'totals'));
DB::run('DELETE FROM print_templates WHERE tenant_id = ?', [$tA]);

echo "The app is the front door\n";
$sp = ['/' => '/app/', '/dashboard' => '/app/dashboard', '/customers/12/edit' => '/app/customers/12/edit', '/settings/formdesign' => '/app/settings/formdesign', '/forgot-password' => '/app/forgot-password',
    '/admin' => '/app/admin', '/purchase/rates' => '/app/purchase/rates', '/items/import' => '/app/items/import'];
$bad = []; foreach ($sp as $from => $to) if (Core\Spa::redirectFor($from) !== $to) $bad[] = $from;
check('classic screen addresses map to their twin in the app', !$bad);
$keep = ['/items/export', '/items/import/template', '/reports/stock/export/csv', '/lookup/items', '/company/logo', '/admin/system/backup', '/labels/barcode', '/stock/batches/4/barcode', '/search', '/logout', '/api/v1/session'];
$bad = []; foreach ($keep as $path) if (Core\Spa::redirectFor($path) !== null) $bad[] = $path;
check('files and lookups are never sent to the app', !$bad);
$pr = ['/purchase/orders/5/print' => '/app/purchase/orders/5/print', '/sales/invoices/5/print' => '/app/sales/invoices/5/print', '/sales/deliveries/3/print' => '/app/sales/deliveries/3/print', '/sales/deliveries/3/voucher' => '/app/sales/deliveries/3/voucher',
    '/stock/adjustments/3/voucher' => '/app/stock/adjustments/3/voucher', '/stock/transfers/3/voucher' => '/app/stock/transfers/3/voucher', '/stock/transfers/3/challan' => '/app/stock/transfers/3/challan', '/labels/print' => '/app/labels/print'];
$bad = []; foreach ($pr as $from => $to) if (Core\Spa::redirectFor($from) !== $to) $bad[] = $from;
check('printouts are pages of the app too', !$bad && Core\Spa::redirectFor('/reports/stock-summary', ['print' => '1']) === '/app/reports/stock-summary');

// print data over JSON: letterhead and ready-made line amounts
$po = (int)DB::val('SELECT id FROM purchase_orders WHERE tenant_id = ? LIMIT 1', [$tA]);
if ($po) {
    $r = $c->call('GET', "/purchase/orders/$po/print");
    check('the purchase-order printout data has the letterhead, the layout and amounts per line', ($r['json']['data']['head']['name'] ?? '') !== '' && isset($r['json']['data']['tpl']['accent']) && (!$r['json']['data']['items'] || isset($r['json']['data']['items'][0]['tot'], $r['json']['data']['items'][0]['net'])));
}
$inv = (int)DB::val('SELECT id FROM sales_invoices WHERE tenant_id = ? LIMIT 1', [$tA]);
if ($inv) {
    $r = $c->call('GET', "/sales/invoices/$inv/print");
    check('the invoice printout data has the letterhead and discount / tax / total per line', isset($r['json']['data']['head']['name']) && (!$r['json']['data']['items'] || isset($r['json']['data']['items'][0]['dsc'], $r['json']['data']['items'][0]['tx'], $r['json']['data']['items'][0]['tot'])));
}
$r = $c->call('GET', '/reports/stock-summary?print=1');
check('a report for printing answers with its rows, company and print date', $r['status'] === 200 && isset($r['json']['data']['rows'], $r['json']['data']['company'], $r['json']['data']['printed']));
$r = $c->call('GET', '/labels/print?type=items&template=b:item-50x25&n[' . (int)DB::val('SELECT id FROM item_variants WHERE tenant_id = ? LIMIT 1', [$tA]) . ']=2');
check('labels for printing answer with the template, the page layout and the filled values', $r['status'] === 200 && isset($r['json']['data']['tpl']['elements'], $r['json']['data']['page']['mode']) && count($r['json']['data']['labels'] ?? []) === 2);
$r = $c->call('POST', '/logout', []);
check('signing out over JSON answers success (not an error) and ends the session', $r['status'] === 200 && ($r['json']['ok'] ?? false) === true && $c->call('GET', '/customers')['status'] === 401);
$rootNoFollow = (function () use ($base) { $ch = curl_init($base . '/'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true]); $h = (string)curl_exec($ch); $st = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return [$st, $h]; })();
check('the site address itself opens the app', $rootNoFollow[0] === 302 && (bool)preg_match('~Location: .*/app/\r?\n~i', $rootNoFollow[1]));

echo "The React shell\n";
$spa = (new Client($base))->get('/app/customers/12');
check('/app/... serves the built React page (any deep link), without needing a session', $spa['status'] === 200 && str_contains($spa['body'], 'id="root"') && str_contains($spa['body'], '/ui/assets/'));
check('the app page has the product icon, the product name as its title and the brand colours from the first paint (so the sign-in page is never unstyled)', str_contains($spa['body'], 'rel="icon"') && str_contains($spa['body'], '<title>' . config('name') . '</title>') && str_contains($spa['body'], 'id="brand-vars">:root{--brand:#') && str_contains($spa['body'], 'name="app-title"') && str_contains($spa['body'], '/assets/js/charts.js'));
check('its built files are reachable', preg_match('~src="(/ui/assets/[^"]+\.js)"~', $spa['body'], $m) === 1 && (new Client($base))->get($m[1])['status'] === 200);

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
