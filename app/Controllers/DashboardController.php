<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use App\Models\User;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('app/dashboard', [
            'title' => 'Dashboard',
            'userCount' => User::count(),
            'roleCount' => Role::count(),
            'stats' => $this->stats(),
            'u' => Auth::user(),
        ]);
    }

    private function stats(): array
    {
        $t = (int)Auth::tenantId();
        $low = (int)DB::val(
            'SELECT COUNT(*) FROM items i WHERE i.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND i.reorder_level > 0
               AND (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id WHERE v.item_id = i.id) <= i.reorder_level', [$t]);
        return [
            'items' => (int)DB::val('SELECT COUNT(*) FROM items WHERE tenant_id = ?', [$t]),
            'warehouses' => (int)DB::val('SELECT COUNT(*) FROM warehouses WHERE tenant_id = ? AND is_active = 1', [$t]),
            'low' => $low,
            'rolls' => (int)DB::val('SELECT COUNT(*) FROM (SELECT batch_id FROM stock_balances WHERE tenant_id = ? AND batch_id > 0 GROUP BY batch_id HAVING SUM(qty) > 0.0005) x', [$t]),
            'value' => (float)DB::val(
                'SELECT COALESCE(SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)),0) FROM stock_balances s
                 JOIN item_variants v ON v.id = s.variant_id LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0 WHERE s.tenant_id = ?', [$t]),
        ];
    }
}
