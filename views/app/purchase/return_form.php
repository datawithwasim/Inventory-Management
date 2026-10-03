<form method="post" action="<?= url('purchase/returns') ?>"><?= csrf_field() ?><input type="hidden" name="grn_id" value="<?= (int)$g['id'] ?>">
<div class="card mb-3"><div class="card-body"><div class="row g-3 <?= ffclass('purchase_return') ?>"><?= ffextras('purchase_return', [], []) ?>
  <div class="col-md-4"><div class="text-muted small"><?= e(term('supplier')) ?></div><?= e($g['supplier']) ?><br><small class="text-muted">Receipt <?= e($g['grn_no']) ?> · <?= e($g['warehouse']) ?></small></div>
  <div class="col-md-3"<?= ffa('purchase_return.return_date') ?>><label class="form-label"><?= fl('purchase_return.return_date', 'Return date') ?></label><input type="date" name="return_date" class="form-control" value="<?= e(old('return_date', date('Y-m-d'))) ?>" required></div>
<?php if (ff('purchase_return.reason')): ?>  <div class="col-md-5"<?= ffa('purchase_return.reason') ?>><label class="form-label"><?= fl('purchase_return.reason', 'Reason') ?><?= ffstar('purchase_return.reason') ?></label><input name="reason"<?= ffreq('purchase_return.reason') ?> class="form-control" maxlength="150" value="<?= e(old('reason')) ?>" placeholder="e.g. damaged, wrong shade"></div><?php else: ?><?= ffh('purchase_return.reason', e(old('reason'))) ?><?php endif; ?>
</div></div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?> (roll)</th><th class="text-end">Received</th><th class="text-end">Can return</th><th>Take from <?= e(term('rack', true)) ?></th><th style="width:150px">Return qty</th></tr></thead><tbody>
  <?php foreach ($items as $l): $top = $l['placements'][0]['location_id'] ?? 0; $mine = old('lines', [])[$l['id']] ?? []; ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td><?= e($l['batch_no'] ?? '—') ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td>
      <td class="text-end"><?= e(qty($l['returnable'])) ?><div class="small text-muted">in stock: <?= e(qty($l['on_hand'])) ?></div></td>
      <td><?php if ($l['returnable'] > 0): ?><select name="lines[<?= (int)$l['id'] ?>][location_id]" class="form-select form-select-sm">
          <option value="0">No <?= e(term('rack', true)) ?></option>
          <?php foreach ($racks[$g['warehouse_id']] ?? [] as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (int)($mine['location_id'] ?? $top) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['code']) ?></option><?php endforeach; ?></select>
        <?php if ($l['placements']): ?><div class="form-text small">In stock: <?= e(implode(' · ', array_map(fn($p) => ($p['code'] ?: 'No rack') . ' ' . qty($p['qty']), $l['placements']))) ?></div><?php endif; ?><?php endif; ?></td>
      <td><?php if ($l['returnable'] > 0): ?><input type="number" step="0.001" min="0" max="<?= e(qty($l['returnable'])) ?>" name="lines[<?= (int)$l['id'] ?>][qty]" class="form-control form-control-sm" value="<?= e($mine['qty'] ?? '') ?>" placeholder="0"><?php else: ?><span class="text-muted">all returned</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<button class="btn btn-danger">Return to <?= e(term('supplier', true)) ?></button> <a class="btn btn-link" href="<?= url('purchase/returns/create') ?>">Back</a>
</form>
