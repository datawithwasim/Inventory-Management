import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

type Col = { label: string; type: 'text' | 'money' | 'qty' | 'int' | 'date'; sum: boolean };
const numeric = (c: Col) => ['money', 'qty', 'int'].includes(c.type);
/** One cell the way the PHP report_cell() draws it. */
const cell = (c: Col, v: any) => {
  if (v === null || v === undefined || v === '') return '';
  switch (c.type) { case 'money': return money(v); case 'qty': return qty(v); case 'int': return String(parseInt(v, 10)); case 'date': return fdate(String(v)); default: return String(v); }
};

/** The report picker: every report, grouped. */
export function ReportIndex() {
  const q = useQuery({ queryKey: ['reports'], queryFn: async () => (await get('/reports')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  return (
    <>
      <PageHead title="Reports" />
      <p className="text-muted">Pick a report, set the period, then view it on screen or download it as Excel / CSV, or print it as a PDF.</p>
      {Object.entries(d.groups as Record<string, Record<string, any>>).map(([g, list]) => (
        <div key={g}>
          <h2 className="h6 text-uppercase text-muted mt-4 mb-2">{d.names[g] ?? g}</h2>
          <div className="row g-3">
            {Object.entries(list).map(([slug, r]) => (
              <div className="col-md-6 col-xl-4" key={slug}>
                <Link className="card h-100 text-decoration-none" to={`/reports/${slug}`}><div className="card-body"><div className="fw-semibold text-body">{r.title}</div><div className="small text-muted">{r.desc}</div></div></Link>
              </div>
            ))}
          </div>
        </div>
      ))}
    </>
  );
}

/** Any report: the filter bar its definition asks for, the table, totals, downloads. Low stock also turns ticked rows into draft POs. */
export function ReportShow() {
  const { slug } = useParams(); const [sp, setSp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const qs = sp.toString();
  const q = useQuery({ queryKey: ['report', slug, qs], queryFn: async () => await get(`/reports/${slug}${qs ? `?${qs}` : ''}`) });
  const [form, setForm] = useState<Record<string, string>>({}); const [pick, setPick] = useState<Set<number> | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = q.data?.data;
  // the filter boxes start from what the server settled on (defaults for dates, first option of a select …)
  useEffect(() => { if (d) setForm(Object.fromEntries(Object.entries(d.f as Record<string, any>).map(([k, v]) => [k, String(v)]))); }, [d]);
  useEffect(() => { if (d && slug === 'low-stock') setPick(new Set(d.rows.filter((r: any) => Number(r.suggest) > 0).map((r: any) => Number(r.item_id)))); }, [d, slug]);
  if (q.isLoading) return <Spinner />;
  if (!d) return <div className="alert alert-danger">Report not found.</div>;
  const def = d.def; const cols = def.columns as Record<string, Col>; const keys = Object.keys(cols); const rows: any[] = d.rows; const totals = d.totals ?? {};
  const labels: Record<string, string> = { from: 'From', to: 'To', warehouse: term('warehouse'), category: 'Category', customer: term('customer'), supplier: term('supplier'), days: 'Idle for at least (days)', q: 'Search', by: 'Group', status: 'Status', type: 'Movement', kind: 'Show', stock: 'Stock', order: 'Order' };
  const apply = () => setSp(new URLSearchParams(Object.entries(form).filter(([, v]) => v !== '')));
  const isLow = slug === 'low-stock' && can('purchase.create') && rows.length > 0;
  const toggle = (id: number) => setPick((p) => { const n = new Set(p); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const selectable = rows.filter((r) => Number(r.suggest) > 0);
  const createPos = async () => {
    if (!confirm('Create draft purchase orders for the ticked items?')) return;
    setBusy(true); const r = await post('/reports/low-stock/create-pos', { pick: [...(pick ?? [])] }); setBusy(false); setMsgs(r.messages ?? []);
    qc.invalidateQueries({ queryKey: ['report'] }); qc.invalidateQueries({ queryKey: ['pos'] }); qc.invalidateQueries({ queryKey: ['reqs'] });
    if (r.ok && r.redirect && !String(r.redirect).startsWith('reports')) nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  const link = (extra = '') => legacyUrl(`/reports/${slug}${extra}`);
  return (
    <>
      <PageHead title={def.title}>
        <div className="btn-group btn-group-sm">
          {can('reports.export') && <><a className="btn btn-outline-secondary" href={link(`/export/xlsx${qs ? `?${qs}` : ''}`)}><i className="bi bi-file-earmark-excel me-1" />Excel</a>
            <a className="btn btn-outline-secondary" href={link(`/export/csv${qs ? `?${qs}` : ''}`)}><i className="bi bi-filetype-csv me-1" />CSV</a></>}
          <a className="btn btn-outline-secondary" target="_blank" rel="noreferrer" href={link(`?${qs ? qs + '&' : ''}print=1`)}><i className="bi bi-printer me-1" />Print / PDF</a>
        </div>
      </PageHead>
      <div className="mb-3"><Link to="/reports" className="small text-decoration-none">« All reports</Link><p className="text-muted mb-0">{def.desc}</p></div>
      <form className="card mb-3" onSubmit={(e) => { e.preventDefault(); apply(); }}><div className="card-body row g-2 align-items-end">
        {(def.filters as string[]).map((name) => (
          <div className="col-6 col-md-3 col-xl-2" key={name}>
            <label className="form-label small mb-1" htmlFor={`f-${name}`}>{labels[name] ?? name}</label>
            {name === 'from' || name === 'to' ? <input type="date" className="form-control form-control-sm" id={`f-${name}`} value={form[name] ?? ''} onChange={(e) => setForm({ ...form, [name]: e.target.value })} />
              : name === 'days' ? <input type="number" min={1} className="form-control form-control-sm" id="f-days" value={form.days ?? ''} onChange={(e) => setForm({ ...form, days: e.target.value })} />
              : name === 'q' ? <input type="search" className="form-control form-control-sm" id="f-q" placeholder="Name, SKU…" value={form.q ?? ''} onChange={(e) => setForm({ ...form, q: e.target.value })} />
              : d.lists[name] ? <select className="form-select form-select-sm" id={`f-${name}`} value={form[name] ?? '0'} onChange={(e) => setForm({ ...form, [name]: e.target.value })}>
                  <option value="0">{['customer', 'supplier'].includes(name) && def.needs ? '— choose —' : 'All'}</option>{d.lists[name].map((o: any) => <option key={o.id} value={o.id}>{o.name}</option>)}</select>
              : <select className="form-select form-select-sm" id={`f-${name}`} value={form[name] ?? ''} onChange={(e) => setForm({ ...form, [name]: e.target.value })}>{Object.entries(def.select[name] as Record<string, string>).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>}
          </div>))}
        <div className="col-auto"><button className="btn btn-sm btn-primary">Show report</button></div>
      </div></form>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      {d.truncated && <div className="alert alert-info py-2 small">Showing the first {d.screenLimit} rows. Narrow the filters, or download Excel/CSV to get everything.</div>}
      <div className="card"><div className="card-body p-0"><div className="table-responsive"><table className="table table-sm table-hover align-middle report-table mb-0">
        <thead className="table-light"><tr>
          {isLow && <th style={{ width: 32 }}><input type="checkbox" className="form-check-input" aria-label="Select all" checked={!!pick && pick.size === selectable.length && selectable.length > 0} onChange={(e) => setPick(e.target.checked ? new Set(selectable.map((r) => Number(r.item_id))) : new Set())} /></th>}
          {keys.map((k) => <th key={k} className={numeric(cols[k]) ? 'text-end' : ''}>{cols[k].label}</th>)}</tr></thead>
        <tbody>
          {rows.map((r, i) => <tr key={i}>
            {isLow && <td><input type="checkbox" className="form-check-input" disabled={!(Number(r.suggest) > 0)} checked={!!pick?.has(Number(r.item_id))} onChange={() => toggle(Number(r.item_id))} /></td>}
            {keys.map((k) => <td key={k} className={numeric(cols[k]) ? 'text-end text-nowrap' : ''}>{cell(cols[k], r[k])}</td>)}</tr>)}
          {!rows.length && <tr><td colSpan={keys.length + (isLow ? 1 : 0)} className="text-center text-muted py-4">{def.needs && !Number(d.f[def.needs]) ? `Choose a ${def.needs} above to see the statement.` : 'No data for these filters.'}</td></tr>}
        </tbody>
        {rows.length > 0 && Object.keys(totals).length > 0 && <tfoot className="table-light fw-semibold"><tr>
          {isLow && <td />}
          {keys.map((k, i) => <td key={k} className={numeric(cols[k]) ? 'text-end text-nowrap' : ''}>{i === 0 ? 'Total' : totals[k] !== undefined ? cell(cols[k], totals[k]) : ''}</td>)}</tr></tfoot>}
      </table></div></div></div>
      {isLow && <div className="mt-3 d-flex align-items-center gap-3">
        <button className="btn btn-primary" disabled={busy || !pick?.size} onClick={createPos}><i className="bi bi-cart-plus me-1" />Create draft purchase orders</button>
        <span className="text-muted small">One draft PO per last supplier. Items never bought before go into one requisition. Nothing is sent until you submit the PO.</span></div>}
      <div className="text-muted small mt-2">{rows.length} row{rows.length === 1 ? '' : 's'}</div>
    </>
  );
}
