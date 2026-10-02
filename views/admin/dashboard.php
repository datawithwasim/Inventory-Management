<div class="row g-3 mb-4">
  <?php foreach ([['Companies', $counts['total']], ['Active', $counts['active']], ['On trial', $counts['trial']], ['Suspended', $counts['suspended']], ['Total users', $users]] as [$l, $v]): ?>
    <div class="col-6 col-md"><div class="card"><div class="card-body">
      <div class="text-muted small"><?= e($l) ?></div><div class="fs-3"><?= (int)$v ?></div>
    </div></div></div>
  <?php endforeach; ?>
</div>
<div class="card"><div class="card-header d-flex justify-content-between">
  <span>Newest companies</span><a href="<?= url('admin/tenants/create') ?>">+ New company</a></div>
<div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Company</th><th>Plan</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $t): ?>
    <tr><td><?= e($t['name']) ?></td><td><?= e($t['plan_name']) ?></td><td><?= e($t['status']) ?></td><td><?= e($t['created_at']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="4" class="text-muted">No companies yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
