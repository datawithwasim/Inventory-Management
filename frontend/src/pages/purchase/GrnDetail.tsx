import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';

/** For the first roll line of an item: what the supplier billed against what arrived across that item's roll lines. */
function billedNote(rows: any[], n: number) {
  const l = rows[n]; if (l.billed_qty === null || l.billed_qty === undefined) return null;
  let got = Number(l.qty); let count = 1;
  for (let k = n + 1; k < rows.length && rows[k].variant_id === l.variant_id && (rows[k].billed_qty === null || rows[k].billed_qty === undefined); k++) { got += Number(rows[k].qty); count++; }
  const billed = Number(l.billed_qty); const diff = Math.round((got - billed) * 1000) / 1000;
  return (
    <div className="small mt-1">Billed {qty(billed)} {l.unit} · received {qty(got)} {l.unit}{count > 1 ? ` in ${count} rolls` : ''}
      {Math.abs(diff) < 0.0005 ? <span className="badge text-bg-success ms-2">matches the bill</span> : diff < 0 ? <span className="badge text-bg-warning ms-2">short by {qty(-diff)} {l.unit}</span> : <span className="badge text-bg-danger ms-2">{qty(diff)} {l.unit} more than billed</span>}</div>
  );
}

export default function GrnDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['grn', id], queryFn: async () => (await get(`/purchase/grns/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Goods receipt not found.</div>;
  const g = d.g;
  const createBill = async () => { const r = await post(`/purchase/grns/${id}/bill`); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['grn'] }); qc.invalidateQueries({ queryKey: ['record'] }); if (r.ok && r.redirect) nav('/' + r.redirect.replace(/^\//, '')); };
  const actions = <>
    {g.bill_id ? <Link className="btn btn-sm btn-outline-primary" to={`/purchase/bills/${g.bill_id}`}>Bill {g.bill_no}</Link> : can('purchase.create') && <button className="btn btn-sm btn-primary" onClick={createBill}>Create bill</button>}
    {can('labels.view') && <Link className="btn btn-sm btn-outline-secondary" to={`/labels?type=rolls&grn=${g.id}`} title="Print a label for each roll received on this receipt"><i className="bi bi-upc" /> Roll labels</Link>}
    {can('purchase.create') && <Link className="btn btn-sm btn-outline-danger" to={`/purchase/returns/create?grn=${g.id}`}>Return goods</Link>}
  </>;
  return (
    <RecordPage entity="grn" id={g.id} name={g.grn_no} back="/purchase/grns" actions={actions}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'items', label: 'Items received' }, ...(d.returns.length ? [{ id: 'returns', label: 'Returns' }] : [])]}>
      <div className="card mb-3" id="items"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th>{term('rack')}</th><th className="text-end">Qty</th><th className="text-end">Returned</th><th className="text-end">Price</th><th className="text-end">Stock cost</th></tr></thead>
        <tbody>
          {d.items.map((l: any, n: number) => { const short = billedNote(d.items, n); return <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small>{(l.sup_code || l.sup_name) && <div className="small text-muted">Their: {[l.sup_code, l.sup_name].filter(Boolean).join(' · ')}</div>}{short}</td>
            <td>{l.batch_no ? <><Link className="text-nowrap" to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link>{l.supplier_lot ? <><br /><small className="text-muted">lot {l.supplier_lot}</small></> : null}</> : '—'}</td>
            <td>{l.rack ?? 'No rack'}</td><td className="text-end">{qty(l.qty)} {l.unit}</td><td className="text-end">{Number(l.qty_returned) > 0 ? qty(l.qty_returned) : ''}</td><td className="text-end">{money(l.unit_price)}</td><td className="text-end">{money(l.landed_unit_cost)}</td></tr>; })}</tbody></table></div></div>
      {d.returns.length > 0 && <div className="card mb-3" id="returns"><div className="card-header">Returns</div><ul className="list-group list-group-flush">
        {d.returns.map((r: any) => <li className="list-group-item" key={r.id}><Link to={`/purchase/returns/${r.id}`}>{r.return_no}</Link> <span className="text-muted">· {fdate(r.return_date)} · {money(r.total)}</span></li>)}</ul></div>}
    </RecordPage>
  );
}
