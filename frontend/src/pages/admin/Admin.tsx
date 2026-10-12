import { useEffect, useState, type FormEvent } from 'react';
import { Link, Navigate, NavLink, Outlet, useLocation, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, getCsrf, legacyUrl, post, setCsrf, type Msg } from '../../lib/api';
import { fdate } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';

type AdminSession = { authenticated: boolean; csrf: string; admin?: { name: string; email: string }; pending?: number };
const useAdmin = () => useQuery({ queryKey: ['admin-session'], gcTime: 0, queryFn: async () => { const r = (await get('/admin/session')) as unknown as AdminSession; setCsrf(r.csrf); return r; } });
const stamp = (s?: string | null) => (s ? `${fdate(String(s).slice(0, 10))} ${String(s).slice(11, 16)}` : '—');

const NAV: [string, string, string][] = [['/admin', 'speedometer2', 'Dashboard'], ['/admin/tenants', 'buildings', 'Companies'], ['/admin/plans', 'layers', 'Plans'], ['/admin/system', 'gear', 'System'], ['/admin/audit', 'journal-text', 'Audit log']];

function setMode(m: string) {
  try { localStorage.setItem('inv-mode', m); } catch { /* private mode */ }
  document.documentElement.setAttribute('data-bs-theme', m); document.documentElement.setAttribute('data-theme', m);
}

/** The platform owner's sign-in. */
export function AdminLogin() {
  const nav = useNavigate(); const qc = useQueryClient(); const a = useAdmin();
  const [email, setEmail] = useState(''); const [password, setPassword] = useState(''); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (a.isLoading) return <Spinner />;
  if (a.data?.authenticated) return <Navigate to="/admin" replace />;
  const submit = async (e: FormEvent) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    const r = await post('/admin/login', { email, password });
    setBusy(false);
    if (r.ok) { await qc.invalidateQueries({ queryKey: ['admin-session'] }); nav('/admin', { replace: true }); } else setMsgs(r.messages ?? []);
  };
  return (
    <div className="auth-split">
      <section className="auth-hero"><div className="auth-logo"><span className="sb-mark"><i className="bi bi-shield-check" /></span>Super Admin</div>
        <div><h2>Companies, plans and the health of the platform.</h2></div><div className="small opacity-75">&copy; {new Date().getFullYear()} Inventory</div></section>
      <section className="auth-pane"><div className="auth-card"><div className="card"><div className="card-body p-4">
        <Messages items={msgs} />
        <h2 className="h5 mb-3">Super Admin sign in</h2>
        <form onSubmit={submit}>
          <div className="mb-3"><label className="form-label">Email</label><input type="email" className="form-control" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus /></div>
          <div className="mb-3"><label className="form-label">Password</label><input type="password" className="form-control" value={password} onChange={(e) => setPassword(e.target.value)} required /></div>
          <button className="btn btn-dark w-100" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
          <div className="text-center mt-3"><a href={legacyUrl('/admin/forgot-password')}>Forgot password?</a></div>
        </form>
      </div></div></div></section>
    </div>
  );
}

