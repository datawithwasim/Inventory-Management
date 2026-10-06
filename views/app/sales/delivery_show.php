
<?php ob_start(); ?>
    <a class="btn btn-outline-secondary" target="_blank" href="<?= url("sales/deliveries/{$d['id']}/print") ?>"><i class="bi bi-printer"></i> Delivery note</a>
    <?php if ($d['invoice_id']): ?><a class="btn btn-outline-primary" href="<?= url('sales/invoices/' . (int)$d['invoice_id']) ?>">Invoice <?= e($d['invoice_no']) ?></a>
    <?php elseif (can('sales.create')): ?><form method="post" action="<?= url("sales/deliveries/{$d['id']}/invoice") ?>"><?= csrf_field() ?><button class="btn btn-primary">Create invoice</button></form><?php endif; ?>
  <?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="lines"><div class="table-responsive"><?php require __DIR__ . '/_delivery_rows.php'; ?></div></div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'delivery', 'row' => $d, 'name' => $d['delivery_no'], 'back' => 'sales/deliveries', 'body' => $body, 'actions' => $actions,
    'badges' => $d['source'] === 'pos' ? '<span class="badge text-bg-light border">POS</span>' : '', 'related' => ['lines' => 'Items delivered'],
    'facts' => [[term('customer'), '<a href="' . url('customers/' . (int)$d['customer_id']) . '">' . e($d['customer']) . '</a>'],
        ['Order', $d['order_id'] ? '<a href="' . url('sales/orders/' . (int)$d['order_id']) . '">' . e($d['order_no']) . '</a>' : ''],
        ['Invoice', $d['invoice_id'] ? '<a href="' . url('sales/invoices/' . (int)$d['invoice_id']) . '">' . e($d['invoice_no']) . '</a>' : ''], ['By', e($d['user_name'] ?? '—')]]];
require dirname(__DIR__) . '/_record.php';
