import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, PageHead, Spinner } from '../../components/ui';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';

const whSelect = (d: any, sp: URLSearchParams, term: (k: string, p?: boolean) => string) => (
  <div className="col-md-3"><select name="warehouse" className="form-select" defaultValue={sp.get('warehouse') ?? ''}><option value="">All {term('warehouse', true).toLowerCase()}</option>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
);
const when = (s: string) => <span className="text-nowrap">{fdate(String(s).slice(0, 10))} <span className="text-muted">{String(s).slice(11, 16)}</span></span>;
const change = (v: any) => <span className={Number(v) < 0 ? 'text-danger' : 'text-success'}>{Number(v) > 0 ? '+' : ''}{qty(v)}</span>;
export const batchTone = (st: string) => (st === 'Finished' ? 'secondary' : st === 'Available' ? 'success' : 'info');
export const batchState = (balance: number, received: number) => (balance <= 0.0005 ? 'Finished' : balance < received - 0.0005 ? 'Partially used' : 'Available');

/** Stock on hand per item variant, with rack split, reserved quantity, value and low-stock flag. */
export function StockOverview() {
  const { can, term } = useSession();
  return (
    <DocList title="Stock" api="/stock" queryKey="stock" empty="No stock to show."
      filters={(d, set, sp) => (
        <Filters names={['q', 'warehouse', 'low']} sp={sp} set={set} right={can('stock.adjust') ? <Link className="btn btn-primary" to="/stock/adjustments/create"><i className="bi bi-plus-lg" /> Adjust / opening stock</Link> : null}>
          <div className="col-md-4"><input name="q" className="form-control" placeholder="Search name, SKU, barcode" defaultValue={sp.get('q') ?? ''} /></div>
          {whSelect(d, sp, term)}
          <div className="col-auto form-check d-flex align-items-center ms-2"><input className="form-check-input me-2" type="checkbox" name="low" value="1" id="low" defaultChecked={!!sp.get('low')} /><label htmlFor="low" className="form-check-label">Low stock only</label></div>
        </Filters>)}
      above={(d) => <div className="card mb-3"><div className="card-body py-2"><span className="text-muted">Stock value{d.wh ? ' (selected warehouse)' : ''}:</span> <strong>{money(d.totalValue)}</strong>
        <span className="text-muted small ms-2">quantity × {term('batch').toLowerCase()} cost (or {term('item').toLowerCase()} cost)</span></div></div>}
      columns={[
        { head: term('item'), cell: (r) => <Link to={`/items/${r.item_id}`}>{r.name}{r.vname ? ` — ${r.vname}` : ''}</Link> }, { head: 'SKU', cell: (r) => r.sku },
        { head: 'On hand', className: 'text-end', cell: (r) => `${qty(r.qty)} ${r.unit}` },
        { head: 'Reserved', className: 'text-end', cell: (r) => Number(r.reserved) > 0.0005 ? <span className="text-warning">{qty(r.reserved)}<div className="small text-muted">free {qty(r.qty - r.reserved)}</div></span> : <span className="text-muted">—</span> },
        { head: `Where (${term('rack', true).toLowerCase()})`, className: 'small', cell: (r, d) => <>{(r.racks ?? []).slice(0, 4).map((k: any, i: number) => <span key={i} className={`badge text-bg-${k.code === '' ? 'warning' : 'primary'} me-1`}>{d.wh ? '' : `${k.warehouse} · `}{k.code !== '' ? k.code : 'No rack'}: {qty(k.qty)}</span>)}{(r.racks ?? []).length > 4 ? '…' : ''}</> },
        { head: term('rolls'), className: 'text-end', cell: (r) => Number(r.track_batch) ? r.batches : '—' }, { head: 'Value', className: 'text-end', cell: (r) => money(r.value) },
        { head: 'Reorder', cell: (r) => Number(r.reorder_level) > 0 && Number(r.item_total) <= Number(r.reorder_level) ? <Badge tone="danger">Low (≤ {qty(r.reorder_level)})</Badge> : null },
      ]} />
  );
}

