/* Item search box used by the purchase, sales, delivery and stock line editors.
   ItemPicker.attach({ input, list, host, url(q), render(row) -> {title, sub, tag, right}, pick(row), onType(), emptyOk })
   Typing searches; ↑ ↓ move, Enter picks, Esc closes; the panel opens upwards when there is no room below. */
(function () {
  function h(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  window.ItemPicker = {
    attach: function (o) {
      var input = o.input, list = o.list, host = o.host, rows = [], active = -1, timer = null, seq = 0;
      list.classList.add('ip-list'); list.setAttribute('role', 'listbox'); input.setAttribute('autocomplete', 'off'); input.setAttribute('role', 'combobox'); input.setAttribute('aria-expanded', 'false');

      function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }
      function place() {   // open below the box, or above it when the page ends too soon
        list.classList.remove('up');
        var r = input.getBoundingClientRect(), room = window.innerHeight - r.bottom, want = Math.min(list.scrollHeight, 340) + 12;
        if (room < want && r.top > room) list.classList.add('up');
      }
      function open() { list.hidden = false; input.setAttribute('aria-expanded', 'true'); place(); }
      function mark(i, scroll) {
        active = i;
        Array.prototype.forEach.call(list.querySelectorAll('.ip-row'), function (el, k) { el.classList.toggle('on', k === i); if (k === i && scroll) el.scrollIntoView({ block: 'nearest' }); });
      }
      function show(res) {
        rows = res; list.innerHTML = '';
        if (!res.length) { list.appendChild(h('div', 'ip-msg', 'No items found — check the spelling or try the SKU.')); open(); active = -1; return; }
        res.forEach(function (v, i) {
          var d = o.render(v), row = h('div', 'ip-row'), left = h('div', 'ip-main');
          row.setAttribute('role', 'option');
          left.appendChild(h('div', 'ip-title', d.title));
          if (d.sub) left.appendChild(h('div', 'ip-sub', d.sub));
          row.appendChild(left);
          if (d.tag || d.right) { var rt = h('div', 'ip-side'); if (d.right) rt.appendChild(h('div', 'ip-right', d.right)); if (d.tag) rt.appendChild(h('span', 'ip-tag', d.tag)); row.appendChild(rt); }
          row.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });   // mousedown: the input must not lose focus first
          row.addEventListener('mousemove', function () { if (active !== i) mark(i, false); });
          list.appendChild(row);
        });
        open(); mark(0, false);
      }
      function choose(i) { if (rows[i]) { close(); o.pick(rows[i]); } }
      function search() {
        var q = input.value.trim(), my = ++seq;
        if (!q && !o.emptyOk) { close(); return; }
        list.innerHTML = ''; list.appendChild(h('div', 'ip-msg', 'Searching…')); open();
        fetch(o.url(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) { if (my === seq) show(res); }).catch(function () { if (my === seq) { list.innerHTML = ''; list.appendChild(h('div', 'ip-msg', 'Could not load the items. Try again.')); } });
      }
      input.addEventListener('input', function () { if (o.onType) o.onType(); clearTimeout(timer); timer = setTimeout(search, 160); });
      input.addEventListener('focus', function () { if (o.emptyOk && !input.value.trim()) search(); else if (rows.length && input.value.trim() && !list.hidden) place(); });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); if (!list.hidden && active > -1) choose(active); return; }
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'Tab') { close(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (list.hidden) { if (rows.length) { open(); mark(Math.max(active, 0), true); } else search(); return; }
          if (!rows.length) return;
          mark(e.key === 'ArrowDown' ? (active + 1) % rows.length : (active - 1 + rows.length) % rows.length, true);
        }
      });
      document.addEventListener('mousedown', function (e) { if (!host.contains(e.target)) close(); });
      window.addEventListener('resize', function () { if (!list.hidden) place(); });
    }
  };
})();
