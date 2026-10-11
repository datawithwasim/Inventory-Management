import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';
import { PoBadge } from '../../components/Badges';

export default function PoDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['po', id], queryFn: async () => (await get(`/purchase/orders/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Purchase order not found.</div>;
  const po = d.po; const st: string = po.status; const canReceive = ['approved', 'partial'].includes(st) && d.remaining > 0;
  const act = async (path: string, ask?: string, after?: () => void) => {
    if (ask && !confirm(ask)) return;
    const r = await post(`/purchase/orders/${id}/${path}`);
    setMsgs(r.messages ?? []);
    ['po', 'pos', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    if (r.ok) after?.();
  };
  const btn = (cls: string, label: string, onClick: () => void) => <button className={`btn btn-sm ${cls}`} onClick={onClick}>{label}</button>;
  const actions = <>
    <a className="btn btn-sm btn-outline-secondary" target="_blank" href={legacyUrl(`/purchase/orders/${id}/print`)}><i className="bi bi-printer" /> Print</a>
    {st === 'draft' && can('purchase.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/purchase/orders/${id}/edit`}>Edit</Link>}
    {st === 'draft' && can('purchase.create') && btn('btn-primary', d.approval ? 'Submit for approval' : 'Confirm order', () => act('submit'))}
    {st === 'pending_approval' && can('purchase.approve') && <>{btn('btn-success', 'Approve', () => act('approve'))}{btn('btn-outline-secondary', 'Send back to draft', () => act('reject'))}</>}
    {canReceive && can('purchase.create') && <Link className="btn btn-sm btn-success" to={`/purchase/orders/${id}/receive`}><i className="bi bi-box-arrow-in-down" /> Receive goods</Link>}
    {st === 'partial' && can('purchase.edit') && btn('btn-outline-dark', 'Close order', () => act('close', 'Close this order? Nothing more can be received against it.'))}
    {['draft', 'pending_approval', 'approved'].includes(st) && !d.grns.length && can('purchase.edit') && btn('btn-outline-danger', 'Cancel order', () => act('cancel', 'Cancel this purchase order?'))}
    {st === 'draft' && can('purchase.delete') && btn('btn-outline-danger', 'Delete draft', () => act('delete', 'Delete this draft?', () => nav('/purchase/orders')))}
  </>;
  return (
    <RecordPage entity="purchase_order" id={po.id} name={po.po_no} back="/purchase/orders" badges={<PoBadge status={st} />} actions={actions}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'items', label: 'Items' }, ...(d.grns.length ? [{ id: 'grns', label: 'Goods received' }] : [])]}>
      <div className="card mb-3" id="items"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th className="text-end">Ordered</th><th className="text-end">Received</th><th className="text-end">Still due</th><th className="text-end">Price</th><th className="text-end">Tax %</th><th className="text-end">Total</th></tr></thead>
        <tbody>
          {d.items.map((l: any) => {
            const net = Number(l.qty_ordered) * Number(l.unit_price); const tot = net * (1 + Number(l.tax_rate) / 100); const due = Math.max(0, Number(l.qty_ordered) - Number(l.qty_received));
            return <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small>{(l.sup_code || l.sup_name) && <div className="small text-muted">Their: {[l.sup_code, l.sup_name].filter(Boolean).join(' · ')}</div>}</td>
              <td className="text-end">{qty(l.qty_ordered)} {l.unit}</td><td className="text-end">{qty(l.qty_received)}</td><td className="text-end">{qty(due)}</td><td className="text-end">{money(l.unit_price)}</td><td className="text-end">{qty(l.tax_rate)}</td><td className="text-end">{money(tot)}</td></tr>;
          })}</tbody>
        <tfoot><tr><td colSpan={6} className="text-end text-muted">Subtotal</td><td className="text-end">{money(po.subtotal)}</td></tr><tr><td colSpan={6} className="text-end text-muted">Tax</td><td className="text-end">{money(po.tax_total)}</td></tr><tr><th colSpan={6} className="text-end">Total</th><th className="text-end">{money(po.total)}</th></tr></tfoot></table></div></div>
      {d.grns.length > 0 && <div className="card mb-3" id="grns"><div className="card-header">Goods received against this order</div><ul className="list-group list-group-flush">
        {d.grns.map((g: any) => <li className="list-group-item" key={g.id}><Link to={`/purchase/grns/${g.id}`}>{g.grn_no}</Link> <span className="text-muted">· {fdate(g.received_date)}</span></li>)}</ul></div>}
    </RecordPage>
  );
}
