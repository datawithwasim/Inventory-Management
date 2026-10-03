<div class="card" style="max-width:520px"><div class="card-body">
<form method="post" action="<?= url("sales/invoices/{$i['id']}") ?>"><?= csrf_field() ?>
  <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Invoice date</label><input type="date" name="invoice_date" class="form-control" value="<?= e(old('invoice_date', $i['invoice_date'])) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control" value="<?= e(old('due_date', $i['due_date'] ?? '')) ?>"></div></div>
  <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Delivery charge</label><input type="number" step="0.01" min="0" name="delivery_charge" class="form-control" value="<?= e(old('delivery_charge', $i['delivery_charge'])) ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Installation charge</label><input type="number" step="0.01" min="0" name="installation_charge" class="form-control" value="<?= e(old('installation_charge', $i['installation_charge'])) ?>"></div></div>
  <div class="mb-3"><label class="form-label">Notes (printed on the invoice)</label><input name="notes" class="form-control" maxlength="255" value="<?= e(old('notes', $i['notes'] ?? '')) ?>"></div>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url("sales/invoices/{$i['id']}") ?>">Cancel</a>
</form></div></div>
