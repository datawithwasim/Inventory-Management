<?php
/**
 * Zoho-style record page: header, left "Related list", Overview / Timeline tabs, notes.
 * @var array $rec entity, row, name, back, badges (html), actions (html: buttons), menu (html: dropdown items), related [anchor => label], body (html), cfValues,
 *            facts [key => [label, html]] (extra details such as linked documents), warnTitle
 */
$GLOBALS['rec_shell'] = true;
$E = $rec['entity'];
$row = $rec['row'];
$notes = Core\RecordView::notes($E, (int)$row['id']);
$canNote = isset(Core\RecordView::META[$E]) && can(Core\RecordView::META[$E][0]);
$related = ['recOverview' => 'Overview'] + ($rec['related'] ?? []) + ['notes' => 'Notes (' . count($notes) . ')'];
$layoutUrl = url('settings/formdesign?form=' . $E . '&return=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
?>
<div class="rec">
  <div class="rec-top">
    <a class="rec-back" href="<?= url($rec['back']) ?>" title="Back to the list"><i class="bi bi-arrow-left"></i></a>
    <div class="rec-avatar"><?= e(mb_strtoupper(mb_substr(trim((string)$rec['name']), 0, 1))) ?></div>
    <div class="rec-title"><h1><?= e($rec['name']) ?></h1><div class="rec-badges"><?= $rec['badges'] ?? '' ?></div></div>
    <div class="rec-actions">
      <?= $rec['actions'] ?? '' ?>
      <?php $hasLayout = isset(Core\FormDesign::FIELDS[$E]) && can('settings.edit'); ?>
      <?php if (!empty($rec['menu']) || $hasLayout): ?>
        <div class="dropdown d-inline-block">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <?= $rec['menu'] ?? '' ?>
            <?php if ($hasLayout): ?><li><a class="dropdown-item" href="<?= e($layoutUrl) ?>"><i class="bi bi-layout-text-window me-2 text-muted"></i>Edit page layout</a></li><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php require __DIR__ . '/../layouts/flash.php'; ?>
  <div class="rec-layout">
    <aside class="rec-side"><div class="rec-side-h">Related list</div>
      <?php foreach ($related as $id => $label): ?><a href="#<?= e($id) ?>" data-rel="<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </aside>
    <div class="rec-main">
      <div class="rec-tabs" role="tablist"><button type="button" class="on" data-tab="recOverview">Overview</button><button type="button" data-tab="recTimeline">Timeline</button></div>
      <div id="recOverview" class="rec-panel">
        <?= Core\RecordView::summary($E, $row, $rec['cfValues'] ?? [], $rec['facts'] ?? []) ?>
        <?= Core\RecordView::sections($E, $row, $rec['cfValues'] ?? [], $rec['facts'] ?? []) ?>
        <?php if (!empty($rec['facts']) && !isset(Core\FormDesign::FIELDS[$E])): ?><section class="card rec-sec"><div class="card-header"><?= e($rec['factsTitle'] ?? 'Details') ?></div><div class="card-body"><div class="rec-grid c2">
          <?php foreach ($rec['facts'] as [$fl, $fv]): ?><div class="rec-kv"><span><?= e($fl) ?></span><b><?= $fv !== '' && $fv !== null ? $fv : '<i class="text-muted">—</i>' ?></b></div><?php endforeach; ?>
        </div></div></section><?php endif; ?>
        <?= $rec['body'] ?? '' ?>
        <section class="card mb-3" id="notes"><div class="card-header">Notes</div><div class="card-body">
          <?php if ($canNote): ?><form method="post" action="<?= url("records/$E/{$row['id']}/notes") ?>" class="mb-3"><?= csrf_field() ?>
            <textarea name="body" class="form-control" rows="2" maxlength="1500" placeholder="Add a note…" required></textarea>
            <button class="btn btn-sm btn-primary mt-2">Add note</button></form><?php endif; ?>
          <?php foreach ($notes as $n): ?>
            <div class="rec-note"><div><?= nl2br(e($n['body'])) ?></div>
              <div class="small text-muted d-flex justify-content-between"><span><?= e($n['user_name'] ?: 'Someone') ?> · <?= e(fdate(substr((string)$n['created_at'], 0, 10))) ?> <?= e(substr((string)$n['created_at'], 11, 5)) ?></span>
                <?php if ($canNote && ((int)$n['created_by'] === (int)(Core\Auth::user()['id'] ?? 0) || can('settings.edit'))): ?><form method="post" action="<?= url("records/notes/{$n['id']}/delete") ?>" onsubmit="return confirm('Remove this note?')"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0 text-danger">Delete</button></form><?php endif; ?></div></div>
          <?php endforeach; ?>
          <?php if (!$notes): ?><div class="text-muted small">No notes yet.</div><?php endif; ?>
        </div></section>
      </div>
      <div id="recTimeline" class="rec-panel" hidden><div class="card"><div class="card-body"><?= Core\RecordView::timeline($E, (int)$row['id'], $row['created_at'] ?? null) ?></div></div></div>
    </div>
  </div>
</div>
<?php $relCfg = isset(Core\FormDesign::FIELDS[$E]) && Core\FormDesign::customised($E) ? Core\FormDesign::resolve($E)['related'] : []; ?>
<?php if ($relCfg): ?><script>
/* the company's own order / visibility of the cards below the sections (Edit page layout) */
(function () {
  var cfg = <?= json_encode($relCfg, JSON_HEX_TAG | JSON_HEX_AMP) ?>, box = document.getElementById('recOverview');
  if (!box) return;
  var kids = Array.prototype.slice.call(box.children);
  function top(id) { var el = document.getElementById(id); while (el && el.parentNode !== box) el = el.parentNode; return el; }
  var groups = [];   // [{node, ids:[...], pos}] one per top-level block, in page order
  cfg.forEach(function (c, i) {
    var n = top(c.id); if (!n) return;
    var g = groups.filter(function (x) { return x.node === n; })[0];
    if (!g) groups.push(g = { node: n, ids: [], pos: i, show: false });
    g.ids.push(c.id); if (c.show) g.show = true;
  });
  var slots = groups.map(function (g) { return kids.indexOf(g.node); }).sort(function (a, b) { return a - b; });
  box.style.display = 'flex'; box.style.flexDirection = 'column';
  kids.forEach(function (k, i) { k.style.order = i; });
  groups.slice().sort(function (a, b) { return a.pos - b.pos; }).forEach(function (g, i) { g.node.style.order = slots[i]; if (!g.show) g.node.style.display = 'none'; });
  var side = document.querySelector('.rec-side'), hid = {};
  cfg.forEach(function (c) { hid[c.id] = !c.show; });
  if (side) {
    var links = {}; side.querySelectorAll('a[data-rel]').forEach(function (a) { links[a.dataset.rel] = a; });
    cfg.forEach(function (c) { var a = links[c.id]; if (!a) return; side.appendChild(a); if (!c.show) a.style.display = 'none'; });
  }
})();
</script><?php endif; ?>
<script>
(function () {
  var tabs = document.querySelectorAll('.rec-main > .rec-tabs button'), panels = document.querySelectorAll('.rec-main > .rec-panel');
  function show(id) { tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === id); }); panels.forEach(function (p) { p.hidden = p.id !== id; }); }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
  document.querySelectorAll('.rec-side a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault(); show('recOverview');
      var el = document.getElementById(a.dataset.rel); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.querySelectorAll('.rec-side a').forEach(function (x) { x.classList.toggle('on', x === a); });
    });
  });
  if (location.hash && document.getElementById(location.hash.slice(1))) setTimeout(function () { document.getElementById(location.hash.slice(1)).scrollIntoView(); }, 50);
})();
</script>
