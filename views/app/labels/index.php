<div class="mb-3">
  <div class="btn-group btn-group-sm">
    <a class="btn btn-outline-secondary <?= $type === 'items' ? 'active' : '' ?>" href="?type=items"><?= e(term('items')) ?> (SKU / price)</a>
    <a class="btn btn-outline-secondary <?= $type === 'rolls' ? 'active' : '' ?>" href="?type=rolls"><?= e(term('batches')) ?> (roll labels)</a>
  </div>
</div>
<form method="get" class="row g-2 mb-3">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, SKU or batch" value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>
<form method="get" data-noauto action="<?= url('labels/print') ?>" target="_blank">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="card mb-3"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th><?= e(term('item')) ?></th><th>Barcode</th><?= $type === 'rolls' ? '<th class="text-end">Balance</th>' : '<th class="text-end">Price</th>' ?><th style="width:110px">Copies</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['item']) ?><?= $r['variant'] ? ' — ' . e($r['variant']) : '' ?></td><td><code><?= e($r['code']) ?></code></td>
        <td class="text-end"><?= $type === 'rolls' ? e(qty($r['bal'])) . ' ' . e($r['unit']) : e(money($r['sale_price'])) ?></td>
        <td><input type="number" min="0" max="200" class="form-control form-control-sm" name="n[<?= (int)$r['id'] ?>]" value="<?= $type === 'rolls' ? 1 : 0 ?>"></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="text-muted">Nothing found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small">Label size</label>
      <select name="size" class="form-select"><?php foreach ($sizes as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-upc me-1"></i>Make labels</button></div>
  </div>
  <p class="text-muted small mt-2">Items without a barcode use their SKU. Roll labels carry the batch number, size and rack. Labels use Code 128, which every normal scanner reads.</p>
</form>
