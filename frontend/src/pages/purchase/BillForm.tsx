import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { money } from '../../lib/format';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';

/** Edit a bill's own details (supplier's bill no., dates, other charges, notes). */
export default function BillForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const schema = useSchema('bill');
  const src = useQuery({ queryKey: ['bill-form', id], queryFn: async () => (await get(`/purchase/bills/${id}/edit`)).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const b = src.data.b; const v: Record<string, any> = {};
    for (const k of Object.keys(schema.data.fields)) v[k] = b[k] ?? '';
    if (Number(v.other_charges) === 0) v.other_charges = '';
    setValues(v);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const b = src.data.b;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(`/purchase/bills/${id}`, toPayload(schema.data!, values));
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    ['bill', 'bills', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(`/purchase/bills/${id}`);
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title="Edit bill" extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=bill&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(`/purchase/bills/${id}`)}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 760 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <div className="form-text">Goods {money(b.subtotal)} + tax {money(b.tax_total)} + these charges = bill total.</div></div></div>
    </form>
  );
}
