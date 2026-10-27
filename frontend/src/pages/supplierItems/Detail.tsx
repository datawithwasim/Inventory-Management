import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, moneyIn, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';

export default function SupplierItemDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can } = useSession();
  const q = useQuery({ queryKey: ['si', id], queryFn: async () => (await get(`/purchase/supplier-items/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Supplier item not found.</div>;
  const r = d.r; const cur = r.s_origin === 'imported' && r.s_currency ? r.s_currency : 'INR'; const linked = !!r.variant_id; const canEdit = can('suppliers.edit');
  const reload = () => { qc.invalidateQueries({ queryKey: ['si', id] }); qc.invalidateQueries({ queryKey: ['record'] }); };
  const delRate = async (rid: number) => { if (!confirm('Remove this rate?')) return; await post(`/suppliers/${r.supplier_id}/rates/${rid}/delete`, { return: `purchase/supplier-items/${r.id}` }); reload(); };
  const remove = async () => { if (!confirm('Remove this supplier item? Its rates stay in the history.')) return; const res = await post(`/purchase/supplier-items/${r.id}/delete`); if (res.ok) nav('/purchase/supplier-items'); };
  const name = r.supplier_name || r.supplier_code || r.item_name || 'Supplier item';
  return (
    <RecordPage entity="supplier_item" id={r.id} name={name} back="/purchase/supplier-items"
      badges={<><span className="badge text-bg-light border">{r.supplier}</span> {r.is_preferred ? <><Badge tone="success">Preferred</Badge> </> : null}{linked ? null : <><Badge tone="warning">Not linked</Badge> </>}{r.is_active ? null : <Badge tone="dark">Inactive</Badge>}</>}
      actions={canEdit ? <Link className="btn btn-sm btn-primary" to={`/purchase/supplier-items/${r.id}/edit`}>Edit</Link> : null}
      menu={(close) => can('suppliers.delete') ? <li><button className="dropdown-item text-danger" onClick={() => { close(); void remove(); }}><i className="bi bi-trash me-2" />Delete</button></li> : null}
      related={linked ? [{ id: 'rates', label: 'Rates' }, { id: 'purchases', label: 'Recent purchases' }] : []}>
      {!linked && <div className="alert alert-warning small mb-3"><i className="bi bi-link-45deg" /> This supplier item is <b>not linked</b> to one of your items yet. Link it to use it on purchase orders and to keep a rate list for it. {canEdit && <Link className="fw-semibold" to={`/purchase/supplier-items/${r.id}/edit`}>Link it now</Link>}</div>}
      {linked && <>
        <section className="card mb-3" id="rates">
          <div className="card-header d-flex justify-content-between align-items-center"><span><i className="bi bi-tags me-1" />Rates <span className="act-n">{d.rates.length}</span></span>
            <span className="d-flex gap-2">{can('reports.view') && <a className="btn btn-sm btn-outline-secondary" href={legacyUrl(`/reports/rate-history?supplier=${r.supplier_id}`)}><i className="bi bi-clock-history" /> History report</a>}
              {canEdit && <Link className="btn btn-sm btn-primary" to={`/purchase/rates/create?supplier=${r.supplier_id}&variant=${r.variant_id}&return=/purchase/supplier-items/${r.id}`}><i className="bi bi-plus-lg" /> Add rate</Link>}</span></div>
          <div className="table-responsive"><table className="table table-sm align-middle mb-0"><thead><tr><th>Valid</th><th className="text-end">Roll</th><th className="text-end">Cut</th><th className="text-end">MOQ</th><th className="text-end">Disc.</th><th /></tr></thead><tbody>
            {d.rates.map((x: any) => <tr key={x.id} className={x.is_current ? '' : 'text-muted'}><td className="small">{fdate(x.valid_from)} → {x.valid_to ? fdate(x.valid_to) : 'open'}{x.is_current ? <> <Badge tone="success">Current</Badge></> : null}</td>
              <td className="text-end fw-semibold">{moneyIn(x.nets.roll, x.currency)}</td><td className="text-end">{x.nets.cut !== null ? moneyIn(x.nets.cut, x.currency) : '—'}</td><td className="text-end">{x.nets.moq !== null ? <>{moneyIn(x.nets.moq, x.currency)} <span className="small text-muted">from {qty(x.min_qty)}</span></> : '—'}</td><td className="text-end">{Number(x.discount_pct) > 0 ? `${Number(x.discount_pct)}%` : '—'}</td>
              <td className="text-end">{canEdit && <button className="btn btn-sm btn-link text-danger px-1" aria-label="Remove rate" onClick={() => delRate(x.id)}><i className="bi bi-trash" /></button>}</td></tr>)}
            {!d.rates.length && <tr><td colSpan={7} className="text-muted">No rate yet. Add what this supplier charges for it above.</td></tr>}</tbody></table></div>
        </section>
        <section className="card mb-3" id="purchases"><div className="card-header">Recent purchases</div>
          <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Goods receipt</th><th>Date</th><th className="text-end">Qty</th><th className="text-end">Price</th></tr></thead><tbody>
            {d.purchases.map((p: any) => <tr key={p.grn_id}><td><a href={legacyUrl(`/purchase/grns/${p.grn_id}`)}>{p.grn_no}</a></td><td>{fdate(p.received_date)}</td><td className="text-end">{qty(p.qty)} {r.unit}</td><td className="text-end">{money(p.unit_price)}</td></tr>)}
            {!d.purchases.length && <tr><td colSpan={4} className="text-muted">Nothing bought from this supplier yet.</td></tr>}</tbody></table></div></section>
      </>}
    </RecordPage>
  );
}
