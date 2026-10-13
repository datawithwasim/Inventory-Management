import { useEffect, useState, type ReactNode } from 'react';
import { Link, useLocation, useParams, useSearchParams } from 'react-router-dom';
import DocView from '../../components/DocView';
import { normalise, paperMm, type Cfg, type DocKind } from '../../lib/printdoc';
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

function PrintFrame({ back, backLabel = 'Back', layoutDoc, children, wide = false, design }: { back: string; backLabel?: string; layoutDoc?: string; children: ReactNode; wide?: boolean; design?: { id: string; cfg: Cfg; list: { id: string; name: string; is_default: boolean }[] } }) {
  useEffect(() => {
    const root = document.documentElement; const was = [root.getAttribute('data-bs-theme'), root.getAttribute('data-theme')];
    root.setAttribute('data-bs-theme', 'light'); root.setAttribute('data-theme', 'light'); document.body.classList.add('print-doc');
    return () => { root.setAttribute('data-bs-theme', was[0] ?? 'light'); root.setAttribute('data-theme', was[1] ?? 'light'); document.body.classList.remove('print-doc'); };
  }, []);
  const { can } = useSession(); const [sp, setSp] = useSearchParams();
  const [pw, ph] = design ? paperMm(design.cfg.page) : [0, 0]; const m = design?.cfg.page.margin ?? 0;
  // a designed document is a sheet of its own paper size; the printer margin is the layout's margin
  const paper = design ? `.print-root .sheet{width:${pw}mm;max-width:none;min-height:${ph}mm;padding:${m}mm;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.2)}body.print-doc{background:#e5e7eb!important}@page{size:${pw}mm ${ph}mm;margin:${m}mm}@media print{.print-root .sheet{width:auto;min-height:0;padding:0;box-shadow:none}body.print-doc{background:#fff!important}}` : '';
  return (
    <div className="print-root">
      <style>{`body.print-doc{background:#fff!important;color:#111!important}.print-root .sheet{max-width:${wide ? 'none' : '820px'};margin:0 auto;padding:24px}@media screen{.print-root .sheet{overflow-x:auto}}@media print{.no-print{display:none!important}.print-root .sheet{padding:0;max-width:none}}${paper}`}</style>
      {design && <div className="no-print" style={{ maxWidth: pw + 'mm', margin: '12px auto 0' }}><div className="d-flex gap-2 align-items-center flex-wrap">
        <button className="btn btn-primary btn-sm" onClick={() => window.print()}><i className="bi bi-printer" /> Print</button>
        <Link className="btn btn-outline-secondary btn-sm" to={back}>{backLabel}</Link>
        {design.list.length > 1 && <select className="form-select form-select-sm w-auto" aria-label="Template" value={design.id} onChange={(e) => { const n = new URLSearchParams(sp); n.set('tpl', e.target.value); setSp(n, { replace: true }); }}>
          {design.list.map((t) => <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (default)' : ''}</option>)}</select>}
        {layoutDoc && can('settings.edit') && <Link className="btn btn-outline-secondary btn-sm" to={`/settings/templates?doc=${layoutDoc}`}>Change this layout</Link>}
      </div></div>}
      <div className="sheet">
        {!design && <div className="no-print mb-3 d-flex gap-2">
          <button className="btn btn-primary btn-sm" onClick={() => window.print()}><i className="bi bi-printer" /> Print</button>
          <Link className="btn btn-outline-secondary btn-sm" to={back}>{backLabel}</Link>
          {layoutDoc && can('settings.edit') && <Link className="btn btn-outline-secondary btn-sm" to={`/settings/templates?doc=${layoutDoc}`}>Change this layout</Link>}
        </div>}
        {children}
      </div>
    </div>
  );
}

function usePrint(path: string, key: unknown[]) {
  const tpl = useSearchParams()[0].get('tpl');
  if (tpl && !path.includes('?')) path += `?tpl=${encodeURIComponent(tpl)}`;
  const q = useQuery({ queryKey: ['print', ...key, tpl], gcTime: 0, staleTime: 0, queryFn: async () => { const r = await get(path); return { data: r.ok ? r.data : null, msg: r.messages?.[0]?.msg as string | undefined }; } });
  return { isLoading: q.isLoading, data: q.data?.data, msg: q.data?.msg };
}

const accentOf = (tpl: Tpl) => (/^#[0-9a-fA-F]{6}$/.test(String(tpl.accent ?? '')) ? tpl.accent : '#1f2937');

/** Company letterhead for the documents that are not designed pages (vouchers, challan): follows the Delivery note layout's header. */
function Letterhead({ head, cfg, title, no, date, meta = {} }: { head: Head; cfg: Cfg; title: string; no: string; date: string; meta?: Record<string, string | null | undefined> }) {
  const h = cfg.header; const accent = cfg.page.accent;
  const rule = h.rule === 'none' ? 'none' : `${h.rule_w}px ${h.rule} ${accent}`;
  return (
    <>
      <style>{`.doc-accent{color:${accent}}.doc-table thead th{border-bottom:2px solid ${accent}}`}</style>
      <div className="d-flex justify-content-between align-items-start pb-2 mb-3" style={{ borderBottom: rule }}>
        <div className="d-flex align-items-center gap-3">
          {h.logo !== 'none' && head.logo ? <img src={head.logo} alt="" style={{ height: `${h.logo_h}mm`, maxWidth: '60mm', objectFit: 'contain' }} /> : null}
          <div><div className="mb-0" style={{ fontSize: `${h.name_size}pt`, fontWeight: 600 }}>{head.name}</div>
            {h.show_address && head.address && <div className="small" style={{ whiteSpace: 'pre-line' }}>{head.address}</div>}
            <div className="small text-muted">{[h.show_phone && head.phone, h.show_email && head.email, h.show_website && head.website].filter(Boolean).join(' · ')}</div>
            {h.show_tax && head.tax_no && <div className="small">Tax no. {head.tax_no}</div>}</div>
        </div>
        <div className="text-end"><div className="doc-accent" style={{ fontSize: `${h.title_size}pt` }}>{title}</div><div><strong>{no}</strong></div><div className="small text-muted">{fdate(date)}</div>
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

/* ---------------- purchase order, invoice, delivery note: drawn from the company's designed layout ---------------- */
function Designed({ kind, path, back, backLabel, layoutDoc, what, titleFallback }: { kind: DocKind; path: string; back: (a: any) => string; backLabel?: string; layoutDoc: string; what: string; titleFallback: string }) {
  const { id } = useParams();
  const q = usePrint(path.replace(':id', String(id)), [kind, id]);
  if (q.isLoading) return <Spinner />;
  const a = q.data; if (!a) return <Fail what={what} msg={q.msg} />;
  const design = a.design as { id: string; name: string; cfg: Cfg; list: any[] };
  return (
    <PrintFrame back={back(a)} backLabel={backLabel} layoutDoc={layoutDoc} design={design}>
      <DocView cfg={design.cfg} title={titleFallback} d={normalise(kind, a)} head={a.head} />
    </PrintFrame>
  );
}
export const PoPrint = () => <Designed kind="purchase_order" path="/purchase/orders/:id/print" back={(a) => `/purchase/orders/${a.po.id}`} layoutDoc="purchase_order" what="Purchase order" titleFallback="Purchase order" />;
export const InvoicePrint = () => <Designed kind="invoice" path="/sales/invoices/:id/print" back={(a) => `/sales/invoices/${a.i.id}`} backLabel="Back to invoice" layoutDoc="invoice" what="Invoice" titleFallback="Tax invoice" />;
export const DeliveryPrint = () => <Designed kind="delivery_note" path="/sales/deliveries/:id/print" back={(a) => `/sales/deliveries/${a.d.id}`} layoutDoc="delivery_note" what="Delivery" titleFallback="Delivery note" />;

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
      <Letterhead head={head} cfg={d.design.cfg} title="Stock-out voucher" no={v.no} date={v.date} meta={v.meta} />
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
      <Letterhead head={head} cfg={x.design.cfg} title="Transfer challan" no={doc.doc_no} date={date} meta={{ Expected: doc.expected_date ? fdate(doc.expected_date) : '' }} />
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
