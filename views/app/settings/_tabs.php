<ul class="nav nav-pills mb-3 flex-wrap">
  <?php foreach ($tabs as $k => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $k === $tab ? 'active' : '' ?>" href="<?= url($k === 'fields' ? 'settings/custom-fields' : "settings/$k") ?>"><?= e($label) ?></a></li>
  <?php endforeach; ?>
</ul>
