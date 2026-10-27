<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\Audit;
use Core\DB;

/** All supplier rates in one place (Purchase → Rate lists). Adding, revising and removing reuse the per-supplier routes. */
final class RateListController extends PurchaseBase
{
    public function index(): void
    {
        $t = $this->tid();
        $supplier = (int)($_GET['supplier'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $show = ($_GET['show'] ?? 'current') === 'all' ? 'all' : 'current';
        $where = 'r.tenant_id = ?';
        $p = [$t];
        if ($supplier) { $where .= ' AND r.supplier_id = ?'; $p[] = $supplier; }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ? OR r.supplier_code LIKE ? OR sp.supplier_code LIKE ? OR sp.supplier_name LIKE ? OR sp.design_no LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like, $like, $like);
        }
        $rows = Purchase::markCurrent(DB::all(
            "SELECT r.*, s.name AS supplier_name, v.sku, v.name AS vname, i.name AS item_name, i.id AS item_id, sp.supplier_name AS sname, COALESCE(sp.supplier_code, r.supplier_code) AS scode
             FROM supplier_rates r JOIN suppliers s ON s.id = r.supplier_id JOIN item_variants v ON v.id = r.variant_id JOIN items i ON i.id = v.item_id
             LEFT JOIN supplier_items sp ON sp.tenant_id = r.tenant_id AND sp.supplier_id = r.supplier_id AND sp.variant_id = r.variant_id
             WHERE $where ORDER BY i.name, v.name, r.variant_id, r.supplier_id, r.valid_from DESC, r.id DESC LIMIT 3000", $p));
        // lowest current net per variant (across the suppliers shown)
        // lowest current net roll rate per variant, in rupees (foreign rates at today's exchange rate), across the suppliers shown
        $low = [];
        foreach ($rows as &$r) { $r['net_inr'] = \App\Models\Currencies::toInr($r['net'], (string)$r['currency']); }
        unset($r);
        foreach ($rows as $r) if ($r['is_current'] && (!isset($low[$r['variant_id']]) || $r['net_inr'] < $low[$r['variant_id']])) $low[$r['variant_id']] = $r['net_inr'];
        $stats = ['current' => 0, 'earlier' => 0, 'suppliers' => [], 'items' => []];
        foreach ($rows as $r) {
            if ($r['is_current']) { $stats['current']++; $stats['suppliers'][$r['supplier_id']] = 1; $stats['items'][$r['variant_id']] = 1; } else $stats['earlier']++;
        }
        if ($show === 'current') $rows = array_values(array_filter($rows, fn($r) => $r['is_current']));
        $this->view('app/purchase/rates', ['title' => 'Rate lists', 'rows' => $rows, 'low' => $low, 'supplier' => $supplier, 'q' => $q, 'show' => $show,
            'stats' => ['current' => $stats['current'], 'earlier' => $stats['earlier'], 'suppliers' => count($stats['suppliers']), 'items' => count($stats['items'])],
            'suppliers' => DB::all('SELECT id, name, origin, currency FROM suppliers WHERE tenant_id = ? ORDER BY name', [$t]), 'currencies' => \App\Models\Currencies::all()]);
    }

    /** Saves the exchange rates (rupees for one unit) and adds a currency when asked. */
    public function saveCurrencies(): void
    {
        $back = 'purchase/rates';
        $d = $this->input();
        $t = $this->tid();
        foreach ((array)($d['rows'] ?? []) as $x) {
            if (!is_array($x)) continue;
            $code = strtoupper(trim((string)($x['code'] ?? '')));
            if ($code === \App\Models\Currencies::BASE || !\App\Models\Currencies::get($code)) continue;
            $rate = trim((string)($x['rate_to_inr'] ?? ''));
            if (!is_numeric($rate) || (float)$rate <= 0 || (float)$rate > 1000000) $this->bounce("$code: the exchange rate must be a number above zero (rupees for one $code).", $back);
            DB::run('UPDATE currencies SET rate_to_inr = ?, is_active = ? WHERE tenant_id = ? AND code = ?', [round((float)$rate, 6), !empty($x['is_active']) ? 1 : 0, $t, $code]);
        }
        $n = (array)($d['new'] ?? []);
        $code = strtoupper(trim((string)($n['code'] ?? '')));
        if ($code !== '') {
            $rate = trim((string)($n['rate_to_inr'] ?? ''));
            if (!preg_match('/^[A-Z]{3}$/', $code)) $this->bounce('A currency code is three letters, for example CHF.', $back);
            if (\App\Models\Currencies::get($code)) $this->bounce("$code is already in the list.", $back);
            if (!is_numeric($rate) || (float)$rate <= 0 || (float)$rate > 1000000) $this->bounce('Enter the exchange rate of the new currency (rupees for one unit).', $back);
            $name = mb_substr(trim((string)($n['name'] ?? '')), 0, 40) ?: $code;
            DB::run('INSERT INTO currencies (tenant_id, code, name, symbol, rate_to_inr, is_active) VALUES (?,?,?,?,?,1)', [$t, $code, $name, mb_substr(trim((string)($n['symbol'] ?? '')), 0, 8) ?: $code, round((float)$rate, 6)]);
        }
        Audit::log('currencies_save', 'currency', null, 'exchange rates');
        flash('success', 'Exchange rates saved. New purchase orders use them; orders already made keep the rate they were made at.');
        redirect($back);
    }
}
