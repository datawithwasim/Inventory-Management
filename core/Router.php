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

        foreach ($this->routes as [$m, $pattern, $handler, $mw]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if ($m !== $method || !preg_match($regex, $path, $match)) continue;

            if ($method === 'POST' && !hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
                http_response_code(419);
                View::render('errors/419', [], null);
                return;
            }
            foreach ($mw as $name) {
                if (!$this->middleware($name)) return;
            }
            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = explode('@', $handler);
            (new $class())->$action(...array_values($params));
            return;
        }
        http_response_code(404);
        View::render('errors/404', [], null);
    }

    /** @return bool true to continue, false when the middleware already responded */
    private function middleware(string $name): bool
    {
        [$n, $arg] = array_pad(explode(':', $name, 2), 2, null);
        switch ($n) {
            case 'auth':
                if (!Auth::check()) redirect('login');
                return true;
            case 'guest':
                if (Auth::check()) redirect('dashboard');
                return true;
            case 'admin':
                if (!Auth::admin()) redirect('admin/login');
                return true;
            case 'admin_guest':
                if (Auth::admin()) redirect('admin');
                return true;
            case 'perm':
                if (!Auth::can((string)$arg)) {
                    http_response_code(403);
                    View::render('errors/403', [], null);
                    return false;
                }
                return true;
        }
        throw new \RuntimeException("Unknown middleware $n");
    }
}
