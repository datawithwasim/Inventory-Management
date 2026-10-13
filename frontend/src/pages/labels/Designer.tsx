import { useCallback, useEffect, useMemo, useRef, useState, type PointerEvent as RPointerEvent, type ReactNode } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { Messages, Spinner } from '../../components/ui';
import { FONT_STACK, LabelView, SheetPreview, type El, type Page, type Template, type Values } from './render';

type Field = { key: string; label: string; group: string; for: string; sample: string };
type Preset = { key: string; name: string; w: number; h: number; page: Partial<Page> };
type Handle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w';
type Drag = { id: string; mode: 'move' | Handle; sx: number; sy: number; o: { x: number; y: number; w: number; h: number } };

const r2 = (n: number) => Math.round(n * 100) / 100;
const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v));
const KINDS: Record<El['type'], string> = { text: 'Text', barcode: 'Barcode', qr: 'QR code', box: 'Box / line', image: 'Logo' };
const KIND_ICON: Record<El['type'], string> = { text: 'bi-fonts', barcode: 'bi-upc', qr: 'bi-qr-code', box: 'bi-square', image: 'bi-image' };
const SNAP_MM = 0.8;

function newElement(type: El['type'], id: string, W: number, H: number, extra: Partial<El> = {}): El {
  const base = { id, type, x: 2, y: 2, rot: 0 } as El;   // the caller staggers it
  switch (type) {
    case 'text': return { ...base, w: r2(Math.min(30, W - 4)), h: 5, text: 'Text', font: 'Arial', size: 8, bold: false, italic: false, underline: false, upper: false, align: 'left', valign: 'top', color: '#000000', bg: '', lh: 1.1, ls: 0, maxlines: 0, fit: false, ...extra };
    case 'barcode': return { ...base, w: r2(Math.max(10, W - 4)), h: r2(Math.min(10, Math.max(5, H / 2.5))), sym: 'code128', value: '{code}', showtext: true, tsize: 6, font: 'Arial', color: '#000000', ...extra };
    case 'qr': { const s = r2(Math.max(6, Math.min(H - 4, W - 4, 18))); return { ...base, w: s, h: s, value: '{code}', ecc: 'M', ...extra }; }
    case 'box': return { ...base, w: 20, h: 10, border: 0.3, color: '#000000', fill: '', radius: 0, ...extra };
    default: return { ...base, w: 12, h: 8, src: 'logo', ...extra };
  }
}

