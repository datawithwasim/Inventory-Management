/* Point of sale: scan, cart, pay. Plain JS, no dependencies. */
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var me = document.currentScript, urls = { one: me.dataset.one, search: me.dataset.search, checkout: me.dataset.checkout };
  var csrf = document.querySelector('meta[name=csrf]').content;
  var cart = [], amountTouched = false, timer = null;
  var scan = $('scan'), tbody = $('cart'), msg = $('msg');

  var f = function (x) { return parseFloat(x) || 0; };
  var r2 = function (x) { return Math.round(x * 100) / 100; };
  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function say(text, kind) { msg.className = 'alert alert-' + (kind || 'danger') + (text ? '' : ' d-none'); msg.textContent = text || ''; }
  var qs = function () { return 'customer=' + encodeURIComponent($('customer').value) + '&warehouse=' + encodeURIComponent($('wh').value); };

  function lineTotal(l) {
    var g = r2(l.qty * l.price), d = r2(g * l.disc / 100), net = r2(g - d), x = r2(net * l.tax / 100);
    return { g: g, d: d, net: net, x: x, t: r2(net + x) };
  }
  function totals() {
    var sum = 0, tax = 0, disc = 0;
    cart.forEach(function (l) { var t = lineTotal(l); sum += t.t; tax += t.x; disc += t.d; });
    return { total: r2(sum), tax: r2(tax), disc: r2(disc) };
  }

  function render() {
    tbody.innerHTML = '';
    cart.forEach(function (l, i) {
      var qty = mk('input', { type: 'number', step: l.dec ? '0.001' : '1', min: '0', class: 'form-control form-control-sm text-end', style: 'width:90px' }); qty.value = l.qty;
      var price = mk('input', { type: 'number', step: '0.01', min: '0', class: 'form-control form-control-sm text-end', style: 'width:90px' }); price.value = l.price;
      var disc = mk('input', { type: 'number', step: '0.01', min: '0', max: '100', class: 'form-control form-control-sm text-end', style: 'width:65px' }); disc.value = l.disc || '';
      var tot = mk('td', { class: 'text-end', text: lineTotal(l).t.toFixed(2) });
      var upd = function () { l.qty = f(qty.value); l.price = f(price.value); l.disc = f(disc.value); tot.textContent = lineTotal(l).t.toFixed(2); paintTotals(); };
      [qty, price, disc].forEach(function (e) { e.addEventListener('input', upd); });
      var rm = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: '×' });
      rm.onclick = function () { cart.splice(i, 1); render(); };
      var label = mk('td', {}, [mk('div', { text: l.label }), mk('div', { class: 'small text-muted', text: (l.tb ? 'Roll item · enter the length · ' : '') + (l.bundle ? 'Set · ' : '') + (l.available != null ? 'free ' + parseFloat(l.available) + ' ' + l.unit : '') })]);
      var tr = mk('tr', {}, [label, mk('td', {}, [qty]), mk('td', {}, [price]), mk('td', {}, [disc]), tot, mk('td', {}, [rm])]);
      tr._qty = qty;
      tbody.appendChild(tr);
    });
    paintTotals();
  }
  function paintTotals() {
    var t = totals();
    $('tTotal').textContent = t.total.toFixed(2); $('tTax').textContent = t.tax.toFixed(2); $('tDisc').textContent = t.disc.toFixed(2);
    if (!amountTouched) $('amount').value = t.total.toFixed(2);
    var change = r2(f($('amount').value) - t.total);
    $('change').textContent = (change >= 0 ? 'Change: ' : 'Balance due: ') + Math.abs(change).toFixed(2);
    $('pay').disabled = !cart.length;
  }

  function add(v) {
    say('');
    var ex = cart.filter(function (l) { return l.id === v.id; })[0];
    if (ex && !v.track_batch) { ex.qty = r2(ex.qty + 1); }
    else if (!ex) {
      cart.push({ id: v.id, label: v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')', unit: v.unit, dec: !!v.allow_decimal, tb: !!v.track_batch, bundle: !!v.is_bundle,
        qty: 1, price: v.price, disc: v.discount || 0, tax: v.tax_rate || 0, available: v.available });
    }
    render();
    var row = tbody.rows[ex ? cart.indexOf(ex) : cart.length - 1];
    if (row && (v.track_batch || v.allow_decimal) && !ex) { row._qty.focus(); row._qty.select(); } else scan.focus();
  }

  function find(code) {
    fetch(urls.one + '?code=' + encodeURIComponent(code) + '&' + qs(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (v) {
      if (v) { add(v); scan.value = ''; return; }
      fetch(urls.search + '?q=' + encodeURIComponent(code) + '&' + qs(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        var list = $('results'); list.innerHTML = '';
        rows.forEach(function (x) {
          var b = mk('button', { type: 'button', class: 'list-group-item list-group-item-action py-1', text: x.item_name + (x.name ? ' — ' + x.name : '') + ' (' + x.sku + ') · ' + x.price.toFixed(2) + (x.available != null ? ' · free ' + parseFloat(x.available) : '') });
          b.onclick = function () { add(x); list.hidden = true; scan.value = ''; };
          list.appendChild(b);
        });
        if (!rows.length) list.appendChild(mk('div', { class: 'list-group-item text-muted', text: 'Nothing found for "' + code + '"' }));
        list.hidden = false;
      });
    });
  }

  scan.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var code = scan.value.trim(); if (code) find(code);
  });
  scan.addEventListener('input', function () {
    clearTimeout(timer);
    var q = scan.value.trim();
    if (q.length < 2) { $('results').hidden = true; return; }
    timer = setTimeout(function () {
      fetch(urls.search + '?q=' + encodeURIComponent(q) + '&' + qs(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        if (scan.value.trim() !== q) return;
        var list = $('results'); list.innerHTML = '';
        rows.forEach(function (x) {
          var b = mk('button', { type: 'button', class: 'list-group-item list-group-item-action py-1', text: x.item_name + (x.name ? ' — ' + x.name : '') + ' (' + x.sku + ') · ' + x.price.toFixed(2) + (x.available != null ? ' · free ' + parseFloat(x.available) : '') });
          b.onclick = function () { add(x); list.hidden = true; scan.value = ''; };
          list.appendChild(b);
        });
        list.hidden = !rows.length;
      });
    }, 250);
  });
  document.addEventListener('click', function (e) { if (!$('results').contains(e.target) && e.target !== scan) $('results').hidden = true; });

  // New customer → re-price the cart with that customer's price list / group discount.
  $('customer').addEventListener('change', function () {
    cart.forEach(function (l) {
      fetch(urls.one + '?id=' + l.id + '&' + qs(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (v) { if (v) { l.price = v.price; l.disc = v.discount || 0; render(); } });
    });
  });
  $('amount').addEventListener('input', function () { amountTouched = true; paintTotals(); });
  $('clear').onclick = function () { cart = []; amountTouched = false; say(''); render(); scan.focus(); };

  function checkout() {
    if (!cart.length || $('pay').disabled) return;
    $('pay').disabled = true; say('');
    var fd = new FormData();
    fd.append('_csrf', csrf); fd.append('customer_id', $('customer').value); fd.append('warehouse_id', $('wh').value);
    fd.append('method', $('method').value); fd.append('amount', $('amount').value); fd.append('reference', $('reference').value);
    cart.forEach(function (l, i) {
      fd.append('lines[' + i + '][variant_id]', l.id); fd.append('lines[' + i + '][qty]', l.qty); fd.append('lines[' + i + '][unit_price]', l.price);
      fd.append('lines[' + i + '][discount_pct]', l.disc); fd.append('lines[' + i + '][tax_rate]', l.tax);
    });
    fetch(urls.checkout, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.ok) { window.location = res.url; } else { say(res.error); $('pay').disabled = false; }
    }).catch(function () { say('Could not reach the server. Try again.'); $('pay').disabled = false; });
  }
  $('pay').onclick = checkout;
  document.addEventListener('keydown', function (e) { if (e.key === 'F9') { e.preventDefault(); checkout(); } });
  render(); scan.focus();
})();
