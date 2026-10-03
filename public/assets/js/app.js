(function () {
  var doc = document, root = doc.documentElement, body = doc.body;
  var base = ((doc.querySelector('meta[name=base]') || {}).content || '').replace(/\/+$/, '');
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

  /* ---- feedback: top progress bar while a page loads, and a spinner on submit buttons (also stops double-clicks) ---- */
  var bar = doc.createElement('div'); bar.id = 'navbar-progress'; body.appendChild(bar);
  function startBar() { bar.className = ''; void bar.offsetWidth; bar.className = 'on'; }
  function resetBar() { bar.className = ''; }
  window.addEventListener('pageshow', function () {
    resetBar();
    doc.querySelectorAll('.btn.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.disabled = false; var sp = b.querySelector('.btn-spin'); if (sp) sp.remove(); });
  });
  doc.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var h = a.getAttribute('href');
    if (!h || h.charAt(0) === '#' || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || /^(mailto|tel|javascript):/.test(h)) return;
    if (a.origin && a.origin !== location.origin) return;
    if (/\/export\/|print=1|\/print$/.test(h)) return;
    startBar();
  });
  doc.addEventListener('submit', function (e) {
    var f = e.target; if (e.defaultPrevented || !f.matches || !f.matches('form') || f.hasAttribute('data-noload')) return;
    if ((f.getAttribute('target') || '') === '_blank') return;
    startBar();
    var b = e.submitter || f.querySelector('button:not([type=button]),input[type=submit]');
    if (b && b.tagName === 'BUTTON' && !b.classList.contains('is-loading')) {
      setTimeout(function () { b.classList.add('is-loading'); b.insertAdjacentHTML('afterbegin', '<span class="btn-spin"></span>'); b.disabled = true; }, 0);
    }
  });

  /* ---- sidebar ---- */
  var sb = doc.getElementById('sidebar');
  if (sb) {
    // Groups are closed by default; the one holding the current page is open, and so are any the user opened themselves.
    var opened = []; try { opened = JSON.parse(store('inv-open') || '[]'); } catch (e) {}
    sb.querySelectorAll('.sb-group').forEach(function (g) {
      var key = g.dataset.group, hasActive = !!g.querySelector('.sb-link.active');
      if (hasActive || opened.indexOf(key) > -1) g.classList.add('open');
      g.querySelector('.sb-group-head').setAttribute('aria-expanded', g.classList.contains('open'));
      g.querySelector('.sb-group-head').addEventListener('click', function () {
        g.classList.toggle('open');
        var o = []; sb.querySelectorAll('.sb-group.open').forEach(function (x) { o.push(x.dataset.group); });
        store('inv-open', JSON.stringify(o));
        this.setAttribute('aria-expanded', g.classList.contains('open'));
      });
    });
    var active = sb.querySelector('.sb-link.active'); if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    var col = doc.getElementById('sbCollapse');
    if (col) col.addEventListener('click', function () { root.classList.toggle('sb-collapsed'); store('inv-sb', root.classList.contains('sb-collapsed') ? '1' : '0'); });
    var menuBtn = doc.getElementById('menuBtn'), back = doc.getElementById('sbBackdrop');
    if (menuBtn) menuBtn.addEventListener('click', function () { body.classList.add('sb-open'); });
    if (back) back.addEventListener('click', function () { body.classList.remove('sb-open'); });
    sb.addEventListener('click', function (e) { if (e.target.closest('a.sb-link')) body.classList.remove('sb-open'); });
  }

  /* ---- light / dark ---- */
  var modeBtn = doc.getElementById('modeBtn');
  function syncModeIcon() { if (modeBtn) modeBtn.innerHTML = '<i class="bi bi-' + (root.getAttribute('data-bs-theme') === 'dark' ? 'sun' : 'moon-stars') + '"></i>'; }
  syncModeIcon();
  if (modeBtn) modeBtn.addEventListener('click', function () {
    var m = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-bs-theme', m); root.setAttribute('data-theme', m); store('inv-mode', m); syncModeIcon();
  });

  /* ---- flash messages: success fades away by itself ---- */
  doc.querySelectorAll('.alert-success.alert-dismissible').forEach(function (a) {
    setTimeout(function () { if (window.bootstrap && a.isConnected) bootstrap.Alert.getOrCreateInstance(a).close(); }, 5000);
  });

  /* ---- filter forms: dropdowns apply immediately ---- */
  doc.querySelectorAll('form[method=get]').forEach(function (f) {
    if (f.hasAttribute('data-noauto') || f.closest('.palette')) return;
    f.querySelectorAll('select').forEach(function (s) { if (s.form === f) s.addEventListener('change', function () { f.submit(); }); });
    f.querySelectorAll('input[type=checkbox]').forEach(function (c) { if (c.form === f) c.addEventListener('change', function () { f.submit(); }); });
  });

  /* ---- tables that are not already scrollable get a scroll wrapper, so wide tables never stretch the page on phones ---- */
  doc.querySelectorAll('main table').forEach(function (t) {
    if (t.closest('.table-responsive') || t.id === 'lineTable' || t.classList.contains('table-borderless') || t.closest('.viz') || t.closest('.palette')) return;
    var w = doc.createElement('div'); w.className = 'table-responsive'; t.parentNode.insertBefore(w, t); w.appendChild(t);
  });

  /* ---- line tables: copy the column titles onto cells so the stacked phone layout can show them ---- */
  var lt = doc.getElementById('lineTable');
  if (lt && window.MutationObserver) {
    var heads = Array.prototype.map.call(lt.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    var label = function () { lt.querySelectorAll('tbody tr').forEach(function (tr) { Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i]); }); }); };
    new MutationObserver(label).observe(lt.querySelector('tbody'), { childList: true }); label();
  }

  /* ---- command palette ---- */
  var pal = doc.getElementById('palette'); if (!pal) return;
  var input = doc.getElementById('paletteInput'), list = doc.getElementById('paletteList');
  var local = []; try { local = JSON.parse(doc.getElementById('paletteData').textContent); } catch (e) {}
  var shown = [], sel = 0, timer = null, seq = 0;
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function render(groups) {
    shown = []; var h = '';
    groups.forEach(function (g) {
      if (!g.rows.length) return;
      h += '<div class="pal-head">' + esc(g.title) + '</div>';
      g.rows.forEach(function (r) { shown.push(r); h += '<a class="pal-item" href="' + esc(r.u) + '" data-i="' + (shown.length - 1) + '"><i class="bi bi-' + esc(r.i || 'dot') + '"></i><span>' + esc(r.l) + '</span>' + (r.s ? '<span class="sub">' + esc(r.s) + '</span>' : '') + '</a>'; });
    });
    list.innerHTML = h || '<div class="pal-empty">No matches</div>';
    sel = 0; mark();
  }
  function mark() { list.querySelectorAll('.pal-item').forEach(function (a, i) { a.classList.toggle('sel', i === sel); if (i === sel && a.scrollIntoView) a.scrollIntoView({ block: 'nearest' }); }); }
  function localMatches(q) {
    q = q.toLowerCase(); var by = {};
    local.forEach(function (r) { if (!q || r.l.toLowerCase().indexOf(q) > -1) (by[r.t] = by[r.t] || []).push(r); });
    return [{ title: 'Create', rows: (by['Create'] || []).slice(0, q ? 6 : 8) }, { title: 'Go to', rows: (by['Go to'] || by['Page'] || []).slice(0, q ? 8 : 10) }];
  }
  var remote = [];
  function draw() { var q = input.value.trim(); render(remote.concat(localMatches(q))); }
  function search() {
    var q = input.value.trim();
    if (q.length < 2) { remote = []; draw(); return; }
    var my = ++seq;
    fetch(base + '/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (d) { if (my !== seq) return; remote = d.groups || []; draw(); }).catch(function () {});
  }
  function open() { pal.hidden = false; input.value = ''; remote = []; draw(); input.focus(); }
  function close() { pal.hidden = true; }
  doc.querySelectorAll('[data-palette]').forEach(function (b) { b.addEventListener('click', open); });
  pal.addEventListener('click', function (e) { if (e.target === pal) close(); });
  input.addEventListener('input', function () { draw(); clearTimeout(timer); timer = setTimeout(search, 180); });
  doc.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); pal.hidden ? open() : close(); return; }
    if (e.key === '/' && pal.hidden && tag !== 'input' && tag !== 'textarea' && tag !== 'select' && !e.target.isContentEditable) { e.preventDefault(); open(); return; }
    if (pal.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, shown.length - 1); mark(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); mark(); }
    else if (e.key === 'Enter' && shown[sel]) { e.preventDefault(); window.location.href = shown[sel].u; }
  });
  list.addEventListener('mousemove', function (e) { var a = e.target.closest('.pal-item'); if (a) { sel = +a.dataset.i; mark(); } });
})();
