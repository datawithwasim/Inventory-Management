<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("sales/quotations/{$q['id']}") ?>">Back</a><a class="btn btn-outline-secondary btn-sm" href="<?= url('settings/templates?doc=quotation') ?>">Change this layout</a></div>
<?php $docTitle = 'Quotation'; $docNo = $q['quote_no']; $docDate = $q['quote_date']; $meta = !empty($tpl['show_valid']) ? ['Valid until' => fdate($q['valid_until'] ?? null)] : []; require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="mb-3"><div class="small text-muted">Prepared for</div><strong><?= e($q['customer']) ?></strong>
  <?php if ($q['cust_address']): ?><div><?= e($q['cust_address']) ?></div><?php endif; ?><?php if ($q['cust_phone']): ?><div><?= e($q['cust_phone']) ?></div><?php endif; ?></div>
<?php $showDisc = !empty($tpl['show_discount']); $showTax = !empty($tpl['show_tax']); ?>
<table class="table table-sm align-middle doc-table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><?= $showDisc ? '<th class="text-end">Disc %</th>' : '' ?><?= $showTax ? '<th class="text-end">Tax %</th>' : '' ?><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $l): [, , $net, , $tot] = App\Models\Sales::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['unit_price'])) ?></td>
      <?= $showDisc ? '<td class="text-end">' . ((float)$l['discount_pct'] ? e(qty($l['discount_pct'])) : '') . '</td>' : '' ?><?= $showTax ? '<td class="text-end">' . e(qty($l['tax_rate'])) . '</td>' : '' ?><td class="text-end"><?= e(money($showTax ? $net : $tot)) ?></td></tr>
  <?php endforeach; ?></tbody></table>
<div class="row justify-content-end"><div class="col-md-5"><table class="table table-sm table-borderless">
  <tr><td>Gross</td><td class="text-end"><?= e(money($q['subtotal'])) ?></td></tr>
  <?php if ((float)$q['discount_total'] > 0): ?><tr><td>Discount</td><td class="text-end">− <?= e(money($q['discount_total'])) ?></td></tr><?php endif; ?>
  <tr><td>Tax</td><td class="text-end"><?= e(money($q['tax_total'])) ?></td></tr>
  <?php if ((float)$q['delivery_charge'] > 0): ?><tr><td>Delivery</td><td class="text-end"><?= e(money($q['delivery_charge'])) ?></td></tr><?php endif; ?>
  <?php if ((float)$q['installation_charge'] > 0): ?><tr><td>Installation</td><td class="text-end"><?= e(money($q['installation_charge'])) ?></td></tr><?php endif; ?>
  <tr class="border-top"><th>Total</th><th class="text-end"><?= e(money($q['total'])) ?></th></tr></table></div></div>
<?php $notes = $q['notes']; require dirname(__DIR__) . '/print/_foot.php'; ?>
