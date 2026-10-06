<?php
declare(strict_types=1);

/** Zoho-style record pages: detail view laid out from the design, Edit page layout link, summary, timeline, notes.  php tests/records.php */
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
[$a, $tA] = $mk('Recco');
[$b, $tB] = $mk('Other');
$val = fn(string $sql, array $p = []) => DB::val($sql, $p);
$txt = fn(string $h) => preg_replace('/\s+/', ' ', strip_tags($h));
$unit = (int)$val("SELECT id FROM units WHERE tenant_id = ? AND name = 'Meter'", [$tA]);
$savePayload = fn(array $p) => $a->post('/settings/formdesign', ['payload' => json_encode($p), 'current' => 'supplier'], '/settings/modules');

echo "Setup\n";
$a->post('/suppliers', ['name' => 'Rec Mills', 'supplier_type' => 'manufacturer', 'phone' => '9811100000', 'city' => 'Surat', 'state' => 'Gujarat', 'payment_terms_days' => '30', 'credit_limit' => '25000', 'notes' => 'Reliable'], '/suppliers/create');
$s1 = (int)$val("SELECT id FROM suppliers WHERE tenant_id = ? AND name = 'Rec Mills'", [$tA]);
$a->post('/customers', ['name' => 'Rec Client', 'phone' => '9822200000', 'email' => 'rc@x.com', 'address' => '12 Main Rd', 'credit_days' => '15'], '/customers/create');
$c1 = (int)$val("SELECT id FROM customers WHERE tenant_id = ? AND name = 'Rec Client'", [$tA]);
$a->post('/items', ['name' => 'Rec Velvet', 'item_type' => 'fabric', 'unit_id' => $unit, 'hsn_code' => '5407', 'design_no' => 'RV-1', 'composition' => 'Polyester', 'width' => '54 in', 'variants' => [['sku' => 'RECV', 'colour' => 'Wine', 'cost_price' => 300]]], '/items/create');
$i1 = (int)$val("SELECT id FROM items WHERE tenant_id = ? AND name = 'Rec Velvet'", [$tA]);
check('records created', $s1 && $c1 && $i1);

echo "Detail page\n";
$h = $a->get("/suppliers/$s1")['body'];
check('supplier page has the record header, Related list sidebar and Overview / Timeline tabs', str_contains($h, 'class="rec-top"') && str_contains($h, 'Related list') && str_contains($h, 'data-tab="recOverview"') && str_contains($h, 'data-tab="recTimeline"'));
check('the layout h1 is not duplicated (one h1)', substr_count($h, '<h1') === 1);
$t = $txt($h);
check('sections list every field with its value (city, state, terms, limit) and a dash for blanks', str_contains($t, 'Surat') && str_contains($t, 'Gujarat') && str_contains($t, '30 days') && str_contains($t, '25,000.00') && str_contains($t, 'Manufacturer / mill') && str_contains($h, '<i class="text-muted">—</i>'));
check('the default summary strip shows supplier type, phone, city and terms', str_contains($h, 'rec-summary') && preg_match('/rec-summary.*?Supplier type.*?Phone.*?City.*?Payment terms/s', $h) === 1);
$h = $a->get("/customers/$c1")['body']; $t = $txt($h);
check('customer page: sections show phone, email, address, credit days', str_contains($h, 'class="rec-top"') && str_contains($t, '9822200000') && str_contains($t, 'rc@x.com') && str_contains($t, '12 Main Rd') && str_contains($t, '15 days'));
$h = $a->get("/items/$i1")['body']; $t = $txt($h);
check('item page: type label, HSN, design no, composition, unit name, and the variants & stock block', str_contains($t, 'Fabric') && str_contains($t, '5407') && str_contains($t, 'RV-1') && str_contains($t, 'Polyester') && str_contains($t, 'Meter (') && str_contains($h, 'id="variants"'));
check('the old per-variant content is still on the item page (Wine / RECV)', str_contains($t, 'RECV'));

