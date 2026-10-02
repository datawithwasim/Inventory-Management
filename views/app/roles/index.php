<div class="d-flex justify-content-between mb-3">
  <div class="text-muted">Control what each team member can see and do.</div>
  <?php if (can('roles.create')): ?><a class="btn btn-primary" href="<?= url('roles/create') ?>"><i class="bi bi-plus-lg"></i> New role</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Role</th><th>Users</th><th>Type</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($roles as $r): ?>
    <tr>
      <td><?= e($r['name']) ?></td>
      <td><?= (int)$r['user_count'] ?></td>
      <td><?= $r['is_owner'] ? 'Full access' : ($r['is_system'] ? 'Default' : 'Custom') ?></td>
      <td class="text-end text-nowrap">
        <?php if (!$r['is_owner'] && can('roles.edit')): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= url("roles/{$r['id']}/edit") ?>">Permissions</a>
        <?php endif; ?>
        <?php if (!$r['is_system'] && can('roles.delete')): ?>
          <form class="d-inline" method="post" action="<?= url("roles/{$r['id']}/delete") ?>" onsubmit="return confirm('Delete this role?')"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
