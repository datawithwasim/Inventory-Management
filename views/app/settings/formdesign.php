<div class="card mb-3"><div class="card-body">
  <p class="text-muted mb-0">Design every form your way: move fields up or down, make a field half or full width, give it your own name, and add a short hint under it. Changes show on the real form as soon as you save. <strong>Reset</strong> brings a form back to the standard design. Which fields are shown or required is in <a href="<?= url('settings/formfields') ?>">Form fields</a>.</p>
</div></div>
<form method="post" action="<?= url('settings/formdesign') ?>"><?= csrf_field() ?>
  <ul class="nav nav-tabs ff-tabs mb-3" role="tablist">
    <?php $first = true; foreach ($sections as $heading => $entities): ?>
      <li class="nav-item" role="presentation"><button class="nav-link <?= $first ? 'active' : '' ?>" type="button" data-bs-toggle="tab" data-bs-target="#fd-<?= e(md5($heading)) ?>" role="tab"><?= e($heading) ?></button></li>
    <?php $first = false; endforeach; ?>
  </ul>
  <div class="tab-content">
    <?php $first = true; foreach ($sections as $heading => $entities): ?>
      <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="fd-<?= e(md5($heading)) ?>" role="tabpanel">
        <?php foreach ($entities as $entity): if (!isset($fields[$entity])): continue; endif; ?>
          <div class="card mb-3 fd-card" data-entity="<?= e($entity) ?>">
            <div class="card-header d-flex justify-content-between align-items-center"><span><?= e($entityLabels[$entity]) ?><?php if (Core\FormDesign::customised($entity)): ?> <span class="badge text-bg-primary ms-1">customised</span><?php endif; ?></span>
              <label class="small text-muted fw-normal m-0"><input type="checkbox" class="form-check-input me-1" name="reset[<?= e($entity) ?>]" value="1">Reset to standard on save</label></div>
            <div class="fd-row fd-head"><span></span><span>Field</span><span>Your label</span><span>Width</span><span>Hint under the field</span></div>
            <div class="fd-list">
              <?php foreach (Core\FormDesign::order($entity) as $col): $f = Core\FormDesign::field($entity, $col); $def = $fields[$entity][$col]; ?>
                <div class="fd-row" data-col="<?= e($col) ?>">
                  <input type="hidden" name="order[<?= e($entity) ?>][]" value="<?= e($col) ?>">
                  <span class="fd-arrows"><button type="button" class="fd-up" aria-label="Move up"><i class="bi bi-caret-up-fill"></i></button><button type="button" class="fd-down" aria-label="Move down"><i class="bi bi-caret-down-fill"></i></button></span>
                  <span class="fd-name"><?= e($def) ?></span>
                  <input class="form-control form-control-sm" maxlength="60" name="design[<?= e($entity) ?>][<?= e($col) ?>][label]" value="<?= e($f['label']) ?>" placeholder="<?= e($def) ?>">
                  <select class="form-select form-select-sm" name="design[<?= e($entity) ?>][<?= e($col) ?>][w]"><?php foreach ($widths as $k => $l): ?><option value="<?= e((string)$k) ?>" <?= $f['w'] === (string)$k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                  <input class="form-control form-control-sm fd-help" maxlength="140" name="design[<?= e($entity) ?>][<?= e($col) ?>][help]" value="<?= e($f['help']) ?>" placeholder="Optional hint, e.g. “as printed on the GST certificate”">
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php $first = false; endforeach; ?>
  </div>
  <button class="btn btn-primary">Save form design</button>
</form>
<script>
document.querySelectorAll('.fd-list').forEach(function (list) {
  list.addEventListener('click', function (e) {
    var b = e.target.closest('.fd-up, .fd-down'); if (!b) return;
    var row = b.closest('.fd-row');
    if (b.classList.contains('fd-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
    if (b.classList.contains('fd-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    row.classList.remove('moved'); void row.offsetWidth; row.classList.add('moved');
  });
});
</script>
