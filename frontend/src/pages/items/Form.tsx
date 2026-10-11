import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';

type Variant = { id: string | number; name: string; colour: string; size: string; sku: string; barcode: string; cost_price: string; sale_price: string };
type Component = { variant_id: string; qty: string };
const blankVariant = (): Variant => ({ id: '', name: '', colour: '', size: '', sku: '', barcode: '', cost_price: '', sale_price: '' });

/** Item create / edit: the designed form, the options box, the variants table (or the single bundle SKU) and bundle components. */
export default function ItemForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('item');
  const src = useQuery({ queryKey: ['item-form', id ?? 'new'], queryFn: async () => (await get(id ? `/items/${id}/edit` : '/items/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [opt, setOpt] = useState({ is_bundle: false, track_batch: false, reorder_level: '0', reorder_qty: '0', is_active: true });
  const [variants, setVariants] = useState<Variant[]>([blankVariant()]); const [components, setComponents] = useState<Component[]>([]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const it = src.data.item ?? {}; const cf = src.data.cfValues ?? {};
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; }
      else v[k] = it[k] ?? (k === 'item_type' ? 'other' : '');
    }
    setValues(v);
    setOpt({ is_bundle: !!Number(it.is_bundle ?? 0), track_batch: !!Number(it.track_batch ?? 0), reorder_level: String(Number(it.reorder_level ?? 0)), reorder_qty: String(Number(it.reorder_qty ?? 0)), is_active: it.is_active === undefined ? true : !!Number(it.is_active) });
    const vs: any[] = src.data.variants ?? [];
    setVariants(vs.length ? vs.map((r) => ({ id: r.id ?? '', name: r.name ?? '', colour: r.colour ?? '', size: r.size ?? '', sku: r.sku ?? '', barcode: r.barcode ?? '', cost_price: r.cost_price ?? '', sale_price: r.sale_price ?? '' })) : [blankVariant()]);
    setComponents((src.data.components ?? []).map((c: any) => ({ variant_id: String(c.variant_id), qty: String(Number(c.qty)) })));
  }, [schema.data, src.data, values]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data; const locked: boolean = !!d.locked; const editing = !!id; const bundle = editing ? !!Number(d.item.is_bundle) : opt.is_bundle;
  const setV = (i: number, k: keyof Variant, val: string) => setVariants((vs) => vs.map((r, j) => (j === i ? { ...r, [k]: val } : r)));
  const setC = (i: number, k: keyof Component, val: string) => setComponents((cs) => cs.map((r, j) => (j === i ? { ...r, [k]: val } : r)));

  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), reorder_level: opt.reorder_level, reorder_qty: opt.reorder_qty,
      variants: (bundle ? variants.slice(0, 1) : variants).map((v) => ({ ...v })), components: bundle ? components : [] };
    if (!editing && opt.is_bundle) body.is_bundle = 1;
    if (!bundle && (locked ? Number(d.item.track_batch) : opt.track_batch)) body.track_batch = 1;
    if (editing && opt.is_active) body.is_active = 1;
    const r = await post(editing ? `/items/${id}` : '/items', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['items', 'item', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/items');
  };

  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={(editing ? 'Edit ' : 'New ') + term('item').toLowerCase()}
        extra={schema.data.canDesign ? <a className="page-layout-link" href={legacyUrl(`/settings/formdesign?form=item&return=${encodeURIComponent(location.pathname)}`)}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</a> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(editing ? `/items/${id}` : '/items')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="row">
        <div className="col-lg-8"><div className="card mb-3"><div className="card-body"><SchemaForm schema={schema.data} values={values} onChange={setValues} /></div></div></div>
        <div className="col-lg-4"><div className="card mb-3"><div className="card-body">
          {!editing && <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="isBundle" checked={opt.is_bundle} onChange={(e) => setOpt({ ...opt, is_bundle: e.target.checked })} /><label className="form-check-label" htmlFor="isBundle">Bundle / set <small className="text-muted d-block">e.g. dining table + 6 chairs sold as one</small></label></div>}
          {!bundle && <div className="mb-2">
            <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="trk" checked={locked ? !!Number(d.item.track_batch) : opt.track_batch} disabled={locked} onChange={(e) => setOpt({ ...opt, track_batch: e.target.checked })} />
              <label className="form-check-label" htmlFor="trk">Track by {term('batch', true).toLowerCase()} (one roll = one batch)<small className="text-muted d-block">Each purchased roll becomes its own {term('batch').toLowerCase()}, and every sale is traced to it. Use for fabric.</small></label></div>
            {locked && <small className="text-muted">Locked: stock has already moved.</small>}</div>}
          <hr />
          <div className="mb-2"><label className="form-label">Reorder level</label><input type="number" step="0.001" min="0" className="form-control" value={opt.reorder_level} onChange={(e) => setOpt({ ...opt, reorder_level: e.target.value })} /></div>
          <div className="mb-2"><label className="form-label">Reorder quantity</label><input type="number" step="0.001" min="0" className="form-control" value={opt.reorder_qty} onChange={(e) => setOpt({ ...opt, reorder_qty: e.target.value })} /></div>
          {editing && <div className="form-check mt-3"><input className="form-check-input" type="checkbox" id="isActive" checked={opt.is_active} onChange={(e) => setOpt({ ...opt, is_active: e.target.checked })} /><label className="form-check-label" htmlFor="isActive">Active</label></div>}
        </div></div></div>
      </div>

      <div className="mt-3">
        <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>{bundle ? 'Bundle SKU & price' : 'Variants'}</span>
          {!bundle && <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setVariants((v) => [...v, blankVariant()])}><i className="bi bi-plus-lg" /> Add variant</button>}</div>
          <div className="table-responsive"><table className="table mb-0 align-middle" id="variantTable"><thead><tr><th>Variant name</th><th>Colour</th><th>Size</th><th>SKU</th><th>Barcode</th><th>Cost</th><th>Sale price</th><th /></tr></thead><tbody>
            {(bundle ? variants.slice(0, 1) : variants).map((r, i) => (
              <tr key={i}>
                <td><input className="form-control form-control-sm" value={r.name} onChange={(e) => setV(i, 'name', e.target.value)} /></td>
                <td><input className="form-control form-control-sm" maxLength={60} value={r.colour} onChange={(e) => setV(i, 'colour', e.target.value)} /></td>
                <td><input className="form-control form-control-sm" maxLength={60} placeholder="e.g. 54 in / King" value={r.size} onChange={(e) => setV(i, 'size', e.target.value)} /></td>
                <td><input className="form-control form-control-sm" placeholder="auto" value={r.sku} onChange={(e) => setV(i, 'sku', e.target.value)} /></td>
                <td><input className="form-control form-control-sm" value={r.barcode} onChange={(e) => setV(i, 'barcode', e.target.value)} /></td>
                <td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={r.cost_price} onChange={(e) => setV(i, 'cost_price', e.target.value)} /></td>
                <td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={r.sale_price} onChange={(e) => setV(i, 'sale_price', e.target.value)} /></td>
                <td>{!bundle && variants.length > 1 && <button type="button" className="btn btn-sm btn-outline-danger" title="Remove" onClick={() => setVariants((v) => v.filter((_, j) => j !== i))}>×</button>}</td>
              </tr>))}</tbody></table></div></div>

        {bundle && <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>Bundle components</span>
          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setComponents((c) => [...c, { variant_id: '', qty: '1' }])}><i className="bi bi-plus-lg" /> Add component</button></div>
          <div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th style={{ width: 140 }}>Qty per set</th><th /></tr></thead><tbody>
            {components.map((c, i) => (
              <tr key={i}><td><select className="form-select form-select-sm" value={c.variant_id} onChange={(e) => setC(i, 'variant_id', e.target.value)}><option value="">Choose…</option>
                {(d.pickable ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.item_name}{p.name ? ` — ${p.name}` : ''} ({p.sku})</option>)}</select></td>
                <td><input type="number" step="0.001" min="0" className="form-control form-control-sm" value={c.qty} onChange={(e) => setC(i, 'qty', e.target.value)} /></td>
                <td><button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setComponents((cs) => cs.filter((_, j) => j !== i))}>×</button></td></tr>))}
            {!components.length && <tr><td colSpan={3} className="text-muted">Add the items that make up one set.</td></tr>}</tbody></table></div></div>}
      </div>
    </form>
  );
}
