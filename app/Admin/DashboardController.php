<?php
declare(strict_types=1);

namespace App\Admin;

use Core\Controller;
use Core\DB;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $counts = DB::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status='active'),0) AS active,
                    COALESCE(SUM(status='trial'),0) AS trial,
                    COALESCE(SUM(status='suspended'),0) AS suspended
             FROM tenants");
        $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'counts' => $counts,
            'users' => (int)DB::val('SELECT COUNT(*) FROM users'),
            'recent' => DB::all(
                'SELECT t.*, p.name AS plan_name FROM tenants t JOIN plans p ON p.id = t.plan_id
                 ORDER BY t.id DESC LIMIT 5'),
        ], 'layouts/admin');
    }

    public function audit(): void
    {
        $this->view('admin/audit', [
            'title' => 'Audit log',
            'logs' => DB::all(
                'SELECT a.*, t.name AS tenant_name FROM audit_logs a
                 LEFT JOIN tenants t ON t.id = a.tenant_id ORDER BY a.id DESC LIMIT 200'),
        ], 'layouts/admin');
    }
}
