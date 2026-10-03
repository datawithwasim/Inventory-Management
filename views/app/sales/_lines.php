<?php /** quote / order line table with live totals */ ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(term('items')) ?></span>
  <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Add <?= e(term('item', true)) ?></button></div>
<div class="table-responsive" style="overflow:visible"><table class="table mb-0 align-middle" id="lineTable"<?= fflines($lineEntity ?? '') ?>>
  <thead><tr><th><?= e(term('item')) ?></th><th>Qty</th><th>Price</th><th>Disc %</th><th>Tax %</th><th class="text-end">Total</th><th></th></tr></thead><tbody></tbody></table></div>
  <div class="card-footer"><div class="row justify-content-end"><div class="col-md-4"><table class="table table-sm table-borderless mb-0">
    <tr><td>Gross</td><td class="text-end" id="tSub">0.00</td></tr><tr><td>Discount</td><td class="text-end" id="tDisc">0.00</td></tr><tr><td>Tax</td><td class="text-end" id="tTax">0.00</td></tr>
    <tr><td>Delivery &amp; installation</td><td class="text-end" id="tCharges">0.00</td></tr><tr class="border-top"><th>Total</th><th class="text-end" id="tTotal">0.00</th></tr></table></div></div></div></div>
<script type="application/json" id="initialLines"><?= json_encode($oldLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= asset('js/sales-lines.js') ?>" data-lookup="<?= url('lookup/sale-items') ?>"></script>
