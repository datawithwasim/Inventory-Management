<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Sales;
use App\Models\Stock;
use App\Models\StockException;
use Core\Audit;
use Core\Auth;
use Core\DB;

/** Counter sale: scan items, take payment, stock is picked automatically and an invoice is printed. */
final class PosController extends SalesBase
{
    public function index(): void
    {
        $this->view('app/pos/index', [
            'title' => 'Point of sale', 'customers' => $this->customers(), 'warehouses' => $this->warehouses(), 'walkIn' => Sales::walkIn(), 'methods' => Purchase::METHODS,
        ], 'layouts/pos');
    }

    private function fail(string $msg): never
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }

    public function checkout(): void
    {
        $d = $this->input();
        $customer = Sales::customer((int)($d['customer_id'] ?? 0));
        if (!$customer || !$customer['is_active']) $this->fail('Choose a valid customer.');
        $wh = Stock::warehouse((int)($d['warehouse_id'] ?? 0));
        if (!$wh || !$wh['is_active']) $this->fail('Choose a valid warehouse.');

        $lines = [];
        $seen = [];
        foreach ((array)($d['lines'] ?? []) as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            $v = $this->sellable((int)($l['variant_id'] ?? 0));
            if (!$v) $this->fail("$prefix: item not found.");
            if (isset($seen[$v['id']])) $this->fail("$prefix: " . $v['item_name'] . ' is in the cart twice.');
            $seen[$v['id']] = 1;
            $qty = trim((string)($l['qty'] ?? ''));
            if ($qty === '' || !is_numeric($qty) || (float)$qty <= 0) $this->fail("$prefix: enter a quantity above zero.");
            $q = Stock::round((float)$qty);
            if (!$v['allow_decimal'] && floor($q) != $q) $this->fail("$prefix: " . $v['item_name'] . ' is sold in whole ' . $v['unit'] . '.');
            $price = trim((string)($l['unit_price'] ?? ''));
            $disc = trim((string)($l['discount_pct'] ?? ''));
            $tax = trim((string)($l['tax_rate'] ?? ''));
            foreach ([[$price, 'price', 1e9], [$disc, 'discount', 100], [$tax, 'tax', 100]] as [$val, $what, $max]) {
                if ($val !== '' && (!is_numeric($val) || (float)$val < 0 || (float)$val > $max)) $this->fail("$prefix: $what is not valid.");
            }
            $lines[] = ['variant_id' => (int)$v['id'], 'qty' => $q, 'price' => (float)$price, 'disc' => (float)$disc, 'tax' => (float)$tax, 'auto' => true];
        }
        if (!$lines) $this->fail('The cart is empty.');
        $method = (string)($d['method'] ?? 'cash');
        if (!isset(Purchase::METHODS[$method])) $this->fail('Choose a payment method.');
        $paid = trim((string)($d['amount'] ?? ''));
        if ($paid !== '' && (!is_numeric($paid) || (float)$paid < 0)) $this->fail('The amount received is not valid.');

        try {
            $invoiceId = DB::transaction(function () use ($customer, $wh, $lines, $method, $paid, $d) {
                $deliveryId = Sales::postDelivery(['customer_id' => $customer['id'], 'customer_name' => $customer['name'], 'warehouse_id' => $wh['id'], 'delivery_date' => date('Y-m-d'),
                    'note' => 'Counter sale', 'source' => 'pos'], $lines);
                $invoiceId = Sales::createInvoice($deliveryId);
                $inv = DB::one('SELECT total FROM sales_invoices WHERE tenant_id = ? AND id = ?', [$this->tid(), $invoiceId]);
                $take = min((float)$paid, (float)$inv['total']);
                if ($take > 0.004) {
                    DB::insert('customer_payments', ['tenant_id' => $this->tid(), 'customer_id' => $customer['id'], 'invoice_id' => $invoiceId, 'amount' => round($take, 2), 'paid_on' => date('Y-m-d'),
                        'method' => $method, 'reference' => trim((string)($d['reference'] ?? '')) ?: null, 'created_by' => Auth::user()['id']]);
                    Sales::refreshPaid($invoiceId);
                }
                return $invoiceId;
            });
        } catch (StockException $e) {
            $this->fail($e->getMessage());
        }
        Audit::log('pos_sale', 'sales_invoice', $invoiceId);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'url' => url("sales/invoices/$invoiceId/print?receipt=1"), 'invoice' => $invoiceId]);
        exit;
    }
}
