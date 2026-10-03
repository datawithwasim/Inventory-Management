<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Delivery</div><strong><?= e($d['delivery_no']) ?></strong> <?= $d['source'] === 'pos' ? '<span class="badge text-bg-light border">POS</span>' : '' ?></div>
  <div class="col-md-3"><div class="text-muted small"><?= e(term('customer')) ?></div><a href="<?= url('customers/' . (int)$d['customer_id']) ?>"><?= e($d['customer']) ?></a></div>
  <div class="col-md-3"><div class="text-muted small">Taken from</div><?= e($d['warehouse']) ?><br><small class="text-muted"><?= e(fdate($d['delivery_date'])) ?> by <?= e($d['user_name'] ?? '—') ?></small></div>
  <div class="col-md-3"><div class="text-muted small">Order</div><?= $d['order_id'] ? '<a href="' . url('sales/orders/' . (int)$d['order_id']) . '">' . e($d['order_no']) . '</a>' : '—' ?></div></div>
  <?php if ($d['ship_to']): ?><div class="mt-2">Deliver to: <?= e($d['ship_to']) ?></div><?php endif; ?><?php if ($d['note']): ?><div class="text-muted"><?= e($d['note']) ?></div><?php endif; ?>
  <div class="mt-3 d-flex gap-2">
    <a class="btn btn-outline-secondary" target="_blank" href="<?= url("sales/deliveries/{$d['id']}/print") ?>"><i class="bi bi-printer"></i> Delivery note</a>
    <?php if ($d['invoice_id']): ?><a class="btn btn-outline-primary" href="<?= url('sales/invoices/' . (int)$d['invoice_id']) ?>">Invoice <?= e($d['invoice_no']) ?></a>
    <?php elseif (can('sales.create')): ?><form method="post" action="<?= url("sales/deliveries/{$d['id']}/invoice") ?>"><?= csrf_field() ?><button class="btn btn-primary">Create invoice</button></form><?php endif; ?>
  </div></div></div>
<div class="card"><div class="table-responsive"><?php require __DIR__ . '/_delivery_rows.php'; ?></div></div>
