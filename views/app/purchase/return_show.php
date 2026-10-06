
<?php ob_start(); ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0"><thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?></th><th><?= e(term('rack')) ?></th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Value</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['batch_no'] ?? '—') ?></td><td><?= e($l['rack'] ?? 'No rack') ?></td>
    <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(money($l['line_total'])) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="5" class="text-end">Total (incl. tax)</th><th class="text-end"><?= e(money($r['total'])) ?></th></tr></tfoot></table></div></div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'purchase_return', 'row' => $r, 'name' => $r['return_no'], 'back' => 'purchase/returns', 'body' => $body, 'badges' => '',
    'related' => ['items' => 'Items returned'],
    'facts' => [[term('supplier'), e($r['supplier'])], ['Goods receipt', '<a href="' . url('purchase/grns/' . (int)$r['grn_id']) . '">' . e($r['grn_no']) . '</a>'],
        ['Bill', $r['bill_id'] ? '<a href="' . url('purchase/bills/' . (int)$r['bill_id']) . '">' . e($r['bill_no']) . '</a>' : ''], ['By', e($r['user_name'] ?? '—')], ['Total (incl. tax)', e(money($r['total']))]]];
require dirname(__DIR__) . '/_record.php';