/** Sidebar + top bar of the Super Admin area. */
export function AdminLayout() {
  const a = useAdmin(); const loc = useLocation(); const nav = useNavigate(); const qc = useQueryClient();
  const [mobile, setMobile] = useState(false); const [collapsed, setCollapsed] = useState(document.documentElement.classList.contains('sb-collapsed'));
  useEffect(() => { document.body.className = 'app sbs-dark'; document.getElementById('brand-vars')!.textContent = ''; }, []);
  if (a.isLoading) return <Spinner />;
  if (!a.data?.authenticated) return <Navigate to="/admin/login" replace />;
  const path = loc.pathname.replace(/\/+$/, '') || '/';
  const active = (p: string) => (p === '/admin' ? path === '/admin' : path.startsWith(p));
  const title = NAV.find(([p]) => p === path)?.[2] ?? '';   // forms bring their own heading
  const logout = async () => { await post('/admin/logout'); await qc.invalidateQueries({ queryKey: ['admin-session'] }); nav('/admin/login'); };
  const toggleCollapse = () => { const v = !collapsed; setCollapsed(v); document.documentElement.classList.toggle('sb-collapsed', v); };
  return (
    <>
      <aside className={`sb ${mobile ? 'open' : ''}`} id="sidebar">
        <Link className="sb-brand" to="/admin"><span className="sb-mark"><i className="bi bi-shield-check" /></span><span className="sb-name">Super Admin</span></Link>
        <nav className="sb-nav">
          {NAV.map(([p, icon, label]) => (
            <NavLink key={p} to={p} end={p === '/admin'} className={`sb-link ${active(p) ? 'active' : ''}`} title={label} onClick={() => setMobile(false)}><i className={`bi bi-${icon}`} /><span>{label}</span>
              {p === '/admin/system' && !!a.data!.pending && <span className="badge text-bg-warning ms-auto">{a.data!.pending}</span>}</NavLink>))}
        </nav>
        <div className="sb-foot"><button type="button" className="sb-collapse" onClick={toggleCollapse}><i className="bi bi-chevron-bar-left" /><span>Collapse</span></button></div>
      </aside>
      <div className={`sb-backdrop ${mobile ? 'show' : ''}`} onClick={() => setMobile(false)} />
      <div className="shell">
        <header className="topbar">
          <button type="button" className="icon-btn d-lg-none" aria-label="Open menu" onClick={() => setMobile(true)}><i className="bi bi-list fs-4" /></button>
          <div className="topbar-actions">
            <button type="button" className="icon-btn" aria-label="Switch light / dark" onClick={() => setMode(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark')}><i className="bi bi-moon-stars" /></button>
            <span className="text-muted small d-none d-sm-inline">{a.data.admin?.name}</span>
            <button className="btn btn-sm btn-outline-secondary" onClick={logout}>Sign out</button>
          </div>
        </header>
        <main className="content">{title && <div className="page-head"><div><h1>{title}</h1></div></div>}<Outlet /></main>
      </div>
    </>
  );
}

// ------------------------------------------------------------------ dashboard

export function AdminDashboard() {
  const q = useQuery({ queryKey: ['admin-dashboard'], queryFn: async () => (await get('/admin')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  return (
    <>
      <div className="row g-3 mb-4">
        {[['Companies', d.counts.total], ['Active', d.counts.active], ['On trial', d.counts.trial], ['Suspended', d.counts.suspended], ['Total users', d.users]].map(([l, v]) => (
          <div className="col-6 col-md" key={l as string}><div className="card"><div className="card-body"><div className="text-muted small">{l}</div><div className="fs-3">{Number(v)}</div></div></div></div>))}
      </div>
      <div className="card"><div className="card-header d-flex justify-content-between"><span>Newest companies</span><Link to="/admin/tenants/create">+ New company</Link></div>
        <div className="table-responsive"><table className="table mb-0"><thead><tr><th>Company</th><th>Plan</th><th>Status</th><th>Created</th></tr></thead><tbody>
          {d.recent.map((t: any) => <tr key={t.id}><td>{t.name}</td><td>{t.plan_name}</td><td>{t.status}</td><td>{stamp(t.created_at)}</td></tr>)}
          {!d.recent.length && <tr><td colSpan={4} className="text-muted">No companies yet.</td></tr>}</tbody></table></div></div>
    </>
  );
}

// ------------------------------------------------------------------ companies

const statusTone: Record<string, string> = { active: 'success', trial: 'info', suspended: 'danger' };

export function TenantList() {
  const nav = useNavigate(); const qc = useQueryClient(); const { reload } = useSession(); const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['admin-tenants'], queryFn: async () => (await get('/admin/tenants')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  const status = async (id: number) => { const r = await post(`/admin/tenants/${id}/status`); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['admin-tenants'] }); };
  const open = async (id: number) => {
    const r = await post(`/admin/tenants/${id}/impersonate`); setMsgs(r.messages ?? []);
    if (r.ok) { await reload(); nav('/dashboard'); }
  };
  return (
    <>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="d-flex justify-content-end mb-3"><Link className="btn btn-primary" to="/admin/tenants/create"><i className="bi bi-plus-lg" /> New company</Link></div>
      <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
        <thead><tr><th>Company</th><th>Plan</th><th>Status</th><th>Users</th><th>Ends</th><th /></tr></thead><tbody>
          {d.tenants.map((t: any) => { const end = t.status === 'trial' ? t.trial_ends_at : t.subscription_ends_at; return (
            <tr key={t.id}><td>{t.name}</td><td>{t.plan_name}</td><td><Badge tone={statusTone[t.status]}>{t.status}</Badge></td><td>{t.user_count}</td><td>{end ? fdate(String(end).slice(0, 10)) : '—'}</td>
              <td className="text-end text-nowrap">
                <Link className="btn btn-sm btn-outline-primary" to={`/admin/tenants/${t.id}/edit`}>Edit</Link>{' '}
                <button className="btn btn-sm btn-outline-dark" onClick={() => open(t.id)}>Open as owner</button>{' '}
                <button className={`btn btn-sm btn-outline-${t.status === 'suspended' ? 'success' : 'warning'}`} onClick={() => status(t.id)}>{t.status === 'suspended' ? 'Activate' : 'Suspend'}</button></td></tr>); })}
          {!d.tenants.length && <tr><td colSpan={6} className="text-muted">No companies yet.</td></tr>}
        </tbody></table></div></div>
    </>
  );
}

export function TenantForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['admin-tenant-form', id ?? 'new'], gcTime: 0, queryFn: async () => (await get(id ? `/admin/tenants/${id}/edit` : '/admin/tenants/create')).data });
  const [v, setV] = useState<Record<string, string> | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!src.data || v) return; const t = src.data.tenant ?? {};
    setV({ name: t.name ?? '', plan_id: String(t.plan_id ?? src.data.plans?.[0]?.id ?? ''), status: t.status ?? 'trial', trial_ends_at: t.trial_ends_at ?? '', subscription_ends_at: t.subscription_ends_at ?? '', owner_name: '', owner_email: '', owner_password: '' });
  }, [src.data, v]);
  if (src.isLoading || !v) return <Spinner />;
  const d = src.data; const set = (k: string, val: string) => setV({ ...v, [k]: val });
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(id ? `/admin/tenants/${id}` : '/admin/tenants', v);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['admin-tenants'] }); qc.invalidateQueries({ queryKey: ['admin-dashboard'] }); nav('/admin/tenants');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? 'Edit company' : 'New company'}><button type="button" className="btn btn-outline-secondary" onClick={() => nav('/admin/tenants')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></PageHead>
      <Messages items={msgs} />
      <div className="row"><div className="col-lg-7"><div className="card"><div className="card-body">
        <div className="mb-3"><label className="form-label">Company name</label><input className="form-control" required maxLength={150} value={v.name} onChange={(e) => set('name', e.target.value)} /></div>
        <div className="row">
          <div className="col-md-6 mb-3"><label className="form-label">Plan</label><select className="form-select" required value={v.plan_id} onChange={(e) => set('plan_id', e.target.value)}>{d.plans.map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}</select></div>
          <div className="col-md-6 mb-3"><label className="form-label">Status</label><select className="form-select" value={v.status} onChange={(e) => set('status', e.target.value)}>{['trial', 'active', 'suspended'].map((s) => <option key={s} value={s}>{s[0].toUpperCase() + s.slice(1)}</option>)}</select></div>
        </div>
        <div className="row">
          {id && <div className="col-md-6 mb-3"><label className="form-label">Trial ends</label><input type="date" className="form-control" value={v.trial_ends_at} onChange={(e) => set('trial_ends_at', e.target.value)} /></div>}
          <div className="col-md-6 mb-3"><label className="form-label">Subscription ends</label><input type="date" className="form-control" value={v.subscription_ends_at} onChange={(e) => set('subscription_ends_at', e.target.value)} /></div>
        </div>
        {!id && <><hr /><h2 className="h6">Owner account</h2>
          <div className="mb-3"><label className="form-label">Owner name</label><input className="form-control" required value={v.owner_name} onChange={(e) => set('owner_name', e.target.value)} /></div>
          <div className="mb-3"><label className="form-label">Owner email</label><input type="email" className="form-control" required value={v.owner_email} onChange={(e) => set('owner_email', e.target.value)} /></div>
          <div className="mb-1"><label className="form-label">Temporary password</label><input className="form-control" minLength={8} required value={v.owner_password} onChange={(e) => set('owner_password', e.target.value)} /></div></>}
      </div></div></div>
      {id && d.users?.length > 0 && <div className="col-lg-5"><div className="card"><div className="card-header">Users</div><ul className="list-group list-group-flush">
        {d.users.map((u: any, i: number) => <li className="list-group-item d-flex justify-content-between" key={i}><span>{u.name}<br /><small className="text-muted">{u.email}</small></span><span><Badge>{u.role_name}</Badge> {Number(u.is_active) ? '' : <Badge tone="danger">off</Badge>}</span></li>)}</ul></div></div>}
      </div>
    </form>
  );
}

