import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema, type Schema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';
import RecordPage from '../../components/RecordPage';

const refresh = (qc: ReturnType<typeof useQueryClient>) => ['locations', 'location', 'record', 'warehouse', 'warehouses', 'racks'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));

export function LocationList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const del = async (id: number) => {
    if (!confirm(`Delete this ${term('rack').toLowerCase()}?`)) return;
    const r = await post(`/locations/${id}/delete`);
    if (!r.ok) alert(r.messages?.[0]?.msg ?? 'Could not delete.');
    refresh(qc);
  };
  return (
    <DocList title={`${term('rack', true)} / locations`} api="/locations" queryKey="locations" empty={`No ${term('rack', true).toLowerCase()} yet. Add your ${term('rack', true).toLowerCase()} or bins (for example A-01, A-02 …) so you can see where everything is kept.`}
      filters={(d, set, sp) => (
        <Filters names={['warehouse', 'q']} sp={sp} set={set} right={can('warehouses.create') ? <Link className="btn btn-primary" to={`/locations/create${sp.get('warehouse') ? `?warehouse=${sp.get('warehouse')}` : ''}`}><i className="bi bi-plus-lg" /> Add {term('rack', true).toLowerCase()}</Link> : null}>
          <div className="col-md-3"><select name="warehouse" className="form-select" defaultValue={sp.get('warehouse') ?? ''}><option value="">All {term('warehouse', true).toLowerCase()}</option>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="Search rack code" defaultValue={sp.get('q') ?? ''} /></div>
        </Filters>)}
      above={(d) => d.unassigned > 0 ? <div className="alert alert-warning py-2">{d.unassigned} {term('item').toLowerCase()}(s) have stock with <strong>no {term('rack').toLowerCase()}</strong> assigned. <Link to="/stock/racks?q=-">See them</Link> and move them to a {term('rack').toLowerCase()} with a stock transfer.</div> : null}
      columns={[
        { head: term('rack'), cell: (r) => <Link to={`/locations/${r.id}`}><strong>{r.code}</strong></Link> }, { head: term('warehouse'), cell: (r) => r.warehouse }, { head: 'Description', cell: (r) => r.description ?? '' },
        { head: `${term('item', true)} on it`, className: 'text-end', cell: (r) => r.items },
        { head: 'Status', cell: (r) => Number(r.is_active) ? <Badge tone="success">Active</Badge> : <Badge tone="secondary">Inactive</Badge> },
        { head: '', className: 'text-end text-nowrap', cell: (r) => (<>
          {can('warehouses.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/locations/${r.id}/edit`}>Edit</Link>}{' '}
          {can('warehouses.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => del(r.id)}>Delete</button>}</>) },
      ]} />
  );
}

export function LocationDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const q = useQuery({ queryKey: ['location', id], queryFn: async () => (await get(`/locations/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">{term('rack')} not found.</div>;
  const l = d.l;
  const remove = async () => {
    if (!confirm(`Delete this ${term('rack').toLowerCase()}?`)) return;
    const r = await post(`/locations/${l.id}/delete`);
    if (!r.ok) { alert(r.messages?.[0]?.msg ?? 'Could not delete.'); return; }
    refresh(qc); nav(`/locations?warehouse=${l.warehouse_id}`);
  };
  return (
    <RecordPage entity="location" id={l.id} name={l.code} back="/locations" badges={Number(l.is_active) ? null : <Badge tone="secondary">Inactive</Badge>}
      actions={can('warehouses.edit') ? <Link className="btn btn-sm btn-primary" to={`/locations/${l.id}/edit`}>Edit</Link> : null}
      menu={() => can('warehouses.delete') ? <li><button className="dropdown-item text-danger" onClick={remove}><i className="bi bi-trash me-2" />Delete</button></li> : null}
      related={[{ id: 'stock', label: 'Stored here' }]}>
      <div className="card mb-3" id="stock"><div className="card-header">Stored on this {term('rack').toLowerCase()}</div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>{term('item')}</th><th>SKU</th><th>{term('batch')}</th><th className="text-end">Qty</th></tr></thead><tbody>
          {d.stock.map((r: any, i: number) => <tr key={i}><td><Link to={`/items/${r.item_id}`}>{r.item_name}</Link>{r.vname ? ` — ${r.vname}` : ''}</td><td>{r.sku}</td><td>{r.batch_no ? <Link to={`/stock/batches/${r.batch_id}`}>{r.batch_no}</Link> : ''}</td><td className="text-end">{qty(r.qty)} {r.unit}</td></tr>)}
          {!d.stock.length && <tr><td colSpan={4} className="text-muted">Nothing is stored here.</td></tr>}</tbody></table></div></div>
    </RecordPage>
  );
}

/** Add one rack or a range of racks; edit a rack's code / description / active flag. */
export function LocationForm() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('location');
  const src = useQuery({ queryKey: ['location-form', id ?? 'new'], queryFn: async () => (await get(id ? `/locations/${id}/edit` : '/locations/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [active, setActive] = useState(true);
  const [wh, setWh] = useState(''); const [mode, setMode] = useState<'single' | 'range'>('single');
  const [rng, setRng] = useState({ prefix: '', from: '1', to: '10', pad: '2' });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const row = src.data.row ?? {}; const v: Record<string, any> = {};
    Object.keys(schema.data.fields).forEach((k) => (v[k] = row[k] ?? ''));
    setValues(v); setActive(row.is_active === undefined ? true : !!Number(row.is_active));
    setWh(sp.get('warehouse') ?? String(src.data.warehouses?.[0]?.id ?? ''));
  }, [schema.data, src.data, values, sp]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const row = src.data.row;
  // in "many racks" mode the single code box is replaced by the prefix / from / to fields
  const shown: Schema = !id && mode === 'range' ? { ...schema.data, sections: schema.data.sections.map((s) => ({ ...s, fields: s.fields.filter((k) => k !== 'code') })) } : schema.data;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = toPayload(schema.data!, values);
    if (id) { if (active) body.is_active = 1; } else Object.assign(body, { warehouse_id: wh, mode }, mode === 'range' ? rng : {});
    const r = await post(id ? `/locations/${id}` : '/locations', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc);
    nav(id ? `/locations/${id}` : `/locations?warehouse=${wh}`);
  };
  const num = (k: keyof typeof rng, label: string, col: string, extra: any = {}) => <div className={col}><label className="form-label">{label}</label><input className="form-control" type="number" min="0" value={rng[k]} onChange={(e) => setRng({ ...rng, [k]: e.target.value })} {...extra} /></div>;
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? `Edit ${term('rack').toLowerCase()}` : `Add ${term('rack', true).toLowerCase()}`}
        extra={schema.data.canDesign ? <a className="page-layout-link" href={legacyUrl(`/settings/formdesign?form=location&return=${encodeURIComponent(location.pathname)}`)}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</a> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/locations/${id}` : '/locations')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 640 }}><div className="card-body">
        {id ? <p className="text-muted">{term('warehouse')}: <strong>{row.warehouse}</strong></p> : (<>
          <div className="mb-3"><label className="form-label">{term('warehouse')}</label>
            <select className="form-select" required value={wh} onChange={(e) => setWh(e.target.value)}>{(src.data.warehouses ?? []).map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
          <div className="btn-group mb-3" role="group">
            <button type="button" className={`btn btn-outline-primary ${mode === 'single' ? 'active' : ''}`} onClick={() => setMode('single')}>One {term('rack').toLowerCase()}</button>
            <button type="button" className={`btn btn-outline-primary ${mode === 'range' ? 'active' : ''}`} onClick={() => setMode('range')}>Many {term('rack', true).toLowerCase()} (a range)</button></div>
          {mode === 'range' && <div className="mb-2"><div className="row">
            <div className="col-md-4 mb-3"><label className="form-label">Prefix</label><input className="form-control" placeholder="A-" value={rng.prefix} onChange={(e) => setRng({ ...rng, prefix: e.target.value })} /></div>
            {num('from', 'From', 'col-md-3 mb-3')}{num('to', 'To', 'col-md-3 mb-3')}{num('pad', 'Digits', 'col-md-2 mb-3', { max: 6 })}</div>
            <p className="text-muted small">Prefix <code>A-</code>, from 1 to 10 with 2 digits creates A-01 … A-10 (max 200 at a time).</p></div>}
        </>)}
        <SchemaForm schema={shown} values={values} onChange={setValues} />
        {id && <div className="form-check mb-1"><input className="form-check-input" type="checkbox" id="act" checked={active} onChange={(e) => setActive(e.target.checked)} /><label className="form-check-label" htmlFor="act">Active (new stock can be put here)</label></div>}
      </div></div>
    </form>
  );
}
