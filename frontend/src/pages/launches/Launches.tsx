import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { fdate, money, qty as fq } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import DocList from '../../components/DocList';
import ItemSearch from '../../components/ItemSearch';

const TONE: Record<string, string> = { draft: 'secondary', ordered: 'info', partial: 'warning', dispatched: 'primary', received: 'success', closed: 'dark', cancelled: 'danger' };
const DOC: Record<string, string> = { launch_issue: 'Fabric dispatched', launch_return: 'Fabric returned', launch_receipt: 'Books received' };
const refresh = (qc: ReturnType<typeof useQueryClient>) => ['launches', 'launch', 'launch-form', 'stock'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
const StatusBadge = ({ s, label }: { s: string; label: string }) => <Badge tone={TONE[s] ?? 'secondary'}>{label}</Badge>;
const catLabel = (r: { catalogue: string; catalogue_code?: string | null }) => <>{r.catalogue}{r.catalogue_code && r.catalogue_code !== r.catalogue ? <small className="text-muted"> · gutt {r.catalogue_code}</small> : null}</>;

/** Catalogue launches: fabric ordered from a warehouse for a new catalogue, and the printed books received back. */
export function LaunchList() {
  const { can } = useSession();
  return (
    <DocList title="Catalogue launches" api="/launches" queryKey="launches" empty="No launches yet. A launch is a new catalogue: order its fabric from the warehouse, then receive the printed books."
      actions={() => can('products.create') ? <Link className="btn btn-primary" to="/launches/create"><i className="bi bi-plus-lg" /> New launch</Link> : null}
      filters={(d, set, sp) => (
        <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q: (new FormData(e.currentTarget).get('q') as string) ?? '' }); }}>
          <div className="col-md-4"><input name="q" className="form-control" defaultValue={sp.get('q') ?? ''} placeholder="Launch no., catalogue or gutt no." /></div>
          <div className="col-md-3"><select className="form-select" value={sp.get('status') ?? ''} onChange={(e) => set({ status: e.target.value })}><option value="">All launches</option><option value="open">Not closed</option>{Object.entries(d.statuses).map(([k, l]) => <option key={k} value={k}>{l as string}</option>)}</select></div>
          <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
        </form>)}
      columns={[
        { head: 'Launch', cell: (r) => <Link to={`/launches/${r.id}`} className="fw-medium">{r.launch_no}</Link> },
        { head: 'Catalogue', cell: (r) => catLabel(r) },
        { head: 'Warehouse', cell: (r) => r.warehouse },
        { head: 'Status', cell: (r, d) => <StatusBadge s={r.status} label={d.statuses[r.status]} /> },
        { head: 'Fabric dispatched', className: 'text-end', cell: (r) => Number(r.fabric_ordered) ? <>{fq(r.fabric_issued)} / {fq(r.fabric_ordered)}</> : '—' },
        { head: 'Books received', className: 'text-end', cell: (r) => Number(r.copies_planned) || Number(r.copies_received) ? <>{r.copies_received} / {r.copies_planned}</> : '—' },
        { head: 'Created', cell: (r) => fdate(String(r.created_at).slice(0, 10)) },
      ]} />
  );
}

/* ---------------------------------------------------------------- create / edit */

type FLine = { variant_id: string; label: string; qty: string; note: string; unit: string };
type SRow = { size_name: string; qty: string };

