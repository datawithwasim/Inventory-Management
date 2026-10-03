<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option><option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open (not finished)</option>
    <?php foreach (App\Models\Purchase::PO_STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="supplier" class="form-select"><option value="">All <?= e(term('suppliers', true)) ?></option>
    <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input name="q" class="form-control" placeholder="PO no." value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('purchase.create')): ?><a class="btn btn-primary" href="<?= url('purchase/orders/create') ?>"><i class="bi bi-plus-lg"></i> New purchase order</a><?php endif; ?></div>
</form>
<div class="card mb-3"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>PO</th><th>Date</th><th><?= e(term('supplier')) ?></th><th>Deliver to</th><th>Expected</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("purchase/orders/{$r['id']}") ?>"><?= e($r['po_no']) ?></a></td><td><?= e(fdate($r['order_date'])) ?></td><td><?= e($r['supplier']) ?></td><td><?= e($r['warehouse']) ?></td>
      <td><?= e(fdate($r['expected_date'])) ?></td><td class="text-end"><?= e(money($r['total'])) ?></td><td><?= po_badge($r['status']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No purchase orders found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
<?php if (can('settings.edit')): ?>
<form class="mt-3 form-check form-switch" method="post" action="<?= url('purchase/orders/approval-setting') ?>"><?= csrf_field() ?>
  <input class="form-check-input" type="checkbox" name="po_approval" value="1" id="appr" <?= $approval ? 'checked' : '' ?> onchange="this.form.submit()">
  <label class="form-check-label" for="appr">Purchase orders need approval before goods can be received</label>
</form>
<?php endif; ?>