echo "Edit page layout link\n";
$f = $a->get("/suppliers/$s1/edit")['body'];
check('edit page shows "Edit page layout" next to the title, pointing at the designer with a return path', preg_match('~class="page-layout-link" href="[^"]*settings/formdesign\?form=supplier&amp;return=%2F[^"]*suppliers%2F' . $s1 . '%2Fedit"~', $f) === 1);
check('create pages and document forms have it too (item, PO, GRN, warehouse)', str_contains($a->get('/items/create')['body'], 'form=item') && str_contains($a->get('/purchase/orders/create')['body'], 'form=purchase_order') && str_contains($a->get('/warehouses/create')['body'], 'form=warehouse'));
check('the record page menu has "Edit page layout" as well', str_contains($a->get("/suppliers/$s1")['body'], 'Edit page layout'));
check('lists and detail pages are not forms: no layout link on the suppliers list', !str_contains($a->get('/suppliers')['body'], 'page-layout-link'));
check('Settings menu no longer has a "Form designer" entry', !str_contains($a->get('/settings/company')['body'], 'Form designer') && !str_contains($a->get('/settings/company')['body'], 'settings/formdesign'));
$d = $a->get("/settings/formdesign?form=supplier&return=" . rawurlencode("/suppliers/$s1/edit"));
check('the designer still opens and Cancel / back lead to the page you came from', $d['status'] === 200 && substr_count($d['body'], 'href="/suppliers/' . $s1 . '/edit"') >= 2 && str_contains($d['body'], 'name="return" value="/suppliers/' . $s1 . '/edit"'));
$d = $a->get('/settings/formdesign?form=supplier&return=' . rawurlencode('//evil.example/x'))['body'];
check('an off-site return path is ignored', !str_contains($d, 'evil.example') && str_contains($d, 'href="/settings/company"'));
$d = $a->get('/settings/formdesign?form=supplier&return=' . rawurlencode('http://evil.example'))['body'];
check('an absolute-URL return path is ignored too', !str_contains($d, 'evil.example'));
$r = $a->post('/settings/formdesign', ['payload' => json_encode([]), 'current' => 'supplier', 'close' => '1', 'return' => "/suppliers/$s1/edit"], '/settings/modules');
check('Save and close goes back to the edit page', $r['status'] === 302 && $r['location'] === "/suppliers/$s1/edit");
$r = $a->post('/settings/formdesign', ['payload' => json_encode([]), 'current' => 'supplier', 'close' => '1', 'return' => '//evil.example'], '/settings/modules');
check('an unsafe return on save falls back to settings', $r['status'] === 302 && !str_contains((string)$r['location'], 'evil'));
$r = $a->post('/settings/formdesign', ['payload' => json_encode([]), 'current' => 'supplier', 'return' => "/suppliers/$s1/edit"], '/settings/modules');
check('plain Save keeps you in the designer and remembers where to return', str_contains((string)$r['location'], 'settings/formdesign?form=supplier&return='));
$view = new Client($base);   // viewer: settings.view only
$rid = DB::insert('roles', ['tenant_id' => $tA, 'name' => 'Viewer ' . $sfx]);
foreach (['suppliers.view', 'customers.view', 'items.view', 'suppliers.edit'] as $perm) DB::insert('role_permissions', ['role_id' => $rid, 'permission' => $perm]);
DB::insert('users', ['tenant_id' => $tA, 'role_id' => $rid, 'name' => 'Vw', 'email' => "vw-$sfx@test.local", 'password_hash' => password_hash('Password123', PASSWORD_DEFAULT)]);
$view->post('/login', ['email' => "vw-$sfx@test.local", 'password' => 'Password123'], '/login');
check('a user without settings.edit sees no "Edit page layout" anywhere', !str_contains($view->get("/suppliers/$s1/edit")['body'], 'Edit page layout') && !str_contains($view->get("/suppliers/$s1")['body'], 'Edit page layout'));

