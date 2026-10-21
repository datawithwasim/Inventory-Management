import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { saleLine, lineName } from '../../lib/docs';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';
import RecordPage from '../../components/RecordPage';
import { SoBadge } from '../../components/Badges';
import SalesLines, { blankSLine, type SLine } from '../../components/SalesLines';
import DetailTable, { useViewCols, type DetailDef } from '../../components/DetailTable';

export const refreshSales = (qc: ReturnType<typeof useQueryClient>) => ['sales-orders', 'sales-order', 'deliveries', 'delivery', 'invoices', 'invoice', 'sales-returns', 'record', 'customer', 'stock'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
const go = (nav: (p: string) => void, redirect: string | undefined, fallback: string) => nav(redirect ? '/' + redirect.replace(/^\//, '') : fallback);

export function OrderList() {
  const { can, term, session } = useSession();
  return (
    <DocList title="Sales orders" api="/sales/orders" queryKey="sales-orders" empty="No orders found."
      actions={() => can('sales.create') ? <Link className="btn btn-primary" to="/sales/orders/create"><i className="bi bi-plus-lg" /> New sales order</Link> : null}
      filters={(d, set, sp) => (
        <Filters names={['status', 'customer', 'q']} sp={sp} set={set}>
          <div className="col-md-3"><select name="status" className="form-select" defaultValue={sp.get('status') ?? ''}><option value="">All statuses</option><option value="open">Open (to deliver)</option>{Object.entries(session?.soStatus ?? {}).map(([k, [l]]) => <option key={k} value={k}>{l}</option>)}</select></div>
          <div className="col-md-3"><select name="customer" className="form-select" defaultValue={sp.get('customer') ?? ''}><option value="">All {term('customer', true).toLowerCase()}</option>{d.customers.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
          <div className="col-md-2"><input name="q" className="form-control" placeholder="Order no." defaultValue={sp.get('q') ?? ''} /></div>
        </Filters>)}
      columns={[
        { head: 'Order', cell: (r) => <><Link to={`/sales/orders/${r.id}`}>{r.order_no}</Link>{r.launch_no ? <> <span className="badge text-bg-light border" title="Fabric for a catalogue launch"><i className="bi bi-rocket-takeoff" /> {r.launch_no}</span></> : null}</> }, { head: 'Date', cell: (r) => fdate(r.order_date) }, { head: term('customer'), cell: (r) => r.customer },
        { head: 'Ship from', cell: (r) => r.warehouse }, { head: 'Deliver by', cell: (r) => fdate(r.expected_date) }, { head: 'Total', className: 'text-end', cell: (r) => money(r.total) }, { head: 'Status', cell: (r) => <SoBadge status={r.status} /> },
      ]} />
  );
}

/** Sales order create / edit (drafts only). */
export function OrderForm() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('sales_order');
  const launchId = sp.get('launch') ?? '';
  const src = useQuery({ queryKey: ['so-form', id ?? 'new', launchId], gcTime: 0, queryFn: async () => (await get(id ? `/sales/orders/${id}/edit` : `/sales/orders/create${launchId ? `?launch=${launchId}` : ''}`)) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [lines, setLines] = useState<SLine[]>([blankSLine()]); const [backorder, setBackorder] = useState(false);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data) return;
    if (src.data.redirect) { nav('/' + String(src.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (values || !src.data.data) return;
    const d = src.data.data; const o = d.o ?? {}; const cf = d.cfValues ?? {}; const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; } else v[k] = o[k] ?? '';
    }
    if (!id) { v.order_date = new Date().toISOString().slice(0, 10); v.warehouse_id = String(d.warehouses?.[0]?.id ?? ''); v.customer_id = sp.get('customer') ?? String(d.customers?.[0]?.id ?? ''); v.delivery_charge = ''; v.installation_charge = '';
      // a fabric order for a catalogue launch: addressed to the catalogue department, sent where the catalogue team said
      if (d.launch) { v.customer_id = String(d.launch.customer_id); v.warehouse_id = String(d.launch.warehouse_id); v.ship_to = d.launch.ship_to ?? ''; v.notes = `Fabric for catalogue launch ${d.launch.launch_no} (${d.launch.catalogue})`; } }
    setValues(v); setBackorder(!!Number(o.allow_backorder ?? 0));
    const old: any[] = d.oldLines ?? [];
    if (old.length) setLines(old.map((l) => ({ variant_id: String(l.variant_id), product_id: String(l.product_id ?? ''), label: l.label, unit: l.unit ?? '', dec: l.dec !== 0, qty: String(Number(l.qty)), unit_price: String(l.unit_price ?? ''), discount_pct: Number(l.discount_pct) ? String(Number(l.discount_pct)) : '', tax_rate: Number(l.tax_rate) ? String(Number(l.tax_rate)) : '', hint: '' })));
  }, [schema.data, src.data, values, id, sp, nav]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const o = src.data.data.o; const launch = src.data.data.launch;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body = { ...toPayload(schema.data!, values), allow_backorder: backorder ? 1 : '', launch_id: launch ? launch.id : '', lines: lines.filter((l) => l.variant_id).map((l) => ({ variant_id: l.variant_id, product_id: l.product_id, qty: l.qty, unit_price: l.unit_price, discount_pct: l.discount_pct, tax_rate: l.tax_rate })) };
    const r = await post(id ? `/sales/orders/${id}` : '/sales/orders', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refreshSales(qc); go(nav, r.redirect, '/sales/orders');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? `Edit ${o.order_no}` : 'New sales order'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=sales_order&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/sales/orders/${id}` : '/sales/orders')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save draft'}</button>
      </PageHead>
      <Messages items={msgs} />
      {launch && <div className="alert alert-info"><i className="bi bi-rocket-takeoff" /> Fabric order for catalogue launch <b>{launch.launch_no}</b> ({launch.catalogue}). The warehouse will deliver it from here, to the delivery address below. Fabric is not sold: the prices are 0.</div>}
      <div className="card mb-3"><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="bo" checked={backorder} onChange={(e) => setBackorder(e.target.checked)} /><label className="form-check-label" htmlFor="bo">Take the order even if stock is short <small className="text-muted d-block">(made-to-order / back-order)</small></label></div>
        <p className="text-muted small mb-0">Confirming the order <strong>reserves</strong> the stock, so it cannot be sold to someone else before this {term('customer').toLowerCase()}'s delivery.</p>
      </div></div>
      <SalesLines lines={lines} setLines={setLines} customer={String(values.customer_id ?? '')} warehouse={String(values.warehouse_id ?? '')} charges={(Number(values.delivery_charge) || 0) + (Number(values.installation_charge) || 0)}
        grid={schema.data.grid} term={term} free={!!launch} />
    </form>
  );
}

export function OrderDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term, session } = useSession();
  const cols = useViewCols('sales_order', { item: term('item'), ordered: 'Qty', delivered: 'Delivered', price: 'Price', disc: 'Disc %', tax: 'Tax %', total: 'Total' });
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [adv, setAdv] = useState({ amount: '', paid_on: new Date().toISOString().slice(0, 10), method: 'cash', reference: '' });
  const q = useQuery({ queryKey: ['sales-order', id], queryFn: async () => (await get(`/sales/orders/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Order not found.</div>;
  const o = d.o; const st: string = o.status; const methods = session?.payMethods ?? {};
  const canDeliver = ['confirmed', 'partial'].includes(st) && d.remaining > 0;
  const run = async (path: string, ask?: string, then?: (r: any) => void) => {
    if (ask && !confirm(ask)) return;
    const r = await post(path, {}); setMsgs(r.messages ?? []); refreshSales(qc); if (r.ok) then?.(r);
  };
  const addAdvance = async () => { const r = await post(`/sales/orders/${id}/advance`, adv); setMsgs(r.messages ?? []); if (r.ok) { setAdv({ ...adv, amount: '', reference: '' }); refreshSales(qc); } };
  const lineDefs: Record<string, DetailDef<any>> = {
    item: { cell: (l) => <>{lineName(l)}{l.product_name ? <div className="small text-muted">Item: {l.item_name}</div> : null} <small className="text-muted">{l.sku}</small>{Number(l.is_bundle) ? <> <Badge tone="info">Set</Badge></> : null}
      {l.free !== undefined && <div className="small text-muted">free for others now: {qty(l.free)}</div>}</> },
    ordered: { className: 'text-end', cell: (l) => <>{qty(l.qty_ordered)} {l.unit}</> },
    delivered: { className: 'text-end', cell: (l) => qty(l.qty_delivered) },
    price: { className: 'text-end', cell: (l) => money(l.unit_price) },
    disc: { className: 'text-end', cell: (l) => Number(l.discount_pct) ? qty(l.discount_pct) : '' },
    tax: { className: 'text-end', cell: (l) => qty(l.tax_rate) },
    total: { className: 'text-end', cell: (l) => money(saleLine(Number(l.qty_ordered), Number(l.unit_price), Number(l.discount_pct), Number(l.tax_rate)).total) },
  };
  return (
    <RecordPage entity="sales_order" id={o.id} name={o.order_no} back="/sales/orders"
      badges={<><SoBadge status={st} />{Number(o.allow_backorder) ? <> <span className="badge text-bg-light border">back-order allowed</span></> : null}{d.launch ? <> <Link className="badge text-bg-light border text-decoration-none" to={`/launches/${d.launch.id}`}><i className="bi bi-rocket-takeoff" /> Fabric for launch {d.launch.launch_no}</Link></> : null}</>}
      actions={<>
        {st === 'draft' && can('sales.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/sales/orders/${o.id}/edit`}>Edit</Link>}
        {st === 'draft' && can('sales.create') && <button className="btn btn-sm btn-primary" onClick={() => run(`/sales/orders/${id}/confirm`)}>Confirm &amp; reserve stock</button>}
        {canDeliver && can('sales.create') && <Link className="btn btn-sm btn-success" to={`/sales/orders/${o.id}/deliver`}><i className="bi bi-truck" /> Deliver goods</Link>}
        {st === 'partial' && can('sales.edit') && <button className="btn btn-sm btn-outline-dark" onClick={() => run(`/sales/orders/${id}/close`, 'Close this order? The undelivered part is dropped.')}>Close order</button>}
        {['draft', 'confirmed'].includes(st) && !d.deliveries.length && can('sales.edit') && <button className="btn btn-sm btn-outline-danger" onClick={() => run(`/sales/orders/${id}/cancel`, 'Cancel this order?')}>Cancel order</button>}
        {st === 'draft' && can('sales.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => run(`/sales/orders/${id}/delete`, 'Delete this draft?', () => nav('/sales/orders'))}>Delete draft</button>}
      </>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'lines', label: 'Items' }, { id: 'deliveries', label: 'Deliveries & advance' }]}>
      <DetailTable id="lines" cols={cols} rows={d.items} rowKey={(l: any) => l.id} defs={lineDefs}
        footer={<>
          <tr><td className="text-muted">Gross</td><td className="text-end">{money(o.subtotal)}</td></tr>
          {Number(o.discount_total) > 0 && <tr><td className="text-muted">Discount</td><td className="text-end">− {money(o.discount_total)}</td></tr>}
          <tr><td className="text-muted">Tax</td><td className="text-end">{money(o.tax_total)}</td></tr>
          {Number(o.delivery_charge) > 0 && <tr><td className="text-muted">Delivery</td><td className="text-end">{money(o.delivery_charge)}</td></tr>}
          {Number(o.installation_charge) > 0 && <tr><td className="text-muted">Installation</td><td className="text-end">{money(o.installation_charge)}</td></tr>}
          <tr><th>Total</th><th className="text-end">{money(o.total)}</th></tr></>} />
      <div className="row g-3 mb-3" id="deliveries">
        <div className="col-lg-6"><div className="card"><div className="card-header">Deliveries &amp; invoices</div><ul className="list-group list-group-flush">
          {d.deliveries.map((x: any) => <li className="list-group-item d-flex justify-content-between" key={x.id}><Link to={`/sales/deliveries/${x.id}`}>{x.delivery_no}</Link>
            <span>{fdate(x.delivery_date)}{x.invoice_id ? <> · <Link to={`/sales/invoices/${x.invoice_id}`}>{x.invoice_no}</Link></> : null}</span></li>)}
          {!d.deliveries.length && <li className="list-group-item text-muted">Nothing delivered yet.</li>}</ul></div></div>
        <div className="col-lg-6"><div className="card"><div className="card-header">Advance taken</div><ul className="list-group list-group-flush">
          {d.advances.map((a: any) => { const left = Number(a.amount) - Number(a.applied); return (
            <li className="list-group-item d-flex justify-content-between align-items-center" key={a.id}><span>{fdate(a.paid_on)} · {methods[a.method] ?? a.method}{a.reference ? ` · ${a.reference}` : ''}</span>
              <span>{money(a.amount)}{left < Number(a.amount) - 0.004 && <small className="text-muted"> ({money(left)} left)</small>}
                {left > 0.004 && can('sales.edit') && <button className="btn btn-sm btn-outline-secondary ms-2" onClick={() => run(`/sales/orders/${id}/advance/${a.id}/refund`, 'Mark the unused advance as given back to the customer?')}>Refunded</button>}</span></li>); })}
          {!d.advances.length && <li className="list-group-item text-muted">No advance taken.</li>}</ul>
          {!['cancelled', 'closed'].includes(st) && can('sales.create') && <div className="card-body border-top"><div className="row g-2">
            <div className="col-4"><input type="number" step="0.01" min="0.01" className="form-control" placeholder="Amount" value={adv.amount} onChange={(e) => setAdv({ ...adv, amount: e.target.value })} /></div>
            <div className="col-3"><input type="date" className="form-control" value={adv.paid_on} onChange={(e) => setAdv({ ...adv, paid_on: e.target.value })} /></div>
            <div className="col-3"><select className="form-select" value={adv.method} onChange={(e) => setAdv({ ...adv, method: e.target.value })}>{Object.entries(methods).filter(([k]) => k !== 'advance').map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></div>
            <div className="col-2"><button className="btn btn-outline-success w-100" onClick={addAdvance}>Add</button></div>
            <div className="col-12"><input className="form-control" placeholder="Reference (optional)" maxLength={80} value={adv.reference} onChange={(e) => setAdv({ ...adv, reference: e.target.value })} /></div></div></div>}</div></div>
      </div>
    </RecordPage>
  );
}
