<?php
declare(strict_types=1);

namespace App\Controllers;

/** Serves the React app's page (built into public/app/) for every /app/... address; the app itself decides what to show. */
final class SpaController
{
    public static function serve(): never
    {
        $file = ROOT . '/public/ui/index.html';
        if (!is_file($file)) {
            http_response_code(404);
            echo 'The new interface has not been built yet. Run "npm install && npm run build" in the frontend folder and upload public/ui.';
            exit;
        }
        $html = (string)file_get_contents($file);
        $base = base_path();
        // asset links were built for /ui/..., keep them working when the site sits in a sub-folder
        if ($base !== '') $html = str_replace(['"/ui/', "'/ui/", '"/assets/'], ['"' . $base . '/ui/', "'" . $base . "/ui/", '"' . $base . '/assets/'], $html);
        $html = str_replace('</head>', '<meta name="app-base" content="' . htmlspecialchars($base, ENT_QUOTES) . '"></head>', $html);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }
}
