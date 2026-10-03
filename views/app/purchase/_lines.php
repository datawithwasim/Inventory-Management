<?php
/** @var string $mode req|po|grn  @var array $oldLines  @var array $racks (grn) */
$heads = [
    'req' => ['Item', 'Qty', 'Note'],
    'po'  => ['Item', 'Qty', 'Unit price', 'Tax %', 'Line total'],
    'grn' => ['Item', 'Rack', 'Supplier lot (rolls)', 'Qty received', 'Unit price', 'Tax %'],
][$mode];
?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(term('items')) ?></span>
  <?php if (empty($noAdd)): ?><button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Add <?= e(term('item', true)) ?></button><?php else: ?><button type="button" id="addLine" hidden></button><?php endif; ?></div>
<div class="table-responsive" style="overflow:visible"><table class="table mb-0 align-middle" id="lineTable">
  <thead><tr><?php foreach ($heads as $h): ?><th><?= e($h) ?></th><?php endforeach; ?><th></th></tr></thead>
  <tbody></tbody>
  <?php if ($mode === 'po'): ?><tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-end" id="grandTotal">0.00</th><th></th></tr></tfoot><?php endif; ?>
</table></div></div>
<script type="application/json" id="initialLines"><?= json_encode($oldLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if ($mode === 'grn'): ?><script type="application/json" id="rackData"><?= json_encode((object)($racks ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endif; ?>
<script src="<?= asset('js/purchase-lines.js') ?>" data-mode="<?= e($mode) ?>" data-lookup="<?= url('lookup/items') ?>"></script>
