<?php
declare(strict_types=1);

/*
 * One company only: the software no longer hosts several companies.
 * Keeps one company (KEEP_COMPANY_ID in .env, else the company of the owner who presses "Run updates", else the one with
 * the most stock movements and items), deletes the
 * rows of any other company, removes the tenant_id column from every table (its indexes keep their other columns),
 * renames tenant_settings to settings, keeps the company name as the setting company.name, and drops the
 * tenants, plans and super_admins tables. Safe to run again if it stopped half way.
 */
return function (PDO $pdo): void {
    $q = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s; })();
    $exists = fn(string $table) => (bool)$q('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])->fetchColumn();
    $settings = $exists('tenant_settings') ? 'tenant_settings' : 'settings';
    $tables = $q("SELECT table_name FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'tenant_id'")->fetchAll(PDO::FETCH_COLUMN);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        // 1. the company that stays, and its name
        if ($exists('tenants') && $tables) {
            $keep = (int)(config('keep_company_id') ?? 0);
            if ((!$keep || !$q('SELECT 1 FROM tenants WHERE id = ?', [$keep])->fetchColumn()) && !empty($_SESSION['user_id'])) {
                $keep = (int)$q('SELECT tenant_id FROM users WHERE id = ?', [(int)$_SESSION['user_id']])->fetchColumn();
            }
            if (!$keep || !$q('SELECT 1 FROM tenants WHERE id = ?', [$keep])->fetchColumn()) {
                $keep = (int)$q('SELECT t.id FROM tenants t ORDER BY
                    (SELECT COUNT(*) FROM stock_ledger l WHERE l.tenant_id = t.id) + (SELECT COUNT(*) FROM items i WHERE i.tenant_id = t.id) DESC,
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) DESC, t.id LIMIT 1')->fetchColumn();
            }
            if ($keep) {
                $name = (string)$q('SELECT name FROM tenants WHERE id = ?', [$keep])->fetchColumn();
                if (in_array($settings, $tables, true)) {
                    $q("INSERT IGNORE INTO `$settings` (tenant_id, skey, svalue) VALUES (?, 'company.name', ?)", [$keep, $name]);
                }
                foreach ($tables as $t) $q("DELETE FROM `$t` WHERE tenant_id IS NULL OR tenant_id <> ?", [$keep]);
            } else {
                foreach ($tables as $t) $pdo->exec("DELETE FROM `$t`");
            }
            // rows of other companies that hang off a parent without tenant_id
            $pdo->exec('DELETE FROM role_permissions WHERE role_id NOT IN (SELECT id FROM roles)');
            if ($exists('user_prefs')) $pdo->exec('DELETE FROM user_prefs WHERE user_id NOT IN (SELECT id FROM users)');
            $pdo->exec('DELETE FROM password_resets WHERE email NOT IN (SELECT email FROM users)');
        }

        // 2. foreign keys on tenant_id or pointing at the platform tables
        foreach ($q("SELECT DISTINCT table_name, constraint_name FROM information_schema.key_column_usage
                     WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL
                       AND (column_name = 'tenant_id' OR referenced_table_name IN ('tenants', 'plans', 'super_admins'))")->fetchAll() as $fk) {
            $pdo->exec("ALTER TABLE `{$fk['table_name']}` DROP FOREIGN KEY `{$fk['constraint_name']}`");
        }

        // 3. indexes: drop tenant_id from each, keep the rest of the index (unique stays unique)
        foreach ($tables as $t) {
            $idx = [];
            foreach ($q('SELECT index_name, non_unique, column_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? ORDER BY index_name, seq_in_index', [$t])->fetchAll() as $r) {
                $idx[$r['index_name']]['unique'] = !(int)$r['non_unique'];
                $idx[$r['index_name']]['cols'][] = $r['column_name'];
            }
            $parts = [];
            foreach ($idx as $name => $i) {
                if (!in_array('tenant_id', $i['cols'], true)) continue;
                $rest = array_values(array_filter($i['cols'], fn($c) => $c !== 'tenant_id'));
                $cols = implode(', ', array_map(fn($c) => "`$c`", $rest));
                if ($name === 'PRIMARY') { $parts[] = 'DROP PRIMARY KEY'; if ($rest) $parts[] = "ADD PRIMARY KEY ($cols)"; continue; }
                $parts[] = "DROP INDEX `$name`";
                if ($rest) $parts[] = ($i['unique'] ? "ADD UNIQUE KEY `$name`" : "ADD KEY `$name`") . " ($cols)";
            }
            $parts[] = 'DROP COLUMN tenant_id';
            $pdo->exec("ALTER TABLE `$t` " . implode(', ', $parts));
        }

        // 4. platform tables go; company settings keep their plain name
        if ($exists('tenant_settings') && !$exists('settings')) $pdo->exec('RENAME TABLE tenant_settings TO settings');
        if ($exists('audit_logs')) $pdo->exec("DELETE FROM audit_logs WHERE actor_type = 'admin'");
        foreach (['tenants', 'plans', 'super_admins'] as $t) $pdo->exec("DROP TABLE IF EXISTS `$t`");
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
};
