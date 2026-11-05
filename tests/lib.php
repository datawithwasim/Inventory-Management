<?php
declare(strict_types=1);

/** Tiny HTTP client + assertions shared by the end-to-end tests. */
final class Client
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function req(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($method === 'POST') {
            $hasFile = (bool)array_filter($data, fn($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
        }
        $raw = (string)curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = substr($raw, 0, $hs);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
        return ['status' => $status, 'body' => substr($raw, $hs), 'location' => $m[1] ?? null, 'headers' => $headers];
    }

    public function get(string $p): array
    {
        return $this->req('GET', $p);
    }

    /** POST with a CSRF token scraped from a page that has a form. */
    public function post(string $p, array $data, string $tokenPage): array
    {
        $page = $this->get($tokenPage)['body'];
        preg_match('/name="_csrf" value="([a-f0-9]+)"|name="csrf" content="([a-f0-9]+)"/', $page, $m);
        $m[1] = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
        return $this->req('POST', $p, $data + ['_csrf' => $m[1] ?? '']);
    }

    /** Follow the redirect of a POST and return the page it lands on (to read flash messages). */
    public function follow(array $r): array
    {
        return $r['location'] ? $this->get((string)parse_url($r['location'], PHP_URL_PATH)) : $r;
    }
}

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . "\n";
    if (!$ok) $fails++;
}

/** Receives everything still on the road of one transfer (racks: line id => rack; others use the rack suggested at dispatch). */
function receive_all(Client $c, int $docId, array $racks = []): void
{
    $lines = [];
    foreach (Core\DB::all('SELECT id, qty - qty_received - qty_lost - qty_returned AS pending FROM stock_doc_lines WHERE doc_id = ?', [$docId]) as $l) {
        if ((float)$l['pending'] <= 0.0005) continue;
        $row = ['qty' => (string)$l['pending']];
        if (isset($racks[$l['id']])) $row['location_id'] = (string)$racks[$l['id']];
        $lines[$l['id']] = $row;
    }
    if ($lines) $c->post("/stock/transfers/$docId/receive", ['lines' => $lines], "/stock/transfers/$docId");
}
/** The newest transfer document. */
function last_transfer(): int
{
    return (int)Core\DB::val("SELECT MAX(id) FROM stock_docs WHERE type = 'transfer'");
}

/**
 * Empties the test database and sets it up again for one company with its owner (password Password123).
 * Run the tests against a throw-away database only. Returns the owner's signed-in client and e-mail.
 */
function fresh_company(string $base, string $name = 'Test Company', ?string $email = null): array
{
    // never on a live site: the installer writes APP_ENV=production into its .env
    $env = is_file(ROOT . '/.env') && preg_match('/^APP_ENV\s*=\s*"?(\w+)/m', (string)file_get_contents(ROOT . '/.env'), $m) ? $m[1] : '';
    if ($env === 'production') { fwrite(STDERR, "Refusing to empty a production database (APP_ENV=production in .env).\n"); exit(2); }
    $pdo = Core\DB::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec("DROP TABLE `$t`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    Core\Migrator::run();
    Core\Settings::flush();
    $email ??= 'owner-' . bin2hex(random_bytes(3)) . '@test.local';
    Core\Seeder::run($name, 'Owner', $email, 'Password123');
    Core\Settings::flush();
    $c = new Client($base);
    $c->post('/login', ['email' => $email, 'password' => 'Password123'], '/login');
    return [$c, $email];
}
