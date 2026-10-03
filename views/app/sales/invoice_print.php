<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("sales/invoices/{$i['id']}") ?>">Back to invoice</a>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('settings/templates?doc=' . (!empty($receipt) ? 'receipt' : 'invoice')) ?>">Change this layout</a></div>
<?php $docTitle = !empty($receipt) ? 'Receipt' : 'Tax invoice'; $docNo = $i['invoice_no']; $docDate = $i['invoice_date']; $meta = ['Due' => fdate($i['due_date'] ?? null), 'Order' => $i['order_no'] ?? null]; require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="mb-3"><div class="small text-muted">Billed to</div><strong><?= e($i['customer']) ?></strong>
  <?php if ($i['cust_address']): ?><div><?= e($i['cust_address']) ?></div><?php endif; ?><?php if ($i['cust_phone']): ?><div><?= e($i['cust_phone']) ?></div><?php endif; ?><?php if ($i['cust_tax']): ?><div>Tax no. <?= e($i['cust_tax']) ?></div><?php endif; ?>
  <?php if ($i['ship_to'] && !empty($tpl['show_ship_to']) && empty($receipt)): ?><div class="small text-muted mt-1">Deliver to: <?= e($i['ship_to']) ?></div><?php endif; ?></div>
<?php $showDisc = !empty($tpl['show_discount']) && empty($receipt); $showTax = !empty($tpl['show_tax']) && empty($receipt); ?>
<table class="table table-sm align-middle doc-table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><?= $showDisc ? '<th class="text-end">Disc</th>' : '' ?><?= $showTax ? '<th class="text-end">Tax</th>' : '' ?><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $l): if ($l['parent_id'] !== null) continue; [, $dsc, $net, $tx, $tot] = App\Models\Sales::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?><?= $l['batch_no'] && !empty($tpl['show_batch']) ? '<div class="small text-muted">Roll ' . e($l['batch_no']) . '</div>' : '' ?></td>
      <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['unit_price'])) ?></td>
      <?= $showDisc ? '<td class="text-end">' . ($dsc > 0 ? e(money($dsc)) : '') . '</td>' : '' ?><?= $showTax ? '<td class="text-end">' . e(money($tx)) . ' <small class="text-muted">(' . e(qty($l['tax_rate'])) . '%)</small></td>' : '' ?>
      <td class="text-end"><?= e(money($showTax ? $net : $tot)) ?></td></tr>
  <?php endforeach; ?></tbody></table>
<div class="row justify-content-end"><div class="<?= !empty($receipt) ? 'col-12' : 'col-md-5' ?>"><table class="table table-sm table-borderless">
  <tr><td>Gross</td><td class="text-end"><?= e(money($i['subtotal'])) ?></td></tr>
  <?php if ((float)$i['discount_total'] > 0): ?><tr><td>Discount</td><td class="text-end">− <?= e(money($i['discount_total'])) ?></td></tr><?php endif; ?>
  <tr><td>Tax</td><td class="text-end"><?= e(money($i['tax_total'])) ?></td></tr>
  <?php if ((float)$i['delivery_charge'] > 0): ?><tr><td>Delivery</td><td class="text-end"><?= e(money($i['delivery_charge'])) ?></td></tr><?php endif; ?>
  <?php if ((float)$i['installation_charge'] > 0): ?><tr><td>Installation</td><td class="text-end"><?= e(money($i['installation_charge'])) ?></td></tr><?php endif; ?>
  <tr class="border-top"><th>Total</th><th class="text-end"><?= e(money($i['total'])) ?></th></tr>
  <?php if ((float)$i['returned_amount'] > 0): ?><tr><td>Returns</td><td class="text-end">− <?= e(money($i['returned_amount'])) ?></td></tr><?php endif; ?>
  <tr><td>Paid</td><td class="text-end"><?= e(money($i['paid_amount'])) ?></td></tr>
  <tr><th>Balance due</th><th class="text-end"><?= e(money(max(0, $due))) ?></th></tr></table></div></div>
<?php $notes = $i['notes']; $showBank = !empty($tpl['show_bank']) && empty($receipt); require dirname(__DIR__) . '/print/_foot.php'; ?>
