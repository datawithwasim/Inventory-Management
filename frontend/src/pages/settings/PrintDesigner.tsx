import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { Messages, Spinner } from '../../components/ui';
import DocView from '../../components/DocView';
import { normalise, paperMm, sample, SIZES, type Block, type Cfg, type DocData, type DocKind, type Head } from '../../lib/printdoc';

/* Designer for printed documents: paper, letterhead and the ordered sections of the document, tried live on the latest real document (or a sample). */

const FONTS = ['Arial', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Times New Roman', 'Georgia', 'Courier New'];
const PRINT_PATH: Record<DocKind, (id: number) => string> = { invoice: (i) => `/sales/invoices/${i}/print`, delivery_note: (i) => `/sales/deliveries/${i}/print`, purchase_order: (i) => `/purchase/orders/${i}/print` };
const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v));
const BLOCK_NAME: Record<string, string> = { parties: 'Parties', items: 'Items table', totals: 'Totals', notes: 'Document note', bank: 'Bank details', terms: 'Terms & conditions', signature: 'Signatures', footer: 'Footer line' };

export default function PrintDesigner() {
  const [sp] = useSearchParams(); const doc = sp.get('doc') ?? 'invoice'; const id = sp.get('id') ?? '';
  const [version, setVersion] = useState(0);
  const res = useQuery({ queryKey: ['print-design', doc, id, version], gcTime: 0, staleTime: 0, queryFn: async () => (await get(`/settings/templates/design?doc=${doc}${id ? `&id=${id}` : ''}`)).data });
  useEffect(() => { document.body.classList.add('dz-page'); return () => document.body.classList.remove('dz-page'); }, []);
  if (res.isLoading || !res.data) return <Spinner />;
  return <Inner key={`${doc}-${res.data.id ?? 'new'}-${version}`} d={res.data} reload={() => setVersion((v) => v + 1)} />;
}

/* ---- small form pieces on the dark panel ---- */
const Lbl = ({ children }: { children: ReactNode }) => <div className="dz-lbl">{children}</div>;
const Sw = ({ label, on, set }: { label: string; on: boolean; set: (v: boolean) => void }) => <div className="dz-row"><span>{label}</span><label className="dz-sw"><input type="checkbox" checked={on} onChange={(e) => set(e.target.checked)} /><i /></label></div>;
const Seg = ({ value, opts, set }: { value: string; opts: [string, string][]; set: (v: string) => void }) => <div className="dz-seg" style={{ display: 'flex' }}>{opts.map(([v, l]) => <button type="button" key={v} className={value === v ? 'on' : ''} style={{ flex: 1 }} onClick={() => set(v)}>{l}</button>)}</div>;
const Num = ({ value, set, min, max, step = 1 }: { value: number; set: (v: number) => void; min: number; max: number; step?: number }) => <input type="number" className="dz-in" value={value} min={min} max={max} step={step} onChange={(e) => set(Number(e.target.value))} />;

