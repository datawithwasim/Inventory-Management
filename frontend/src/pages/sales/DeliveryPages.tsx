import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, qty as fq } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';
import RecordPage from '../../components/RecordPage';
import DeliveryRows from '../../components/DeliveryRows';
import ItemSearch from '../../components/ItemSearch';
import { refreshSales } from './OrderPages';

export function DeliveryList() {
  const { can, term } = useSession();
  return (
    <DocList title="Deliveries" api="/sales/deliveries" queryKey="deliveries" empty="No deliveries yet."
      actions={() => can('sales.create') ? <Link className="btn btn-primary" to="/sales/deliveries/create"><i className="bi bi-plus-lg" /> Deliver without an order</Link> : null}
      filters={(d, set, sp) => (
        <Filters names={['customer', 'q']} sp={sp} set={set}>
          <div className="col-md-3"><select name="customer" className="form-select" defaultValue={sp.get('customer') ?? ''}><option value="">All {term('customer', true).toLowerCase()}</option>{d.customers.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="Delivery no." defaultValue={sp.get('q') ?? ''} /></div>
        </Filters>)}
      columns={[
        { head: 'Delivery', cell: (r) => <><Link to={`/sales/deliveries/${r.id}`}>{r.delivery_no}</Link> {r.source === 'pos' ? <span className="badge text-bg-light border">POS</span> : null}</> },
        { head: 'Date', cell: (r) => fdate(r.delivery_date) }, { head: term('customer'), cell: (r) => r.customer }, { head: 'From', cell: (r) => r.warehouse },
        { head: 'Order', cell: (r) => r.order_id ? <Link to={`/sales/orders/${r.order_id}`}>{r.order_no}</Link> : '—' },
        { head: 'Invoice', cell: (r) => r.invoice_id ? <Link to={`/sales/invoices/${r.invoice_id}`}>{r.invoice_no}</Link> : <span className="text-muted">not invoiced</span> },
      ]} />
  );
}

// ------------------------------------------------------------------ the delivery editor

type DL = { order_item_id: string; variant_id: string; label: string; tb: boolean; bundle: boolean; unit: string; dec: boolean; qty: string; left: string; unit_price: string; discount_pct: string; tax_rate: string; batch_id: string; location_id: string; warn: string; multi: boolean; hint: string };
const blankDl = (): DL => ({ order_item_id: '', variant_id: '', label: '', tb: false, bundle: false, unit: '', dec: true, qty: '', left: '', unit_price: '', discount_pct: '', tax_rate: '', batch_id: '0', location_id: '0', warn: '', multi: false, hint: '' });
type Stock = { tb: boolean; batches: { id: number; batch_no: string; lot: string | null; balance: number; racks: { location_id: number; code: string; qty: number }[] }[]; racks: { location_id: number; code: string; qty: number }[] };

function Row({ l, linked, warehouse, customer, shade, patch, remove, split }: { l: DL; linked: boolean; warehouse: string; customer: string; shade: boolean; patch: (p: Partial<DL>) => void; remove: () => void; split?: () => void }) {
  const { term } = useSession();
  const [stock, setStock] = useState<Stock | null>(null);
  useEffect(() => {
    if (!l.variant_id || l.bundle) { setStock(null); return; }
    let live = true;
    get(`/lookup/stock?variant=${l.variant_id}&warehouse=${warehouse}`).then((r: any) => { if (live) setStock(r && r.batches ? r : null); });
    return () => { live = false; };
  }, [l.variant_id, l.bundle, warehouse]);
  const racksOf = (): Stock['racks'] => (!stock ? [] : stock.tb ? stock.batches.find((b) => String(b.id) === l.batch_id)?.racks ?? [] : stock.racks);
  // keep the chosen roll / rack among what really exists (a new warehouse or roll changes the choices)
  useEffect(() => {
    if (!stock) return;
    const p: Partial<DL> = {};
    if (stock.tb && l.batch_id !== '0' && !stock.batches.some((b) => String(b.id) === l.batch_id)) p.batch_id = '0';
    const racks = (stock.tb ? stock.batches.find((b) => String(b.id) === (p.batch_id ?? l.batch_id))?.racks : stock.racks) ?? [];
    if (!racks.some((r) => String(r.location_id) === l.location_id)) p.location_id = String(racks[0]?.location_id ?? 0);
    if (Object.keys(p).length) patch(p);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [stock, l.batch_id]);
  const racks = racksOf();
  return (
    <tr>
      <td style={{ minWidth: linked ? 170 : 250 }}>
        {linked ? <span>{l.label}</span> : (
          <ItemSearch path="/lookup/sale-items" extra={`&customer=${customer || 0}&warehouse=${warehouse || 0}`} value={l.variant_id} label={l.label} placeholder="Search item, SKU or barcode"
            onPick={(p) => patch(p ? { variant_id: String(p.row.id), label: p.label, tb: !!Number(p.row.track_batch), bundle: !!Number(p.row.is_bundle), unit: p.row.unit, dec: !!Number(p.row.allow_decimal), unit_price: String(p.row.price ?? ''), discount_pct: Number(p.row.discount) ? String(p.row.discount) : '', tax_rate: Number(p.row.tax_rate) ? String(Number(p.row.tax_rate)) : '', batch_id: '0', location_id: '0', hint: p.row.available != null ? `Free stock: ${fq(p.row.available)} ${p.row.unit}` : '' }
              : { variant_id: '', label: '', hint: '' })} />)}
        {linked && l.left !== '' && <div className="form-text small">Still due: {fq(l.left)} {l.unit}</div>}
        {!linked && l.hint && <div className="form-text small">{l.hint}</div>}
        {l.warn && <div className="small text-danger">{l.warn}</div>}
        {shade && <div className="small text-warning">⚠ Cut from different rolls – the shade may differ.</div>}
      </td>
      <td style={{ minWidth: 190 }}>
        {l.bundle ? <span className="small text-muted">Parts are picked automatically</span> : l.tb ? (
          <select className="form-select form-select-sm" value={l.batch_id} onChange={(e) => patch({ batch_id: e.target.value })}>
            <option value="0">{stock?.batches.length ? 'Choose the roll…' : 'No rolls in stock here'}</option>
            {(stock?.batches ?? []).map((b) => <option key={b.id} value={b.id}>{b.batch_no}{b.lot ? ` · lot ${b.lot}` : ''} · {fq(b.balance)} left</option>)}
          </select>) : <span className="text-muted small">—</span>}
      </td>
      <td style={{ minWidth: 140 }}>{!l.bundle && (
        <select className="form-select form-select-sm" value={l.location_id} onChange={(e) => patch({ location_id: e.target.value })}>
          {racks.map((r) => <option key={r.location_id} value={r.location_id}>{r.code || `No ${term('rack').toLowerCase()}`} · {fq(r.qty)}</option>)}
          {!racks.length && <option value="0">No stock here</option>}
        </select>)}</td>
      <td className="text-nowrap"><input type="number" className="form-control form-control-sm d-inline-block" style={{ width: 100 }} min="0" step={l.dec ? '0.001' : '1'} value={l.qty} onChange={(e) => patch({ qty: e.target.value })} /><span className="ms-1 text-muted small">{l.unit}</span></td>
      <td><input type="number" className={`form-control form-control-sm ${linked ? 'bg-light' : ''}`} style={{ width: 80 }} step="0.01" min="0" readOnly={linked} value={l.unit_price} onChange={(e) => patch({ unit_price: e.target.value })} /></td>
      <td><input type="number" className={`form-control form-control-sm ${linked ? 'bg-light' : ''}`} style={{ width: 70 }} step="0.01" min="0" readOnly={linked} value={l.discount_pct} onChange={(e) => patch({ discount_pct: e.target.value })} /></td>
      <td><input type="number" className={`form-control form-control-sm ${linked ? 'bg-light' : ''}`} style={{ width: 70 }} step="0.01" min="0" readOnly={linked} value={l.tax_rate} onChange={(e) => patch({ tax_rate: e.target.value })} /></td>
      <td className="text-nowrap">{split && !l.bundle && <button type="button" className="btn btn-sm btn-outline-secondary me-1" title="Take part of it from another roll / rack" onClick={split}>＋ split</button>}
        <button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={remove}>×</button></td>
    </tr>
  );
}

/** Deliver goods: against a confirmed order (roll and rack suggested per line) or straight to a customer. This is where stock leaves. */
export function DeliveryForm() {
  const { id: orderId } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('delivery');
  const src = useQuery({ queryKey: ['delivery-form', orderId ?? 'new'], gcTime: 0, queryFn: async () => (await get(orderId ? `/sales/orders/${orderId}/deliver` : '/sales/deliveries/create')) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [lines, setLines] = useState<DL[]>([]);
  const [customer, setCustomer] = useState(''); const [wh, setWh] = useState(''); const [invoice, setInvoice] = useState(true);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data) return;
    if (src.data.redirect && !src.data.data) { nav('/' + String(src.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (values || !src.data.data) return;
    const d = src.data.data; const o = d.order; const v: Record<string, any> = {};
    Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    v.delivery_date = new Date().toISOString().slice(0, 10); v.ship_to = o?.ship_to ?? '';
    setValues(v);
    setCustomer(String(o?.customer_id ?? d.customers?.[0]?.id ?? '')); setWh(String(o?.warehouse_id ?? d.warehouses?.[0]?.id ?? ''));
    const old: any[] = d.oldLines ?? [];
    setLines(old.length ? old.map((l) => ({ ...blankDl(), order_item_id: String(l.order_item_id ?? ''), variant_id: String(l.variant_id), label: l.label, tb: !!Number(l.tb), bundle: !!Number(l.bundle), unit: l.unit ?? '', dec: !!Number(l.dec ?? 1), qty: l.qty !== undefined && l.qty !== '' ? String(Number(l.qty)) : '', left: l.left !== undefined ? String(l.left) : '', unit_price: String(l.unit_price ?? ''), discount_pct: Number(l.discount_pct) ? String(Number(l.discount_pct)) : '', tax_rate: Number(l.tax_rate) ? String(Number(l.tax_rate)) : '', batch_id: String(l.batch_id ?? 0), location_id: String(l.location_id ?? 0), warn: l.warn ?? '', multi: !!l.multi })) : orderId ? [] : [blankDl()]);
  }, [schema.data, src.data, values, orderId, nav]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data.data; if (!d) return <div className="alert alert-danger">{(src.data.messages ?? []).map((m: Msg) => m.msg).join(' ') || 'Goods cannot be delivered on this order right now.'}</div>;
  const order = d.order; const linked = !!order;
  // the same item cut from more than one roll: warn about shade
  const rolls: Record<string, Set<string>> = {};
  lines.forEach((l) => { if (l.tb && l.variant_id && l.batch_id !== '0') (rolls[l.variant_id] ??= new Set()).add(l.batch_id); });
  const shadeFor = (l: DL) => l.multi || (l.tb && !!l.variant_id && (rolls[l.variant_id]?.size ?? 0) > 1);
  const patch = (i: number) => (p: Partial<DL>) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, ...p } : l)));
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), make_invoice: invoice ? 1 : '',
      lines: lines.filter((l) => l.variant_id).map((l) => ({ order_item_id: l.order_item_id, variant_id: l.variant_id, qty: l.qty, batch_id: l.batch_id, location_id: l.location_id, unit_price: l.unit_price, discount_pct: l.discount_pct, tax_rate: l.tax_rate })) };
    if (linked) body.order_id = order.id; else { body.customer_id = customer; body.warehouse_id = wh; }
    const r = await post('/sales/deliveries', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refreshSales(qc); nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  const whName = (d.warehouses ?? []).find((w: any) => String(w.id) === String(order?.warehouse_id))?.name ?? '';
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={linked ? `Deliver · ${order.order_no}` : 'Deliver goods (no order)'} extra={schema.data.canDesign ? <a className="page-layout-link" href={legacyUrl(`/settings/formdesign?form=delivery&return=${encodeURIComponent(location.pathname)}`)}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</a> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(linked ? `/sales/orders/${order.id}` : '/sales/deliveries')}>Cancel</button>
        <button className="btn btn-success" disabled={saving}>{saving ? 'Delivering…' : 'Deliver & take stock out'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body">
        <div className="row g-3 mb-2">
          <div className="col-md-6"><label className="form-label">{term('customer')}</label>
            {linked ? <input className="form-control" disabled value={order.customer} /> : <select className="form-select" required value={customer} onChange={(e) => setCustomer(e.target.value)}>{d.customers.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>}</div>
          <div className="col-md-6"><label className="form-label">Take stock from</label>
            {linked ? <input className="form-control" disabled value={whName} /> : <select className="form-select" required value={wh} onChange={(e) => setWh(e.target.value)}>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>}</div>
        </div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <div className="form-check mb-2"><input className="form-check-input" type="checkbox" id="mk" checked={invoice} onChange={(e) => setInvoice(e.target.checked)} /><label className="form-check-label" htmlFor="mk">Create the invoice now</label></div>
        <p className="text-muted small mb-0">Fabric: pick the <strong>roll</strong> each length is cut from. The oldest roll that can cover the quantity is suggested; if the quantity needs more than one roll you will see a shade warning. Set the quantity to 0 on lines you are not delivering now.</p>
      </div></div>
      <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>{term('item', true)}</span>
        {!linked && <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines((ls) => [...ls, blankDl()])}><i className="bi bi-plus-lg" /> Add {term('item').toLowerCase()}</button>}</div>
        <div className="table-responsive" style={{ overflow: 'visible' }}><table className="table mb-0 align-middle">
          <thead><tr><th>{term('item')}</th><th>Roll ({term('batch', true).toLowerCase()})</th><th>{term('rack')}</th><th>Qty</th><th>Price</th><th>Disc %</th><th>Tax %</th><th /></tr></thead>
          <tbody>{lines.map((l, i) => (
            <Row key={i} l={l} linked={!!l.order_item_id} warehouse={linked ? String(order.warehouse_id) : wh} customer={customer} shade={shadeFor(l)} patch={patch(i)}
              remove={() => setLines((ls) => (ls.length > 1 || linked ? ls.filter((_, j) => j !== i) : [blankDl()]))}
              split={l.order_item_id ? () => setLines((ls) => [...ls.slice(0, i + 1), { ...l, qty: '', batch_id: '0', location_id: '0', warn: '', multi: false }, ...ls.slice(i + 1)]) : undefined} />))}
            {!lines.length && <tr><td colSpan={8} className="text-muted">Nothing left to deliver on this order.</td></tr>}</tbody>
        </table></div></div>
    </form>
  );
}

