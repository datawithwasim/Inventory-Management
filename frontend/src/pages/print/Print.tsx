import { useEffect, useState, type ReactNode } from 'react';
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Spinner } from '../../components/ui';
import { barcodesPending, LabelView, type El, type Page, type Template, type Values } from '../labels/render';

/* Documents made for paper: purchase order, invoice, delivery note, stock-out voucher, transfer challan, report, barcode labels.
   They open on their own (no menu), always in light colours, with a Print button that is hidden on paper. */

type Head = { name: string; address: string; phone: string; email: string; website: string; tax_no: string; bank: string; logo: string | null };
type Tpl = Record<string, any>;

function PrintFrame({ back, backLabel = 'Back', layoutDoc, children, wide = false }: { back: string; backLabel?: string; layoutDoc?: string; children: ReactNode; wide?: boolean }) {
  useEffect(() => {
    const root = document.documentElement; const was = [root.getAttribute('data-bs-theme'), root.getAttribute('data-theme')];
    root.setAttribute('data-bs-theme', 'light'); root.setAttribute('data-theme', 'light'); document.body.classList.add('print-doc');
    return () => { root.setAttribute('data-bs-theme', was[0] ?? 'light'); root.setAttribute('data-theme', was[1] ?? 'light'); document.body.classList.remove('print-doc'); };
  }, []);
  const { can } = useSession();
  return (
    <div className="print-root">
      <style>{`body.print-doc{background:#fff!important;color:#111!important}.print-root .sheet{max-width:${wide ? 'none' : '820px'};margin:0 auto;padding:24px}@media screen{.print-root .sheet{overflow-x:auto}}@media print{.no-print{display:none!important}.print-root .sheet{padding:0;max-width:none}}`}</style>
      <div className="sheet">
        <div className="no-print mb-3 d-flex gap-2">
          <button className="btn btn-primary btn-sm" onClick={() => window.print()}><i className="bi bi-printer" /> Print</button>
          <Link className="btn btn-outline-secondary btn-sm" to={back}>{backLabel}</Link>
          {layoutDoc && can('settings.edit') && <Link className="btn btn-outline-secondary btn-sm" to={`/settings/templates?doc=${layoutDoc}`}>Change this layout</Link>}
        </div>
        {children}
      </div>
    </div>
  );
}

function usePrint(path: string, key: unknown[]) {
  const q = useQuery({ queryKey: ['print', ...key], gcTime: 0, staleTime: 0, queryFn: async () => { const r = await get(path); return { data: r.ok ? r.data : null, msg: r.messages?.[0]?.msg as string | undefined }; } });
  return { isLoading: q.isLoading, data: q.data?.data, msg: q.data?.msg };
}

