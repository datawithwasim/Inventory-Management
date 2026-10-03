<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Batch no., supplier lot, item, SKU" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="status" class="form-select">
    <?php foreach (['active' => 'In stock', 'finished' => 'Finished', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('batch')) ?></th><th><?= e(term('item')) ?></th><th><?= e(term('supplier')) ?> lot</th><th>Received</th><th class="text-end">Roll size</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $st = App\Models\Stock::batchStatus((float)$r['balance'], (float)$r['received_qty']); ?>
    <tr><td><a href="<?= url("stock/batches/{$r['id']}") ?>"><?= e($r['batch_no']) ?></a></td>
      <td><?= e($r['item_name']) ?><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?></td><td><?= e($r['supplier_lot'] ?? '') ?></td><td><?= e(fdate($r['received_date'])) ?></td>
      <td class="text-end"><?= e(qty($r['received_qty'])) ?> <?= e($r['unit']) ?></td><td class="text-end"><?= e(qty($r['balance'])) ?> <?= e($r['unit']) ?></td>
      <td><span class="badge text-bg-<?= $st === 'Finished' ? 'secondary' : ($st === 'Available' ? 'success' : 'info') ?>"><?= e($st) ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No <?= e(term('rolls', true)) ?> found. <?= e(term('rolls')) ?> are created when stock is added to an <?= e(term('item', true)) ?> that has "Track by <?= e(term('batch', true)) ?>" turned on.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
