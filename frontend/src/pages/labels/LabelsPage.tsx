import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { LabelView, SheetPreview, type Template, type Values } from './render';

const sizeText = (t: { w: number; h: number }) => `${+t.w.toFixed(2)} × ${+t.h.toFixed(2)} mm`;
const layoutText = (t: Template) => (t.page.mode === 'sheet' ? `${t.page.paper} sheet · ${t.page.cols} × ${t.page.rows}` : t.page.cols > 1 ? `Roll · ${t.page.cols} across` : 'Roll');

/** Pick what to print, choose a template, preview it, print. */
export default function LabelsPage() {
  const { term } = useSession();
  const [sp, setSp] = useSearchParams();
  const type = sp.get('type') === 'rolls' ? 'rolls' : 'items';
  const q = sp.get('q') ?? ''; const category = sp.get('category') ?? ''; const stock = sp.get('stock') === '1'; const grn = sp.get('grn') ?? '';
  const [search, setSearch] = useState(q);
  const [copies, setCopies] = useState<Record<string, number>>({});
  const [tplId, setTplId] = useState<string>('');
  const [start, setStart] = useState(1); const [outline, setOutline] = useState<boolean | null>(null);
  const qs = `type=${type}&q=${encodeURIComponent(q)}&category=${category}&stock=${stock ? 1 : ''}&grn=${grn}`;
  const res = useQuery({ queryKey: ['labels', qs], queryFn: async () => (await get(`/labels?${qs}`)).data });
  const d = res.data;

  const set = (p: Record<string, string>) => { const n = new URLSearchParams(sp); Object.entries(p).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); setSp(n, { replace: true }); setCopies({}); };
  useEffect(() => { setSearch(q); }, [q]);
  const templates: Template[] = d?.templates ?? [];
  const usable = useMemo(() => templates.filter((t) => t.for === 'any' || t.for === type), [templates, type]);
  useEffect(() => {
    if (!d) return;
    const remembered = localStorage.getItem('label.tpl.' + type);
    const pick = usable.find((t) => String(t.id) === tplId) ?? usable.find((t) => String(t.id) === remembered) ?? usable.find((t) => String(t.id) === String(d.default)) ?? usable[0];
    if (pick && String(pick.id) !== tplId) setTplId(String(pick.id));
  }, [d, usable, tplId, type]);
  const tpl = templates.find((t) => String(t.id) === tplId);
  const rows: any[] = d?.rows ?? [];
  const picked = rows.filter((r) => (copies[r.id] ?? 0) > 0);
  const total = picked.reduce((s, r) => s + (copies[r.id] ?? 0), 0);
  const previewRow = picked[0] ?? rows[0];
  const vals = useQuery({ queryKey: ['label-values', type, previewRow?.id], enabled: !!previewRow, queryFn: async () => (await get(`/labels/data?type=${type}&ids=${previewRow.id}`)) });
  const values: Values = (previewRow && vals.data?.values?.[previewRow.id]) || vals.data?.sample || {};

  if (res.isLoading || !d) return <Spinner />;
  const setAll = (fn: (r: any) => number) => { const n: Record<string, number> = {}; rows.forEach((r) => { const v = Math.max(0, Math.min(200, Math.round(fn(r)))); if (v > 0) n[r.id] = v; }); setCopies(n); };
  const perPage = tpl ? (tpl.page.mode === 'sheet' ? tpl.page.cols * tpl.page.rows : tpl.page.cols) : 1;
  const skip = tpl?.page.mode === 'sheet' ? Math.max(0, start - 1) : 0;
  const pages = total ? Math.ceil((total + skip) / perPage) : 0;
  const print = () => {
    if (!tpl || !total) return;
    const p = new URLSearchParams({ type, template: String(tpl.id), start: String(start) });
    if (outline !== null) p.set('outline', outline ? '1' : '0');
    picked.forEach((r) => p.set(`n[${r.id}]`, String(copies[r.id])));
    localStorage.setItem('label.tpl.' + type, String(tpl.id));
    window.open(legacyUrl('/labels/print?' + p.toString()), '_blank');
  };

  return (
    <>
      <PageHead title="Barcode labels">
        <Link className="btn btn-outline-secondary" to="/labels/templates"><i className="bi bi-grid-1x2" /> Label templates</Link>
        {d.can.create && <Link className="btn btn-outline-primary" to="/labels/templates/create"><i className="bi bi-vector-pen" /> Design a label</Link>}
        <button className="btn btn-primary" disabled={!total || !tpl} onClick={print}><i className="bi bi-printer" /> Print {total ? `${total} label${total === 1 ? '' : 's'}` : ''}</button>
      </PageHead>
      <div className="row g-3">
        <div className="col-xl-8">
          <div className="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div className="btn-group"><button className={`btn btn-outline-secondary ${type === 'items' ? 'active' : ''}`} onClick={() => set({ type: 'items', grn: '' })}>{term('item', true)} (SKU / price)</button>
              <button className={`btn btn-outline-secondary ${type === 'rolls' ? 'active' : ''}`} onClick={() => set({ type: 'rolls' })}>{term('batch', true)} (roll labels)</button></div>
            <form className="d-flex gap-2 flex-grow-1" onSubmit={(e) => { e.preventDefault(); set({ q: search }); }}>
              <input className="form-control" style={{ maxWidth: 280 }} placeholder={type === 'rolls' ? 'Search roll no., item, SKU, lot' : 'Search name, SKU, barcode, supplier code'} value={search} onChange={(e) => setSearch(e.target.value)} />
              {type === 'items' && <select className="form-select" style={{ maxWidth: 190 }} value={category} onChange={(e) => set({ category: e.target.value })}><option value="">All categories</option>{d.categories.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>}
              {type === 'items' && <div className="form-check d-flex align-items-center"><input className="form-check-input me-1" id="inStock" type="checkbox" checked={stock} onChange={(e) => set({ stock: e.target.checked ? '1' : '' })} /><label className="form-check-label small text-nowrap" htmlFor="inStock">In stock only</label></div>}
              <button className="btn btn-outline-secondary">Search</button>
            </form>
          </div>
          {grn && <div className="alert alert-info py-2 small d-flex justify-content-between">Showing the rolls received on this goods receipt.<button className="btn btn-sm btn-link p-0" onClick={() => set({ grn: '' })}>Show all rolls</button></div>}
          <div className="d-flex flex-wrap gap-2 mb-2 small align-items-center">
            <span className="text-muted">Copies:</span>
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setAll(() => 1)}>1 each</button>
            {type === 'items' && <button className="btn btn-sm btn-outline-secondary" onClick={() => setAll((r) => Number(r.stock))}>= stock on hand</button>}
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setCopies({})}>Clear</button>
            <span className="ms-auto text-muted">{rows.length} shown{rows.length >= d.limit ? ` (first ${d.limit} — search to narrow)` : ''}</span>
          </div>
          <div className="card"><div className="table-responsive" style={{ maxHeight: '60vh' }}><table className="table table-sm align-middle mb-0">
            <thead className="sticky-top bg-white"><tr><th>{term('item')}</th><th>{type === 'rolls' ? 'Roll no.' : 'Barcode / SKU'}</th>{type === 'rolls' ? <><th className="text-end">Balance</th><th>Rack</th></> : <><th className="text-end">Price</th><th className="text-end">In stock</th></>}<th style={{ width: 100 }}>Copies</th></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className={(copies[r.id] ?? 0) > 0 ? 'table-active' : ''}>
                  <td>{r.item}{type === 'rolls' && r.sku ? <div className="small text-muted">{r.sku}{r.supplier_lot ? ` · lot ${r.supplier_lot}` : ''}</div> : null}</td>
                  <td><code>{r.code}</code></td>
                  {type === 'rolls' ? <><td className="text-end">{qty(r.bal)} {r.unit}</td><td className="small">{r.rack ?? ''}</td></> : <><td className="text-end">{money(r.sale_price)}</td><td className="text-end">{qty(r.stock)} {r.unit}</td></>}
                  <td><input type="number" min={0} max={200} className="form-control form-control-sm" value={copies[r.id] ?? ''} placeholder="0" onChange={(e) => setCopies({ ...copies, [r.id]: Math.max(0, Math.min(200, Number(e.target.value) || 0)) })} /></td>
                </tr>))}
              {!rows.length && <tr><td colSpan={5} className="text-muted">Nothing found.</td></tr>}
            </tbody></table></div></div>
          <p className="text-muted small mt-2 mb-0">Items without a barcode use their SKU. Roll labels carry the roll number, quantity and rack. Each template decides what appears on the label and which barcode type is used.</p>
        </div>
        <div className="col-xl-4">
          <div className="card mb-3"><div className="card-header">Label template</div><div className="card-body">
            <select className="form-select mb-2" value={tplId} onChange={(e) => setTplId(e.target.value)}>
              {usable.some((t) => !t.builtin) && <optgroup label="Your templates">{usable.filter((t) => !t.builtin).map((t) => <option key={t.id} value={String(t.id)}>{t.name}{t.is_default ? ' ★' : ''}</option>)}</optgroup>}
              <optgroup label="Ready-made">{usable.filter((t) => t.builtin).map((t) => <option key={t.id} value={String(t.id)}>{t.name}</option>)}</optgroup>
            </select>
            {tpl && <div className="small text-muted mb-2">{sizeText(tpl)} · {layoutText(tpl)}</div>}
            {tpl && <div className="d-flex justify-content-center p-3 mb-2" style={{ background: '#eef0f4', borderRadius: 6, overflow: 'auto' }}>
              <LabelView w={tpl.w} h={tpl.h} elements={tpl.elements} values={values} scale={Math.min(7, Math.max(2.5, 300 / tpl.w))} />
            </div>}
            {previewRow && <div className="small text-muted mb-2">Preview of: {previewRow.item}</div>}
            <div className="d-flex gap-2 flex-wrap">
              {tpl && (d.can.edit && !tpl.builtin ? <Link className="btn btn-sm btn-outline-primary" to={`/labels/templates/${tpl.id}/edit`}><i className="bi bi-pencil" /> Edit this label</Link>
                : d.can.create ? <Link className="btn btn-sm btn-outline-primary" to={`/labels/templates/create?copy=${encodeURIComponent(String(tpl.id))}`}><i className="bi bi-copy" /> Customise a copy</Link> : null)}
            </div>
          </div></div>
          {tpl?.page.mode === 'sheet' && <div className="card mb-3"><div className="card-header">Sheet</div><div className="card-body">
            <label className="form-label small">Start printing at label no.</label>
            <input type="number" min={1} max={perPage} className="form-control form-control-sm mb-2" style={{ maxWidth: 120 }} value={start} onChange={(e) => setStart(Math.max(1, Math.min(perPage, Number(e.target.value) || 1)))} />
            <div className="form-text mb-2">Already used the first labels of a sheet? Start part-way through. Applies to the first sheet only.</div>
            <SheetPreview w={tpl.w} h={tpl.h} page={tpl.page} skip={skip} count={Math.min(total, perPage - skip)} />
            <div className="small text-muted mt-2">{total ? `${total} labels on ${pages} sheet${pages === 1 ? '' : 's'}` : 'Choose copies to see how many sheets you need.'}</div>
          </div></div>}
          <div className="form-check"><input className="form-check-input" id="outline" type="checkbox" checked={outline ?? !!tpl?.page.outline} onChange={(e) => setOutline(e.target.checked)} /><label className="form-check-label small" htmlFor="outline">Print a thin outline around each label (useful on plain paper)</label></div>
          <button className="btn btn-primary w-100 mt-3" disabled={!total || !tpl} onClick={print}><i className="bi bi-printer" /> {total ? `Print ${total} label${total === 1 ? '' : 's'}${pages > 1 ? ` (${pages} pages)` : ''}` : 'Choose copies first'}</button>
          <div className="text-muted small mt-2">In the print window set margins to “None” and scale to 100%.</div>
        </div>
      </div>
    </>
  );
}

