<div class="d-flex justify-content-between mb-3">
  <div class="text-muted"><?= count($rows) ?> warehouse(s)</div>
  <?php if (can('warehouses.create')): ?>
    <?= $limitReached ? '<span class="text-warning">Warehouse limit reached for your plan</span>' : '<a class="btn btn-primary" href="' . url('warehouses/create') . '"><i class="bi bi-plus-lg"></i> Add warehouse</a>' ?>
  <?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Name</th><th>Code</th><th>Address</th><th class="text-end">Stock qty</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['name']) ?> <?= $r['is_default'] ? '<span class="badge text-bg-primary">Default</span>' : '' ?></td>
      <td><?= e($r['code']) ?></td><td><?= e($r['address'] ?? '') ?></td>
      <td class="text-end"><?= e(qty($r['total_qty'])) ?></td>
      <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
      <td class="text-end text-nowrap">
        <?php if (can('warehouses.edit')): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= url("warehouses/{$r['id']}/edit") ?>">Edit</a>
          <?php if (!$r['is_default']): ?><form class="d-inline" method="post" action="<?= url("warehouses/{$r['id']}/default") ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Make default</button></form><?php endif; ?>
        <?php endif; ?>
        <?php if (can('warehouses.delete') && !$r['is_default']): ?>
          <form class="d-inline" method="post" action="<?= url("warehouses/{$r['id']}/delete") ?>" onsubmit="return confirm('Delete this warehouse?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
