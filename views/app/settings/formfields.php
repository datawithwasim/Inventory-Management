<div class="card mb-3"><div class="card-body">
  <p class="text-muted mb-0">Choose which optional fields appear on the Item, Customer and Supplier forms, and which ones must be filled in. Hidden fields keep any data already saved. Name, SKU, prices, unit and other essentials always stay.</p>
</div></div>
<form method="post" action="<?= url('settings/formfields') ?>"><?= csrf_field() ?>
  <?php foreach ($registry as $entity => $cols): ?>
    <div class="card mb-3"><div class="card-header"><?= e($entityLabels[$entity]) ?></div>
      <table class="table align-middle mb-0"><thead><tr><th>Field</th><th class="text-center" style="width:120px">Show</th><th class="text-center" style="width:120px">Required</th></tr></thead><tbody>
        <?php foreach ($cols as $col => $label): $shown = Core\FormFields::shown($entity, $col); $req = Core\FormFields::required($entity, $col); ?>
          <tr><td><?= e($label) ?></td>
            <td class="text-center"><span class="form-check form-switch d-inline-block m-0"><input class="form-check-input ff-show" type="checkbox" name="show[<?= e($entity) ?>][<?= e($col) ?>]" value="1" <?= $shown ? 'checked' : '' ?>></span></td>
            <td class="text-center"><span class="form-check form-switch d-inline-block m-0"><input class="form-check-input ff-req" type="checkbox" name="req[<?= e($entity) ?>][<?= e($col) ?>]" value="1" <?= $req ? 'checked' : '' ?> <?= $shown ? '' : 'disabled' ?>></span></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
  <?php endforeach; ?>
  <button class="btn btn-primary">Save</button>
</form>
<script>
document.querySelectorAll('.ff-show').forEach(function (s) {
  s.addEventListener('change', function () { var r = s.closest('tr').querySelector('.ff-req'); r.disabled = !s.checked; if (!s.checked) r.checked = false; });
});
</script>