/** All label templates: thumbnails, edit, duplicate, default, delete. */
export function LabelTemplates() {
  const qc = useQueryClient();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const res = useQuery({ queryKey: ['label-templates'], queryFn: async () => (await get('/labels/templates')).data });
  const d = res.data;
  const sample = useQuery({ queryKey: ['label-sample'], queryFn: async () => (await get('/labels/data?type=items&ids=')).sample as Values });
  if (res.isLoading || !d) return <Spinner />;
  const act = async (path: string, ask?: string) => { if (ask && !confirm(ask)) return; const r = await post(path); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['label-templates'] }); qc.invalidateQueries({ queryKey: ['labels'] }); };
  const rows: Template[] = d.templates;
  return (
    <>
      <PageHead title="Label templates">
        <Link className="btn btn-outline-secondary" to="/labels"><i className="bi bi-printer" /> Print labels</Link>
        {d.can.create && <Link className="btn btn-primary" to="/labels/templates/create"><i className="bi bi-plus-lg" /> New template</Link>}
      </PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <p className="text-muted">A template is the size of your label, how labels sit on the sheet or roll, and what is printed on each one. Ready-made templates cannot be changed — customise a copy.</p>
      <div className="row g-3">
        {rows.map((t) => (
          <div className="col-md-6 col-xl-4" key={t.id}><div className="card h-100"><div className="card-body">
            <div className="d-flex justify-content-between align-items-start gap-2 mb-1"><strong>{t.name}</strong><span className="d-flex gap-1">{t.is_default ? <Badge tone="primary">Default</Badge> : null}{t.builtin ? <Badge>Ready-made</Badge> : null}</span></div>
            <div className="small text-muted mb-2">{sizeText(t)} · {layoutText(t)} · for {t.for === 'any' ? 'items & rolls' : t.for}</div>
            <div className="d-flex justify-content-center p-3 mb-2" style={{ background: '#eef0f4', borderRadius: 6, overflow: 'hidden', minHeight: 70 }}>
              <LabelView w={t.w} h={t.h} elements={t.elements} values={sample.data ?? {}} scale={Math.min(4.2, 230 / t.w, 150 / t.h)} />
            </div>
            <div className="d-flex flex-wrap gap-1">
              {d.can.edit && !t.builtin ? <Link className="btn btn-sm btn-outline-primary" to={`/labels/templates/${t.id}/edit`}>Edit</Link> : <Link className="btn btn-sm btn-outline-primary" to={`/labels/templates/${t.id}/edit`}>View</Link>}
              {d.can.create && <Link className="btn btn-sm btn-outline-secondary" to={`/labels/templates/create?copy=${encodeURIComponent(String(t.id))}`}>Duplicate</Link>}
              {d.can.edit && !t.builtin && !t.is_default && <button className="btn btn-sm btn-outline-secondary" onClick={() => act(`/labels/templates/${t.id}/default`)}>Make default</button>}
              {d.can.delete && !t.builtin && <button className="btn btn-sm btn-outline-danger ms-auto" onClick={() => act(`/labels/templates/${t.id}/delete`, `Delete "${t.name}"?`)}>Delete</button>}
            </div>
          </div></div></div>
        ))}
      </div>
    </>
  );
}
