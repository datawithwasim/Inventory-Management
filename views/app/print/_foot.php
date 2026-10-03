<?php /** Terms, footer line and signature. Needs $tpl; optional $notes. */ ?>
<?php if (!empty($notes)): ?><div class="small mt-3"><strong>Note:</strong> <?= e($notes) ?></div><?php endif; ?>
<?php if (!empty($showBank) && Core\Settings::get('company.bank') !== ''): ?><div class="small mt-3"><strong>Bank details</strong><br><?= nl2br(e(Core\Settings::get('company.bank'))) ?></div><?php endif; ?>
<?php if (!empty($tpl['terms'])): ?><div class="small text-muted mt-3"><strong>Terms &amp; conditions</strong><br><?= nl2br(e($tpl['terms'])) ?></div><?php endif; ?>
<?php if (!empty($tpl['signature'])): ?><div class="row mt-5 pt-4"><div class="col-6 offset-6 text-center"><div class="border-top pt-1 small"><?= e($tpl['signature_label'] ?: 'Authorised signatory') ?></div></div></div><?php endif; ?>
<?php if (!empty($tpl['footer'])): ?><div class="text-center small text-muted mt-4"><?= e($tpl['footer']) ?></div><?php endif; ?>