export function LaunchForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const [sp] = useSearchParams(); const { term } = useSession();
  const res = useQuery({ queryKey: ['launch-form', id ?? 'new'], gcTime: 0, queryFn: async () => (await get(id ? `/launches/${id}/edit` : '/launches/create')).data });
  const [v, setV] = useState<{ catalogue_id: string; warehouse_id: string; ship_to: string; note: string } | null>(null);
  const [fabric, setFabric] = useState<FLine[]>([{ variant_id: '', label: '', qty: '', note: '', unit: '' }]);
  const [sizes, setSizes] = useState<SRow[]>([{ size_name: 'Large', qty: '' }, { size_name: 'Small', qty: '' }]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = res.data;
  useEffect(() => {
    if (!d || v) return;
    const l = d.launch;
    setV({ catalogue_id: String(l?.catalogue_id ?? d.preset?.catalogue_id ?? sp.get('catalogue') ?? '') === '0' ? '' : String(l?.catalogue_id ?? d.preset?.catalogue_id ?? sp.get('catalogue') ?? ''), warehouse_id: String(l?.warehouse_id ?? d.warehouses?.[0]?.id ?? ''), ship_to: l?.ship_to ?? '', note: l?.note ?? '' });
    if (l) {
      setFabric(d.fabric.length ? d.fabric.map((f: any) => ({ variant_id: String(f.variant_id), label: `${f.item_name} (${f.sku})`, qty: String(Number(f.qty_ordered)), note: f.note ?? '', unit: f.unit })) : [{ variant_id: '', label: '', qty: '', note: '', unit: '' }]);
      setSizes(d.sizes.length ? d.sizes.map((s: any) => ({ size_name: s.size_name, qty: String(s.qty_planned || '') })) : []);
    }
  }, [d, v, sp]);
  if (res.isLoading || !d || !v) return <Spinner />;
  const l = d.launch; const put = (patch: Partial<typeof v>) => setV({ ...v, ...patch });
  const setF = (i: number, p: Partial<FLine>) => setFabric((xs) => xs.map((x, k) => (k === i ? { ...x, ...p } : x)));
  const setS = (i: number, p: Partial<SRow>) => setSizes((xs) => xs.map((x, k) => (k === i ? { ...x, ...p } : x)));
  const save = async () => {
    setBusy(true); setMsgs([]);
    const r = await post(id ? `/launches/${id}` : '/launches', { ...v, fabric: fabric.filter((f) => f.variant_id).map(({ variant_id, qty, note }) => ({ variant_id, qty, note })), sizes: sizes.filter((s) => s.size_name.trim() || s.qty).map((s) => ({ size_name: s.size_name, qty: s.qty })) });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc); nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/launches');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={l ? `Edit ${l.launch_no}` : 'New catalogue launch'}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(l ? `/launches/${l.id}` : '/launches')}>Cancel</button>
        <button className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><div className="row g-3">
        <div className="col-md-4"><label className="form-label">Catalogue</label>
          <select className="form-select" required value={v.catalogue_id} onChange={(e) => put({ catalogue_id: e.target.value })}><option value="">Choose…</option>{d.catalogues.map((c: any) => <option key={c.id} value={c.id}>{c.name}{c.code && c.code !== c.name ? ` · gutt ${c.code}` : ''}</option>)}</select>
          <div className="form-text">Not there yet? <Link to="/catalogues/create">Create the catalogue</Link> with its gutt number first.</div></div>
        <div className="col-md-4"><label className="form-label">Order fabric from</label>
          <select className="form-select" required value={v.warehouse_id} onChange={(e) => put({ warehouse_id: e.target.value })}>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
        <div className="col-md-4"><label className="form-label">Note <span className="text-muted fw-normal">(optional)</span></label><input className="form-control" maxLength={255} value={v.note} onChange={(e) => put({ note: e.target.value })} /></div>
        <div className="col-12"><label className="form-label">Send the fabric to <span className="text-muted fw-normal">(the place the catalogue team wants it dispatched)</span></label>
          <input className="form-control" maxLength={255} value={v.ship_to} onChange={(e) => put({ ship_to: e.target.value })} placeholder="name and address of the press / binder" /></div>
      </div></div></div>

      <div className="card mb-3">
        <div className="card-header d-flex justify-content-between align-items-center"><span>Fabric needed</span><button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setFabric((xs) => [...xs, { variant_id: '', label: '', qty: '', note: '', unit: '' }])}><i className="bi bi-plus-lg" /> Add fabric</button></div>
        <div style={{ overflow: 'visible' }}><table className="table mb-0 align-middle">
          <thead><tr><th style={{ minWidth: 300 }}>{term('item')}</th><th style={{ width: 170 }}>Quantity needed</th><th>Note</th><th style={{ width: 44 }} /></tr></thead>
          <tbody>{fabric.map((f, i) => (
            <tr key={i}>
              <td><ItemSearch value={f.variant_id} label={f.label} placeholder="Search the fabric by name or SKU" onPick={(p) => setF(i, p ? { variant_id: String(p.id), label: p.label, unit: p.row.unit ?? '' } : { variant_id: '', label: '' })} /></td>
              <td><div className="input-group input-group-sm"><input type="number" step="0.001" min="0" className="form-control" value={f.qty} onChange={(e) => setF(i, { qty: e.target.value })} />{f.unit && <span className="input-group-text">{f.unit}</span>}</div></td>
              <td><input className="form-control form-control-sm" maxLength={150} value={f.note} onChange={(e) => setF(i, { note: e.target.value })} placeholder="optional" /></td>
              <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove" onClick={() => setFabric((xs) => (xs.length > 1 ? xs.filter((_, k) => k !== i) : [{ variant_id: '', label: '', qty: '', note: '', unit: '' }]))}>×</button></td>
            </tr>))}</tbody>
        </table></div>
      </div>

      <div className="card mb-4">
        <div className="card-header d-flex justify-content-between align-items-center"><span>Sizes of the book</span><button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setSizes((xs) => [...xs, { size_name: '', qty: '' }])}><i className="bi bi-plus-lg" /> Add size</button></div>
        <table className="table mb-0 align-middle">
          <thead><tr><th>Size</th><th style={{ width: 200 }}>Books planned</th><th style={{ width: 44 }} /></tr></thead>
          <tbody>{sizes.map((s, i) => (
            <tr key={i}>
              <td><input className="form-control form-control-sm" maxLength={60} value={s.size_name} onChange={(e) => setS(i, { size_name: e.target.value })} placeholder="Large, Small …" /></td>
              <td><input type="number" step="1" min="0" className="form-control form-control-sm" value={s.qty} onChange={(e) => setS(i, { qty: e.target.value })} /></td>
              <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove" onClick={() => setSizes((xs) => xs.filter((_, k) => k !== i))}>×</button></td>
            </tr>))}
            {!sizes.length && <tr><td colSpan={3} className="text-muted small">No sizes yet. You can add them later, before the books are received.</td></tr>}</tbody>
        </table>
      </div>
    </form>
  );
}

