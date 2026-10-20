import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, seedCustom, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import RecordPage from '../../components/RecordPage';

const refresh = (qc: ReturnType<typeof useQueryClient>) => ['warehouses', 'warehouse', 'record', 'racks', 'stock'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));

export function WarehouseList() {
  const { can: canRaw, term, session } = useSession(); const staff = !!session?.user?.warehouse; const can = (p: string) => canRaw(p) && !(staff && /^warehouses\.(create|delete)$/.test(p)); const qc = useQueryClient();
  const act = async (path: string, ask?: string) => {
    if (ask && !confirm(ask)) return;
    const r = await post(path);
    if (!r.ok) alert(r.messages?.[0]?.msg ?? 'That did not work.');
    refresh(qc);
  };
  return (
    <DocList title={term('warehouse', true)} api="/warehouses" queryKey="warehouses" empty="No warehouses yet."
      actions={(d) => can('warehouses.create') ? (d.limitReached ? <span className="text-warning">Warehouse limit reached for your plan</span> : <Link className="btn btn-primary" to="/warehouses/create"><i className="bi bi-plus-lg" /> Add {term('warehouse').toLowerCase()}</Link>) : null}
      columns={[
        { head: 'Name', cell: (r) => <><Link to={`/warehouses/${r.id}`}>{r.name}</Link> {Number(r.is_default) ? <Badge tone="primary">Default</Badge> : null}</> },
        { head: 'Code', cell: (r) => r.code }, { head: 'Address', cell: (r) => r.address ?? '' },
        { head: 'Stock qty', className: 'text-end', cell: (r) => qty(r.total_qty) },
        { head: 'Status', cell: (r) => Number(r.is_active) ? <Badge tone="success">Active</Badge> : <Badge tone="secondary">Inactive</Badge> },
        { head: '', className: 'text-end text-nowrap', cell: (r) => (<>
          {can('warehouses.edit') && <><Link className="btn btn-sm btn-outline-primary" to={`/warehouses/${r.id}/edit`}>Edit</Link>{' '}
            {!staff && !Number(r.is_default) && <button className="btn btn-sm btn-outline-secondary" onClick={() => act(`/warehouses/${r.id}/default`)}>Make default</button>}{' '}</>}
          {can('warehouses.delete') && !Number(r.is_default) && <button className="btn btn-sm btn-outline-danger" onClick={() => act(`/warehouses/${r.id}/delete`, `Delete this ${term('warehouse').toLowerCase()}?`)}>Delete</button>}
        </>) },
      ]} />
  );
}