export function DeliveryDetail() {
  const { id } = useParams(); const qc = useQueryClient();
  const { can } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const nav = useNavigate();
  const q = useQuery({ queryKey: ['delivery', id], queryFn: async () => (await get(`/sales/deliveries/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Delivery not found.</div>;
  const x = d.d;
  const makeInvoice = async () => { const r = await post(`/sales/deliveries/${id}/invoice`); setMsgs(r.messages ?? []); refreshSales(qc); if (r.ok && r.redirect) nav('/' + String(r.redirect).replace(/^\//, '')); };
  return (
    <RecordPage entity="delivery" id={x.id} name={x.delivery_no} back="/sales/deliveries" badges={x.source === 'pos' ? <Badge tone="light">POS</Badge> : null}
      actions={<>
        <a className="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer" href={legacyUrl(`/sales/deliveries/${x.id}/print`)}><i className="bi bi-printer" /> Delivery note</a>
        <a className="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer" href={legacyUrl(`/sales/deliveries/${x.id}/voucher`)}><i className="bi bi-scissors" /> Stock-out voucher</a>
        {x.invoice_id ? <Link className="btn btn-sm btn-outline-primary" to={`/sales/invoices/${x.invoice_id}`}>Invoice {x.invoice_no}</Link> : can('sales.create') && <button className="btn btn-sm btn-primary" onClick={makeInvoice}>Create invoice</button>}
      </>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'lines', label: 'Items delivered' }]}>
      <div className="card mb-3" id="lines"><div className="table-responsive"><DeliveryRows items={d.items} /></div></div>
    </RecordPage>
  );
}
