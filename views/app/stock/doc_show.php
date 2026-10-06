
<?php ob_start(); ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="lines"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th><?= e(term('item')) ?></th><th>SKU</th><th><?= e(term('batch')) ?></th><th><?= $doc['to_warehouse'] ? 'From rack → To rack' : 'Rack' ?></th><th class="text-end">Qty</th></tr></thead><tbody>
  <?php foreach ($lines as $l): ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['sku']) ?></td>
      <td><?= $l['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$l['batch_id']) . '">' . e($l['batch_no']) . '</a>' : '' ?></td>
      <td><?= e($l['rack'] ?? 'No rack') ?><?= $doc['to_warehouse'] ? ' → ' . e($l['to_rack'] ?? 'No rack') : '' ?></td>
      <td class="text-end <?= $signed ? ($l['qty'] < 0 ? 'text-danger' : 'text-success') : '' ?>"><?= $signed && $l['qty'] > 0 ? '+' : '' ?><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php $body = ob_get_clean();
$ent = $doc['to_warehouse'] ? 'transfer' : 'adjustment';
$viewRow = $doc; if ($doc['reason']) $viewRow['reason'] = $reasons[$doc['reason']] ?? $doc['reason'];
$rec = ['entity' => $ent, 'row' => $viewRow, 'name' => $doc['doc_no'], 'back' => $back, 'body' => $body, 'badges' => '<span class="badge text-bg-light border">' . e($heading) . '</span>', 'related' => ['lines' => 'Items'],
    'facts' => [['Date', e(fdate(substr((string)$doc['created_at'], 0, 10))) . ' ' . e(substr((string)$doc['created_at'], 11, 5))], ['By', e($doc['user_name'] ?? '—')]]];
require dirname(__DIR__) . '/_record.php';
