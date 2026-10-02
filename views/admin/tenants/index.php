<div class="d-flex justify-content-end mb-3"><a class="btn btn-primary" href="<?= url('admin/tenants/create') ?>"><i class="bi bi-plus-lg"></i> New company</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Company</th><th>Plan</th><th>Status</th><th>Users</th><th>Ends</th><th></th></tr></thead>
  <tbody>
  <?php $badge = ['active' => 'success', 'trial' => 'info', 'suspended' => 'danger'];
  foreach ($tenants as $t): $end = $t['status'] === 'trial' ? $t['trial_ends_at'] : $t['subscription_ends_at']; ?>
    <tr>
      <td><?= e($t['name']) ?></td><td><?= e($t['plan_name']) ?></td>
      <td><span class="badge text-bg-<?= $badge[$t['status']] ?>"><?= e($t['status']) ?></span></td>
      <td><?= (int)$t['user_count'] ?></td><td><?= e($end ?? '—') ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="<?= url("admin/tenants/{$t['id']}/edit") ?>">Edit</a>
        <form class="d-inline" method="post" action="<?= url("admin/tenants/{$t['id']}/impersonate") ?>"><?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-dark">Open as owner</button></form>
        <form class="d-inline" method="post" action="<?= url("admin/tenants/{$t['id']}/status") ?>"><?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-<?= $t['status'] === 'suspended' ? 'success' : 'warning' ?>"><?= $t['status'] === 'suspended' ? 'Activate' : 'Suspend' ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$tenants): ?><tr><td colspan="6" class="text-muted">No companies yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
