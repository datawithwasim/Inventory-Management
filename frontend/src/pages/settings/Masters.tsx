import { useEffect, useState } from 'react';
import { Link, NavLink, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

type Def = { title: string; one: string; fields: [string, string, string, boolean][] };
const Tabs = ({ tabs, type }: { tabs: Record<string, Def>; type: string }) => (
  <ul className="nav nav-pills mb-3">{Object.entries(tabs).map(([k, t]) => <li className="nav-item" key={k}><NavLink className={`nav-link ${k === type ? 'active' : ''}`} to={`/masters/${k}`}>{t.title}</NavLink></li>)}</ul>
);

/** Categories, brands, units and taxes: one generic list. */
export function MasterList() {
  const { type = 'categories' } = useParams(); const { can, term } = useSession(); const qc = useQueryClient(); const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['masters', type], queryFn: async () => (await get(`/masters/${type}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Not found.</div>;
  const def: Def = d.def;
  const del = async (id: number) => { if (!confirm('Delete?')) return; const r = await post(`/masters/${type}/${id}/delete`); setMsgs(r.messages ?? []); qc.invalidateQueries({ queryKey: ['masters', type] }); };
  return (
    <>
      <PageHead title="Masters">{can('masters.create') && <Link className="btn btn-primary" to={`/masters/${type}/create`}><i className="bi bi-plus-lg" /> Add {def.one.toLowerCase()}</Link>}</PageHead>
      <Tabs tabs={d.tabs} type={type} />
      <Messages items={msgs} onClose={() => setMsgs([])} />
      <div className="text-muted mb-2">{d.rows.length} {def.title.toLowerCase()}</div>
      <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
        <thead><tr>{def.fields.map(([col, label]) => <th key={col}>{type === 'units' && col === 'allow_decimal' ? 'Decimals' : label}</th>)}<th>Used by {term('item', true).toLowerCase()}</th><th /></tr></thead><tbody>
          {d.rows.map((r: any) => <tr key={r.id}>{def.fields.map(([col, , kind]) => <td key={col}>{kind === 'checkbox' ? (Number(r[col]) ? 'Yes' : 'No') : String(r[col] ?? '')}</td>)}<td>{r.used}</td>
            <td className="text-end text-nowrap">{can('masters.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/masters/${type}/${r.id}/edit`}>Edit</Link>}{' '}{can('masters.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => del(r.id)}>Delete</button>}</td></tr>)}
          {!d.rows.length && <tr><td colSpan={9} className="text-muted">Nothing here yet.</td></tr>}
        </tbody></table></div></div>
    </>
  );
}

export function MasterForm() {
  const { type = 'categories', id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['master-form', type, id ?? 'new'], gcTime: 0, queryFn: async () => (await get(id ? `/masters/${type}/${id}/edit` : `/masters/${type}/create`)).data });
  const [v, setV] = useState<Record<string, string> | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!src.data || v) return;
    const def: Def = src.data.def; const row = src.data.row ?? {};
    setV(Object.fromEntries(def.fields.map(([col, , kind]) => [col, kind === 'checkbox' ? (Number(row[col] ?? 0) ? '1' : '0') : String(row[col] ?? (kind === 'number' ? '0' : ''))])));
  }, [src.data, v]);
  if (src.isLoading || !v) return <Spinner />;
  const def: Def = src.data.def;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(id ? `/masters/${type}/${id}` : `/masters/${type}`, v);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    ['masters', 'options'].forEach((k) => qc.invalidateQueries({ queryKey: [k] })); nav(`/masters/${type}`);
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={`${id ? 'Edit' : 'New'} ${def.one.toLowerCase()}`}><button type="button" className="btn btn-outline-secondary" onClick={() => nav(`/masters/${type}`)}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 480 }}><div className="card-body">
        {def.fields.map(([col, label, kind, required]) => kind === 'checkbox'
          ? <div className="form-check mb-3" key={col}><input className="form-check-input" type="checkbox" id={`f_${col}`} checked={v[col] === '1'} onChange={(e) => setV({ ...v, [col]: e.target.checked ? '1' : '0' })} /><label className="form-check-label" htmlFor={`f_${col}`}>{label}</label></div>
          : <div className="mb-3" key={col}><label className="form-label">{label}</label><input className="form-control" required={required} {...(kind === 'number' ? { type: 'number', step: '0.01', min: 0, max: 999 } : {})} value={v[col]} onChange={(e) => setV({ ...v, [col]: e.target.value })} /></div>)}
      </div></div>
    </form>
  );
}