const accentOf = (tpl: Tpl) => (/^#[0-9a-fA-F]{6}$/.test(String(tpl.accent ?? '')) ? tpl.accent : '#1f2937');

/** Company letterhead and the document title block. */
function Letterhead({ head, tpl, title, no, date, meta = {} }: { head: Head; tpl: Tpl; title: string; no: string; date: string; meta?: Record<string, string | null | undefined> }) {
  const accent = accentOf(tpl);
  return (
    <>
      <style>{`.doc-accent{color:${accent}}.doc-rule{border-bottom:3px solid ${accent}}.doc-table thead th{border-bottom:2px solid ${accent}}`}</style>
      <div className="d-flex justify-content-between align-items-start pb-2 mb-3 doc-rule">
        <div className="d-flex align-items-center gap-3">
          {tpl.show_logo && head.logo ? <img src={head.logo} alt="" style={{ maxHeight: 70, maxWidth: 180 }} /> : null}
          <div><div className="h5 mb-0">{head.name}</div>
            {tpl.show_company ? <>
              {head.address && <div className="small" style={{ whiteSpace: 'pre-line' }}>{head.address}</div>}
              <div className="small text-muted">{[head.phone, head.email, head.website].filter(Boolean).join(' · ')}</div>
              {head.tax_no && <div className="small">Tax no. {head.tax_no}</div>}
            </> : null}</div>
        </div>
        <div className="text-end"><div className="h4 mb-0 doc-accent">{tpl.title ? tpl.title : title}</div><div><strong>{no}</strong></div><div className="small text-muted">{fdate(date)}</div>
          {Object.entries(meta).map(([k, v]) => (v ? <div key={k} className="small text-muted">{k}: {v}</div> : null))}</div>
      </div>
    </>
  );
}

/** Notes, bank details, terms, signature line and footer line. */
function DocFoot({ head, tpl, notes, showBank }: { head: Head; tpl: Tpl; notes?: string | null; showBank?: boolean }) {
  return (
    <>
      {notes ? <div className="small mt-3"><strong>Note:</strong> {notes}</div> : null}
      {showBank && head.bank ? <div className="small mt-3" style={{ whiteSpace: 'pre-line' }}><strong>Bank details</strong><br />{head.bank}</div> : null}
      {tpl.terms ? <div className="small text-muted mt-3" style={{ whiteSpace: 'pre-line' }}><strong>Terms &amp; conditions</strong><br />{tpl.terms}</div> : null}
      {tpl.signature ? <div className="row mt-5 pt-4"><div className="col-6 offset-6 text-center"><div className="border-top pt-1 small">{tpl.signature_label || 'Authorised signatory'}</div></div></div> : null}
      {tpl.footer ? <div className="text-center small text-muted mt-4">{tpl.footer}</div> : null}
    </>
  );
}

const Fail = ({ what, msg }: { what: string; msg?: string }) => <div className="p-4"><div className="alert alert-warning">{msg || `${what} not found.`}</div></div>;

/* ---------------- purchase order ---------------- */
export function PoPrint() {
  const { id } = useParams(); const { term } = useSession();
  const q = usePrint(`/purchase/orders/${id}/print`, ['po', id]);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <Fail what="Purchase order" msg={q.msg} />;
  const { po, items, tpl, head } = d;
  return (
    <PrintFrame back={`/purchase/orders/${po.id}`} layoutDoc="purchase_order">
      <Letterhead head={head} tpl={tpl} title="Purchase order" no={po.po_no} date={po.order_date} meta={tpl.show_expected ? { Expected: po.expected_date ? fdate(po.expected_date) : '' } : {}} />
      <div className="row mb-3"><div className="col-6"><div className="small text-muted">{term('supplier')}</div><strong>{po.supplier}</strong>
        {po.sup_address && <div className="small">{po.sup_address}</div>}{po.sup_phone && <div className="small">{po.sup_phone}</div>}{po.sup_tax && <div className="small">Tax no. {po.sup_tax}</div>}</div>
        <div className="col-6"><div className="small text-muted">Deliver to</div><strong>{po.warehouse}</strong></div></div>
      <table className="table table-sm align-middle doc-table"><thead><tr><th>Item</th><th className="text-end">Qty</th><th className="text-end">Price</th>{tpl.show_tax ? <th className="text-end">Tax %</th> : null}<th className="text-end">Amount</th></tr></thead><tbody>
        {items.map((l: any) => (
          <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small>{l.their ? <div className="small text-muted">Their: {l.their}</div> : null}</td>
            <td className="text-end">{qty(l.qty_ordered)} {l.unit}</td><td className="text-end">{money(l.unit_price)}</td>{tpl.show_tax ? <td className="text-end">{qty(l.tax_rate)}</td> : null}<td className="text-end">{money(tpl.show_tax ? l.net : l.tot)}</td></tr>))}
      </tbody></table>
      <div className="row justify-content-end"><div className="col-md-5"><table className="table table-sm table-borderless"><tbody>
        <tr><td>Subtotal</td><td className="text-end">{money(po.subtotal)}</td></tr><tr><td>Tax</td><td className="text-end">{money(po.tax_total)}</td></tr>
        <tr className="border-top"><th>Total</th><th className="text-end">{money(po.total)}</th></tr></tbody></table></div></div>
      <DocFoot head={head} tpl={tpl} notes={po.notes} showBank={!!tpl.show_bank} />
    </PrintFrame>
  );
}

/* ---------------- sales invoice ---------------- */
export function InvoicePrint() {
  const { id } = useParams();
  const q = usePrint(`/sales/invoices/${id}/print`, ['inv', id]);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <Fail what="Invoice" msg={q.msg} />;
  const { i, items, tpl, head, due } = d;
  return (
    <PrintFrame back={`/sales/invoices/${i.id}`} backLabel="Back to invoice" layoutDoc="invoice">
      <Letterhead head={head} tpl={tpl} title="Tax invoice" no={i.invoice_no} date={i.invoice_date} meta={{ Due: i.due_date ? fdate(i.due_date) : '', Order: i.order_no }} />
      <div className="mb-3"><div className="small text-muted">Billed to</div><strong>{i.customer}</strong>
        {i.cust_address && <div>{i.cust_address}</div>}{i.cust_phone && <div>{i.cust_phone}</div>}{i.cust_tax && <div>Tax no. {i.cust_tax}</div>}
        {i.ship_to && tpl.show_ship_to ? <div className="small text-muted mt-1">Deliver to: {i.ship_to}</div> : null}</div>
      <table className="table table-sm align-middle doc-table"><thead><tr><th>Item</th><th className="text-end">Qty</th><th className="text-end">Price</th>{tpl.show_discount ? <th className="text-end">Disc</th> : null}{tpl.show_tax ? <th className="text-end">Tax</th> : null}<th className="text-end">Amount</th></tr></thead><tbody>
        {items.filter((l: any) => l.parent_id === null).map((l: any) => (
          <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''}{l.batch_no && tpl.show_batch ? <div className="small text-muted">Roll {l.batch_no}</div> : null}</td>
            <td className="text-end">{qty(l.qty)} {l.unit}</td><td className="text-end">{money(l.unit_price)}</td>
            {tpl.show_discount ? <td className="text-end">{l.dsc > 0 ? money(l.dsc) : ''}</td> : null}
            {tpl.show_tax ? <td className="text-end">{money(l.tx)} <small className="text-muted">({qty(l.tax_rate)}%)</small></td> : null}
            <td className="text-end">{money(tpl.show_tax ? l.net : l.tot)}</td></tr>))}
      </tbody></table>
      <div className="row justify-content-end"><div className="col-md-5"><table className="table table-sm table-borderless"><tbody>
        <tr><td>Gross</td><td className="text-end">{money(i.subtotal)}</td></tr>
        {Number(i.discount_total) > 0 && <tr><td>Discount</td><td className="text-end">− {money(i.discount_total)}</td></tr>}
        <tr><td>Tax</td><td className="text-end">{money(i.tax_total)}</td></tr>
        {Number(i.delivery_charge) > 0 && <tr><td>Delivery</td><td className="text-end">{money(i.delivery_charge)}</td></tr>}
        {Number(i.installation_charge) > 0 && <tr><td>Installation</td><td className="text-end">{money(i.installation_charge)}</td></tr>}
        <tr className="border-top"><th>Total</th><th className="text-end">{money(i.total)}</th></tr>
        {Number(i.returned_amount) > 0 && <tr><td>Returns</td><td className="text-end">− {money(i.returned_amount)}</td></tr>}
        <tr><td>Paid</td><td className="text-end">{money(i.paid_amount)}</td></tr>
        <tr><th>Balance due</th><th className="text-end">{money(Math.max(0, Number(due)))}</th></tr></tbody></table></div></div>
      <DocFoot head={head} tpl={tpl} notes={i.notes} showBank={!!tpl.show_bank} />
    </PrintFrame>
  );
}

