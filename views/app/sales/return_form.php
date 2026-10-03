<form method="post" action="<?= url('sales/returns') ?>"><?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>">
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-4"><div class="text-muted small">Customer</div><?= e($i['customer']) ?><br><small class="text-muted">Invoice <?= e($i['invoice_no']) ?> · stock goes back to <?= e($i['warehouse']) ?></small></div>
  <div class="col-md-3"><label class="form-label">Return date</label><input type="date" name="return_date" class="form-control" value="<?= e(old('return_date', date('Y-m-d'))) ?>" required></div>
  <div class="col-md-5"><label class="form-label">Reason</label><input name="reason" class="form-control" maxlength="150" value="<?= e(old('reason')) ?>" placeholder="e.g. wrong colour, damaged"></div>
</div><p class="text-muted small mt-3 mb-0">For a <strong>set</strong>, return the set line (for the credit) and each part you take back (to put it back on a rack). Untick "Put back" for goods that are damaged and must not return to stock.</p></div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Item</th><th>Roll (batch)</th><th class="text-end">Sold</th><th class="text-end">Can return</th><th>Put back on rack</th><th style="width:130px">Return qty</th></tr></thead><tbody>
  <?php foreach ($items as $l): $part = $l['parent_id'] !== null; $mine = old('lines', [])[$l['id']] ?? []; ?>
    <tr class="<?= $part ? 'small' : '' ?>"><td><?= $part ? '&nbsp;&nbsp;↳ ' : '' ?><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small><?= $l['is_bundle'] ? ' <span class="badge text-bg-info">Set</span>' : '' ?></td>
      <td><?= e($l['batch_no'] ?? '') ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(qty($l['returnable'])) ?></td>
      <td><?php if (!$l['is_bundle'] && $l['returnable'] > 0): ?>
        <select name="lines[<?= (int)$l['id'] ?>][location_id]" class="form-select form-select-sm d-inline-block" style="width:auto"><option value="0">No rack</option>
          <?php foreach ($racks[$i['warehouse_id']] ?? [] as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($mine['location_id'] ?? $l['location_id']) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['code']) ?></option><?php endforeach; ?></select>
        <label class="ms-2 small"><input type="checkbox" name="lines[<?= (int)$l['id'] ?>][restock]" value="1" <?= !$mine || !empty($mine['restock']) ? 'checked' : '' ?>> Put back</label><?php endif; ?></td>
      <td><?php if ($l['returnable'] > 0): ?><input type="number" step="0.001" min="0" max="<?= e(qty($l['returnable'])) ?>" name="lines[<?= (int)$l['id'] ?>][qty]" class="form-control form-control-sm" value="<?= e($mine['qty'] ?? '') ?>" placeholder="0"><?php else: ?><span class="text-muted">all returned</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<button class="btn btn-danger">Record return</button> <a class="btn btn-link" href="<?= url('sales/returns/create') ?>">Back</a>
</form>
