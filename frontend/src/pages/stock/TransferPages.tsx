import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, Spinner } from '../../components/ui';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';
import RecordPage from '../../components/RecordPage';

const stamp = (s?: string | null) => (s ? `${fdate(String(s).slice(0, 10))} ${String(s).slice(11, 16)}` : '—');
const refresh = (qc: ReturnType<typeof useQueryClient>) => ['stock-docs', 'stock-doc', 'record', 'stock', 'stock-racks', 'stock-batches', 'stock-batch', 'stock-ledger', 'dashboard', 'report'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
const EVENT: Record<string, string> = { dispatch: 'Dispatched', receive: 'Received', lost: 'Written off (lost / damaged)', returned: 'Sent back to sender', cancel: 'Dispatch cancelled' };
const late = (r: any) => ['in_transit', 'partial'].includes(r.status) && !!r.expected_date && String(r.expected_date).slice(0, 10) < new Date().toISOString().slice(0, 10);

function StatusBadge({ r, statuses }: { r: any; statuses: Record<string, [string, string]> }) {
  const [label, tone] = statuses[r.status] ?? [r.status, 'secondary'];
  return <><Badge tone={tone}>{label}</Badge>{late(r) && <> <Badge tone="danger">overdue</Badge></>}</>;
}

export function TransferList() {
  const { can, term } = useSession();
  return (
    <DocList title="Stock transfers" api="/stock/transfers" queryKey="stock-docs" empty="No transfers yet."
      actions={() => can('stock.transfer') ? <Link className="btn btn-primary" to="/stock/transfers/create"><i className="bi bi-plus-lg" /> New transfer</Link> : null}
      filters={(d, set, sp) => (
        <Filters names={['status', 'warehouse']} sp={sp} set={set}>
          <div className="col-md-3"><select name="status" className="form-select" defaultValue={sp.get('status') ?? ''}><option value="">All transfers</option><option value="open">Still on the road</option><option value="overdue">Overdue</option>
            {Object.entries(d.statuses as Record<string, [string, string]>).map(([k, [l]]) => <option key={k} value={k}>{l}</option>)}</select></div>
          <div className="col-md-3"><select name="warehouse" className="form-select" defaultValue={sp.get('warehouse') ?? ''}><option value="">Any {term('warehouse').toLowerCase()} (sending or receiving)</option>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
        </Filters>)}
      above={(d) => (
        <div className="row g-2 mb-3">
          {[['On the road', d.counts.open, 'open', 'warning'], ['Overdue', d.counts.overdue, 'overdue', 'danger'], ['Closed with a shortage', d.counts.short, 'received_short', 'secondary']].map(([l, n, st, tone]) => (
            <div className="col-auto" key={String(st)}><Link to={`/stock/transfers?status=${st}`} className={`btn btn-sm btn-outline-${tone}`}>{l}: <strong>{n}</strong></Link></div>))}
          <div className="col text-muted small d-flex align-items-center">Between {term('warehouse', true).toLowerCase()} the stock is <strong className="mx-1">in transit</strong> until the other side confirms what arrived.</div>
        </div>)}
      columns={[
        { head: 'No.', cell: (r) => <Link to={`/stock/transfers/${r.id}`}>{r.doc_no}</Link> }, { head: 'Dispatched', cell: (r) => stamp(r.dispatched_at ?? r.created_at) },
        { head: 'From', cell: (r) => r.warehouse }, { head: 'To', cell: (r) => r.to_warehouse }, { head: 'Lines', cell: (r) => r.line_count },
        { head: 'Sent', className: 'text-end', cell: (r) => qty(r.qty_sent) },
        { head: 'Received', className: 'text-end', cell: (r) => qty(r.qty_received) },
        { head: 'On the road', className: 'text-end', cell: (r) => Number(r.qty_pending) > 0.0005 ? <strong>{qty(r.qty_pending)}</strong> : <span className="text-muted">—</span> },
        { head: 'Status', cell: (r, d) => <StatusBadge r={r} statuses={d.statuses} /> }, { head: 'By', cell: (r) => r.user_name ?? '' },
      ]} />
  );
}

type RowIn = { qty: string; location_id: string; note: string; action: string };

export function TransferDetail() {
  const { id } = useParams(); const qc = useQueryClient(); const { can, term } = useSession();
  const q = useQuery({ queryKey: ['stock-doc', 'transfer', id], queryFn: async () => (await get(`/stock/transfers/${id}`)).data });
  const [panel, setPanel] = useState<null | 'receive' | 'settle'>(null); const [rows, setRows] = useState<Record<string, RowIn>>({}); const [note, setNote] = useState('');
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Transfer not found.</div>;
  const doc = d.doc; const sum = d.sum; const between: boolean = d.between; const open: boolean = d.open;
  const toRacks: { id: number; code: string }[] = d.racks?.[doc.to_warehouse_id] ?? []; const fromRacks: { id: number; code: string }[] = d.racks?.[doc.warehouse_id] ?? [];
  const pendingLines = d.lines.filter((l: any) => Number(l.pending) > 0.0005);

  const begin = (kind: 'receive' | 'settle') => {
    const r: Record<string, RowIn> = {};
    pendingLines.forEach((l: any) => { r[l.id] = { qty: kind === 'receive' ? qty(l.pending) : '', location_id: kind === 'receive' ? String(Number(l.to_location_id) || 0) : String(Number(l.location_id) || 0), note: '', action: 'lost' }; });
    setRows(r); setNote(''); setPanel(kind); setMsgs([]);
  };
  const setRow = (lid: string, p: Partial<RowIn>) => setRows((x) => ({ ...x, [lid]: { ...x[lid], ...p } }));
  const send = async (path: string, body: object, ask?: string) => {
    if (ask && !confirm(ask)) return;
    setBusy(true); const r = await post(`/stock/transfers/${id}/${path}`, body); setBusy(false); setMsgs(r.messages ?? []);
    if (r.ok) { setPanel(null); refresh(qc); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  const sub = (kind: 'receive' | 'settle') => {
    const lines: Record<string, any> = {};
    Object.entries(rows).forEach(([k, v]) => { lines[k] = kind === 'receive' ? { qty: v.qty, location_id: v.location_id, note: v.note } : { qty: v.qty, action: v.action, location_id: v.location_id, note: v.note }; });
    void send(kind, kind === 'receive' ? { lines, note } : { lines });
  };

  return (
    <RecordPage entity="transfer" id={doc.id} name={doc.doc_no} back="/stock/transfers"
      badges={between ? <StatusBadge r={doc} statuses={d.statuses} /> : <Badge tone="light">{d.heading}</Badge>}
      actions={<>
        {open && can('stock.receive') && pendingLines.length > 0 && <button className="btn btn-sm btn-success" onClick={() => begin('receive')}><i className="bi bi-box-arrow-in-down" /> Receive goods</button>}
        {open && can('stock.adjust') && pendingLines.length > 0 && <button className="btn btn-sm btn-outline-warning" onClick={() => begin('settle')}>Settle shortage</button>}
        {doc.status === 'in_transit' && can('stock.transfer') && <button className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => send('cancel', {}, 'Cancel this dispatch? Every roll goes back to the rack it left.')}>Cancel dispatch</button>}
        {between && <a className="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer" href={legacyUrl(`/stock/transfers/${doc.id}/challan`)}><i className="bi bi-file-earmark-text" /> Challan</a>}
        <a className="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer" href={legacyUrl(`/stock/transfers/${doc.id}/voucher`)}><i className="bi bi-scissors" /> Stock-out voucher</a>
      </>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[...(between ? [{ id: 'tracking', label: 'Tracking' }] : []), { id: 'lines', label: 'Items' }, ...(d.events.length ? [{ id: 'history', label: 'History' }] : [])]}>
      {between && (
        <div className="card mb-3" id="tracking"><div className="card-body">
          <div className="row g-3 text-center mb-3">
            {[['Sent', sum.sent, ''], ['Received', sum.received, 'text-success'], ['On the road', sum.pending, sum.pending > 0.0005 ? 'text-warning' : ''], ['Lost', sum.lost, sum.lost > 0 ? 'text-danger' : ''], ['Sent back', sum.returned, '']].map(([l, v, tone]) => (
              <div className="col" key={String(l)}><div className="text-muted small">{l}</div><div className={`fs-4 fw-semibold ${tone}`}>{qty(v)}</div></div>))}
          </div>
          <div className="progress mb-3" style={{ height: 8 }}><div className="progress-bar bg-success" style={{ width: `${sum.sent ? (sum.received / sum.sent) * 100 : 0}%` }} />
            <div className="progress-bar bg-danger" style={{ width: `${sum.sent ? ((sum.lost + sum.returned) / sum.sent) * 100 : 0}%` }} /></div>
          <div className="row g-2 small">
            <div className="col-md-3"><span className="text-muted">Dispatched</span><br /><strong>{stamp(doc.dispatched_at)}</strong>{doc.user_name ? <> by {doc.user_name}</> : null}</div>
            <div className="col-md-3"><span className="text-muted">Expected</span><br /><strong className={d.overdue ? 'text-danger' : ''}>{doc.expected_date ? fdate(doc.expected_date) : '—'}{d.overdue ? ' (overdue)' : ''}</strong></div>
            <div className="col-md-3"><span className="text-muted">Transporter / vehicle</span><br /><strong>{[doc.transport, doc.vehicle_no].filter(Boolean).join(' · ') || '—'}</strong></div>
            <div className="col-md-3"><span className="text-muted">LR / challan no.</span><br /><strong>{doc.lr_no || '—'}</strong></div>
          </div>
          {open && <div className="alert alert-warning py-2 small mt-3 mb-0">This stock is <strong>in transit</strong>: it has left {doc.warehouse} and is not yet on any rack of {doc.to_warehouse}. {doc.to_warehouse} must press <em>Receive goods</em> and confirm what arrived.</div>}
          {doc.status === 'received_short' && <div className="alert alert-danger py-2 small mt-3 mb-0">Closed with a shortage: {qty(sum.lost)} lost{sum.returned > 0 ? `, ${qty(sum.returned)} sent back` : ''}.</div>}
          {doc.status === 'cancelled' && <div className="alert alert-secondary py-2 small mt-3 mb-0">This dispatch was cancelled. The stock went back to {doc.warehouse}.</div>}
        </div></div>
      )}

      {panel === 'receive' && (
        <div className="card mb-3 border-success"><div className="card-header bg-success-subtle">Receive at {doc.to_warehouse}: what actually arrived?</div>
          <div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th className="text-end">On the road</th><th style={{ width: 150 }}>Received now</th><th style={{ width: 180 }}>Put on {term('rack').toLowerCase()}</th><th>Remark</th></tr></thead><tbody>
            {pendingLines.map((l: any) => { const r = rows[l.id]; if (!r) return null; return (
              <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small></td><td>{l.batch_no ?? '—'}</td><td className="text-end">{qty(l.pending)} {l.unit}</td>
                <td><input type="number" step="0.001" min="0" max={qty(l.pending)} className="form-control form-control-sm" value={r.qty} onChange={(e) => setRow(l.id, { qty: e.target.value })} /></td>
                <td><select className="form-select form-select-sm" value={r.location_id} onChange={(e) => setRow(l.id, { location_id: e.target.value })}><option value="0">— choose {term('rack').toLowerCase()} —</option>{toRacks.map((k) => <option key={k.id} value={k.id}>{k.code}</option>)}</select></td>
                <td><input className="form-control form-control-sm" maxLength={255} placeholder="e.g. 1 roll wet, 2 m short" value={r.note} onChange={(e) => setRow(l.id, { note: e.target.value })} /></td></tr>); })}
          </tbody></table></div>
          <div className="card-body border-top"><input className="form-control mb-2" maxLength={255} placeholder="Note for the whole receipt (optional)" value={note} onChange={(e) => setNote(e.target.value)} />
            <p className="text-muted small">Receive less than what is on the road if some did not arrive — the rest stays <em>on the road</em>. If it will never come, use <em>Settle shortage</em>.</p>
            <div className="d-flex gap-2"><button className="btn btn-success" disabled={busy} onClick={() => sub('receive')}>{busy ? 'Saving…' : 'Confirm receipt'}</button><button className="btn btn-outline-secondary" onClick={() => setPanel(null)}>Cancel</button></div></div></div>
      )}

      {panel === 'settle' && (
        <div className="card mb-3 border-warning"><div className="card-header bg-warning-subtle">Settle what did not arrive</div>
          <div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th className="text-end">Unaccounted</th><th style={{ width: 130 }}>Quantity</th><th style={{ width: 220 }}>What happened</th><th style={{ width: 170 }}>Back on {term('rack').toLowerCase()}</th><th>Note</th></tr></thead><tbody>
            {pendingLines.map((l: any) => { const r = rows[l.id]; if (!r) return null; return (
              <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''}</td><td>{l.batch_no ?? '—'}</td><td className="text-end">{qty(l.pending)} {l.unit}</td>
                <td><input type="number" step="0.001" min="0" max={qty(l.pending)} className="form-control form-control-sm" placeholder="0" value={r.qty} onChange={(e) => setRow(l.id, { qty: e.target.value })} /></td>
                <td><select className="form-select form-select-sm" value={r.action} onChange={(e) => setRow(l.id, { action: e.target.value })}><option value="lost">Lost / damaged in transit (write off)</option><option value="return">Send back to {doc.warehouse}</option></select></td>
                <td>{r.action === 'return' && <select className="form-select form-select-sm" value={r.location_id} onChange={(e) => setRow(l.id, { location_id: e.target.value })}><option value="0">No {term('rack').toLowerCase()}</option>{fromRacks.map((k) => <option key={k.id} value={k.id}>{k.code}</option>)}</select>}</td>
                <td><input className="form-control form-control-sm" maxLength={255} value={r.note} onChange={(e) => setRow(l.id, { note: e.target.value })} /></td></tr>); })}
          </tbody></table></div>
          <div className="card-body border-top"><p className="text-muted small">A written-off quantity is gone for good (it has already left {doc.warehouse}); a quantity sent back is added to {doc.warehouse}'s racks again. Both are recorded with your name.</p>
            <div className="d-flex gap-2"><button className="btn btn-warning" disabled={busy} onClick={() => confirm('Settle these quantities? This cannot be undone.') && sub('settle')}>{busy ? 'Saving…' : 'Settle'}</button><button className="btn btn-outline-secondary" onClick={() => setPanel(null)}>Cancel</button></div></div></div>
      )}

      <div className="card mb-3" id="lines"><div className="table-responsive"><table className="table mb-0">
        <thead><tr><th>{term('item')}</th><th>SKU</th><th>{term('batch')}</th><th>{between ? `Packed from ${term('rack').toLowerCase()}` : `From ${term('rack').toLowerCase()} → To ${term('rack').toLowerCase()}`}</th>
          <th className="text-end">Sent</th>{between && <><th className="text-end">Received</th><th className="text-end">On the road</th><th className="text-end">Lost</th><th className="text-end">Sent back</th></>}</tr></thead><tbody>
          {d.lines.map((l: any) => (
            <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''}</td><td>{l.sku}</td><td>{l.batch_no ? <Link to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link> : ''}</td>
              <td>{l.rack ?? 'No rack'}{between ? '' : ` → ${l.to_rack ?? 'No rack'}`}</td><td className="text-end">{qty(l.qty)} {l.unit}</td>
              {between && <><td className="text-end text-success">{qty(l.qty_received)}</td><td className={`text-end ${Number(l.pending) > 0.0005 ? 'fw-bold text-warning' : 'text-muted'}`}>{qty(l.pending)}</td>
                <td className={`text-end ${Number(l.qty_lost) > 0 ? 'text-danger' : 'text-muted'}`}>{qty(l.qty_lost)}</td><td className="text-end text-muted">{qty(l.qty_returned)}</td></>}</tr>))}
        </tbody></table></div></div>

      {d.events.length > 0 && (
        <div className="card mb-3" id="history"><div className="card-header">History</div><div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>When</th><th>What</th><th>{term('item')}</th><th className="text-end">Qty</th><th>{term('rack')}</th><th>Note</th><th>By</th></tr></thead><tbody>
          {d.events.map((e: any) => <tr key={e.id}><td className="text-nowrap">{stamp(e.created_at)}</td><td>{EVENT[e.event] ?? e.event}</td><td>{e.item_name ? `${e.item_name}${e.vname ? ` — ${e.vname}` : ''}${e.batch_no ? ` · ${e.batch_no}` : ''}` : ''}</td><td className="text-end">{qty(e.qty)} {e.unit ?? ''}</td><td>{e.rack ?? ''}</td><td>{e.note ?? ''}</td><td>{e.user_name ?? ''}</td></tr>)}
        </tbody></table></div></div>
      )}
    </RecordPage>
  );
}
