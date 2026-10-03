<?php if ($pending): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div><strong><?= count($pending) ?> database update(s) waiting:</strong> <?= e(implode(', ', $pending)) ?>
      <div class="small">Run this after uploading a new version of the app.</div></div>
    <form method="post" action="<?= url('admin/system/migrate') ?>"><?= csrf_field() ?>
      <button class="btn btn-warning">Run updates</button></form>
  </div>
<?php else: ?>
  <div class="alert alert-success">The database is up to date.</div>
<?php endif; ?>
<div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><strong>Backup</strong><div class="small text-muted">Downloads every company's data as one .sql file. Do this before each update and keep copies away from the server.
    To restore: cPanel → phpMyAdmin → Import into an empty database. Uploaded logos live in <code>storage/uploads</code>; copy that folder too.</div></div>
  <form method="post" action="<?= url('admin/system/backup') ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="bi bi-download me-1"></i>Download backup</button></form>
</div></div>
<div class="row g-3">
  <div class="col-md-5"><div class="card"><div class="card-header">Environment</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($info as $k => $v): ?><li class="list-group-item d-flex justify-content-between"><span><?= e($k) ?></span><span class="text-muted"><?= e($v) ?></span></li><?php endforeach; ?>
    </ul></div></div>
  <div class="col-md-7"><div class="card"><div class="card-header">Applied updates</div>
    <table class="table table-sm mb-0"><tbody>
      <?php foreach ($applied as $a): ?><tr><td><?= e($a['name']) ?></td><td class="text-muted"><?= e($a['applied_at']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
