<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Columns;
use Core\Controller;
use Core\Prefs;

/** Small personal preferences that are not worth a settings page (list columns). */
final class PrefsController extends Controller
{
    public function columns(): void
    {
        $list = (string)($_POST['list'] ?? '');
        if (!isset(Columns::LISTS[$list])) $this->notFound();
        if (!empty($_POST['reset'])) {
            Prefs::forget('cols.' . $list);
        } else {
            $allowed = Columns::options($list);
            $cols = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['cols'] ?? [])), fn($k) => isset($allowed[$k]))));
            Prefs::set('cols.' . $list, $cols);
        }
        redirect(Columns::LISTS[$list]['route']);
    }
}
