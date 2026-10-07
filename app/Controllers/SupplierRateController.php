<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\Audit;
use Core\DB;

/** A supplier's rate list: what they charge for each item, with history. */
final class SupplierRateController extends PurchaseBase
{
    private function supplier(string $id): array
    {
        return Purchase::supplier((int)$id) ?? $this->notFound();
    }

    /** Where to go afterwards: a relative path the form asked for (e.g. the global rate list), else the supplier's rate card. */
    private function back(int $supplierId, string $anchor = 'rates'): never
    {
        $r = trim((string)($this->input()['return'] ?? ''));
        redirect(preg_match('~^[a-z0-9_/-]+(\?[A-Za-z0-9_=&%.\-]*)?(#[a-z]+)?$~i', $r) === 1 ? $r : "suppliers/$supplierId#$anchor");
    }

    private function fail(string $msg, int $supplierId, string $anchor = 'rates'): never
    {
        flash('danger', $msg);
        $this->back($supplierId, $anchor);
    }

    /** Checks one rate row; returns the clean values or an error string. */
    private function clean(array $d): array|string
    {
        $num = fn($k) => trim((string)($d[$k] ?? ''));
        $rate = $num('rate');
        if ($rate === '' || !is_numeric($rate) || (float)$rate < 0 || (float)$rate > 99999999) return 'Rate must be a number, zero or more.';
        $disc = $num('discount_pct');
        if ($disc !== '' && (!is_numeric($disc) || (float)$disc < 0 || (float)$disc > 100)) return 'Discount must be between 0 and 100.';
        $min = $num('min_qty');
        if ($min !== '' && (!is_numeric($min) || (float)$min < 0)) return 'Minimum quantity must be zero or more.';
        $lead = $num('lead_time_days');
        if ($lead !== '' && (!ctype_digit($lead) || (int)$lead > 365)) return 'Lead time must be 0 to 365 days.';
        $from = $num('valid_from') ?: date('Y-m-d');
        $to = $num('valid_to');
        foreach ([$from, $to] as $dt) {
            if ($dt !== '' && !(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dt, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]))) return 'A date is not valid.';
        }
        if ($to !== '' && $to < $from) return '"Valid till" cannot be before "Valid from".';
        $code = $num('supplier_code');
        if (mb_strlen($code) > 60) return "Supplier's own code is too long.";
        return ['rate' => round((float)$rate, 2), 'discount_pct' => $disc === '' ? 0.0 : round((float)$disc, 2), 'min_qty' => $min === '' ? 0.0 : (float)$min,
            'lead_time_days' => $lead === '' ? null : (int)$lead, 'supplier_code' => $code !== '' ? $code : null, 'valid_from' => $from, 'valid_to' => $to !== '' ? $to : null, 'note' => null];
    }

    public function store(string $id): void
    {
        $s = $this->supplier($id);
        $d = $this->input();
        $vid = (int)($d['variant_id'] ?? 0);
        if (!DB::val('SELECT 1 FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ? AND i.is_bundle = 0', [$this->tid(), $vid])) $this->fail('Pick an item from the list.', (int)$s['id']);
        $row = $this->clean($d);
        if (is_string($row)) $this->fail($row, (int)$s['id']);
        Purchase::addRate((int)$s['id'], $vid, $row);
        Purchase::saveProduct((int)$s['id'], $vid, (string)($d['supplier_name'] ?? ''), $row['supplier_code']);
        Audit::log('supplier_rate_add', 'supplier', (int)$s['id'], 'variant ' . $vid . ' @ ' . $row['rate']);
        flash('success', 'Rate saved.');
        $this->back((int)$s['id']);
    }

    public function destroy(string $id, string $rateId): void
    {
        $s = $this->supplier($id);
        DB::run('DELETE FROM supplier_rates WHERE tenant_id = ? AND supplier_id = ? AND id = ?', [$this->tid(), $s['id'], (int)$rateId]);
        Audit::log('supplier_rate_delete', 'supplier', (int)$s['id'], 'rate ' . (int)$rateId);
        flash('success', 'Rate removed.');
        $this->back((int)$s['id']);
    }

    public function template(string $id): void
    {
        $this->supplier($id);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="supplier-rates-template.csv"');
        echo "sku,rate,discount_pct,min_qty,lead_time_days,supplier_code,supplier_name,valid_from\nCURTAIN-001,285.00,5,100,7,D-4471,Royal Velvet 54," . date('Y-m-d') . "\n";
        exit;
    }

    public function import(string $id): void
    {
        $s = $this->supplier($id);
        $sid = (int)$s['id'];
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) $this->fail('Choose a CSV file to import.', $sid);
        if ($f['size'] > 2 * 1024 * 1024) $this->fail('The file is larger than 2 MB.', $sid);
        $h = fopen($f['tmp_name'], 'r');
        $head = fgetcsv($h, 0, ',', '"', '\\');
        if (!$head) $this->fail('The file is empty.', $sid);
        $head = array_map(fn($x) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$x))), $head);
        if (!in_array('sku', $head, true) || !in_array('rate', $head, true)) $this->fail('The first row must contain the columns "sku" and "rate".', $sid);
        $t = $this->tid();
        $ok = 0; $bad = [];
        $line = 1;
        DB::transaction(function () use ($h, $head, $t, $sid, &$ok, &$bad, &$line) {
            while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
                $line++;
                if ($line > 2001) { $bad[] = 'Stopped at 2000 rows.'; break; }
                if (!array_filter($r, fn($x) => trim((string)$x) !== '')) continue;
                $d = [];
                foreach ($head as $i => $k) $d[$k] = $r[$i] ?? '';
                $sku = trim((string)($d['sku'] ?? ''));
                $vid = (int)DB::val('SELECT v.id FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.sku = ? AND i.is_bundle = 0', [$t, $sku]);
                if (!$vid) { $bad[] = "Row $line: SKU \"$sku\" not found."; continue; }
                $row = $this->clean($d);
                if (is_string($row)) { $bad[] = "Row $line: $row"; continue; }
                Purchase::addRate($sid, $vid, $row);
                Purchase::saveProduct($sid, $vid, (string)($d['supplier_name'] ?? ''), $row['supplier_code']);
                $ok++;
            }
        });
        fclose($h);
        Audit::log('supplier_rate_import', 'supplier', $sid, "$ok rates");
        flash($bad ? ($ok ? 'warning' : 'danger') : 'success', "$ok rate(s) imported" . ($bad ? ', ' . count($bad) . ' skipped. ' . implode(' ', array_slice($bad, 0, 5)) : '.'));
        $this->back($sid);
    }
}
