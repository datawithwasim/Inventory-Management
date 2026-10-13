<?php
$isBundle = $item ? (bool)$item['is_bundle'] : (bool)old('is_bundle');
$v = fn($k, $d = '') => e(old($k, $item[$k] ?? $d));
$sel = fn($k, $id) => (int)old($k, $item[$k] ?? 0) === (int)$id ? 'selected' : '';
?>
<form method="post" action="<?= $item ? url("items/{$item['id']}") : url('items') ?>"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card mb-3"><div class="card-body">
    <div class="ffgrid <?= ffclass('item') ?>">
    <div class="mb-3"<?= ffa('item.name') ?>><label class="form-label"><?= fl('item.name', e(term('item')) . ' name') ?></label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="190"></div>
    <div class="row">
      <?php if (ff('item.item_type')): $it = old('item_type', $item['item_type'] ?? 'other'); ?><div class="col-md-4 mb-3"<?= ffa('item.item_type') ?>><label class="form-label"><?= fl('item.item_type', 'Product type') ?><?= ffstar('item.item_type') ?></label><select name="item_type" id="itemType" class="form-select"<?= ffreq('item.item_type') ?>>
        <?php foreach (App\Models\ItemTypes::LABELS as $k => $l): ?><option value="<?= e($k) ?>" <?= $it === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-md-4 mb-3"<?= ffa('item.category_id') ?>><label class="form-label"><?= fl('item.category_id', 'Category') ?></label><select name="category_id" class="form-select"><option value="">—</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('category_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <?php if (ff('item.brand_id')): ?><div class="col-md-4 mb-3"<?= ffa('item.brand_id') ?>><label class="form-label"><?= fl('item.brand_id', 'Brand') ?><?= ffstar('item.brand_id') ?></label><select name="brand_id" class="form-select"<?= ffreq('item.brand_id') ?>><option value="">—</option>
        <?php foreach ($brands as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('brand_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-md-4 mb-3"<?= ffa('item.unit_id') ?>><label class="form-label"><?= fl('item.unit_id', 'Unit') ?></label><select name="unit_id" class="form-select" required>
        <?php foreach ($units as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('unit_id', $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['short_name']) ?>)</option><?php endforeach; ?></select></div>
    </div>
    <?php if (ff('item.tax_id')): ?><div class="row">
      <div class="col-md-4 mb-3"<?= ffa('item.tax_id') ?>><label class="form-label"><?= fl('item.tax_id', 'Tax') ?><?= ffstar('item.tax_id') ?></label><select name="tax_id" class="form-select"<?= ffreq('item.tax_id') ?>><option value="">No tax</option>
        <?php foreach ($taxes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $sel('tax_id', $c['id']) ?>><?= e($c['name']) ?> (<?= e($c['rate']) ?>%)</option><?php endforeach; ?></select></div>
    </div><?php endif; ?>
    <?php if (ff('item.hsn_code')): ?><div class="row"><div class="col-md-4 mb-3"<?= ffa('item.hsn_code') ?>><label class="form-label"><?= fl('item.hsn_code', 'HSN code') ?><?= ffstar('item.hsn_code') ?></label><input name="hsn_code" class="form-control" maxlength="20" value="<?= $v('hsn_code') ?>"<?= ffreq('item.hsn_code') ?>></div></div><?php endif; ?>
    <?php if (ff('item.description')): ?><div class="mb-0"<?= ffa('item.description') ?>><label class="form-label"><?= fl('item.description', 'Description') ?><?= ffstar('item.description') ?></label><textarea name="description" class="form-control" rows="2"<?= ffreq('item.description') ?>><?= $v('description') ?></textarea></div><?php endif; ?>
    <?= ffextras('item', $cfFields, $cfValues) ?>
    </div>
  </div></div>

  </div>
  <div class="col-lg-4"><div class="card mb-3"><div class="card-body">
    <?php if (!$item): ?>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_bundle" value="1" id="isBundle" <?= $isBundle ? 'checked' : '' ?>>
        <label class="form-check-label" for="isBundle">Bundle / set <small class="text-muted d-block">e.g. dining table + 6 chairs sold as one</small></label></div>
    <?php else: ?><input type="hidden" id="isBundle" value="<?= (int)$isBundle ?>"><?php endif; ?>
    <div id="batchBox" <?= $isBundle ? 'hidden' : '' ?>>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="track_batch" value="1" id="trk" <?= old('track_batch', $item['track_batch'] ?? 0) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
        <label class="form-check-label" for="trk">Track by <?= e(term('batch', true)) ?> (one roll = one batch)
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
<div class="mt-3">
  <div class="card mb-3"><div class="card-header">SKU &amp; price <small class="text-muted ms-1">every colour, design or size is its own <?= e(term('item')) ?>; the supplier item carries the details</small></div>
    <div class="card-body"><div class="row g-3">
      <div class="col-md-3"><label class="form-label">SKU</label><input name="sku" class="form-control" maxlength="60" placeholder="auto" value="<?= e(old('sku', $variants[0]['sku'] ?? '')) ?>"></div>
      <div class="col-md-3"><label class="form-label">Barcode</label><input name="barcode" class="form-control" maxlength="60" value="<?= e(old('barcode', $variants[0]['barcode'] ?? '')) ?>"></div>
      <div class="col-md-3"><label class="form-label">Sale price</label><input name="sale_price" type="number" step="0.01" min="0" class="form-control" value="<?= e(old('sale_price', $variants[0]['sale_price'] ?? '')) ?>"></div>
      <div class="col-md-3"><label class="form-label" title="For stock value and as a last-resort price. Purchase orders use the supplier's rate first.">Default cost</label><input name="cost_price" type="number" step="0.01" min="0" class="form-control" value="<?= e(old('cost_price', $variants[0]['cost_price'] ?? '')) ?>"></div>
    </div></div></div>

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
</div>

</form>
<script>
(function () {
  var ct = document.querySelector('#compTable tbody');
  var cn = ct.rows.length;
  var pick = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 't' => $p['item_name'] . ($p['name'] ? ' — ' . $p['name'] : '') . ' (' . $p['sku'] . ')'], $pickable)) ?>;
  function el(html) { var t = document.createElement('tbody'); t.innerHTML = html; return t.firstElementChild; }
  function addComp() {
    var i = cn++, tr = el('<tr><td><select class="form-select form-select-sm"></select></td>' +
      '<td><input type="number" step="0.001" min="0" class="form-control form-control-sm" value="1"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td></tr>');
    var s = tr.querySelector('select'); s.name = 'components[' + i + '][variant_id]';
    pick.forEach(function (p) { var o = document.createElement('option'); o.value = p.id; o.textContent = p.t; s.appendChild(o); });
    tr.querySelector('input').name = 'components[' + i + '][qty]';
    ct.appendChild(tr);
  }
  document.getElementById('addComp').onclick = addComp;
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('rm')) return;
    var tb = e.target.closest('tbody');
    e.target.closest('tr').remove();
  });
  var bundle = document.getElementById('isBundle');
  function sync() {
    var on = bundle.type === 'checkbox' ? bundle.checked : bundle.value === '1';
    document.getElementById('componentsCard').hidden = !on;
    document.getElementById('batchBox').hidden = on;
    if (on && !ct.rows.length) addComp();
  }
  bundle.addEventListener('change', sync); sync();
  var typ = document.getElementById('itemType'), trk = document.getElementById('trk'), rollTypes = <?= json_encode(App\Models\ItemTypes::ROLL_TYPES) ?>;
  function syncType(user) {
    if (user && typ && trk && !trk.disabled && !bundle.checked && rollTypes.indexOf(typ.value) > -1) trk.checked = true;
  }
  if (typ) { typ.addEventListener('change', function () { syncType(true); }); syncType(false); }
})();
</script>
