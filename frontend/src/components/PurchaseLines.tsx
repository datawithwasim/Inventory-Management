import { money, moneyIn, qty as fmtQty } from '../lib/format';
import { lineTotals } from '../lib/docs';
import ItemSearch from './ItemSearch';
import LineGrid, { gridCols, type CellDef, type GridCol } from './LineGrid';

export type Rates = { roll: number | null; cut: number | null; moq: number | null; min_qty: number; currency: string };
/** rate_kind: '' = follow the quantity (MOQ rate from the MOQ up, else roll), 'roll' | 'cut' | 'moq' = chosen, 'manual' = typed by hand. */
export type Line = { variant_id: string; label: string; unit: string; dec: boolean; qty: string; unit_price: string; tax_rate: string; hint?: string; rates?: Rates | null; rate_kind?: string; cost?: number };

/** The supplier's rate for a kind in this line's quote, or null. */
const kindPrice = (r: Rates, k: string) => (k === 'moq' ? (r.min_qty > 0 ? r.moq : null) : k === 'cut' ? r.cut : r.roll);
const autoKind = (r: Rates, q: number) => (r.min_qty > 0 && r.moq !== null && q >= r.min_qty ? 'moq' : 'roll');
/** Re-prices a line from its supplier quote after the quantity or the chosen kind changed (only when it follows the quote). */
export const repriced = (l: Line, patch: Partial<Line>): Line => {
  const n = { ...l, ...patch };
  if (!n.rates || n.rate_kind === 'manual') return n;
  const k = n.rate_kind || autoKind(n.rates, Number(n.qty) || 0);
  const v = kindPrice(n.rates, k);
  return v === null ? n : { ...n, unit_price: String(v) };
};
export const blankLine = (): Line => ({ variant_id: '', label: '', unit: '', dec: true, qty: '', unit_price: '', tax_rate: '', hint: '' });

/** The items table of a purchase order, drawn from its column design: search an item, qty, price, tax, line total. */
export default function PurchaseLines({ lines, setLines, supplier, grid, term, currency = 'INR', exchange = 1 }: { lines: Line[]; setLines: (l: Line[]) => void; supplier?: string; grid?: GridCol[]; term: (k: string) => string; currency?: string; exchange?: number }) {
  const set = (i: number, patch: Partial<Line>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const foreign = currency !== 'INR';
  const m = (v: number) => moneyIn(v, currency);
  const calc = (l: Line) => lineTotals(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.tax_rate) || 0);
  const totals = lines.reduce((a, l) => { const t = calc(l); return { net: a.net + t.net, tax: a.tax + t.tax }; }, { net: 0, tax: 0 });
  const cols = gridCols(grid, { item: term('item'), qty: 'Qty', price: 'Unit price', tax: 'Tax %', total: 'Line total' });
  const hasTax = cols.some((c) => c.key === 'tax');
  const defs: Record<string, CellDef<Line>> = {
    item: { style: { minWidth: 260 }, cell: (l, i) => (<>
      <ItemSearch value={l.variant_id} label={l.label} supplier={supplier} placeholder="Search item, SKU or barcode"
        onPick={(p) => {
          if (!p) { set(i, { variant_id: '', label: '', hint: '' }); return; }
          const v = p.row; const hint = [v.sup_code || v.sup_name ? 'Their: ' + [v.sup_code, v.sup_name].filter(Boolean).join(' · ') : '', v.rate_note, v.last_note].filter(Boolean).join(' · ');
          // the supplier's quote counts only when it is in this order's currency; otherwise start from our own cost (rupees) turned into the order's currency
          const quote: Rates | null = v.rates && v.rates.currency === currency ? v.rates : null;
          const cost = Number(v.last_paid ?? v.cost_price ?? 0);
          const own = foreign && exchange > 0 ? Math.round((cost / exchange) * 100) / 100 : cost;
          const base: Line = { ...lines[i], variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), unit_price: quote ? String(quote.roll ?? '') : String(own || ''), tax_rate: String(Number(v.tax_rate ?? 0)), hint, rates: quote, rate_kind: '' };
          setLines(lines.map((l, j) => (j === i ? repriced(base, {}) : l)));
        }} />
      {l.hint && <div className="small text-muted mt-1">{l.hint}</div>}</>) },
    qty: { style: { width: 130 }, cell: (l, i) => <div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => setLines(lines.map((x, j) => (j === i ? repriced(x, { qty: e.target.value }) : x)))} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div> },
    price: { style: { width: 150 }, cell: (l, i) => (<>
      <input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value, rate_kind: 'manual' })} />
      {l.rates && (l.rates.cut !== null || (l.rates.moq !== null && l.rates.min_qty > 0)) && (
        <select className="form-select form-select-sm mt-1" aria-label="Which rate" value={l.rate_kind === 'manual' ? 'manual' : l.rate_kind || ''} onChange={(e) => setLines(lines.map((x, j) => (j === i ? repriced(x, { rate_kind: e.target.value }) : x)))}>
          <option value="">By quantity ({autoKind(l.rates, Number(l.qty) || 0) === 'moq' ? 'MOQ' : 'Roll'})</option>
          <option value="roll">Roll rate {l.rates.roll !== null ? m(l.rates.roll) : ''}</option>
          {l.rates.cut !== null && <option value="cut">Cut rate {m(l.rates.cut)}</option>}
          {l.rates.moq !== null && l.rates.min_qty > 0 && <option value="moq">MOQ rate {m(l.rates.moq)}</option>}
          {l.rate_kind === 'manual' && <option value="manual">Typed by hand</option>}
        </select>)}
    </>) },
    tax: { style: { width: 100 }, cell: (l, i) => <input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /> },
    total: { style: { width: 130 }, className: 'text-end', cell: (l) => m(calc(l).total) },
  };
  return (
    <LineGrid title="Items" addLabel="Add item" onAdd={() => setLines([...lines, blankLine()])} cols={cols} defs={defs} rows={lines}
      onRemove={(i) => setLines(lines.length > 1 ? lines.filter((_, j) => j !== i) : [blankLine()])}
      footer={<>
        <tr><td className="text-muted">Subtotal</td><td className="text-end">{m(totals.net)}</td></tr>
        {hasTax && <tr><td className="text-muted">Tax</td><td className="text-end">{m(totals.tax)}</td></tr>}
        <tr><th>Total{foreign ? ` (${currency})` : ''}</th><th className="text-end">{m(totals.net + totals.tax)}</th></tr>
        {foreign && <tr><td className="text-muted">In rupees at ₹ {exchange}</td><td className="text-end">{money((totals.net + totals.tax) * exchange)}</td></tr>}</>} />
  );
}
export { fmtQty };
