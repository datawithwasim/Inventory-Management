<?php
declare(strict_types=1);

namespace Core;

/**
 * JSON mode. A request to /api/v1/<any existing route> runs the same controller as the page does, but:
 *  - the controller's view data is returned as JSON instead of HTML,
 *  - a redirect (after saving, or after a refused form) becomes {ok, redirect, messages},
 *  - errors are JSON with the matching status.
 * Writes use the same validation as the pages; a POST needs the session cookie plus the X-CSRF-Token header.
 */
final class Api
{
    private static bool $on = false;

    public static function on(): bool
    {
        return self::$on;
    }

    public static function enable(): void
    {
        self::$on = true;
        // JSON request bodies reach the controllers the way form posts do
        $type = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($type, 'application/json')) {
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (is_array($body)) $_POST = $body + $_POST;
        }
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $_POST['_csrf'] = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    /** Keys that never leave the server. */
    private const HIDDEN = '/password|passwd|token|secret|_hash$|api_key|remember/i';

    public static function clean(mixed $v, int $depth = 0): mixed
    {
        if ($depth > 8) return null;
        if (is_array($v)) {
            $o = [];
            foreach ($v as $k => $x) {
                if (is_string($k) && preg_match(self::HIDDEN, $k)) continue;
                if (is_object($x) || is_resource($x)) continue;
                $o[$k] = self::clean($x, $depth + 1);
            }
            return $o;
        }
        return is_object($v) || is_resource($v) ? null : $v;
    }

    public static function send(array $payload, int $status = 200): never
    {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    public static function error(int $status, string $message): never
    {
        self::send(['ok' => false, 'error' => $message, 'messages' => [['type' => 'danger', 'msg' => $message]]], $status);
    }

    /** What a page would have shown: its data, plus the flash messages waiting for it. */
    public static function view(string $view, array $data): never
    {
        unset($data['content']);
        self::send(['ok' => true, 'view' => $view, 'data' => self::clean($data), 'messages' => flashes()]);
    }

    /** A redirect after a write: success unless a "danger" message came with it. */
    public static function redirect(string $path): never
    {
        $messages = flashes();
        $bad = (bool)array_filter($messages, fn($m) => ($m['type'] ?? '') === 'danger');
        $path = trim($path);
        if ($path === 'login' && !Auth::check() && !$messages) self::error(401, 'Please sign in.');
        self::send(['ok' => !$bad, 'redirect' => preg_replace('~^' . preg_quote(base_path(), '~') . '~', '', $path), 'messages' => $messages], $bad ? 422 : 200);
    }
}
