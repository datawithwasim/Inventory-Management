import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { fdate } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';

// ------------------------------------------------------------------ users

export function UserList() {
  const { can } = useSession(); const qc = useQueryClient(); const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['users'], queryFn: async () => (await get('/users')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  const act = async (path: string, ask?: string) => { if (ask && !confirm(ask)) return; const r = await post(path); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['users'] }); };
  return (
    <>
      <PageHead title="Users">{can('users.create') && (d.limitReached ? <span className="text-warning">User limit reached for your plan</span> : <Link className="btn btn-primary" to="/users/create"><i className="bi bi-plus-lg" /> Add user</Link>)}</PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="text-muted mb-2">{d.users.length} user(s)</div>
      <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th /></tr></thead><tbody>
          {d.users.map((x: any) => <tr key={x.id}><td>{x.name}</td><td>{x.email}</td><td><Badge>{x.role_name}</Badge></td><td>{Number(x.is_active) ? <Badge tone="success">Active</Badge> : <Badge tone="danger">Disabled</Badge>}</td>
            <td className="text-muted small">{x.last_login_at ? `${fdate(String(x.last_login_at).slice(0, 10))} ${String(x.last_login_at).slice(11, 16)}` : '—'}</td>
            <td className="text-end text-nowrap">
              {can('users.edit') && <><Link className="btn btn-sm btn-outline-primary" to={`/users/${x.id}/edit`}>Edit</Link>{' '}<button className="btn btn-sm btn-outline-warning" onClick={() => act(`/users/${x.id}/toggle`)}>{Number(x.is_active) ? 'Disable' : 'Enable'}</button>{' '}</>}
              {can('users.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => act(`/users/${x.id}/delete`, 'Delete this user?')}>Delete</button>}</td></tr>)}
        </tbody></table></div></div>
    </>
  );
}

export function UserForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['user-form', id ?? 'new'], gcTime: 0, queryFn: async () => await get(id ? `/users/${id}/edit` : '/users/create') });
  const [v, setV] = useState<Record<string, string> | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!src.data) return;
    if (src.data.redirect && !src.data.data) { nav('/' + String(src.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (v || !src.data.data) return;
    const u = src.data.data.user ?? {};
    setV({ name: u.name ?? '', email: u.email ?? '', role_id: String(u.role_id ?? src.data.data.roles?.[0]?.id ?? ''), password: '' });
  }, [src.data, v, nav]);
  if (src.isLoading || !v || !src.data) return <Spinner />;
  const d = src.data.data;
  const set = (k: string, val: string) => setV({ ...v, [k]: val });
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(id ? `/users/${id}` : '/users', v);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['users'] }); nav('/users');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? 'Edit user' : 'Add user'}><button type="button" className="btn btn-outline-secondary" onClick={() => nav('/users')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 560 }}><div className="card-body">
        <div className="mb-3"><label className="form-label">Name</label><input className="form-control" required maxLength={120} value={v.name} onChange={(e) => set('name', e.target.value)} /></div>
        <div className="mb-3"><label className="form-label">Email</label><input type="email" className="form-control" required value={v.email} onChange={(e) => set('email', e.target.value)} /></div>
        <div className="mb-3"><label className="form-label">Role</label><select className="form-select" required value={v.role_id} onChange={(e) => set('role_id', e.target.value)}>{d.roles.map((r: any) => <option key={r.id} value={r.id}>{r.name}</option>)}</select></div>
        <div className="mb-1"><label className="form-label">{id ? 'New password (leave blank to keep)' : 'Password'}</label><input type="password" className="form-control" minLength={8} required={!id} autoComplete="new-password" value={v.password} onChange={(e) => set('password', e.target.value)} /></div>
      </div></div>
    </form>
  );
}

// ------------------------------------------------------------------ roles

export function RoleList() {
  const { can } = useSession(); const qc = useQueryClient(); const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['roles'], queryFn: async () => (await get('/roles')).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return null;
  const del = async (id: number) => { if (!confirm('Delete this role?')) return; const r = await post(`/roles/${id}/delete`); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['roles'] }); };
  return (
    <>
      <PageHead title="Roles & permissions">{can('roles.create') && <Link className="btn btn-primary" to="/roles/create"><i className="bi bi-plus-lg" /> New role</Link>}</PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="text-muted mb-2">Control what each team member can see and do.</div>
      <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
        <thead><tr><th>Role</th><th>Users</th><th>Type</th><th /></tr></thead><tbody>
          {d.roles.map((r: any) => <tr key={r.id}><td>{r.name}</td><td>{r.user_count}</td><td>{Number(r.is_owner) ? 'Full access' : Number(r.is_system) ? 'Default' : 'Custom'}</td>
            <td className="text-end text-nowrap">
              {!Number(r.is_owner) && can('roles.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/roles/${r.id}/edit`}>Permissions</Link>}{' '}
              {!Number(r.is_system) && can('roles.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => del(r.id)}>Delete</button>}</td></tr>)}
        </tbody></table></div></div>
    </>
  );
}

const ACTIONS = ['view', 'create', 'edit', 'delete', 'approve', 'adjust', 'transfer', 'use', 'export'];

export function RoleForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['role-form', id ?? 'new'], gcTime: 0, queryFn: async () => await get(id ? `/roles/${id}/edit` : '/roles/create') });
  const [name, setName] = useState<string | null>(null); const [perms, setPerms] = useState<Set<string>>(new Set());
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!src.data) return;
    if (src.data.redirect && !src.data.data) { nav('/' + String(src.data.redirect).replace(/^\//, ''), { replace: true }); return; }
    if (name !== null || !src.data.data) return;
    setName(src.data.data.role?.name ?? ''); setPerms(new Set(src.data.data.granted ?? []));
  }, [src.data, name, nav]);
  if (src.isLoading || name === null || !src.data) return <Spinner />;
  const d = src.data.data; const system = !!Number(d.role?.is_system);
  const toggle = (p: string) => setPerms((s) => { const n = new Set(s); n.has(p) ? n.delete(p) : n.add(p); return n; });
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(id ? `/roles/${id}` : '/roles', { name, perms: [...perms] });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['roles'] }); nav('/roles');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? 'Edit role' : 'New role'}><button type="button" className="btn btn-outline-secondary" onClick={() => nav('/roles')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save role'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card mb-3" style={{ maxWidth: 560 }}><div className="card-body"><label className="form-label">Role name</label><input className="form-control" maxLength={80} readOnly={system} required={!system} value={name} onChange={(e) => setName(e.target.value)} /></div></div>
      <div className="card"><div className="table-responsive"><table className="table perm-table mb-0">
        <thead><tr><th>Module</th>{ACTIONS.map((a) => <th key={a} className="text-center text-capitalize">{a}</th>)}</tr></thead><tbody>
          {Object.entries(d.modules as Record<string, { label: string; actions: string[] }>).map(([key, def]) => (
            <tr key={key}><td>{def.label}</td>{ACTIONS.map((a) => <td key={a} className="text-center">{def.actions.includes(a) && <input className="form-check-input" type="checkbox" checked={perms.has(`${key}.${a}`)} onChange={() => toggle(`${key}.${a}`)} />}</td>)}</tr>))}
        </tbody></table></div></div>
    </form>
  );
}