export function WarehouseDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can: canRaw, term, session } = useSession(); const staff = !!session?.user?.warehouse; const can = (p: string) => canRaw(p) && !(staff && /^warehouses\.(create|delete)$/.test(p));
  const q = useQuery({ queryKey: ['warehouse', id], queryFn: async () => (await get(`/warehouses/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">{term('warehouse')} not found.</div>;
  const w = d.w;
  const act = async (path: string, ask?: string, then?: () => void) => {
    if (ask && !confirm(ask)) return;
    const r = await post(path);
    if (!r.ok) { alert(r.messages?.[0]?.msg ?? 'That did not work.'); return; }
    refresh(qc); then?.();
  };
  return (
    <RecordPage entity="warehouse" id={w.id} name={w.name} back="/warehouses"
      badges={<>{Number(w.is_default) ? <><Badge tone="primary">Default</Badge> </> : null}{Number(w.is_active) ? null : <Badge tone="secondary">Inactive</Badge>}</>}
      actions={can('warehouses.edit') ? <Link className="btn btn-sm btn-primary" to={`/warehouses/${w.id}/edit`}>Edit</Link> : null}
      menu={() => <>
        {can('warehouses.edit') && !staff && !Number(w.is_default) && <li><button className="dropdown-item" onClick={() => act(`/warehouses/${w.id}/default`)}><i className="bi bi-star me-2 text-muted" />Make default</button></li>}
        {can('warehouses.delete') && !Number(w.is_default) && <li><button className="dropdown-item text-danger" onClick={() => act(`/warehouses/${w.id}/delete`, `Delete this ${term('warehouse').toLowerCase()}?`, () => nav('/warehouses'))}><i className="bi bi-trash me-2" />Delete</button></li>}
      </>}
      related={[{ id: 'stats', label: 'Overview figures' }, { id: 'racks', label: term('rack', true) }, { id: 'stock', label: 'Stock here' }]}>
      <div className="row g-3 mb-3" id="stats">
        {[['Total stock', qty(d.totalQty)], ['Different items', String(d.skuCount)], [term('rack', true), String(d.racks.length)]].map(([l, v]) => (
          <div className="col-6 col-md-3" key={l}><div className="card"><div className="card-body"><div className="text-muted small">{l}</div><div className="fs-3">{v}</div></div></div></div>))}
      </div>
      <div className="card mb-3" id="racks"><div className="card-header d-flex justify-content-between align-items-center"><span>{term('rack', true)} / locations</span>
        {can('warehouses.create') && <Link className="btn btn-sm btn-outline-primary" to={`/locations/create?warehouse=${w.id}`}>Add {term('rack').toLowerCase()}</Link>}</div>
        <div className="table-responsive"><table className="table table-sm mb-0 align-middle"><thead><tr><th>Code</th><th>Description</th><th className="text-end">Stock</th><th /></tr></thead><tbody>
          {d.racks.map((r: any) => <tr key={r.id}><td><Link to={`/locations/${r.id}`}><strong>{r.code}</strong></Link></td><td>{r.description ?? ''}</td><td className="text-end">{qty(r.qty)}</td><td>{Number(r.is_active) ? '' : <Badge tone="secondary">Inactive</Badge>}</td></tr>)}
          {!d.racks.length && <tr><td colSpan={4} className="text-muted">No {term('rack', true).toLowerCase()} yet.</td></tr>}</tbody></table></div></div>
      <div className="card mb-3" id="stock"><div className="card-header">What is stored here (top 25 by quantity)</div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>{term('item')}</th><th>SKU</th><th className="text-end">Qty</th></tr></thead><tbody>
          {d.stock.map((r: any, i: number) => <tr key={i}><td><Link to={`/items/${r.item_id}`}>{r.item_name}</Link>{r.vname ? ` — ${r.vname}` : ''}</td><td>{r.sku}</td><td className="text-end">{qty(r.qty)} {r.unit}</td></tr>)}
          {!d.stock.length && <tr><td colSpan={3} className="text-muted">Nothing in stock here.</td></tr>}</tbody></table></div></div>
    </RecordPage>
  );
}

export function WarehouseForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('warehouse');
  const src = useQuery({ queryKey: ['warehouse-form', id ?? 'new'], queryFn: async () => await get(id ? `/warehouses/${id}/edit` : '/warehouses/create') });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [active, setActive] = useState(true);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (src.data?.redirect) { nav('/' + String(src.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (!schema.data || !src.data?.data || values) return;
    const row = src.data.data.row ?? {}; const v: Record<string, any> = {};
    Object.keys(schema.data.fields).forEach((k) => (v[k] = row[k] ?? ''));
    seedCustom(schema.data, v, src.data.data.cfValues);
    setValues(v); setActive(row.is_active === undefined ? true : !!Number(row.is_active));
  }, [schema.data, src.data, values, nav]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const isDefault = !!Number(src.data?.data?.row?.is_default);
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = toPayload(schema.data!, values);
    if (id && active) body.is_active = 1;
    const r = await post(id ? `/warehouses/${id}` : '/warehouses', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc);
    nav('/warehouses');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={(id ? 'Edit ' : 'New ') + term('warehouse').toLowerCase()}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=warehouse&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav('/warehouses')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 720 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        {id && !isDefault && <div className="form-check mb-1"><input className="form-check-input" type="checkbox" id="act" checked={active} onChange={(e) => setActive(e.target.checked)} /><label className="form-check-label" htmlFor="act">Active (can receive and issue stock)</label></div>}
      </div></div>
    </form>
  );
}
