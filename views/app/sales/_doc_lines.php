<?php /** read-only priced lines. $items need qty-ish column in $qtyKey, $withDelivered for orders */ ?>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th class="text-end">Qty</th><?= !empty($withDelivered) ? '<th class="text-end">Delivered</th>' : '' ?><th class="text-end">Price</th><th class="text-end">Disc %</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr></thead><tbody>
  <?php foreach ($items as $l): [, , , , $tot] = App\Models\Sales::line((float)$l[$qtyKey], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small><?= !empty($l['is_bundle']) ? ' <span class="badge text-bg-info">Set</span>' : '' ?>
        <?php if (isset($l['free'])): ?><div class="small text-muted">free for others now: <?= e(qty($l['free'])) ?></div><?php endif; ?></td>
      <td class="text-end"><?= e(qty($l[$qtyKey])) ?> <?= e($l['unit']) ?></td><?= !empty($withDelivered) ? '<td class="text-end">' . e(qty($l['qty_delivered'])) . '</td>' : '' ?>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= (float)$l['discount_pct'] ? e(qty($l['discount_pct'])) : '' ?></td><td class="text-end"><?= e(qty($l['tax_rate'])) ?></td><td class="text-end"><?= e(money($tot)) ?></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end text-muted">Gross</td><td class="text-end"><?= e(money($doc['subtotal'])) ?></td></tr>
    <?php if ((float)$doc['discount_total'] > 0): ?><tr><td colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end text-muted">Discount</td><td class="text-end">− <?= e(money($doc['discount_total'])) ?></td></tr><?php endif; ?>
    <tr><td colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end text-muted">Tax</td><td class="text-end"><?= e(money($doc['tax_total'])) ?></td></tr>
    <?php if ((float)$doc['delivery_charge'] > 0): ?><tr><td colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end text-muted">Delivery</td><td class="text-end"><?= e(money($doc['delivery_charge'])) ?></td></tr><?php endif; ?>
    <?php if ((float)$doc['installation_charge'] > 0): ?><tr><td colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end text-muted">Installation</td><td class="text-end"><?= e(money($doc['installation_charge'])) ?></td></tr><?php endif; ?>
    <tr><th colspan="<?= !empty($withDelivered) ? 6 : 5 ?>" class="text-end">Total</th><th class="text-end"><?= e(money($doc['total'])) ?></th></tr></tfoot></table></div></div>
