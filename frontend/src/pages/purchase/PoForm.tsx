import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import PurchaseLines, { blankLine, type Line } from '../../components/PurchaseLines';

/** Purchase order create / edit (drafts only): the designed header, then the items table. */
export default function PoForm() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('purchase_order');
  const src = useQuery({ queryKey: ['po-form', id ?? 'new'], queryFn: async () => (await get(id ? `/purchase/orders/${id}/edit` : '/purchase/orders/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [lines, setLines] = useState<Line[]>([blankLine()]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const po = src.data.po ?? {}; const cf = src.data.cfValues ?? {};
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) {
      if (f.custom && f.cfId) { const raw = cf[f.cfId] ?? ''; v[k] = f.type === 'multiselect' ? (raw ? JSON.parse(raw) : []) : raw; }
      else v[k] = po[k] ?? '';
    }
    if (!id) { v.order_date = new Date().toISOString().slice(0, 10); v.warehouse_id = String(src.data.warehouses?.[0]?.id ?? ''); if (sp.get('supplier')) v.supplier_id = sp.get('supplier'); }
    setValues(v);
    const old: any[] = src.data.oldLines ?? [];
    setLines(old.length ? old.map((l) => ({ variant_id: String(l.variant_id), label: l.label, unit: l.unit ?? '', dec: l.dec !== 0, qty: String(Number(l.qty)), unit_price: String(l.unit_price ?? ''), tax_rate: String(Number(l.tax_rate ?? 0)), hint: '' })) : [blankLine()]);
  }, [schema.data, src.data, values, id, sp]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const body = { ...toPayload(schema.data!, values), lines: lines.filter((l) => l.variant_id).map((l) => ({ variant_id: l.variant_id, qty: l.qty, unit_price: l.unit_price, tax_rate: l.tax_rate })) };
    const r = await post(id ? `/purchase/orders/${id}` : '/purchase/orders', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['pos', 'po', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/purchase/orders');
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={id ? `Edit ${src.data.po.po_no}` : 'New purchase order'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=purchase_order&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(id ? `/purchase/orders/${id}` : '/purchase/orders')}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save draft'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><SchemaForm schema={schema.data} values={values} onChange={setValues} /></div></div>
      <PurchaseLines lines={lines} setLines={setLines} supplier={values.supplier_id || undefined} showTax={schema.data.lines?.tax !== false} term={term} />
    </form>
  );
}
