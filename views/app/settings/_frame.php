<?php use App\Models\SettingsNav; ?>
<div class="settings-grid">
  <nav class="settings-nav" aria-label="Settings">
    <?php foreach (SettingsNav::GROUPS as $group => $items): ?>
      <div class="settings-nav-head"><?= e($group) ?></div>
      <?php foreach ($items as $key => [$label, $icon, $href]): ?>
        <a class="settings-nav-link <?= $key === $tab ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi bi-<?= e($icon) ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="settings-body"><?= $inner ?></div>
</div>
