import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';
import ItemSearch from '../../components/ItemSearch';

const poTone: Record<string, string> = { draft: 'secondary', pending_approval: 'warning', approved: 'primary', partial: 'info', received: 'success', closed: 'dark', cancelled: 'danger' };
const payTone = (b: any) => { const due = Number(b.total) - Number(b.returned_amount) - Number(b.paid_amount); return due <= 0.004 ? ['success', 'Paid'] : Number(b.paid_amount) > 0 ? ['warning', 'Partly paid'] : ['danger', 'Unpaid']; };

/** Add / revise a rate for one of this supplier's items. */
function RateForm({ supplierId, preset, onDone }: { supplierId: number; preset?: any; onDone: () => void }) {
  const [item, setItem] = useState<{ id: string; label: string }>({ id: preset ? String(preset.variant_id) : '', label: preset ? `${preset.item_name}${preset.vname ? ' — ' + preset.vname : ''} (${preset.sku})` : '' });
  const [f, setF] = useState({ rate: preset ? String(preset.rate) : '', discount_pct: preset && Number(preset.discount_pct) ? String(preset.discount_pct) : '', min_qty: preset && Number(preset.min_qty) ? String(preset.min_qty) : '', lead_time_days: preset?.lead_time_days ?? '', supplier_code: preset?.scode ?? '', supplier_name: preset?.sname ?? '', valid_from: new Date().toISOString().slice(0, 10), valid_to: '' });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => setF((x) => ({ ...x, [k]: v }));
  const save = async () => {
    if (!item.id) { setMsgs([{ type: 'danger', msg: 'Pick an item from the list.' }]); return; }
    setBusy(true);
    const r = await post(`/suppliers/${supplierId}/rates`, { ...f, variant_id: item.id, return: `suppliers/${supplierId}` });
    setBusy(false);
    if (r.ok) onDone(); else setMsgs(r.messages ?? []);
  };
  const inp = (k: string, label: string, type = 'text', col = 'col-6', extra: any = {}) => <div className={col}><label className="form-label small mb-1">{label}</label><input className="form-control form-control-sm" type={type} value={(f as any)[k]} onChange={(e) => set(k, e.target.value)} {...extra} /></div>;
  return (
    <div className="p-3 border-bottom bg-body-tertiary">
      <Messages items={msgs} />
      <div className="row g-2 align-items-end">
        <div className="col-12"><label className="form-label small mb-1">Item</label><ItemSearch value={item.id} label={item.label} disabled={!!preset} onPick={(p) => setItem(p ? { id: String(p.id), label: p.label } : { id: '', label: '' })} /></div>
        {inp('rate', 'Rate', 'number', 'col-6 col-md-3', { step: '0.01', min: '0' })}{inp('discount_pct', 'Discount %', 'number', 'col-6 col-md-3', { step: '0.01', min: '0', max: '100' })}
        {inp('min_qty', 'Min. qty', 'number', 'col-6 col-md-3', { step: '0.001', min: '0' })}{inp('lead_time_days', 'Lead time (days)', 'number', 'col-6 col-md-3', { min: '0', max: '365' })}
        {inp('valid_from', 'Valid from', 'date', 'col-6 col-md-3')}{inp('valid_to', 'Valid till', 'date', 'col-6 col-md-3')}{inp('supplier_code', 'Their code', 'text', 'col-6 col-md-3', { maxLength: 60 })}{inp('supplier_name', 'Their item name', 'text', 'col-6 col-md-3', { maxLength: 150 })}
        <div className="col-12 d-flex gap-2"><button className="btn btn-sm btn-primary" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Save rate'}</button><button className="btn btn-sm btn-link text-muted" onClick={onDone}>Cancel</button></div>
      </div>
    </div>
  );
}