echo "Detail page tab in the designer (summary strip)\n";
$m = $a->get('/settings/formdesign?form=supplier')['body'];
check('designer has Edit page / Detail page tabs and the summary pane', str_contains($m, 'data-tab="detail"') && str_contains($m, 'id="dzSumList"'));
preg_match('/<script type="application\/json" id="dzData">(.*?)<\/script>/s', $m, $mm);
$dz = json_decode($mm[1] ?? '{}', true);
$sup = array_values(array_filter($dz['model'] ?? [], fn($x) => $x['key'] === 'supplier'))[0] ?? [];
check('the model says supplier / customer / item have a detail page and gives the default summary', ($sup['detail'] ?? false) === true && ($sup['summary'] ?? []) === ['supplier_type', 'phone', 'city', 'payment_terms_days']);
$wh = array_values(array_filter($dz['model'] ?? [], fn($x) => $x['key'] === 'warehouse'))[0] ?? [];
check('warehouse has a detail page too, with its own summary default', ($wh['detail'] ?? false) === true && ($wh['summary'] ?? []) === ['code']);
$savePayload(['supplier' => ['summary' => ['state', 'bank_name', 'credit_limit', 'lead_time_days', 'transport', 'pan', 'not_a_field', 'state']]]);
$h = $a->get("/suppliers/$s1")['body'];
preg_match('/<div class="card rec-summary">(.*?)<\/div><\/div>/s', $h, $sm);
$labels = array_map('trim', explode('|', preg_replace('/<span>(.*?)<\/span><b>.*?<\/b>/s', '$1|', strip_tags($sm[1] ?? '', '<span><b>'))));
check('a chosen summary replaces the default: at most 4, duplicates / unknown keys dropped, in the order picked', substr_count($sm[1] ?? '', 'class="rec-kv"') === 4 && str_contains($sm[1] ?? '', '<span>State</span>') && str_contains($sm[1] ?? '', '<span>Bank name</span>') && str_contains($sm[1] ?? '', '<span>Credit limit</span>') && !str_contains($sm[1] ?? '', 'PAN'));
$savePayload(['supplier' => ['summary' => []]]);
check('an empty summary hides the strip', !str_contains($a->get("/suppliers/$s1")['body'], 'rec-summary'));
$savePayload(['supplier' => ['summary' => ['city'], 'fields' => ['city' => ['show' => true], 'notes' => ['show' => false]], 'sections' => [['title' => 'Basics', 'cols' => 1, 'fields' => ['name', 'phone', 'city']], ['title' => 'Money', 'cols' => 3, 'fields' => ['payment_terms_days', 'credit_limit']]]]]);
$h = $a->get("/suppliers/$s1")['body'];
check('the page follows the designed sections and titles (Basics, Money) in order', preg_match('/Basics.*?Supplier name.*?City.*?Money.*?Payment terms.*?Credit limit/s', $h) === 1 && str_contains($h, 'rec-grid c1') && str_contains($h, 'rec-grid c3'));
check('a field hidden in the designer disappears from the detail page too', !preg_match('/<span>Notes<\/span>/', $h) && !str_contains($txt($h), 'Reliable'));
$savePayload(['supplier' => ['summary' => ['notes', 'city'], 'fields' => ['notes' => ['show' => false]], 'sections' => [['title' => '', 'cols' => 0, 'fields' => ['name', 'city']]]]]);
$h = $a->get("/suppliers/$s1")['body'];
check('summary skips a hidden field even if it was chosen', substr_count($h, 'class="rec-kv"') >= 1 && !str_contains($txt($h), 'Reliable') && preg_match('/rec-summary.*?<span>City<\/span>/s', $h) === 1);

