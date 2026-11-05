<?php
declare(strict_types=1);

/**
 * Migration 030 on an older multi-company database: builds one from migrations 001–029 in a scratch database,
 * puts two companies in it, runs 030 and checks that one company stays whole and nothing of the other is left.
 *   php tests/upgrade.php        # needs a MySQL user that may create databases (the test setup uses root)
 */
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

$c = config('db');
$name = 'inv_upgrade_' . bin2hex(random_bytes(3));
$root = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$name;charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
$val = fn(string $sql, array $p = []) => $q($sql, $p)->fetchColumn();

try {
    foreach (glob(ROOT . '/database/migrations/*.sql') as $f) {
        if (basename($f) >= '030') continue;
        foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", (string)file_get_contents($f))))) as $st) $pdo->exec($st);
    }
    // the old platform: a plan, a Super Admin, two companies with their owners, settings, numbering and a little data
    $q("INSERT INTO plans (name, slug) VALUES ('Pro', 'pro')");
    $q("INSERT INTO super_admins (name, email, password_hash) VALUES ('SA', 'sa@test.local', 'x')");
    foreach ([1 => 'Nirmals', 2 => 'Old Test Co'] as $t => $co) {
        $q("INSERT INTO tenants (id, name, slug, plan_id, status) VALUES (?, ?, ?, 1, 'active')", [$t, $co, "co-$t"]);
        $q('INSERT INTO roles (tenant_id, name, is_owner, is_system) VALUES (?, ?, 1, 1)', [$t, 'Owner']);
        $role = (int)$pdo->lastInsertId();
        $q('INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)', [$role, 'items.view']);
        $q('INSERT INTO users (tenant_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)', [$t, $role, "Owner $t", "owner$t@test.local", 'x']);
        $q('INSERT INTO units (tenant_id, name, short_name, allow_decimal) VALUES (?, ?, ?, 1)', [$t, 'Meter', 'm']);
        $unit = (int)$pdo->lastInsertId();
        $q('INSERT INTO warehouses (tenant_id, name, code, is_default) VALUES (?, ?, ?, 1)', [$t, 'Main Warehouse', 'MAIN']);
        $q('INSERT INTO items (tenant_id, name, unit_id) VALUES (?, ?, ?)', [$t, "Velvet $t", $unit]);
        $item = (int)$pdo->lastInsertId();
        $q('INSERT INTO item_variants (tenant_id, item_id, sku) VALUES (?, ?, ?)', [$t, $item, 'VEL']);   // the same SKU in both companies
        $q("INSERT INTO tenant_settings (tenant_id, skey, svalue) VALUES (?, 'currency.symbol', ?)", [$t, $t === 1 ? '₹' : '$']);
        $q("INSERT INTO counters (tenant_id, prefix, next_no) VALUES (?, 'PO', ?)", [$t, $t * 10]);
    }
    $q("INSERT INTO password_resets (email, token_hash, expires_at) VALUES ('admin:sa@test.local', 'x', NOW())");

    $GLOBALS['config']['keep_company_id'] = 1;
    $fn = require ROOT . '/database/migrations/030_single_company.php';
    $fn($pdo);
    check('no tenant_id column is left in any table', !$val("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'tenant_id'"));
    check('the companies, plans and Super Admin tables are gone', !$val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('tenants', 'plans', 'super_admins', 'tenant_settings')"));
    check('the kept company keeps its owner, role, permissions, item and warehouse', $val('SELECT email FROM users') === 'owner1@test.local' && (int)$val('SELECT COUNT(*) FROM users') === 1
        && (int)$val('SELECT COUNT(*) FROM roles') === 1 && (int)$val('SELECT COUNT(*) FROM role_permissions') === 1 && $val('SELECT name FROM items') === 'Velvet 1' && (int)$val('SELECT COUNT(*) FROM warehouses') === 1);
    check('nothing of the other company is left (its item, unit, numbering, settings)', (int)$val('SELECT COUNT(*) FROM items') === 1 && (int)$val('SELECT COUNT(*) FROM units') === 1
        && (int)$val("SELECT next_no FROM counters WHERE prefix = 'PO'") === 10 && $val("SELECT svalue FROM settings WHERE skey = 'currency.symbol'") === '₹');
    check('the company name becomes the setting company.name', $val("SELECT svalue FROM settings WHERE skey = 'company.name'") === 'Nirmals');
    check('a SKU stays unique without the company in the index', (function () use ($q) { try { $q("INSERT INTO item_variants (item_id, sku) SELECT id, 'VEL' FROM items LIMIT 1"); return false; } catch (PDOException) { return true; } })());
    check('settings and counters keep a primary key on their own column', (bool)$val("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'settings' AND index_name = 'PRIMARY' AND column_name = 'skey'")
        && (bool)$val("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'counters' AND index_name = 'PRIMARY' AND column_name = 'prefix'"));
    check('the Super Admin reset tokens are gone', !$val("SELECT COUNT(*) FROM password_resets"));
    $fn($pdo);
    check('running it again changes nothing', (int)$val('SELECT COUNT(*) FROM users') === 1 && $val("SELECT svalue FROM settings WHERE skey = 'company.name'") === 'Nirmals');
} finally {
    $root->exec("DROP DATABASE IF EXISTS `$name`");
}

echo $fails ? "\n$fails check(s) FAILED\n" : "\nAll checks passed\n";
exit($fails ? 1 : 0);
