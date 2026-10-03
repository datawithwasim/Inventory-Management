(function () {
  var data = JSON.parse(document.getElementById('fdzData').textContent);
  var $ = function (id) { return document.getElementById(id); };
  var WIDTHS = ['25', '33', '50', '66', '75', '100'];
  var cur = 0, sel = null, drag = null, history = [], typing = null;
  var initial = JSON.stringify(data);

  var formSel = $('fdzForm'), canvas = $('fdzCanvas'), tray = $('fdzTray'), trayWrap = $('fdzTrayWrap');
  var sections = {};
  data.forEach(function (f, i) {
    var g = sections[f.section]; if (!g) { g = sections[f.section] = document.createElement('optgroup'); g.label = f.section; formSel.appendChild(g); }
    var o = document.createElement('option'); o.value = i; o.textContent = f.title; g.appendChild(o);
  });

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function form() { return data[cur]; }
  function field(col) { return form().fields.filter(function (f) { return f.col === col; })[0]; }
  function spanOf(f) { return f.w ? Math.max(1, Math.round(parseInt(f.w, 10) / 100 * 12)) : f.span; }
  function snapshot() { return JSON.stringify(data); }

  function touch(keepReset) {
    if (!keepReset) form().resetFlag = false;
    var now = snapshot();
    if (history[history.length - 1] !== now) history.push(now);
    if (history.length > 60) history.shift();
    $('fdzUndo').disabled = history.length < 2;
    $('fdzDirty').classList.toggle('d-none', now === initial);
  }
  function undo() {
    if (history.length < 2) return;
    history.pop(); data = JSON.parse(history[history.length - 1]);
    $('fdzUndo').disabled = history.length < 2;
    $('fdzDirty').classList.toggle('d-none', snapshot() === initial);
    if (sel && !field(sel)) sel = null;
    render();
  }

  function render() {
    var f = form();
    $('fdzTitle').textContent = f.title;
    $('fdzCustom').classList.toggle('d-none', !f.customised && !f.fields.some(function (x) { return x.w || x.custom || x.help || !x.show || x.req; }));
    formSel.value = cur;
    canvas.innerHTML = ''; tray.innerHTML = '';
    var hidden = 0;
    f.fields.forEach(function (x) {
      if (!x.show) {
        hidden++;
        var chip = document.createElement('button'); chip.type = 'button'; chip.className = 'fdz-chip';
        chip.innerHTML = '<i class="bi bi-plus-lg"></i>' + esc(x.custom || x.label);
        chip.addEventListener('click', function () { x.show = true; f.fields.splice(f.fields.indexOf(x), 1); f.fields.push(x); sel = x.col; touch(); render(); });
        tray.appendChild(chip); return;
      }
      var t = document.createElement('div');
      t.className = 'fdz-tile' + (x.col === sel ? ' sel' : '') + (f.layout ? '' : ' fixed');
      t.style.setProperty('--span', spanOf(x)); t.draggable = f.layout; t.tabIndex = 0; t.dataset.col = x.col;
      t.innerHTML = '<i class="bi bi-grip-vertical fdz-grip"></i><span class="fdz-ico"><i class="bi bi-' + x.kind + '"></i></span>' +
        '<span class="fdz-lbl"><span class="fdz-name">' + esc(x.custom || x.label) + (x.req ? '<b class="text-danger"> *</b>' : '') + '</span>' +
        (x.help ? '<span class="fdz-sub">' + esc(x.help) + '</span>' : (x.custom ? '<span class="fdz-sub">was: ' + esc(x.label) + '</span>' : '')) + '</span>' +
        '<span class="fdz-tools">' + (f.layout ? '<button type="button" data-a="narrow" title="Narrower"><i class="bi bi-dash"></i></button><button type="button" data-a="wide" title="Wider"><i class="bi bi-plus"></i></button>' : '') +
        (x.optional ? '<button type="button" data-a="hide" title="Hide this field"><i class="bi bi-eye-slash"></i></button>' : '<i class="bi bi-lock-fill text-muted ms-1" title="Essential field"></i>') + '</span>' +
        '<span class="fdz-fake"></span>';
      canvas.appendChild(t);
    });
    trayWrap.hidden = !hidden;
    renderPanel();
  }

  function renderPanel() {
    var x = sel && field(sel);
    $('fdzEmpty').hidden = !!x; $('fdzProps').hidden = !x;
    if (!x) return;
    $('pIcon').className = 'bi bi-' + x.kind; $('pName').textContent = x.label;
    $('pType').textContent = x.optional ? 'Optional field' : 'Essential field';
    if (document.activeElement !== $('pLabel')) $('pLabel').value = x.custom;
    $('pLabel').placeholder = x.label;
    if (document.activeElement !== $('pHelp')) $('pHelp').value = x.help;
    document.querySelectorAll('#pWidth button').forEach(function (b) { b.classList.toggle('on', b.dataset.w === (x.w || '')); });
    $('pWidth').classList.toggle('disabled', !form().layout);
    $('pOptional').hidden = !x.optional; $('pCore').hidden = x.optional;
    $('pShow').checked = x.show; $('pReq').checked = x.req; $('pReq').disabled = !x.show;
    $('pMove').hidden = !form().layout;
  }

  function move(col, dir) {
    var fs = form().fields, i = fs.indexOf(field(col)), j = i + dir;
    while (j >= 0 && j < fs.length && !fs[j].show) j += dir;
    if (j < 0 || j >= fs.length) return;
    var it = fs.splice(i, 1)[0]; fs.splice(j, 0, it); touch(); render();
  }
  function stepWidth(x, dir) {
    var cur = x.w || String(Math.round(x.span / 12 * 100));
    var best = 0, d = 1e9; WIDTHS.forEach(function (w, i) { var dd = Math.abs(parseInt(w, 10) - parseInt(cur, 10)); if (dd < d) { d = dd; best = i; } });
    var n = Math.max(0, Math.min(WIDTHS.length - 1, best + dir)); x.w = WIDTHS[n]; touch(); render();
  }

  /* ---- events ---- */
  formSel.addEventListener('change', function () { cur = +formSel.value; sel = null; render(); });
  canvas.addEventListener('click', function (e) {
    var t = e.target.closest('.fdz-tile'); if (!t) return;
    var x = field(t.dataset.col), b = e.target.closest('button[data-a]');
    if (b) {
      e.stopPropagation();
      if (b.dataset.a === 'hide') { x.show = false; x.req = false; if (sel === x.col) sel = null; touch(); render(); }
      else stepWidth(x, b.dataset.a === 'wide' ? 1 : -1);
      return;
    }
    sel = x.col; render();
  });
  canvas.addEventListener('keydown', function (e) {
    var t = e.target.closest('.fdz-tile'); if (!t) return;
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sel = t.dataset.col; render(); var n = canvas.querySelector('[data-col="' + sel + '"]'); if (n) n.focus(); }
    if (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowUp')) { e.preventDefault(); move(t.dataset.col, -1); canvas.querySelector('[data-col="' + t.dataset.col + '"]').focus(); }
    if (e.altKey && (e.key === 'ArrowRight' || e.key === 'ArrowDown')) { e.preventDefault(); move(t.dataset.col, 1); canvas.querySelector('[data-col="' + t.dataset.col + '"]').focus(); }
  });
  canvas.addEventListener('dragstart', function (e) {
    var t = e.target.closest('.fdz-tile'); if (!t || !form().layout) return;
    drag = t.dataset.col; t.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); } catch (x) {}
  });
  canvas.addEventListener('dragend', function () { drag = null; clearDrop(); canvas.querySelectorAll('.dragging').forEach(function (n) { n.classList.remove('dragging'); }); });
  function clearDrop() { canvas.querySelectorAll('.drop-before,.drop-after').forEach(function (n) { n.classList.remove('drop-before', 'drop-after'); }); }
  function after(t, e) { var r = t.getBoundingClientRect(); return (e.clientX - r.left) > r.width / 2; }
  canvas.addEventListener('dragover', function (e) {
    if (!drag) return; e.preventDefault(); clearDrop();
    var t = e.target.closest('.fdz-tile'); if (!t || t.dataset.col === drag) return;
    t.classList.add(after(t, e) ? 'drop-after' : 'drop-before');
  });
  canvas.addEventListener('drop', function (e) {
    if (!drag) return; e.preventDefault();
    var t = e.target.closest('.fdz-tile'); clearDrop();
    if (!t || t.dataset.col === drag) return;
    var fs = form().fields, item = field(drag), isAfter = after(t, e);
    fs.splice(fs.indexOf(item), 1);
    var ti = fs.indexOf(field(t.dataset.col)); fs.splice(isAfter ? ti + 1 : ti, 0, item);
    sel = drag; drag = null; touch(); render();
  });

  function bind(id, ev, fn) { $(id).addEventListener(ev, fn); }
  function typed(fn) { return function () { var x = sel && field(sel); if (!x) return; fn(x, this); clearTimeout(typing); typing = setTimeout(touch, 450); renderTiles(); }; }
  function renderTiles() { var keep = document.activeElement && document.activeElement.id; render(); if (keep && $(keep)) $(keep).focus(); }
  bind('pLabel', 'input', typed(function (x, el) { x.custom = el.value.trim() === x.label ? '' : el.value; }));
  bind('pHelp', 'input', typed(function (x, el) { x.help = el.value; }));
  bind('pWidth', 'click', function (e) { var b = e.target.closest('button'); var x = sel && field(sel); if (!b || !x || !form().layout) return; x.w = b.dataset.w; touch(); render(); });
  bind('pShow', 'change', function () { var x = field(sel); x.show = this.checked; if (!x.show) { x.req = false; sel = null; } touch(); render(); });
  bind('pReq', 'change', function () { var x = field(sel); x.req = this.checked; touch(); render(); });
  bind('pUp', 'click', function () { move(sel, -1); });
  bind('pDown', 'click', function () { move(sel, 1); });
  bind('fdzUndo', 'click', undo);
  bind('fdzReset', 'click', function () {
    if (!confirm('Put "' + form().title + '" back to the standard design? (Nothing is saved until you press Save.)')) return;
    var f = form(); f.fields.sort(function (a, b) { return a.pos - b.pos; });
    f.fields.forEach(function (x) { x.w = ''; x.custom = ''; x.help = ''; x.show = true; x.req = false; });
    f.resetFlag = true; sel = null; touch(true); render();
  });
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !/input|textarea/i.test(e.target.tagName)) { e.preventDefault(); undo(); } });

  $('fdzSave').addEventListener('submit', function () {
    var out = {};
    data.forEach(function (f) {
      var o = { order: f.fields.map(function (x) { return x.col; }), fields: {} };
      if (f.resetFlag) o.reset = true;
      f.fields.forEach(function (x) { o.fields[x.col] = { w: x.w, label: x.custom, help: x.help, show: x.show, req: x.req }; });
      out[f.key] = o;
    });
    $('fdzPayload').value = JSON.stringify(out);
    window.removeEventListener('beforeunload', warn);
  });
  function warn(e) { if (snapshot() !== initial) { e.preventDefault(); e.returnValue = ''; } }
  window.addEventListener('beforeunload', warn);

  history.push(snapshot());
  render();
})();
