import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';

/** Supplier item create / edit: their name and code, linked to one of our items, from the designed layout. */
export default function SupplierItemForm() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const schema = useSchema('supplier_item');
  const qs = id ? '' : `?${sp.toString()}`;
  const src = useQuery({ queryKey: ['si-form', id ?? 'new', qs], queryFn: async () => (await get(id ? `/purchase/supplier-items/${id}/edit` : `/purchase/supplier-items/create${qs}`)).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [flags, setFlags] = useState({ preferred: false, active: true });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const row = src.data.row ?? {}; const cf = src.data.cfValues ?? {};
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; }
      else v[k] = row[k] ?? '';
    }
    if (!id && src.data.pre) v.supplier_id = String(src.data.pre);
    v.variant_id = src.data.vid ? String(src.data.vid) : ''; v['variant_id:label'] = src.data.ourLabel ?? '';
    if (v.min_order_qty === 0 || v.min_order_qty === '0.000') v.min_order_qty = '';
    setValues(v); setFlags({ preferred: !!Number(row.is_preferred ?? 0), active: row.is_active === undefined ? true : !!Number(row.is_active) });
  }, [schema.data, src.data, values, id]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = toPayload(schema.data!, values);
    if (flags.preferred) body.is_preferred = 1; if (id && flags.active) body.is_active = 1;
    const r = await post(id ? `/purchase/supplier-items/${id}` : '/purchase/supplier-items', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['supplier-items'] }); qc.invalidateQueries({ queryKey: ['record'] }); qc.invalidateQueries({ queryKey: ['si'] }); qc.invalidateQueries({ queryKey: ['supplier'] });
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/purchase/supplier-items');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? 'Edit supplier item' : 'New supplier item'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=supplier_item&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/purchase/supplier-items/${id}` : '/purchase/supplier-items')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 860 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} locked={id ? ['supplier_id'] : []} />
        {id && <div className="form-text mb-3 mt-n2">The supplier cannot be changed. Add a new supplier item for another supplier.</div>}
        <div className="d-flex gap-4 mb-3">
          <div className="form-check"><input className="form-check-input" type="checkbox" id="pref" checked={flags.preferred} onChange={(e) => setFlags({ ...flags, preferred: e.target.checked })} /><label className="form-check-label" htmlFor="pref">Preferred source for this item</label></div>
          {id && <div className="form-check"><input className="form-check-input" type="checkbox" id="act" checked={flags.active} onChange={(e) => setFlags({ ...flags, active: e.target.checked })} /><label className="form-check-label" htmlFor="act">Active</label></div>}
        </div>
      </div></div>
    </form>
  );
}
