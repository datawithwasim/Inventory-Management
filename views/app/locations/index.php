<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="warehouse" class="form-select"><option value="">All <?= e(term('warehouses', true)) ?></option>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Search rack code" value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('warehouses.create')): ?><a class="btn btn-primary" href="<?= url('locations/create') ?>"><i class="bi bi-plus-lg"></i> Add <?= e(term('racks', true)) ?></a><?php endif; ?></div>
</form>
<?php if ($unassigned): ?>
  <div class="alert alert-warning py-2"><?= (int)$unassigned ?> <?= e(term('item', true)) ?>(s) have stock with <strong>no <?= e(term('rack', true)) ?></strong> assigned.
    <a href="<?= url('stock/racks?q=-') ?>">See them</a> and move them to a <?= e(term('rack', true)) ?> with a stock transfer.</div>
<?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('rack')) ?></th><th><?= e(term('warehouse')) ?></th><th>Description</th><th class="text-end"><?= e(term('items')) ?> on it</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url('stock/racks?warehouse=' . (int)$r['warehouse_id'] . '&q=' . urlencode($r['code'])) ?>"><strong><?= e($r['code']) ?></strong></a></td><td><?= e($r['warehouse']) ?></td><td><?= e($r['description'] ?? '') ?></td>
      <td class="text-end"><?= (int)$r['items'] ?></td>
      <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
      <td class="text-end text-nowrap">
        <?php if (can('warehouses.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("locations/{$r['id']}/edit") ?>">Edit</a><?php endif; ?>
        <?php if (can('warehouses.delete')): ?><form class="d-inline" method="post" action="<?= url("locations/{$r['id']}/delete") ?>" onsubmit="return confirm('Delete this rack?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?>
      </td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No <?= e(term('racks', true)) ?> yet. Add your <?= e(term('racks', true)) ?> or bins (for example A-01, A-02 …) so you can see where everything is kept.</td></tr><?php endif; ?>
  </tbody></table></div></div>