/* ---------------- delivery note ---------------- */
export function DeliveryPrint() {
  const { id } = useParams();
  const q = usePrint(`/sales/deliveries/${id}/print`, ['del', id]);
  if (q.isLoading) return <Spinner />;
  const x = q.data; if (!x) return <Fail what="Delivery" msg={q.msg} />;
  const { d, items, tpl, head } = x;
  return (
    <PrintFrame back={`/sales/deliveries/${d.id}`} layoutDoc="delivery_note">
      <Letterhead head={head} tpl={tpl} title="Delivery note" no={d.delivery_no} date={d.delivery_date} meta={{ Order: d.order_no, Invoice: d.invoice_no }} />
      <div className="mb-3"><div className="small text-muted">Delivered to</div><strong>{d.customer}</strong>
        {d.cust_phone && <div>{d.cust_phone}</div>}{tpl.show_ship_to ? <div>{d.ship_to || d.cust_address || ''}</div> : null}</div>
      <table className="table table-sm align-middle doc-table"><thead><tr><th>Item</th>{tpl.show_batch ? <th>Roll</th> : null}{tpl.show_rack ? <th>Rack</th> : null}<th className="text-end">Qty</th>{tpl.show_prices ? <th className="text-end">Amount</th> : null}</tr></thead><tbody>
        {items.map((l: any) => { const part = l.parent_id !== null; return (
          <tr key={l.id} className={part ? 'small text-muted' : ''}><td>{part ? '  ↳ ' : ''}{l.item_name}{l.vname ? ` — ${l.vname}` : ''}</td>
            {tpl.show_batch ? <td>{l.batch_no ?? ''}</td> : null}{tpl.show_rack ? <td>{l.is_bundle ? '' : l.rack ?? ''}</td> : null}
            <td className="text-end">{qty(l.qty)} {l.unit}</td>{tpl.show_prices ? <td className="text-end">{part ? '' : money(l.tot)}</td> : null}</tr>); })}
      </tbody></table>
      <DocFoot head={head} tpl={tpl} notes={d.note} />
    </PrintFrame>
  );
}