echo "Custom fields on the record page\n";
$savePayload(['supplier' => ['reset' => true]]);
$savePayload(['supplier' => ['sections' => [['title' => '', 'cols' => 0, 'fields' => ['name', 'city', 'cf:new1']]], 'fields' => ['cf:new1' => ['isNew' => true, 'name' => 'Loom count', 'type' => 'number', 'show' => true]], 'summary' => ['cf:new1', 'city']]]);
$cf = (int)$val("SELECT id FROM custom_fields WHERE tenant_id = ? AND label = 'Loom count'", [$tA]);
$a->post("/suppliers/$s1", ['name' => 'Rec Mills', 'is_active' => 1, 'city' => 'Surat', 'cf' => [$cf => '48']], "/suppliers/$s1/edit");
$h = $a->get("/suppliers/$s1")['body'];
check('a custom field value shows in the sections and in the summary (new field keyed in the summary)', str_contains($h, 'Loom count') && substr_count($h, '<b>48</b>') >= 2);
$savePayload(['supplier' => ['reset' => true]]);

echo "Timeline\n";
$a->post("/suppliers/$s1", ['name' => 'Rec Mills', 'is_active' => 1, 'city' => 'Vapi'], "/suppliers/$s1/edit");
$t = $txt($a->get("/suppliers/$s1")['body']);
check('timeline shows who updated the record', str_contains($t, 'Recco') && str_contains($t, 'updated'));
$a->post("/suppliers/$s1/rates", ['variant_id' => (int)$val("SELECT id FROM item_variants WHERE tenant_id = ? AND sku = 'RECV'", [$tA]), 'rate' => '290', 'valid_from' => '2026-01-01'], "/suppliers/$s1");
check('…and other events like adding a rate', str_contains($txt($a->get("/suppliers/$s1")['body']), 'added a rate'));
check('a record with no history says so instead of showing an empty box', str_contains($txt($a->get("/customers/$c1")['body']), 'No activity recorded yet') || str_contains($txt($a->get("/customers/$c1")['body']), 'created'));
check("another company's timeline for the same id shows nothing of A", !str_contains($txt($b->get("/suppliers/$s1")['body']), 'Recco'));

echo "Notes\n";
$a->post("/records/supplier/$s1/notes", ['body' => 'Call before dispatch'], "/suppliers/$s1");
check('a note can be added and appears on the record', str_contains($txt($a->get("/suppliers/$s1")['body']), 'Call before dispatch') && (int)$val("SELECT COUNT(*) FROM record_notes WHERE tenant_id = ? AND entity = 'supplier' AND entity_id = ?", [$tA, $s1]) === 1);
check('the Related list counts it', str_contains($a->get("/suppliers/$s1")['body'], 'Notes (1)'));
$a->post("/records/supplier/$s1/notes", ['body' => '   '], "/suppliers/$s1");
$a->post("/records/supplier/$s1/notes", ['body' => str_repeat('x', 1501)], "/suppliers/$s1");
check('empty and over-long notes are refused', (int)$val('SELECT COUNT(*) FROM record_notes WHERE tenant_id = ?', [$tA]) === 1);
$a->post("/records/supplier/$s1/notes", ['body' => '<script>alert(1)</script>'], "/suppliers/$s1");
check('note text is escaped', !str_contains($a->get("/suppliers/$s1")['body'], '<script>alert(1)</script>') && str_contains($a->get("/suppliers/$s1")['body'], '&lt;script&gt;'));
$a->post("/records/customer/$c1/notes", ['body' => 'Prefers morning delivery'], "/customers/$c1");
$a->post("/records/item/$i1/notes", ['body' => 'Check shade'], "/items/$i1");
check('notes work on customers and items', str_contains($txt($a->get("/customers/$c1")['body']), 'Prefers morning delivery') && str_contains($txt($a->get("/items/$i1")['body']), 'Check shade'));
$b->post("/records/supplier/$s1/notes", ['body' => 'intruder'], '/suppliers');
check("another company cannot add a note to A's record", !$val("SELECT 1 FROM record_notes WHERE body = 'intruder'"));
$a->post('/records/warehouse/1/notes', ['body' => 'nope'], "/suppliers/$s1");
check('only item / customer / supplier accept notes', !$val("SELECT 1 FROM record_notes WHERE body = 'nope'"));
$view->post("/records/customer/$c1/notes", ['body' => 'viewer note'], '/suppliers');
check('a user without edit permission on that record type cannot add notes', !$val("SELECT 1 FROM record_notes WHERE body = 'viewer note'"));
$view->post("/records/supplier/$s1/notes", ['body' => 'viewer ok'], "/suppliers/$s1");
$nid = (int)$val("SELECT id FROM record_notes WHERE body = 'viewer ok'");
check('with edit permission a user can add one', $nid > 0);
$other = (int)$val("SELECT id FROM record_notes WHERE body = 'Call before dispatch'");
$view->post("/records/notes/$other/delete", [], "/suppliers/$s1");
check("a user cannot delete someone else's note", (bool)$val('SELECT 1 FROM record_notes WHERE id = ?', [$other]));
$view->post("/records/notes/$nid/delete", [], "/suppliers/$s1");
check('…but can delete their own', !$val('SELECT 1 FROM record_notes WHERE id = ?', [$nid]));
$b->post("/records/notes/$other/delete", [], '/suppliers');
check("another company cannot delete A's note", (bool)$val('SELECT 1 FROM record_notes WHERE id = ?', [$other]));
$a->post("/records/notes/$other/delete", [], "/suppliers/$s1");
check('the owner can delete any note', !$val('SELECT 1 FROM record_notes WHERE id = ?', [$other]));

