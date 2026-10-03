<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="warehouse" class="form-select"><option value="">All <?= e(term('warehouses', true)) ?></option>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Rack code, item, SKU or batch no." value="<?= e($q === '-' ? '' : $q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col-auto"><a class="btn btn-outline-warning" href="<?= url('stock/racks?q=-') ?>">Only "no <?= e(term('rack', true)) ?>"</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('warehouse')) ?></th><th><?= e(term('rack')) ?></th><th><?= e(term('item')) ?></th><th>SKU</th><th><?= e(term('batch')) ?> (roll)</th><th class="text-end">Qty</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['warehouse']) ?></td>
      <td><?= $r['rack'] ? '<strong>' . e($r['rack']) . '</strong>' : '<span class="badge text-bg-warning">No rack</span>' ?></td>
      <td><a href="<?= url("items/{$r['item_id']}") ?>"><?= e($r['item_name']) ?><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?></a></td><td><?= e($r['sku']) ?></td>
      <td><?= $r['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$r['batch_id']) . '">' . e($r['batch_no']) . '</a>' : '' ?></td>
      <td class="text-end"><?= e(qty($r['qty'])) ?> <?= e($r['unit']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">Nothing found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
