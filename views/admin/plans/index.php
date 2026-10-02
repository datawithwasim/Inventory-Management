<div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="<?= url('admin/plans/create') ?>"><i class="bi bi-plus-lg"></i> New plan</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Plan</th><th>Users</th><th>Items</th><th>Warehouses</th><th>Price</th><th>Trial days</th><th>Companies</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php $lim = fn($n) => (int)$n === 0 ? '∞' : $n; foreach ($plans as $p): ?>
    <tr>
      <td><?= e($p['name']) ?></td><td><?= e($lim($p['max_users'])) ?></td><td><?= e($lim($p['max_items'])) ?></td>
      <td><?= e($lim($p['max_warehouses'])) ?></td><td><?= e(number_format((float)$p['price'], 2)) ?></td>
      <td><?= (int)$p['trial_days'] ?></td><td><?= (int)$p['tenant_count'] ?></td>
      <td><?= $p['is_active'] ? 'Yes' : 'No' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url("admin/plans/{$p['id']}/edit") ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<p class="text-muted small mt-2">0 means unlimited.</p>
