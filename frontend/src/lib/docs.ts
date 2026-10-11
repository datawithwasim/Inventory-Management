/** Money still owed on a purchase bill / sales invoice, and its payment state (mirrors Purchase::outstanding / payStatus). */
export const owed = (r: { total: any; returned_amount: any; paid_amount: any }) => Number(r.total) - Number(r.returned_amount) - Number(r.paid_amount);
export const payState = (r: { total: any; returned_amount: any; paid_amount: any }): 'paid' | 'partial' | 'unpaid' => {
  if (owed(r) <= 0.004) return 'paid';
  return Number(r.paid_amount) > 0.004 ? 'partial' : 'unpaid';
};
/** Line maths: net = qty × price, tax on it, total. */
export const lineTotals = (qty: number, price: number, taxPct: number) => { const net = qty * price; const tax = net * taxPct / 100; return { net, tax, total: net + tax }; };
