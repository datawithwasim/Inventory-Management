<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); $sid = (int)old('supplier_id', $row['supplier_id'] ?? $pre); ?>
<div class="card" style="max-width:820px"><div class="card-body">
<form method="post" action="<?= $row ? url("purchase/supplier-items/{$row['id']}") : url('purchase/supplier-items') ?>" id="siForm"><?= csrf_field() ?>
  <div class="ffgrid <?= ffclass('supplier_item') ?>">
    <?= ffextras('supplier_item', $cfFields, $cfValues) ?>
    <div class="col-md-6 mb-3"<?= ffa('supplier_item.supplier_id') ?>><label class="form-label"><?= fl('supplier_item.supplier_id', e(term('supplier'))) ?></label>
      <?php if ($row): ?><input class="form-control" value="<?= e($row['supplier']) ?>" disabled><div class="form-text">The supplier cannot be changed. Add a new supplier item for another supplier.</div>
      <?php else: ?><select name="supplier_id" class="form-select" required><option value="">Choose…</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sid === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
    <div class="col-md-6 mb-3"<?= ffa('supplier_item.supplier_name') ?>><label class="form-label"><?= fl('supplier_item.supplier_name', 'Supplier item name') ?></label><input name="supplier_name" class="form-control" maxlength="150" value="<?= $v('supplier_name') ?>" placeholder="What the supplier calls it"></div>
    <?php if (ff('supplier_item.supplier_code')): ?><div class="col-md-4 mb-3"<?= ffa('supplier_item.supplier_code') ?>><label class="form-label"><?= fl('supplier_item.supplier_code', 'Supplier item code') ?><?= ffstar('supplier_item.supplier_code') ?></label><input name="supplier_code"<?= ffreq('supplier_item.supplier_code') ?> class="form-control" maxlength="60" value="<?= $v('supplier_code') ?>"></div><?php endif; ?>
    <?php if (ff('supplier_item.variant_id')): ?><div class="col-md-8 mb-3 position-relative"<?= ffa('supplier_item.variant_id') ?> id="siOurBox"><label class="form-label"><?= fl('supplier_item.variant_id', 'Our item') ?><?= ffstar('supplier_item.variant_id') ?></label>
      <input type="hidden" name="variant_id" id="siVariant" value="<?= (int)$vid ?: '' ?>"><input id="siOur" class="form-control" autocomplete="off" placeholder="Search your items by name, SKU or barcode…" value="<?= e($ourLabel) ?>"><div id="siOurList" hidden></div>
      <div class="form-text">Which of <i>your</i> items is this? Leave empty to link it later. Several suppliers can supply the same item.</div></div><?php endif; ?>
    <?php if (ff('supplier_item.min_order_qty')): ?><div class="col-md-4 mb-3"<?= ffa('supplier_item.min_order_qty') ?>><label class="form-label"><?= fl('supplier_item.min_order_qty', 'Minimum order qty') ?><?= ffstar('supplier_item.min_order_qty') ?></label><input type="number" step="0.001" min="0" name="min_order_qty"<?= ffreq('supplier_item.min_order_qty') ?> class="form-control" value="<?= $v('min_order_qty') ?>"></div><?php endif; ?>
    <?php if (ff('supplier_item.lead_time_days')): ?><div class="col-md-4 mb-3"<?= ffa('supplier_item.lead_time_days') ?>><label class="form-label"><?= fl('supplier_item.lead_time_days', 'Lead time (days)') ?><?= ffstar('supplier_item.lead_time_days') ?></label><input type="number" min="0" max="365" name="lead_time_days"<?= ffreq('supplier_item.lead_time_days') ?> class="form-control" value="<?= $v('lead_time_days') ?>"></div><?php endif; ?>
    <?php if (ff('supplier_item.note')): ?><div class="col-12 mb-3"<?= ffa('supplier_item.note') ?>><label class="form-label"><?= fl('supplier_item.note', 'Notes') ?><?= ffstar('supplier_item.note') ?></label><input name="note"<?= ffreq('supplier_item.note') ?> class="form-control" maxlength="150" value="<?= $v('note') ?>"></div><?php endif; ?>
  </div>
  <div class="d-flex gap-4 mb-3">
    <div class="form-check"><input class="form-check-input" type="checkbox" name="is_preferred" value="1" id="siPref" <?= old('is_preferred', $row['is_preferred'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="siPref">Preferred source for this item</label></div>
    <?php if ($row): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="siAct" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="siAct">Active</label></div><?php endif; ?>
  </div>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= $row ? url("purchase/supplier-items/{$row['id']}") : url('purchase/supplier-items') ?>">Cancel</a>
</form></div></div>
<script src="<?= asset('js/item-picker.js') ?>"></script>
<script>
(function () {
  var box = document.getElementById('siOurBox'); if (!box) return;
  var inp = document.getElementById('siOur'), vid = document.getElementById('siVariant'), list = document.getElementById('siOurList');
  ItemPicker.attach({ input: inp, list: list, host: box, emptyOk: false,
    url: function (q) { return <?= json_encode(url('lookup/items')) ?> + '?q=' + encodeURIComponent(q); },
    onType: function () { vid.value = ''; },
    render: function (v) { return { title: v.item_name + (v.name ? ' — ' + v.name : ''), sub: v.sku, tag: v.track_batch ? 'Roll' : '', right: '' }; },
    pick: function (v) { vid.value = v.id; inp.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')'; } });
  document.getElementById('siForm').addEventListener('submit', function (e) {
    if (!inp.value.trim()) { vid.value = ''; return; }
    if (!vid.value) { e.preventDefault(); inp.setCustomValidity('Pick one of your items from the list, or clear the box to link it later'); inp.reportValidity(); setTimeout(function () { inp.setCustomValidity(''); }, 0); }
  });
})();
</script>
