<?php  ?>
<p class="text-muted">Choose how your document numbers look. The number keeps counting; changing the prefix only changes how new documents are named.</p>
<form method="post" action="<?= url('settings/numbering') ?>"><?= csrf_field() ?>
<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Document</th><th style="width:140px">Prefix</th><th style="width:120px">Digits</th><th style="width:160px">Add the year</th><th>Next number looks like</th></tr></thead><tbody>
  <?php foreach ($docs as $code => $d): $row = old('num', [])[$code] ?? []; $cfg = $d['cfg']; ?>
    <tr><td><?= e($d['label']) ?></td>
      <td><input name="num[<?= e($code) ?>][prefix]" class="form-control form-control-sm text-uppercase" maxlength="10" value="<?= e($row['prefix'] ?? $cfg['prefix']) ?>" required></td>
      <td><input name="num[<?= e($code) ?>][pad]" type="number" min="3" max="8" class="form-control form-control-sm" value="<?= e($row['pad'] ?? $cfg['pad']) ?>"></td>
      <td><div class="form-check"><input class="form-check-input" type="checkbox" name="num[<?= e($code) ?>][year]" value="1" <?= ($row ? !empty($row['year']) : $cfg['year']) ? 'checked' : '' ?>><label class="form-check-label small">restart every year</label></div></td>
      <td><code><?= e(Core\Numbering::format($cfg, $d['next'])) ?></code></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<button class="btn btn-primary mt-3">Save</button></form>
