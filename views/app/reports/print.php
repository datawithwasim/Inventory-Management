<style>
  @page { size: A4 landscape; margin: 12mm; }
  .sheet { max-width: none !important; }
  .report-table { font-size: 11px; }
  .report-table th, .report-table td { padding: 3px 6px !important; }
</style>
<div class="no-print mb-3"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
  <span class="text-muted small ms-2">Choose “Save as PDF” as the printer. Landscape A4 is pre-selected.</span></div>
<h1 class="h5 mb-0"><?= e($def['title']) ?></h1>
<div class="text-muted small mb-2"><?= e($company) ?> · printed <?= e(fdate(date('Y-m-d'))) ?>
  <?php if (isset($f['from'])): ?> · <?= e(fdate($f['from'])) ?> – <?= e(fdate($f['to'])) ?><?php endif; ?></div>
<?php require __DIR__ . '/_table.php'; ?>
<?php if ($truncated): ?><p class="small text-muted">First <?= (int)$screenLimit ?> rows only — use Excel/CSV for the full list.</p><?php endif; ?>
