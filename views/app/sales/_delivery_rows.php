<?php /** delivery lines with rolls / racks; set parts are indented */ ?>
<table class="table mb-0 align-middle"><thead><tr><th><?= e(term('item')) ?></th><th>Roll (<?= e(term('batch', true)) ?>)</th><th><?= e(term('rack')) ?></th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>
<?php foreach ($items as $l): $part = $l['parent_id'] !== null; [, , , , $tot] = App\Models\Sales::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
  <tr class="<?= $part ? 'text-muted small' : '' ?>"><td><?= $part ? '&nbsp;&nbsp;↳ ' : '' ?><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small><?= $l['is_bundle'] ? ' <span class="badge text-bg-info">Set</span>' : '' ?></td>
    <td><?= $l['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$l['batch_id']) . '">' . e($l['batch_no']) . '</a>' : '' ?></td><td><?= $l['is_bundle'] ? '' : e($l['rack'] ?? 'No rack') ?></td>
    <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?><?= (float)$l['qty_returned'] > 0 ? '<div class="small text-danger">returned ' . e(qty($l['qty_returned'])) . '</div>' : '' ?></td>
    <td class="text-end"><?= $part ? '' : e(money($l['unit_price'])) . ((float)$l['discount_pct'] ? '<div class="small text-muted">−' . e(qty($l['discount_pct'])) . '%</div>' : '') ?></td>
    <td class="text-end"><?= $part ? '' : e(money($tot)) ?></td></tr>
<?php endforeach; ?></tbody></table>
