import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';

/** Customer create / edit, drawn from the layout the company designed (sections, own fields, labels). */
export default function CustomerForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('customer');
  const src = useQuery({ queryKey: ['customer-form', id ?? 'new'], queryFn: async () => (await get(id ? `/customers/${id}/edit` : '/customers/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [active, setActive] = useState(true);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const row = src.data.row ?? {}; const cf = src.data.cfValues ?? {};
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; }
      else v[k] = row[k] ?? (f.key === 'credit_days' && !id ? '0' : '');
    }
    setValues(v); setActive(row.is_active === undefined ? true : !!Number(row.is_active));
  }, [schema.data, src.data, values, id]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const walkin = !!src.data.row?.is_walkin;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = toPayload(schema.data!, values);
    if (id && active) body.is_active = 1;
    const r = await post(id ? `/customers/${id}` : '/customers', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['customers'] }); qc.invalidateQueries({ queryKey: ['customer'] }); qc.invalidateQueries({ queryKey: ['record'] });
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/customers');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={(id ? 'Edit ' : 'New ') + term('customer').toLowerCase()}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=customer&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/customers/${id}` : '/customers')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 820 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        {id && !walkin && <div className="form-check mb-3"><input className="form-check-input" type="checkbox" id="act" checked={active} onChange={(e) => setActive(e.target.checked)} /><label className="form-check-label" htmlFor="act">Active</label></div>}
      </div></div>
    </form>
  );
}
