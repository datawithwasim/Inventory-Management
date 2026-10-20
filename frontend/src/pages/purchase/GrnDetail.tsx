import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';
import DetailTable, { useViewCols, type DetailDef } from '../../components/DetailTable';
import { ItemCell, anyTheirs, theirText } from '../../components/ItemCell';

/** What the supplier's bill said against what arrived, one row per billed item (the roll lines after a billed line belong to it). */
function billedRows(rows: any[]) {
  const out: { key: number; l: any; billed: number; got: number; rolls: number }[] = [];
  rows.forEach((l, n) => {
    if (l.billed_qty === null || l.billed_qty === undefined) return;
    let got = Number(l.qty); let rolls = 1;
    for (let k = n + 1; k < rows.length && rows[k].variant_id === l.variant_id && (rows[k].billed_qty === null || rows[k].billed_qty === undefined); k++) { got += Number(rows[k].qty); rolls++; }
    out.push({ key: l.id, l, billed: Number(l.billed_qty), got: Math.round(got * 1000) / 1000, rolls });
  });
  return out;
}

export default function GrnDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const allCols = useViewCols('grn', { item: term('item'), their: `${term('supplier')}'s item`, po: 'PO', batch: term('batch'), rack: term('rack'), qty: 'Qty', returned: 'Returned', price: 'Price', cost: 'Stock cost' });
  const q = useQuery({ queryKey: ['grn', id], queryFn: async () => (await get(`/purchase/grns/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Goods receipt not found.</div>;
  const g = d.g; const bills = billedRows(d.items); const theirs = anyTheirs(d.items);
  const cols = allCols.filter((c) => (c.key !== 'their' || theirs) && (c.key !== 'po' || d.pos.length > 1));
  const grnDefs: Record<string, DetailDef<any>> = {
    item: { cell: (l) => <ItemCell l={l} /> },
    their: { className: 'small text-muted', cell: (l) => theirText(l) },
    po: { className: 'small text-nowrap', cell: (l) => l.po_no ?? '—' },
    batch: { cell: (l) => l.batch_no ? <><Link className="text-nowrap" to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link>{l.supplier_lot ? <div className="small text-muted text-nowrap">lot {l.supplier_lot}</div> : null}</> : '—' },
    rack: { cell: (l) => l.rack ?? 'No rack' },
    qty: { className: 'text-end text-nowrap', cell: (l) => <>{qty(l.qty)} {l.unit}</> },
    returned: { className: 'text-end', cell: (l) => Number(l.qty_returned) > 0 ? qty(l.qty_returned) : '' },
    price: { className: 'text-end', cell: (l) => money(l.unit_price) },
    cost: { className: 'text-end', cell: (l) => money(l.landed_unit_cost) },
  };
  const createBill = async () => { const r = await post(`/purchase/grns/${id}/bill`); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['grn'] }); qc.invalidateQueries({ queryKey: ['record'] }); if (r.ok && r.redirect) nav('/' + r.redirect.replace(/^\//, '')); };
  const actions = <>
    {g.bill_id ? <Link className="btn btn-sm btn-outline-primary" to={`/purchase/bills/${g.bill_id}`}>Bill {g.bill_no}</Link> : can('purchase.create') && <button className="btn btn-sm btn-primary" onClick={createBill}>Create bill</button>}
    {can('labels.view') && <Link className="btn btn-sm btn-outline-secondary" to={`/labels?type=rolls&grn=${g.id}`} title="Print a label for each roll received on this receipt"><i className="bi bi-upc" /> Roll labels</Link>}
    {can('purchase.create') && <Link className="btn btn-sm btn-outline-danger" to={`/purchase/returns/create?grn=${g.id}`}>Return goods</Link>}
  </>;
  return (
    <RecordPage entity="grn" id={g.id} name={g.grn_no} back="/purchase/grns" actions={actions}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[...(bills.length ? [{ id: 'billed', label: "Against the bill" }] : []), { id: 'items', label: 'Items received' }, ...(d.returns.length ? [{ id: 'returns', label: 'Returns' }] : [])]}>
      {bills.length > 0 && <div className="card mb-3" id="billed"><div className="card-header">Against the supplier's bill</div><div className="table-responsive"><table className="table table-sm mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th className="text-end">Billed</th><th className="text-end">Received</th><th className="text-end">Rolls</th><th>Result</th></tr></thead>
        <tbody>{bills.map((x) => { const diff = Math.round((x.got - x.billed) * 1000) / 1000; return <tr key={x.key}><td><ItemCell l={x.l} /></td><td className="text-end">{qty(x.billed)} {x.l.unit}</td><td className="text-end">{qty(x.got)} {x.l.unit}</td><td className="text-end">{x.rolls}</td>
          <td>{Math.abs(diff) < 0.0005 ? <span className="badge text-bg-success">matches the bill</span> : diff < 0 ? <span className="badge text-bg-warning">short by {qty(-diff)} {x.l.unit}</span> : <span className="badge text-bg-danger">{qty(diff)} {x.l.unit} more than billed</span>}</td></tr>; })}</tbody></table></div></div>}
      <DetailTable cols={cols} rows={d.items} rowKey={(l: any) => l.id} defs={grnDefs} />
      {d.returns.length > 0 && <div className="card mb-3" id="returns"><div className="card-header">Returns</div><ul className="list-group list-group-flush">
        {d.returns.map((r: any) => <li className="list-group-item" key={r.id}><Link to={`/purchase/returns/${r.id}`}>{r.return_no}</Link> <span className="text-muted">· {fdate(r.return_date)} · {money(r.total)}</span></li>)}</ul></div>}
    </RecordPage>
  );
}
