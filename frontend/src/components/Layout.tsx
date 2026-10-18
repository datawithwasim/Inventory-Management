import { useEffect, useState, type ReactNode } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { legacyUrl, post } from '../lib/api';
import { fdate } from '../lib/format';
import { isReact, isScreen, localPath } from '../lib/routes';
import { useSession, type MenuItem } from '../lib/session';
import { useQueryClient } from '@tanstack/react-query';
import { Dropdown } from './ui';
import Palette from './Palette';
import ErrorBoundary from './ErrorBoundary';

/** A menu link: React route when the screen has been rebuilt, otherwise the PHP page. */
export function AppLink({ item, className, children, onClick }: { item: { path: string; href?: string }; className?: string; children: ReactNode; onClick?: () => void }) {
  if (isReact(item.path)) return <NavLink to={item.href ?? item.path} end={item.path === '/stock'} className={({ isActive }) => `${className ?? ''} ${isActive ? 'active' : ''}`} onClick={onClick}>{children}</NavLink>;
  return <a className={className} href={legacyUrl(item.href ?? item.path)} onClick={onClick}>{children}</a>;
}

function setMode(m: string) {
  try { localStorage.setItem('inv-mode', m); } catch { /* private mode */ }
  const eff = m === 'auto' ? (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : m;
  document.documentElement.setAttribute('data-bs-theme', eff); document.documentElement.setAttribute('data-theme', eff);
}
const currentMode = () => { try { return localStorage.getItem('inv-mode') || 'light'; } catch { return 'light'; } };

function bestMatch(path: string, session: NonNullable<ReturnType<typeof useSession>['session']>): [string | null, MenuItem | null] {
  let best: [string | null, MenuItem | null] = [null, null]; let len = -1;
  const test = (it: MenuItem, group: string) => {
    const hit = it.exact ? path === it.path : path === it.path || path.startsWith(it.path + '/');
    if (hit && it.path.length > len) { best = [group, it]; len = it.path.length; }
  };
  session.menu?.account.forEach((it) => test(it, 'Administration'));
  Object.values(session.menu?.groups ?? {}).forEach((g) => g.items.forEach((it) => test(it, g.label)));
  return best;
}

export default function Layout() {
  const { session, reload } = useSession(); const qc = useQueryClient();
  const loc = useLocation(); const nav = useNavigate();
  const [mode, setModeState] = useState(currentMode());
  const [collapsed, setCollapsed] = useState(document.documentElement.classList.contains('sb-collapsed'));
  const [mobile, setMobile] = useState(false);
  // one group open at a time (like an accordion); until one is clicked, the group of the current page is the open one
  const [openKey, setOpenKey] = useState<string | null>(null);
  useEffect(() => { setOpenKey(null); }, [loc.pathname]);
  const [denied, setDenied] = useState<number | null>(null);
  const [searching, setSearching] = useState(false);
  useEffect(() => { const k = (e: KeyboardEvent) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setSearching(true); } }; document.addEventListener('keydown', k); return () => document.removeEventListener('keydown', k); }, []);
  useEffect(() => { const on = (e: Event) => setDenied((e as CustomEvent).detail); window.addEventListener('inv-denied', on); return () => window.removeEventListener('inv-denied', on); }, []);
  useEffect(() => { setDenied(null); }, [loc.pathname]);
  // a plain link to one of the app's own screens (from server-drawn blocks, older links) opens inside the app, not as a new page load
  useEffect(() => {
    const click = (e: MouseEvent) => {
      if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      const a = (e.target as HTMLElement).closest?.('a');
      if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) return;
      const href = a.getAttribute('href');
      if (!href || href.startsWith('#') || (/^[a-z][a-z0-9+.-]*:/i.test(href) && !href.startsWith(window.location.origin))) return;
      const path = localPath(href);
      const [clean, search = ''] = path.split('#')[0].split('?');
      if (isScreen(clean, '?' + search)) { e.preventDefault(); nav(path); }
    };
    document.addEventListener('click', click);
    return () => document.removeEventListener('click', click);
  }, [nav]);
  // the tab title the classic pages had: page · company
  useEffect(() => {
    if (!session?.tenant || !session.menu) return;
    const p = loc.pathname.replace(/\/+$/, '') || '/dashboard';
    const item = bestMatch(p, session)[1];
    document.title = `${item ? item.label : 'Dashboard'} · ${session.tenant.name}`;
  }, [loc.pathname, session]);
  if (!session?.authenticated || !session.menu || !session.user || !session.tenant) return null;
  const { menu, user, tenant } = session;
  const path = loc.pathname.replace(/\/+$/, '') || '/';
  const [crumbGroup, crumbItem] = bestMatch(path, session);
  const initial = user.name.slice(0, 1).toUpperCase();
  const toggleCollapse = () => { const v = !collapsed; setCollapsed(v); document.documentElement.classList.toggle('sb-collapsed', v); try { localStorage.setItem('inv-sb', v ? '1' : '0'); } catch { /* ignore */ } };
  const logout = async () => {
    try { await post('/logout'); } catch { /* the session may already be gone: signing out is still the right end state */ }
    qc.clear();   // nothing of this company stays in memory for the next person on this browser
    await reload(); nav('/login', { replace: true });
  };
  const chooseMode = (m: string) => { setMode(m); setModeState(m); };
  const stopSupport = async () => { await post('/impersonate/stop'); nav('/admin/tenants'); await reload(); };

  return (
    <>
      <aside className={`sb ${mobile ? 'open' : ''}`} id="sidebar" aria-label="Main menu">
        <Link className="sb-brand" to="/dashboard" onClick={() => setMobile(false)}>
          {tenant.logo ? <img src={legacyUrl('/company/logo')} alt="" className="sb-logo" /> : <span className="sb-mark">{tenant.name.slice(0, 1).toUpperCase()}</span>}
          <span className="sb-name">{tenant.name}</span>
        </Link>
        <nav className="sb-nav">
          <AppLink item={menu.dashboard} className="sb-link" onClick={() => setMobile(false)}><i className={`bi bi-${menu.dashboard.icon}`} /><span>Dashboard</span></AppLink>
          {Object.entries(menu.groups).map(([key, g]) => {
            const active = g.items.some((it) => crumbItem === it);
            const open = openKey === null ? active : openKey === key;
            return (
              <div key={key} className={`sb-group ${open ? 'open' : ''}`} data-group={key}>
                <button type="button" className="sb-group-head" aria-expanded={open} title={g.label} onClick={() => setOpenKey(open ? '' : key)}>
                  <i className={`bi bi-${g.icon} sb-gicon`} /><span>{g.label}</span><i className="bi bi-chevron-down sb-caret" />
                </button>
                <div className="sb-items"><div className="sb-items-inner">
                  {g.items.map((it) => <AppLink key={it.path} item={it} className={`sb-link ${crumbItem === it ? 'active' : ''}`} onClick={() => setMobile(false)}><i className={`bi bi-${it.icon}`} /><span>{it.label}</span></AppLink>)}
                </div></div>
              </div>
            );
          })}
        </nav>
        <div className="sb-foot">
          <div className="sb-plan"><span className="badge rounded-pill">{tenant.plan}</span><span className="small">{tenant.status === 'trial' ? 'Trial' : 'Valid'}{tenant.expires ? ' until ' + fdate(tenant.expires) : ''}</span></div>
          <button type="button" className="sb-collapse" title="Collapse menu" onClick={toggleCollapse}><i className="bi bi-chevron-bar-left" /><span>Collapse</span></button>
        </div>
      </aside>
      <div className="sb-backdrop" style={mobile ? { display: 'block' } : undefined} onClick={() => setMobile(false)} />

      <div className="shell">
        {session.supportMode && <div className="support-bar d-flex justify-content-between align-items-center"><span><i className="bi bi-eye me-1" />Super Admin support mode: you are viewing <strong>{tenant.name}</strong>.</span><button className="btn btn-sm btn-dark" onClick={stopSupport}>Back to admin</button></div>}
        <header className="topbar">
          <button type="button" className="icon-btn d-lg-none" aria-label="Open menu" onClick={() => setMobile(true)}><i className="bi bi-list fs-4" /></button>
          <nav className="crumbs" aria-label="Breadcrumb">
            <Link to="/dashboard">Home</Link>
            {crumbItem && path !== '/dashboard' && <><i className="bi bi-chevron-right" /><span>{crumbGroup}</span><i className="bi bi-chevron-right" /><AppLink item={crumbItem}>{crumbItem.label}</AppLink></>}
          </nav>
          <div className="topbar-actions">
            <button type="button" className="icon-btn" aria-label="Search" title="Search (Ctrl K)" onClick={() => setSearching(true)}><i className="bi bi-search" /></button>
            {!!session.quick?.length && (
              <Dropdown button={() => <button className="btn btn-primary btn-sm d-flex align-items-center gap-1"><i className="bi bi-plus-lg" /><span className="d-none d-sm-inline">New</span></button>}>
                {(close) => <ul className="list-unstyled m-0">{session.quick!.map((q) => <li key={q.path}><AppLink item={q} className="dropdown-item" onClick={close}><i className={`bi bi-${q.icon} me-2 text-muted`} />{q.label}</AppLink></li>)}</ul>}
              </Dropdown>
            )}
            <Dropdown button={() => (
              <button className="user-btn" aria-label="Account menu"><span className="avatar">{initial}</span>
                <span className="d-none d-md-block text-start lh-sm"><span className="d-block fw-semibold small">{user.name}</span><span className="d-block text-muted" style={{ fontSize: '.72rem' }}>{user.role}{user.warehouse ? ` · ${user.warehouse.name}` : ''}</span></span><i className="bi bi-chevron-down small d-none d-md-block" /></button>
            )} menuClass="pm">
              {(close) => <>
                <div className="pm-head"><span className="avatar avatar-lg">{initial}</span><div className="pm-who"><div className="pm-name">{user.name}</div><div className="pm-mail">{user.email}</div><div className="pm-role">{user.role} · {user.warehouse ? user.warehouse.name : tenant.name}</div></div></div>
                <div className="pm-list">{menu.account.map((it) => <AppLink key={it.path} item={it} className="pm-item" onClick={close}><i className={`bi bi-${it.icon}`} /><span>{it.label}</span></AppLink>)}</div>
                <div className="pm-theme"><span><i className="bi bi-circle-half" /> Theme</span>
                  <div className="pm-seg" role="group" aria-label="Theme">
                    <button type="button" className={mode === 'light' ? 'on' : ''} onClick={() => chooseMode('light')}><i className="bi bi-sun" /> Light</button>
                    <button type="button" className={mode === 'dark' ? 'on' : ''} onClick={() => chooseMode('dark')}><i className="bi bi-moon-stars" /> Dark</button>
                    <button type="button" className={mode === 'auto' ? 'on' : ''} onClick={() => chooseMode('auto')} title="Follow the device setting">Auto</button>
                  </div></div>
                <div className="pm-foot"><button className="pm-item pm-out" onClick={logout}><i className="bi bi-box-arrow-right" /><span>Sign out</span></button></div>
              </>}
            </Dropdown>
          </div>
        </header>
        <main className="content" id="main">{denied && <div className="alert alert-warning">{denied === 403 ? 'You do not have permission to open this page. Ask the company owner to change your role.' : 'This page or record was not found. It may have been deleted.'}</div>}<ErrorBoundary resetKey={loc.pathname}><Outlet /></ErrorBoundary></main>
        <Palette open={searching} onClose={() => setSearching(false)} />
      </div>
    </>
  );
}
