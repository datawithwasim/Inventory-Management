<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomFields;
use App\Models\ItemTypes;
use App\Models\Purchase;
use Core\Api;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\FormDesign;
use Core\Menu;
use Core\Prefs;
use Core\RecordView;
use Core\Theme;

/** Endpoints the React app needs besides the page routes: who is signed in, form schemas, pick lists, record pages. */
final class ApiController extends Controller
{
    /** Who is signed in and what they may see. Safe to call signed-out (it hands out the CSRF token the login form needs). */
    public function session(): void
    {
        $u = Auth::user();
        $out = ['ok' => true, 'authenticated' => (bool)$u, 'csrf' => csrf_token(), 'base' => rtrim(url(''), '/'),
            // signed out, this is the product's own colours: the sign-in page must not keep the colours of the company that just left
            'themeCss' => preg_replace('~</?style[^>]*>~', '', Theme::css())];
        if ($u) {
            $perms = $u['is_owner'] ? ['*'] : array_column(DB::all('SELECT permission FROM role_permissions WHERE role_id = ?', [$u['role_id']]), 'permission');
            $terms = [];
            foreach (TERMS as $k => $pair) $terms[$k] = [term($k), term($pair[1])];
            $expiry = $u['tenant_status'] === 'trial' ? $u['trial_ends_at'] : $u['subscription_ends_at'];
            $out += [
                'user' => ['name' => $u['name'], 'email' => $u['email'] ?? '', 'role' => $u['role_name'], 'owner' => (bool)$u['is_owner'],
                    'warehouse' => \Core\Scope::restricted() ? ['id' => \Core\Scope::warehouse(), 'name' => \Core\Scope::name()] : null],
                'tenant' => ['name' => $u['tenant_name'], 'plan' => $u['plan_name'], 'status' => $u['tenant_status'], 'expires' => $expiry, 'logo' => company_logo_url()],
                'perms' => $perms, 'menu' => Menu::sidebar(), 'quick' => Menu::quick(), 'terms' => $terms, 'supportMode' => Auth::impersonating(),
                'fmt' => ['decimals' => max(0, min(3, (int)\Core\Settings::get('currency.decimals'))), 'grouping' => \Core\Settings::get('number.grouping'), 'symbol' => \Core\Settings::get('currency.symbol'),
                    'position' => \Core\Settings::get('currency.position'), 'date' => \Core\Settings::get('date.format') ?: 'Y-m-d'],
                'stockTypes' => StockController::TYPES, 'poStatus' => Purchase::PO_STATUS, 'soStatus' => \App\Models\Sales::ORDER_STATUS, 'payStatus' => Purchase::PAY_STATUS, 'payMethods' => Purchase::METHODS,
                'theme' => ['brand' => Theme::get('appearance.brand'), 'sidebar' => Theme::get('appearance.sidebar'), 'density' => Theme::get('appearance.density'), 'mode' => Theme::get('appearance.mode')],
            ];
        }
        Api::send($out);
    }

    /** The column picker of a list page: every choice and the ones switched on. */
    /** Is a platform administrator signed in? Safe signed-out too (the Super Admin sign-in page asks first). */
    public function adminSession(): void
    {
        $a = Auth::admin();
        $out = ['ok' => true, 'authenticated' => (bool)$a, 'csrf' => csrf_token()];
        if ($a) $out += ['admin' => ['name' => $a['name'], 'email' => $a['email'] ?? ''], 'pending' => count(\Core\Migrator::pending())];
        Api::send($out);
    }

    public function columns(string $list): void
    {
        if (!isset(\Core\Columns::LISTS[$list])) $this->notFound();
        Api::send(['ok' => true, 'options' => \Core\Columns::options($list), 'visible' => \Core\Columns::visible($list)]);
    }

    public function schema(string $entity): void
    {
        if (!isset(FormDesign::FIELDS[$entity])) $this->notFound();
        $perm = RecordView::META[$entity][0] ?? null;
        if ($perm && !Auth::can($perm) && !Auth::can(explode('.', $perm)[0] . '.view')) $this->forbidden();
        Api::send(['ok' => true, 'schema' => Api::clean(FormDesign::schema($entity))]);
    }

    /** Pick-list contents for the form selects. */
    public function options(string $source): void
    {
        $t = (int)Auth::tenantId();
        $rows = match ($source) {
            'suppliers', 'customers' => DB::all("SELECT id, name FROM $source WHERE tenant_id = ? AND is_active = 1 ORDER BY name", [$t]),
            // a warehouse user only picks their own warehouse; "all_warehouses" is for the receiving side of a transfer
            'warehouses' => DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1' . \Core\Scope::sql('id') . ' ORDER BY is_default DESC, name', [$t]),
            'all_warehouses' => DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name', [$t]),
            'customer_groups' => DB::all('SELECT id, CONCAT(name, IF(discount_pct > 0, CONCAT(" (", TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM discount_pct)), "% off)"), "")) AS name FROM customer_groups WHERE tenant_id = ? ORDER BY name', [$t]),
            'categories', 'brands' => DB::all("SELECT id, name FROM $source WHERE tenant_id = ? ORDER BY name", [$t]),
            'units' => DB::all('SELECT id, CONCAT(name, " (", short_name, ")") AS name FROM units WHERE tenant_id = ? ORDER BY name', [$t]),
            'taxes' => DB::all('SELECT id, CONCAT(name, " (", TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM rate)), "%)") AS name FROM taxes WHERE tenant_id = ? ORDER BY name', [$t]),
            'item_types' => array_map(fn($k, $l) => ['id' => $k, 'name' => $l], array_keys(ItemTypes::LABELS), ItemTypes::LABELS),
            'supplier_types' => array_map(fn($k, $l) => ['id' => $k, 'name' => $l], array_keys(Purchase::SUPPLIER_TYPES), Purchase::SUPPLIER_TYPES),
            'stock_reasons' => array_map(fn($k, $l) => ['id' => $k, 'name' => $l], array_keys(\App\Controllers\AdjustmentController::REASONS), \App\Controllers\AdjustmentController::REASONS),
            default => $this->notFound(),
        };
        Api::send(['ok' => true, 'options' => $rows]);
    }

    /** A record's designed page as data (summary strip, sections), its timeline and notes. */
    public function record(string $entity, string $id): void
    {
        $meta = RecordView::META[$entity] ?? $this->notFound();
        if (!Auth::can($meta[0]) && !Auth::can(explode('.', $meta[0])[0] . '.view')) $this->forbidden();
        $row = DB::one("SELECT * FROM `{$meta[1]}` WHERE tenant_id = ? AND id = ?", [Auth::tenantId(), (int)$id]) ?? $this->notFound();
        $cf = isset(CustomFields::ENTITIES[$entity]) ? CustomFields::values($entity, (int)$id) : [];
        [$row, $facts] = RecordView::prepare($entity, $row);
        Api::send(['ok' => true, 'record' => Api::clean(RecordView::payload($entity, $row, $cf, $facts)), 'timeline' => RecordView::timelineRows($entity, (int)$id),
            'notes' => Api::clean(RecordView::notes($entity, (int)$id)), 'canNote' => Auth::can($meta[0])]);
    }
}
