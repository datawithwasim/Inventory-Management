import { useState } from 'react';
import { post, type Msg } from '../lib/api';
import { Messages } from './ui';
import ItemSearch from './ItemSearch';

/** Add / revise a rate for one of this supplier's items. */
export default function RateForm({ supplierId, preset, onDone, plain = false, currency = 'INR' }: { supplierId: number; preset?: any; onDone: () => void; plain?: boolean; currency?: string }) {
  const [item, setItem] = useState<{ id: string; label: string }>({ id: preset ? String(preset.variant_id) : '', label: preset ? `${preset.item_name}${preset.vname ? ' — ' + preset.vname : ''} (${preset.sku})` : '' });
  const [f, setF] = useState({ rate: preset ? String(preset.rate) : '', cut_rate: preset && preset.cut_rate != null ? String(preset.cut_rate) : '', moq_rate: preset && preset.moq_rate != null ? String(preset.moq_rate) : '', discount_pct: preset && Number(preset.discount_pct) ? String(preset.discount_pct) : '', min_qty: preset && Number(preset.min_qty) ? String(preset.min_qty) : '', lead_time_days: preset?.lead_time_days ?? '', supplier_code: preset?.scode ?? '', supplier_name: preset?.sname ?? '', valid_from: new Date().toISOString().slice(0, 10), valid_to: '' });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const set = (k: string, v: string) => setF((x) => ({ ...x, [k]: v }));
  const save = async () => {
    if (!item.id) { setMsgs([{ type: 'danger', msg: 'Pick an item from the list.' }]); return; }
    setBusy(true);
    const r = await post(`/suppliers/${supplierId}/rates`, { ...f, variant_id: item.id, return: `suppliers/${supplierId}` });
    setBusy(false);
    if (r.ok) onDone(); else setMsgs(r.messages ?? []);
  };
  const inp = (k: string, label: string, type = 'text', col = 'col-6', extra: any = {}) => <div className={col}><label className="form-label small mb-1">{label}</label><input className="form-control form-control-sm" type={type} value={(f as any)[k]} onChange={(e) => set(k, e.target.value)} {...extra} /></div>;
  return (
    <div className={plain ? '' : 'p-3 border-bottom bg-body-tertiary'}>
      <Messages items={msgs} />
      <div className="row g-2 align-items-end">
        <div className="col-12"><label className="form-label small mb-1">Item</label><ItemSearch value={item.id} label={item.label} supplier={supplierId} disabled={!!preset} onPick={(p) => setItem(p ? { id: String(p.id), label: p.label } : { id: '', label: '' })} /></div>
        {inp('rate', `Roll rate (${currency})`, 'number', 'col-6 col-md-3', { step: '0.01', min: '0' })}{inp('cut_rate', `Cut rate (${currency})`, 'number', 'col-6 col-md-3', { step: '0.01', min: '0' })}
        {inp('moq_rate', `MOQ rate (${currency})`, 'number', 'col-6 col-md-3', { step: '0.01', min: '0' })}{inp('min_qty', 'MOQ (min. qty)', 'number', 'col-6 col-md-3', { step: '0.001', min: '0' })}
        {inp('discount_pct', 'Discount %', 'number', 'col-6 col-md-3', { step: '0.01', min: '0', max: '100' })}{inp('lead_time_days', 'Lead time (days)', 'number', 'col-6 col-md-3', { min: '0', max: '365' })}
        {inp('valid_from', 'Valid from', 'date', 'col-6 col-md-3')}{inp('valid_to', 'Valid till', 'date', 'col-6 col-md-3')}{inp('supplier_code', 'Their code', 'text', 'col-6 col-md-3', { maxLength: 60 })}{inp('supplier_name', 'Their item name', 'text', 'col-6 col-md-3', { maxLength: 150 })}
        <div className="col-12 form-text mt-0">Roll rate is the base rate. Cut rate is for cut lengths; the MOQ rate applies from the MOQ quantity up. All in {currency}{currency !== 'INR' ? ' (the supplier is imported)' : ''}.</div>
        <div className="col-12 d-flex gap-2"><button className="btn btn-sm btn-primary" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Save rate'}</button><button className="btn btn-sm btn-link text-muted" onClick={onDone}>Cancel</button></div>
      </div>
    </div>
  );
}

