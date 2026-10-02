<?php
$action = $role ? url("roles/{$role['id']}") : url('roles');
$allActions = ['view', 'create', 'edit', 'delete', 'approve', 'adjust', 'transfer', 'use', 'export'];
$isSystem = $role && $role['is_system'];
?>
<form method="post" action="<?= $action ?>"><?= csrf_field() ?>
<div class="card mb-3" style="max-width:560px"><div class="card-body">
  <label class="form-label">Role name</label>
  <input name="name" class="form-control" value="<?= e(old('name', $role['name'] ?? '')) ?>" <?= $isSystem ? 'readonly' : 'required' ?>>
</div></div>
<div class="card"><div class="table-responsive">
<table class="table perm-table mb-0">
  <thead><tr><th>Module</th><?php foreach ($allActions as $a): ?><th class="text-center text-capitalize"><?= e($a) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($modules as $key => $def): ?>
    <tr>
      <td><?= e($def['label']) ?></td>
      <?php foreach ($allActions as $a): ?>
        <td class="text-center">
          <?php if (in_array($a, $def['actions'], true)): $p = "$key.$a"; ?>
            <input class="form-check-input" type="checkbox" name="perms[]" value="<?= e($p) ?>" <?= in_array($p, $granted, true) ? 'checked' : '' ?>>
          <?php endif; ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<div class="mt-3"><button class="btn btn-primary">Save role</button> <a class="btn btn-link" href="<?= url('roles') ?>">Cancel</a></div>
</form>