/* ---------------- stock-out voucher (delivery, adjustment, transfer) ---------------- */
export function VoucherPrint({ kind }: { kind: 'delivery' | 'adjustment' | 'transfer' }) {
  const { id } = useParams();
  const base = kind === 'delivery' ? 'sales/deliveries' : kind === 'adjustment' ? 'stock/adjustments' : 'stock/transfers';
  const q = usePrint(`/${base}/${id}/voucher`, ['voucher', kind, id]);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <Fail what="Voucher" msg={q.msg} />;
  const { v, lines, head } = d; const tpl = { ...d.tpl, title: '', signature: 0, terms: '' };
  const transfer = v.kind === 'transfer'; const units = new Set(lines.map((l: any) => l.unit));
  return (
    <PrintFrame back={'/' + String(v.back).replace(/^\//, '')}>
      <style>{`.vch-table th,.vch-table td{vertical-align:middle}.vch-qty{font-size:1.15rem;font-weight:700;white-space:nowrap}.vch-table td{height:46px}.vch-blank{min-width:80px;border:1px solid #aaa!important}.vch-table th{font-size:.7rem}.vch-table td.roll{white-space:nowrap}.vch-rack{font-size:1.05rem;font-weight:700;white-space:nowrap}.vch-sign{border-top:1px solid #333;padding-top:4px;font-size:.8rem;margin-top:46px}@media print{.vch-table tr{page-break-inside:avoid}}`}</style>
      <Letterhead head={head} tpl={tpl} title="Stock-out voucher" no={v.no} date={v.date} meta={v.meta} />
      <div className="d-flex justify-content-between mb-3"><div><div className="small text-muted">{v.kind === 'delivery' ? 'Issued for' : 'Purpose'}</div><strong>{v.for}</strong></div>
        <div className="text-end"><div className="small text-muted">Take stock from</div><strong>{v.warehouse}</strong></div></div>
      <table className="table table-sm doc-table vch-table">
        <thead><tr><th style={{ width: 34 }}>#</th><th>Rack</th><th>Item</th><th>Roll</th><th className="text-end">Roll length before</th><th className="text-end">Issue qty</th>{transfer ? <th>To rack</th> : null}<th>Actual cut</th><th>Left on roll</th></tr></thead>
        <tbody>{lines.map((l: any, n: number) => (
          <tr key={n}><td>{n + 1}</td><td className="vch-rack">{l.rack !== '' ? l.rack : <span className="text-muted fw-normal">No rack</span>}</td>
            <td>{l.item} <small className="text-muted">{l.sku}</small></td><td className="fw-semibold roll">{l.batch !== '' ? l.batch : <span className="text-muted fw-normal">—</span>}</td>
            <td className="text-end">{l.before !== null ? `${qty(l.before)} ${l.unit}` : <span className="text-muted">—</span>}</td>
            <td className="text-end vch-qty">{qty(l.qty)} <small className="fw-normal">{l.unit}</small></td>
            {transfer ? <td className="vch-rack">{l.to_rack !== '' ? l.to_rack : '—'}</td> : null}<td className="vch-blank" /><td className="vch-blank" /></tr>))}</tbody>
        <tfoot><tr><td colSpan={transfer ? 6 : 5} className="text-end small text-muted">Lines: {lines.length}</td><td className="text-end fw-semibold">{units.size === 1 ? `${qty(lines.reduce((a: number, l: any) => a + Number(l.qty), 0))} ${lines[0].unit}` : ''}</td><td colSpan={transfer ? 3 : 2} /></tr></tfoot>
      </table>
      <div className="small text-muted">Take each line from the rack and roll shown. Write the length actually cut and what is left on the roll.</div>
      <div className="row mt-4"><div className="col-4"><div className="vch-sign">Issued by (warehouse){v.by ? ` — ${v.by}` : ''}</div></div><div className="col-4"><div className="vch-sign">Received by (cutter)</div></div><div className="col-4"><div className="vch-sign">Checked by</div></div></div>
      <DocFoot head={head} tpl={tpl} notes={v.note} />
    </PrintFrame>
  );
}

/* ---------------- transfer challan ---------------- */
export function ChallanPrint() {
  const { id } = useParams();
  const q = usePrint(`/stock/transfers/${id}/challan`, ['challan', id]);
  if (q.isLoading) return <Spinner />;
  const x = q.data; if (!x) return <Fail what="Transfer" msg={q.msg} />;
  const { doc, lines, from, to, head } = x; const tpl = { ...x.tpl, title: '', signature: 0, terms: '' };
  const between = Number(doc.to_warehouse_id) !== Number(doc.warehouse_id); const units = new Set(lines.map((l: any) => l.unit));
  const date = String(doc.dispatched_at || doc.created_at).slice(0, 10);
  return (
    <PrintFrame back={`/stock/transfers/${doc.id}`}>
      <style>{`.chl-table th,.chl-table td{vertical-align:middle}.chl-table td{height:40px}.chl-blank{min-width:80px;border:1px solid #aaa!important}.chl-table td.roll{white-space:nowrap}.chl-sign{border-top:1px solid #333;padding-top:4px;font-size:.8rem;margin-top:46px}@media print{.chl-table tr{page-break-inside:avoid}}`}</style>
      <Letterhead head={head} tpl={tpl} title="Transfer challan" no={doc.doc_no} date={date} meta={{ Expected: doc.expected_date ? fdate(doc.expected_date) : '' }} />
      <div className="row g-3 mb-3">
        <div className="col-6"><div className="small text-muted">From</div><strong>{from.name}</strong>{from.address ? <div className="small" style={{ whiteSpace: 'pre-line' }}>{from.address}</div> : null}</div>
        <div className="col-6"><div className="small text-muted">To</div><strong>{to.name}</strong>{to.address ? <div className="small" style={{ whiteSpace: 'pre-line' }}>{to.address}</div> : null}</div>
      </div>
      {between && (doc.transport || doc.vehicle_no || doc.lr_no) ? (
        <div className="row g-3 mb-3 small">{([['Transporter', doc.transport], ['Vehicle no.', doc.vehicle_no], ['LR / challan no.', doc.lr_no]] as const).filter(([, v]) => v).map(([k, v]) => <div key={k} className="col-4"><span className="text-muted">{k}</span><br /><strong>{v}</strong></div>)}</div>
      ) : null}
      <table className="table table-sm doc-table chl-table">
        <thead><tr><th style={{ width: 34 }}>#</th><th>Item</th><th>Roll</th><th>Packed from rack</th><th className="text-end">Qty sent</th><th>Qty received</th><th>Put on rack</th><th>Remark</th></tr></thead>
        <tbody>{lines.map((l: any, n: number) => (
          <tr key={n}><td>{n + 1}</td><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small></td><td className="fw-semibold roll">{l.batch_no ?? <span className="text-muted fw-normal">—</span>}</td>
            <td>{l.rack ?? '—'}</td><td className="text-end fw-bold">{qty(l.qty)} <small className="fw-normal">{l.unit}</small></td><td className="chl-blank" /><td className="chl-blank" /><td className="chl-blank" /></tr>))}</tbody>
        <tfoot><tr><td colSpan={4} className="text-end small text-muted">Lines: {lines.length}</td><td className="text-end fw-semibold">{units.size === 1 ? `${qty(lines.reduce((a: number, l: any) => a + Number(l.qty), 0))} ${lines[0].unit}` : ''}</td><td colSpan={3} /></tr></tfoot>
      </table>
      <div className="small text-muted">Receiver: check every roll against this challan, write what you actually received and the rack it was put on. Report any difference the same day.</div>
      <div className="row mt-4"><div className="col-3"><div className="chl-sign">Sent by</div></div><div className="col-3"><div className="chl-sign">Driver / transporter</div></div><div className="col-3"><div className="chl-sign">Received by</div></div><div className="col-3"><div className="chl-sign">Date &amp; time received</div></div></div>
      <DocFoot head={head} tpl={tpl} notes={doc.note} />
    </PrintFrame>
  );
}

/* ---------------- report ---------------- */
type Col = { label: string; type: 'text' | 'money' | 'qty' | 'int' | 'date' };
const num = (c: Col) => ['money', 'qty', 'int'].includes(c.type);
const cellOf = (c: Col, v: any) => {
  if (v === null || v === undefined || v === '') return '';
  switch (c.type) { case 'money': return money(v); case 'qty': return qty(v); case 'int': return String(parseInt(v, 10)); case 'date': return fdate(String(v)); default: return String(v); }
};
export function ReportPrint() {
  const { slug } = useParams(); const [sp] = useSearchParams();
  const qs = new URLSearchParams(sp); qs.set('print', '1');
  const q = usePrint(`/reports/${slug}?${qs.toString()}`, ['report', slug, qs.toString()]);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <Fail what="Report" msg={q.msg} />;
  const def = d.def; const cols = def.columns as Record<string, Col>; const keys = Object.keys(cols); const rows: any[] = d.rows; const totals = d.totals ?? {};
  const back = `/reports/${slug}${sp.toString() ? `?${sp.toString()}` : ''}`;
  return (
    <PrintFrame back={back} wide>
      <style>{'@page{size:A4 landscape;margin:12mm}.report-table{font-size:11px}.report-table th,.report-table td{padding:3px 6px!important}'}</style>
      <div className="no-print mb-2"><span className="text-muted small">Choose “Save as PDF” as the printer. Landscape A4 is pre-selected.</span></div>
      <h1 className="h5 mb-0">{def.title}</h1>
      <div className="text-muted small mb-2">{d.company} · printed {fdate(d.printed)}{d.f?.from ? ` · ${fdate(d.f.from)} – ${fdate(d.f.to)}` : ''}</div>
      <table className="table table-sm align-middle report-table">
        <thead className="table-light"><tr>{keys.map((k) => <th key={k} className={num(cols[k]) ? 'text-end' : ''}>{cols[k].label}</th>)}</tr></thead>
        <tbody>
          {rows.map((r, n) => <tr key={n}>{keys.map((k) => <td key={k} className={num(cols[k]) ? 'text-end text-nowrap' : ''}>{cellOf(cols[k], r[k])}</td>)}</tr>)}
          {!rows.length && <tr><td colSpan={keys.length} className="text-center text-muted py-4">No data for these filters.</td></tr>}
        </tbody>
        {rows.length > 0 && Object.keys(totals).length > 0 && <tfoot className="table-light fw-semibold"><tr>{keys.map((k, n) => <td key={k} className={num(cols[k]) ? 'text-end text-nowrap' : ''}>{n === 0 ? 'Total' : totals[k] !== undefined ? cellOf(cols[k], totals[k]) : ''}</td>)}</tr></tfoot>}
      </table>
      {d.truncated && <p className="small text-muted">First {d.screenLimit} rows only — use Excel/CSV for the full list.</p>}
    </PrintFrame>
  );
}

/* ---------------- barcode labels ---------------- */
const MM = 96 / 25.4;   // CSS pixels in a millimetre: what the printer turns back into exact millimetres
const mmStr = (v: number) => `${Math.round(v * 1000) / 1000}mm`;
export function LabelPrint() {
  const { search } = useLocation();
  const q = usePrint(`/labels/print${search}`, ['labels', search]);
  const [waiting, setWaiting] = useState(false);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <Fail what="Labels" msg={q.msg} />;
  const tpl = d.tpl as Template; const page = d.page as Page; const labels = d.labels as Values[]; const skip = d.skip as number;
  const w = Number(tpl.w), h = Number(tpl.h), cols = Number(page.cols), rowsN = Number(page.rows), gx = Number(page.gap_x), gy = Number(page.gap_y);
  const sheet = page.mode === 'sheet';
  let pw: number, ph: number, ml: number, mt: number, per: number;
  if (sheet) {
    pw = Number(page.paper_w); ph = Number(page.paper_h);
    ml = page.margin_left ?? Math.max(0, (pw - (cols * w + (cols - 1) * gx)) / 2); mt = page.margin_top ?? Math.max(0, (ph - (rowsN * h + (rowsN - 1) * gy)) / 2); per = cols * rowsN;
  } else { pw = cols * w + (cols - 1) * gx; ph = h; ml = 0; mt = 0; per = cols; }
  const cells: (Values | null)[] = [...Array(skip).fill(null), ...labels];
  const pages: (Values | null)[][] = []; for (let i = 0; i < cells.length; i += per) pages.push(cells.slice(i, i + per));
  const print = async () => {
    setWaiting(true);
    for (let i = 0; i < 100 && barcodesPending() > 0; i++) await new Promise((r) => setTimeout(r, 100));   // the barcode pictures must be there before paper
    setWaiting(false); window.print();
  };
  return (
    <div className="print-root">
      <style>{`body.print-doc{background:#eee!important}.lpage{position:relative;width:${mmStr(pw)};height:${mmStr(ph)};overflow:hidden;background:#fff;break-after:page;page-break-after:always}.lpage:last-child{break-after:auto;page-break-after:auto}.lbl{position:absolute;width:${mmStr(w)};height:${mmStr(h)};overflow:hidden;box-sizing:border-box}.lbl.out{outline:.2mm solid #888;outline-offset:-.1mm}@page{size:${mmStr(pw)} ${mmStr(ph)};margin:0}@media print{html,body{margin:0;padding:0;background:#fff!important}.lbl.out{outline-color:#aaa}.no-print{display:none!important}}@media screen{.lpage{margin:12px auto;box-shadow:0 1px 6px rgba(0,0,0,.25)}.lbl{outline:1px dashed #ddd}.lbl.out{outline:.2mm solid #888}}`}</style>
      <LabelBar onPrint={print} waiting={waiting} count={labels.length} pages={pages.length} tpl={tpl} w={w} h={h} />
      {pages.map((cs, pi) => (
        <div className="lpage" key={pi}>
          {cs.map((values, i) => values === null ? null : (
            <div key={i} className={`lbl${page.outline ? ' out' : ''}`} style={{ left: mmStr(ml + (i % cols) * (w + gx)), top: mmStr(mt + Math.floor(i / cols) * (h + gy)) }}>
              <LabelView w={w} h={h} elements={tpl.elements as El[]} values={values} scale={MM} shadow={false} />
            </div>))}
        </div>))}
      {!pages.length && <p className="p-3 text-muted">No labels selected. Go back and enter how many copies of each you want.</p>}
    </div>
  );
}
function LabelBar({ onPrint, waiting, count, pages, tpl, w, h }: { onPrint: () => void; waiting: boolean; count: number; pages: number; tpl: Template; w: number; h: number }) {
  const nav = useLocation();
  useEffect(() => { document.body.classList.add('print-doc'); return () => document.body.classList.remove('print-doc'); }, []);
  const n = (v: number) => String(Math.round(v * 100) / 100);
  return (
    <div className="no-print p-3 bg-white border-bottom position-sticky top-0" style={{ zIndex: 5 }}>
      <button className="btn btn-primary btn-sm" onClick={onPrint} disabled={waiting}><i className="bi bi-printer me-1" />{waiting ? 'Preparing…' : 'Print'}</button>
      <Link className="btn btn-outline-secondary btn-sm ms-2" to="/labels" state={nav.state}>Back</Link>
      <span className="text-muted small ms-2">{count} label{count === 1 ? '' : 's'} · {pages} page{pages === 1 ? '' : 's'} · template “{tpl.name}” ({n(w)} × {n(h)} mm). In the print window set margins to “None” and scale to 100% (not “fit to page”).</span>
    </div>
  );
}
