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
    $decimals = max(0, min(3, (int)Core\Settings::get('currency.decimals')));
    $n = round((float)$v, $decimals);
    $neg = $n < 0;
    $str = number_format(abs($n), $decimals, '.', Core\Settings::get('number.grouping') === 'indian' ? '' : ',');
    if (Core\Settings::get('number.grouping') === 'indian') {
        [$int, $dec] = array_pad(explode('.', $str, 2), 2, null);
        $int = strlen($int) > 3 ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)) . ',' . substr($int, -3) : $int;
        $str = $int . ($dec !== null ? '.' . $dec : '');
    }
    $sym = Core\Settings::get('currency.symbol');
    if ($sym !== '') $str = Core\Settings::get('currency.position') === 'after' ? "$str $sym" : "$sym $str";
    return ($neg ? '-' : '') . $str;
}

/** Formats a date (or date-time) the way the company likes (Settings → Preferences). */
function fdate(?string $v): string
{
    if ($v === null || $v === '' || str_starts_with($v, '0000')) return '';
    $t = strtotime($v);
    return $t ? date(Core\Settings::get('date.format') ?: 'Y-m-d', $t) : $v;
}

const TERMS = [
    'supplier' => ['Supplier', 'Suppliers'], 'customer' => ['Customer', 'Customers'], 'item' => ['Item', 'Items'],
    'warehouse' => ['Warehouse', 'Warehouses'], 'rack' => ['Rack', 'Racks'], 'batch' => ['Batch', 'Batches'],
];

/** The company's own word for something (Settings → Labels). term('supplier'), term('suppliers'), term('supplier', true) for lower case. */
function term(string $key, bool $lower = false): string
{
    static $cache = [];
    $plural = false;
    if (!isset(TERMS[$key])) {
        $found = null;
        foreach (TERMS as $k => [, $pl]) if (strtolower($pl) === strtolower($key)) $found = $k;
        if ($found === null) return $key;
        $key = $found;
        $plural = true;
    }
    $tid = (int)Core\Auth::tenantId();
    $labels = $cache[$tid] ??= Core\Settings::json('labels', []);
    $word = $labels[$key][$plural ? 1 : 0] ?? TERMS[$key][$plural ? 1 : 0];
    return $lower ? mb_strtolower($word) : $word;
}

function company_logo_url(): ?string
{
    return Core\Settings::get('company.logo') !== '' ? url('company/logo') : null;
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

function sale_badge(string $kind, string $status): string
{
    $map = $kind === 'quote' ? App\Models\Sales::QUOTE_STATUS : App\Models\Sales::ORDER_STATUS;
    [$label, $color] = $map[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

/** Format one report cell for the screen (exports use raw numbers). */
function report_cell(array $col, mixed $v): string
{
    if ($v === null || $v === '') return '';
    return match ($col['type']) {
        'money' => money($v),
        'qty' => qty($v),
        'int' => (string)(int)$v,
        'date' => fdate((string)$v),
        default => (string)$v,
    };
}

/** Is this optional standard field switched on for the company? e.g. ff('customer.email') */
function ff(string $key): bool
{
    [$e, $c] = explode('.', $key, 2);
    return Core\FormFields::shown($e, $c);
}

/** ' required' attribute + star helper for mandatory fields. */
function ffreq(string $key): string
{
    [$e, $c] = explode('.', $key, 2);
    return Core\FormFields::required($e, $c) ? ' required' : '';
}

function ffstar(string $key): string
{
    [$e, $c] = explode('.', $key, 2);
    return Core\FormFields::required($e, $c) ? ' <span class="text-danger">*</span>' : '';
}

/** When a field is switched off, keep its current value travelling with the form so editing never wipes saved data. */
function ffh(string $key, mixed $value = ''): string
{
    [$e, $c] = explode('.', $key, 2);
    return Core\FormFields::shown($e, $c) ? '' : '<input type="hidden" name="' . e($c) . '" value="' . e((string)$value) . '">';
}

/** Design attributes (order, width, help text) for one field wrapper: e.g. <div class="col-md-4"<?= ffa('customer.email') ?>> */
function ffa(string $key): string
{
    [$e, $c] = explode('.', $key, 2);
    return Core\FormDesign::attrs($e, $c);
}

/** The label to show: the company's own wording if set, else the default (already-escaped HTML). */
function fl(string $key, string $default): string
{
    [$e, $c] = explode('.', $key, 2);
    $custom = Core\FormDesign::label($e, $c);
    return $custom !== '' ? e($custom) : $default;
}
