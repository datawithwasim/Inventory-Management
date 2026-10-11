import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react';
import { get, setCsrf } from './api';
import { setFormat } from './format';

export type MenuItem = { label: string; icon: string; path: string; href: string; exact?: boolean };
export type Session = {
  authenticated: boolean; csrf: string; base: string;
  user?: { name: string; email: string; role: string; owner: boolean };
  tenant?: { name: string; plan: string; status: string; expires: string | null; logo: string | null };
  perms?: string[]; menu?: { dashboard: MenuItem; groups: Record<string, { label: string; icon: string; items: MenuItem[] }>; account: MenuItem[] };
  fmt?: { decimals: number; grouping: string; symbol: string; position: string; date: string };
  stockTypes?: Record<string, string>; poStatus?: Record<string, [string, string]>; payStatus?: Record<string, [string, string]>; payMethods?: Record<string, string>; quick?: MenuItem[]; terms?: Record<string, [string, string]>; supportMode?: boolean; themeCss?: string;
  theme?: { brand: string; sidebar: string; density: string; mode: string };
};

type Ctx = { session: Session | null; reload: () => Promise<Session>; can: (p: string) => boolean; term: (k: string, plural?: boolean) => string };
const SessionCtx = createContext<Ctx>(null as unknown as Ctx);
export const useSession = () => useContext(SessionCtx);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const reload = useCallback(async () => {
    const r = (await get('/session')) as unknown as Session;
    setCsrf(r.csrf); setFormat(r.fmt); setSession(r);
    return r;
  }, []);
  useEffect(() => { reload().catch(() => setSession({ authenticated: false, csrf: '', base: '' })); }, [reload]);
  useEffect(() => {
    if (!session) return;
    const el = document.getElementById('brand-vars'); if (el) el.textContent = session.themeCss ?? '';
    const b = document.body, t = session.theme;
    b.className = 'app' + (t ? ` sbs-${t.sidebar} density-${t.density}` : '');
  }, [session]);
  const can = (p: string) => !!session?.perms && (session.perms.includes('*') || session.perms.includes(p));
  const term = (k: string, plural = false) => session?.terms?.[k]?.[plural ? 1 : 0] ?? k;
  if (!session) return <div className="d-flex align-items-center justify-content-center" style={{ minHeight: '100vh' }}><div className="spinner-border text-primary" role="status" aria-label="Loading" /></div>;
  return <SessionCtx.Provider value={{ session, reload, can, term }}>{children}</SessionCtx.Provider>;
}
