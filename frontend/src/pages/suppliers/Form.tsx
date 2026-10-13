import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';

type Contact = { name: string; role: string; phone: string; email: string };

/** Supplier create / edit from the designed layout, plus the extra contact people. */
export default function SupplierForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('supplier');
  const src = useQuery({ queryKey: ['supplier-form', id ?? 'new'], queryFn: async () => (await get(id ? `/suppliers/${id}/edit` : '/suppliers/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]); const [active, setActive] = useState(true);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const row = src.data.row ?? {}; const cf = src.data.cfValues ?? {};
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; }
      else v[k] = row[k] ?? ((k === 'payment_terms_days' || k === 'lead_time_days') && !id ? '0' : '');
    }
    setValues(v); setActive(row.is_active === undefined ? true : !!Number(row.is_active));
    setContacts((src.data.contacts ?? []).map((c: any) => ({ name: c.name ?? '', role: c.role ?? '', phone: c.phone ?? '', email: c.email ?? '' })));
  }, [schema.data, src.data, values, id]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const setC = (i: number, k: keyof Contact, v: string) => setContacts((cs) => cs.map((c, j) => (j === i ? { ...c, [k]: v } : c)));
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), contacts };
    if (id && active) body.is_active = 1;
    const r = await post(id ? `/suppliers/${id}` : '/suppliers', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['suppliers'] }); qc.invalidateQueries({ queryKey: ['supplier'] }); qc.invalidateQueries({ queryKey: ['record'] });
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/suppliers');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={(id ? 'Edit ' : 'New ') + term('supplier').toLowerCase()}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=supplier&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/suppliers/${id}` : '/suppliers')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 980 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <div className="d-flex justify-content-between align-items-center mb-2 mt-2"><strong>More contact people</strong>
          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setContacts((c) => [...c, { name: '', role: '', phone: '', email: '' }])}><i className="bi bi-plus-lg" /> Add contact</button></div>
        {contacts.length > 0 && <div className="table-responsive"><table className="table table-sm align-middle mb-3"><thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Email</th><th /></tr></thead><tbody>
          {contacts.map((c, i) => <tr key={i}>
            {(['name', 'role', 'phone', 'email'] as const).map((k) => <td key={k}><input className="form-control form-control-sm" type={k === 'email' ? 'email' : 'text'} value={c[k]} maxLength={k === 'name' ? 100 : k === 'role' ? 60 : k === 'phone' ? 40 : 190} onChange={(e) => setC(i, k, e.target.value)} /></td>)}
            <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove contact" onClick={() => setContacts((cs) => cs.filter((_, j) => j !== i))}>×</button></td></tr>)}</tbody></table></div>}
        {id && <div className="form-check mb-3"><input className="form-check-input" type="checkbox" id="act" checked={active} onChange={(e) => setActive(e.target.checked)} /><label className="form-check-label" htmlFor="act">Active</label></div>}
      </div></div>
    </form>
  );
}
