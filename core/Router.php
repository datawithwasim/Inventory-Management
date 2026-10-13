<?php
declare(strict_types=1);

namespace Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, string $handler, array $mw = []): void
    {
        $this->routes[] = ['GET', $path, $handler, $mw];
    }

    public function post(string $path, string $handler, array $mw = []): void
    {
        $this->routes[] = ['POST', $path, $handler, $mw];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        $path = '/' . trim($path, '/');
        if ($path === '/app' || str_starts_with($path, '/app/')) \App\Controllers\SpaController::serve();
        if (preg_match('~^/api(/v1)?(/|$)~', $path)) {
            Api::enable();
            $path = '/' . trim((string)preg_replace('~^/api(/v1)?~', '', $path), '/');
        }

        // a browser opening a classic screen address goes to the same screen in the app; the root always does
        if ($method === 'GET' && !Api::on()) {
            $to = Spa::redirectFor($path, $_GET);
            if ($to !== null && ($path === '/' || !config('legacy_ui'))) {
                $qs = (string)parse_url($uri, PHP_URL_QUERY);
                header('Location: ' . url(ltrim($to, '/')) . ($qs !== '' ? '?' . $qs : ''));
                exit;
            }
        }

        foreach ($this->routes as [$m, $pattern, $handler, $mw]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if ($m !== $method || !preg_match($regex, $path, $match)) continue;

            if ($method === 'POST' && !hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
                if (Api::on()) Api::error(419, 'Your session expired. Reload the page and try again.');
                http_response_code(419);
                View::render('errors/419', [], null);
                return;
            }
            foreach ($mw as $name) {
                if (!$this->middleware($name)) return;
            }
            if (Auth::user() && Modules::blocked($path) !== null) {
                if (Api::on()) Api::error(404, 'This part is switched off.');
                http_response_code(404);
                View::render('errors/404', [], null);
                return;
            }
            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = explode('@', $handler);
            (new $class())->$action(...array_values($params));
            return;
        }
        if (Api::on()) Api::error(404, 'Not found.');
        http_response_code(404);
        View::render('errors/404', [], null);
    }

    /** @return bool true to continue, false when the middleware already responded */
    private function middleware(string $name): bool
    {
        [$n, $arg] = array_pad(explode(':', $name, 2), 2, null);
        switch ($n) {
            case 'auth':
                if (!Auth::check()) { if (Api::on()) Api::error(401, 'Please sign in.'); redirect('login'); }
                return true;
            case 'guest':
                if (Auth::check()) { if (Api::on()) Api::send(['ok' => true, 'redirect' => 'dashboard', 'messages' => []]); redirect('dashboard'); }
                return true;
            case 'admin':
                if (!Auth::admin()) { if (Api::on()) Api::error(401, 'Please sign in.'); redirect('admin/login'); }
                return true;
            case 'admin_guest':
                if (Auth::admin()) redirect('admin');
                return true;
            case 'perm':
                if (!Auth::can((string)$arg)) {
                    if (Api::on()) Api::error(403, 'You do not have permission for this.');
                    http_response_code(403);
                    View::render('errors/403', [], null);
                    return false;
                }
                return true;
        }
        throw new \RuntimeException("Unknown middleware $n");
    }
}
