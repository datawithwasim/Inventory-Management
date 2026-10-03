<?php
/** Company letterhead + document title. Needs: $tpl (template), $docNo, $docDate, optional $meta [label => value]. */
use Core\Settings;
$S = fn($k) => Settings::get('company.' . $k);
$companyName = $S('legal_name') !== '' ? $S('legal_name') : Core\Auth::user()['tenant_name'];
$logo = !empty($tpl['show_logo']) ? company_logo_url() : null;
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($tpl['accent'] ?? '')) ? $tpl['accent'] : '#1f2937';
?>
<style>.doc-accent { color: <?= e($accent) ?>; } .doc-rule { border-bottom: 3px solid <?= e($accent) ?>; } .doc-table thead th { border-bottom: 2px solid <?= e($accent) ?>; }</style>
<div class="d-flex justify-content-between align-items-start pb-2 mb-3 doc-rule">
  <div class="d-flex align-items-center gap-3">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" style="max-height:<?= !empty($receipt) ? 40 : 70 ?>px;max-width:180px"><?php endif; ?>
    <div><div class="h5 mb-0"><?= e($companyName) ?></div>
      <?php if (!empty($tpl['show_company'])): ?>
        <?php if ($S('address') !== ''): ?><div class="small"><?= nl2br(e($S('address'))) ?></div><?php endif; ?>
        <div class="small text-muted"><?= e(implode(' · ', array_filter([$S('phone'), $S('email'), $S('website')]))) ?></div>
        <?php if ($S('tax_no') !== ''): ?><div class="small">Tax no. <?= e($S('tax_no')) ?></div><?php endif; ?>
      <?php endif; ?></div>
  </div>
  <div class="text-end"><div class="h4 mb-0 doc-accent"><?= e($tpl['title'] !== '' ? $tpl['title'] : $docTitle) ?></div><div><strong><?= e($docNo) ?></strong></div><div class="small text-muted"><?= e(fdate($docDate)) ?></div>
    <?php foreach (($meta ?? []) as $label => $value): if ($value === null || $value === '') continue; ?><div class="small text-muted"><?= e($label) ?>: <?= e($value) ?></div><?php endforeach; ?></div>
</div>
