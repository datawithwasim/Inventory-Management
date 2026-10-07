(function () {
  var raw = JSON.parse(document.getElementById('dzData').textContent);
  var CHOICE = { dropdown: 1, radio: 1, multiselect: 1 }, UNIQ = { text: 1, email: 1, phone: 1, url: 1, number: 1, decimal: 1, dropdown: 1, date: 1 };
  var data = raw.model, TYPES = raw.types, ICONS = raw.icons;
  var $ = function (id) { return document.getElementById(id); };
  var cur = 0, sel = null, drag = null, preview = false, history = [], newSeq = 0, typing = null;
  data.forEach(function (f) { f.deleted = f.deleted || []; f.resetFlag = false; });
  var initial = JSON.stringify(data);

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function F() { return data[cur]; }
  function fld(key) { return F().fields[key]; }
  function labelOf(x) { return x.custom ? x.label : (x.custom_label || x.label); }
  function spanOf(x, cols) { if (x.w) return Math.max(1, Math.round(parseInt(x.w, 10) / 100 * 12)); if (cols === 0) return x.span || 12; return cols === 3 ? 4 : 12 / cols; }
  function snap() { return JSON.stringify(data); }
  var typedTag = null;
  function touchTyped(tag) {   // typing: one undo step per field, always recorded straight away
    var now = snap();
    if (typedTag === tag && history.length > 1) history[history.length - 1] = now; else if (history[history.length - 1] !== now) history.push(now);
    typedTag = tag; F().resetFlag = false;
    $('dzUndo').disabled = history.length < 2; $('dzDirty').classList.toggle('d-none', now === initial);
  }
  function touch(keepReset) {
    typedTag = null;
    if (!keepReset) F().resetFlag = false;
    var now = snap(); if (history[history.length - 1] !== now) history.push(now);
    if (history.length > 80) history.shift();
    $('dzUndo').disabled = history.length < 2;
    $('dzDirty').classList.toggle('d-none', now === initial);
  }
  function undo() {
    if (history.length < 2) return; history.pop(); data = JSON.parse(history[history.length - 1]);
    $('dzUndo').disabled = history.length < 2; $('dzDirty').classList.toggle('d-none', snap() === initial);
    if (sel && !F().fields[sel]) sel = null; render();
  }

  /* ---------------- rendering ---------------- */
  var formSel = $('dzForm'), groups = {};
  data.forEach(function (f, i) {
    var g = groups[f.group]; if (!g) { g = groups[f.group] = document.createElement('optgroup'); g.label = f.group; formSel.appendChild(g); }
    var o = document.createElement('option'); o.value = i; o.textContent = f.title; g.appendChild(o);
  });
  var start = document.getElementById('dz').dataset.start;
  data.forEach(function (f, i) { if (f.key === start) cur = i; });

  function render() {
    var f = F();
    formSel.value = cur; $('dzCurrent').value = f.key;
    document.querySelectorAll('#dzStyle button').forEach(function (b) { b.classList.toggle('on', b.dataset.s === f.style); });
    $('dzPaperTitle').textContent = (f.title.replace(/ form$/, '')) + ' — ' + (preview ? 'preview' : 'layout');
    $('dzPaper').className = 'dz-paper' + (preview ? ' preview' : '') + (f.style === 'left' ? ' left' : '');
    $('dzPreview').classList.toggle('on', preview);
    $('dzNoCustom').classList.toggle('d-none', f.customFields);
    $('dzTypes').classList.toggle('off', !f.customFields);
    renderSummary(); renderSections(); renderUnused(); renderLines(); renderRight();
  }

  /* ---------------- summary strip (top of the detail page) ---------------- */
  function renderSummary() {
    var f = F(), box = $('dzSum'); box.hidden = !f.detail; if (!f.detail) return;
    var sum = f.summary || (f.summary = []);
    var h = '<div class="dz-sum-head"><b>Summary strip</b><span>Key fields shown at the top of the detail page · up to 4 · not on the edit form</span>' +
      (preview || sum.length >= 4 ? '' : '<button type="button" data-a="sum-add"><i class="bi bi-plus-lg"></i> Add field</button>') + '</div><div class="dz-sum-row">';
    sum.forEach(function (k) {
      var x = fld(k); if (!x) return;
      h += '<div class="dz-sum-chip"><span>' + esc(labelOf(x)) + '</span><b>—</b>' + (preview ? '' : '<button type="button" data-a="sum-del" data-key="' + esc(k) + '" title="Remove from the strip"><i class="bi bi-x"></i></button>') + '</div>';
    });
    if (!sum.length) h += '<em class="text-muted small">Empty — the page opens straight on the sections. Drag a field here or use Add field.</em>';
    box.innerHTML = h + '</div>';
  }
  function sumToggle(key) {
    var f = F(), sum = f.summary || (f.summary = []), i = sum.indexOf(key);
    if (i > -1) sum.splice(i, 1); else if (sum.length < 4) sum.push(key); else { alert('The summary strip holds up to four fields.'); return; }
    touch(); render();
  }

  function tileHtml(x) {
    var req = x.req ? ' req' : '';
    var pinned = F().detail && (F().summary || []).indexOf(x.key) > -1;
    return '<div class="dz-tile' + (x.key === sel ? ' sel' : '') + req + (x.sys ? ' sys' : '') + (F().layout ? '' : ' fixed') + '" draggable="' + (F().layout && !preview) + '" data-key="' + esc(x.key) + '" tabindex="0">' +
      '<i class="bi bi-grip-vertical dz-grip"></i><span class="dz-ico"><i class="bi bi-' + esc(x.kind) + '"></i></span>' +
      '<span class="dz-name">' + esc(labelOf(x)) + (x.req ? '<b class="dz-star"> *</b>' : '') + (x.custom ? '<em class="dz-cf">custom</em>' : '') + (x.sys ? '<em class="dz-cf dz-sysb">detail page only</em>' : '') + (pinned ? '<i class="bi bi-pin-angle-fill dz-pin" title="Shown in the summary strip"></i>' : '') + '</span>' +
      '<button type="button" class="dz-more" data-a="menu" title="More"><i class="bi bi-three-dots"></i></button>' +
      '<span class="dz-faux">' + (x.help ? '<small>' + esc(x.help) + '</small>' : '') + '</span></div>';
  }

  function renderSections() {
    var f = F(), box = $('dzSections'), h = '';
    f.sections.forEach(function (s, si) {
      h += '<section class="dz-sec" data-i="' + si + '"><div class="dz-sec-head">' +
        (preview ? '<div class="dz-sec-name">' + esc(s.title) + '</div>' : '<input class="dz-sec-title" data-a="title" value="' + esc(s.title) + '" placeholder="' + esc(si === 0 ? f.title.replace(/ form$/, '') + ' information' : 'More details') + '" maxlength="60">') +
        '<div class="dz-sec-tools"><button type="button" data-a="cols" title="Columns in this section"><i class="bi bi-layout-three-columns"></i> ' + (s.cols ? s.cols + ' col' : 'Original') + '</button>' +
        '<button type="button" data-a="sec-up" title="Move section up"><i class="bi bi-arrow-up"></i></button><button type="button" data-a="sec-down" title="Move section down"><i class="bi bi-arrow-down"></i></button>' +
        '<button type="button" data-a="sec-del" title="Delete section"><i class="bi bi-trash"></i></button></div></div>' +
        '<div class="dz-grid" data-i="' + si + '">';
      s.fields.forEach(function (k) { var x = fld(k); if (!x) return; h += tileHtml(x).replace('<div class="dz-tile', '<div style="--span:' + spanOf(x, s.cols) + '" class="dz-tile'); });
      h += (s.fields.length ? '' : '<div class="dz-drop-empty">Drop fields here</div>') + '</div></section>';
    });
    box.innerHTML = h;
  }

  function renderUnused() {
    var f = F(), un = Object.keys(f.fields).filter(function (k) { return !f.fields[k].show; });
    $('dzUnusedCount').textContent = un.length;
    $('dzUnused').innerHTML = un.length ? un.map(function (k) { var x = f.fields[k]; return '<div class="dz-un" draggable="true" data-key="' + esc(k) + '"><i class="bi bi-' + esc(x.kind) + '"></i><span>' + esc(labelOf(x)) + '</span><button type="button" data-a="restore" title="Put back on the form"><i class="bi bi-plus-lg"></i></button></div>'; }).join('')
      : '<div class="dz-un-empty">Nothing here. Fields you remove from the form wait here.</div>';
  }

  function renderLines() {
    var f = F(), b = $('dzLines'); b.hidden = !f.lineForm;
    if (!f.lineForm) return;
    var cols = ['Item', 'Qty', 'Price', 'Disc %', 'Tax %', 'Total'];
    b.innerHTML = '<div class="dz-sec-head"><div class="dz-sec-name">Line items</div><div class="dz-sec-tools"><span class="dz-hint">Choose the columns of the items table</span></div></div>' +
      '<div class="dz-table">' + cols.map(function (c) {
        var k = c === 'Disc %' ? 'disc' : c === 'Tax %' ? 'tax' : null, on = !k || f.lines[k];
        return '<div class="dz-col' + (on ? '' : ' off') + (k ? ' tog' : '') + '"' + (k ? ' data-line="' + k + '"' : '') + '>' + c + (k ? '<i class="bi bi-' + (on ? 'eye' : 'eye-slash') + '"></i>' : '') + '</div>';
      }).join('') + '</div>';
  }

  function renderRight() {
    var x = sel && fld(sel), r = $('dzRight');
    r.hidden = !x; if (!x) return;
    $('pIcon').className = 'bi bi-' + x.kind; $('pName').textContent = labelOf(x);
    $('pKind').textContent = x.sys ? 'Record detail · detail page only' : x.custom ? 'Custom field · ' + (TYPES[x.type] || x.type) : (x.optional ? 'Standard field (optional)' : 'Standard field (essential)');
    var lab = $('pLabel'); if (document.activeElement !== lab) lab.value = x.custom ? x.label : x.custom_label; lab.placeholder = x.custom ? 'Field name' : x.label;
    var hp = $('pHelp'); if (document.activeElement !== hp) hp.value = x.help;
    $('pTypeBox').hidden = !x.custom; $('pType').value = x.type || 'text'; $('pType').disabled = !x.isNew;
    $('pTypeNote').textContent = x.isNew ? '' : 'The type of a saved field cannot be changed. Delete it and add a new one if needed.';
    var choice = x.custom && CHOICE[x.type]; $('pOptBox').hidden = !choice;
    var op = $('pOptions'); if (document.activeElement !== op) op.value = x.options || '';
    document.querySelectorAll('#pWidth button').forEach(function (b) { b.classList.toggle('on', b.dataset.w === (x.w || '')); });
    $('pReqRow').hidden = !x.optional || x.type === 'checkbox'; $('pReq').checked = !!x.req;
    $('pUniqRow').hidden = !(x.custom && UNIQ[x.type]); $('pUniq').checked = !!x.unique;
    $('pListRow').hidden = !x.custom; $('pList').checked = !!x.inList;
    ['pHelp', 'pWidth'].forEach(function (id) { $(id).hidden = !!x.sys; if ($(id).previousElementSibling) $(id).previousElementSibling.hidden = !!x.sys; });
    $('pCore').hidden = x.optional; $('pHide').hidden = !x.optional; $('pDelete').hidden = !x.custom;
  }

  /* ---------------- model operations ---------------- */
  function locate(key) { var f = F(); for (var i = 0; i < f.sections.length; i++) { var j = f.sections[i].fields.indexOf(key); if (j > -1) return [i, j]; } return null; }
  function detach(key) { var p = locate(key); if (p) F().sections[p[0]].fields.splice(p[1], 1); }
  function insertAt(si, ref, after, key) {
    var s = F().sections[si], j = ref ? s.fields.indexOf(ref) : s.fields.length;
    if (j < 0) j = s.fields.length; else if (after) j++;
    s.fields.splice(j, 0, key);
  }
  function newField(type) {
    var key = 'cf:new' + (++newSeq);
    F().fields[key] = { key: key, label: TYPES[type] || 'New field', kind: ICONS[type] || 'input-cursor-text', optional: true, custom: true, isNew: true, type: type, options: CHOICE[type] ? 'Option 1\nOption 2' : '',
      show: true, req: false, inList: false, w: '', help: '', span: 4 };
    return key;
  }
  function hideField(key) { var x = fld(key); x.show = false; x.req = false; detach(key); if (F().summary) F().summary = F().summary.filter(function (k) { return k !== key; }); if (sel === key) sel = null; }
  function restore(key, si) { var x = fld(key); x.show = true; if (si == null) si = F().sections.length - 1; insertAt(si, null, false, key); }
  function drop(si, ref, after) {
    var k;
    if (!drag) return;
    if (drag.kind === 'tile') { k = drag.key; if (k === ref) return; detach(k); }
    else if (drag.kind === 'unused') { k = drag.key; fld(k).show = true; }
    else { if (!F().customFields) return; k = newField(drag.type); }
    insertAt(si, ref, after, k); sel = k; drag = null; touch(); render();
  }
  function move(key, dir) {
    var p = locate(key), f = F(); if (!p) return;
    var si = p[0], j = p[1] + dir;
    if (j < 0) { if (si === 0) return; f.sections[si].fields.splice(p[1], 1); f.sections[si - 1].fields.push(key); }
    else if (j >= f.sections[si].fields.length) { if (si === f.sections.length - 1) return; f.sections[si].fields.splice(p[1], 1); f.sections[si + 1].fields.unshift(key); }
    else { f.sections[si].fields.splice(p[1], 1); f.sections[si].fields.splice(j, 0, key); }
    touch(); render();
  }

  /* ---------------- popovers ---------------- */
  var pop = $('dzPop');
  function openPop(btn, html, onClick) {
    pop.innerHTML = html; pop.hidden = false;
    var r = btn.getBoundingClientRect(), w = pop.offsetWidth;
    pop.style.top = (r.bottom + 6 + window.scrollY) + 'px'; pop.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.right - w)) + 'px';
    pop.onclick = function (e) { var b = e.target.closest('[data-p]'); if (!b) return; pop.hidden = true; onClick(b.dataset.p); };
  }
  document.addEventListener('mousedown', function (e) { if (!pop.hidden && !pop.contains(e.target) && !e.target.closest('[data-a=menu],[data-a=cols],[data-a=sum-add]')) pop.hidden = true; });

  /* ---------------- events ---------------- */
  var secs = $('dzSections');
  $('dzSum').addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]'); if (!b) return;
    if (b.dataset.a === 'sum-del') sumToggle(b.dataset.key);
    if (b.dataset.a === 'sum-add') {
      var f = F(), sum = f.summary || [], opts = [];
      f.sections.forEach(function (s) { s.fields.forEach(function (k) { if (fld(k) && sum.indexOf(k) < 0) opts.push(k); }); });
      if (!opts.length) return;
      openPop(b, opts.map(function (k) { return '<button data-p="' + esc(k) + '"><i class="bi bi-' + esc(fld(k).kind) + '"></i> ' + esc(labelOf(fld(k))) + '</button>'; }).join(''), function (k) { sumToggle(k); });
    }
  });
  $('dzSum').addEventListener('dragover', function (e) { if (drag && drag.kind === 'tile') { e.preventDefault(); $('dzSum').classList.add('drop-into'); } });
  $('dzSum').addEventListener('dragleave', function () { $('dzSum').classList.remove('drop-into'); });
  $('dzSum').addEventListener('drop', function (e) { $('dzSum').classList.remove('drop-into'); if (!drag || drag.kind !== 'tile') return; e.preventDefault(); var k = drag.key; drag = null; var sum = F().summary || (F().summary = []); if (sum.indexOf(k) < 0) { if (sum.length >= 4) { alert('The summary strip holds up to four fields.'); return; } sum.push(k); touch(); render(); } });
  secs.addEventListener('click', function (e) {
    var tile = e.target.closest('.dz-tile'), btn = e.target.closest('[data-a]'), sec = e.target.closest('.dz-sec');
    var a = btn && btn.dataset.a;
    if (a === 'menu' && tile) {
      var x = fld(tile.dataset.key);
      openPop(btn, '<button data-p="edit"><i class="bi bi-pencil"></i> Edit properties</button>' +
        (x.optional && x.type !== 'checkbox' ? '<button data-p="req"><i class="bi bi-asterisk"></i> ' + (x.req ? 'Make optional' : 'Make mandatory') + '</button>' : '') +
        (F().detail ? '<button data-p="sum"><i class="bi bi-pin-angle"></i> ' + ((F().summary || []).indexOf(x.key) > -1 ? 'Remove from summary strip' : 'Show in summary strip') + '</button>' : '') +
        '<button data-p="up"><i class="bi bi-arrow-up"></i> Move earlier</button><button data-p="down"><i class="bi bi-arrow-down"></i> Move later</button>' +
        (x.optional ? '<button data-p="hide"><i class="bi bi-eye-slash"></i> Move to unused</button>' : '') + (x.custom ? '<button data-p="del" class="danger"><i class="bi bi-trash"></i> Delete field</button>' : ''),
        function (p) {
          if (p === 'edit') { sel = x.key; render(); }
          if (p === 'sum') sumToggle(x.key);
          if (p === 'req') { x.req = !x.req; touch(); render(); }
          if (p === 'up') move(x.key, -1); if (p === 'down') move(x.key, 1);
          if (p === 'hide') { hideField(x.key); touch(); render(); }
          if (p === 'del') delField(x.key);
        });
      return;
    }
    if (a === 'cols' && sec) {
      var si = +sec.dataset.i;
      openPop(btn, [[0, 'Original widths'], [1, '1 column'], [2, '2 columns'], [3, '3 columns']].map(function (o) { return '<button data-p="' + o[0] + '"' + (F().sections[si].cols === o[0] ? ' class="on"' : '') + '><i class="bi bi-layout-three-columns"></i> ' + o[1] + '</button>'; }).join(''),
        function (p) { F().sections[si].cols = +p; touch(); render(); });
      return;
    }
    if (a === 'sec-up' || a === 'sec-down') { var i = +sec.dataset.i, j = i + (a === 'sec-up' ? -1 : 1), S = F().sections; if (j < 0 || j >= S.length) return; var t = S[i]; S[i] = S[j]; S[j] = t; touch(); render(); return; }
    if (a === 'sec-del') {
      var S2 = F().sections, i2 = +sec.dataset.i;
      if (S2.length === 1) { alert('A form needs at least one section.'); return; }
      var dst = S2[i2 === 0 ? 1 : i2 - 1]; S2[i2].fields.forEach(function (k) { dst.fields.push(k); }); S2.splice(i2, 1); touch(); render(); return;
    }
    if (tile && !btn && !preview) { sel = tile.dataset.key; render(); }
    else if (tile && preview) { sel = tile.dataset.key; preview = false; render(); }
  });
  secs.addEventListener('input', function (e) { var t = e.target.closest('[data-a=title]'); if (!t) return; var si = +t.closest('.dz-sec').dataset.i; F().sections[si].title = t.value; touchTyped('sec' + si); });
  $('dzUnused').addEventListener('click', function (e) { var b = e.target.closest('[data-a=restore]'); if (!b) return; var k = b.closest('.dz-un').dataset.key; restore(k); sel = k; touch(); render(); });
  $('dzLines').addEventListener('click', function (e) { var c = e.target.closest('[data-line]'); if (!c) return; F().lines[c.dataset.line] = !F().lines[c.dataset.line]; touch(); render(); });

  // drag & drop
  $('dzTypes').addEventListener('dragstart', function (e) { var t = e.target.closest('.dz-type'); if (!t || !F().customFields) { e.preventDefault(); return; } drag = { kind: 'new', type: t.dataset.type }; e.dataTransfer.effectAllowed = 'copy'; try { e.dataTransfer.setData('text/plain', t.dataset.type); } catch (x) {} });
  $('dzUnused').addEventListener('dragstart', function (e) { var t = e.target.closest('.dz-un'); if (!t) return; drag = { kind: 'unused', key: t.dataset.key }; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', t.dataset.key); } catch (x) {} });
  secs.addEventListener('dragstart', function (e) { var t = e.target.closest('.dz-tile'); if (!t || preview || !F().layout) { e.preventDefault(); return; } drag = { kind: 'tile', key: t.dataset.key }; t.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', t.dataset.key); } catch (x) {} });
  document.addEventListener('dragend', function () { drag = null; clearDrop(); document.querySelectorAll('.dragging').forEach(function (n) { n.classList.remove('dragging'); }); });
  function clearDrop() { document.querySelectorAll('.drop-before,.drop-after,.drop-into').forEach(function (n) { n.classList.remove('drop-before', 'drop-after', 'drop-into'); }); }
  function isAfter(t, e) { var r = t.getBoundingClientRect(); return (e.clientX - r.left) > r.width / 2; }
  secs.addEventListener('dragover', function (e) {
    if (!drag) return; e.preventDefault(); clearDrop();
    var t = e.target.closest('.dz-tile'), g = e.target.closest('.dz-grid');
    if (t && !(drag.kind === 'tile' && t.dataset.key === drag.key)) t.classList.add(isAfter(t, e) ? 'drop-after' : 'drop-before'); else if (g && !t) g.classList.add('drop-into');
  });
  secs.addEventListener('drop', function (e) {
    if (!drag) return; e.preventDefault(); clearDrop();
    var t = e.target.closest('.dz-tile'), g = e.target.closest('.dz-grid'), s = e.target.closest('.dz-sec');
    if (!s) return;
    var si = +s.dataset.i;
    if (t) drop(si, t.dataset.key, isAfter(t, e)); else drop(si, null, false);
  });
  $('dzUnused').addEventListener('dragover', function (e) { if (drag && drag.kind === 'tile' && fld(drag.key).optional) { e.preventDefault(); $('dzUnused').classList.add('drop-into'); } });
  $('dzUnused').addEventListener('dragleave', function () { $('dzUnused').classList.remove('drop-into'); });
  $('dzUnused').addEventListener('drop', function (e) { $('dzUnused').classList.remove('drop-into'); if (drag && drag.kind === 'tile' && fld(drag.key).optional) { e.preventDefault(); hideField(drag.key); drag = null; touch(); render(); } });

  function delField(key) {
    var x = fld(key); if (!confirm('Delete the field "' + x.label + '"? Everything typed into it on existing records is deleted too.')) return;
    detach(key); if (!x.isNew) F().deleted.push(key); delete F().fields[key]; if (sel === key) sel = null; touch(); render();
  }

  // right panel
  function typed(fn) { return function () { var x = sel && fld(sel); if (!x) return; fn(x, this); touchTyped(sel + ':' + this.id); keepFocusRender(); }; }
  function keepFocusRender() { var id = document.activeElement && document.activeElement.id; render(); if (id && $(id)) { var el = $(id), v = el.selectionStart; el.focus(); try { el.setSelectionRange(v, v); } catch (x) {} } }
  $('pLabel').addEventListener('input', typed(function (x, el) { if (x.custom) x.label = el.value; else x.custom_label = el.value === x.label ? '' : el.value; }));
  $('pHelp').addEventListener('input', typed(function (x, el) { x.help = el.value; }));
  $('pOptions').addEventListener('input', typed(function (x, el) { x.options = el.value; }));
  $('pType').addEventListener('change', function () { var x = fld(sel); if (!x || !x.isNew) return; x.type = this.value; x.kind = ICONS[this.value] || x.kind; if (CHOICE[x.type] && !x.options) x.options = 'Option 1\nOption 2'; if (x.type === 'checkbox') x.req = false; touch(); render(); });
  $('pWidth').addEventListener('click', function (e) { var b = e.target.closest('button'), x = sel && fld(sel); if (!b || !x) return; x.w = b.dataset.w; touch(); render(); });
  $('pReq').addEventListener('change', function () { var x = fld(sel); x.req = this.checked; touch(); render(); });
  $('pList').addEventListener('change', function () { var x = fld(sel); x.inList = this.checked; touch(); });
  $('pUniq').addEventListener('change', function () { var x = fld(sel); x.unique = this.checked; touch(); });
  $('pUp').addEventListener('click', function () { move(sel, -1); });
  $('pDown').addEventListener('click', function () { move(sel, 1); });
  $('pHide').addEventListener('click', function () { hideField(sel); touch(); render(); });
  $('pDelete').addEventListener('click', function () { delField(sel); });
  $('pClose').addEventListener('click', function () { sel = null; render(); });

  // top bar
  formSel.addEventListener('change', function () { cur = +formSel.value; sel = null; render(); });
  $('dzStyle').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; F().style = b.dataset.s; touch(); render(); });
  $('dzPreview').addEventListener('click', function () { preview = !preview; render(); });
  $('dzUndo').addEventListener('click', undo);
  $('dzAddSection').addEventListener('click', function () { F().sections.push({ id: 'n' + (++newSeq), title: 'New section', cols: 2, fields: [] }); touch(); render(); var inputs = document.querySelectorAll('.dz-sec-title'); inputs[inputs.length - 1].focus(); inputs[inputs.length - 1].select(); });
  $('dzReset').addEventListener('click', function () {
    var f = F(); if (!confirm('Put "' + f.title + '" back to the standard design? Your custom fields stay. (Nothing is saved until you press Save.)')) return;
    var std = f.stdOrder.slice(), cfs = Object.keys(f.fields).filter(function (k) { return f.fields[k].custom; });
    Object.keys(f.fields).forEach(function (k) { var x = f.fields[k]; x.w = ''; x.help = ''; if (!x.custom) { x.custom_label = ''; x.show = true; x.req = false; } else x.show = true; });
    f.sections = [{ id: 's1', title: '', cols: 0, fields: f.layout ? std : [] }];
    if (cfs.length) f.sections.push({ id: 's2', title: 'Additional details', cols: 0, fields: cfs });
    var sysK = (f.sysOrder || []); sysK.forEach(function (k) { f.fields[k].show = true; f.fields[k].custom_label = ''; });
    if (sysK.length) f.sections.push({ id: 'sys', title: 'Record details', cols: 2, fields: sysK.slice() });
    if (f.detail && f.summaryDefault) f.summary = f.summaryDefault.slice();
    if (!f.layout) f.sections[0].fields = Object.keys(f.fields).filter(function (k) { return !f.fields[k].custom; });
    f.style = 'top'; f.lines = { disc: true, tax: true }; f.resetFlag = true; sel = null; touch(true); render();
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !/input|textarea|select/i.test(e.target.tagName)) { e.preventDefault(); undo(); }
    if (e.key === 'Escape') { pop.hidden = true; if (preview) { preview = false; render(); } }
  });

  // save
  var initialByKey = {}; JSON.parse(initial).forEach(function (f) { initialByKey[f.key] = JSON.stringify(f); });
  $('dzSave').addEventListener('submit', function (e) {
    var out = {};
    data.forEach(function (f) {
      if (JSON.stringify(f) === initialByKey[f.key]) return;
      var o = { style: f.style, lines: f.lines, deleted: f.deleted, sections: f.sections.map(function (s) { return { title: s.title, cols: s.cols, fields: s.fields.slice() }; }), fields: {} };
      if (f.detail) o.summary = (f.summary || []).slice();
      if (f.resetFlag) o.reset = true;
      Object.keys(f.fields).forEach(function (k) {
        var x = f.fields[k];
        o.fields[k] = { w: x.w, help: x.help, show: x.show, req: x.req };
        if (x.custom) { o.fields[k].name = x.label; o.fields[k].type = x.type; o.fields[k].options = x.options; o.fields[k].inList = x.inList; o.fields[k].unique = !!x.unique; if (x.isNew) o.fields[k].isNew = true; }
        else o.fields[k].custom_label = x.custom_label;
      });
      out[f.key] = o;
    });
    $('dzPayload').value = JSON.stringify(out);
    $('dzClose').value = (e.submitter && e.submitter.id === 'dzSaveClose') ? '1' : '';
    window.removeEventListener('beforeunload', warn);
  });
  function warn(e) { if (snap() !== initial) { e.preventDefault(); e.returnValue = ''; } }
  window.addEventListener('beforeunload', warn);

  history.push(snap());
  render();
})();
