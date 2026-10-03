<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $k => $t): ?>
    <li class="nav-item"><a class="nav-link <?= $k === $type ? 'active' : '' ?>" href="<?= url("masters/$k") ?>"><?= e($t['title']) ?></a></li>
  <?php endforeach; ?>
</ul>
