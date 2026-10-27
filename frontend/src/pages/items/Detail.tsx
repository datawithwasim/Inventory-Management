import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get, legacyUrl, post } from '../../lib/api';
import { fdate, money, moneyIn, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';

const rollStatus = (balance: number, received: number, transit = 0) => (balance <= 0.0005 && transit > 0.0005 ? 'In transit' : balance <= 0.0005 ? 'Finished' : balance < received - 0.0005 ? 'Partially used' : 'Available');

export default function ItemDetail() {
  const { id } = useParams(); const nav = useNavigate();
  const { can, term, session } = useSession();
  const q = useQuery({ queryKey: ['item', id], queryFn: async () => (await get(`/items/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">{term('item')} not found.</div>;
  const it = d.item; const bundle = !!Number(it.is_bundle); const tracked = !!Number(it.track_batch);
  const remove = async () => { if (!confirm('Delete this item?')) return; const r = await post(`/items/${id}/delete`); if (r.ok) nav('/items'); else alert(r.messages?.[0]?.msg ?? 'Could not delete.'); };
  const rates: any[] = d.rates ?? [];
  const best: Record<string, number> = {};
  rates.forEach((r) => { if (r.net_inr != null && (best[r.variant_id] === undefined || r.net_inr < best[r.variant_id])) best[r.variant_id] = r.net_inr; });
  const types = session?.stockTypes ?? {};
  const related = [bundle ? { id: 'contents', label: 'Bundle contents' } : null, !bundle ? { id: 'rates', label: 'Suppliers & rates' } : null, (d.products ?? []).length ? { id: 'products', label: 'Sold as products' } : null, { id: 'variants', label: 'Stock & SKU' }, !bundle ? { id: 'history', label: 'Stock movements' } : null].filter(Boolean) as { id: string; label: string }[];
  return (
    <RecordPage entity="item" id={it.id} name={it.name} back="/items"
      badges={<>{bundle ? <><Badge tone="info">Bundle</Badge> </> : null}{tracked ? <><Badge>Tracked by roll</Badge> </> : null}{Number(it.is_active) ? null : <Badge tone="dark">Inactive</Badge>}</>}
      actions={<>{can('labels.view') && !bundle && d.variants[0] && <Link className="btn btn-sm btn-outline-secondary" to={`/labels?q=${encodeURIComponent(d.variants[0].sku)}`}><i className="bi bi-upc" /> Print label</Link>}{can('items.edit') && <Link className="btn btn-sm btn-primary" to={`/items/${it.id}/edit`}>Edit</Link>}</>}
      menu={(close) => can('items.delete') ? <li><button className="dropdown-item text-danger" onClick={() => { close(); void remove(); }}><i className="bi bi-trash me-2" />Delete</button></li> : null}
      related={related}>
      {bundle && (
        <div className="card mb-3" id="contents"><div className="card-header">Contents — {d.bundleAvailable} set(s) can be made from stock</div>
          <table className="table mb-0"><thead><tr><th>Component</th><th className="text-end">Qty per set</th><th className="text-end">In stock</th></tr></thead><tbody>
            {d.components.map((c: any, i: number) => <tr key={i}><td>{c.iname}{c.vname ? ` — ${c.vname}` : ''} ({c.sku})</td><td className="text-end">{qty(c.qty)} {c.unit}</td><td className="text-end">{qty(c.have)} {c.unit}</td></tr>)}</tbody></table></div>
      )}
      {!bundle && (
        <div className="card mb-3" id="rates"><div className="card-header d-flex justify-content-between align-items-center gap-2"><span><i className="bi bi-truck me-1" />Suppliers &amp; rates <span className="act-n">{rates.length}</span></span>
          <span className="d-flex gap-2">{can('reports.view') && <Link className="btn btn-sm btn-outline-secondary" to={`/reports/rate-history?q=${encodeURIComponent(it.name)}`}><i className="bi bi-clock-history" /> Rate history</Link>}
            {can('suppliers.create') && d.variants.length > 0 && <Link className="btn btn-sm btn-outline-primary" to={`/purchase/supplier-items/create?variant=${d.variants[0].id}`}><i className="bi bi-plus-lg" /> Link a supplier</Link>}</span></div>
          {rates.length ? <div className="table-responsive"><table className="table table-sm align-middle mb-0"><thead><tr><th>{term('supplier')}</th><th>Their item</th><th>Details</th><th className="text-end">Roll rate</th><th className="text-end">Cut rate</th><th className="text-end">MOQ rate</th><th>Lead time</th></tr></thead><tbody>
            {rates.map((r) => {
              const many = rates.filter((x) => x.variant_id === r.variant_id && x.net !== null).length > 1;
              return (
                <tr key={r.si_id}>
                  <td><Link to={`/suppliers/${r.supplier_id}`}>{r.supplier_name}</Link>{r.is_preferred ? <> <Badge tone="primary">Preferred</Badge></> : null}{r.net !== null && many && Math.abs(r.net_inr - best[r.variant_id]) < 0.005 ? <> <Badge tone="success">Lowest</Badge></> : null}</td>
                  <td className="small"><Link to={`/purchase/supplier-items/${r.si_id}`}>{r.sname || r.scode || 'Same as ours'}</Link>{r.sname && r.scode ? <div className="text-muted">{r.scode}</div> : null}</td>
                  <td className="small text-muted">{[r.design_no && `Design ${r.design_no}`, r.composition, r.width && `Width ${r.width}`, r.gsm && `${r.gsm} GSM`, r.pattern, r.finish].filter(Boolean).join(' · ') || '—'}</td>
                  {r.net === null ? <td colSpan={3} className="text-end text-muted small">No rate yet</td> : <><td className="text-end fw-semibold">{moneyIn(r.nets.roll, r.currency)}{Number(r.discount_pct) > 0 ? <div className="small text-muted fw-normal">less {Number(r.discount_pct)}%</div> : null}</td><td className="text-end">{r.nets.cut !== null ? moneyIn(r.nets.cut, r.currency) : '—'}</td><td className="text-end">{r.nets.moq !== null ? <>{moneyIn(r.nets.moq, r.currency)}<div className="small text-muted">from {qty(r.min_qty)}</div></> : '—'}</td></>}
                  <td>{r.lead_time_days != null ? `${r.lead_time_days} days` : '—'}</td>
                </tr>
              );
            })}</tbody></table></div>
            : <div className="card-body text-muted small">No supplier is linked to this {term('item').toLowerCase()} yet. You can buy it from any {term('supplier').toLowerCase()} — linking one here just keeps their name, code and rate for quick purchase orders. Several suppliers can be linked.</div>}
        </div>
      )}
      {(d.products ?? []).length > 0 && (
        <section className="card mb-3" id="products"><div className="card-header">Sold as products <span className="act-n">{d.products.length}</span><span className="small text-muted ms-2">the names and prices the showroom sells this {term('item').toLowerCase()} by</span></div>
          <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Product</th><th>Catalogue</th><th className="text-end">Price</th></tr></thead><tbody>
            {d.products.map((p: any) => <tr key={p.id} className={Number(p.is_active) ? '' : 'text-muted'}><td>{can('products.edit') ? <Link to={`/products/${p.id}/edit`}>{p.name}</Link> : p.name}{Number(p.is_active) ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
              <td>{p.catalogue_id ? <Link to={`/catalogues/${p.catalogue_id}`}>{p.catalogue}</Link> : <span className="text-muted">—</span>}</td><td className="text-end">{money(p.price)}</td></tr>)}
          </tbody></table></div></section>)}
      <section className="card mb-3" id="variants"><div className="card-header d-flex justify-content-between align-items-center"><span>Stock &amp; SKU <span className="act-n">{d.variants.length}</span></span>
        {can('items.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/items/${it.id}/edit`}><i className="bi bi-pencil" /> Edit</Link>}</div>
        <div className="table-responsive"><table className="table var-table align-middle mb-0"><thead><tr><th>{term('item')}</th><th>SKU</th><th className="text-end" title="Default cost: for stock value and as a last-resort price">Default cost</th><th className="text-end">Sale</th>{!bundle && <><th className="text-end">In stock</th><th>Where</th></>}</tr></thead><tbody>
          {d.variants.map((v: any) => (
            <FragmentRows key={v.id} v={v} it={it} bundle={bundle} tracked={tracked} term={term} />
          ))}</tbody></table></div></section>
      {!bundle && (
        <div className="card mb-3" id="history"><div className="card-header">Recent stock movements</div>
          <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>SKU</th><th>{term('warehouse')}</th><th>{term('rack')}</th><th>{term('batch')}</th><th className="text-end">Qty</th><th>Note</th></tr></thead><tbody>
            {d.history.map((h: any) => <tr key={h.id}><td className="text-nowrap">{fdate(String(h.created_at).slice(0, 10))} <span className="text-muted">{String(h.created_at).slice(11, 16)}</span></td><td>{types[h.type] ?? h.type}</td><td>{h.sku}</td><td>{h.warehouse}</td><td>{h.rack ?? ''}</td><td>{h.batch_no ?? ''}</td>
              <td className={`text-end ${Number(h.qty_change) < 0 ? 'text-danger' : 'text-success'}`}>{Number(h.qty_change) > 0 ? '+' : ''}{qty(h.qty_change)}</td><td>{h.note ?? ''}</td></tr>)}
            {!d.history.length && <tr><td colSpan={8} className="text-muted">No movements yet. Add stock with a stock adjustment (opening stock).</td></tr>}</tbody></table></div></div>
      )}
    </RecordPage>
  );
}

function FragmentRows({ v, it, bundle, tracked, term }: { v: any; it: any; bundle: boolean; tracked: boolean; term: (k: string, p?: boolean) => string }) {
  const cols = bundle ? 4 : 6;
  return (
    <>
      <tr className={Number(v.is_active) ? '' : 'text-muted'}>
        <td><strong>{it.name}</strong>{Number(v.is_active) ? null : <> <Badge tone="dark">Hidden</Badge></>}</td>
        <td className="small">{v.sku}{v.barcode ? <div className="text-muted">{v.barcode}</div> : null}</td>
        <td className="text-end">{money(v.cost_price)}</td><td className="text-end">{money(v.sale_price)}</td>
        {!bundle && <>
          <td className="text-end fw-semibold">{qty(v.total)} <span className="text-muted fw-normal">{it.unit}</span></td>
          <td className="small">
            {v.by_wh.map((w: any) => <span key={w.name} className="badge text-bg-light border fw-normal me-1">{w.name}: {qty(w.qty)}</span>)}
            {v.by_rack.map((r: any, i: number) => <span key={i} className={`badge text-bg-${r.rack === '' ? 'warning' : 'primary-subtle text-primary-emphasis'} fw-normal me-1`}><i className="bi bi-geo-alt" /> {r.rack !== '' ? r.rack : 'No rack'}: {qty(r.qty)}</span>)}
            {Number(v.in_transit) > 0.0005 && <Link to="/stock/transfers?status=open" className="badge text-bg-warning fw-normal me-1 text-decoration-none"><i className="bi bi-truck" /> In transit: {qty(v.in_transit)}</Link>}
            {!v.by_wh.length && !(Number(v.in_transit) > 0.0005) && <span className="text-muted">—</span>}</td></>}
      </tr>
      {!bundle && tracked && (
        <tr className="var-rolls-row"><td colSpan={cols} className="p-0 border-0">
          <details className="var-rolls"><summary><i className="bi bi-layers" /> {v.batches.length} {term('batch', true).toLowerCase()}{v.batches.length ? ` · ${qty(v.batches.reduce((a: number, b: any) => a + Number(b.balance), 0))} ${it.unit} left` : ''}</summary>
            <table className="table table-sm mb-0"><thead><tr><th>{term('batch')}</th><th>{term('supplier')} lot</th><th>Received</th><th className="text-end">Roll size</th><th className="text-end">Balance</th><th>Status</th></tr></thead><tbody>
              {v.batches.map((b: any) => <tr key={b.id}><td><Link to={`/stock/batches/${b.id}`}>{b.batch_no}</Link></td><td>{b.supplier_lot ?? ''}</td><td>{fdate(b.received_date)}</td><td className="text-end">{qty(b.received_qty)}</td><td className="text-end">{qty(b.balance)}</td><td>{rollStatus(Number(b.balance), Number(b.received_qty), Number(b.in_transit ?? 0))}</td></tr>)}
              {!v.batches.length && <tr><td colSpan={6} className="text-muted">No {term('batch', true).toLowerCase()} yet.</td></tr>}</tbody></table></details></td></tr>
      )}
    </>
  );
}
