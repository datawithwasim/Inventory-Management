<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
