<?php
declare(strict_types=1);

namespace App\Admin;

use Core\Audit;
use Core\Backup;
use Core\Controller;
use Core\DB;
use Core\Migrator;

/** Lets the platform owner apply database updates from the browser (no terminal on shared hosting). */
final class SystemController extends Controller
{
    public function index(): void
    {
        $this->view('admin/system', [
            'title' => 'System',
            'pending' => Migrator::pending(),
            'applied' => DB::all('SELECT name, applied_at FROM migrations ORDER BY name'),
            'info' => [
                'PHP' => PHP_VERSION, 'Database' => DB::pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            ],
        ], 'layouts/admin');
    }

    public function migrate(): void
    {
        try {
            $done = Migrator::run();
        } catch (\Throwable $e) {
            flash('danger', 'Update failed: ' . $e->getMessage());
            redirect('admin/system');
        }
        if ($done) Audit::log('db_migrate', 'system', null, implode(', ', $done));
        flash('success', $done ? 'Database updated: ' . implode(', ', $done) : 'Already up to date.');
        redirect('admin/system');
    }

    /** Full database backup as a download (POST so it needs the CSRF token and cannot be triggered by a link). */
    public function backup(): void
    {
        @set_time_limit(0);
        $name = 'inventory-backup-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        $rows = Backup::stream(function (string $chunk) {
            echo $chunk;
            if (PHP_SAPI !== 'cli') { @ob_flush(); flush(); }
        });
        Audit::log('db_backup', 'system', null, "$rows rows");
    }
}
