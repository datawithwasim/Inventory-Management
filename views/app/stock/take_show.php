<?php $draft = $doc['status'] === 'draft'; ?>
<?php ob_start(); ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<form method="post" action="<?= url("stock/takes/{$doc['id']}") ?>"><?= csrf_field() ?>
<div class="card mb-3" id="lines"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?></th><th><?= e(term('rack')) ?></th><th class="text-end">System qty</th><th class="text-end" style="width:170px"><?= $draft ? 'Counted' : 'Counted' ?></th><th class="text-end">Difference</th></tr></thead><tbody>
  <?php foreach ($lines as $l): $diff = (float)$l['qty'] - (float)$l['expected_qty']; ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td><?= e($l['batch_no'] ?? '') ?></td><td><?= e($l['rack'] ?? 'No rack') ?></td><td class="text-end"><?= e(qty($l['expected_qty'])) ?> <?= e($l['unit']) ?></td>
      <td class="text-end"><?php if ($draft): ?><input type="number" step="0.001" min="0" name="counts[<?= (int)$l['id'] ?>]" class="form-control form-control-sm text-end" value="<?= e(qty($l['qty'])) ?>"><?php else: ?><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?><?php endif; ?></td>
      <td class="text-end <?= abs($diff) > 0.0005 ? ($diff < 0 ? 'text-danger' : 'text-success') : 'text-muted' ?>"><?= $diff > 0.0005 ? '+' : '' ?><?= e(qty($diff)) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$lines): ?><tr><td colspan="6" class="text-muted">This <?= e(term('warehouse', true)) ?> has no stock. Use an adjustment to add stock.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php if ($draft): ?>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-outline-primary" name="action" value="save">Save counts</button>
    <button class="btn btn-primary" name="action" value="post" onclick="return confirm('Post this stock-take? Stock will be corrected to the counted quantities.')">Post stock-take</button>
    <a class="btn btn-link" href="<?= url('stock/takes') ?>">Back</a>
  </div>
</form>
<form method="post" action="<?= url("stock/takes/{$doc['id']}/delete") ?>" class="mt-2" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete draft</button></form>
<?php else: ?></form><a class="btn btn-link mt-2" href="<?= url('stock/takes') ?>">&laquo; Back</a><?php endif; ?>
<?php $body = ob_get_clean();
$rec = ['entity' => 'stocktake', 'row' => $doc, 'name' => $doc['doc_no'], 'back' => 'stock/takes', 'body' => $body,
    'badges' => '<span class="badge text-bg-' . ($draft ? 'warning' : 'success') . '">' . e($doc['status']) . '</span>', 'related' => ['lines' => 'Counts'], 'factsTitle' => 'Stock-take details',
    'facts' => ['started' => ['Started', e(fdate(substr((string)$doc['created_at'], 0, 10)))]]];
require dirname(__DIR__) . '/_record.php';
