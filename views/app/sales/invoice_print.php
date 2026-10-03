<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("sales/invoices/{$i['id']}") ?>">Back to invoice</a></div>
<div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4 mb-0"><?= e($company) ?></h1></div>
  <div class="text-end"><div class="h5 mb-0"><?= !empty($receipt) ? 'Receipt' : 'Tax invoice' ?></div><div><?= e($i['invoice_no']) ?></div><div class="small text-muted"><?= e($i['invoice_date']) ?></div></div></div>
<div class="mb-3"><div class="small text-muted">Billed to</div><strong><?= e($i['customer']) ?></strong>
  <?php if ($i['cust_address']): ?><div><?= e($i['cust_address']) ?></div><?php endif; ?><?php if ($i['cust_phone']): ?><div><?= e($i['cust_phone']) ?></div><?php endif; ?><?php if ($i['cust_tax']): ?><div>Tax no. <?= e($i['cust_tax']) ?></div><?php endif; ?>
  <?php if ($i['ship_to'] && empty($receipt)): ?><div class="small text-muted mt-1">Deliver to: <?= e($i['ship_to']) ?></div><?php endif; ?></div>
<table class="table table-sm align-middle"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><?= empty($receipt) ? '<th class="text-end">Disc</th><th class="text-end">Tax</th>' : '' ?><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $l): if ($l['parent_id'] !== null) continue; [, $dsc, $net, $tx, $tot] = App\Models\Sales::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?><?= $l['batch_no'] && empty($receipt) ? '<div class="small text-muted">Roll ' . e($l['batch_no']) . '</div>' : '' ?></td>
      <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['unit_price'])) ?></td>
      <?= empty($receipt) ? '<td class="text-end">' . ($dsc > 0 ? e(money($dsc)) : '') . '</td><td class="text-end">' . e(money($tx)) . ' <small class="text-muted">(' . e(qty($l['tax_rate'])) . '%)</small></td>' : '' ?>
      <td class="text-end"><?= e(money(!empty($receipt) ? $tot : $net)) ?></td></tr>
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
<?php if ($i['notes']): ?><div class="small text-muted"><?= e($i['notes']) ?></div><?php endif; ?>
<div class="text-center small text-muted mt-4">Thank you for your business.</div>
