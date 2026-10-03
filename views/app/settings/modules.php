<div class="card"><div class="card-body">
  <p class="text-muted">Switch off the parts you do not use. They disappear from the menu and the search, and their pages stop opening. Nothing is deleted — switch a part back on any time and everything is still there.</p>
  <form method="post" action="<?= url('settings/modules') ?>"><?= csrf_field() ?>
    <div class="list-group list-group-flush mb-3">
      <?php foreach ($modules as $key => [$label, $desc]): ?>
        <label class="list-group-item d-flex align-items-center justify-content-between gap-3 px-0">
          <span><span class="fw-semibold"><?= e($label) ?></span><span class="d-block small text-muted"><?= e($desc) ?></span></span>
          <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="on[]" value="<?= e($key) ?>" <?= in_array($key, $off, true) ? '' : 'checked' ?>></span>
        </label>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">Save</button>
  </form>
</div></div>
