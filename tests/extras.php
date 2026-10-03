<?php
declare(strict_types=1);

/**
 * End-to-end test for Phase 8 (barcode labels, backup, security hardening).
 *   php tests/extras.php http://127.0.0.1:8099        # against a throw-away database
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\Code128;
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
[$a, $tA] = $mk('Labeler');
[$b, $tB] = $mk('Rival');
$unit = fn(string $n) => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$GLOBALS['tA'], $n]);
$main = (int)DB::val('SELECT id FROM warehouses WHERE tenant_id = ? AND is_default = 1', [$tA]);

echo "Barcode encoder\n";
$svg = Code128::svg('A');
// Start B (104) + 'A'(33) + checksum (104 + 33*1) % 103 = 34 + stop → 4 symbols; each symbol has 3 bars (stop has 4)
check('Code 128 for "A": 3+3+3+4 = 13 bars', substr_count($svg, '<rect') === 13);
check('empty / non-printable input gives no barcode', Code128::svg('') === '' && Code128::svg("\x01\x02") === '');
check('text is escaped in the aria-label', !str_contains(Code128::svg('"><script>'), '<script>'));
$long = Code128::svg(str_repeat('X', 30));
check('longer text makes a wider barcode', preg_match('/viewBox="0 0 (\d+)/', $long, $m1) && preg_match('/viewBox="0 0 (\d+)/', $svg, $m2) && (int)$m1[1] > (int)$m2[1]);

echo "Labels\n";
$a->post('/items', ['name' => 'Velvet', 'unit_id' => $unit('Meter'), 'track_batch' => 1, 'variants' => [['sku' => 'VEL', 'cost_price' => 400, 'sale_price' => 700]]], '/items/create');
$a->post('/items', ['name' => 'Side Table', 'unit_id' => $unit('Piece'), 'variants' => [['sku' => 'TBL', 'barcode' => '8901234', 'cost_price' => 2500, 'sale_price' => 4200]]], '/items/create');
$b->post('/items', ['name' => 'Rival Lamp', 'unit_id' => (int)DB::val('SELECT id FROM units WHERE tenant_id = ? AND name = ?', [$tB, 'Piece']), 'variants' => [['sku' => 'LMP', 'cost_price' => 1, 'sale_price' => 2]]], '/items/create');
[$vel, $tbl] = array_map(fn($s) => (int)DB::val('SELECT id FROM item_variants WHERE tenant_id = ? AND sku = ?', [$tA, $s]), ['VEL', 'TBL']);
$lamp = (int)DB::val('SELECT id FROM item_variants WHERE tenant_id = ? AND sku = ?', [$tB, 'LMP']);
DB::insert('locations', ['tenant_id' => $tA, 'warehouse_id' => $main, 'code' => 'R-07']);
$loc = (int)DB::val("SELECT id FROM locations WHERE tenant_id = ? AND code = 'R-07'", [$tA]);
$a->post('/stock/adjustments', ['warehouse_id' => $main, 'reason' => 'opening', 'lines' => [['variant_id' => $vel, 'qty' => 50, 'batch_id' => 'new', 'lot_no' => 'L1', 'location_id' => $loc]]], '/stock/adjustments/create');
$roll = (int)DB::val('SELECT id FROM batches WHERE tenant_id = ?', [$tA]);
$batchNo = (string)DB::val('SELECT batch_no FROM batches WHERE id = ?', [$roll]);

check('label picker (items) opens and lists items', ($r = $a->get('/labels'))['status'] === 200 && str_contains($r['body'], 'Side Table') && str_contains($r['body'], '8901234'));
check('item without barcode falls back to its SKU', str_contains($a->get('/labels')['body'], '<code>VEL</code>'));
check('roll picker lists the roll with its batch number', ($r = $a->get('/labels?type=rolls'))['status'] === 200 && str_contains($r['body'], $batchNo));
check('other company\'s items are not offered', !str_contains($a->get('/labels')['body'], 'Rival Lamp'));
$r = $a->get("/labels/print?type=items&size=50x25&n[$tbl]=3&n[$vel]=1");
check('item labels: 4 labels (3 + 1), barcode SVGs, price shown', $r['status'] === 200 && substr_count($r['body'], 'class="lbl"') === 4 && substr_count($r['body'], '<svg') === 4 && str_contains($r['body'], '4,200'));
check('item label carries the barcode number', str_contains($r['body'], '8901234'));
$r = $a->get("/labels/print?type=rolls&size=65x33&n[$roll]=2");
check('roll labels: batch no., roll size and rack are printed', substr_count($r['body'], 'class="lbl"') === 2 && str_contains($r['body'], $batchNo) && str_contains($r['body'], 'R-07') && str_contains($r['body'], '50'));
check('A4 sheet size sets an A4 page; roll printer sets label-size page', str_contains($r['body'], 'size: A4') && str_contains($a->get("/labels/print?n[$tbl]=1&size=50x25")['body'], 'size: 50mm 25mm'));
check('copies are capped (999 → 200)', substr_count($a->get("/labels/print?n[$tbl]=999")['body'], 'class="lbl"') === 200);
check('negative / junk copies print nothing', str_contains($a->get("/labels/print?n[$tbl]=-5&n[abc]=2")['body'], 'No labels selected'));
check('another company\'s item id prints nothing', str_contains($a->get("/labels/print?n[$lamp]=2")['body'], 'No labels selected') && !str_contains($a->get("/labels/print?n[$lamp]=2")['body'], 'Rival'));
check('unknown size falls back safely', $a->get("/labels/print?n[$tbl]=1&size=9x9")['status'] === 200 && str_contains($a->get("/labels/print?n[$tbl]=1&size=9x9")['body'], '50mm'));
check('labels need a login', (new Client($base))->get('/labels')['status'] === 302);
check('sidebar link to labels', str_contains($a->get('/dashboard')['body'], 'bi-upc'));

echo "Backup\n";
$sysPage = $admin->get('/admin/system');
check('Super Admin system page has the backup button', str_contains($sysPage['body'], 'admin/system/backup') && str_contains($sysPage['body'], 'Download backup'));
check('backup cannot be triggered with GET', $admin->get('/admin/system/backup')['status'] === 404 || $admin->get('/admin/system/backup')['status'] === 405);
check('a company user cannot download a backup', in_array($a->post('/admin/system/backup', [], '/dashboard')['status'], [302, 403, 404], true));
$noCsrf = $admin->req('POST', '/admin/system/backup', []);
check('backup without CSRF token is refused', !str_contains($noCsrf['body'], 'CREATE TABLE'));
$dump = $admin->post('/admin/system/backup', [], '/admin/system');
check('backup downloads as an .sql attachment', $dump['status'] === 200 && str_contains($dump['headers'], 'attachment') && str_contains($dump['headers'], '.sql'));
check('dump contains structure and data for this test company', str_contains($dump['body'], 'CREATE TABLE `items`') && str_contains($dump['body'], 'Side Table') && str_contains($dump['body'], 'INSERT INTO `stock_ledger`'));
$scratch = 'inv_restore_' . $sfx;
$root = fn(string $sql) => shell_exec('mysql -uroot -e ' . escapeshellarg($sql) . ' 2>&1');
$root("CREATE DATABASE `$scratch`");
$tmp = tempnam(sys_get_temp_dir(), 'dump');
file_put_contents($tmp, $dump['body']);
$out = shell_exec("mysql -uroot $scratch < " . escapeshellarg($tmp) . ' 2>&1');
check('dump restores into an empty database without errors', trim((string)$out) === '');
$tables = array_column(DB::all('SELECT table_name AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\''), 'n');
$same = true;
$diff = [];
foreach ($tables as $t) {
    if ($t === 'audit_logs') continue; // the backup itself writes one audit row after the dump
    $orig = (int)DB::val("SELECT COUNT(*) FROM `$t`");
    $copy = (int)trim((string)shell_exec('mysql -uroot -N ' . $scratch . ' -e ' . escapeshellarg("SELECT COUNT(*) FROM `$t`") . ' 2>&1'));
    if ($orig !== $copy) { $same = false; $diff[] = "$t {$orig}≠{$copy}"; }
}
check('every table has the same row count after restore' . ($diff ? ' — ' . implode(', ', $diff) : '') . ' (' . count($tables) . ' tables)', $same && count($tables) > 20);
$o = trim((string)shell_exec('mysql -uroot -N ' . $scratch . ' -e ' . escapeshellarg("SELECT name FROM items WHERE tenant_id = $tA ORDER BY id") . ' 2>&1'));
check('restored data is intact (item names)', $o === "Velvet\nSide Table");
$root("DROP DATABASE `$scratch`");
@unlink($tmp);
check('backup is recorded in the audit log', (bool)DB::val("SELECT 1 FROM audit_logs WHERE action = 'db_backup' ORDER BY id DESC LIMIT 1"));

echo "Security hardening\n";
$h = $a->get('/dashboard')['headers'];
check('security headers on every page', stripos($h, 'X-Frame-Options: SAMEORIGIN') !== false && stripos($h, 'X-Content-Type-Options: nosniff') !== false && stripos($h, 'Referrer-Policy') !== false);
check('login page has them too', stripos((new Client($base))->get('/login')['headers'], 'X-Frame-Options') !== false);
check('session cookie is HttpOnly + SameSite', (bool)preg_match('/Set-Cookie: inv_session=[^;]+;.*HttpOnly/i', (new Client($base))->get('/login')['headers']) && stripos((new Client($base))->get('/login')['headers'], 'SameSite=Lax') !== false);
check('POST without a CSRF token is rejected', !(function () use ($a, $base) { $before = DB::val('SELECT COUNT(*) FROM customers WHERE name = ?', ['CsrfProbe']); $a->req('POST', '/customers', ['name' => 'CsrfProbe']); return DB::val('SELECT COUNT(*) FROM customers WHERE name = ?', ['CsrfProbe']) > $before; })());
$l = new Client($base);
$fail = 0;
for ($i = 0; $i < 8; $i++) { $r = $l->post('/login', ['email' => "labeler-$sfx@test.local", 'password' => 'wrong' . $i], '/login'); }
$after = $l->post('/login', ['email' => "labeler-$sfx@test.local", 'password' => 'Password123'], '/login');
check('repeated wrong passwords lock the account out, even for the right password', $after['location'] !== null && !str_ends_with((string)$after['location'], '/dashboard'));
check('.htaccess blocks code, config, storage and .env', (function () { $h = file_get_contents(dirname(__DIR__) . '/.htaccess'); foreach (['config', 'core', 'storage', '\.env'] as $x) if (!str_contains($h, $x)) return false; return true; })());
check('installer is locked once installed', in_array($a->post('/install', ['x' => 1], '/login')['status'], [302, 403, 404, 419], true) && (new Client($base))->get('/install')['status'] !== 200);
check('debug is off in the installer\'s .env template', str_contains((string)file_get_contents(dirname(__DIR__) . '/app/Controllers/InstallController.php'), "'APP_DEBUG=false'"));

echo "\n" . ($fails ? "$fails check(s) FAILED" : 'All checks passed') . "\n";
exit($fails ? 1 : 0);
