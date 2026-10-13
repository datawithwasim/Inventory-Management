import { useState, type FormEvent } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { legacyUrl, post, type Msg } from '../lib/api';
import { useSession } from '../lib/session';
import { Messages } from '../components/ui';

/** The product name the server put in the page (the same one the classic sign-in page shows). */
const appName = (document.querySelector('meta[name="app-title"]') as HTMLMetaElement | null)?.content || 'Inventory';

export default function Login() {
  const { session, reload } = useSession();
  const nav = useNavigate();
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('');
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (session?.authenticated) return <Navigate to="/dashboard" replace />;
  const submit = async (e: FormEvent) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    try {
      const r = await post('/login', { email, password });
      if (r.ok) { await reload(); nav('/dashboard', { replace: true }); } else setMsgs(r.messages ?? []);
    } catch (err: any) { setMsgs([{ type: 'danger', msg: err.message }]); }
    setBusy(false);
  };
  return (
    <div className="auth-split">
      <section className="auth-hero">
        <div className="auth-logo"><span className="sb-mark"><i className="bi bi-box-seam" /></span>{appName}</div>
        <div><h2>Know exactly what you have, where it is, and what is selling.</h2>
          <ul><li><i className="bi bi-check-circle-fill" />Roll-by-roll fabric tracking with rack locations</li><li><i className="bi bi-check-circle-fill" />Purchase, sales, returns and stock by roll and rack</li><li><i className="bi bi-check-circle-fill" />Reports, barcode labels and your own settings</li></ul></div>
        <div className="small opacity-75">&copy; {new Date().getFullYear()} {appName}</div>
      </section>
      <section className="auth-pane"><div className="auth-card"><div className="card"><div className="card-body p-4">
        <Messages items={msgs} />
        <h2 className="h5 mb-3">Sign in</h2>
        <form onSubmit={submit}>
          <div className="mb-3"><label className="form-label" htmlFor="email">Email</label><input id="email" type="email" className="form-control" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus /></div>
          <div className="mb-3"><label className="form-label" htmlFor="pw">Password</label><input id="pw" type="password" className="form-control" value={password} onChange={(e) => setPassword(e.target.value)} required /></div>
          <button className="btn btn-primary w-100" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
          <div className="text-center mt-3"><a href={legacyUrl('/forgot-password')}>Forgot password?</a></div>
        </form>
      </div></div></div></section>
    </div>
  );
}
