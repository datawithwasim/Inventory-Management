<form class="row g-2 mb-3" method="get">
  <div class="col-md-2"><select name="status" class="form-select"><option value="">All invoices</option><?php foreach (['unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="customer" class="form-select"><option value="">All customers</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $customer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Invoice no." value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="card mb-2"><div class="card-body py-2">Balance due on these invoices: <strong class="<?= $totalDue > 0.004 ? 'text-danger' : '' ?>"><?= e(money($totalDue)) ?></strong></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Due date</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $due = App\Models\Purchase::outstanding($r); $st = App\Models\Purchase::payStatus($r); $late = $st !== 'paid' && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
    <tr><td><a href="<?= url("sales/invoices/{$r['id']}") ?>"><?= e($r['invoice_no']) ?></a></td><td><?= e($r['invoice_date']) ?></td><td><?= e($r['customer']) ?></td><td class="<?= $late ? 'text-danger' : '' ?>"><?= e($r['due_date'] ?? '') ?><?= $late ? ' (overdue)' : '' ?></td>
      <td class="text-end"><?= e(money($r['total'])) ?></td><td class="text-end"><?= e(money($due)) ?></td><td><?= pay_badge($st) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No invoices found.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
