<?php /** @var string $mode adjust|transfer  @var array $oldLines */ ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(term('items')) ?></span>
  <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Add line</button></div>
<div class="table-responsive" style="overflow:visible"><table class="table mb-0 align-middle" id="lineTable">
  <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?></th><?= $mode === 'adjust' ? '<th>Rack</th>' : '<th>From rack</th><th>To rack</th>' ?><th><?= $mode === 'adjust' ? 'Qty change (−  to remove)' : 'Qty to move' ?></th><?= $mode === 'adjust' ? '<th>Cost / unit</th>' : '' ?><th></th></tr></thead>
  <tbody></tbody></table></div></div>
<script type="application/json" id="initialLines"><?= json_encode($oldLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="rackData"><?= json_encode((object)$racks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= asset('js/item-picker.js') ?>"></script>
<script src="<?= asset('js/stock-lines.js') ?>" data-mode="<?= e($mode) ?>" data-lookup="<?= url('stock/lookup') ?>" data-batches="<?= url('stock/batch-options') ?>" data-placement="<?= url('stock/placement') ?>"></script>
