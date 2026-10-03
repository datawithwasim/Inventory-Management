<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $dir;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function with_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['_csrf']);
    $_SESSION['_old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function csrf_token(): string
{
    return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function can(string $permission): bool
{
    return Core\Auth::can($permission);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function slugify(string $text): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $s !== '' ? $s : 'company';
}

function send_mail(string $to, string $subject, string $body): void
{
    if (config('mail.driver') === 'mail') {
        $from = config('mail.from');
        $ok = @mail($to, $subject, $body, "From: $from\r\nContent-Type: text/plain; charset=UTF-8");
        if ($ok) return;
    }
    // Fallback / "log" driver: written to storage/logs/mail.log.
    $entry = sprintf("[%s] To: %s | Subject: %s\n%s\n---\n", date('c'), $to, $subject, $body);
    file_put_contents(ROOT . '/storage/logs/mail.log', $entry, FILE_APPEND);
}

function qty(float|string|null $q): string
{
    return App\Models\Stock::fmt($q);
}

function money(float|string|null $v): string
{
    return number_format((float)$v, 2);
}

/** Prev / next links that keep the current filters. */
function pager(int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $link = function (int $p, string $label, bool $enabled) {
        $q = $_GET;
        $q['page'] = $p;
        return '<li class="page-item' . ($enabled ? '' : ' disabled') . '"><a class="page-link" href="?' . e(http_build_query($q)) . '">' . $label . '</a></li>';
    };
    return '<nav class="mt-3"><ul class="pagination pagination-sm mb-0">'
        . $link($page - 1, '&laquo; Prev', $page > 1)
        . '<li class="page-item disabled"><span class="page-link">Page ' . $page . ' of ' . $pages . '</span></li>'
        . $link($page + 1, 'Next &raquo;', $page < $pages) . '</ul></nav>';
}

function po_badge(string $status): string
{
    [$label, $color] = App\Models\Purchase::PO_STATUS[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

function pay_badge(string $status): string
{
    [$label, $color] = App\Models\Purchase::PAY_STATUS[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}
