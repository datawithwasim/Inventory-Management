<div class="card mb-3"><div class="card-body">
  <p class="text-muted mb-0">Choose which optional fields appear on each form, and which ones must be filled in. Hidden fields keep any data already saved. Essentials such as names, dates, items and quantities always stay.</p>
</div></div>
<form method="post" action="<?= url('settings/formfields') ?>"><?= csrf_field() ?>
  <ul class="nav nav-tabs ff-tabs mb-3" role="tablist">
    <?php $first = true; foreach ($sections as $heading => $entities): ?>
      <li class="nav-item" role="presentation"><button class="nav-link <?= $first ? 'active' : '' ?>" type="button" data-bs-toggle="tab" data-bs-target="#ff-<?= e(md5($heading)) ?>" role="tab"><?= e($heading) ?></button></li>
    <?php $first = false; endforeach; ?>
  </ul>
  <div class="tab-content">
    <?php $first = true; foreach ($sections as $heading => $entities): ?>
      <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="ff-<?= e(md5($heading)) ?>" role="tabpanel">
        <div class="row g-3">
        <?php foreach ($entities as $entity): ?>
          <div class="col-xl-6"><div class="card h-100"><div class="card-header"><?= e($entityLabels[$entity]) ?></div>
            <table class="table align-middle mb-0"><thead><tr><th>Field</th><th class="text-center" style="width:90px">Show</th><th class="text-center" style="width:100px">Required</th></tr></thead><tbody>
              <?php foreach ($registry[$entity] as $col => $label): $shown = Core\FormFields::shown($entity, $col); $req = Core\FormFields::required($entity, $col); ?>
                <tr><td><?= e($label) ?></td>
                  <td class="text-center"><span class="form-check form-switch d-inline-block m-0"><input class="form-check-input ff-show" type="checkbox" name="show[<?= e($entity) ?>][<?= e($col) ?>]" value="1" <?= $shown ? 'checked' : '' ?>></span></td>
                  <td class="text-center"><span class="form-check form-switch d-inline-block m-0"><input class="form-check-input ff-req" type="checkbox" name="req[<?= e($entity) ?>][<?= e($col) ?>]" value="1" <?= $req ? 'checked' : '' ?> <?= $shown ? '' : 'disabled' ?>></span></td></tr>
              <?php endforeach; ?>
            </tbody></table></div></div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php $first = false; endforeach; ?>
  </div>
  <button class="btn btn-primary mt-3">Save</button>
</form>
<script>
document.querySelectorAll('.ff-show').forEach(function (s) {
  s.addEventListener('change', function () { var r = s.closest('tr').querySelector('.ff-req'); r.disabled = !s.checked; if (!s.checked) r.checked = false; });
});
</script>
