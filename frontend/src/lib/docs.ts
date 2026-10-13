/** Money still owed on a purchase bill / sales invoice, and its payment state (mirrors Purchase::outstanding / payStatus). */
export const owed = (r: { total: any; returned_amount: any; paid_amount: any }) => Number(r.total) - Number(r.returned_amount) - Number(r.paid_amount);
export const payState = (r: { total: any; returned_amount: any; paid_amount: any }): 'paid' | 'partial' | 'unpaid' => {
  if (owed(r) <= 0.004) return 'paid';
  return Number(r.paid_amount) > 0.004 ? 'partial' : 'unpaid';
};
/** Line maths: net = qty × price, tax on it, total. */
export const lineTotals = (qty: number, price: number, taxPct: number) => { const net = qty * price; const tax = net * taxPct / 100; return { net, tax, total: net + tax }; };
/** Sales line maths with the same rounding as the server (Sales::line): gross, discount, net, tax, total. */
const r2 = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100;
export const saleLine = (qty: number, price: number, discPct: number, taxPct: number) => {
  const gross = r2(qty * price); const disc = r2(gross * discPct / 100); const net = r2(gross - disc); const tax = r2(net * taxPct / 100);
  return { gross, disc, net, tax, total: r2(net + tax) };
};

/** What a sales line is called: the product the customer chose, or the item itself when it was sold without a product. */
export const lineName = (l: any): string => l.product_name || `${l.item_name}${l.vname ? ` — ${l.vname}` : ''}`;
