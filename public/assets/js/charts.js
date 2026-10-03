(function () {
  var tip = document.createElement('div');
  tip.className = 'viz-tip'; tip.style.display = 'none';
  document.body.appendChild(tip);

  function esc(s) { return s.replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }

  // data-tip: first line = title, then "idx|name|value" lines (idx 1..4 = series swatch, 0 = none)
  function show(el, ev) {
    var lines = (el.getAttribute('data-tip') || '').split('\n');
    var html = '<div class="t">' + esc(lines[0]) + '</div>';
    for (var i = 1; i < lines.length; i++) {
      var p = lines[i].split('|');
      var sw = p[0] > 0 ? '<i style="background:var(--s' + p[0] + ')"></i>' : '';
      html += '<div class="row2"><span>' + sw + esc(p[1] || '') + '</span><strong>' + esc(p[2] || '') + '</strong></div>';
    }
    tip.innerHTML = html;
    tip.style.display = 'block';
    var w = tip.offsetWidth, h = tip.offsetHeight;
    var x = ev.clientX + 14, y = ev.clientY + 14;
    if (x + w > window.innerWidth - 8) x = ev.clientX - w - 14;
    if (y + h > window.innerHeight - 8) y = ev.clientY - h - 14;
    tip.style.left = x + 'px'; tip.style.top = y + 'px';
  }
  function hide() { tip.style.display = 'none'; }

  document.addEventListener('mousemove', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-tip]') : null;
    document.querySelectorAll('.viz-svg').forEach(function (svg) {
      if (!t || !svg.contains(t)) {
        var c = svg.querySelector('.cross'); if (c) c.style.display = 'none';
        var d = svg.querySelector('.dots'); if (d) d.innerHTML = '';
      }
    });
    if (!t) return hide();
    show(t, ev);
    var svg = t.closest('.viz-svg');
    if (svg && t.dataset.x) {
      var c = svg.querySelector('.cross');
      c.setAttribute('x1', t.dataset.x); c.setAttribute('x2', t.dataset.x); c.style.display = '';
      var g = svg.querySelector('.dots'); g.innerHTML = '';
      t.dataset.dots.split(',').forEach(function (cy, i) {
        var dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        dot.setAttribute('class', 'dot s' + (i + 1));
        dot.setAttribute('cx', t.dataset.x); dot.setAttribute('cy', cy); dot.setAttribute('r', 4);
        g.appendChild(dot);
      });
    }
  });
  document.addEventListener('mouseleave', hide);

  // Chart <-> Table switch
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('.viz-toggle') : null;
    if (!b) return;
    var card = b.closest('[data-viz]');
    var table = card.querySelector('.viz-table'), chart = card.querySelector('.viz-chart');
    var showTable = table.hidden;
    table.hidden = !showTable; chart.hidden = showTable;
    b.textContent = showTable ? 'Chart' : 'Table';
    b.setAttribute('aria-pressed', showTable ? 'true' : 'false');
    hide();
  });
})();
