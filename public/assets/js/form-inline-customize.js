/* In-context form customization for admins: rename / hide / require / widen fields and add new ones,
   right on the real form. Reuses the Form designer's save contract (fetch the whole model, mutate, post). */
(function () {
  var btn = document.getElementById('ffCustomize');
  if (!btn) return;
  var entity = btn.dataset.entity, modelUrl = btn.dataset.model, saveUrl = btn.dataset.save, advUrl = btn.dataset.advanced;
  var data = null, widths = {}, types = {}, icons = {}, seq = 0, active = false, dirty = false;

  function el(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else if (k === 'html') e.innerHTML = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { if (c) e.appendChild(c); });
    return e;
  }
  function csrf() { var m = document.querySelector('input[name="_csrf"], meta[name="csrf"]'); return m ? (m.value || m.content) : ''; }
  function field(key) { return data.fields[key]; }
  function isChoice(t) { return t === 'dropdown' || t === 'radio' || t === 'multiselect'; }

  btn.addEventListener('click', function () {
    if (active) return exit(false);
    if (data) return enter();
    btn.disabled = true;
    fetch(modelUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      btn.disabled = false;
      if (!j.model) return;
      data = j.model; widths = j.widths || {}; types = j.types || {}; icons = j.icons || {};
      enter();
    }).catch(function () { btn.disabled = false; });
  });

  function enter() {
    active = true; dirty = false;
    document.body.classList.add('ff-customizing');
    btn.classList.add('active'); btn.innerHTML = '<i class="bi bi-x-lg"></i> Done';
    decorate();
    toolbar();
  }
  function exit(saved) {
    active = false;
    document.body.classList.remove('ff-customizing');
    btn.classList.remove('active'); btn.innerHTML = '<i class="bi bi-magic"></i> Customize';
    document.querySelectorAll('.ff-tag').forEach(function (n) { n.remove(); });
    var tb = document.getElementById('ffBar'); if (tb) tb.remove();
    var pop = document.getElementById('ffPop'); if (pop) pop.remove();
  }

  /* put a gear on every field wrapper the model knows */
  function decorate() {
    document.querySelectorAll('[data-ff]').forEach(function (w) {
      var key = w.dataset.ff.indexOf(entity + '.') === 0 ? w.dataset.ff.slice(entity.length + 1) : null;
      if (!key || !field(key) || w.querySelector('.ff-tag')) return;
      w.classList.add('ff-wrap');
      var f = field(key);
      var tag = el('button', { type: 'button', class: 'ff-tag', title: 'Customize this field' }, [el('i', { class: 'bi bi-pencil-fill' })]);
      tag.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); popover(w, key); });
      w.appendChild(tag);
      paint(w, key);
    });
  }
  function paint(w, key) {
    var f = field(key);
    w.classList.toggle('ff-hidden', f.show === false);
    var badge = w.querySelector('.ff-badge'); if (badge) badge.remove();
    if (f.show === false) w.appendChild(el('span', { class: 'ff-badge', text: 'Hidden' }));
  }

  function popover(w, key) {
    var old = document.getElementById('ffPop'); if (old) old.remove();
    var f = field(key);
    var rows = [];
    // rename
    var lab = el('input', { class: 'form-control form-control-sm', maxlength: '60', value: f.custom ? f.label : (f.custom_label || f.label) });
    lab.placeholder = f.custom ? 'Field name' : f.label;
    lab.addEventListener('input', function () { if (f.custom) f.label = lab.value; else f.custom_label = lab.value; dirty = true; });
    rows.push(el('div', { class: 'mb-2' }, [el('label', { class: 'ff-lbl', text: 'Label' }), lab]));
    // width
    var wsel = el('select', { class: 'form-select form-select-sm' });
    Object.keys(widths).forEach(function (k) { var o = el('option', { value: k, text: widths[k] }); if ((f.w || '') === k) o.selected = true; wsel.appendChild(o); });
    wsel.addEventListener('change', function () { f.w = wsel.value; dirty = true; });
    rows.push(el('div', { class: 'mb-2' }, [el('label', { class: 'ff-lbl', text: 'Width' }), wsel]));
    // choices for custom choice fields
    if (f.custom && isChoice(f.type)) {
      var opt = el('textarea', { class: 'form-control form-control-sm', rows: '3' }); opt.value = f.options || '';
      opt.addEventListener('input', function () { f.options = opt.value; dirty = true; });
      rows.push(el('div', { class: 'mb-2' }, [el('label', { class: 'ff-lbl', text: 'Choices (one per line)' }), opt]));
    }
    // toggles
    if (f.optional && f.type !== 'checkbox') rows.push(toggle('Required', !!f.req, function (v) { f.req = v; dirty = true; }));
    if (f.optional || f.custom) rows.push(toggle('Show on the form', f.show !== false, function (v) { f.show = v; dirty = true; paint(w, key); }));
    else rows.push(el('div', { class: 'ff-note', text: 'This is an essential field — it stays on the form.' }));

    var pop = el('div', { id: 'ffPop', class: 'ff-pop card shadow' }, [
      el('div', { class: 'card-body p-2' }, rows.concat([el('button', { type: 'button', class: 'btn btn-sm btn-primary w-100 mt-1', text: 'Done' })]))
    ]);
    pop.querySelector('.btn-primary').addEventListener('click', function () { pop.remove(); });
    document.body.appendChild(pop);
    var r = w.getBoundingClientRect();
    pop.style.top = (window.scrollY + r.top) + 'px';
    pop.style.left = Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - 300) + 'px';
    lab.focus();
    setTimeout(function () {
      document.addEventListener('click', function close(e) { if (!pop.contains(e.target)) { pop.remove(); document.removeEventListener('click', close); } });
    }, 0);
  }
  function toggle(label, on, cb) {
    var inp = el('input', { type: 'checkbox', class: 'form-check-input' }); inp.checked = on;
    inp.addEventListener('change', function () { cb(inp.checked); });
    return el('label', { class: 'form-check ff-row' }, [inp, el('span', { class: 'form-check-label', text: label })]);
  }

  function toolbar() {
    var bar = el('div', { id: 'ffBar', class: 'ff-bar shadow' });
    if (data.customFields) {
      var add = el('button', { type: 'button', class: 'btn btn-sm btn-primary', html: '<i class="bi bi-plus-lg"></i> Add field' });
      add.addEventListener('click', addField);
      bar.appendChild(add);
    }
    var adv = el('a', { class: 'btn btn-sm btn-outline-secondary', href: advUrl, html: '<i class="bi bi-sliders"></i> Advanced layout' });
    var save = el('button', { type: 'button', class: 'btn btn-sm btn-success', html: '<i class="bi bi-check-lg"></i> Save' });
    save.addEventListener('click', doSave);
    var cancel = el('button', { type: 'button', class: 'btn btn-sm btn-link text-muted', text: 'Cancel' });
    cancel.addEventListener('click', function () { if (!dirty || confirm('Discard your changes?')) exit(false); });
    bar.appendChild(el('span', { class: 'ff-bar-title', html: '<i class="bi bi-magic"></i> Customizing this form' }));
    bar.appendChild(adv); bar.appendChild(cancel); bar.appendChild(save);
    document.body.appendChild(bar);
  }

  function addField() {
    var key = 'cf:new' + (++seq);
    var tsel = el('select', { class: 'form-select form-select-sm mb-2' });
    Object.keys(types).forEach(function (k) { tsel.appendChild(el('option', { value: k, text: types[k] })); });
    var name = el('input', { class: 'form-control form-control-sm mb-2', maxlength: '80', placeholder: 'Field name (e.g. Lot number)' });
    var pop = el('div', { id: 'ffPop', class: 'ff-pop card shadow' }, [el('div', { class: 'card-body p-2' }, [
      el('div', { class: 'ff-lbl', text: 'New field' }), name, el('label', { class: 'ff-lbl', text: 'Type' }), tsel,
      el('button', { type: 'button', class: 'btn btn-sm btn-primary w-100', text: 'Add to form' })
    ])]);
    pop.querySelector('.btn-primary').addEventListener('click', function () {
      var label = name.value.trim(); if (!label) { name.focus(); return; }
      var t = tsel.value;
      data.fields[key] = { key: key, label: label, custom: true, isNew: true, type: t, options: isChoice(t) ? 'Option 1\nOption 2' : '', show: true, req: false, inList: false, unique: false, w: '', help: '' };
      data.sections[data.sections.length - 1].fields.push(key);
      dirty = true; pop.remove();
      flash('“' + label + '” will appear once you press Save.');
    });
    document.body.appendChild(pop);
    pop.style.top = (window.scrollY + 120) + 'px'; pop.style.left = (window.scrollX + 20) + 'px';
    name.focus();
    setTimeout(function () { document.addEventListener('click', function close(e) { if (!pop.contains(e.target) && !e.target.closest('#ffBar')) { pop.remove(); document.removeEventListener('click', close); } }); }, 0);
  }

  function flash(msg) {
    var n = el('div', { class: 'ff-flash', text: msg });
    document.body.appendChild(n);
    setTimeout(function () { n.remove(); }, 2600);
  }

  function doSave() {
    var o = { style: data.style, lines: data.lines, deleted: [], sections: data.sections.map(function (s) { return { title: s.title, cols: s.cols, fields: s.fields.slice() }; }), fields: {} };
    Object.keys(data.fields).forEach(function (k) {
      var x = data.fields[k];
      o.fields[k] = { w: x.w || '', help: x.help || '', show: x.show !== false, req: !!x.req };
      if (x.custom) { o.fields[k].name = x.label; o.fields[k].type = x.type; o.fields[k].options = x.options || ''; o.fields[k].inList = !!x.inList; o.fields[k].unique = !!x.unique; if (x.isNew) o.fields[k].isNew = true; }
      else o.fields[k].custom_label = x.custom_label || '';
    });
    var payload = {}; payload[entity] = o;
    var body = new URLSearchParams(); body.set('_csrf', csrf()); body.set('ajax', '1'); body.set('current', entity); body.set('payload', JSON.stringify(payload));
    var save = document.querySelector('#ffBar .btn-success'); if (save) { save.disabled = true; save.innerHTML = 'Saving…'; }
    fetch(saveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.ok) { dirty = false; location.reload(); } else { if (save) { save.disabled = false; save.innerHTML = 'Save'; } alert('Could not save. Please try again.'); } })
      .catch(function () { if (save) { save.disabled = false; save.innerHTML = 'Save'; } alert('Could not save. Please try again.'); });
  }
})();
