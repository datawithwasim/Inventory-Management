<?php require_once __DIR__ . '/../stock/_types.php'; ?>
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <?= $item['is_bundle'] ? '<span class="badge text-bg-info">Bundle</span>' : '' ?>
    <?= $item['track_batch'] ? '<span class="badge text-bg-secondary">Batch tracked</span>' : '' ?>
    <?= $item['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>' ?>
    <div class="text-muted mt-1"><?= e(implode(' · ', array_filter([$meta['category'], $meta['brand'], 'Unit: ' . $item['unit_name'], $meta['tax'] ? $meta['tax'] . ' ' . $meta['rate'] . '%' : null]))) ?></div>
    <?php if ($item['description']): ?><p class="mt-2 mb-0"><?= nl2br(e($item['description'])) ?></p><?php endif; ?>
  </div>
  <div class="text-nowrap">
    <?php if (can('items.edit')): ?><a class="btn btn-primary" href="<?= url("items/{$item['id']}/edit") ?>">Edit</a><?php endif; ?>
    <?php if (can('items.delete')): ?><form class="d-inline" method="post" action="<?= url("items/{$item['id']}/delete") ?>" onsubmit="return confirm('Delete this item?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete</button></form><?php endif; ?>
  </div>
</div>

<?php if ($item['is_bundle']): ?>
  <div class="card mb-3"><div class="card-header">Contents — <?= (int)$bundleAvailable ?> set(s) can be made from stock</div>
  <table class="table mb-0"><thead><tr><th>Component</th><th class="text-end">Qty per set</th><th class="text-end">In stock</th></tr></thead><tbody>
    <?php foreach ($components as $c): ?><tr><td><?= e($c['iname'] . ($c['vname'] ? ' — ' . $c['vname'] : '') . ' (' . $c['sku'] . ')') ?></td>
      <td class="text-end"><?= e(qty($c['qty'])) ?> <?= e($c['unit']) ?></td><td class="text-end"><?= e(qty($c['have'])) ?> <?= e($c['unit']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>

<?php foreach ($variants as $v): ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span><strong><?= e($v['name'] ?: 'Default') ?></strong> <span class="text-muted">SKU <?= e($v['sku']) ?><?= $v['barcode'] ? ' · Barcode ' . e($v['barcode']) : '' ?></span> <?= $v['is_active'] ? '' : '<span class="badge text-bg-dark">Hidden</span>' ?></span>
      <span class="text-muted">Cost <?= e(money($v['cost_price'])) ?> · Sale <?= e(money($v['sale_price'])) ?></span>
    </div>
    <div class="card-body">
      <?php if (!$item['is_bundle']): ?>
        <div class="mb-2"><strong><?= e(qty($v['total'])) ?> <?= e($item['unit']) ?></strong> in stock
          <?php foreach ($v['by_wh'] as $w): ?><span class="badge text-bg-light border ms-1"><?= e($w['name']) ?>: <?= e(qty($w['qty'])) ?></span><?php endforeach; ?></div>
        <?php if ($v['by_rack']): ?>
          <div class="mb-2 small"><i class="bi bi-geo-alt text-muted"></i> <span class="text-muted">Kept at:</span>
            <?php foreach ($v['by_rack'] as $r): ?><span class="badge text-bg-<?= $r['rack'] === '' ? 'warning' : 'primary' ?> ms-1"><?= e($r['warehouse']) ?> · <?= e($r['rack'] !== '' ? $r['rack'] : 'No rack') ?>: <?= e(qty($r['qty'])) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($item['track_batch']): ?>
          <table class="table table-sm mb-0"><thead><tr><th>Batch</th><th>Supplier lot</th><th>Received</th><th class="text-end">Roll size</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($v['batches'] as $b): $st = App\Models\Stock::batchStatus((float)$b['balance'], (float)$b['received_qty']); ?>
              <tr><td><a href="<?= url("stock/batches/{$b['id']}") ?>"><?= e($b['batch_no']) ?></a></td><td><?= e($b['supplier_lot'] ?? '') ?></td><td><?= e($b['received_date']) ?></td>
                <td class="text-end"><?= e(qty($b['received_qty'])) ?></td><td class="text-end"><?= e(qty($b['balance'])) ?></td><td><?= e($st) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$v['batches']): ?><tr><td colspan="6" class="text-muted">No batches yet.</td></tr><?php endif; ?>
          </tbody></table>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$item['is_bundle']): ?>
<div class="card"><div class="card-header">Recent stock movements</div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>SKU</th><th>Warehouse</th><th>Rack</th><th>Batch</th><th class="text-end">Qty</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?>
    <tr><td class="text-nowrap"><?= e($h['created_at']) ?></td><td><?= e(stock_type($h['type'])) ?></td><td><?= e($h['sku']) ?></td><td><?= e($h['warehouse']) ?></td><td><?= e($h['rack'] ?? '') ?></td>
      <td><?= $h['batch_no'] ? e($h['batch_no']) : '' ?></td>
      <td class="text-end <?= $h['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $h['qty_change'] > 0 ? '+' : '' ?><?= e(qty($h['qty_change'])) ?></td><td><?= e($h['note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$history): ?><tr><td colspan="8" class="text-muted">No movements yet. Add stock with a stock adjustment (opening stock).</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php endif; ?>