function Num({ label, value, onChange, step = 0.5, min, max, suffix, width }: { label: string; value: number | ''; onChange: (v: number) => void; step?: number; min?: number; max?: number; suffix?: string; width?: number }) {
  return (
    <label className="d-block mb-2" style={{ width }}><span className="form-label small mb-0 d-block text-muted">{label}</span>
      <div className="input-group input-group-sm"><input type="number" className="form-control" step={step} min={min} max={max} value={value} onChange={(e) => e.target.value !== '' && onChange(Number(e.target.value))} />{suffix && <span className="input-group-text">{suffix}</span>}</div></label>
  );
}
function Toggle({ on, onClick, title, children }: { on: boolean; onClick: () => void; title: string; children: ReactNode }) {
  return <button type="button" title={title} className={`btn btn-sm ${on ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={onClick}>{children}</button>;
}
function Colour({ label, value, onChange, allowNone }: { label: string; value: string; onChange: (v: string) => void; allowNone?: boolean }) {
  return (
    <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">{label}</span>
      <div className="d-flex gap-1 align-items-center"><input type="color" className="form-control form-control-color form-control-sm" value={value || '#ffffff'} onChange={(e) => onChange(e.target.value)} />
        {allowNone && <button type="button" className="btn btn-sm btn-outline-secondary" disabled={!value} onClick={() => onChange('')}>None</button>}</div></label>
  );
}

export default function Designer() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const copy = sp.get('copy') ?? '';
  const src = useQuery({ queryKey: ['label-designer', id ?? 'new', copy], gcTime: 0, queryFn: async () => (await get(id ? `/labels/templates/${id}/edit` : `/labels/templates/create${copy ? `?copy=${encodeURIComponent(copy)}` : ''}`)).data });
  const [tpl, setTpl] = useState<Template | null>(null);
  const [sel, setSel] = useState<string | null>(null);
  const [tab, setTab] = useState<'element' | 'label'>('element');
  const [scale, setScale] = useState(10); const [snap, setSnap] = useState(true);
  const [guides, setGuides] = useState<{ x: number[]; y: number[] }>({ x: [], y: [] });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  const [fieldQ, setFieldQ] = useState('');
  const [preview, setPreview] = useState<{ type: 'items' | 'rolls'; label: string; values: Values | null }>({ type: 'items', label: '', values: null });
  const [pq, setPq] = useState(''); const [hits, setHits] = useState<any[]>([]);
  const past = useRef<Template[]>([]); const future = useRef<Template[]>([]); const lastKey = useRef(''); const lastAt = useRef(0);
  const saved = useRef(''); const [, bump] = useState(0);
  const area = useRef<HTMLDivElement>(null); const drag = useRef<Drag | null>(null);
  const scaleRef = useRef(scale); scaleRef.current = scale;
  const tplRef = useRef<Template | null>(null); tplRef.current = tpl;

  const d = src.data;
  useEffect(() => {
    if (!d || tpl) return;
    const t: Template = clone(d.template); t.id = d.template.id ?? null;
    setTpl(t); saved.current = id || !copy ? JSON.stringify(t) : '';
    if (t.for === 'rolls') setPreview((p) => ({ ...p, type: 'rolls' }));
    const w = area.current?.clientWidth ?? 800;
    setScale(Math.max(3, Math.min(16, Math.floor(((w - 120) / t.w) * 2) / 2)));
  }, [d, tpl, id, copy]);

  // sample values until a real item or roll is picked
  const sampleQ = useQuery({ queryKey: ['label-sample', preview.type], queryFn: async () => (await get(`/labels/data?type=${preview.type}&ids=`)).sample as Values });
  const values: Values = preview.values ?? sampleQ.data ?? {};

  const dirty = !!tpl && JSON.stringify(tpl) !== saved.current;
  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn); return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  /** Change the template; consecutive edits of the same thing (typing, nudging) become one undo step. */
  const change = useCallback((fn: (t: Template) => Template, key = '') => {
    setTpl((cur) => {
      if (!cur) return cur;
      const now = Date.now();
      if (!key || key !== lastKey.current || now - lastAt.current > 900) { past.current.push(clone(cur)); if (past.current.length > 80) past.current.shift(); future.current = []; }
      lastKey.current = key; lastAt.current = now;
      return fn(clone(cur));
    });
  }, []);
  const undo = () => setTpl((cur) => { const p = past.current.pop(); if (!p || !cur) return cur; future.current.push(clone(cur)); lastKey.current = ''; return p; });
  const redo = () => setTpl((cur) => { const n = future.current.pop(); if (!n || !cur) return cur; past.current.push(clone(cur)); lastKey.current = ''; return n; });

  const el = tpl?.elements.find((e) => e.id === sel) ?? null;
  const patch = (p: Partial<El>, key?: string) => change((t) => ({ ...t, elements: t.elements.map((e) => (e.id === sel ? { ...e, ...p } : e)) }), key ?? 'p:' + sel + ':' + Object.keys(p).join(','));
  const nextId = (t: Template) => 'e' + (Math.max(0, ...t.elements.map((e) => Number(e.id.replace(/\D/g, '')) || 0)) + 1);
  const add = (type: El['type'], extra: Partial<El> = {}) => {
    if (!tpl) return;
    if (tpl.elements.length >= d.maxElements) { setMsgs([{ type: 'warning', msg: `A label can hold at most ${d.maxElements} elements.` }]); return; }
    const nid = nextId(tpl);
    change((t) => { const n = t.elements.length; const e = newElement(type, nid, t.w, t.h, extra); e.x = r2(Math.min(2 + (n % 6) * 2, Math.max(0, t.w - e.w))); e.y = r2(Math.min(2 + (n % 6) * 3, Math.max(0, t.h - e.h))); return { ...t, elements: [...t.elements, e] }; });
    setSel(nid); setTab('element');
  };
  const remove = () => { if (!sel) return; change((t) => ({ ...t, elements: t.elements.filter((e) => e.id !== sel) })); setSel(null); };
  const duplicate = () => {
    if (!tpl || !el) return;
    const nid = nextId(tpl);
    change((t) => ({ ...t, elements: [...t.elements, { ...clone(el), id: nid, x: r2(el.x + 2), y: r2(el.y + 2) }] })); setSel(nid);
  };
  const reorder = (id2: string, dir: -1 | 1 | 'front' | 'back') => change((t) => {
    const a = [...t.elements]; const i = a.findIndex((e) => e.id === id2); if (i < 0) return t;
    const j = dir === 'front' ? a.length - 1 : dir === 'back' ? 0 : Math.max(0, Math.min(a.length - 1, i + dir));
    const [x] = a.splice(i, 1); a.splice(j, 0, x); return { ...t, elements: a };
  });
  const alignTo = (a: 'l' | 'c' | 'r' | 't' | 'm' | 'b') => {
    if (!tpl || !el) return;
    const p: Partial<El> = a === 'l' ? { x: 0 } : a === 'c' ? { x: r2((tpl.w - el.w) / 2) } : a === 'r' ? { x: r2(tpl.w - el.w) } : a === 't' ? { y: 0 } : a === 'm' ? { y: r2((tpl.h - el.h) / 2) } : { y: r2(tpl.h - el.h) };
    patch(p, '');
  };

  // ---- dragging and resizing on the canvas
  const move = useCallback((e: PointerEvent) => {
    const dr = drag.current; const t = tplRef.current; if (!dr || !t) return;
    const k = scaleRef.current;
    const dx = (e.clientX - dr.sx) / k, dy = (e.clientY - dr.sy) / k;
    const others = t.elements.filter((x) => x.id !== dr.id);
    const xs = [0, t.w / 2, t.w, ...others.flatMap((o) => [o.x, o.x + o.w / 2, o.x + o.w])];
    const ys = [0, t.h / 2, t.h, ...others.flatMap((o) => [o.y, o.y + o.h / 2, o.y + o.h])];
    const free = e.altKey || !snap;
    const near = (v: number, targets: number[]) => { let best: number | null = null; for (const g of targets) if (Math.abs(g - v) <= SNAP_MM && (best === null || Math.abs(g - v) < Math.abs(best - v))) best = g; return best; };
    const gx: number[] = [], gy: number[] = [];
    let { x, y, w, h } = dr.o;
    if (dr.mode === 'move') {
      x = dr.o.x + dx; y = dr.o.y + dy;
      if (!free) {
        const cand = [x, x + w / 2, x + w]; let sx: number | null = null;
        for (const c of cand) { const n = near(c, xs); if (n !== null && (sx === null || Math.abs(n - c) < Math.abs(sx))) { sx = n - c; gx.push(n); } }
        x = sx !== null ? x + sx : Math.round(x * 2) / 2;
        const candY = [y, y + h / 2, y + h]; let sy: number | null = null;
        for (const c of candY) { const n = near(c, ys); if (n !== null && (sy === null || Math.abs(n - c) < Math.abs(sy))) { sy = n - c; gy.push(n); } }
        y = sy !== null ? y + sy : Math.round(y * 2) / 2;
      }
    } else {
      const m = dr.mode;
      const sn = (v: number, targets: number[], g: number[]) => { if (free) return v; const n = near(v, targets); if (n !== null) { g.push(n); return n; } return Math.round(v * 2) / 2; };
      if (m.includes('e')) { const right = sn(dr.o.x + dr.o.w + dx, xs, gx); w = Math.max(1, right - dr.o.x); }
      if (m.includes('w')) { const left = sn(dr.o.x + dx, xs, gx); const r = dr.o.x + dr.o.w; x = Math.min(left, r - 1); w = r - x; }
      if (m.includes('s')) { const bottom = sn(dr.o.y + dr.o.h + dy, ys, gy); h = Math.max(1, bottom - dr.o.y); }
      if (m.includes('n')) { const top = sn(dr.o.y + dy, ys, gy); const b = dr.o.y + dr.o.h; y = Math.min(top, b - 1); h = b - y; }
    }
    setGuides({ x: gx, y: gy });
    setTpl((cur) => cur && { ...cur, elements: cur.elements.map((o) => (o.id === dr.id ? { ...o, x: r2(x), y: r2(y), w: r2(w), h: r2(h) } : o)) });
  }, [snap]);
  const stop = useCallback(() => { drag.current = null; setGuides({ x: [], y: [] }); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); }, [move]);
  const beginDrag = (e1: El, mode: 'move' | Handle, ev: RPointerEvent) => {
    ev.stopPropagation(); ev.preventDefault();
    setSel(e1.id); setTab('element');
    if (tpl) { past.current.push(clone(tpl)); future.current = []; lastKey.current = ''; }
    drag.current = { id: e1.id, mode, sx: ev.clientX, sy: ev.clientY, o: { x: e1.x, y: e1.y, w: e1.w, h: e1.h } };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', stop);
  };
  useEffect(() => () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); }, [move, stop]);

  // ---- keyboard
  useEffect(() => {
    const key = (e: KeyboardEvent) => {
      const t = e.target as HTMLElement;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable) return;
      const mod = e.ctrlKey || e.metaKey;
      if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); return; }
      if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); return; }
      if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicate(); return; }
      if (!sel) return;
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(); return; }
      if (e.key === 'Escape') { setSel(null); return; }
      const step = e.shiftKey ? 2 : 0.5;
      const dx = e.key === 'ArrowLeft' ? -step : e.key === 'ArrowRight' ? step : 0, dy = e.key === 'ArrowUp' ? -step : e.key === 'ArrowDown' ? step : 0;
      if (dx || dy) { e.preventDefault(); const cur = tplRef.current?.elements.find((x) => x.id === sel); if (cur) patch({ x: r2(cur.x + dx), y: r2(cur.y + dy) }, 'nudge:' + sel); }
    };
    window.addEventListener('keydown', key); return () => window.removeEventListener('keydown', key);
  });

  // ---- preview with a real item / roll
  useEffect(() => {
    if (pq.trim().length < 1) { setHits([]); return; }
    const h = setTimeout(async () => { const r = await get(`/labels?type=${preview.type}&q=${encodeURIComponent(pq)}`); setHits((r.data?.rows ?? []).slice(0, 8)); }, 250);
    return () => clearTimeout(h);
  }, [pq, preview.type]);
  const pick = async (row: any) => {
    const r = await get(`/labels/data?type=${preview.type}&ids=${row.id}`);
    setPreview({ ...preview, label: row.item, values: r.values?.[row.id] ?? null }); setHits([]); setPq('');
  };

  const save = async (asNew = false) => {
    if (!tpl) return;
    setSaving(true); setMsgs([]);
    const body = { name: tpl.name, for: tpl.for, w: tpl.w, h: tpl.h, page: tpl.page, elements: tpl.elements };
    const existing = !asNew && tpl.id && !tpl.builtin;
    const r = await post(existing ? `/labels/templates/${tpl.id}` : '/labels/templates', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? [{ type: 'danger', msg: 'Could not save.' }]); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['label-templates', 'labels'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    if (existing) { saved.current = JSON.stringify(tpl); bump((n) => n + 1); setMsgs([{ type: 'success', msg: 'Saved.' }]); }
    else { saved.current = JSON.stringify(tpl); nav(`/labels/templates/${r.id}/edit`, { replace: true }); }
  };
  const del = async () => {
    if (!tpl?.id || tpl.builtin || !confirm(`Delete "${tpl.name}"?`)) return;
    saved.current = JSON.stringify(tpl);
    await post(`/labels/templates/${tpl.id}/delete`); ['label-templates', 'labels'].forEach((k) => qc.invalidateQueries({ queryKey: [k] })); nav('/labels/templates');
  };

  const fields: Field[] = d?.fields ?? [];
  const usableFields = useMemo(() => fields.filter((f) => !tpl || tpl.for === 'any' || f.for === 'both' || f.for === tpl.for).filter((f) => !fieldQ || (f.label + f.key).toLowerCase().includes(fieldQ.toLowerCase())), [fields, tpl, fieldQ]);
  const groups = useMemo(() => { const g: Record<string, Field[]> = {}; usableFields.forEach((f) => (g[f.group] ??= []).push(f)); return g; }, [usableFields]);

  if (src.isLoading || !d || !tpl) return <Spinner />;
  const canEdit = tpl.builtin ? false : d.can.edit || (!tpl.id && d.can.create);
  const readOnly = !canEdit && !(d.can.create);
  const setPage = (p: Partial<Page>) => change((t) => ({ ...t, page: { ...t.page, ...p } }), 'page:' + Object.keys(p).join(','));
  const applyPreset = (key: string) => {
    const p: Preset | undefined = d.presets.find((x: Preset) => x.key === key); if (!p) return;
    change((t) => ({ ...t, w: p.w, h: p.h, page: { ...t.page, mode: 'roll', cols: 1, rows: 1, gap_x: 0, gap_y: 0, margin_top: null, margin_left: null, ...p.page } as Page }));
  };
  const insertField = (key: string) => { if (!el) return; if (el.type === 'text') patch({ text: (el.text ?? '') + `{${key}}` }); else if (el.type === 'barcode' || el.type === 'qr') patch({ value: (el.value ?? '') + `{${key}}` }); };
  const fieldSelect = (
    <select className="form-select form-select-sm mb-2" value="" onChange={(e) => e.target.value && insertField(e.target.value)}>
      <option value="">Insert a field…</option>{Object.entries(groups).map(([g, fs]) => <optgroup key={g} label={g}>{fs.map((f) => <option key={f.key} value={f.key}>{f.label}</option>)}</optgroup>)}
    </select>
  );
  const perSheet = tpl.page.mode === 'sheet' ? tpl.page.cols * tpl.page.rows : tpl.page.cols;

  return (
    <div style={{ margin: '-8px -4px 0' }}>
      <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
        <Link to="/labels/templates" className="btn btn-sm btn-outline-secondary"><i className="bi bi-arrow-left" /> Templates</Link>
        <input className="form-control form-control-sm fw-semibold" style={{ maxWidth: 280 }} value={tpl.name} maxLength={100} onChange={(e) => change((t) => ({ ...t, name: e.target.value }), 'name')} aria-label="Template name" />
        {tpl.builtin && <span className="badge text-bg-secondary">Ready-made · saving makes your own copy</span>}
        {dirty && <span className="badge text-bg-warning">Unsaved changes</span>}
        <div className="btn-group btn-group-sm ms-2"><button className="btn btn-outline-secondary" title="Undo (Ctrl+Z)" disabled={!past.current.length} onClick={undo}><i className="bi bi-arrow-counterclockwise" /></button>
          <button className="btn btn-outline-secondary" title="Redo (Ctrl+Y)" disabled={!future.current.length} onClick={redo}><i className="bi bi-arrow-clockwise" /></button></div>
        <div className="btn-group btn-group-sm"><button className="btn btn-outline-secondary" onClick={() => setScale((s) => Math.max(2, r2(s - 1)))}><i className="bi bi-zoom-out" /></button>
          <button className="btn btn-outline-secondary" disabled style={{ minWidth: 56 }}>{Math.round(scale * 10)}%</button>
          <button className="btn btn-outline-secondary" onClick={() => setScale((s) => Math.min(24, r2(s + 1)))}><i className="bi bi-zoom-in" /></button>
          <button className="btn btn-outline-secondary" title="Fit the label to the screen" onClick={() => setScale(Math.max(2, Math.min(24, Math.floor((((area.current?.clientWidth ?? 800) - 90) / tpl.w) * 2) / 2)))}>Fit</button></div>
        <div className="form-check form-switch mb-0"><input className="form-check-input" id="snap" type="checkbox" checked={snap} onChange={(e) => setSnap(e.target.checked)} /><label className="form-check-label small" htmlFor="snap" title="Hold Alt while dragging to move freely">Snap</label></div>
        <span className="ms-auto d-flex gap-2">
          {tpl.id && !tpl.builtin && d.can.delete && <button className="btn btn-sm btn-outline-danger" onClick={del}>Delete</button>}
          {(tpl.id || tpl.builtin) && d.can.create && <button className="btn btn-sm btn-outline-primary" disabled={saving} onClick={() => save(true)}>Save as new</button>}
          {!readOnly && <button className="btn btn-sm btn-primary" disabled={saving || (!d.can.edit && !!tpl.id && !tpl.builtin) || (tpl.builtin && !d.can.create)} onClick={() => save(false)}>{saving ? 'Saving…' : tpl.builtin ? 'Save as my template' : 'Save'}</button>}
        </span>
      </div>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="d-flex gap-3 align-items-start" style={{ minHeight: '70vh' }}>
        {/* left: add + fields + layers */}
        <div style={{ width: 230, flex: '0 0 230px' }} className="d-flex flex-column gap-3">
          <div className="card"><div className="card-header py-2">Add to the label</div><div className="card-body p-2"><div className="row g-2">
            {(['text', 'barcode', 'qr', 'box', 'image'] as El['type'][]).map((k) => <div className="col-6" key={k}><button className="btn btn-sm btn-outline-secondary w-100 text-start" onClick={() => add(k)}><i className={`bi ${KIND_ICON[k]} me-1`} />{KINDS[k]}</button></div>)}
            <div className="col-6"><button className="btn btn-sm btn-outline-secondary w-100 text-start" onClick={() => add('box', { w: 30, h: 0.3, border: 0, fill: '#000000' })}><i className="bi bi-dash-lg me-1" />Line</button></div>
          </div></div></div>
          <div className="card"><div className="card-header py-2">Fields <span className="text-muted small">click to add</span></div><div className="card-body p-2">
            <input className="form-control form-control-sm mb-2" placeholder="Find a field" value={fieldQ} onChange={(e) => setFieldQ(e.target.value)} />
            <div style={{ maxHeight: 260, overflow: 'auto' }}>
              {Object.entries(groups).map(([g, fs]) => <div key={g} className="mb-2"><div className="small fw-semibold text-muted">{g}</div>
                {fs.map((f) => <button key={f.key} className="btn btn-sm btn-link text-start d-block p-0 py-1 text-decoration-none" title={`Example: ${f.sample}`} onClick={() => add('text', { text: `{${f.key}}`, w: r2(Math.min(tpl.w - 4, 30)) })}>{f.label}</button>)}</div>)}
            </div>
          </div></div>
          <div className="card"><div className="card-header py-2">Layers</div><ul className="list-group list-group-flush">
            {[...tpl.elements].reverse().map((e) => (
              <li key={e.id} className={`list-group-item d-flex align-items-center gap-1 py-1 px-2 small ${e.id === sel ? 'active' : ''}`} style={{ cursor: 'pointer' }} onClick={() => { setSel(e.id); setTab('element'); }}>
                <i className={`bi ${KIND_ICON[e.type]}`} /><span className="text-truncate flex-grow-1">{e.type === 'text' ? e.text || '(empty)' : e.type === 'barcode' || e.type === 'qr' ? e.value : KINDS[e.type]}</span>
                <button className="btn btn-sm p-0 text-reset" title="Bring forward" onClick={(ev) => { ev.stopPropagation(); reorder(e.id, 1); }}><i className="bi bi-chevron-up" /></button>
                <button className="btn btn-sm p-0 text-reset" title="Send back" onClick={(ev) => { ev.stopPropagation(); reorder(e.id, -1); }}><i className="bi bi-chevron-down" /></button>
              </li>))}
            {!tpl.elements.length && <li className="list-group-item small text-muted">Nothing yet. Add text, a barcode or a field.</li>}
          </ul></div>
        </div>

        {/* centre: canvas */}
        <div className="flex-grow-1" style={{ minWidth: 0 }}>
          <div className="card mb-2"><div className="card-body py-2 d-flex flex-wrap align-items-center gap-2">
            <span className="small text-muted">Preview with</span>
            <div className="btn-group btn-group-sm"><button className={`btn btn-outline-secondary ${preview.type === 'items' ? 'active' : ''}`} onClick={() => setPreview({ type: 'items', label: '', values: null })}>Item</button>
              <button className={`btn btn-outline-secondary ${preview.type === 'rolls' ? 'active' : ''}`} onClick={() => setPreview({ type: 'rolls', label: '', values: null })}>Roll</button></div>
            <div className="position-relative" style={{ minWidth: 240 }}>
              <input className="form-control form-control-sm" placeholder={`Search a real ${preview.type === 'rolls' ? 'roll' : 'item'}…`} value={pq} onChange={(e) => setPq(e.target.value)} />
              {hits.length > 0 && <div className="list-group position-absolute shadow" style={{ zIndex: 20, top: '100%', left: 0, right: 0 }}>{hits.map((h) => <button key={h.id} className="list-group-item list-group-item-action small py-1" onClick={() => pick(h)}>{h.item} <span className="text-muted">{h.code}</span></button>)}</div>}
            </div>
            <span className="small text-muted">{preview.values ? <>Showing <strong>{preview.label}</strong> <button className="btn btn-sm btn-link p-0" onClick={() => setPreview({ ...preview, label: '', values: null })}>use sample</button></> : 'Sample data'}</span>
          </div></div>
          <div ref={area} className="p-4" style={{ background: '#e6e9ef', borderRadius: 8, minHeight: 360, overflow: 'auto', backgroundImage: 'radial-gradient(#cfd4dc 1px, transparent 1px)', backgroundSize: '16px 16px' }}>
            <div style={{ width: 'fit-content', margin: '0 auto' }}><LabelView w={tpl.w} h={tpl.h} elements={tpl.elements} values={values} scale={scale} selected={sel} onSelect={setSel} onStart={beginDrag} guides={guides} /></div>
          </div>
          <div className="small text-muted mt-2">Click an element to select it · drag to move · drag the squares to resize · arrow keys nudge (Shift = bigger steps) · Delete removes · Alt turns snapping off for one drag · Ctrl+Z undoes.</div>
        </div>

        {/* right: properties */}
        <div style={{ width: 300, flex: '0 0 300px' }}>
          <ul className="nav nav-tabs mb-2">
            <li className="nav-item"><button className={`nav-link ${tab === 'element' ? 'active' : ''}`} onClick={() => setTab('element')}>Element</button></li>
            <li className="nav-item"><button className={`nav-link ${tab === 'label' ? 'active' : ''}`} onClick={() => setTab('label')}>Label &amp; page</button></li>
          </ul>
          {tab === 'element' && !el && <div className="card"><div className="card-body text-muted small">Select an element on the label to change it, or add one from the left.</div></div>}
          {tab === 'element' && el && (
            <div className="card"><div className="card-body">
              <div className="d-flex justify-content-between align-items-center mb-2"><strong><i className={`bi ${KIND_ICON[el.type]} me-1`} />{KINDS[el.type]}</strong>
                <span className="btn-group btn-group-sm"><button className="btn btn-outline-secondary" title="Duplicate (Ctrl+D)" onClick={duplicate}><i className="bi bi-copy" /></button><button className="btn btn-outline-secondary" title="Bring to front" onClick={() => reorder(el.id, 'front')}><i className="bi bi-layer-forward" /></button><button className="btn btn-outline-secondary" title="Send to back" onClick={() => reorder(el.id, 'back')}><i className="bi bi-layer-backward" /></button><button className="btn btn-outline-danger" title="Delete" onClick={remove}><i className="bi bi-trash" /></button></span></div>

              {el.type === 'text' && <>
                <label className="form-label small mb-0 text-muted">Text — use fields like {'{item_name}'}</label>
                <textarea className="form-control form-control-sm mb-1" rows={3} value={el.text ?? ''} onChange={(e) => patch({ text: e.target.value }, 'text:' + el.id)} />
                {fieldSelect}
                <div className="row g-2"><div className="col-7"><label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Font</span>
                  <select className="form-select form-select-sm" value={el.font} onChange={(e) => patch({ font: e.target.value })}>{d.fonts.map((f: string) => <option key={f} style={{ fontFamily: FONT_STACK[f] }}>{f}</option>)}</select></label></div>
                  <div className="col-5"><Num label="Size" value={el.size ?? 8} step={0.5} min={3} max={120} suffix="pt" onChange={(v) => patch({ size: v })} /></div></div>
                <div className="d-flex gap-1 mb-2 flex-wrap">
                  <Toggle on={!!el.bold} title="Bold" onClick={() => patch({ bold: !el.bold })}><i className="bi bi-type-bold" /></Toggle>
                  <Toggle on={!!el.italic} title="Italic" onClick={() => patch({ italic: !el.italic })}><i className="bi bi-type-italic" /></Toggle>
                  <Toggle on={!!el.underline} title="Underline" onClick={() => patch({ underline: !el.underline })}><i className="bi bi-type-underline" /></Toggle>
                  <Toggle on={!!el.upper} title="CAPITALS" onClick={() => patch({ upper: !el.upper })}>Aa</Toggle>
                  <span className="vr" />
                  {(['left', 'center', 'right'] as const).map((a) => <Toggle key={a} on={el.align === a} title={`Align ${a}`} onClick={() => patch({ align: a })}><i className={`bi bi-text-${a}`} /></Toggle>)}
                  <span className="vr" />
                  {(['top', 'middle', 'bottom'] as const).map((a) => <Toggle key={a} on={el.valign === a} title={`Vertical: ${a}`} onClick={() => patch({ valign: a })}><i className={`bi bi-align-${a}`} /></Toggle>)}
                </div>
                <div className="row g-2"><div className="col-6"><Colour label="Text colour" value={el.color ?? '#000000'} onChange={(v) => patch({ color: v })} /></div><div className="col-6"><Colour label="Background" value={el.bg ?? ''} allowNone onChange={(v) => patch({ bg: v })} /></div>
                  <div className="col-6"><Num label="Line height" value={el.lh ?? 1.1} step={0.1} min={0.8} max={3} onChange={(v) => patch({ lh: v })} /></div><div className="col-6"><Num label="Letter spacing" value={el.ls ?? 0} step={0.1} min={-1} max={5} suffix="pt" onChange={(v) => patch({ ls: v })} /></div></div>
                <div className="form-check mb-1"><input className="form-check-input" id="fit" type="checkbox" checked={!!el.fit} onChange={(e) => patch({ fit: e.target.checked })} /><label className="form-check-label small" htmlFor="fit">Shrink the text to fit the box</label></div>
                {!el.fit && <Num label="Show at most … lines (0 = all)" value={el.maxlines ?? 0} step={1} min={0} max={20} onChange={(v) => patch({ maxlines: v })} />}
              </>}

              {(el.type === 'barcode' || el.type === 'qr') && <>
                {el.type === 'barcode' && <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Barcode type</span>
                  <select className="form-select form-select-sm" value={el.sym} onChange={(e) => patch({ sym: e.target.value })}>{Object.entries(d.symbols as Record<string, string>).filter(([k]) => k !== 'qr').map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></label>}
                <label className="form-label small mb-0 text-muted">{el.type === 'qr' ? 'What the QR code holds' : 'Barcode value'}</label>
                <input className="form-control form-control-sm mb-1" value={el.value ?? ''} onChange={(e) => patch({ value: e.target.value }, 'value:' + el.id)} />
                {fieldSelect}
                <div className="form-text mb-2">{el.type === 'qr' ? 'Any text — a code, a web link, or several fields together.' : el.sym === 'ean13' ? 'EAN-13 needs 12 or 13 digits (the check digit is added for you).' : el.sym === 'code39' ? 'Code 39 takes capitals, digits and - . space $ / + %.' : 'Code 128 takes any normal characters.'} Scan a printed sample before you print a large batch.</div>
                {el.type === 'barcode' && <>
                  <div className="form-check mb-2"><input className="form-check-input" id="st" type="checkbox" checked={!!el.showtext} onChange={(e) => patch({ showtext: e.target.checked })} /><label className="form-check-label small" htmlFor="st">Print the value under the bars</label></div>
                  {el.showtext && <div className="row g-2"><div className="col-6"><Num label="Text size" value={el.tsize ?? 6} step={0.5} min={3} max={40} suffix="pt" onChange={(v) => patch({ tsize: v })} /></div></div>}
                </>}
                {el.type === 'qr' && <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Error correction</span>
                  <select className="form-select form-select-sm" value={el.ecc ?? 'M'} onChange={(e) => patch({ ecc: e.target.value as 'L' | 'M' })}><option value="M">Medium — more robust</option><option value="L">Low — holds more text</option></select></label>}
              </>}

              {el.type === 'box' && <div className="row g-2">
                <div className="col-6"><Num label="Border" value={el.border ?? 0} step={0.1} min={0} max={5} suffix="mm" onChange={(v) => patch({ border: v })} /></div><div className="col-6"><Num label="Corner radius" value={el.radius ?? 0} step={0.5} min={0} max={50} suffix="mm" onChange={(v) => patch({ radius: v })} /></div>
                <div className="col-6"><Colour label="Border colour" value={el.color ?? '#000000'} onChange={(v) => patch({ color: v })} /></div><div className="col-6"><Colour label="Fill" value={el.fill ?? ''} allowNone onChange={(v) => patch({ fill: v })} /></div>
              </div>}
              {el.type === 'image' && <div className="form-text mb-2">Your company logo (Settings → Company profile). Resize the box to fit.</div>}

              <hr className="my-2" />
              <div className="row g-2">
                <div className="col-6"><Num label="X" value={el.x} suffix="mm" onChange={(v) => patch({ x: v })} /></div><div className="col-6"><Num label="Y" value={el.y} suffix="mm" onChange={(v) => patch({ y: v })} /></div>
                <div className="col-6"><Num label="Width" value={el.w} min={0.5} suffix="mm" onChange={(v) => patch({ w: Math.max(0.5, v) })} /></div><div className="col-6"><Num label="Height" value={el.h} min={0.5} suffix="mm" onChange={(v) => patch({ h: Math.max(0.5, v) })} /></div>
              </div>
              <div className="d-flex gap-2 align-items-end flex-wrap">
                <label className="d-block"><span className="form-label small mb-0 d-block text-muted">Rotate</span>
                  <select className="form-select form-select-sm" value={el.rot} onChange={(e) => patch({ rot: Number(e.target.value) })}>{[0, 90, 180, 270].map((r) => <option key={r} value={r}>{r}°</option>)}</select></label>
                <div className="btn-group btn-group-sm"><button className="btn btn-outline-secondary" title="Align left" onClick={() => alignTo('l')}><i className="bi bi-align-start" /></button><button className="btn btn-outline-secondary" title="Centre across" onClick={() => alignTo('c')}><i className="bi bi-align-center" /></button><button className="btn btn-outline-secondary" title="Align right" onClick={() => alignTo('r')}><i className="bi bi-align-end" /></button>
                  <button className="btn btn-outline-secondary" title="Align top" onClick={() => alignTo('t')}><i className="bi bi-align-top" /></button><button className="btn btn-outline-secondary" title="Centre down" onClick={() => alignTo('m')}><i className="bi bi-align-middle" /></button><button className="btn btn-outline-secondary" title="Align bottom" onClick={() => alignTo('b')}><i className="bi bi-align-bottom" /></button></div>
              </div>
            </div></div>
          )}

          {tab === 'label' && (
            <div className="card"><div className="card-body">
              <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Used for</span>
                <select className="form-select form-select-sm" value={tpl.for} onChange={(e) => change((t) => ({ ...t, for: e.target.value as Template['for'] }), 'for')}><option value="any">Items and rolls</option><option value="items">Items (SKU / price labels)</option><option value="rolls">Rolls (roll labels)</option></select></label>
              <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Start from a standard size</span>
                <select className="form-select form-select-sm" value="" onChange={(e) => e.target.value && applyPreset(e.target.value)}><option value="">Choose a size or sheet…</option>{d.presets.map((p: Preset) => <option key={p.key} value={p.key}>{p.name}</option>)}</select></label>
              <div className="row g-2"><div className="col-6"><Num label="Label width" value={tpl.w} min={10} max={300} suffix="mm" onChange={(v) => change((t) => ({ ...t, w: v }), 'w')} /></div><div className="col-6"><Num label="Label height" value={tpl.h} min={8} max={300} suffix="mm" onChange={(v) => change((t) => ({ ...t, h: v }), 'h')} /></div></div>
              <hr className="my-2" />
              <div className="btn-group btn-group-sm w-100 mb-2"><button className={`btn btn-outline-secondary ${tpl.page.mode === 'roll' ? 'active' : ''}`} onClick={() => setPage({ mode: 'roll', rows: 1 })}>Label roll</button><button className={`btn btn-outline-secondary ${tpl.page.mode === 'sheet' ? 'active' : ''}`} onClick={() => setPage({ mode: 'sheet' })}>Sheet of labels</button></div>
              {tpl.page.mode === 'roll' ? <div className="row g-2">
                <div className="col-6"><Num label="Labels across" value={tpl.page.cols} step={1} min={1} max={20} onChange={(v) => setPage({ cols: Math.max(1, Math.round(v)) })} /></div>
                <div className="col-6"><Num label="Gap between" value={tpl.page.gap_x} min={0} suffix="mm" onChange={(v) => setPage({ gap_x: v })} /></div>
                <div className="form-text">A roll printer prints one row of labels per page; the page is exactly the label size.</div>
              </div> : <>
                <label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Paper</span>
                  <select className="form-select form-select-sm" value={tpl.page.paper} onChange={(e) => { const p = (d.papers as { key: string; w: number; h: number }[]).find((x) => x.key === e.target.value); setPage(p ? { paper: p.key, paper_w: p.w, paper_h: p.h } : { paper: 'custom' }); }}>
                    {(d.papers as { key: string }[]).map((p) => <option key={p.key} value={p.key}>{p.key}</option>)}<option value="custom">Custom size</option></select></label>
                {tpl.page.paper === 'custom' && <div className="row g-2"><div className="col-6"><Num label="Paper width" value={tpl.page.paper_w} suffix="mm" onChange={(v) => setPage({ paper_w: v })} /></div><div className="col-6"><Num label="Paper height" value={tpl.page.paper_h} suffix="mm" onChange={(v) => setPage({ paper_h: v })} /></div></div>}
                <div className="row g-2">
                  <div className="col-6"><Num label="Columns" value={tpl.page.cols} step={1} min={1} max={20} onChange={(v) => setPage({ cols: Math.max(1, Math.round(v)) })} /></div><div className="col-6"><Num label="Rows" value={tpl.page.rows} step={1} min={1} max={60} onChange={(v) => setPage({ rows: Math.max(1, Math.round(v)) })} /></div>
                  <div className="col-6"><Num label="Gap across" value={tpl.page.gap_x} min={0} suffix="mm" onChange={(v) => setPage({ gap_x: v })} /></div><div className="col-6"><Num label="Gap down" value={tpl.page.gap_y} min={0} suffix="mm" onChange={(v) => setPage({ gap_y: v })} /></div>
                  <div className="col-6"><label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Top margin</span><div className="input-group input-group-sm"><input type="number" step={0.5} min={0} className="form-control" placeholder="centred" value={tpl.page.margin_top ?? ''} onChange={(e) => setPage({ margin_top: e.target.value === '' ? null : Number(e.target.value) })} /><span className="input-group-text">mm</span></div></label></div>
                  <div className="col-6"><label className="d-block mb-2"><span className="form-label small mb-0 d-block text-muted">Left margin</span><div className="input-group input-group-sm"><input type="number" step={0.5} min={0} className="form-control" placeholder="centred" value={tpl.page.margin_left ?? ''} onChange={(e) => setPage({ margin_left: e.target.value === '' ? null : Number(e.target.value) })} /><span className="input-group-text">mm</span></div></label></div>
                </div>
                <div className="form-text mb-2">Match these to your label sheet’s pack. Blank margins centre the labels on the paper.</div>
              </>}
              <div className="form-check mb-2"><input className="form-check-input" id="ol" type="checkbox" checked={tpl.page.outline} onChange={(e) => setPage({ outline: e.target.checked })} /><label className="form-check-label small" htmlFor="ol">Print an outline around each label</label></div>
              <div className="d-flex justify-content-center py-2"><SheetPreview w={tpl.w} h={tpl.h} page={tpl.page} /></div>
              <div className="small text-muted text-center">{perSheet} label{perSheet === 1 ? '' : 's'} per {tpl.page.mode === 'sheet' ? 'sheet' : 'row'}</div>
            </div></div>
          )}
        </div>
      </div>
    </div>
  );
}
