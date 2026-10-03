<?php
$v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d));
$col = fn(string $k, string $label, int $span, string $type = 'text', array $o = []) => (function () use ($k, $label, $span, $type, $o, $v) {
    if (!ff("supplier.$k")) return '';
    $attrs = ($o['attr'] ?? '') . ffreq("supplier.$k");
    return '<div class="col-md-' . $span . ' mb-3"' . ffa("supplier.$k") . '><label class="form-label">' . fl("supplier.$k", $label) . ffstar("supplier.$k") . '</label>'
        . '<input' . ($type === 'text' ? '' : ' type="' . $type . '"') . ' name="' . $k . '" class="form-control" value="' . $v($k, $o['default'] ?? '') . '"' . $attrs . '></div>';
})();
?>
<div class="card" style="max-width:980px"><div class="card-body">
<form method="post" action="<?= $row ? url("suppliers/{$row['id']}") : url('suppliers') ?>"><?= csrf_field() ?>
  <div class="ffgrid <?= ffclass('supplier') ?>">
  <div class="row">
    <div class="col-md-8 mb-3"<?= ffa('supplier.name') ?>><label class="form-label"><?= fl('supplier.name', e(term('supplier')) . ' name') ?></label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150"></div>
    <?php if (ff('supplier.supplier_type')): ?><div class="col-md-4 mb-3"<?= ffa('supplier.supplier_type') ?>><label class="form-label"><?= fl('supplier.supplier_type', 'Supplier type') ?><?= ffstar('supplier.supplier_type') ?></label>
      <select name="supplier_type" class="form-select"<?= ffreq('supplier.supplier_type') ?>><option value="">—</option>
        <?php foreach (App\Models\Purchase::SUPPLIER_TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= old('supplier_type', $row['supplier_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <?= $col('contact_person', 'Contact person', 4, 'text', ['attr' => ' maxlength="100"']) ?>
    <?= $col('phone', 'Phone', 4, 'text', ['attr' => ' maxlength="40"']) ?>
    <?= $col('email', 'Email', 4, 'email', ['attr' => ' maxlength="190"']) ?>
    <?= $col('tax_no', 'GSTIN / tax number', 6, 'text', ['attr' => ' maxlength="40"']) ?>
    <?= $col('pan', 'PAN', 6, 'text', ['attr' => ' maxlength="20"']) ?>
    <?php if (ff('supplier.address')): ?><div class="col-12 mb-3"<?= ffa('supplier.address') ?>><label class="form-label"><?= fl('supplier.address', 'Address') ?><?= ffstar('supplier.address') ?></label><input name="address" class="form-control" value="<?= $v('address') ?>" maxlength="255"<?= ffreq('supplier.address') ?>></div><?php endif; ?>
    <?= $col('city', 'City', 4, 'text', ['attr' => ' maxlength="80"']) ?>
    <?= $col('state', 'State', 4, 'text', ['attr' => ' maxlength="80"']) ?>
    <?= $col('pincode', 'Pincode', 4, 'text', ['attr' => ' maxlength="12"']) ?>
    <?php if (ff('supplier.ship_address')): ?><div class="col-12 mb-3"<?= ffa('supplier.ship_address') ?>><label class="form-label"><?= fl('supplier.ship_address', 'Godown / dispatch address') ?><?= ffstar('supplier.ship_address') ?></label><input name="ship_address" class="form-control" value="<?= $v('ship_address') ?>" maxlength="255"<?= ffreq('supplier.ship_address') ?>></div><?php endif; ?>
    <?= $col('payment_terms_days', 'Payment terms (days)', 3, 'number', ['attr' => ' min="0" max="365"', 'default' => 0]) ?>
    <?= $col('credit_limit', 'Credit limit', 3, 'number', ['attr' => ' min="0" step="0.01"', 'default' => 0]) ?>
    <?= $col('lead_time_days', 'Lead time (days)', 3, 'number', ['attr' => ' min="0" max="365"', 'default' => 0]) ?>
    <?= $col('transport', 'Preferred transport', 3, 'text', ['attr' => ' maxlength="100"']) ?>
    <?= $col('bank_name', 'Bank name', 4, 'text', ['attr' => ' maxlength="100"']) ?>
    <?= $col('bank_account', 'Account number', 4, 'text', ['attr' => ' maxlength="40"']) ?>
    <?= $col('bank_ifsc', 'IFSC', 4, 'text', ['attr' => ' maxlength="20"']) ?>
    <?php if (ff('supplier.notes')): ?><div class="col-12 mb-3"<?= ffa('supplier.notes') ?>><label class="form-label"><?= fl('supplier.notes', 'Notes') ?><?= ffstar('supplier.notes') ?></label><input name="notes" class="form-control" value="<?= $v('notes') ?>" maxlength="255"<?= ffreq('supplier.notes') ?>></div><?php endif; ?>
  </div>
    <?= ffextras('supplier', $cfFields, $cfValues) ?>
  </div>

  <div class="border rounded p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><strong>More contact people</strong>
      <button type="button" class="btn btn-sm btn-outline-primary" id="addContact"><i class="bi bi-plus-lg"></i> Add contact</button></div>
    <div class="small text-muted mb-2">Owner, accounts, dispatch… anyone you may need to call at this <?= e(term('supplier', true)) ?>.</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="contactTable"><thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Email</th><th></th></tr></thead><tbody>
      <?php foreach ($contacts as $i => $c): ?>
        <tr><td><input name="contacts[<?= $i ?>][name]" class="form-control form-control-sm" maxlength="100" value="<?= e($c['name'] ?? '') ?>"></td>
          <td><input name="contacts[<?= $i ?>][role]" class="form-control form-control-sm" maxlength="60" value="<?= e($c['role'] ?? '') ?>"></td>
          <td><input name="contacts[<?= $i ?>][phone]" class="form-control form-control-sm" maxlength="40" value="<?= e($c['phone'] ?? '') ?>"></td>
          <td><input name="contacts[<?= $i ?>][email]" type="email" class="form-control form-control-sm" maxlength="190" value="<?= e($c['email'] ?? '') ?>"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>

  <?php if ($row): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div><?php endif; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('suppliers') ?>">Cancel</a>
</form></div></div>
<script>
(function () {
  var tb = document.querySelector('#contactTable tbody'), n = tb.rows.length + 100;
  document.getElementById('addContact').onclick = function () {
    var i = n++, tr = document.createElement('tr');
    tr.innerHTML = ['name', 'role', 'phone', 'email'].map(function (k) { return '<td><input name="contacts[' + i + '][' + k + ']" class="form-control form-control-sm"' + (k === 'email' ? ' type="email"' : '') + '></td>'; }).join('') + '<td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td>';
    tb.appendChild(tr); tr.querySelector('input').focus();
  };
  tb.addEventListener('click', function (e) { var b = e.target.closest('.rm'); if (b) b.closest('tr').remove(); });
})();
</script>
