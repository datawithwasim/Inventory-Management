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
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page, $m);
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