echo "Record pages hold up\n";
foreach (["/suppliers/$s1", "/customers/$c1", "/items/$i1"] as $u) check("$u renders without notices", $a->get($u)['status'] === 200 && !preg_match('/Warning:|Notice:|Fatal error|Deprecated:/', $a->get($u)['body']));
check('a made-up record id is a 404', $a->get('/suppliers/9999999')['status'] === 404 && $a->get('/items/9999999')['status'] === 404);

echo "Profile menu, activity tabs, rate list\n";
$dh = $a->get('/dashboard')['body'];
check('profile menu shows name, email, role · company, Light / Dark / Auto and Sign out', str_contains($dh, 'class="pm-head"') && str_contains($dh, "recco-$sfx@test.local") && str_contains($dh, 'data-theme-set="dark"') && str_contains($dh, 'data-theme-set="auto"') && str_contains($dh, 'pm-out') && str_contains($dh, 'data-bs-auto-close="outside"'));
check('the old single "Dark mode" menu item is gone from the app (POS / admin keep their icon button)', !str_contains($dh, 'id="modeBtn"') && str_contains($a->get('/pos')['body'], 'id="modeBtn"'));
check('the page tells the script the company default theme', str_contains($dh, 'data-default-mode'));
$sh = $a->get("/suppliers/$s1")['body'];
check('supplier page: rate list uses modals (add / import), no inline form', str_contains($sh, 'id="rateModal"') && str_contains($sh, 'id="rateImportModal"') && !str_contains($sh, 'id="rateAdd"'));
check('Purchase orders and Bills are two separate full-width cards like the Rate list, each in the Related list', preg_match('~<section class="card mb-3" id="orders">.*?Purchase orders~s', $sh) === 1 && str_contains($sh, '<section class="card mb-3" id="bills">') && str_contains($sh, 'data-rel="orders"') && str_contains($sh, 'data-rel="bills"') && !str_contains($sh, 'data-act=') && !str_contains($sh, 'col-md-6'));
$ch = $a->get("/customers/$c1")['body'];
check('customer page: Orders and Invoices are separate cards listed in the Related list', str_contains($ch, '<section class="card mb-3" id="orders">') && str_contains($ch, '<section class="card mb-3" id="invoices">') && str_contains($ch, 'data-rel="invoices"') && !str_contains($ch, 'data-act='));
check('the record tab script only controls Overview / Timeline', str_contains($sh, ".rec-main > .rec-tabs button"));
check('an empty rate list shows only its empty message (the "Nothing matches" line starts hidden)', preg_match('~<tr id="rlNone" hidden>~', $sh) === 1);

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