/* ---------------------------------------------------------------- detail */

export function LaunchDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const res = useQuery({ queryKey: ['launch', id], queryFn: async () => (await get(`/launches/${id}`)).data });
  if (res.isLoading) return <Spinner />;
  const d = res.data; if (!d) return <div className="alert alert-danger">Launch not found.</div>;
  const l = d.l; const c = d.can; const m = d.money;
  const act = async (path: string, ask?: string) => {
    if (ask && !confirm(ask)) return;
    const r = await post(`/launches/${id}/${path}`); setMsgs(r.messages ?? []); refresh(qc);
  };
  return (
    <>
      <PageHead title={<>{l.launch_no} <StatusBadge s={l.status} label={d.statuses[l.status]} /></>}>
        <Link className="btn btn-outline-secondary" to="/launches">All launches</Link>
        {c.edit && can('products.edit') && <Link className="btn btn-outline-primary" to={`/launches/${l.id}/edit`}>Edit</Link>}
        {c.order && can('products.edit') && <button className="btn btn-primary" onClick={() => act('order', `Send this fabric order to ${l.warehouse}?`)}><i className="bi bi-send" /> Send order to warehouse</button>}
        {c.dispatch && can('stock.transfer') && <Link className="btn btn-primary" to={`/launches/${l.id}/dispatch`}><i className="bi bi-truck" /> Dispatch fabric</Link>}
        {c.return && can('stock.transfer') && <Link className="btn btn-outline-danger" to={`/launches/${l.id}/return`}>Wrong fabric came back</Link>}
        {c.receive && can('stock.receive') && <Link className="btn btn-success" to={`/launches/${l.id}/receive`}><i className="bi bi-box-arrow-in-down" /> Receive catalogues</Link>}
        {c.close && can('products.edit') && <button className="btn btn-outline-dark" onClick={() => act('close', 'Close this launch?')}>Close</button>}
        {c.cancel && can('products.edit') && <button className="btn btn-outline-danger" onClick={() => act('cancel', 'Cancel this launch?')}>Cancel launch</button>}
      </PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="card mb-3"><div className="card-body"><div className="row g-3">
        <div className="col-md-3"><div className="text-muted small">Catalogue</div><Link to={`/catalogues/${l.catalogue_id}`}>{l.catalogue}</Link>{l.catalogue_code && l.catalogue_code !== l.catalogue ? <div className="small text-muted">gutt {l.catalogue_code}</div> : null}</div>
        <div className="col-md-3"><div className="text-muted small">Fabric comes from</div>{l.warehouse}</div>
        <div className="col-md-4"><div className="text-muted small">Fabric is sent to</div>{l.ship_to || <span className="text-muted">—</span>}</div>
        <div className="col-md-2"><div className="text-muted small">Created</div>{fdate(String(l.created_at).slice(0, 10))}<div className="small text-muted">{l.user_name}</div></div>
        {l.note ? <div className="col-12 small text-muted">{l.note}</div> : null}
      </div></div></div>

      <div className="card mb-3"><div className="card-header">Fabric</div><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th className="text-end">Ordered</th><th className="text-end">Dispatched</th><th className="text-end">Came back</th><th className="text-end">Still due</th><th className="text-end">Cost</th></tr></thead>
        <tbody>
          {d.fabric.map((f: any) => { const net = Number(f.qty_issued) - Number(f.qty_returned); const due = Math.max(0, Number(f.qty_ordered) - net);
            return <tr key={f.id}><td>{f.item_name} <small className="text-muted">{f.sku}</small>{f.note ? <div className="small text-muted">{f.note}</div> : null}</td><td className="text-end">{fq(f.qty_ordered)} {f.unit}</td><td className="text-end">{fq(f.qty_issued)}</td>
              <td className="text-end">{Number(f.qty_returned) > 0 ? <span className="text-danger">{fq(f.qty_returned)}</span> : ''}</td><td className="text-end">{due > 0.0005 ? fq(due) : <span className="text-success"><i className="bi bi-check-circle" /></span>}</td><td className="text-end">{money(f.value_issued)}</td></tr>; })}
          {!d.fabric.length && <tr><td colSpan={6} className="text-muted">No fabric on the order yet.</td></tr>}</tbody>
        {d.fabric.length > 0 && <tfoot><tr><th colSpan={5} className="text-end">Fabric cost</th><th className="text-end">{money(m.fabric_value)}</th></tr></tfoot>}
      </table></div></div>

      <div className="row g-3 mb-3">
        <div className="col-lg-7"><div className="card h-100"><div className="card-header">Sizes</div><div className="table-responsive"><table className="table mb-0 align-middle">
          <thead><tr><th>Size</th><th className="text-end">Planned</th><th className="text-end">Received</th><th>Stocked as</th></tr></thead>
          <tbody>
            {d.sizes.map((s: any) => <tr key={s.id}><td className="fw-medium">{s.size_name}</td><td className="text-end">{s.qty_planned}</td><td className="text-end">{s.qty_received}</td><td>{s.variant_id ? <Link to={`/items/${s.item_id}`} className="small">open item</Link> : <span className="text-muted small">made when books arrive</span>}</td></tr>)}
            {!d.sizes.length && <tr><td colSpan={4} className="text-muted">No sizes yet. Add them with Edit.</td></tr>}</tbody></table></div></div></div>
        <div className="col-lg-5"><div className="card h-100"><div className="card-header">Cost</div><div className="card-body"><table className="table table-sm table-borderless mb-0"><tbody>
          <tr><td className="text-muted">Fabric</td><td className="text-end">{money(m.fabric_value)}</td></tr>
          <tr><td className="text-muted">Printing, binding … (extra)</td><td className="text-end">{money(m.extra_cost)}</td></tr>
          <tr className="border-top"><th>Total</th><th className="text-end">{money(m.total_cost)}</th></tr>
          <tr><td className="text-muted">Books received</td><td className="text-end">{m.copies}</td></tr>
          <tr><td className="text-muted">Cost of one book</td><td className="text-end">{m.cost_per_copy === null ? '—' : money(m.cost_per_copy)}</td></tr></tbody></table></div></div></div>
      </div>

      <div className="card mb-3"><div className="card-header">Documents</div>
        <div className="list-group list-group-flush">
          {d.docs.map((x: any) => (
            <details key={x.id} className="list-group-item">
              <summary className="d-flex flex-wrap gap-2 align-items-center"><b>{x.doc_no}</b><Badge tone={x.type === 'launch_receipt' ? 'success' : x.type === 'launch_return' ? 'danger' : 'primary'}>{DOC[x.type]}</Badge><span className="text-muted small">{fdate(String(x.created_at).slice(0, 10))} · {x.warehouse} · {x.user_name}</span>{x.note ? <span className="small text-muted">· {x.note}</span> : null}</summary>
              <table className="table table-sm mt-2 mb-0"><tbody>{x.lines.map((r: any, k: number) => <tr key={k}><td>{r.item_name} <small className="text-muted">{r.sku}</small></td><td>{r.batch_no ?? ''}</td><td>{r.rack ?? ''}</td><td className="text-end">{fq(r.qty)}</td><td className="text-end text-muted">{money(r.unit_cost)}</td></tr>)}</tbody></table>
            </details>))}
          {!d.docs.length && <div className="list-group-item text-muted">Nothing dispatched or received yet.</div>}
        </div></div>
      <button className="d-none" onClick={() => nav('/launches')} />
    </>
  );
}