/** Where every item sits: one row per rack / roll. */
export function StockRacks() {
  const { term } = useSession();
  return (
    <DocList title="Stock by rack" api="/stock/racks" queryKey="stock-racks" empty="Nothing found."
      filters={(d, set, sp) => (
        <Filters names={['warehouse', 'q']} sp={sp} set={set}>
          {whSelect(d, sp, term)}
          <div className="col-md-4"><input name="q" className="form-control" placeholder="Rack code, item, SKU or batch no." defaultValue={sp.get('q') === '-' ? '' : sp.get('q') ?? ''} /></div>
          <div className="col-auto"><button type="button" className="btn btn-outline-warning" onClick={() => set({ q: '-' })}>Only "no {term('rack').toLowerCase()}"</button></div>
        </Filters>)}
      columns={[
        { head: term('warehouse'), cell: (r) => r.warehouse }, { head: term('rack'), cell: (r) => r.rack ? <strong>{r.rack}</strong> : <Badge tone="warning">No rack</Badge> },
        { head: term('item'), cell: (r) => <Link to={`/items/${r.item_id}`}>{r.item_name}{r.vname ? ` — ${r.vname}` : ''}</Link> }, { head: 'SKU', cell: (r) => r.sku },
        { head: term('batch'), cell: (r) => r.batch_no ? <Link to={`/stock/batches/${r.batch_id}`}>{r.batch_no}</Link> : '' }, { head: 'Qty', className: 'text-end', cell: (r) => `${qty(r.qty)} ${r.unit}` },
      ]} />
  );
}

/** Every roll (batch) with its remaining balance. */
export function StockBatches() {
  const { term } = useSession();
  return (
    <DocList title={`${term('rolls')} (rolls)`} api="/stock/batches" queryKey="stock-batches"
      empty={`No ${term('rolls').toLowerCase()} found. ${term('rolls')} are created when stock is added to an ${term('item').toLowerCase()} that has "Track by ${term('batch').toLowerCase()}" turned on.`}
      filters={(_d, set, sp) => (
        <Filters names={['q', 'status']} sp={sp} set={set}>
          <div className="col-md-4"><input name="q" className="form-control" placeholder="Batch no., supplier lot, item, SKU" defaultValue={sp.get('q') ?? ''} /></div>
          <div className="col-md-3"><select name="status" className="form-select" defaultValue={sp.get('status') ?? 'active'}><option value="active">In stock</option><option value="finished">Finished</option><option value="all">All</option></select></div>
        </Filters>)}
      columns={[
        { head: term('batch'), cell: (r) => <Link to={`/stock/batches/${r.id}`}>{r.batch_no}</Link> }, { head: term('item'), cell: (r) => `${r.item_name}${r.vname ? ` — ${r.vname}` : ''}` },
        { head: `${term('supplier')} lot`, cell: (r) => r.supplier_lot ?? '' }, { head: 'Received', cell: (r) => fdate(r.received_date) },
        { head: 'Roll size', className: 'text-end', cell: (r) => `${qty(r.received_qty)} ${r.unit}` }, { head: 'Balance', className: 'text-end', cell: (r) => `${qty(r.balance)} ${r.unit}` },
        { head: 'Status', cell: (r) => { const st = batchState(Number(r.balance), Number(r.received_qty)); return <Badge tone={batchTone(st)}>{st}</Badge>; } },
      ]} />
  );
}