// ------------------------------------------------------------------ plans

export function PlanList() {
  const q = useQuery({ queryKey: ['admin-plans'], queryFn: async () => (await get('/admin/plans')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  const lim = (n: any) => (Number(n) === 0 ? '∞' : n);
  return (
    <>
      <div className="d-flex justify-content-end mb-3"><Link className="btn btn-primary" to="/admin/plans/create"><i className="bi bi-plus-lg" /> New plan</Link></div>
      <div className="card"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>Plan</th><th>Users</th><th>Items</th><th>Warehouses</th><th>Price</th><th>Trial days</th><th>Companies</th><th>Active</th><th /></tr></thead><tbody>
          {d.plans.map((p: any) => <tr key={p.id}><td>{p.name}</td><td>{lim(p.max_users)}</td><td>{lim(p.max_items)}</td><td>{lim(p.max_warehouses)}</td><td>{Number(p.price).toFixed(2)}</td><td>{p.trial_days}</td><td>{p.tenant_count}</td><td>{Number(p.is_active) ? 'Yes' : 'No'}</td>
            <td className="text-end"><Link className="btn btn-sm btn-outline-primary" to={`/admin/plans/${p.id}/edit`}>Edit</Link></td></tr>)}
        </tbody></table></div></div>
      <p className="text-muted small mt-2">0 means unlimited.</p>
    </>
  );
}

export function PlanForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['admin-plan-form', id ?? 'new'], gcTime: 0, queryFn: async () => (await get(id ? `/admin/plans/${id}/edit` : '/admin/plans/create')).data });
  const [v, setV] = useState<Record<string, string> | null>(null); const [active, setActive] = useState(true); const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!src.data || v) return; const p = src.data.plan ?? {};
    setV({ name: p.name ?? '', max_users: String(p.max_users ?? 0), max_items: String(p.max_items ?? 0), max_warehouses: String(p.max_warehouses ?? 0), price: String(p.price ?? 0), trial_days: String(p.trial_days ?? 0) });
    setActive(p.is_active === undefined ? true : !!Number(p.is_active));
  }, [src.data, v]);
  if (src.isLoading || !v) return <Spinner />;
  const set = (k: string, val: string) => setV({ ...v, [k]: val });
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(id ? `/admin/plans/${id}` : '/admin/plans', { ...v, is_active: active ? 1 : '' });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    qc.invalidateQueries({ queryKey: ['admin-plans'] }); nav('/admin/plans');
  };
  const num = (k: string, label: string, col: string, extra: any = {}) => <div className={`${col} mb-3`}><label className="form-label">{label}</label><input type="number" min={0} required className="form-control" value={v[k]} onChange={(e) => set(k, e.target.value)} {...extra} /></div>;
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? 'Edit plan' : 'New plan'}><button type="button" className="btn btn-outline-secondary" onClick={() => nav('/admin/plans')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 560 }}><div className="card-body">
        <div className="mb-3"><label className="form-label">Plan name</label><input className="form-control" required maxLength={80} value={v.name} onChange={(e) => set('name', e.target.value)} /></div>
        <div className="row">{num('max_users', 'Max users', 'col-md-4')}{num('max_items', 'Max items', 'col-md-4')}{num('max_warehouses', 'Max warehouses', 'col-md-4')}</div>
        <div className="row">{num('price', 'Price', 'col-md-6', { step: '0.01' })}{num('trial_days', 'Trial days', 'col-md-6')}</div>
        <div className="form-check mb-3"><input className="form-check-input" type="checkbox" id="act" checked={active} onChange={(e) => setActive(e.target.checked)} /><label className="form-check-label" htmlFor="act">Active (can be assigned to new companies)</label></div>
        <p className="text-muted small mb-0">0 = unlimited.</p>
      </div></div>
    </form>
  );
}

