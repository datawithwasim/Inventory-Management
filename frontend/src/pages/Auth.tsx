import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { get, post, type Msg } from '../lib/api';
import { Messages, Spinner } from '../components/ui';

const appName = (document.querySelector('meta[name="app-title"]') as HTMLMetaElement | null)?.content || 'Inventory';

/** The split sign-in frame (brand panel + card), shared by the forgot / reset pages. */
function Frame({ admin, title, children }: { admin?: boolean; title: string; children: ReactNode }) {
  return (
    <div className="auth-split">
      <section className="auth-hero">
        <div className="auth-logo"><span className="sb-mark"><i className={`bi bi-${admin ? 'shield-check' : 'box-seam'}`} /></span>{admin ? 'Super Admin' : appName}</div>
        {admin ? <div><h2>Companies, plans and the health of the platform.</h2></div>
          : <div><h2>Know exactly what you have, where it is, and what is selling.</h2>
            <ul><li><i className="bi bi-check-circle-fill" />Roll-by-roll fabric tracking with rack locations</li><li><i className="bi bi-check-circle-fill" />Purchase, sales, returns and stock by roll and rack</li><li><i className="bi bi-check-circle-fill" />Reports, barcode labels and your own settings</li></ul></div>}
        <div className="small opacity-75">&copy; {new Date().getFullYear()} {appName}</div>
      </section>
      <section className="auth-pane"><div className="auth-card"><div className="card"><div className="card-body p-4"><h2 className="h5 mb-3">{title}</h2>{children}</div></div></div></section>
    </div>
  );
}

/** "Forgot password": the address gets a link by mail (the same answer whether or not it is known). */
export function ForgotPassword({ admin = false }: { admin?: boolean }) {
  const [email, setEmail] = useState(''); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false); const [sent, setSent] = useState(false);
  const back = admin ? '/admin/login' : '/login';
  const submit = async (e: FormEvent) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    try { const r = await post(admin ? '/admin/forgot-password' : '/forgot-password', { email }); setMsgs(r.messages ?? []); setSent(r.ok); } catch (err: any) { setMsgs([{ type: 'danger', msg: err.message }]); }
    setBusy(false);
  };
  return (
    <Frame admin={admin} title={admin ? 'Super Admin: forgot password' : 'Forgot password'}>
      <Messages items={msgs} />
      {!sent && <form onSubmit={submit}>
        <div className="mb-3"><label className="form-label" htmlFor="fe">{admin ? 'Your Super Admin email' : 'Your email'}</label><input id="fe" type="email" className="form-control" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus /></div>
        <button className={`btn ${admin ? 'btn-dark' : 'btn-primary'} w-100`} disabled={busy}>{busy ? 'Sending…' : 'Send reset link'}</button>
      </form>}
      <div className="text-center mt-3"><Link to={back}>Back to sign in</Link></div>
    </Frame>
  );
}

/** The page the mailed link opens: choose a new password. */
export function ResetPassword({ admin = false }: { admin?: boolean }) {
  const { token } = useParams(); const nav = useNavigate();
  const base = admin ? '/admin' : '';
  const [state, setState] = useState<'checking' | 'ok' | 'bad'>('checking');
  const [pw, setPw] = useState(''); const [pw2, setPw2] = useState(''); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  useEffect(() => { get(`${base}/reset-password/${token}`).then((r) => setState(r.ok && r.data ? 'ok' : 'bad')).catch(() => setState('bad')); }, [base, token]);
  if (state === 'checking') return <Spinner />;
  const submit = async (e: FormEvent) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    try {
      const r = await post(`${base}/reset-password/${token}`, { password: pw, password_confirm: pw2 });
      if (r.ok) nav(admin ? '/admin/login' : '/login', { replace: true, state: { notice: 'Password updated. You can sign in now.' } }); else setMsgs(r.messages ?? []);
    } catch (err: any) { setMsgs([{ type: 'danger', msg: err.message }]); }
    setBusy(false);
  };
  if (state === 'bad') return (
    <Frame admin={admin} title="Reset link">
      <div className="alert alert-danger">This reset link is invalid or has expired.</div>
      <Link className={`btn ${admin ? 'btn-dark' : 'btn-primary'} w-100`} to={`${base}/forgot-password`}>Ask for a new link</Link>
    </Frame>
  );
  return (
    <Frame admin={admin} title={admin ? 'Choose a new Super Admin password' : 'Choose a new password'}>
      <Messages items={msgs} />
      <form onSubmit={submit}>
        <div className="mb-3"><label className="form-label" htmlFor="p1">New password</label><input id="p1" type="password" className="form-control" minLength={8} value={pw} onChange={(e) => setPw(e.target.value)} required autoFocus autoComplete="new-password" /></div>
        <div className="mb-3"><label className="form-label" htmlFor="p2">Confirm password</label><input id="p2" type="password" className="form-control" minLength={8} value={pw2} onChange={(e) => setPw2(e.target.value)} required autoComplete="new-password" /></div>
        <button className={`btn ${admin ? 'btn-dark' : 'btn-primary'} w-100`} disabled={busy}>{busy ? 'Saving…' : 'Update password'}</button>
      </form>
    </Frame>
  );
}
