<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, SKU, barcode" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="warehouse" class="form-select"><option value="">All warehouses</option>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto form-check d-flex align-items-center ms-2"><input class="form-check-input me-2" type="checkbox" name="low" value="1" id="low" <?= $low ? 'checked' : '' ?>><label for="low" class="form-check-label">Low stock only</label></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end">
    <?php if (can('stock.adjust')): ?><a class="btn btn-primary" href="<?= url('stock/adjustments/create') ?>"><i class="bi bi-plus-lg"></i> Adjust / opening stock</a><?php endif; ?>
  </div>
</form>
<div class="card mb-3"><div class="card-body py-2"><span class="text-muted">Stock value<?= $wh ? ' (selected warehouse)' : '' ?>:</span> <strong><?= e(money($totalValue)) ?></strong>
  <span class="text-muted small ms-2">quantity × batch cost (or item cost)</span></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Item</th><th>SKU</th><th class="text-end">On hand</th><th>Where (rack)</th><th class="text-end">Batches</th><th class="text-end">Value</th><th>Reorder</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $isLow = $r['reorder_level'] > 0 && $r['item_total'] <= $r['reorder_level']; ?>
    <tr>
      <td><a href="<?= url("items/{$r['item_id']}") ?>"><?= e($r['name']) ?><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?></a></td>
      <td><?= e($r['sku']) ?></td>
      <td class="text-end"><?= e(qty($r['qty'])) ?> <?= e($r['unit']) ?></td>
      <td class="small"><?php foreach (array_slice($r['racks'], 0, 4) as $k): ?><span class="badge text-bg-<?= $k['code'] === '' ? 'warning' : 'primary' ?> me-1"><?= $wh ? '' : e($k['warehouse']) . ' · ' ?><?= e($k['code'] !== '' ? $k['code'] : 'No rack') ?>: <?= e(qty($k['qty'])) ?></span><?php endforeach; ?><?= count($r['racks']) > 4 ? '…' : '' ?></td>
      <td class="text-end"><?= $r['track_batch'] ? (int)$r['batches'] : '—' ?></td>
      <td class="text-end"><?= e(money($r['value'])) ?></td>
      <td><?= $isLow ? '<span class="badge text-bg-danger">Low (≤ ' . e(qty($r['reorder_level'])) . ')</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No stock to show.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