/** One roll: size, balance, where it is and its whole history. */
export function StockBatch() {
  const { id } = useParams(); const { term, session } = useSession();
  const q = useQuery({ queryKey: ['stock-batch', id], queryFn: async () => (await get(`/stock/batches/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">{term('batch')} not found.</div>;
  const b = d.b; const st = batchState(Number(d.balance), Number(b.received_qty)); const types = session?.stockTypes ?? d.types ?? {};
  return (
    <>
      <PageHead title={`${term('batch')} ${b.batch_no}`}><Link className="btn btn-outline-secondary" to="/stock/batches">All {term('rolls').toLowerCase()}</Link></PageHead>
      <div className="row g-3 mb-3">
        <div className="col-md-4"><div className="card"><div className="card-body">
          <div className="text-muted small">{term('item')}</div><Link to={`/items/${b.item_id}`}>{b.item_name}{b.vname ? ` — ${b.vname}` : ''}</Link>
          <div className="text-muted small mt-2">SKU</div>{b.sku}
          <div className="text-muted small mt-2">{term('supplier')} lot</div>{b.supplier_lot || '—'}
          <div className="text-muted small mt-2">Received</div>{fdate(b.received_date)} · cost {money(b.unit_cost)}/{b.unit}
        </div></div></div>
        <div className="col-md-4"><div className="card"><div className="card-body">
          <div className="text-muted small">Roll size (received)</div><div className="fs-4">{qty(b.received_qty)} {b.unit}</div>
          <div className="text-muted small mt-2">Balance now</div><div className="fs-4">{qty(d.balance)} {b.unit} <Badge tone={batchTone(st)}>{st}</Badge></div>
          <div className="text-muted small mt-2">Used so far</div>{qty(Math.max(0, Number(b.received_qty) - Number(d.balance)))} {b.unit}
        </div></div></div>
        <div className="col-md-4"><div className="card"><div className="card-header">Where it is</div><ul className="list-group list-group-flush">
          {d.where.map((w: any, i: number) => <li key={i} className="list-group-item d-flex justify-content-between"><span>{w.name} · <i className="bi bi-geo-alt" /> {w.rack !== '' ? w.rack : 'No rack'}</span><strong>{qty(w.qty)} {b.unit}</strong></li>)}
          {!d.where.length && <li className="list-group-item text-muted">No stock left.</li>}
        </ul></div></div>
      </div>
      <div className="card"><div className="card-header">History of this {term('batch').toLowerCase()}</div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>{term('warehouse')}</th><th>{term('rack')}</th><th className="text-end">Qty</th><th>By</th><th>Note</th></tr></thead><tbody>
          {d.history.map((h: any, i: number) => <tr key={i}><td>{when(h.created_at)}</td><td>{types[h.type] ?? h.type}</td><td>{h.warehouse}</td><td>{h.rack ?? ''}</td><td className="text-end">{change(h.qty_change)}</td><td>{h.user_name ?? ''}</td><td>{h.note ?? ''}</td></tr>)}
        </tbody></table></div></div>
    </>
  );
}

/** Every stock movement, newest first. */
export function StockLedger() {
  const { term, session } = useSession();
  const types: Record<string, string> = session?.stockTypes ?? {};
  return (
    <DocList title="Stock ledger" api="/stock/ledger" queryKey="stock-ledger" empty="No movements found."
      filters={(d, set, sp) => (
        <Filters names={['q', 'type', 'warehouse', 'from', 'to']} sp={sp} set={set}>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="Item, SKU, batch no., rack" defaultValue={sp.get('q') ?? ''} /></div>
          <div className="col-md-2"><select name="type" className="form-select" defaultValue={sp.get('type') ?? ''}><option value="">All types</option>{Object.entries(d.types as Record<string, string>).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></div>
          <div className="col-md-2"><select name="warehouse" className="form-select" defaultValue={sp.get('warehouse') ?? ''}><option value="">All {term('warehouse', true).toLowerCase()}</option>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
          <div className="col-md-2"><input type="date" name="from" className="form-control" defaultValue={sp.get('from') ?? ''} /></div>
          <div className="col-md-2"><input type="date" name="to" className="form-control" defaultValue={sp.get('to') ?? ''} /></div>
        </Filters>)}
      columns={[
        { head: 'When', cell: (r) => when(r.created_at) }, { head: 'Type', cell: (r) => types[r.type] ?? r.type },
        { head: term('item'), cell: (r) => <>{r.item_name}{r.vname ? ` — ${r.vname}` : ''} <small className="text-muted">{r.sku}</small></> },
        { head: term('warehouse'), cell: (r) => r.warehouse }, { head: term('rack'), cell: (r) => r.rack ?? '' },
        { head: term('batch'), cell: (r) => r.batch_no ? <Link to={`/stock/batches/${r.batch_id}`}>{r.batch_no}</Link> : '' },
        { head: 'Qty', className: 'text-end', cell: (r) => change(r.qty_change) }, { head: 'By', cell: (r) => r.user_name ?? '' }, { head: 'Note', cell: (r) => r.note ?? '' },
      ]} />
  );
}
