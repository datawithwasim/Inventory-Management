<?php $st = $o['status']; $canDeliver = in_array($st, ['confirmed', 'partial'], true) && $remaining > 0; ?>
<?php ob_start(); ?>
    <?php if ($st === 'draft' && can('sales.edit')): ?><a class="btn btn-outline-primary" href="<?= url("sales/orders/{$o['id']}/edit") ?>">Edit</a><?php endif; ?>
    <?php if ($st === 'draft' && can('sales.create')): ?><form method="post" action="<?= url("sales/orders/{$o['id']}/confirm") ?>"><?= csrf_field() ?><button class="btn btn-primary">Confirm &amp; reserve stock</button></form><?php endif; ?>
    <?php if ($canDeliver && can('sales.create')): ?><a class="btn btn-success" href="<?= url("sales/orders/{$o['id']}/deliver") ?>"><i class="bi bi-truck"></i> Deliver goods</a><?php endif; ?>
    <?php if ($st === 'partial' && can('sales.edit')): ?><form method="post" action="<?= url("sales/orders/{$o['id']}/close") ?>" onsubmit="return confirm('Close this order? The undelivered part is dropped.')"><?= csrf_field() ?><button class="btn btn-outline-dark">Close order</button></form><?php endif; ?>
    <?php if (in_array($st, ['draft', 'confirmed'], true) && !$deliveries && can('sales.edit')): ?><form method="post" action="<?= url("sales/orders/{$o['id']}/cancel") ?>" onsubmit="return confirm('Cancel this order?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Cancel order</button></form><?php endif; ?>
    <?php if ($st === 'draft' && can('sales.delete')): ?><form method="post" action="<?= url("sales/orders/{$o['id']}/delete") ?>" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete draft</button></form><?php endif; ?>
  <?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div id="lines"><?php $doc = $o; $qtyKey = 'qty_ordered'; $withDelivered = true; require __DIR__ . '/_doc_lines.php'; ?></div>
<div class="row g-3 mb-3" id="deliveries">
  <div class="col-lg-6"><div class="card"><div class="card-header">Deliveries &amp; invoices</div><ul class="list-group list-group-flush">
    <?php foreach ($deliveries as $d): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/deliveries/{$d['id']}") ?>"><?= e($d['delivery_no']) ?></a><span><?= e(fdate($d['delivery_date'])) ?><?= $d['invoice_id'] ? ' · <a href="' . url('sales/invoices/' . (int)$d['invoice_id']) . '">' . e($d['invoice_no']) . '</a>' : '' ?></span></li><?php endforeach; ?>
    <?php if (!$deliveries): ?><li class="list-group-item text-muted">Nothing delivered yet.</li><?php endif; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Advance taken</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($advances as $a): $left = (float)$a['amount'] - (float)$a['applied']; ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><?= e(fdate($a['paid_on'])) ?> · <?= e(App\Models\Purchase::METHODS[$a['method']] ?? $a['method']) ?><?= $a['reference'] ? ' · ' . e($a['reference']) : '' ?></span>
        <span><?= e(money($a['amount'])) ?><?= $left < $a['amount'] - 0.004 ? ' <small class="text-muted">(' . e(money($left)) . ' left)</small>' : '' ?>
        <?php if ($left > 0.004 && can('sales.edit')): ?><form class="d-inline" method="post" action="<?= url("sales/orders/{$o['id']}/advance/{$a['id']}/refund") ?>" onsubmit="return confirm('Mark the unused advance as given back to the customer?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary ms-1">Refunded</button></form><?php endif; ?></span></li><?php endforeach; ?>
      <?php if (!$advances): ?><li class="list-group-item text-muted">No advance taken.</li><?php endif; ?></ul>
    <?php if (!in_array($st, ['cancelled', 'closed'], true) && can('sales.create')): ?><div class="card-body border-top"><form class="row g-2" method="post" action="<?= url("sales/orders/{$o['id']}/advance") ?>"><?= csrf_field() ?>
      <div class="col-4"><input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
      <div class="col-3"><input type="date" name="paid_on" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-3"><select name="method" class="form-select"><?php foreach (App\Models\Purchase::METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-2"><button class="btn btn-outline-success w-100">Add</button></div>
      <div class="col-12"><input name="reference" class="form-control" placeholder="Reference (optional)" maxlength="80"></div></form></div><?php endif; ?></div></div>
</div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'sales_order', 'row' => $o, 'name' => $o['order_no'], 'back' => 'sales/orders', 'cfValues' => $cfValues ?? [], 'body' => $body, 'actions' => $actions,
    'badges' => sale_badge('order', $st) . ($o['allow_backorder'] ? ' <span class="badge text-bg-light border">back-order allowed</span>' : ''),
    'related' => ['lines' => 'Items', 'deliveries' => 'Deliveries & advance'],
    'facts' => ['created_by' => ['Created by', e($o['user_name'] ?? '—')], 'total' => ['Total', e(money($o['total']))]]];
require dirname(__DIR__) . '/_record.php';
