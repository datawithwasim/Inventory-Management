<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>When</th><th>Company</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="text-nowrap"><?= e($l['created_at']) ?></td>
      <td><?= e($l['tenant_name'] ?? '—') ?></td>
      <td><?= e($l['actor_type']) ?><?= $l['actor_id'] ? ' #' . (int)$l['actor_id'] : '' ?></td>
      <td><?= e($l['action']) ?></td>
      <td><?= e($l['details'] ?? '') ?></td>
      <td><?= e($l['ip']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
