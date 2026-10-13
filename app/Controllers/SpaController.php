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
        // the same title, icon and brand colours the classic pages have, from the first paint (the sign-in page has no session to ask)
        $icon = '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3E%3Crect width=\'16\' height=\'16\' rx=\'4\' fill=\'%234f46e5\'/%3E%3Cpath d=\'M3 6l5-3 5 3v5l-5 3-5-3z\' fill=\'none\' stroke=\'white\' stroke-width=\'1.2\'/%3E%3C/svg%3E">';
        $html = preg_replace('~<title>.*?</title>~s', '<title>' . htmlspecialchars((string)config('name'), ENT_QUOTES) . '</title>', $html, 1);
        $html = str_replace('<style id="brand-vars"></style>', \Core\Theme::css(), $html);
        $html = str_replace('</head>', $icon . '<meta name="app-title" content="' . htmlspecialchars((string)config('name'), ENT_QUOTES) . '"><meta name="app-base" content="' . htmlspecialchars($base, ENT_QUOTES) . '"></head>', $html);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }
}