// ------------------------------------------------------------------ system + audit

export function AdminSystem() {
  const qc = useQueryClient(); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const q = useQuery({ queryKey: ['admin-system'], queryFn: async () => (await get('/admin/system')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  const migrate = async () => { setBusy(true); const r = await post('/admin/system/migrate'); setBusy(false); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['admin-system'] }); qc.invalidateQueries({ queryKey: ['admin-session'] }); };
  return (
    <>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      {d.pending.length ? (
        <div className="alert alert-warning d-flex justify-content-between align-items-center"><div><strong>{d.pending.length} database update(s) waiting:</strong> {d.pending.join(', ')}<div className="small">Run this after uploading a new version of the app.</div></div>
          <button className="btn btn-warning" disabled={busy} onClick={migrate}>{busy ? 'Running…' : 'Run updates'}</button></div>
      ) : <div className="alert alert-success">The database is up to date.</div>}
      <div className="card mb-3"><div className="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><strong>Backup</strong><div className="small text-muted">Downloads every company's data as one .sql file. Do this before each update and keep copies away from the server. To restore: cPanel → phpMyAdmin → Import into an empty database. Uploaded logos live in <code>storage/uploads</code>; copy that folder too.</div></div>
        {/* a real form post: the answer is a file download, not JSON */}
        <form method="post" action={legacyUrl('/admin/system/backup')}><input type="hidden" name="_csrf" value={getCsrf()} /><button className="btn btn-primary"><i className="bi bi-download me-1" />Download backup</button></form>
      </div></div>
      <div className="row g-3">
        <div className="col-md-5"><div className="card"><div className="card-header">Environment</div><ul className="list-group list-group-flush">
          {Object.entries(d.info as Record<string, string>).map(([k, val]) => <li className="list-group-item d-flex justify-content-between" key={k}><span>{k}</span><span className="text-muted">{val}</span></li>)}</ul></div></div>
        <div className="col-md-7"><div className="card"><div className="card-header">Applied updates</div><table className="table table-sm mb-0"><tbody>
          {d.applied.map((a: any) => <tr key={a.name}><td>{a.name}</td><td className="text-muted">{stamp(a.applied_at)}</td></tr>)}</tbody></table></div></div>
      </div>
    </>
  );
}

export function AdminAudit() {
  const q = useQuery({ queryKey: ['admin-audit'], queryFn: async () => (await get('/admin/audit')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  return (
    <div className="card"><div className="table-responsive"><table className="table table-sm mb-0">
      <thead><tr><th>When</th><th>Company</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
        {d.logs.map((l: any) => <tr key={l.id}><td className="text-nowrap">{stamp(l.created_at)}</td><td>{l.tenant_name ?? '—'}</td><td>{l.actor_type}{l.actor_id ? ` #${l.actor_id}` : ''}</td><td>{l.action}</td><td>{l.details ?? ''}</td><td>{l.ip}</td></tr>)}
        {!d.logs.length && <tr><td colSpan={6} className="text-muted">Nothing recorded yet.</td></tr>}</tbody></table></div></div>
  );
}
