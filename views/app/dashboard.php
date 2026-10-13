<?php use App\Controllers\DashboardController; require __DIR__ . "/_dashboard_widgets.php"; ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="btn-group btn-group-sm" role="group" aria-label="Period">
    <?php foreach ($ranges as $k => $lbl): ?>
      <a class="btn btn-outline-secondary <?= $p['key'] === (string)$k ? 'active' : '' ?>" href="?range=<?= e($k) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="text-muted small"><?= e(fdate($p['from'])) ?> – <?= e(fdate($p['to'])) ?> · compared with the <?= (int)$p['days'] ?> days before</span>
  <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="offcanvas" data-bs-target="#dashCustomize"><i class="bi bi-layout-text-window-reverse me-1"></i>Customize</button>
</div>

<div class="dash-grid">
  <?php foreach ($layout['order'] as $key): if (in_array($key, $layout['hidden'], true) || empty($render[$key])) continue; ?>
    <section class="dash-w span-<?= (int)$spans[$key] ?>" data-widget="<?= e($key) ?>"><?= $render[$key] ?></section>
  <?php endforeach; ?>
  <?php if (count($layout['hidden']) >= count($layout['order'])): ?>
    <div class="empty-state span-12"><i class="bi bi-layout-text-window-reverse"></i>Everything is hidden. Use <strong>Customize</strong> to add widgets back.</div>
  <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="dashCustomize" aria-labelledby="dashCustomizeLabel">
  <form method="post" action="<?= url('dashboard/layout') ?>" class="d-flex flex-column h-100"><?= csrf_field() ?>
    <div class="offcanvas-header"><h2 class="h5 mb-0" id="dashCustomizeLabel">Customize dashboard</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body">
      <p class="text-muted small">Show or hide widgets and move them up or down. This only changes <em>your</em> dashboard.</p>
      <div id="widgetList">
        <?php foreach ($layout['order'] as $key): ?>
          <div class="widget-row" data-key="<?= e($key) ?>">
            <input type="hidden" name="order[]" value="<?= e($key) ?>">
            <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="show[]" value="<?= e($key) ?>" id="w-<?= e($key) ?>" <?= in_array($key, $layout['hidden'], true) ? '' : 'checked' ?>></span>
            <label class="flex-grow-1 m-0" for="w-<?= e($key) ?>"><?= e(DashboardController::WIDGETS[$key]) ?></label>
            <button type="button" class="icon-btn w-up" aria-label="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="icon-btn w-down" aria-label="Move down"><i class="bi bi-arrow-down"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="offcanvas-footer p-3 border-top d-flex gap-2">
      <button class="btn btn-primary flex-grow-1">Save</button>
      <button class="btn btn-outline-secondary" name="reset" value="1" formnovalidate>Reset</button>
    </div>
  </form>
</div>
<script>
document.getElementById('widgetList').addEventListener('click', function (e) {
  var b = e.target.closest('.w-up, .w-down'); if (!b) return;
  var row = b.closest('.widget-row');
  if (b.classList.contains('w-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
  if (b.classList.contains('w-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
});
</script>
<div class="text-muted small mt-4">
  Plan <strong><?= e($u['plan_name']) ?></strong> · Users <?= (int)$userCount ?>/<?= (int)$u['max_users'] === 0 ? 'Unlimited' : (int)$u['max_users'] ?> · Roles <?= (int)$roleCount ?>
</div>
