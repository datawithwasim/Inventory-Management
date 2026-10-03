<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="supplier" class="form-select"><option value="">All suppliers</option>
    <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="GRN no. or challan no." value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('purchase.create')): ?><a class="btn btn-primary" href="<?= url('purchase/grns/create') ?>"><i class="bi bi-plus-lg"></i> Receive goods (no PO)</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>GRN</th><th>Date</th><th>Supplier</th><th>Warehouse</th><th>PO</th><th>Lines</th><th>Bill</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("purchase/grns/{$r['id']}") ?>"><?= e($r['grn_no']) ?></a></td><td><?= e($r['received_date']) ?></td><td><?= e($r['supplier']) ?></td><td><?= e($r['warehouse']) ?></td>
      <td><?= $r['po_id'] ? '<a href="' . url('purchase/orders/' . (int)$r['po_id']) . '">' . e($r['po_no']) . '</a>' : '—' ?></td><td><?= (int)$r['line_count'] ?></td>
      <td><?= $r['bill_id'] ? '<a href="' . url('purchase/bills/' . (int)$r['bill_id']) . '">View bill</a>' : '<span class="text-muted">not billed</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No goods receipts yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
