<div class="d-flex justify-content-between mb-3">
  <div class="text-muted"><?= count($users) ?> user(s)</div>
  <?php if (can('users.create')): ?>
    <?php if ($limitReached): ?>
      <span class="text-warning">User limit reached for your plan</span>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= url('users/create') ?>"><i class="bi bi-plus-lg"></i> Add user</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $x): ?>
    <tr>
      <td><?= e($x['name']) ?></td>
      <td><?= e($x['email']) ?></td>
      <td><span class="badge text-bg-secondary"><?= e($x['role_name']) ?></span></td>
      <td><?= $x['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Disabled</span>' ?></td>
      <td class="text-muted small"><?= e($x['last_login_at'] ?? '—') ?></td>
      <td class="text-end text-nowrap">
        <?php if (can('users.edit')): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= url("users/{$x['id']}/edit") ?>">Edit</a>
          <form class="d-inline" method="post" action="<?= url("users/{$x['id']}/toggle") ?>"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-warning"><?= $x['is_active'] ? 'Disable' : 'Enable' ?></button></form>
        <?php endif; ?>
        <?php if (can('users.delete')): ?>
          <form class="d-inline" method="post" action="<?= url("users/{$x['id']}/delete") ?>" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
