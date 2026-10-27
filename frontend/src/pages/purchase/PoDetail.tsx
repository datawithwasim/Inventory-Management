import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg, appUrl } from '../../lib/api';
import { fdate, money, moneyIn, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';
import { ItemCell, anyTheirs, theirText } from '../../components/ItemCell';
import DetailTable, { useViewCols, type DetailDef } from '../../components/DetailTable';
import { PoBadge } from '../../components/Badges';

export default function PoDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const allCols = useViewCols('purchase_order', { item: term('item'), their: `${term('supplier')}'s item`, ordered: 'Ordered', received: 'Received', due: 'Still due', price: 'Price', tax: 'Tax %', total: 'Total' });
  const q = useQuery({ queryKey: ['po', id], queryFn: async () => (await get(`/purchase/orders/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Purchase order not found.</div>;
  const po = d.po; const st: string = po.status; const canReceive = ['approved', 'partial'].includes(st) && d.remaining > 0;
  const theirs = anyTheirs(d.items); const cols = allCols.filter((c) => c.key !== 'their' || theirs);
  const act = async (path: string, ask?: string, after?: () => void) => {
    if (ask && !confirm(ask)) return;
    const r = await post(`/purchase/orders/${id}/${path}`);
    setMsgs(r.messages ?? []);
    ['po', 'pos', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    if (r.ok) after?.();
  };
  const poDefs: Record<string, DetailDef<any>> = {
    item: { cell: (l) => <ItemCell l={l} /> },
    their: { className: 'small text-muted', cell: (l) => theirText(l) },
    ordered: { className: 'text-end', cell: (l) => <>{qty(l.qty_ordered)} {l.unit}</> },
    received: { className: 'text-end', cell: (l) => qty(l.qty_received) },
    due: { className: 'text-end', cell: (l) => qty(Math.max(0, Number(l.qty_ordered) - Number(l.qty_received))) },
    price: { className: 'text-end', cell: (l) => moneyIn(l.unit_price, po.currency) },
    tax: { className: 'text-end', cell: (l) => qty(l.tax_rate) },
    total: { className: 'text-end', cell: (l) => moneyIn(Number(l.qty_ordered) * Number(l.unit_price) * (1 + Number(l.tax_rate) / 100), po.currency) },
  };
  const btn = (cls: string, label: string, onClick: () => void) => <button className={`btn btn-sm ${cls}`} onClick={onClick}>{label}</button>;
  const actions = <>
    <a className="btn btn-sm btn-outline-secondary" target="_blank" href={appUrl(`/purchase/orders/${id}/print`)}><i className="bi bi-printer" /> Print</a>
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
      <DetailTable cols={cols} rows={d.items} rowKey={(l: any) => l.id} defs={poDefs}
        footer={<><tr><td className="text-muted">Subtotal</td><td className="text-end">{moneyIn(po.subtotal, po.currency)}</td></tr><tr><td className="text-muted">Tax</td><td className="text-end">{moneyIn(po.tax_total, po.currency)}</td></tr><tr><th>Total</th><th className="text-end">{moneyIn(po.total, po.currency)}</th></tr>{po.currency && po.currency !== 'INR' && <tr><td className="text-muted">In rupees at ₹ {Number(po.exchange_rate)}</td><td className="text-end">{money(Number(po.total) * Number(po.exchange_rate))}</td></tr>}</>} />
      {d.grns.length > 0 && <div className="card mb-3" id="grns"><div className="card-header">Goods received against this order</div><ul className="list-group list-group-flush">
        {d.grns.map((g: any) => <li className="list-group-item" key={g.id}><Link to={`/purchase/grns/${g.id}`}>{g.grn_no}</Link> <span className="text-muted">· {fdate(g.received_date)}</span></li>)}</ul></div>}
    </RecordPage>
  );
}
