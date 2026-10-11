import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

/** My profile: name / email, and change my own password. */
export default function Profile() {
  const { reload } = useSession();
  const q = useQuery({ queryKey: ['profile'], gcTime: 0, queryFn: async () => (await get('/profile')).data });
  const [me, setMe] = useState({ name: '', email: '' }); const [pw, setPw] = useState({ current_password: '', password: '', password_confirm: '' });
  const [m1, setM1] = useState<Msg[]>([]); const [m2, setM2] = useState<Msg[]>([]);
  useEffect(() => { if (q.data) setMe({ name: q.data.me.name, email: q.data.me.email }); }, [q.data]);
  if (q.isLoading) return <Spinner />;
  const saveMe = async () => { const r = await post('/profile', me); setM1(r.messages ?? []); if (r.ok) await reload(); };
  const savePw = async () => { const r = await post('/profile/password', pw); setM2(r.messages ?? []); if (r.ok) setPw({ current_password: '', password: '', password_confirm: '' }); };
  return (
    <>
      <PageHead title="My profile" />
      <div className="row g-4">
        <div className="col-lg-6"><div className="card"><div className="card-body">
          <h2 className="h6 mb-3">Details</h2><Messages items={m1} onClose={() => setM1([])} />
          <form onSubmit={(e) => { e.preventDefault(); void saveMe(); }}>
            <div className="mb-3"><label className="form-label">Name</label><input className="form-control" required value={me.name} onChange={(e) => setMe({ ...me, name: e.target.value })} /></div>
            <div className="mb-3"><label className="form-label">Email</label><input type="email" className="form-control" required value={me.email} onChange={(e) => setMe({ ...me, email: e.target.value })} /></div>
            <button className="btn btn-primary">Save</button></form></div></div></div>
        <div className="col-lg-6"><div className="card"><div className="card-body">
          <h2 className="h6 mb-3">Change password</h2><Messages items={m2} onClose={() => setM2([])} />
          <form onSubmit={(e) => { e.preventDefault(); void savePw(); }}>
            <div className="mb-3"><label className="form-label">Current password</label><input type="password" className="form-control" required autoComplete="current-password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} /></div>
            <div className="mb-3"><label className="form-label">New password</label><input type="password" className="form-control" minLength={8} required autoComplete="new-password" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} /></div>
            <div className="mb-3"><label className="form-label">Confirm new password</label><input type="password" className="form-control" minLength={8} required autoComplete="new-password" value={pw.password_confirm} onChange={(e) => setPw({ ...pw, password_confirm: e.target.value })} /></div>
            <button className="btn btn-primary">Change password</button></form></div></div></div>
      </div>
    </>
  );
}
