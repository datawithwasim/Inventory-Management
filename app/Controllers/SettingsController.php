<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrintTemplate;
use App\Models\SettingsNav;
use App\Models\CustomFields;
use Core\FormDesign;
use Core\FormFields;
use Core\Modules;
use Core\Theme;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Numbering;
use Core\Settings;

/** Company settings: profile, preferences, numbering, workflow rules, labels and print templates. */
final class SettingsController extends Controller
{
    private const DATE_FORMATS = ['Y-m-d' => 'YYYY-MM-DD', 'd-m-Y' => 'DD-MM-YYYY', 'd/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'd M Y' => 'DD Mon YYYY'];

    private function bounce(string $msg, string $tab): never
    {
        flash('danger', $msg);
        with_old($_POST);
        redirect("settings/$tab");
    }

    private function done(string $tab, string $msg): never
    {
        Audit::log('settings_' . $tab, 'settings');
        flash('success', $msg);
        redirect("settings/$tab");
    }

    public function index(): void
    {
        redirect('settings/company');
    }

    /** A same-site path to come back to after editing a layout; anything else is dropped. */
    private static function safeReturn(string $r): string
    {
        $r = trim($r);
        return ($r !== '' && strlen($r) <= 500 && $r[0] === '/' && !str_starts_with($r, '//') && !preg_match('/[\x00-\x1f\\\\]/', $r)) ? $r : '';
    }

    public function show(string $tab): void
    {
        if ($tab === 'fields') redirect('settings/formdesign');
        if ($tab === 'formfields') redirect('settings/formdesign');
        if (!SettingsNav::valid($tab) && $tab !== 'formdesign') $this->notFound();
        if ($tab === 'formdesign') {
            $return = self::safeReturn((string)($_GET['return'] ?? ''));
            $this->view('app/settings/formdesign', ['title' => 'Page layout', 'model' => FormDesign::model(), 'widths' => FormDesign::WIDTHS, 'types' => CustomFields::TYPES, 'lookups' => \Core\Lookups::labels(),
                'start' => (string)($_GET['form'] ?? ''), 'colsOpts' => FormDesign::COLS, 'return' => $return, 'backHref' => $return !== '' ? $return : url('settings/company')], 'layouts/designer');
            return;
        }
        $data = ['title' => 'Settings'];
        switch ($tab) {
            case 'company':
                $data['tenantName'] = company_name();
                $data['values'] = ['name' => $data['tenantName'], 'logo' => company_logo_url()];
                foreach (['legal_name', 'address', 'phone', 'email', 'website', 'tax_no', 'bank'] as $k) $data['values'][$k] = Settings::get('company.' . $k);
                break;
            case 'preferences':
                $data['dateFormats'] = self::DATE_FORMATS;
                $data['values'] = ['symbol' => Settings::get('currency.symbol'), 'position' => Settings::get('currency.position'), 'decimals' => (string)Settings::get('currency.decimals'),
                    'grouping' => Settings::get('number.grouping'), 'date_format' => Settings::get('date.format')];
                $data['today'] = [];
                foreach (self::DATE_FORMATS as $k => $l) $data['today'][$k] = date($k);
                break;
            case 'numbering':
                $data['docs'] = [];
                foreach (Numbering::DOCS as $code => $label) {
                    $cfg = Numbering::config($code);
                    $next = (int)DB::val('SELECT next_no FROM counters WHERE prefix = ?', [$code . ($cfg['year'] ? date('y') : '')]) ?: 1;
                    $data['docs'][$code] = ['label' => $label, 'cfg' => $cfg, 'next' => $next, 'sample' => Numbering::format($cfg, $next)];
                }
                break;
            case 'appearance':
                $data += ['brands' => Theme::BRANDS, 'sidebars' => Theme::SIDEBARS, 'densities' => Theme::DENSITY, 'modes' => Theme::MODES];
                $data['values'] = ['brand' => strtolower((string)Theme::get('appearance.brand')), 'sidebar' => Theme::get('appearance.sidebar'), 'density' => Theme::get('appearance.density'), 'mode' => Theme::get('appearance.mode')];
                break;
            case 'workflow':
                $data['values'] = ['po_approval' => Settings::get('po_approval'), 'negative_stock' => Settings::get('negative_stock'), 'require_rack' => Settings::get('require_rack'),
                    'shade_rule' => Settings::get('shade_rule'), 'max_discount' => Settings::get('max_discount')];
                break;
            case 'modules':
                $data['modules'] = Modules::ALL;
                $data['off'] = Modules::off();
                break;
            case 'formfields':
                $data += ['registry' => FormFields::REGISTRY, 'entityLabels' => FormFields::ENTITY_LABELS, 'sections' => FormFields::SECTIONS];
                break;
            case 'labels':
                $data['terms'] = TERMS;
                $data['saved'] = Settings::json('labels', []);
                break;
            case 'system':
                if (!Auth::user()['is_owner']) $this->forbidden();
                $data += ['pending' => \Core\Migrator::pending(), 'applied' => DB::all('SELECT name, applied_at FROM migrations ORDER BY name'),
                    'info' => ['PHP' => PHP_VERSION, 'Database' => DB::pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), 'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'],
                    'logs' => DB::all('SELECT a.id, a.created_at, a.action, a.entity, a.details, a.ip, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id AND a.actor_type = \'user\' ORDER BY a.id DESC LIMIT 200')];
                break;
            case 'templates':
                $doc = (string)($_GET['doc'] ?? 'invoice');
                if (!isset(PrintTemplate::DOCS[$doc])) $doc = 'invoice';
                $data += ['docs' => PrintTemplate::DOCS, 'doc' => $doc, 'fields' => PrintTemplate::fields($doc), 'values' => PrintTemplate::get($doc), 'previewUrl' => $this->previewUrl($doc), 'templates' => \App\Models\PrintDoc::list($doc), 'latest' => \App\Models\PrintDoc::latestId($doc)];
                break;
        }
        $this->settingsView('app/settings/' . $tab, $data, $tab);
    }

