<?php require_once __DIR__ . '/../stock/_types.php'; ?>
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <?= $item['is_bundle'] ? '<span class="badge text-bg-info">Bundle</span>' : '' ?>
    <?= $item['track_batch'] ? '<span class="badge text-bg-secondary">Batch tracked</span>' : '' ?>
    <?= $item['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>' ?>
    <div class="text-muted mt-1"><?= e(implode(' · ', array_filter([$meta['category'], $meta['brand'], 'Unit: ' . $item['unit_name'], $meta['tax'] ? $meta['tax'] . ' ' . $meta['rate'] . '%' : null]))) ?></div>
    <?php $attrs = array_filter(['Type' => App\Models\ItemTypes::LABELS[$item['item_type']] ?? null, 'HSN' => $item['hsn_code'], 'Design no.' => $item['design_no'], 'Composition' => $item['composition'], 'Width' => $item['width'],
        'GSM / weight' => $item['gsm'], 'Pattern' => $item['pattern'], 'Finish' => $item['finish']]); ?>
    <?php if ($attrs): ?><div class="d-flex flex-wrap gap-1 mt-2"><?php foreach ($attrs as $l => $val): ?><span class="badge text-bg-light border fw-normal"><span class="text-muted"><?= e($l) ?>:</span> <?= e($val) ?></span><?php endforeach; ?></div><?php endif; ?>
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

<?php require dirname(__DIR__) . '/settings/_cf_show.php'; ?>
<?php if (!empty($rates)): $bestBy = []; foreach ($rates as $r) if (!isset($bestBy[$r['variant_id']])) $bestBy[$r['variant_id']] = $r['net']; ?>
<div class="card mb-3"><div class="card-header"><i class="bi bi-tags me-1"></i>Supplier rates — current, cheapest first</div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th><?= e(term('supplier')) ?></th><th>Variant</th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net</th><th class="text-end">Min qty</th><th>Lead time</th></tr></thead><tbody>
  <?php foreach ($rates as $r): ?><tr><td><a href="<?= url("suppliers/{$r['supplier_id']}#rates") ?>"><?= e($r['supplier_name']) ?></a><?= abs($r['net'] - $bestBy[$r['variant_id']]) < 0.005 ? ' <span class="badge text-bg-success">Lowest</span>' : '' ?></td>
    <td class="small"><?= e($r['vname'] ?: $r['sku']) ?></td><td class="text-end"><?= e(money($r['rate'])) ?></td><td class="text-end"><?= (float)$r['discount_pct'] > 0 ? e((float)$r['discount_pct']) . '%' : '—' ?></td>
    <td class="text-end fw-semibold"><?= e(money($r['net'])) ?></td><td class="text-end"><?= (float)$r['min_qty'] > 0 ? e(qty($r['min_qty'])) : '—' ?></td><td><?= $r['lead_time_days'] !== null ? (int)$r['lead_time_days'] . ' days' : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php foreach ($variants as $v): ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span><strong><?= e($v['name'] ?: 'Default') ?></strong><?= ($v['colour'] || $v['size']) ? ' <span class="badge text-bg-light border fw-normal">' . e(implode(' · ', array_filter([$v['colour'], $v['size']]))) . '</span>' : '' ?> <span class="text-muted">SKU <?= e($v['sku']) ?><?= $v['barcode'] ? ' · Barcode ' . e($v['barcode']) : '' ?></span> <?= $v['is_active'] ? '' : '<span class="badge text-bg-dark">Hidden</span>' ?></span>
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
          <table class="table table-sm mb-0"><thead><tr><th><?= e(term('batch')) ?></th><th><?= e(term('supplier')) ?> lot</th><th>Received</th><th class="text-end">Roll size</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($v['batches'] as $b): $st = App\Models\Stock::batchStatus((float)$b['balance'], (float)$b['received_qty']); ?>
              <tr><td><a href="<?= url("stock/batches/{$b['id']}") ?>"><?= e($b['batch_no']) ?></a></td><td><?= e($b['supplier_lot'] ?? '') ?></td><td><?= e(fdate($b['received_date'])) ?></td>
                <td class="text-end"><?= e(qty($b['received_qty'])) ?></td><td class="text-end"><?= e(qty($b['balance'])) ?></td><td><?= e($st) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$v['batches']): ?><tr><td colspan="6" class="text-muted">No <?= e(term('rolls', true)) ?> yet.</td></tr><?php endif; ?>
          </tbody></table>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$item['is_bundle']): ?>
<div class="card"><div class="card-header">Recent stock movements</div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>SKU</th><th><?= e(term('warehouse')) ?></th><th><?= e(term('rack')) ?></th><th><?= e(term('batch')) ?></th><th class="text-end">Qty</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?>
    <tr><td class="text-nowrap"><?= e($h['created_at']) ?></td><td><?= e(stock_type($h['type'])) ?></td><td><?= e($h['sku']) ?></td><td><?= e($h['warehouse']) ?></td><td><?= e($h['rack'] ?? '') ?></td>
      <td><?= $h['batch_no'] ? e($h['batch_no']) : '' ?></td>
      <td class="text-end <?= $h['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $h['qty_change'] > 0 ? '+' : '' ?><?= e(qty($h['qty_change'])) ?></td><td><?= e($h['note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$history): ?><tr><td colspan="8" class="text-muted">No movements yet. Add stock with a stock adjustment (opening stock).</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php endif; ?>