function Inner({ d, reload }: { d: any; reload: () => void }) {
  const nav = useNavigate(); const doc = d.doc as DocKind; const spec = d.spec;
  const backTo = `/settings/templates?doc=${doc}`;
  const [cfg, setCfg] = useState<Cfg>(() => clone(d.cfg)); const initial = useRef(JSON.stringify(d.cfg));
  const [name, setName] = useState<string>(d.name); const [nameInit] = useState<string>(d.name);
  const [makeDefault, setMakeDefault] = useState<boolean>(!!d.isDefault);
  const [tab, setTab] = useState<'page' | 'header' | 'sections'>('sections');
  const [open, setOpen] = useState<string | null>('items');
  const [src, setSrc] = useState<'real' | 'sample'>(d.latest ? 'real' : 'sample');
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  const hist = useRef<string[]>([initial.current]); const [, bump] = useState(0); const typed = useRef<string | null>(null);
  const dirty = JSON.stringify(cfg) !== initial.current || name !== nameInit || makeDefault !== !!d.isDefault;
  const dirtyRef = useRef(dirty); dirtyRef.current = dirty;

  const edit = (fn: (c: Cfg) => void, tag?: string) => {
    const c = clone(cfg); fn(c); const now = JSON.stringify(c); const h = hist.current;
    if (tag && typed.current === tag && h.length > 1) h[h.length - 1] = now; else if (h[h.length - 1] !== now) h.push(now);
    typed.current = tag ?? null; if (h.length > 80) h.shift(); setCfg(c); bump((n) => n + 1);
  };
  const undo = () => { const h = hist.current; if (h.length < 2) return; h.pop(); setCfg(JSON.parse(h[h.length - 1])); typed.current = null; bump((n) => n + 1); };
  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => { if (dirtyRef.current) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn); return () => window.removeEventListener('beforeunload', warn);
  }, []);

  // what the preview is drawn with: the latest real document of this kind, or a made-up one
  const real = useQuery({ queryKey: ['print-preview', doc, d.latest], enabled: !!d.latest, gcTime: 0, queryFn: async () => (await get(PRINT_PATH[doc](d.latest))).data });
  const data: DocData = useMemo(() => (src === 'real' && real.data ? normalise(doc, real.data) : sample(doc)), [src, real.data, doc]);
  const head: Head = d.head;

  // paper scaled to the width available
  const box = useRef<HTMLDivElement>(null); const [avail, setAvail] = useState(700);
  useEffect(() => { const el = box.current; if (!el) return; const ro = new ResizeObserver(() => setAvail(el.clientWidth - 40)); ro.observe(el); setAvail(el.clientWidth - 40); return () => ro.disconnect(); }, []);
  const [pw, ph] = paperMm(cfg.page); const pxW = pw * 3.7795; const zoom = Math.min(1.2, Math.max(0.3, avail / pxW));

  const block = (bid: string) => cfg.blocks.find((b) => b.id === bid)!;
  const upd = (bid: string, fn: (b: any) => void, tag?: string) => edit((c) => fn(c.blocks.find((b) => b.id === bid)!), tag ? `${bid}:${tag}` : undefined);
  const move = (i: number, dir: number) => edit((c) => { const j = i + dir; if (j < 0 || j >= c.blocks.length) return; [c.blocks[i], c.blocks[j]] = [c.blocks[j], c.blocks[i]]; });
  const swap = (arr: any[], i: number, dir: number) => { const j = i + dir; if (j < 0 || j >= arr.length) return; [arr[i], arr[j]] = [arr[j], arr[i]]; };
  const addText = () => {
    const nid = 'text:' + Math.random().toString(36).slice(2, 8); const n = cfg.blocks.filter((b) => b.type === 'text').length;
    if (n >= 8) { alert('Up to 8 text blocks.'); return; }
    edit((c) => { const at = c.blocks.findIndex((b) => b.type === 'signature'); c.blocks.splice(at < 0 ? c.blocks.length : at, 0, { id: nid, type: 'text', show: 1, title: 'Heading', text: 'Your text here. You can use {no}, {date} and {party}.', align: 'left', boxed: 0, size: 10 }); });
    setOpen(nid); setTab('sections');
  };

  const save = async (close: boolean) => {
    setSaving(true); setMsgs([]);
    const r = await post('/settings/templates/save', { doc, id: d.id ?? '', name: name.trim(), cfg, make_default: makeDefault ? 1 : '' });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    initial.current = JSON.stringify(cfg);   // saved: leaving needs no warning
    const m = String(r.redirect ?? '').match(/[?&]id=(\d+)/);
    if (close) nav(backTo); else if (m) nav(`/settings/templates/design?doc=${doc}&id=${m[1]}`, { replace: true }); else reload();
  };
  const leave = (e: React.MouseEvent) => { if (dirty && !confirm('You have unsaved changes. Leave without saving?')) e.preventDefault(); };
  const pick = (bid: string) => { setTab('sections'); setOpen(bid); setTimeout(() => document.getElementById('blk-' + bid)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 30); };

  /* ---------- the controls of one section ---------- */
  const options = (b: Block): ReactNode => {
    switch (b.type) {
      case 'parties': return <>
        <Lbl>Columns</Lbl><Seg value={String(b.cols)} opts={[['1', 'One'], ['2', 'Two']]} set={(v) => upd(b.id, (x) => { x.cols = Number(v); })} />
        {b.items.map((p, n) => <div key={p.k} className="d-flex gap-2 align-items-center mt-2"><label className="dz-sw"><input type="checkbox" checked={!!p.show} onChange={(e) => upd(b.id, (x) => { x.items[n].show = e.target.checked ? 1 : 0; })} /><i /></label>
          <input className="dz-in" value={p.label} maxLength={40} onChange={(e) => upd(b.id, (x) => { x.items[n].label = e.target.value; }, 'p' + n)} /></div>)}</>;
      case 'items': return <>
        <Lbl>Table style</Lbl><Seg value={b.style} opts={[['lines', 'Lines'], ['grid', 'Grid'], ['plain', 'Plain']]} set={(v) => upd(b.id, (x) => { x.style = v; })} />
        <Lbl>Heading row</Lbl><Seg value={b.head_bg} opts={[['light', 'Light'], ['accent', 'Colour'], ['none', 'None']]} set={(v) => upd(b.id, (x) => { x.head_bg = v; })} />
        <Sw label="Row numbers" on={!!b.row_no} set={(v) => upd(b.id, (x) => { x.row_no = v ? 1 : 0; })} />
        <Sw label="Striped rows" on={!!b.zebra} set={(v) => upd(b.id, (x) => { x.zebra = v ? 1 : 0; })} />
        <Sw label="Tight rows (more lines per page)" on={!!b.compact} set={(v) => upd(b.id, (x) => { x.compact = v ? 1 : 0; })} />
        <Lbl>Columns · label · align · width %</Lbl>
        {b.columns.map((c, n) => (
          <div key={c.k} className="d-flex gap-1 align-items-center mt-2">
            <label className="dz-sw flex-shrink-0"><input type="checkbox" checked={!!c.show} onChange={(e) => upd(b.id, (x) => { x.columns[n].show = e.target.checked ? 1 : 0; })} /><i /></label>
            <input className="dz-in" style={{ minWidth: 0 }} value={c.label} maxLength={40} onChange={(e) => upd(b.id, (x) => { x.columns[n].label = e.target.value; }, 'c' + n)} />
            <select className="dz-in" style={{ width: 62, padding: '.45rem .2rem' }} value={c.align} onChange={(e) => upd(b.id, (x) => { x.columns[n].align = e.target.value; })}><option value="left">L</option><option value="center">C</option><option value="right">R</option></select>
            <input type="number" className="dz-in" style={{ width: 62, padding: '.45rem .3rem' }} min={0} max={60} value={c.w || ''} placeholder="auto" onChange={(e) => upd(b.id, (x) => { x.columns[n].w = Number(e.target.value) || 0; }, 'w' + n)} />
            <button type="button" className="dz-x" aria-label="Move up" onClick={() => upd(b.id, (x) => swap(x.columns, n, -1))}><i className="bi bi-arrow-up" /></button>
            <button type="button" className="dz-x" aria-label="Move down" onClick={() => upd(b.id, (x) => swap(x.columns, n, 1))}><i className="bi bi-arrow-down" /></button>
          </div>))}</>;
      case 'totals': return <>
        <Lbl>Width of the totals box: {b.width}%</Lbl><input type="range" min={25} max={100} value={b.width} className="w-100" onChange={(e) => upd(b.id, (x) => { x.width = Number(e.target.value); }, 'width')} />
        <Lbl>Side</Lbl><Seg value={b.side} opts={[['left', 'Left'], ['right', 'Right']]} set={(v) => upd(b.id, (x) => { x.side = v; })} />
        <Sw label="Amount in words" on={!!b.words} set={(v) => upd(b.id, (x) => { x.words = v ? 1 : 0; })} />
        {b.words ? <><Lbl>Words start with</Lbl><input className="dz-in" value={b.words_prefix} maxLength={20} onChange={(e) => upd(b.id, (x) => { x.words_prefix = e.target.value; }, 'wp')} /></> : null}
        <Lbl>Rows</Lbl>
        {b.rows.map((r, n) => <div key={r.k} className="d-flex gap-1 align-items-center mt-2"><label className="dz-sw flex-shrink-0"><input type="checkbox" checked={!!r.show} onChange={(e) => upd(b.id, (x) => { x.rows[n].show = e.target.checked ? 1 : 0; })} /><i /></label>
          <input className="dz-in" value={r.label} maxLength={40} onChange={(e) => upd(b.id, (x) => { x.rows[n].label = e.target.value; }, 'r' + n)} />
          <button type="button" className="dz-x" aria-label="Move up" onClick={() => upd(b.id, (x) => swap(x.rows, n, -1))}><i className="bi bi-arrow-up" /></button>
          <button type="button" className="dz-x" aria-label="Move down" onClick={() => upd(b.id, (x) => swap(x.rows, n, 1))}><i className="bi bi-arrow-down" /></button></div>)}</>;
      case 'notes': case 'bank': return <><Lbl>Heading</Lbl><input className="dz-in" value={b.title} maxLength={60} onChange={(e) => upd(b.id, (x) => { x.title = e.target.value; }, 't')} />
        <div className="dz-note">{b.type === 'bank' ? 'The bank details come from Company profile.' : 'The note typed on the document itself.'}</div></>;
      case 'terms': return <><Lbl>Heading</Lbl><input className="dz-in" value={b.title} maxLength={60} onChange={(e) => upd(b.id, (x) => { x.title = e.target.value; }, 't')} />
        <Lbl>Text</Lbl><textarea className="dz-in" rows={6} maxLength={2000} value={b.text} onChange={(e) => upd(b.id, (x) => { x.text = e.target.value; }, 'x')} /></>;
      case 'text': return <>
        <Lbl>Heading (optional)</Lbl><input className="dz-in" value={b.title} maxLength={60} onChange={(e) => upd(b.id, (x) => { x.title = e.target.value; }, 't')} />
        <Lbl>Text · you can use {'{no}'} {'{date}'} {'{party}'}</Lbl><textarea className="dz-in" rows={5} maxLength={1000} value={b.text} onChange={(e) => upd(b.id, (x) => { x.text = e.target.value; }, 'x')} />
        <Lbl>Align</Lbl><Seg value={b.align} opts={[['left', 'Left'], ['center', 'Centre'], ['right', 'Right']]} set={(v) => upd(b.id, (x) => { x.align = v; })} />
        <Lbl>Text size (pt)</Lbl><Num value={b.size} min={7} max={20} set={(v) => upd(b.id, (x) => { x.size = v; }, 'sz')} />
        <Sw label="Draw a box around it" on={!!b.boxed} set={(v) => upd(b.id, (x) => { x.boxed = v ? 1 : 0; })} />
        <button type="button" className="dz-btn danger mt-3" onClick={() => { edit((c) => { c.blocks = c.blocks.filter((x) => x.id !== b.id); }); setOpen(null); }}><i className="bi bi-trash" /> Delete this block</button></>;
      case 'signature': return <>
        <Lbl>Signature lines (up to 4)</Lbl>
        {b.boxes.map((l, n) => <div key={n} className="d-flex gap-1 mt-2"><input className="dz-in" value={l} maxLength={40} onChange={(e) => upd(b.id, (x) => { x.boxes[n] = e.target.value; }, 's' + n)} />
          {b.boxes.length > 1 && <button type="button" className="dz-x" aria-label="Remove" onClick={() => upd(b.id, (x) => { x.boxes.splice(n, 1); })}><i className="bi bi-x-lg" /></button>}</div>)}
        {b.boxes.length < 4 && <button type="button" className="dz-btn mt-2" onClick={() => upd(b.id, (x) => { x.boxes.push('Received by'); })}><i className="bi bi-plus-lg" /> Add a line</button>}
        <Lbl>Space to sign: {b.space} mm</Lbl><input type="range" min={6} max={50} value={b.space} className="w-100" onChange={(e) => upd(b.id, (x) => { x.space = Number(e.target.value); }, 'sp')} /></>;
      case 'footer': return <><Lbl>Text</Lbl><textarea className="dz-in" rows={3} maxLength={300} value={b.text} onChange={(e) => upd(b.id, (x) => { x.text = e.target.value; }, 'x')} />
        <Lbl>Align</Lbl><Seg value={b.align} opts={[['left', 'Left'], ['center', 'Centre'], ['right', 'Right']]} set={(v) => upd(b.id, (x) => { x.align = v; })} /></>;
    }
  };

  const h = cfg.header; const pg = cfg.page;
  const setH = (k: string, v: any, tag?: string) => edit((c) => { (c.header as any)[k] = v; }, tag ? 'h:' + tag : undefined);
  const setP = (k: string, v: any, tag?: string) => edit((c) => { (c.page as any)[k] = v; }, tag ? 'p:' + tag : undefined);

  return (
    <div className="dz">
      <header className="dz-top">
        <Link className="dz-back" to={backTo} title="Back" onClick={leave}><i className="bi bi-arrow-left" /></Link>
        <span className="dz-title">{d.docs[doc]} template</span><span className="dz-sep" />
        <input className="dz-select" style={{ minWidth: 230 }} value={name} maxLength={80} placeholder="Name this template" aria-label="Template name" onChange={(e) => setName(e.target.value)} />
        <label className="d-flex align-items-center gap-2 small" style={{ whiteSpace: 'nowrap' }}><input type="checkbox" checked={makeDefault} onChange={(e) => setMakeDefault(e.target.checked)} /> Use for printing</label>
        <span className="dz-grow" />
        {dirty && <span className="dz-dirty"><i className="bi bi-circle-fill" /> Unsaved</span>}
        <div className="dz-seg" title="What the preview is drawn with">
          <button type="button" className={src === 'real' ? 'on' : ''} disabled={!d.latest} onClick={() => setSrc('real')}>Latest {d.docs[doc].toLowerCase()}</button>
          <button type="button" className={src === 'sample' ? 'on' : ''} onClick={() => setSrc('sample')}>Sample</button>
        </div>
        <button type="button" className="dz-btn" disabled={hist.current.length < 2} onClick={undo}><i className="bi bi-arrow-counterclockwise" /><span>Undo</span></button>
        <Link className="dz-btn light" to={backTo} onClick={leave}>Cancel</Link>
        <button type="button" className="dz-btn primary" disabled={saving} onClick={() => save(false)}><i className="bi bi-check2" /><span>{saving ? 'Saving…' : 'Save'}</span></button>
        <button type="button" className="dz-btn primary soft" disabled={saving} onClick={() => save(true)}>Save and close</button>
      </header>
      <div className="dz-main">
        <aside className="dz-left" style={{ width: 360 }}>
          <div className="dz-seg mb-3" style={{ display: 'flex' }}>{([['sections', 'Sections'], ['header', 'Header'], ['page', 'Page']] as const).map(([k, l]) => <button key={k} type="button" className={tab === k ? 'on' : ''} style={{ flex: 1 }} onClick={() => setTab(k)}>{l}</button>)}</div>

          {tab === 'page' && <>
            <Lbl>Paper</Lbl><select className="dz-in" value={pg.size} onChange={(e) => setP('size', e.target.value)}>{Object.entries(SIZES).map(([k, [w, hh]]) => <option key={k} value={k}>{k} ({w} × {hh} mm)</option>)}</select>
            <Lbl>Orientation</Lbl><Seg value={pg.orient} opts={[['portrait', 'Portrait'], ['landscape', 'Landscape']]} set={(v) => setP('orient', v)} />
            <Lbl>Margin around the page: {pg.margin} mm</Lbl><input type="range" min={0} max={40} value={pg.margin} className="w-100" onChange={(e) => setP('margin', Number(e.target.value), 'm')} />
            <Lbl>Font</Lbl><select className="dz-in" value={pg.font} onChange={(e) => setP('font', e.target.value)}>{FONTS.map((f) => <option key={f}>{f}</option>)}</select>
            <Lbl>Text size: {pg.fsize} pt</Lbl><input type="range" min={7} max={16} step={0.5} value={pg.fsize} className="w-100" onChange={(e) => setP('fsize', Number(e.target.value), 'fs')} />
            <div className="row g-2"><div className="col-6"><Lbl>Text colour</Lbl><input type="color" className="form-control form-control-color w-100" value={pg.color} onChange={(e) => setP('color', e.target.value, 'col')} /></div>
              <div className="col-6"><Lbl>Accent colour</Lbl><input type="color" className="form-control form-control-color w-100" value={pg.accent} onChange={(e) => setP('accent', e.target.value, 'acc')} /></div></div>
            <Lbl>Watermark (for example DRAFT or COPY)</Lbl><input className="dz-in" maxLength={24} value={pg.wm_text} onChange={(e) => setP('wm_text', e.target.value, 'wm')} />
            {pg.wm_text ? <><Lbl>Watermark strength: {pg.wm_opacity}%</Lbl><input type="range" min={2} max={30} value={pg.wm_opacity} className="w-100" onChange={(e) => setP('wm_opacity', Number(e.target.value), 'wo')} /></> : null}
          </>}

          {tab === 'header' && <>
            <Lbl>Logo</Lbl><Seg value={h.logo} opts={[['left', 'Left'], ['right', 'Right'], ['none', 'Hide']]} set={(v) => setH('logo', v)} />
            {h.logo !== 'none' && <><Lbl>Logo height: {h.logo_h} mm</Lbl><input type="range" min={8} max={40} value={h.logo_h} className="w-100" onChange={(e) => setH('logo_h', Number(e.target.value), 'lh')} /></>}
            <Lbl>Arrangement</Lbl><Seg value={h.layout} opts={[['split', 'Company left, title right'], ['center', 'Everything centred']]} set={(v) => setH('layout', v)} />
            <Lbl>Company name size: {h.name_size} pt</Lbl><input type="range" min={10} max={30} value={h.name_size} className="w-100" onChange={(e) => setH('name_size', Number(e.target.value), 'ns')} />
            <Sw label="Address" on={!!h.show_address} set={(v) => setH('show_address', v ? 1 : 0)} />
            <Sw label="Phone" on={!!h.show_phone} set={(v) => setH('show_phone', v ? 1 : 0)} />
            <Sw label="Email" on={!!h.show_email} set={(v) => setH('show_email', v ? 1 : 0)} />
            <Sw label="Website" on={!!h.show_website} set={(v) => setH('show_website', v ? 1 : 0)} />
            <Sw label="Tax number" on={!!h.show_tax} set={(v) => setH('show_tax', v ? 1 : 0)} />
            <Lbl>Heading of the document</Lbl><input className="dz-input dz-in" maxLength={60} value={h.title} placeholder={spec.title} onChange={(e) => setH('title', e.target.value, 'title')} />
            <Lbl>Heading size: {h.title_size} pt</Lbl><input type="range" min={10} max={40} value={h.title_size} className="w-100" onChange={(e) => setH('title_size', Number(e.target.value), 'ts')} />
            <Lbl>Heading colour</Lbl>
            <div className="d-flex gap-2"><Seg value={h.title_color === 'accent' ? 'accent' : 'own'} opts={[['accent', 'Accent'], ['own', 'Own colour']]} set={(v) => setH('title_color', v === 'accent' ? 'accent' : '#111111')} />
              {h.title_color !== 'accent' && <input type="color" className="form-control form-control-color" value={h.title_color} onChange={(e) => setH('title_color', e.target.value, 'tc')} />}</div>
            <Lbl>Line under the header</Lbl><Seg value={h.rule} opts={[['solid', 'Solid'], ['double', 'Double'], ['none', 'None']]} set={(v) => setH('rule', v)} />
            {h.rule !== 'none' && <><Lbl>Thickness: {h.rule_w} px</Lbl><input type="range" min={1} max={6} value={h.rule_w} className="w-100" onChange={(e) => setH('rule_w', Number(e.target.value), 'rw')} /></>}
            {Object.keys(spec.meta).length > 0 && <><Lbl>Also show under the heading</Lbl>
              {Object.entries(spec.meta as Record<string, string>).map(([k, l]) => <Sw key={k} label={l} on={h.meta.includes(k)} set={(v) => edit((c) => { c.header.meta = v ? [...c.header.meta, k] : c.header.meta.filter((x) => x !== k); })} />)}</>}
          </>}

          {tab === 'sections' && <>
            <div className="dz-note mb-2">Switch sections on or off, move them, and open one to change how it looks. You can also click a section in the preview.</div>
            {cfg.blocks.map((b, i) => (
              <div key={b.id} id={'blk-' + b.id} className="mb-2" style={{ background: 'var(--dz-bg2)', border: `1px solid ${open === b.id ? 'var(--brand)' : 'var(--dz-line)'}`, borderRadius: 10 }}>
                <div className="d-flex align-items-center gap-1 p-2">
                  <label className="dz-sw flex-shrink-0"><input type="checkbox" checked={!!b.show} onChange={(e) => upd(b.id, (x) => { x.show = e.target.checked ? 1 : 0; })} /><i /></label>
                  <button type="button" className="btn btn-link text-start flex-grow-1 p-0 ms-1" style={{ color: 'var(--dz-ink)', textDecoration: 'none', opacity: b.show ? 1 : .5 }} onClick={() => setOpen(open === b.id ? null : b.id)}>
                    {b.type === 'text' ? `Text: ${b.title || 'block'}` : BLOCK_NAME[b.type]}</button>
                  <button type="button" className="dz-x" aria-label="Move up" disabled={i === 0} onClick={() => move(i, -1)}><i className="bi bi-arrow-up" /></button>
                  <button type="button" className="dz-x" aria-label="Move down" disabled={i === cfg.blocks.length - 1} onClick={() => move(i, 1)}><i className="bi bi-arrow-down" /></button>
                  <button type="button" className="dz-x" aria-label="Options" onClick={() => setOpen(open === b.id ? null : b.id)}><i className={`bi bi-chevron-${open === b.id ? 'up' : 'down'}`} /></button>
                </div>
                {open === b.id && <div className="px-3 pb-3">{options(b)}</div>}
              </div>))}
            <button type="button" className="dz-wide" onClick={addText}><i className="bi bi-plus-lg" /> ADD A TEXT BLOCK</button>
          </>}
        </aside>

        <main className="dz-center" ref={box} style={{ background: '#e5e7eb' }}>
          <Messages items={msgs} />
          <div style={{ width: pxW * zoom, margin: '0 auto' }}>
            <div style={{ width: `${pw}mm`, minHeight: `${ph}mm`, padding: `${pg.margin}mm`, background: '#fff', boxShadow: '0 2px 14px rgba(0,0,0,.25)', zoom, boxSizing: 'border-box' }}>
              <DocView cfg={cfg} title={spec.title} d={data} head={head} picked={tab === 'sections' ? open : null} onPick={pick} />
            </div>
          </div>
          <div className="text-center small text-muted mt-2">{pg.size} {pg.orient} · {src === 'real' && real.data ? `previewing ${data.no}` : 'sample data'}</div>
        </main>
      </div>
    </div>
  );
}
