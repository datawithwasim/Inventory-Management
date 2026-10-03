<?php
$isBundle = $item ? (bool)$item['is_bundle'] : (bool)old('is_bundle');
$v = fn($k, $d = '') => e(old($k, $item[$k] ?? $d));
$sel = fn($k, $id) => (int)old($k, $item[$k] ?? 0) === (int)$id ? 'selected' : '';
?>
<form method="post" action="<?= $item ? url("items/{$item['id']}") : url('items') ?>"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card mb-3"><div class="card-body">
    <div class="mb-3"><label class="form-label"><?= e(term('item')) ?> name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="190"></div>
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">—</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('category_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <?php if (ff('item.brand_id')): ?><div class="col-md-4 mb-3"><label class="form-label">Brand<?= ffstar('item.brand_id') ?></label><select name="brand_id" class="form-select"<?= ffreq('item.brand_id') ?>><option value="">—</option>
        <?php foreach ($brands as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('brand_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-md-4 mb-3"><label class="form-label">Unit</label><select name="unit_id" class="form-select" required>
        <?php foreach ($units as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('unit_id', $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['short_name']) ?>)</option><?php endforeach; ?></select></div>
    </div>
    <?php if (ff('item.tax_id')): ?><div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">Tax<?= ffstar('item.tax_id') ?></label><select name="tax_id" class="form-select"<?= ffreq('item.tax_id') ?>><option value="">No tax</option>
        <?php foreach ($taxes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('tax_id', $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['rate']) ?>%)</option><?php endforeach; ?></select></div>
    </div><?php endif; ?>
    <?php if (ff('item.description')): ?><div class="mb-0"><label class="form-label">Description<?= ffstar('item.description') ?></label><textarea name="description" class="form-control" rows="2"<?= ffreq('item.description') ?>><?= $v('description') ?></textarea></div><?php endif; ?>
  </div></div>

  <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
    <span id="variantsTitle"><?= $isBundle ? 'Bundle SKU & price' : 'Variants' ?></span>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addVariant" <?= $isBundle ? 'hidden' : '' ?>><i class="bi bi-plus-lg"></i> Add variant</button></div>
    <div class="table-responsive"><table class="table mb-0 align-middle" id="variantTable">
      <thead><tr><th>Variant (e.g. Grey / 3-seater)</th><th>SKU</th><th>Barcode</th><th>Cost</th><th>Sale price</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($variants as $i => $r): ?>
        <tr>
          <td><input type="hidden" name="variants[<?= $i ?>][id]" value="<?= e($r['id'] ?? '') ?>"><input name="variants[<?= $i ?>][name]" class="form-control form-control-sm" value="<?= e($r['name'] ?? '') ?>"></td>
          <td><input name="variants[<?= $i ?>][sku]" class="form-control form-control-sm" placeholder="auto" value="<?= e($r['sku'] ?? '') ?>"></td>
          <td><input name="variants[<?= $i ?>][barcode]" class="form-control form-control-sm" value="<?= e($r['barcode'] ?? '') ?>"></td>
          <td><input name="variants[<?= $i ?>][cost_price]" type="number" step="0.01" min="0" class="form-control form-control-sm" value="<?= e($r['cost_price'] ?? '') ?>"></td>
          <td><input name="variants[<?= $i ?>][sale_price]" type="number" step="0.01" min="0" class="form-control form-control-sm" value="<?= e($r['sale_price'] ?? '') ?>"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger rm" title="Remove">&times;</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div></div>

  <div class="card mb-3" id="componentsCard" <?= $isBundle ? '' : 'hidden' ?>><div class="card-header d-flex justify-content-between align-items-center">
    <span>Bundle contents</span><button type="button" class="btn btn-sm btn-outline-primary" id="addComp"><i class="bi bi-plus-lg"></i> Add component</button></div>
    <div class="table-responsive"><table class="table mb-0 align-middle" id="compTable"><thead><tr><th><?= e(term('item')) ?></th><th style="width:140px">Qty per set</th><th></th></tr></thead><tbody>
      <?php foreach ($components as $i => $c): ?>
        <tr>
          <td><select name="components[<?= $i ?>][variant_id]" class="form-select form-select-sm">
            <?php foreach ($pickable as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$c['variant_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['item_name'] . ($p['name'] ? ' — ' . $p['name'] : '') . ' (' . $p['sku'] . ')') ?></option><?php endforeach; ?></select></td>
          <td><input name="components[<?= $i ?>][qty]" type="number" step="0.001" min="0" class="form-control form-control-sm" value="<?= e($c['qty']) ?>"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td>
        </tr>
      <?php endforeach; ?></tbody></table></div></div>
  <?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
  </div>

  <div class="col-lg-4"><div class="card mb-3"><div class="card-body">
    <?php if (!$item): ?>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_bundle" value="1" id="isBundle" <?= $isBundle ? 'checked' : '' ?>>
        <label class="form-check-label" for="isBundle">Bundle / set <small class="text-muted d-block">e.g. dining table + 6 chairs sold as one</small></label></div>
    <?php else: ?><input type="hidden" id="isBundle" value="<?= (int)$isBundle ?>"><?php endif; ?>
    <div id="batchBox" <?= $isBundle ? 'hidden' : '' ?>>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="track_batch" value="1" id="trk" <?= old('track_batch', $item['track_batch'] ?? 0) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
        <label class="form-check-label" for="trk">Track by <?= e(term('batch', true)) ?> (roll / thaan)
          <small class="text-muted d-block">Each purchased roll becomes its own <?= e(term('batch', true)) ?>, and every sale is traced to it. Use for fabric.</small></label></div>
      <?php if ($locked): ?><input type="hidden" name="track_batch" value="<?= (int)$item['track_batch'] ?>"><small class="text-muted">Locked: stock has already moved.</small><?php endif; ?>
    </div>
    <hr>
    <div class="mb-2"><label class="form-label">Reorder level</label><input name="reorder_level" type="number" step="0.001" min="0" class="form-control" value="<?= $v('reorder_level', 0) ?>"></div>
    <div class="mb-2"><label class="form-label">Reorder quantity</label><input name="reorder_qty" type="number" step="0.001" min="0" class="form-control" value="<?= $v('reorder_qty', 0) ?>"></div>
    <?php if ($item): ?><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $item['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActive">Active</label></div><?php endif; ?>
  </div></div>
  <button class="btn btn-primary">Save <?= e(term('item', true)) ?></button> <a class="btn btn-link" href="<?= $item ? url("items/{$item['id']}") : url('items') ?>">Cancel</a></div>
</div>
</form>
<script>
(function () {
  var vt = document.querySelector('#variantTable tbody'), ct = document.querySelector('#compTable tbody');
  var vn = vt.rows.length, cn = ct.rows.length;
  var pick = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 't' => $p['item_name'] . ($p['name'] ? ' — ' . $p['name'] : '') . ' (' . $p['sku'] . ')'], $pickable)) ?>;
  function el(html) { var t = document.createElement('tbody'); t.innerHTML = html; return t.firstElementChild; }
  function addVariant() {
    var i = vn++;
    vt.appendChild(el('<tr><td><input type="hidden" name="variants[' + i + '][id]"><input name="variants[' + i + '][name]" class="form-control form-control-sm"></td>' +
      '<td><input name="variants[' + i + '][sku]" class="form-control form-control-sm" placeholder="auto"></td>' +
      '<td><input name="variants[' + i + '][barcode]" class="form-control form-control-sm"></td>' +
      '<td><input name="variants[' + i + '][cost_price]" type="number" step="0.01" min="0" class="form-control form-control-sm"></td>' +
      '<td><input name="variants[' + i + '][sale_price]" type="number" step="0.01" min="0" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td></tr>'));
  }
  function addComp() {
    var i = cn++, tr = el('<tr><td><select class="form-select form-select-sm"></select></td>' +
      '<td><input type="number" step="0.001" min="0" class="form-control form-control-sm" value="1"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td></tr>');
    var s = tr.querySelector('select'); s.name = 'components[' + i + '][variant_id]';
    pick.forEach(function (p) { var o = document.createElement('option'); o.value = p.id; o.textContent = p.t; s.appendChild(o); });
    tr.querySelector('input').name = 'components[' + i + '][qty]';
    ct.appendChild(tr);
  }
  document.getElementById('addVariant').onclick = addVariant;
  document.getElementById('addComp').onclick = addComp;
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('rm')) return;
    var tb = e.target.closest('tbody');
    if (tb === vt && vt.rows.length === 1) return;
    e.target.closest('tr').remove();
  });
  if (!vt.rows.length) addVariant();
  var bundle = document.getElementById('isBundle');
  function sync() {
    var on = bundle.type === 'checkbox' ? bundle.checked : bundle.value === '1';
    document.getElementById('componentsCard').hidden = !on;
    document.getElementById('batchBox').hidden = on;
    document.getElementById('addVariant').hidden = on;
    document.getElementById('variantsTitle').textContent = on ? 'Bundle SKU & price' : 'Variants';
    if (on && !ct.rows.length) addComp();
  }
  bundle.addEventListener('change', sync); sync();
})();
</script>
