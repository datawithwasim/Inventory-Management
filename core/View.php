<?php
declare(strict_types=1);

namespace Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture($layout, $data + ['content' => $content]);
    }

    private static function capture(string $view, array $data): string
    {
        $file = ROOT . '/views/' . $view . '.php';
        if (!is_file($file)) throw new \RuntimeException("View not found: $view");
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
