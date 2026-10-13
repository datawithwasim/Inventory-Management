<?php
/** @var array $tpl @var array $page @var array $labels @var int $skip */
use App\Models\Labels;

$w = (float)$tpl['w']; $h = (float)$tpl['h'];
$cols = (int)$page['cols']; $rows = (int)$page['rows'];
$gx = (float)$page['gap_x']; $gy = (float)$page['gap_y'];
$sheet = $page['mode'] === 'sheet';
$mm = fn(float $v) => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') . 'mm';
if ($sheet) {
    [$pw, $ph] = [(float)$page['paper_w'], (float)$page['paper_h']];
    $ml = $page['margin_left'] ?? max(0.0, ($pw - ($cols * $w + ($cols - 1) * $gx)) / 2);
    $mt = $page['margin_top'] ?? max(0.0, ($ph - ($rows * $h + ($rows - 1) * $gy)) / 2);
    $per = $cols * $rows;
} else {
    $pw = $cols * $w + ($cols - 1) * $gx; $ph = $h; $ml = 0.0; $mt = 0.0; $per = $cols;
}
// leading blank positions on a part-used sheet, then the labels, cut into pages
$cells = array_merge(array_fill(0, $skip, null), $labels);
$pages = $cells ? array_chunk($cells, $per) : [];
?>
<style>
  .sheet { max-width: none !important; padding: 0 !important; }
  .lpage { position: relative; width: <?= $mm($pw) ?>; height: <?= $mm($ph) ?>; overflow: hidden; background: #fff; break-after: page; page-break-after: always; }
  .lpage:last-child { break-after: auto; page-break-after: auto; }
  .lbl { position: absolute; width: <?= $mm($w) ?>; height: <?= $mm($h) ?>; overflow: hidden; box-sizing: border-box; }
  .lbl.out { outline: 0.2mm solid #888; outline-offset: -0.1mm; }
  .el { position: absolute; box-sizing: border-box; overflow: hidden; display: flex; flex-direction: column; transform-origin: 50% 50%; }
  .el.t span { display: block; word-break: break-word; }
  .bcs { flex: 1 1 auto; min-height: 0; } .bcs svg { width: 100%; height: 100%; display: block; }
  .bct { flex: 0 0 auto; text-align: center; line-height: 1.1; white-space: nowrap; overflow: hidden; }
  @page { size: <?= $mm($pw) ?> <?= $mm($ph) ?>; margin: 0; }
  @media print { html, body { margin: 0; padding: 0; background: #fff; } .lbl.out { outline-color: #aaa; } }
  @media screen { body { background: #eee !important; } .lpage { margin: 12px auto; box-shadow: 0 1px 6px rgba(0,0,0,.25); } .lbl { outline: 1px dashed #ddd; } .lbl.out { outline: 0.2mm solid #888; } }
</style>
<div class="no-print p-3 bg-white border-bottom position-sticky top-0" style="z-index:5">
  <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
  <span class="text-muted small ms-2"><?= count($labels) ?> label<?= count($labels) === 1 ? '' : 's' ?> · <?= count($pages) ?> page<?= count($pages) === 1 ? '' : 's' ?> · template “<?= e($tpl['name']) ?>” (<?= e(rtrim(rtrim(number_format($w, 2), '0'), '.')) ?> × <?= e(rtrim(rtrim(number_format($h, 2), '0'), '.')) ?> mm).
    In the print window set margins to “None” and scale to 100% (not “fit to page”).</span>
</div>
<?php foreach ($pages as $cells): ?>
  <div class="lpage">
    <?php foreach ($cells as $i => $values): if ($values === null) continue;
        $c = $i % $cols; $r = intdiv($i, $cols);
        $x = $ml + $c * ($w + $gx); $y = $mt + $r * ($h + $gy); ?>
      <div class="lbl<?= !empty($page['outline']) ? ' out' : '' ?>" style="left:<?= $mm($x) ?>;top:<?= $mm($y) ?>"><?= Labels::labelHtml($tpl, $values) ?></div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php if (!$pages): ?><p class="p-3 text-muted">No labels selected. Go back and enter how many copies of each you want.</p><?php endif; ?>
<script>
// text marked "shrink to fit" gets smaller until it fits its box (the designer does the same)
(function () {
  function fit() {
    document.querySelectorAll('.el.t.fit').forEach(function (el) {
      var span = el.firstElementChild, size = parseFloat(el.style.fontSize) || 8;
      span.style.fontSize = ''; el.style.fontSize = size + 'pt';
      var min = 4, guard = 60;
      while (guard-- > 0 && size > min && (span.scrollHeight > el.clientHeight + 0.5 || el.scrollWidth > el.clientWidth + 0.5)) { size -= 0.25; el.style.fontSize = size + 'pt'; }
    });
  }
  window.addEventListener('load', fit); window.addEventListener('beforeprint', fit);
})();
</script>
