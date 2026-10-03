<?php [$w, $h] = array_map('intval', explode('x', $size)); $sheet = $size === '65x33'; ?>
<style>
  .sheet { max-width: none !important; padding: 0 !important; }
  .labels { display: flex; flex-wrap: wrap; gap: 0; <?= $sheet ? 'width:210mm;padding:12mm 7mm;' : '' ?> }
  .lbl { width: <?= $w ?>mm; height: <?= $h ?>mm; box-sizing: border-box; padding: 1.5mm 2mm; overflow: hidden; page-break-inside: avoid; break-inside: avoid;
         font-family: Arial, sans-serif; display: flex; flex-direction: column; justify-content: space-between; border: <?= $sheet ? '0' : '0' ?>; }
  .lbl .t { font-size: <?= $w < 45 ? '7' : '8' ?>pt; font-weight: 700; line-height: 1.1; max-height: 2.3em; overflow: hidden; }
  .lbl .m { font-size: <?= $w < 45 ? '7' : '8' ?>pt; line-height: 1.1; display: flex; justify-content: space-between; gap: 4px; }
  .lbl .bc { width: 100%; height: <?= $h > 28 ? 12 : 8 ?>mm; display: block; }
  .lbl .c { font-size: 6.5pt; text-align: center; letter-spacing: .08em; }
  @media print { @page { size: <?= $sheet ? 'A4' : $w . 'mm ' . $h . 'mm' ?>; margin: 0; } body { margin: 0; } }
  @media screen { .lbl { outline: 1px dashed #bbb; margin: 2px; } }
</style>
<div class="no-print p-3"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
  <span class="text-muted small ms-2"><?= count($labels) ?> label<?= count($labels) === 1 ? '' : 's' ?>. In the print window set margins to “None” and scale to 100%.</span></div>
<div class="labels">
  <?php foreach ($labels as $l): ?>
    <div class="lbl"><div class="t"><?= e($l['title']) ?></div>
      <div class="m"><strong><?= e($l['line']) ?></strong><span><?= e($l['small']) ?></span></div>
      <div><?= $l['svg'] ?><div class="c"><?= e($l['code']) ?></div></div></div>
  <?php endforeach; ?>
  <?php if (!$labels): ?><p class="p-3 text-muted">No labels selected. Go back and enter copies.</p><?php endif; ?>
</div>
