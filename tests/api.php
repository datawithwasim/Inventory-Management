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
check('the supplier item schema types our-item as a searchable item picker', ($r['json']['schema']['fields']['variant_id']['type'] ?? '') === 'variant' && ($r['json']['schema']['fields']['supplier_id']['source'] ?? '') === 'suppliers');
$r = $a->call('GET', '/columns/supplier_items');
check('column choices come for a list', $r['status'] === 200 && isset($r['json']['options']['our_item']) && is_array($r['json']['visible']));

echo "Items through the API\n";
$unit = (int)DB::val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]);
$r = $a->call('POST', '/items', ['name' => 'Api Silk', 'item_type' => 'fabric', 'unit_id' => $unit, 'track_batch' => 1, 'reorder_level' => '20',
    'variants' => [['name' => 'Red', 'sku' => 'ASK-R', 'cost_price' => '300', 'sale_price' => '450'], ['name' => 'Blue', 'sku' => 'ASK-B', 'sale_price' => '460']], 'cf' => (object)[]]);
$iid = (int)DB::val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Api Silk'", [$tA]);
check('an item with two variants saves from JSON (roll tracking on)', $r['status'] === 200 && $iid > 0 && (int)DB::val('SELECT COUNT(*) FROM item_variants WHERE item_id = ?', [$iid]) === 2 && (int)DB::val('SELECT track_batch FROM items WHERE id = ?', [$iid]) === 1);
$r = $a->call('POST', '/items', ['name' => 'Api Silk 2', 'unit_id' => $unit, 'variants' => [['sku' => 'ASK-R']]]);
check('a duplicate SKU is refused with the reason', $r['status'] === 422 && str_contains(json_encode($r['json']['messages']), 'ASK-R') && !DB::val("SELECT 1 FROM items WHERE tenant_id = ? AND name = 'Api Silk 2'", [$tA]));
$r = $a->call('GET', "/items/$iid");
check('the item page data has variants with stock, supplier rates and history', $r['status'] === 200 && count($r['json']['data']['variants']) === 2 && array_key_exists('rates', $r['json']['data']) && array_key_exists('history', $r['json']['data']) && isset($r['json']['data']['variants'][0]['batches']));
$vA = (int)DB::val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'ASK-R'", [$tA]);
$r = $a->call('POST', '/items', ['name' => 'Api Set', 'unit_id' => $unit, 'is_bundle' => 1, 'variants' => [['sku' => 'ASET', 'sale_price' => '900']], 'components' => [['variant_id' => $vA, 'qty' => '2']]]);
$bid = (int)DB::val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Api Set'", [$tA]);
check('a bundle with a component saves', $r['status'] === 200 && (int)DB::val('SELECT COUNT(*) FROM bundle_components WHERE bundle_item_id = ?', [$bid]) === 1);
$r = $a->call('GET', "/items/$iid/edit");
check('the edit form data carries what the form needs (item, variants, lock flag, pickable items)', ($r['json']['data']['item']['name'] ?? '') === 'Api Silk' && count($r['json']['data']['variants']) === 2 && array_key_exists('locked', $r['json']['data']) && is_array($r['json']['data']['pickable']));
$r = $a->call('GET', '/items?q=Silk&low=');
check('the item list filters by search and pages', in_array('Api Silk', array_column($r['json']['data']['rows'], 'name'), true) && isset($r['json']['data']['pages']));
check('the session names the stock movement types for the history table', ($a->session()['json']['stockTypes']['purchase'] ?? '') !== '');
$r = $a->call('POST', "/items/$iid/delete");
check('an item used inside a bundle cannot be deleted (and the reason comes back)', $r['status'] === 422 && DB::val('SELECT 1 FROM items WHERE id = ?', [$iid]) && str_contains(json_encode($r['json']['messages']), 'bundle'));
$a->call('POST', "/items/$bid/delete");
$r = $a->call('POST', "/items/$iid/delete");
check('an item without history, once out of the bundle, can be deleted', $r['status'] === 200 && !DB::val('SELECT 1 FROM items WHERE id = ?', [$iid]));

echo "Isolation and secrets\n";
$b = new J($base); $b->session(); $b->call('POST', '/login', ['email' => $emB, 'password' => 'Password123']); $b->session();
check('another company gets a 404 for it, on the page data and the record data', $b->call('GET', "/customers/$cid")['status'] === 404 && $b->call('GET', "/record/customer/$cid")['status'] === 404);
check('and cannot change it', $b->call('POST', "/customers/$cid", ['name' => 'Hacked'])['status'] === 404 && (string)DB::val('SELECT name FROM customers WHERE id = ?', [$cid]) === 'Rani Textiles');
$r = $a->call('GET', '/users');
check('user lists never include password hashes', $r['status'] === 200 && !str_contains($r['raw'], 'password_hash') && !str_contains($r['raw'], '$2y$'));
$a->call('POST', '/logout', []);
check('signing out ends the session', $a->call('GET', '/customers')['status'] === 401);
check('a plain page request still works and is still HTML (nothing changed for the old UI)', (new Client($base))->get('/login')['status'] === 200 && str_contains((new Client($base))->get('/login')['body'], '<form'));

echo "The React shell\n";
$spa = (new Client($base))->get('/app/customers/12');
check('/app/... serves the built React page (any deep link), without needing a session', $spa['status'] === 200 && str_contains($spa['body'], 'id="root"') && str_contains($spa['body'], '/ui/assets/'));
check('its built files are reachable', preg_match('~src="(/ui/assets/[^"]+\.js)"~', $spa['body'], $m) === 1 && (new Client($base))->get($m[1])['status'] === 200);

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
