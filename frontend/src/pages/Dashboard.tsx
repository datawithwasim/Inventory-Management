import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { get, legacyUrl } from '../lib/api';
import { money, qty } from '../lib/format';
import { useSession } from '../lib/session';
import { PageHead, Spinner } from '../components/ui';

const pct = (now: number, prev: number) => (prev > 0 ? ((now - prev) / prev) * 100 : null);

function Kpi({ label, value, sub, tone, href }: { label: string; value: string; sub?: string; tone?: string; href?: string }) {
  const body = <div className="card h-100"><div className="card-body"><div className="text-muted small">{label}</div><div className={`fs-3 fw-semibold ${tone ?? ''}`}>{value}</div>{sub && <div className="small text-muted">{sub}</div>}</div></div>;
  return <div className="col-6 col-lg-3">{href ? <a href={href} className="text-decoration-none text-reset">{body}</a> : body}</div>;
}

export default function Dashboard() {
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
      <div className="row g-3 mb-3">
        <Kpi label="Sales" value={money(d.salesNow)} sub={trend(sc)} />
        <Kpi label="Purchases" value={money(d.buyNow)} sub={trend(bc)} />
        <Kpi label="To receive from customers" value={money(d.receivable)} tone={d.receivable > 0 ? 'text-warning' : ''} href={legacyUrl('/sales/invoices?status=unpaid')} />
        <Kpi label="To pay suppliers" value={money(d.payable)} tone={d.payable > 0 ? 'text-danger' : ''} href={legacyUrl('/purchase/bills?status=unpaid')} />
        <Kpi label="Stock value" value={money(d.stockValue)} sub={`${d.rolls} ${term('batch', true).toLowerCase()} in stock`} />
        <Kpi label="Low stock" value={String(d.lowCount)} tone={d.lowCount > 0 ? 'text-danger' : ''} href={legacyUrl('/stock?low=1')} />
        <Kpi label="Open orders" value={String(d.ordersOpen)} href={legacyUrl('/sales/orders')} />
        <Kpi label="Team" value={`${d.userCount} users`} sub={`${d.roleCount} roles`} />
      </div>
      <div className="row g-3">
        <div className="col-lg-6"><div className="card h-100"><div className="card-header">Top selling {term('item', true).toLowerCase()}</div>
          <table className="table table-sm mb-0"><tbody>
            {(d.topItems ?? []).map((t: any, i: number) => <tr key={i}><td>{t.name}</td><td className="text-end">{money(t.value)}</td></tr>)}
            {!(d.topItems ?? []).length && <tr><td className="text-muted">No sales in this period.</td></tr>}</tbody></table></div></div>
        <div className="col-lg-6"><div className="card h-100"><div className="card-header">Overdue invoices</div>
          <table className="table table-sm mb-0"><tbody>
            {(d.overdue ?? []).map((o: any) => <tr key={o.id}><td><a href={legacyUrl(`/sales/invoices/${o.id}`)}>{o.invoice_no}</a> <span className="text-muted small">{o.customer}</span></td><td className="text-end text-danger">{money(o.due)}</td><td className="text-end small text-muted">{o.od} days late</td></tr>)}
            {!(d.overdue ?? []).length && <tr><td className="text-muted">Nothing overdue.</td></tr>}</tbody></table></div></div>
        <div className="col-12"><div className="card"><div className="card-header d-flex justify-content-between"><span>Low stock</span><Link className="small" to="/dashboard" onClick={(e) => { e.preventDefault(); window.location.href = legacyUrl('/stock?low=1'); }}>See all</Link></div>
          <table className="table table-sm mb-0"><tbody>
            {(d.low ?? []).slice(0, 8).map((l: any, i: number) => <tr key={i}><td><a href={legacyUrl(`/items/${l.id}`)}>{l.name}</a></td><td className="text-end">{qty(l.stock)} {l.unit}</td><td className="text-end text-muted small">reorder at {qty(l.reorder_level)}</td></tr>)}
            {!(d.low ?? []).length && <tr><td className="text-muted">Everything is above its reorder level.</td></tr>}</tbody></table></div></div>
      </div>
    </>
  );
}
