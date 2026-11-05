<?php
declare(strict_types=1);

/**
 * Robustness sweep: calls every route with junk ids and empty / garbage bodies and checks that the app answers politely
 * (never a 500, never a PHP warning in the log).
 *   php tests/robust.php http://127.0.0.1:8099 [/path/to/php-server.log]
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$logFile = $argv[2] ?? '/tmp/php.log';
require dirname(__DIR__) . '/core/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\DB;

$sfx = bin2hex(random_bytes(3));
[$c] = fresh_company($base, "Robust $sfx", "robust-$sfx@test.local");

check('signed in as the company owner', $c->get('/dashboard')['status'] === 200);

// pull the route table
$router = new Core\Router();
$A = 'App\\Controllers\\';
require dirname(__DIR__) . '/config/routes.php';
$ref = new ReflectionProperty($router, 'routes');
$ref->setAccessible(true);
$routes = $ref->getValue($router);

$skip = fn(string $p) => (bool)preg_match('#/logout$|^/install|/settings/system/backup|/settings/system/migrate|/forgot|/reset-password|^/login#', $p);
$logPos = is_file($logFile) ? filesize($logFile) : 0;
$bad = [];
$count = 0;
$subs = ['999999', 'abc', '0', '-1', "1'--"];
foreach ($routes as [$method, $pattern, $handler, $mw]) {
    if ($skip($pattern)) continue;
    $cli = $c;
    foreach ($subs as $sub) {
        $path = preg_replace('#\{(\w+)\}#', rawurlencode($sub), $pattern);
        $variants = $method === 'POST' ? [[], ['name' => str_repeat('x', 5000), 'qty' => 'abc', 'lines' => 'junk', 'cols' => 'x', 'order' => 'x', 'on' => 'x']] : [[]];
        foreach ($variants as $body) {
            $count++;
            $r = $method === 'POST' ? $cli->post($path, $body, '/dashboard') : $cli->get($path);
            $GLOBALS['hist'][$r['status']] = ($GLOBALS['hist'][$r['status']] ?? 0) + 1;
            if ($r['status'] >= 500 || str_contains($r['body'], 'SQLSTATE') || str_contains($r['body'], 'Stack trace') || preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $r['body'])) $bad[] = "{$r['status']} $method $path";
        }
    }
}
$log = is_file($logFile) ? substr((string)file_get_contents($logFile), $logPos) : '';
preg_match_all('/PHP (Warning|Notice|Deprecated|Fatal error)[^\n]*|Uncaught[^\n]*|Error: [^\n]*/', $log, $m);
check("$count requests across " . count($routes) . ' routes: no 500s, SQL errors or PHP warnings in responses' . ($bad ? ' — ' . implode('; ', array_slice($bad, 0, 8)) : ''), !$bad);
check('server log has no warnings or uncaught errors' . ($m[0] ? ' — ' . implode(' | ', array_slice(array_unique($m[0]), 0, 5)) : ''), !$m[0]);
ksort($GLOBALS['hist']);
echo '  status mix: ' . json_encode($GLOBALS['hist']) . "\n";
echo "\n" . ($fails ? "$fails check(s) FAILED" : 'All checks passed') . "\n";
exit($fails ? 1 : 0);
