<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Metrics;
use App\Models\Role;
use App\Models\User;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Prefs;

final class DashboardController extends Controller
{
    public const WIDGETS = ['kpi' => 'Key numbers', 'trend' => 'Sales vs purchases chart', 'top_items' => 'Top items by sales', 'stock_cat' => 'Stock value by category',
        'setup' => 'Getting started guide', 'age_rec' => 'Money to collect (ageing)', 'age_pay' => 'Money to pay (ageing)', 'low' => 'Running low', 'overdue' => 'Overdue invoices', 'orders' => 'Orders waiting for delivery'];

    /** The signed-in user's widget order + hidden list, always complete and valid. */
    private function layout(): array
    {
        $saved = Prefs::get('dashboard.layout', []);
        $order = array_values(array_filter((array)($saved['order'] ?? []), fn($k) => isset(self::WIDGETS[$k])));
        foreach (array_keys(self::WIDGETS) as $k) if (!in_array($k, $order, true)) $k === 'setup' ? array_unshift($order, $k) : $order[] = $k;
        $hidden = array_values(array_filter((array)($saved['hidden'] ?? []), fn($k) => isset(self::WIDGETS[$k])));
        return ['order' => array_values(array_unique($order)), 'hidden' => $hidden];
    }

    /** Getting-started checklist: [label, done?, link]. Null once finished or dismissed, or for people who cannot change settings. */
    private function setupSteps(): ?array
    {
        if (!can('settings.view') || Prefs::get('setup.dismissed')) return null;
        $t = (int)Auth::tenantId();
        $has = fn(string $table) => (bool)DB::val("SELECT 1 FROM `$table` WHERE tenant_id = ? LIMIT 1", [$t]);
        $customised = $has('custom_fields') || \Core\Modules::off() !== [] || \Core\Settings::get('fields.hidden', '[]') !== '[]' || \Core\Settings::get('fields.required', '[]') !== '[]';
        $steps = [
            ['Add your company details and logo', \Core\Settings::get('company.address') !== '' || \Core\Settings::get('company.phone') !== '' || \Core\Settings::get('company.logo') !== '', url('settings/company')],
            ['Pick your brand colour and menu style', \Core\Settings::get('appearance.brand', '') !== '' || \Core\Settings::get('appearance.sidebar', '') !== '', url('settings/appearance')],
            ['Add your first item', $has('items'), url('items/create')],
            ['Add racks / locations in your warehouse', $has('locations'), url('locations/create')],
            ['Enter opening stock', $has('stock_ledger'), url('stock/adjustments/create')],
            ['Make it yours: custom fields, hide what you do not use', $customised, url('settings/modules')],
        ];
        return array_filter($steps, fn($s) => true) ?: null;
    }

    public function dismissSetup(): void
    {
        Prefs::set('setup.dismissed', 1);
        redirect('dashboard');
    }

    public function saveLayout(): void
    {
        if (!empty($_POST['reset'])) {
            Prefs::forget('dashboard.layout');
            flash('success', 'Dashboard reset to the default layout.');
            redirect('dashboard');
        }
        $order = array_values(array_unique(array_filter((array)($_POST['order'] ?? []), fn($k) => is_string($k) && isset(self::WIDGETS[$k]))));
        $show = array_map('strval', (array)($_POST['show'] ?? []));
        $hidden = array_values(array_diff(array_keys(self::WIDGETS), $show));
        Prefs::set('dashboard.layout', ['order' => $order, 'hidden' => $hidden]);
        flash('success', 'Dashboard saved.');
        redirect('dashboard');
    }

    public function index(): void
    {
        $p = Metrics::period($_GET);
        [$labels, $sales] = Metrics::series('sales_invoices', 'invoice_date', $p['from'], $p['to']);
        [, $buys] = Metrics::series('purchase_bills', 'bill_date', $p['from'], $p['to']);
        $t = (int)Auth::tenantId();
        $scoped = \Core\Scope::restricted();   // warehouse staff: their warehouse's stock and transfers, no company money figures
        if ($scoped) { $sales = array_map(fn() => 0, $sales); $buys = array_map(fn() => 0, $buys); }
        $mine = \Core\Scope::sqlEither('warehouse_id', 'to_warehouse_id');
        $data = [
            'scoped' => $scoped ? \Core\Scope::name() : null,
            'title' => 'Dashboard',
            'p' => $p, 'layout' => $this->layout(), 'setup' => $this->setupSteps(),
            'labels' => $labels, 'salesSeries' => $sales, 'buySeries' => $buys,
            'salesNow' => $scoped ? 0 : array_sum($sales), 'salesPrev' => $scoped ? 0 : Metrics::sales($p['prev_from'], $p['prev_to']),
            'buyNow' => $scoped ? 0 : array_sum($buys), 'buyPrev' => $scoped ? 0 : Metrics::purchases($p['prev_from'], $p['prev_to']),
            'receivable' => $scoped ? 0 : Metrics::due('sales'), 'payable' => $scoped ? 0 : Metrics::due('purchase'),
            'ordersOpen' => (int)DB::val("SELECT COUNT(*) FROM sales_orders WHERE tenant_id = ? AND status IN ('confirmed','partial')" . \Core\Scope::sql('warehouse_id'), [$t]),
            'transitOpen' => (int)DB::val("SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND status IN ('in_transit','partial')$mine", [$t]),
            'transitLate' => (int)DB::val("SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = 'transfer' AND status IN ('in_transit','partial') AND expected_date IS NOT NULL AND expected_date < CURDATE()$mine", [$t]),
            'lowCount' => $scoped ? 0 : Metrics::lowStockCount(), 'stockValue' => Metrics::stockValue(),
            'rolls' => (int)DB::val('SELECT COUNT(*) FROM (SELECT batch_id FROM stock_balances WHERE tenant_id = ? AND batch_id > 0' . \Core\Scope::sql('warehouse_id') . ' GROUP BY batch_id HAVING SUM(qty) > 0.0005) x', [$t]),
            'topItems' => $scoped ? [] : Metrics::topItems($p['from'], $p['to']),
            'byCategory' => Metrics::stockByCategory(),
            'ageRec' => $scoped ? [] : Metrics::ageing('sales'), 'agePay' => $scoped ? [] : Metrics::ageing('purchase'),
            'low' => $scoped ? [] : Metrics::lowStock(), 'overdue' => $scoped ? [] : Metrics::overdueInvoices(), 'orders' => $scoped ? [] : Metrics::openOrders(),
            'userCount' => User::count(), 'roleCount' => Role::count(),
            'u' => Auth::user(),
        ];
        // the new app draws the very same widgets the classic page does (charts included), so hand over their HTML
        if (\Core\Api::on() && !$scoped) {
            [$html, $spans] = $this->widgets($data);
            unset($html['setup']);
            $data += ['html' => $html, 'spans' => $spans, 'widgetNames' => self::WIDGETS, 'ranges' => ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'mtd' => 'This month', 'ytd' => 'This year']];
        }
        $this->view('app/dashboard', $data);
    }

    /** @return array{0: array<string,string>, 1: array<string,int>} each widget's HTML and its width in columns */
    private function widgets(array $data): array
    {
        extract($data, EXTR_SKIP);
        require ROOT . '/views/app/_dashboard_widgets.php';
        return [$render, $spans];
    }
}
