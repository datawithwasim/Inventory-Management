<?php require_once __DIR__ . '/../stock/_types.php'; ?>
<?php ob_start(); ?>
<?php if ($item['is_bundle']): ?>
  <div class="card mb-3" id="contents"><div class="card-header">Contents — <?= (int)$bundleAvailable ?> set(s) can be made from stock</div>
  <table class="table mb-0"><thead><tr><th>Component</th><th class="text-end">Qty per set</th><th class="text-end">In stock</th></tr></thead><tbody>
    <?php foreach ($components as $c): ?><tr><td><?= e($c['iname'] . ($c['vname'] ? ' — ' . $c['vname'] : '') . ' (' . $c['sku'] . ')') ?></td>
      <td class="text-end"><?= e(qty($c['qty'])) ?> <?= e($c['unit']) ?></td><td class="text-end"><?= e(qty($c['have'])) ?> <?= e($c['unit']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>

<?php if (!empty($rates)): $bestBy = []; foreach ($rates as $r) if (!isset($bestBy[$r['variant_id']])) $bestBy[$r['variant_id']] = $r['net']; ?>
<div class="card mb-3" id="rates"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-tags me-1"></i>Supplier rates — current, cheapest first</span><?php if (can('reports.view')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('reports/rate-history?q=' . urlencode($item['name'])) ?>"><i class="bi bi-clock-history"></i> Rate history</a><?php endif; ?></div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th><?= e(term('supplier')) ?></th><th>Variant</th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net</th><th class="text-end">Min qty</th><th>Lead time</th></tr></thead><tbody>
  <?php foreach ($rates as $r): ?><tr><td><a href="<?= url("suppliers/{$r['supplier_id']}#rates") ?>"><?= e($r['supplier_name']) ?></a><?= !empty($r['scode']) || !empty($r['sname']) ? '<div class="small text-muted">Their: ' . e(implode(' · ', array_filter([$r['scode'] ?? '', $r['sname'] ?? '']))) . '</div>' : '' ?><?= abs($r['net'] - $bestBy[$r['variant_id']]) < 0.005 ? ' <span class="badge text-bg-success">Lowest</span>' : '' ?></td>
    <td class="small"><?= e($r['vname'] ?: $r['sku']) ?></td><td class="text-end"><?= e(money($r['rate'])) ?></td><td class="text-end"><?= (float)$r['discount_pct'] > 0 ? e((float)$r['discount_pct']) . '%' : '—' ?></td>
    <td class="text-end fw-semibold"><?= e(money($r['net'])) ?></td><td class="text-end"><?= (float)$r['min_qty'] > 0 ? e(qty($r['min_qty'])) : '—' ?></td><td><?= $r['lead_time_days'] !== null ? (int)$r['lead_time_days'] . ' days' : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>
<section class="card mb-3" id="variants"><div class="card-header d-flex justify-content-between align-items-center"><span>Variants &amp; stock <span class="act-n"><?= count($variants) ?></span></span>
  <?php if (can('items.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("items/{$item['id']}/edit") ?>"><i class="bi bi-pencil"></i> Edit variants</a><?php endif; ?></div>
  <div class="table-responsive"><table class="table var-table align-middle mb-0">
    <thead><tr><th>Variant</th><th>SKU</th><th class="text-end">Cost</th><th class="text-end">Sale</th><?php if (!$item['is_bundle']): ?><th class="text-end">In stock</th><th>Where</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($variants as $v): ?>
      <tr class="<?= $v['is_active'] ? '' : 'text-muted' ?>">
        <td><strong><?= e($v['name'] ?: 'Default') ?></strong><?= ($v['colour'] || $v['size']) ? ' <span class="badge text-bg-light border fw-normal">' . e(implode(' · ', array_filter([$v['colour'], $v['size']]))) . '</span>' : '' ?><?= $v['is_active'] ? '' : ' <span class="badge text-bg-dark">Hidden</span>' ?></td>
        <td class="small"><?= e($v['sku']) ?><?= $v['barcode'] ? '<div class="text-muted">' . e($v['barcode']) . '</div>' : '' ?></td>
        <td class="text-end"><?= e(money($v['cost_price'])) ?></td><td class="text-end"><?= e(money($v['sale_price'])) ?></td>
        <?php if (!$item['is_bundle']): ?>
          <td class="text-end fw-semibold"><?= e(qty($v['total'])) ?> <span class="text-muted fw-normal"><?= e($item['unit']) ?></span></td>
          <td class="small">
            <?php foreach ($v['by_wh'] as $w): ?><span class="badge text-bg-light border fw-normal me-1"><?= e($w['name']) ?>: <?= e(qty($w['qty'])) ?></span><?php endforeach; ?>
            <?php foreach ($v['by_rack'] as $r): ?><span class="badge text-bg-<?= $r['rack'] === '' ? 'warning' : 'primary-subtle text-primary-emphasis' ?> fw-normal me-1"><i class="bi bi-geo-alt"></i> <?= e($r['rack'] !== '' ? $r['rack'] : 'No rack') ?>: <?= e(qty($r['qty'])) ?></span><?php endforeach; ?>
            <?php if (!$v['by_wh']): ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
      <?php if (!$item['is_bundle'] && $item['track_batch']): ?>
        <tr class="var-rolls-row"><td colspan="6" class="p-0 border-0">
          <details class="var-rolls"><summary><i class="bi bi-layers"></i> <?= count($v['batches']) ?> <?= e(term('rolls', true)) ?><?= $v['batches'] ? ' · ' . e(qty(array_sum(array_column($v['batches'], 'balance')))) . ' ' . e($item['unit']) . ' left' : '' ?></summary>
            <table class="table table-sm mb-0"><thead><tr><th><?= e(term('batch')) ?></th><th><?= e(term('supplier')) ?> lot</th><th>Received</th><th class="text-end">Roll size</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
              <?php foreach ($v['batches'] as $b): $st = App\Models\Stock::batchStatus((float)$b['balance'], (float)$b['received_qty']); ?>
                <tr><td><a href="<?= url("stock/batches/{$b['id']}") ?>"><?= e($b['batch_no']) ?></a></td><td><?= e($b['supplier_lot'] ?? '') ?></td><td><?= e(fdate($b['received_date'])) ?></td>
                  <td class="text-end"><?= e(qty($b['received_qty'])) ?></td><td class="text-end"><?= e(qty($b['balance'])) ?></td><td><?= e($st) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$v['batches']): ?><tr><td colspan="6" class="text-muted">No <?= e(term('rolls', true)) ?> yet.</td></tr><?php endif; ?>
            </tbody></table></details></td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div></section>
<?php if (!$item['is_bundle']): ?>
<div class="card mb-3" id="history"><div class="card-header">Recent stock movements</div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>SKU</th><th><?= e(term('warehouse')) ?></th><th><?= e(term('rack')) ?></th><th><?= e(term('batch')) ?></th><th class="text-end">Qty</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?>
    <tr><td class="text-nowrap"><?= e(fdate(substr((string)$h['created_at'], 0, 10))) ?> <span class="text-muted"><?= e(substr((string)$h['created_at'], 11, 5)) ?></span></td><td><?= e(stock_type($h['type'])) ?></td><td><?= e($h['sku']) ?></td><td><?= e($h['warehouse']) ?></td><td><?= e($h['rack'] ?? '') ?></td>
      <td><?= $h['batch_no'] ? e($h['batch_no']) : '' ?></td>
      <td class="text-end <?= $h['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $h['qty_change'] > 0 ? '+' : '' ?><?= e(qty($h['qty_change'])) ?></td><td><?= e($h['note'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$history): ?><tr><td colspan="8" class="text-muted">No movements yet. Add stock with a stock adjustment (opening stock).</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'item', 'row' => $item, 'name' => $item['name'], 'back' => 'items', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => ($item['is_bundle'] ? '<span class="badge text-bg-info">Bundle</span> ' : '') . ($item['track_batch'] ? '<span class="badge text-bg-secondary">Tracked by roll</span> ' : '') . ($item['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>'),
    'related' => array_filter(['contents' => $item['is_bundle'] ? 'Bundle contents' : null, 'rates' => !empty($rates) ? 'Supplier rates' : null, 'variants' => 'Variants & stock', 'history' => !$item['is_bundle'] ? 'Stock movements' : null]),
    'actions' => (can('items.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("items/{$item['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('items.delete') ? '<li><form method="post" action="' . url("items/{$item['id']}/delete") . '" onsubmit="return confirm(\'Delete this item?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
