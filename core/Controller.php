<?php
declare(strict_types=1);

namespace Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
        clear_old();
    }

    /** Renders a Settings page inside the Settings frame (grouped menu on the left). */
    protected function settingsView(string $view, array $data, string $tab): void
    {
        ob_start();
        View::render($view, $data + ['tab' => $tab], null);
        $inner = (string)ob_get_clean();
        $this->view('app/settings/_frame', ['title' => $data['title'] ?? 'Settings', 'tab' => $tab, 'inner' => $inner]);
    }

    protected function input(): array
    {
        return $_POST;
    }

    /** Validate; on failure flash errors, remember input and bounce back. */
    protected function validate(array $rules, string $backTo): array
    {
        $data = $this->input();
        $errors = Validator::check($data, $rules);
        if ($errors) {
            foreach ($errors as $m) flash('danger', $m);
            with_old($data);
            redirect($backTo);
        }
        return $data;
    }

    protected function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', [], null);
        exit;
    }

    protected function forbidden(): never
    {
        http_response_code(403);
        View::render('errors/403', [], null);
        exit;
    }
}
