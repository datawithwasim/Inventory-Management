<?php require __DIR__ . '/_tabs.php'; ?>
<p class="text-muted">Use your own words. For example call suppliers "Vendors" or racks "Bins". Screens, menus and headings follow; leave a box empty to use the standard word.</p>
<div class="card" style="max-width:640px"><div class="card-body">
<form method="post" action="<?= url('settings/labels') ?>"><?= csrf_field() ?>
  <table class="table table-sm align-middle"><thead><tr><th>Standard word</th><th>Your word (one)</th><th>Your word (many)</th></tr></thead><tbody>
  <?php foreach ($terms as $key => [$one, $many]): $mine = old('label', [])[$key] ?? ($saved[$key] ?? ['', '']); ?>
    <tr><td><?= e($one) ?> / <?= e($many) ?></td>
      <td><input name="label[<?= e($key) ?>][0]" class="form-control form-control-sm" maxlength="30" placeholder="<?= e($one) ?>" value="<?= e($mine[0]) ?>"></td>
      <td><input name="label[<?= e($key) ?>][1]" class="form-control form-control-sm" maxlength="30" placeholder="<?= e($many) ?>" value="<?= e($mine[1]) ?>"></td></tr>
  <?php endforeach; ?></tbody></table>
  <button class="btn btn-primary">Save</button>
</form></div></div>