/* ---------------------------------------------------------------- dispatch the fabric / take wrong fabric back */

type Roll = { variant_id: string; batch_id: string; location_id: string; qty: string };

/** One fabric line of a dispatch: roll rows (roll, rack, quantity) that add up to what is wanted. */
function FabricBlock({ f, warehouse, racks, rows, setRows, due, term }: { f: any; warehouse: string; racks: { id: number; code: string }[]; rows: Roll[]; setRows: (r: Roll[]) => void; due: number; term: (k: string, p?: boolean) => string }) {
  const rolls = useQuery({ queryKey: ['batch-options', f.variant_id, warehouse], enabled: !!f.track_batch, queryFn: async () => (await get(`/stock/batch-options?variant=${f.variant_id}&warehouse=${warehouse}&in_stock=1`)) as unknown as any[] });
  const set = (i: number, p: Partial<Roll>) => setRows(rows.map((r, k) => (k === i ? { ...r, ...p } : r)));
  const total = rows.reduce((a, r) => a + (Number(r.qty) || 0), 0);
  return (
    <div className="card mb-3">
      <div className="card-header d-flex justify-content-between flex-wrap gap-2"><span><b>{f.item_name}</b> <small className="text-muted">{f.sku}</small></span><span className="small">Still due <b>{fq(due)} {f.unit}</b> · dispatching <b>{fq(total)}</b></span></div>
      <table className="table table-sm mb-0 align-middle">
        <thead><tr>{f.track_batch ? <th>{term('batch')}</th> : null}<th style={{ width: 200 }}>{term('rack')}</th><th style={{ width: 150 }}>Quantity</th><th style={{ width: 44 }} /></tr></thead>
        <tbody>{rows.map((r, i) => (
          <tr key={i}>
            {f.track_batch ? <td><select className="form-select form-select-sm" value={r.batch_id} onChange={(e) => set(i, { batch_id: e.target.value })}><option value="">{rolls.isLoading ? 'Loading…' : (rolls.data ?? []).length ? `Choose ${term('batch').toLowerCase()}…` : 'No stock in this warehouse'}</option>
              {(rolls.data ?? []).map((b: any) => <option key={b.id} value={b.id}>{b.batch_no}{b.supplier_lot ? ` · lot ${b.supplier_lot}` : ''} · balance {parseFloat(b.balance)}</option>)}</select></td> : null}
            <td><select className="form-select form-select-sm" value={r.location_id} onChange={(e) => set(i, { location_id: e.target.value })}><option value="0">No {term('rack').toLowerCase()}</option>{racks.map((k) => <option key={k.id} value={k.id}>{k.code}</option>)}</select></td>
            <td><div className="input-group input-group-sm"><input type="number" step={f.allow_decimal ? '0.001' : '1'} min="0" className="form-control" value={r.qty} onChange={(e) => set(i, { qty: e.target.value })} /><span className="input-group-text">{f.unit}</span></div></td>
            <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove" onClick={() => setRows(rows.length > 1 ? rows.filter((_, k) => k !== i) : [{ ...r, qty: '', batch_id: '' }])}>×</button></td>
          </tr>))}</tbody>
      </table>
      <div className="card-footer bg-transparent"><button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setRows([...rows, { variant_id: String(f.variant_id), batch_id: '', location_id: '0', qty: '' }])}><i className="bi bi-plus-lg" /> Add another {f.track_batch ? term('batch').toLowerCase() : 'line'}</button></div>
    </div>
  );
}

