
<?php ob_start(); ?>
  <?php if ($g['bill_id']): ?><a class="btn btn-outline-primary" href="<?= url('purchase/bills/' . (int)$g['bill_id']) ?>">Bill <?= e($g['bill_no']) ?></a>
  <?php elseif (can('purchase.create')): ?><form method="post" action="<?= url("purchase/grns/{$g['id']}/bill") ?>"><?= csrf_field() ?><button class="btn btn-primary">Create bill</button></form><?php endif; ?>
  <?php if (can('purchase.create')): ?><a class="btn btn-outline-danger" href="<?= url('purchase/returns/create?grn=' . (int)$g['id']) ?>">Return goods</a><?php endif; ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?> (roll)</th><th><?= e(term('rack')) ?></th><th class="text-end">Qty</th><th class="text-end">Returned</th><th class="text-end">Price</th><th class="text-end">Stock cost</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td><?= $l['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$l['batch_id']) . '">' . e($l['batch_no']) . '</a>' . ($l['supplier_lot'] ? '<br><small class="text-muted">lot ' . e($l['supplier_lot']) . '</small>' : '') : '—' ?></td>
      <td><?= e($l['rack'] ?? 'No rack') ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= (float)$l['qty_returned'] > 0 ? e(qty($l['qty_returned'])) : '' ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(number_format((float)$l['landed_unit_cost'], 2)) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php if ($returns): ?><div class="card mb-3" id="returns"><div class="card-header">Returns</div><ul class="list-group list-group-flush">
  <?php foreach ($returns as $r): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a><span><?= e(fdate($r['return_date'])) ?> · <?= e(money($r['total'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php $body = ob_get_clean();
$rec = ['entity' => 'grn', 'row' => $g, 'name' => $g['grn_no'], 'back' => 'purchase/grns', 'body' => $body, 'actions' => $actions, 'badges' => '',
    'related' => array_filter(['items' => 'Items received', 'returns' => $returns ? 'Returns' : null]),
    'facts' => [[term('supplier'), '<a href="' . url('suppliers/' . (int)$g['supplier_id']) . '">' . e($g['supplier']) . '</a>'],
        ['Purchase order', $g['po_id'] ? '<a href="' . url('purchase/orders/' . (int)$g['po_id']) . '">' . e($g['po_no']) . '</a>' : ''],
        ['Bill', $g['bill_id'] ? '<a href="' . url('purchase/bills/' . (int)$g['bill_id']) . '">' . e($g['bill_no']) . '</a>' : ''], ['Received by', e($g['user_name'] ?? '—')]]];
require dirname(__DIR__) . '/_record.php';
