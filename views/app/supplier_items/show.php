<?php
$canEdit = can('suppliers.edit');
$linked = (bool)$r['variant_id'];
ob_start(); ?>
<?php if (!$linked): ?><div class="alert alert-warning small mb-3"><i class="bi bi-link-45deg"></i> This supplier item is <b>not linked</b> to one of your items yet. Link it to use it on purchase orders and to keep a rate list for it.
  <?php if ($canEdit): ?><a class="fw-semibold" href="<?= url("purchase/supplier-items/{$r['id']}/edit") ?>">Link it now</a><?php endif; ?></div><?php endif; ?>
<?php if ($linked): ?>
<section class="card mb-3" id="rates">
  <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-tags me-1"></i>Rates <span class="act-n"><?= count($rates) ?></span></span>
    <?php if (can('reports.view')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('reports/rate-history?supplier=' . (int)$r['supplier_id']) ?>"><i class="bi bi-clock-history"></i> History report</a><?php endif; ?></div>
  <?php if ($canEdit): ?><form class="row g-2 align-items-end p-3 border-bottom" method="post" action="<?= url("suppliers/{$r['supplier_id']}/rates") ?>"><?= csrf_field() ?>
    <input type="hidden" name="variant_id" value="<?= (int)$r['variant_id'] ?>"><input type="hidden" name="return" value="purchase/supplier-items/<?= (int)$r['id'] ?>#rates">
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Rate</label><input name="rate" type="number" step="0.01" min="0" class="form-control form-control-sm" required></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Discount %</label><input name="discount_pct" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" placeholder="0"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Min. qty</label><input name="min_qty" type="number" step="0.001" min="0" class="form-control form-control-sm" value="<?= (float)$r['min_order_qty'] > 0 ? e(rtrim(rtrim(number_format((float)$r['min_order_qty'], 3, '.', ''), '0'), '.')) : '' ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Valid from</label><input name="valid_from" type="date" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">Add rate</button></div></form><?php endif; ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Valid</th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net</th><th class="text-end">Min qty</th><th></th></tr></thead><tbody>
    <?php foreach ($rates as $x): ?><tr class="<?= $x['is_current'] ? '' : 'text-muted' ?>"><td class="small"><?= e(fdate($x['valid_from'])) ?> → <?= $x['valid_to'] ? e(fdate($x['valid_to'])) : 'open' ?><?= $x['is_current'] ? ' <span class="badge text-bg-success">Current</span>' : '' ?></td>
      <td class="text-end"><?= e(money($x['rate'])) ?></td><td class="text-end"><?= (float)$x['discount_pct'] > 0 ? e((float)$x['discount_pct']) . '%' : '—' ?></td><td class="text-end fw-semibold"><?= e(money($x['net'])) ?></td><td class="text-end"><?= (float)$x['min_qty'] > 0 ? e(qty($x['min_qty'])) : '—' ?></td>
      <td class="text-end"><?php if ($canEdit): ?><form class="d-inline" method="post" action="<?= url("suppliers/{$r['supplier_id']}/rates/{$x['id']}/delete") ?>" onsubmit="return confirm('Remove this rate?')"><?= csrf_field() ?><input type="hidden" name="return" value="purchase/supplier-items/<?= (int)$r['id'] ?>#rates"><button class="btn btn-sm btn-link text-danger px-1" aria-label="Remove rate"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$rates): ?><tr><td colspan="6" class="text-muted">No rate yet. Add what this supplier charges for it above.</td></tr><?php endif; ?></tbody></table></div>
</section>
<section class="card mb-3" id="purchases"><div class="card-header">Recent purchases</div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Goods receipt</th><th>Date</th><th class="text-end">Qty</th><th class="text-end">Price</th></tr></thead><tbody>
    <?php foreach ($purchases as $p): ?><tr><td><a href="<?= url('purchase/grns/' . (int)$p['grn_id']) ?>"><?= e($p['grn_no']) ?></a></td><td><?= e(fdate($p['received_date'])) ?></td><td class="text-end"><?= e(qty($p['qty'])) ?> <?= e($r['unit'] ?? '') ?></td><td class="text-end"><?= e(money($p['unit_price'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$purchases): ?><tr><td colspan="4" class="text-muted">Nothing bought from this supplier yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'supplier_item', 'row' => $r, 'name' => $r['supplier_name'] ?: $r['supplier_code'], 'back' => 'purchase/supplier-items', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => '<span class="badge text-bg-light border">' . e($r['supplier']) . '</span> ' . ($r['is_preferred'] ? '<span class="badge text-bg-success">Preferred</span> ' : '') . ($linked ? '' : '<span class="badge text-bg-warning">Not linked</span> ') . ($r['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>'),
    'related' => array_filter(['rates' => $linked ? 'Rates' : null, 'purchases' => $linked ? 'Recent purchases' : null]),
    'facts' => ['rate' => ['Current rate', $cur ? e(money(App\Models\Purchase::netRate($cur))) . ((float)$cur['discount_pct'] > 0 ? ' <span class="text-muted small">(' . e(money($cur['rate'])) . ' less ' . e((float)$cur['discount_pct']) . '%)</span>' : '') : ''],
        'last_paid' => ['Last paid', $last ? e(money($last['unit_price'])) . ' <span class="text-muted small">on ' . e(fdate($last['received_date'])) . '</span>' : ''],
        'preferred' => ['Preferred source', $r['is_preferred'] ? 'Yes' : '']],
    'actions' => ($canEdit ? '<a class="btn btn-sm btn-primary" href="' . url("purchase/supplier-items/{$r['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('suppliers.delete') ? '<li><form method="post" action="' . url("purchase/supplier-items/{$r['id']}/delete") . '" onsubmit="return confirm(\'Remove this supplier item? Its rates stay in the history.\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
