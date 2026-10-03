<?php  $S = fn($k) => Core\Settings::get($k); ?>
<div class="card" style="max-width:820px"><div class="card-body">
<form method="post" action="<?= url('settings/workflow') ?>"><?= csrf_field() ?>
  <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="po_approval" value="1" id="a1" <?= $S('po_approval') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="a1"><strong>Purchase orders need approval</strong><span class="d-block text-muted small">A person with the "approve" permission must approve a purchase order before goods can be received on it.</span></label></div>
  <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="negative_stock" value="1" id="a2" <?= $S('negative_stock') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="a2"><strong>Allow selling more than the stock shows (negative stock)</strong><span class="d-block text-muted small">For ordinary items only. Fabric rolls (batch-tracked) can never go below zero, because a roll physically cannot.</span></label></div>
  <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="require_rack" value="1" id="a3" <?= $S('require_rack') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="a3"><strong>Always choose a rack when stock comes in</strong><span class="d-block text-muted small">On goods receipts and stock additions, for warehouses that have racks set up.</span></label></div>
  <div class="mb-3" style="max-width:420px"><label class="form-label"><strong>When one item is cut from more than one roll (shade may differ)</strong></label>
    <select name="shade_rule" class="form-select"><option value="warn" <?= $S('shade_rule') !== 'block' ? 'selected' : '' ?>>Warn, but allow</option><option value="block" <?= $S('shade_rule') === 'block' ? 'selected' : '' ?>>Do not allow</option></select></div>
  <div class="mb-3" style="max-width:300px"><label class="form-label"><strong>Highest discount staff can give (%)</strong></label>
    <input type="number" step="0.01" min="0" max="100" name="max_discount" class="form-control" value="<?= e(old('max_discount', $S('max_discount'))) ?>">
    <div class="form-text">0 means no limit. People with the sales "approve" permission (and owners) can go above it.</div></div>
  <button class="btn btn-primary">Save</button>
</form></div></div>
