import { money, qty as fmtQty } from '../lib/format';
import { lineTotals } from '../lib/docs';
import ItemSearch from './ItemSearch';

export type Line = { variant_id: string; label: string; unit: string; dec: boolean; qty: string; unit_price: string; tax_rate: string; hint?: string };
export const blankLine = (): Line => ({ variant_id: '', label: '', unit: '', dec: true, qty: '', unit_price: '', tax_rate: '', hint: '' });

/** The items table of a purchase order (and of receiving goods without an order): search an item, qty, price, tax. */
export default function PurchaseLines({ lines, setLines, supplier, showTax = true, term }: { lines: Line[]; setLines: (l: Line[]) => void; supplier?: string; showTax?: boolean; term: (k: string) => string }) {
  const set = (i: number, patch: Partial<Line>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const totals = lines.reduce((a, l) => { const t = lineTotals(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.tax_rate) || 0); return { net: a.net + t.net, tax: a.tax + t.tax }; }, { net: 0, tax: 0 });
  return (
    <div className="card mb-3">
      <div className="card-header d-flex justify-content-between align-items-center"><span>Items</span>
        <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines([...lines, blankLine()])}><i className="bi bi-plus-lg" /> Add item</button></div>
      <div className="table-responsive"><table className="table mb-0 align-middle" id="lineTable">
        <thead><tr><th style={{ minWidth: 260 }}>{term('item')}</th><th style={{ width: 130 }}>Qty</th><th style={{ width: 130 }}>Unit price</th>{showTax && <th style={{ width: 100 }}>Tax %</th>}<th className="text-end" style={{ width: 130 }}>Line total</th><th style={{ width: 44 }} /></tr></thead>
        <tbody>
          {lines.map((l, i) => {
            const t = lineTotals(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.tax_rate) || 0);
            return (
              <tr key={i}>
                <td><ItemSearch value={l.variant_id} label={l.label} supplier={supplier} placeholder="Search item, SKU or barcode"
                  onPick={(p) => {
                    if (!p) { set(i, { variant_id: '', label: '', hint: '' }); return; }
                    const v = p.row; const hint = [v.sup_code || v.sup_name ? 'Their: ' + [v.sup_code, v.sup_name].filter(Boolean).join(' · ') : '', v.rate_note, v.last_note].filter(Boolean).join(' · ');
                    set(i, { variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), unit_price: String(v.rate ?? v.cost_price ?? ''), tax_rate: String(Number(v.tax_rate ?? 0)), hint });
                  }} />
                  {l.hint && <div className="small text-muted mt-1">{l.hint}</div>}</td>
                <td><div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div></td>
                <td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value })} /></td>
                {showTax && <td><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /></td>}
                <td className="text-end">{money(t.total)}</td>
                <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={() => setLines(lines.length > 1 ? lines.filter((_, j) => j !== i) : [blankLine()])}>×</button></td>
              </tr>
            );
          })}
        </tbody>
        <tfoot><tr><td colSpan={showTax ? 4 : 3} className="text-end text-muted">Subtotal</td><td className="text-end">{money(totals.net)}</td><td /></tr>
          {showTax && <tr><td colSpan={4} className="text-end text-muted">Tax</td><td className="text-end">{money(totals.tax)}</td><td /></tr>}
          <tr><th colSpan={showTax ? 4 : 3} className="text-end">Total</th><th className="text-end">{money(totals.net + totals.tax)}</th><td /></tr></tfoot>
      </table></div>
    </div>
  );
}
export { fmtQty };
