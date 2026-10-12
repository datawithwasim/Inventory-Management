
<?php ob_start(); ?><?php if ($doc['to_warehouse'] || $signed): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url(($doc['to_warehouse'] ? 'stock/transfers/' : 'stock/adjustments/') . (int)$doc['id'] . '/voucher') ?>"><i class="bi bi-scissors"></i> Stock-out voucher</a><?php endif; ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<?php if (!empty($between)): [$stLabel, $stColor] = $statuses[$doc['status']] ?? [$doc['status'], 'secondary']; ?>
<div class="alert alert-<?= $stColor === 'dark' ? 'secondary' : $stColor ?>">Status: <strong><?= e($stLabel) ?></strong>. Receiving, settling shortages and cancelling are done in the <a href="<?= url('app/stock/transfers/' . (int)$doc['id']) ?>">new transfers screen</a>.</div>
<?php endif; ?>
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
    'facts' => ['date' => ['Date', e(fdate(substr((string)$doc['created_at'], 0, 10))) . ' ' . e(substr((string)$doc['created_at'], 11, 5))], 'by' => ['By', e($doc['user_name'] ?? '—')]]];
require dirname(__DIR__) . '/_record.php';
