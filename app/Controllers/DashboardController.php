<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Metrics;
use App\Models\Role;
use App\Models\User;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $p = Metrics::period($_GET);
        [$labels, $sales] = Metrics::series('sales_invoices', 'invoice_date', $p['from'], $p['to']);
        [, $buys] = Metrics::series('purchase_bills', 'bill_date', $p['from'], $p['to']);
        $t = (int)Auth::tenantId();
        $this->view('app/dashboard', [
            'title' => 'Dashboard',
            'p' => $p,
            'labels' => $labels, 'salesSeries' => $sales, 'buySeries' => $buys,
            'salesNow' => array_sum($sales), 'salesPrev' => Metrics::sales($p['prev_from'], $p['prev_to']),
            'buyNow' => array_sum($buys), 'buyPrev' => Metrics::purchases($p['prev_from'], $p['prev_to']),
            'receivable' => Metrics::due('sales'), 'payable' => Metrics::due('purchase'),
            'ordersOpen' => (int)DB::val("SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ? AND status IN ('confirmed','partial')", [$t]),
            'lowCount' => Metrics::lowStockCount(), 'stockValue' => Metrics::stockValue(),
            'rolls' => (int)DB::val('SELECT COUNT(*) FROM (SELECT batch_id FROM stock_balances WHERE tenant_id = ? AND batch_id > 0 GROUP BY batch_id HAVING SUM(qty) > 0.0005) x', [$t]),
            'topItems' => Metrics::topItems($p['from'], $p['to']),
            'byCategory' => Metrics::stockByCategory(),
            'ageRec' => Metrics::ageing('sales'), 'agePay' => Metrics::ageing('purchase'),
            'low' => Metrics::lowStock(), 'overdue' => Metrics::overdueInvoices(), 'orders' => Metrics::openOrders(),
            'userCount' => User::count(), 'roleCount' => Role::count(),
            'u' => Auth::user(),
        ]);
    }
}
