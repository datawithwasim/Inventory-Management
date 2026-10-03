<?php require_once __DIR__ . '/_types.php'; ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Item, SKU, batch no." value="<?= e($q) ?>"></div>
  <div class="col-md-2"><select name="type" class="form-select"><option value="">All types</option>
    <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select name="warehouse" class="form-select"><option value="">All warehouses</option>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $wh === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-md-2"><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
  <thead><tr><th>When</th><th>Type</th><th>Item</th><th>Warehouse</th><th>Batch</th><th class="text-end">Qty</th><th>By</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td class="text-nowrap"><?= e($r['created_at']) ?></td><td><?= e(stock_type($r['type'])) ?></td>
      <td><?= e($r['item_name']) ?><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?> <small class="text-muted"><?= e($r['sku']) ?></small></td>
      <td><?= e($r['warehouse']) ?></td>
      <td><?= $r['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$r['batch_id']) . '">' . e($r['batch_no']) . '</a>' : '' ?></td>
      <td class="text-end <?= $r['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $r['qty_change'] > 0 ? '+' : '' ?><?= e(qty($r['qty_change'])) ?></td>
      <td><?= e($r['user_name'] ?? '') ?></td><td><?= e($r['note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">No movements found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