    private function previewUrl(string $doc): ?string
    {
        $id = match ($doc) {
            'invoice' => DB::val('SELECT MAX(id) FROM sales_invoices'),
            'delivery_note' => DB::val('SELECT MAX(id) FROM deliveries'),
            'purchase_order' => DB::val('SELECT MAX(id) FROM purchase_orders'),
        };
        if (!$id) return null;
        return match ($doc) {
            'invoice' => url("sales/invoices/$id/print"), 'delivery_note' => url("sales/deliveries/$id/print"),
            'purchase_order' => url("purchase/orders/$id/print"),
        };
    }

    public function save(string $tab): void
    {
        $d = $_POST;
        switch ($tab) {
            case 'company':
                $this->saveCompany($d);
            case 'preferences':
                $sym = trim((string)($d['symbol'] ?? ''));
                if (mb_strlen($sym) > 8) $this->bounce('The currency symbol is too long (max 8 characters).', $tab);
                $dec = (string)($d['decimals'] ?? '2');
                if (!in_array($dec, ['0', '1', '2', '3'], true)) $this->bounce('Choose 0 to 3 decimal places.', $tab);
                $fmt = (string)($d['date_format'] ?? 'Y-m-d');
                if (!isset(self::DATE_FORMATS[$fmt])) $this->bounce('Choose a date format from the list.', $tab);
                Settings::set('currency.symbol', $sym);
                Settings::set('currency.position', ($d['position'] ?? '') === 'after' ? 'after' : 'before');
                Settings::set('currency.decimals', $dec);
                Settings::set('number.grouping', ($d['grouping'] ?? '') === 'indian' ? 'indian' : 'intl');
                Settings::set('date.format', $fmt);
                $this->done($tab, 'Currency and date formats saved.');
            case 'numbering':
                foreach (Numbering::DOCS as $code => $label) {
                    $row = (array)($d['num'][$code] ?? []);
                    $prefix = strtoupper(trim((string)($row['prefix'] ?? '')));
                    if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) $this->bounce("$label: the prefix must be 1–10 letters or digits.", $tab);
                    $pad = (int)($row['pad'] ?? 4);
                    if ($pad < 3 || $pad > 8) $this->bounce("$label: use 3 to 8 digits.", $tab);
                    Settings::setJson('numbering.' . $code, ['prefix' => $prefix, 'pad' => $pad, 'year' => empty($row['year']) ? 0 : 1]);
                }
                $this->done($tab, 'Document numbers saved. New documents use them from now on; old documents keep their numbers.');
            case 'appearance':
                $brand = strtolower(trim((string)($d['brand_custom'] ?? '')) ?: trim((string)($d['brand'] ?? '')));
                if (!preg_match('/^#[0-9a-f]{6}$/', $brand)) $this->bounce('Choose a colour from the list, or enter a colour like #0d9488.', $tab);
                $sb = (string)($d['sidebar'] ?? '');
                $den = (string)($d['density'] ?? '');
                $mode = (string)($d['mode'] ?? '');
                if (!isset(Theme::SIDEBARS[$sb]) || !isset(Theme::DENSITY[$den]) || !isset(Theme::MODES[$mode])) $this->bounce('Please choose one option in each group.', $tab);
                Settings::set('appearance.brand', $brand);
                Settings::set('appearance.sidebar', $sb);
                Settings::set('appearance.density', $den);
                Settings::set('appearance.mode', $mode);
                $this->done($tab, 'Appearance saved.');
            case 'modules':
                $on = array_map('strval', (array)($d['on'] ?? []));
                $off = array_values(array_diff(array_keys(Modules::ALL), $on));
                Modules::save($off);
                $this->done($tab, $off ? 'Saved. Switched-off parts are hidden from the menu.' : 'Saved. Everything is switched on.');
            case 'formfields':
                $hidden = $required = [];
                foreach (FormFields::REGISTRY as $entity => $cols) {
                    foreach ($cols as $col => $label) {
                        if (empty($d['show'][$entity][$col])) $hidden[] = "$entity.$col";
                        elseif (!empty($d['req'][$entity][$col])) $required[] = "$entity.$col";
                    }
                }
                FormFields::save($hidden, $required);
                $this->done($tab, 'Form fields saved. The forms now follow your choices.');
            case 'formdesign':
                $payload = json_decode((string)($d['payload'] ?? ''), true);
                if (!is_array($payload)) $this->bounce('Nothing to save — please try again.', $tab);
                $notes = FormDesign::savePayload($payload);
                Audit::log('settings_formdesign', 'settings');
                $msg = 'Forms saved. They now look the way you designed them.';
                if ($notes['created'] || $notes['deleted']) $msg .= ' (' . $notes['created'] . ' field(s) added, ' . $notes['deleted'] . ' deleted.)';
                flash('success', $msg);
                $ret = self::safeReturn((string)($d['return'] ?? ''));
                if (!empty($d['close'])) {
                    if ($ret !== '') { header('Location: ' . $ret); exit; }
                    redirect('settings/company');
                }
                redirect('settings/formdesign?form=' . rawurlencode((string)($d['current'] ?? '')) . ($ret !== '' ? '&return=' . rawurlencode($ret) : ''));
            case 'workflow':
                Settings::set('po_approval', empty($d['po_approval']) ? '0' : '1');
                Settings::set('negative_stock', empty($d['negative_stock']) ? '0' : '1');
                Settings::set('require_rack', empty($d['require_rack']) ? '0' : '1');
                Settings::set('shade_rule', ($d['shade_rule'] ?? '') === 'block' ? 'block' : 'warn');
                $disc = trim((string)($d['max_discount'] ?? '0'));
                if ($disc === '') $disc = '0';
                if (!is_numeric($disc) || (float)$disc < 0 || (float)$disc > 100) $this->bounce('The discount limit must be between 0 and 100 (0 means no limit).', $tab);
                Settings::set('max_discount', (string)round((float)$disc, 2));
                $this->done($tab, 'Rules saved.');
            case 'labels':
                $out = [];
                foreach (TERMS as $key => [$sing, $plur]) {
                    $one = trim((string)($d['label'][$key][0] ?? ''));
                    $many = trim((string)($d['label'][$key][1] ?? ''));
                    foreach ([$one, $many] as $w) {
                        if ($w !== '' && !preg_match('/^[\p{L}\p{N} \'&.\-]{1,30}$/u', $w)) $this->bounce('Names can use letters, numbers and spaces (up to 30 characters).', $tab);
                    }
                    $one = $one !== '' ? $one : $sing;
                    $many = $many !== '' ? $many : ($one === $sing ? $plur : $one . 's');
                    if ($one !== $sing || $many !== $plur) $out[$key] = [$one, $many];
                }
                Settings::setJson('labels', $out);
                $this->done($tab, 'Names saved. Screens now use your words.');
            case 'templates':
                $doc = (string)($d['doc'] ?? '');
                if (!isset(PrintTemplate::DOCS[$doc])) $this->notFound();
                [$vals, $errors] = PrintTemplate::clean($doc, $d);
                if ($errors) {
                    foreach ($errors as $e) flash('danger', $e);
                    with_old($d);
                    redirect("settings/templates?doc=$doc");
                }
                Settings::setJson('template.' . $doc, $vals);
                Audit::log('settings_template', 'settings', null, $doc);
                flash('success', PrintTemplate::DOCS[$doc] . ' template saved.');
                redirect("settings/templates?doc=$doc");
        }
        $this->notFound();
    }

    private function saveCompany(array $d): never
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) $this->bounce('Company name is required (max 150 characters).', 'company');
        $limits = ['legal_name' => 150, 'address' => 500, 'phone' => 60, 'email' => 190, 'website' => 190, 'tax_no' => 60, 'bank' => 500];
        $vals = [];
        foreach ($limits as $k => $max) {
            $v = trim((string)($d[$k] ?? ''));
            if (mb_strlen($v) > $max) $this->bounce("That value is too long (max $max characters).", 'company');
            $vals[$k] = $v;
        }
        if ($vals['email'] !== '' && !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $this->bounce('The company email is not valid.', 'company');

        $logoNote = '';
        if (!empty($_FILES['logo']['name'])) {
            $err = $this->storeLogo($_FILES['logo']);
            if ($err) $this->bounce($err, 'company');
            $logoNote = ' Logo updated.';
        } elseif (!empty($d['remove_logo'])) {
            $this->removeLogo();
            $logoNote = ' Logo removed.';
        }
        Settings::set('company.name', $name);
        foreach ($vals as $k => $v) Settings::set('company.' . $k, $v);
        Audit::log('settings_company', 'settings');
        flash('success', 'Company profile saved.' . $logoNote);
        redirect('settings/company');
    }

    private function storeLogo(array $f): ?string
    {
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return 'The logo could not be uploaded. Try a smaller image.';
        if ($f['size'] > 1024 * 1024) return 'The logo must be 1 MB or smaller.';
        $info = @getimagesize($f['tmp_name']);
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]])) return 'The logo must be a PNG, JPG, GIF or WebP image.';
        if ($info[0] > 3000 || $info[1] > 3000) return 'The logo is too large (max 3000 × 3000 pixels).';
        $dir = ROOT . '/storage/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'The server could not create the upload folder.';
        $name = 'logo-' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return 'The logo could not be saved on the server.';
        $this->removeLogo();
        Settings::set('company.logo', $name);
        return null;
    }

    private function removeLogo(): void
    {
        $old = Settings::get('company.logo');
        if ($old !== '' && preg_match('/^logo-(\d+-)?[a-f0-9]+\.(png|jpg|gif|webp)$/', $old)) @unlink(ROOT . '/storage/uploads/' . $old);
        Settings::set('company.logo', '');
    }

    /** Applies the waiting database updates (owner only; no terminal on shared hosting). */
    public function migrate(): void
    {
        if (!Auth::user()['is_owner']) $this->forbidden();
        try {
            $done = \Core\Migrator::run();
        } catch (\Throwable $e) {
            flash('danger', 'Update failed: ' . $e->getMessage());
            redirect('settings/system');
        }
        Settings::flush();
        if ($done) Audit::log('db_migrate', 'system', null, implode(', ', $done));
        flash('success', $done ? 'Database updated: ' . implode(', ', $done) : 'Already up to date.');
        redirect('settings/system');
    }

    /** Full database backup as a download (POST so it needs the CSRF token and cannot be triggered by a link). */
    public function backup(): void
    {
        if (!Auth::user()['is_owner']) $this->forbidden();
        @set_time_limit(0);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory-backup-' . date('Ymd-His') . '.sql"');
        header('Cache-Control: no-store');
        $rows = \Core\Backup::stream(function (string $chunk) {
            echo $chunk;
            if (PHP_SAPI !== 'cli') { @ob_flush(); flush(); }
        });
        Audit::log('db_backup', 'system', null, "$rows rows");
        exit;
    }

    /** Streams the company logo. */
    public function logo(): void
    {
        $name = Settings::get('company.logo');
        $file = ROOT . '/storage/uploads/' . $name;
        if ($name === '' || !preg_match('/^logo-(\d+-)?[a-f0-9]+\.(png|jpg|gif|webp)$/', $name) || !is_file($file)) $this->notFound();
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)];
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
