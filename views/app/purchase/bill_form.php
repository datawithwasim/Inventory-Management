<div class="card" style="max-width:560px"><div class="card-body">
<form method="post" action="<?= url("purchase/bills/{$b['id']}") ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(term('supplier')) ?>'s bill number</label><input name="supplier_bill_no" class="form-control" maxlength="60" value="<?= e(old('supplier_bill_no', $b['supplier_bill_no'] ?? '')) ?>"></div>
  <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Bill date</label><input type="date" name="bill_date" class="form-control" value="<?= e(old('bill_date', $b['bill_date'])) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control" value="<?= e(old('due_date', $b['due_date'] ?? '')) ?>"></div></div>
  <div class="mb-3"><label class="form-label">Other charges on the bill (freight, packing…)</label><input type="number" step="0.01" min="0" name="other_charges" class="form-control" value="<?= e(old('other_charges', $b['other_charges'])) ?>">
    <div class="form-text">Goods <?= e(money($b['subtotal'])) ?> + tax <?= e(money($b['tax_total'])) ?> + these charges = bill total.</div></div>
  <div class="mb-3"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="255" value="<?= e(old('notes', $b['notes'] ?? '')) ?>"></div>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url("purchase/bills/{$b['id']}") ?>">Cancel</a>
</form></div></div>