export default function SupplierDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const q = useQuery({ queryKey: ['supplier', id], queryFn: async () => (await get(`/suppliers/${id}`)).data });
  const [rateForm, setRateForm] = useState<null | 'new' | any>(null); const [showOld, setShowOld] = useState(false);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">{term('supplier')} not found.</div>;
  const s = d.s; const canEdit = can('suppliers.edit');
  const reload = () => { qc.invalidateQueries({ queryKey: ['supplier', id] }); setRateForm(null); };
  const remove = async () => { if (!confirm('Delete this supplier?')) return; const r = await post(`/suppliers/${id}/delete`); if (r.ok) nav('/suppliers'); else alert(r.messages?.[0]?.msg ?? 'Could not delete.'); };
  const delRate = async (rid: number) => { if (!confirm('Remove this rate?')) return; await post(`/suppliers/${id}/rates/${rid}/delete`, { return: `suppliers/${id}` }); reload(); };
  const current = d.rates.filter((r: any) => r.is_current);
  const rates = showOld ? d.rates : current;
  const typeLabel: Record<string, string> = { manufacturer: 'Manufacturer / mill', trader: 'Trader / dealer', jobworker: 'Job worker', importer: 'Importer' };
  return (
    <RecordPage entity="supplier" id={s.id} name={s.name} back="/suppliers"
      badges={<>{s.is_active ? null : <><Badge tone="dark">Inactive</Badge> </>}{s.supplier_type ? <Badge tone="secondary">{typeLabel[s.supplier_type] ?? s.supplier_type}</Badge> : null}</>}
      actions={canEdit ? <Link className="btn btn-sm btn-primary" to={`/suppliers/${s.id}/edit`}>Edit</Link> : null}
      menu={(close) => <>
        {can('purchase.create') && <li><a className="dropdown-item" href={legacyUrl(`/purchase/orders/create?supplier=${s.id}`)}><i className="bi bi-cart-plus me-2 text-muted" />New purchase order</a></li>}
        {can('suppliers.delete') && <li><button className="dropdown-item text-danger" onClick={() => { close(); void remove(); }}><i className="bi bi-trash me-2" />Delete</button></li>}
      </>}
      related={[{ id: 'balance', label: 'What we owe' }, { id: 'products', label: 'Items supplied' }, { id: 'rates', label: 'Rate list' }, { id: 'orders', label: 'Purchase orders' }, { id: 'bills', label: 'Bills' }]}>
      <div className="card mb-3" id="balance"><div className="card-body">
        <div className="text-muted small">We owe this {term('supplier').toLowerCase()}</div><div className={`fs-2 ${d.outstanding > 0.004 ? 'text-danger' : ''}`}>{money(d.outstanding)}</div>
        <div className="text-muted small">after returns and payments, across all bills</div>
        {Number(s.credit_limit) > 0 && <div className={`mt-2 small ${d.outstanding > Number(s.credit_limit) ? 'text-danger fw-semibold' : 'text-muted'}`}><i className={`bi bi-${d.outstanding > Number(s.credit_limit) ? 'exclamation-triangle' : 'shield-check'}`} /> Credit limit {money(s.credit_limit)}{d.outstanding > Number(s.credit_limit) ? ' — exceeded' : ''}</div>}
      </div></div>
      {d.contacts.length > 0 && <div className="card mb-3" id="contacts"><div className="card-header">Contact people</div><ul className="list-group list-group-flush">
        {d.contacts.map((c: any) => <li className="list-group-item" key={c.id}><b>{c.name}</b>{c.role ? <span className="text-muted"> · {c.role}</span> : null}<div className="small text-muted">{[c.phone, c.email].filter(Boolean).join(' · ')}</div></li>)}</ul></div>}

      <section className="card mb-3" id="products">
        <div className="card-header rl-head"><div><div className="rl-title"><i className="bi bi-box-seam" /> Items supplied <span className="act-n">{d.products.length}</span></div><div className="rl-sub">What this {term('supplier').toLowerCase()} sells, named and coded the way <i>they</i> do, and which of your items each one is.</div></div>
          <div className="rl-tools"><Link className="btn btn-sm btn-outline-secondary" to={`/purchase/supplier-items?supplier=${s.id}`}><i className="bi bi-box-arrow-up-right" /> Open in Supplier items</Link>
            {can('suppliers.create') && <Link className="btn btn-sm btn-primary" to={`/purchase/supplier-items/create?supplier=${s.id}`}><i className="bi bi-plus-lg" /> Add item</Link>}</div></div>
        {d.products.length ? <div className="table-responsive"><table className="table rl-table align-middle mb-0"><thead><tr><th>Their item</th><th>{term('item')} (ours)</th><th className="text-end">Rate</th><th className="text-end d-none d-md-table-cell">Last paid</th></tr></thead><tbody>
          {d.products.map((p: any) => (
            <tr key={p.id} className={p.is_active ? '' : 'text-muted'}>
              <td><Link className="fw-medium" to={`/purchase/supplier-items/${p.id}`}>{p.supplier_name || p.supplier_code || 'Same as ours'}</Link>{p.supplier_name && p.supplier_code ? <div className="small text-muted">Code {p.supplier_code}</div> : null}{p.is_preferred ? <> <Badge tone="success">Preferred</Badge></> : null}</td>
              <td>{p.variant_id ? <><a href={legacyUrl(`/items/${p.item_id}`)}>{p.item_name}{p.vname ? ` — ${p.vname}` : ''}</a> <span className="text-muted small">{p.sku}</span></> : <Badge tone="warning">Not linked</Badge>}</td>
              <td className="text-end">{p.net !== null ? <><span className="fw-semibold">{money(p.net)}</span>{Number(p.rate_row.discount_pct) > 0 && <div className="small text-muted">{money(p.rate_row.rate)} less {Number(p.rate_row.discount_pct)}%</div>}</> : <span className="text-muted small">No rate yet</span>}</td>
              <td className="text-end d-none d-md-table-cell">{p.last_paid !== null ? <>{money(p.last_paid)}<div className="small text-muted">{fdate(p.last_on)}</div></> : <span className="text-muted">—</span>}</td></tr>))}</tbody></table></div>
          : <div className="rl-empty"><i className="bi bi-box-seam" /><div><b>No items yet</b><div className="text-muted small">List what this {term('supplier').toLowerCase()} supplies, with the name and code <i>they</i> use, and link each to your own item.</div></div></div>}
      </section>

      <section className="card mb-3" id="rates">
        <div className="card-header rl-head"><div><div className="rl-title"><i className="bi bi-tags" /> Rate list <span className="act-n">{current.length}</span></div><div className="rl-sub">What this {term('supplier').toLowerCase()} charges us · {current.length} current{d.rates.length - current.length ? ` · ${d.rates.length - current.length} earlier` : ''}</div></div>
          <div className="rl-tools">
            {can('reports.view') && <a className="btn btn-sm btn-outline-secondary" href={legacyUrl(`/reports/rate-history?supplier=${s.id}`)}><i className="bi bi-clock-history" /> History report</a>}
            {canEdit && <><a className="btn btn-sm btn-outline-secondary" href={legacyUrl(`/suppliers/${s.id}/rates/template`)}><i className="bi bi-file-earmark-spreadsheet" /> CSV template</a><button className="btn btn-sm btn-primary" onClick={() => setRateForm('new')}><i className="bi bi-plus-lg" /> Add rate</button></>}</div></div>
        {rateForm && <RateForm key={rateForm === 'new' ? 'new' : rateForm.id} supplierId={s.id} preset={rateForm === 'new' ? undefined : rateForm} onDone={reload} />}
        {d.rates.length > current.length && <div className="rl-bar"><label className="form-check form-switch m-0 small text-muted"><input className="form-check-input" type="checkbox" checked={showOld} onChange={(e) => setShowOld(e.target.checked)} /> <span>Show earlier rates ({d.rates.length - current.length})</span></label></div>}
        {rates.length ? <div className="table-responsive"><table className="table rl-table align-middle mb-0"><thead><tr><th>{term('item')}</th><th className="text-end">Rate</th><th className="text-end">Disc.</th><th className="text-end">Net rate</th><th className="text-end d-none d-md-table-cell">Min qty</th><th className="d-none d-lg-table-cell">Valid</th><th className="d-none d-lg-table-cell">Code</th><th /></tr></thead><tbody>
          {rates.map((r: any) => (
            <tr key={r.id} className={r.is_current ? '' : 'rl-old'}>
              <td><a className="fw-medium" href={legacyUrl(`/items/${r.item_id}`)}>{r.item_name}{r.vname ? ` — ${r.vname}` : ''}</a> <span className="text-muted small">{r.sku}</span>{r.is_current ? null : <> <span className="badge text-bg-light border fw-normal">Earlier</span></>}</td>
              <td className="text-end">{money(r.rate)}</td><td className="text-end">{Number(r.discount_pct) > 0 ? `${Number(r.discount_pct)}%` : <span className="text-muted">—</span>}</td><td className="text-end fw-semibold">{money(r.net)}</td>
              <td className="text-end d-none d-md-table-cell">{Number(r.min_qty) > 0 ? qty(r.min_qty) : <span className="text-muted">—</span>}</td>
              <td className="small text-nowrap d-none d-lg-table-cell">{fdate(r.valid_from)} → {r.valid_to ? fdate(r.valid_to) : <span className="text-muted">open</span>}</td>
              <td className="small d-none d-lg-table-cell">{r.scode}{r.sname ? <div className="text-muted">{r.sname}</div> : null}</td>
              <td className="text-end text-nowrap">{canEdit && <>{r.is_current && <button className="btn btn-sm btn-outline-primary" onClick={() => setRateForm(r)}>Revise</button>} <button className="btn btn-sm btn-link text-danger px-1" aria-label="Remove rate" onClick={() => delRate(r.id)}><i className="bi bi-trash" /></button></>}</td></tr>))}</tbody></table></div>
          : <div className="rl-empty"><i className="bi bi-tags" /><div><b>No rates yet</b><div className="text-muted small">Add what this {term('supplier').toLowerCase()} quotes. The rate fills in by itself when you pick the {term('item').toLowerCase()} on a purchase order.</div></div></div>}
      </section>

      <section className="card mb-3" id="orders"><div className="card-header d-flex justify-content-between align-items-center"><span>Purchase orders <span className="act-n">{d.pos.length}</span></span>
        {can('purchase.create') && <a className="btn btn-sm btn-outline-primary" href={legacyUrl(`/purchase/orders/create?supplier=${s.id}`)}><i className="bi bi-plus-lg" /> New purchase order</a>}</div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th className="text-end">Total</th></tr></thead><tbody>
          {d.pos.map((p: any) => <tr key={p.id}><td><a className="fw-medium" href={legacyUrl(`/purchase/orders/${p.id}`)}>{p.po_no}</a></td><td>{fdate(p.order_date)}</td><td><Badge tone={poTone[p.status] ?? 'secondary'}>{String(p.status).replace('_', ' ')}</Badge></td><td className="text-end">{money(p.total)}</td></tr>)}
          {!d.pos.length && <tr><td colSpan={4} className="text-muted">No purchase orders yet.</td></tr>}</tbody></table></div></section>
      <section className="card mb-3" id="bills"><div className="card-header">Bills <span className="act-n">{d.bills.length}</span></div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Bill</th><th>Date</th><th>Status</th><th className="text-end">Balance due</th></tr></thead><tbody>
          {d.bills.map((b: any) => { const [tone, label] = payTone(b); return <tr key={b.id}><td><a className="fw-medium" href={legacyUrl(`/purchase/bills/${b.id}`)}>{b.bill_no}</a>{b.supplier_bill_no ? <span className="text-muted small"> {b.supplier_bill_no}</span> : null}</td><td>{fdate(b.bill_date)}</td><td><Badge tone={tone}>{label}</Badge></td><td className="text-end">{money(Math.max(0, Number(b.total) - Number(b.returned_amount) - Number(b.paid_amount)))}</td></tr>; })}
          {!d.bills.length && <tr><td colSpan={4} className="text-muted">No bills yet.</td></tr>}</tbody></table></div></section>
    </RecordPage>
  );
}
