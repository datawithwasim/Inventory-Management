<?php
declare(strict_types=1);

namespace App\Admin;

use Core\Audit;
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
}