export function LaunchDispatch() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const { term } = useSession();
  const res = useQuery({ queryKey: ['launch-form', 'dispatch', id], gcTime: 0, queryFn: async () => await get(`/launches/${id}/dispatch`) });
  const [rows, setRows] = useState<Record<string, Roll[]> | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = res.data?.data;
  useEffect(() => {
    if (res.data?.redirect) { nav('/' + String(res.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (!d || rows) return;
    const o: Record<string, Roll[]> = {};
    for (const f of d.fabric) { const left = Math.max(0, Number(f.qty_ordered) - (Number(f.qty_issued) - Number(f.qty_returned))); o[f.variant_id] = [{ variant_id: String(f.variant_id), batch_id: '', location_id: '0', qty: !f.track_batch && left > 0 ? String(left) : '' }]; }
    setRows(o);
  }, [res.data, d, rows, nav]);
  if (res.isLoading || !d || !rows) return <Spinner />;
  const l = d.l; const racks = d.racks[String(l.warehouse_id)] ?? [];
  const save = async () => {
    setBusy(true); setMsgs([]);
    const r = await post(`/launches/${id}/dispatch`, { lines: Object.values(rows).flat().filter((x) => Number(x.qty) > 0) });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc); nav(`/launches/${id}`);
  };
  return (
    <>
      <PageHead title={`Dispatch fabric · ${l.launch_no}`}>
        <button className="btn btn-outline-secondary" onClick={() => nav(`/launches/${id}`)}>Cancel</button>
        <button className="btn btn-success" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Dispatch'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><div className="row"><div className="col-md-4"><div className="text-muted small">Catalogue</div>{l.catalogue}</div><div className="col-md-4"><div className="text-muted small">Dispatching from</div>{l.warehouse}</div>
        <div className="col-md-4"><div className="text-muted small">Send to</div><b>{l.ship_to || '—'}</b></div></div></div></div>
      <p className="text-muted small">Choose the roll and the rack for each fabric, and how much goes. Several rolls can go for one fabric: add another line.</p>
      {d.fabric.map((f: any) => {
        const due = Math.max(0, Number(f.qty_ordered) - (Number(f.qty_issued) - Number(f.qty_returned)));
        return <FabricBlock key={f.id} f={f} warehouse={String(l.warehouse_id)} racks={racks} rows={rows[f.variant_id] ?? []} setRows={(r) => setRows({ ...rows, [f.variant_id]: r })} due={due} term={term} />;
      })}
    </>
  );
}

export function LaunchReturn() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const { term } = useSession();
  const res = useQuery({ queryKey: ['launch-form', 'return', id], gcTime: 0, queryFn: async () => await get(`/launches/${id}/return`) });
  const [qtys, setQtys] = useState<Record<string, { qty: string; location_id: string }>>({}); const [note, setNote] = useState(''); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = res.data?.data;
  useEffect(() => { if (res.data?.redirect) nav('/' + String(res.data.redirect).replace(/^\//, ''), { replace: true }); }, [res.data, nav]);
  if (res.isLoading || !d) return <Spinner />;
  const l = d.l; const racks = d.racks[String(l.warehouse_id)] ?? []; const fab: Record<string, any> = {};
  d.fabric.forEach((f: any) => { fab[String(f.variant_id)] = f; });
  const key = (r: any) => `${r.variant_id}:${r.batch_id}`;
  const save = async () => {
    setBusy(true); setMsgs([]);
    const lines = d.rolls.filter((r: any) => Number(qtys[key(r)]?.qty) > 0).map((r: any) => ({ variant_id: r.variant_id, batch_id: r.batch_id, location_id: qtys[key(r)].location_id || '0', qty: qtys[key(r)].qty }));
    const r = await post(`/launches/${id}/return`, { lines, note });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc); nav(`/launches/${id}`);
  };
  return (
    <>
      <PageHead title={`Wrong fabric came back · ${l.launch_no}`}>
        <button className="btn btn-outline-secondary" onClick={() => nav(`/launches/${id}`)}>Cancel</button>
        <button className="btn btn-danger" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Take back into stock'}</button>
      </PageHead>
      <Messages items={msgs} />
      <p className="text-muted">Fabric normally does not come back. Use this only when the wrong fabric was sent: write how much of each roll is returned and where it is put on the shelf in {l.warehouse}. The order opens again for the right fabric.</p>
      <div className="card mb-3"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th className="text-end">Out on this launch</th><th style={{ width: 200 }}>Put on {term('rack').toLowerCase()}</th><th style={{ width: 160 }}>Quantity back</th></tr></thead>
        <tbody>
          {d.rolls.map((r: any) => { const f = fab[String(r.variant_id)]; const k = key(r); const cur = qtys[k] ?? { qty: '', location_id: '0' }; const out = Number(r.issued) - Number(r.returned);
            return <tr key={k}><td>{f?.item_name} <small className="text-muted">{f?.sku}</small></td><td>{r.batch_no ?? '—'}</td><td className="text-end">{fq(out)} {f?.unit}</td>
              <td><select className="form-select form-select-sm" value={cur.location_id} onChange={(e) => setQtys({ ...qtys, [k]: { ...cur, location_id: e.target.value } })}><option value="0">No {term('rack').toLowerCase()}</option>{racks.map((x: any) => <option key={x.id} value={x.id}>{x.code}</option>)}</select></td>
              <td><div className="input-group input-group-sm"><input type="number" step="0.001" min="0" max={out} className="form-control" value={cur.qty} onChange={(e) => setQtys({ ...qtys, [k]: { ...cur, qty: e.target.value } })} /><span className="input-group-text">{f?.unit}</span></div></td></tr>; })}
          {!d.rolls.length && <tr><td colSpan={5} className="text-muted">Nothing is out on this launch.</td></tr>}
        </tbody></table></div></div>
      <div className="mb-3" style={{ maxWidth: 520 }}><label className="form-label">Why <span className="text-muted fw-normal">(optional)</span></label><input className="form-control" maxLength={255} value={note} onChange={(e) => setNote(e.target.value)} placeholder="for example wrong shade sent" /></div>
    </>
  );
}

/* ---------------------------------------------------------------- the printed books arrive */

export function LaunchReceive() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const { term } = useSession();
  const res = useQuery({ queryKey: ['launch-form', 'receive', id], gcTime: 0, queryFn: async () => await get(`/launches/${id}/receive`) });
  const [wh, setWh] = useState(''); const [rows, setRows] = useState<Record<string, { qty: string; location_id: string }>>({}); const [extra, setExtra] = useState(''); const [note, setNote] = useState('');
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = res.data?.data;
  useEffect(() => { if (res.data?.redirect) nav('/' + String(res.data.redirect).replace(/^\//, ''), { replace: true }); }, [res.data, nav]);
  useEffect(() => { if (d && !wh) setWh(String(d.l.warehouse_id)); }, [d, wh]);
  if (res.isLoading || !d || !wh) return <Spinner />;
  const l = d.l; const m = d.money; const racks = d.racks[wh] ?? [];
  const copies = d.sizes.reduce((a: number, s: any) => a + (Number(rows[s.id]?.qty) || 0), 0);
  const pool = Number(m.unabsorbed) + (Number(extra) || 0); const unit = copies > 0 ? pool / copies : null;
  const save = async () => {
    setBusy(true); setMsgs([]);
    const r = await post(`/launches/${id}/receive`, { warehouse_id: wh, extra_cost: extra, note, lines: d.sizes.filter((s: any) => Number(rows[s.id]?.qty) > 0).map((s: any) => ({ size_id: s.id, qty: rows[s.id].qty, location_id: rows[s.id].location_id || '0' })) });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc); nav(`/launches/${id}`);
  };
  return (
    <>
      <PageHead title={`Receive catalogues · ${l.launch_no}`}>
        <button className="btn btn-outline-secondary" onClick={() => nav(`/launches/${id}`)}>Cancel</button>
        <button className="btn btn-success" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Receive into stock'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><div className="row g-3">
        <div className="col-md-4"><label className="form-label">Catalogue</label><input className="form-control" disabled value={l.catalogue} /></div>
        <div className="col-md-4"><label className="form-label">Receive into</label>
          <select className="form-select" value={wh} onChange={(e) => { setWh(e.target.value); setRows(Object.fromEntries(Object.entries(rows).map(([k, v]) => [k, { ...v, location_id: '0' }]))); }}>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
          <div className="form-text">The warehouse, or the catalogue department.</div></div>
        <div className="col-md-4"><label className="form-label">Extra cost <span className="text-muted fw-normal">(printing, binding …)</span></label><input type="number" step="0.01" min="0" className="form-control" value={extra} onChange={(e) => setExtra(e.target.value)} /></div>
      </div></div></div>
      <div className="card mb-3"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>Size</th><th className="text-end">Planned</th><th className="text-end">Received so far</th><th style={{ width: 200 }}>{term('rack')}</th><th style={{ width: 170 }}>Books received now</th></tr></thead>
        <tbody>{d.sizes.map((s: any) => { const cur = rows[s.id] ?? { qty: '', location_id: '0' };
          return <tr key={s.id}><td className="fw-medium">{s.size_name}</td><td className="text-end">{s.qty_planned}</td><td className="text-end">{s.qty_received}</td>
            <td><select className="form-select form-select-sm" value={cur.location_id} onChange={(e) => setRows({ ...rows, [s.id]: { ...cur, location_id: e.target.value } })}><option value="0">No {term('rack').toLowerCase()}</option>{racks.map((x: any) => <option key={x.id} value={x.id}>{x.code}</option>)}</select></td>
            <td><input type="number" step="1" min="0" className="form-control form-control-sm" value={cur.qty} onChange={(e) => setRows({ ...rows, [s.id]: { ...cur, qty: e.target.value } })} /></td></tr>; })}
          {!d.sizes.length && <tr><td colSpan={5} className="text-muted">This launch has no sizes. Go back and add them with Edit.</td></tr>}</tbody></table></div></div>
      <div className="row g-3 mb-4">
        <div className="col-md-6"><label className="form-label">Note <span className="text-muted fw-normal">(optional)</span></label><input className="form-control" maxLength={255} value={note} onChange={(e) => setNote(e.target.value)} /></div>
        <div className="col-md-6"><div className="border rounded p-2 small bg-body-tertiary">Fabric not yet counted into cost: <b>{money(m.unabsorbed)}</b> + extra <b>{money(Number(extra) || 0)}</b>{unit !== null ? <> over {copies} books = <b>{money(unit)}</b> a book</> : <> — enter the books received to see the cost of one</>}.</div></div>
      </div>
    </>
  );
}
