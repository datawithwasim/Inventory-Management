<?php
$qs = fn(array $extra = []) => http_build_query(array_merge($query, $extra));
$labels = ['from' => 'From', 'to' => 'To', 'warehouse' => term('warehouse'), 'category' => 'Category', 'customer' => term('customer'), 'supplier' => term('supplier'),
    'days' => 'Idle for at least (days)', 'q' => 'Search', 'by' => 'Group', 'status' => 'Status', 'type' => 'Movement', 'kind' => 'Show', 'stock' => 'Stock', 'order' => 'Order'];
$isLow = $def['slug'] === 'low-stock';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><a href="<?= url('reports') ?>" class="small text-decoration-none">&laquo; All reports</a>
    <p class="text-muted mb-0"><?= e($def['desc']) ?></p></div>
  <div class="btn-group btn-group-sm">
    <?php if (can('reports.export')): ?>
      <a class="btn btn-outline-secondary" href="<?= url('reports/' . $def['slug'] . '/export/xlsx') . '?' . e($qs()) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
      <a class="btn btn-outline-secondary" href="<?= url('reports/' . $def['slug'] . '/export/csv') . '?' . e($qs()) ?>"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" target="_blank" href="<?= url('reports/' . $def['slug']) . '?' . e($qs(['print' => 1])) ?>"><i class="bi bi-printer me-1"></i>Print / PDF</a>
  </div>
</div>

<form method="get" class="card mb-3"><div class="card-body row g-2 align-items-end">
  <?php foreach ($def['filters'] as $name): ?>
    <div class="col-6 col-md-3 col-xl-2">
      <label class="form-label small mb-1" for="f-<?= e($name) ?>"><?= e($labels[$name] ?? $name) ?></label>
      <?php if (in_array($name, ['from', 'to'], true)): ?>
        <input type="date" class="form-control form-control-sm" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($f[$name]) ?>">
      <?php elseif ($name === 'days'): ?>
        <input type="number" min="1" class="form-control form-control-sm" id="f-days" name="days" value="<?= (int)$f['days'] ?>">
      <?php elseif ($name === 'q'): ?>
        <input type="search" class="form-control form-control-sm" id="f-q" name="q" value="<?= e($f['q']) ?>" placeholder="Name, SKU…">
      <?php elseif (isset($lists[$name])): ?>
        <select class="form-select form-select-sm" id="f-<?= e($name) ?>" name="<?= e($name) ?>">
          <option value="0"><?= in_array($name, ['customer', 'supplier'], true) && !empty($def['needs']) ? '— choose —' : 'All' ?></option>
          <?php foreach ($lists[$name] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$f[$name] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
        </select>
      <?php else: ?>
        <select class="form-select form-select-sm" id="f-<?= e($name) ?>" name="<?= e($name) ?>">
          <?php foreach ($def['select'][$name] as $v => $lbl): ?><option value="<?= e((string)$v) ?>" <?= (string)$f[$name] === (string)$v ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="col-auto"><button class="btn btn-sm btn-primary">Show report</button></div>
</div></form>

<?php if ($truncated): ?>
  <div class="alert alert-info py-2 small">Showing the first <?= (int)$screenLimit ?> rows. Narrow the filters, or download Excel/CSV to get everything.</div>
<?php endif; ?>

<?php if ($isLow && can('purchase.create') && $rows): ?>
<form method="post" action="<?= url('reports/low-stock/create-pos') ?>">
  <?= csrf_field() ?>
  <div class="card"><div class="card-body p-0"><div class="table-responsive"><?php $selectable = true; require __DIR__ . '/_table.php'; ?></div></div></div>
  <div class="mt-3 d-flex align-items-center gap-3">
    <button class="btn btn-primary" onclick="return confirm('Create draft purchase orders for the ticked items?')"><i class="bi bi-cart-plus me-1"></i>Create draft purchase orders</button>
    <span class="text-muted small">One draft PO per last supplier. Items never bought before go into one requisition. Nothing is sent until you submit the PO.</span>
  </div>
</form>
<script>
document.getElementById('pick-all')?.addEventListener('change', function () {
  document.querySelectorAll('.pick:not(:disabled)').forEach(function (c) { c.checked = this.checked; }, this);
});
</script>
<?php else: ?>
  <div class="card"><div class="card-body p-0"><div class="table-responsive"><?php require __DIR__ . '/_table.php'; ?></div></div></div>
<?php endif; ?>
<div class="text-muted small mt-2"><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?></div>
