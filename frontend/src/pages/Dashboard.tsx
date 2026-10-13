import { useState, type MouseEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { get, post } from '../lib/api';
import { fdate, money, qty } from '../lib/format';
import { isReact, localPath } from '../lib/routes';
import { useSession } from '../lib/session';
import { PageHead, Spinner } from '../components/ui';

const pct = (now: number, prev: number) => (prev > 0 ? ((now - prev) / prev) * 100 : null);

function Kpi({ label, value, sub, tone, href }: { label: string; value: string; sub?: string; tone?: string; href?: string }) {
  const body = <div className="card h-100"><div className="card-body"><div className="text-muted small">{label}</div><div className={`fs-3 fw-semibold ${tone ?? ''}`}>{value}</div>{sub && <div className="small text-muted">{sub}</div>}</div></div>;
  return <div className="col-6 col-lg-3">{href ? <Link to={href} className="text-decoration-none text-reset">{body}</Link> : body}</div>;
}

function ScopedDashboard() {
  const { term, session } = useSession();
  const q = useQuery({ queryKey: ['dashboard'], queryFn: async () => (await get('/dashboard')).data });
  if (q.isLoading) return <Spinner />;
  if (q.error || !q.data) return <div className="alert alert-danger">Could not load the dashboard.</div>;
  const d = q.data;
  const sc = pct(d.salesNow, d.salesPrev), bc = pct(d.buyNow, d.buyPrev);
  const trend = (c: number | null) => (c === null ? 'no earlier figure' : `${c >= 0 ? '▲' : '▼'} ${Math.abs(c).toFixed(1)}% vs previous period`);
  return (
    <>
      <PageHead title={`Hello, ${session?.user?.name.split(' ')[0]}`} />
      {d.scoped && <div className="alert alert-info py-2"><i className="bi bi-building" /> You work at <strong>{d.scoped}</strong> — the figures below are for this warehouse.</div>}
      <div className="row g-3 mb-3">
        {!d.scoped && <>
          <Kpi label="Sales" value={money(d.salesNow)} sub={trend(sc)} />
          <Kpi label="Purchases" value={money(d.buyNow)} sub={trend(bc)} />
          <Kpi label="To receive from customers" value={money(d.receivable)} tone={d.receivable > 0 ? 'text-warning' : ''} href={'/sales/invoices?status=unpaid'} />
          <Kpi label="To pay suppliers" value={money(d.payable)} tone={d.payable > 0 ? 'text-danger' : ''} href={'/purchase/bills?status=unpaid'} />
        </>}
        <Kpi label="Stock value" value={money(d.stockValue)} sub={`${d.rolls} ${term('batch', true).toLowerCase()} in stock`} />
        {!d.scoped && <Kpi label="Low stock" value={String(d.lowCount)} tone={d.lowCount > 0 ? 'text-danger' : ''} href={'/stock?low=1'} />}
        <Kpi label="Open orders" value={String(d.ordersOpen)} href={'/sales/orders'} />
        <Kpi label="Transfers on the road" value={String(d.transitOpen ?? 0)} sub={d.transitLate ? `${d.transitLate} overdue` : 'none overdue'} tone={d.transitLate ? 'text-danger' : ''} href={'/stock/transfers?status=open'} />
        {!d.scoped && <Kpi label="Team" value={`${d.userCount} users`} sub={`${d.roleCount} roles`} />}
      </div>
      {!d.scoped && (
      <div className="row g-3">
        <div className="col-lg-6"><div className="card h-100"><div className="card-header">Top selling {term('item', true).toLowerCase()}</div>
          <table className="table table-sm mb-0"><tbody>
            {(d.topItems ?? []).map((t: any, i: number) => <tr key={i}><td>{t.name}</td><td className="text-end">{money(t.value)}</td></tr>)}
            {!(d.topItems ?? []).length && <tr><td className="text-muted">No sales in this period.</td></tr>}</tbody></table></div></div>
        <div className="col-lg-6"><div className="card h-100"><div className="card-header">Overdue invoices</div>
          <table className="table table-sm mb-0"><tbody>
            {(d.overdue ?? []).map((o: any) => <tr key={o.id}><td><Link to={`/sales/invoices/${o.id}`}>{o.invoice_no}</Link> <span className="text-muted small">{o.customer}</span></td><td className="text-end text-danger">{money(o.due)}</td><td className="text-end small text-muted">{o.od} days late</td></tr>)}
            {!(d.overdue ?? []).length && <tr><td className="text-muted">Nothing overdue.</td></tr>}</tbody></table></div></div>
        <div className="col-12"><div className="card"><div className="card-header d-flex justify-content-between"><span>Low stock</span><Link className="small" to="/stock?low=1">See all</Link></div>
          <table className="table table-sm mb-0"><tbody>
            {(d.low ?? []).slice(0, 8).map((l: any, i: number) => <tr key={i}><td><Link to={`/items/${l.id}`}>{l.name}</Link></td><td className="text-end">{qty(l.stock)} {l.unit}</td><td className="text-end text-muted small">reorder at {qty(l.reorder_level)}</td></tr>)}
            {!(d.low ?? []).length && <tr><td className="text-muted">Everything is above its reorder level.</td></tr>}</tbody></table></div></div>
      </div>
      )}
    </>
  );
}

/** Opens a link from server-drawn HTML inside the app when the page exists here, else as a normal page. */
function useLinkGuard() {
  const nav = useNavigate();
  return (e: MouseEvent<HTMLElement>) => {
    const a = (e.target as HTMLElement).closest('a');
    if (!a || a.target || e.ctrlKey || e.metaKey) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#')) return;
    const path = localPath(href);
    if (isReact(path.split('?')[0])) { e.preventDefault(); nav(path); }
  };
}

type Layout = { order: string[]; hidden: string[] };

function Customize({ layout, names, onClose, onSaved }: { layout: Layout; names: Record<string, string>; onClose: () => void; onSaved: () => void }) {
  const [order, setOrder] = useState<string[]>(layout.order);
  const [shown, setShown] = useState<Set<string>>(new Set(layout.order.filter((k) => !layout.hidden.includes(k))));
  const [busy, setBusy] = useState(false);
  const mv = (i: number, d: number) => setOrder((o) => { const a = [...o]; const j = i + d; if (j < 0 || j >= a.length) return a; [a[i], a[j]] = [a[j], a[i]]; return a; });
  const save = async (reset = false) => { setBusy(true); await post('/dashboard/layout', reset ? { reset: 1 } : { order, show: [...shown] }); setBusy(false); onSaved(); };
  return (
    <>
      <div className="offcanvas-backdrop fade show" onClick={onClose} />
      <div className="offcanvas offcanvas-end show" style={{ visibility: 'visible' }} role="dialog" aria-label="Customize dashboard">
        <div className="offcanvas-header"><h2 className="h5 mb-0">Customize dashboard</h2><button type="button" className="btn-close" aria-label="Close" onClick={onClose} /></div>
        <div className="offcanvas-body">
          <p className="text-muted small">Show or hide widgets and move them up or down. This only changes <em>your</em> dashboard.</p>
          {order.map((k, i) => (
            <div className="widget-row" key={k}>
              <span className="form-check form-switch m-0"><input className="form-check-input" type="checkbox" id={`w-${k}`} checked={shown.has(k)} onChange={(e) => setShown((s) => { const n = new Set(s); e.target.checked ? n.add(k) : n.delete(k); return n; })} /></span>
              <label className="flex-grow-1 m-0" htmlFor={`w-${k}`}>{names[k] ?? k}</label>
              <button type="button" className="icon-btn" aria-label="Move up" onClick={() => mv(i, -1)}><i className="bi bi-arrow-up" /></button>
              <button type="button" className="icon-btn" aria-label="Move down" onClick={() => mv(i, 1)}><i className="bi bi-arrow-down" /></button>
            </div>))}
        </div>
        <div className="p-3 border-top d-flex gap-2"><button className="btn btn-primary flex-grow-1" disabled={busy} onClick={() => save()}>Save</button><button className="btn btn-outline-secondary" disabled={busy} onClick={() => save(true)}>Reset</button></div>
      </div>
    </>
  );
}

/** The dashboard: period, getting-started guide and the widgets (charts included), as the classic page draws them. */
export default function Dashboard() {
  const [sp] = useSearchParams(); const qc = useQueryClient(); const guard = useLinkGuard();
  const [custom, setCustom] = useState(false);
  const range = sp.get('range') ?? '';
  const q = useQuery({ queryKey: ['dashboard', range], queryFn: async () => (await get(`/dashboard${range ? `?range=${encodeURIComponent(range)}` : ''}`)).data });
  if (q.isLoading) return <Spinner />;
  if (q.error || !q.data) return <div className="alert alert-danger">Could not load the dashboard.</div>;
  const d = q.data;
  if (d.scoped) return <ScopedDashboard />;
  const layout: Layout = d.layout; const setup: [string, boolean, string][] | null = d.setup;
  const refresh = () => { setCustom(false); qc.invalidateQueries({ queryKey: ['dashboard'] }); };
  const dismiss = async () => { await post('/dashboard/setup/dismiss'); refresh(); };
  const done = setup ? setup.filter((x) => x[1]).length : 0;
  const visible = layout.order.filter((k) => !layout.hidden.includes(k) && (k === 'setup' ? !!setup : !!d.html[k]));
  return (
    <div onClick={guard}>
      <PageHead title="Dashboard" />
      <div className="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div className="btn-group btn-group-sm" role="group" aria-label="Period">
          {Object.entries(d.ranges as Record<string, string>).map(([k, l]) => <Link key={k} className={`btn btn-outline-secondary ${d.p.key === k ? 'active' : ''}`} to={`/dashboard?range=${k}`}>{l}</Link>)}
        </div>
        <span className="text-muted small">{fdate(d.p.from)} – {fdate(d.p.to)} · compared with the {d.p.days} days before</span>
        <button type="button" className="btn btn-sm btn-outline-secondary ms-auto" onClick={() => setCustom(true)}><i className="bi bi-layout-text-window-reverse me-1" />Customize</button>
      </div>
      <div className="dash-grid">
        {visible.map((k) => k === 'setup' && setup ? (
          <section className="dash-w span-12" key={k}><div className="card setup-card"><div className="card-body">
            <div className="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div><h2 className="h6 mb-1">Getting started <span className="text-muted fw-normal">· {done} of {setup.length} done</span></h2>
                <div className="progress mb-2" style={{ height: 6, width: 260 }}><div className="progress-bar" style={{ width: `${Math.round((done / setup.length) * 100)}%` }} /></div></div>
              <button className="btn btn-sm btn-link text-muted" onClick={dismiss}>Hide this guide</button>
            </div>
            <div className="row g-2 mt-1">{setup.map(([label, isDone, href]) => (
              <div className="col-md-6 col-xl-4" key={label}><a className={`setup-step ${isDone ? 'done' : ''}`} href={href}><i className={`bi bi-${isDone ? 'check-circle-fill' : 'circle'}`} /><span>{label}</span><i className="bi bi-chevron-right ms-auto small" /></a></div>))}</div>
          </div></div></section>
        ) : <section className={`dash-w span-${d.spans[k] ?? 12}`} key={k} data-widget={k} dangerouslySetInnerHTML={{ __html: d.html[k] }} />)}
        {!visible.length && <div className="empty-state span-12"><i className="bi bi-layout-text-window-reverse" />Everything is hidden. Use <strong>Customize</strong> to add widgets back.</div>}
      </div>
      {custom && <Customize layout={layout} names={d.widgetNames} onClose={() => setCustom(false)} onSaved={refresh} />}
      <div className="text-muted small mt-4">Plan <strong>{d.u.plan_name}</strong> · Users {d.userCount}/{Number(d.u.max_users) === 0 ? 'Unlimited' : d.u.max_users} · Roles {d.roleCount}</div>
    </div>
  );
}
