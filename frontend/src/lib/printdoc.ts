import { fdate, money, qty } from './format';

/* A designed print layout (config) and the document data it is drawn with. The server keeps and checks the config (PrintDoc.php);
   the app draws it, in print pages and in the designer's preview, with the same component. */

export type Head = { name: string; address: string; phone: string; email: string; website: string; tax_no: string; bank: string; logo: string | null };
export type Item = { k: string; label: string; show: number };
export type Col = { k: string; label: string; show: number; align: 'left' | 'center' | 'right'; w: number };
export type Block =
  | { id: string; type: 'parties'; show: number; cols: number; items: Item[] }
  | { id: string; type: 'items'; show: number; columns: Col[]; style: 'lines' | 'grid' | 'plain'; head_bg: 'accent' | 'light' | 'none'; zebra: number; row_no: number; compact: number }
  | { id: string; type: 'totals'; show: number; rows: Item[]; width: number; side: 'left' | 'right'; words: number; words_prefix: string }
  | { id: string; type: 'notes' | 'bank'; show: number; title: string }
  | { id: string; type: 'terms'; show: number; title: string; text: string }
  | { id: string; type: 'text'; show: number; title: string; text: string; align: 'left' | 'center' | 'right'; boxed: number; size: number }
  | { id: string; type: 'signature'; show: number; boxes: string[]; space: number }
  | { id: string; type: 'footer'; show: number; text: string; align: 'left' | 'center' | 'right' };
export type Cfg = {
  v: number;
  page: { size: string; orient: 'portrait' | 'landscape'; margin: number; font: string; fsize: number; color: string; accent: string; wm_text: string; wm_opacity: number };
  header: { logo: 'left' | 'right' | 'none'; logo_h: number; layout: 'split' | 'center'; name_size: number; show_address: number; show_phone: number; show_email: number; show_website: number; show_tax: number;
    title: string; title_size: number; title_color: string; rule: 'solid' | 'double' | 'none'; rule_w: number; meta: string[] };
  blocks: Block[];
};
export type DocKind = 'invoice' | 'delivery_note' | 'purchase_order';
export type Row = { cells: Record<string, string>; sub?: string; part?: boolean; net?: string; tot?: string };
export type DocData = {
  no: string; date: string; meta: Record<string, string>; parties: Record<string, string[]>; rows: Row[]; totals: Record<string, string | null>; grand: number; notes: string; party: string;
};

export const META_LABELS: Record<string, string> = { due: 'Due', order: 'Order', invoice: 'Invoice', expected: 'Expected' };

export const SIZES: Record<string, [number, number]> = { A4: [210, 297], A5: [148, 210], A6: [105, 148], Letter: [216, 279], Legal: [216, 356] };
export const paperMm = (p: Cfg['page']): [number, number] => { const [w, h] = SIZES[p.size] ?? SIZES.A4; return p.orient === 'landscape' ? [h, w] : [w, h]; };

const compact = (a: (string | null | undefined)[]) => a.filter((x): x is string => !!x && x.trim() !== '');

/** Turns what the print endpoints answer with into the one shape the layouts are drawn from. */
export function normalise(kind: DocKind, a: any): DocData {
  if (kind === 'invoice') {
    const i = a.i;
    return {
      no: i.invoice_no, date: i.invoice_date, party: i.customer, notes: i.notes ?? '', grand: Number(i.total),
      meta: { due: i.due_date ? fdate(i.due_date) : '', order: i.order_no ?? '' },
      parties: { billed_to: compact([i.customer, i.cust_address, i.cust_phone, i.cust_tax ? `Tax no. ${i.cust_tax}` : '']), ship_to: compact([i.ship_to]) },
      rows: (a.items as any[]).filter((l) => l.parent_id === null).map((l) => ({
        cells: { item: l.item_name + (l.vname ? ` — ${l.vname}` : ''), sku: l.sku ?? '', hsn: l.hsn_code ?? '', roll: l.batch_no ?? '', qty: `${qty(l.qty)} ${l.unit}`, price: money(l.unit_price),
          disc: l.dsc > 0 ? money(l.dsc) : '', tax: `${money(l.tx)} (${qty(l.tax_rate)}%)` }, net: money(l.net), tot: money(l.tot) })),
      totals: { gross: money(i.subtotal), discount: Number(i.discount_total) > 0 ? `− ${money(i.discount_total)}` : null, tax: money(i.tax_total), delivery: Number(i.delivery_charge) > 0 ? money(i.delivery_charge) : null,
        installation: Number(i.installation_charge) > 0 ? money(i.installation_charge) : null, total: money(i.total), returns: Number(i.returned_amount) > 0 ? `− ${money(i.returned_amount)}` : null,
        paid: money(i.paid_amount), balance: money(Math.max(0, Number(a.due))) },
    };
  }
  if (kind === 'purchase_order') {
    const po = a.po;
    return {
      no: po.po_no, date: po.order_date, party: po.supplier, notes: po.notes ?? '', grand: Number(po.total),
      meta: { expected: po.expected_date ? fdate(po.expected_date) : '' },
      parties: { supplier: compact([po.supplier, po.sup_address, po.sup_phone, po.sup_tax ? `Tax no. ${po.sup_tax}` : '']), deliver_to: compact([po.warehouse]) },
      rows: (a.items as any[]).map((l) => ({
        cells: { item: l.item_name + (l.vname ? ` — ${l.vname}` : ''), sku: l.sku ?? '', hsn: l.hsn_code ?? '', their: l.their ?? '', qty: `${qty(l.qty_ordered)} ${l.unit}`, price: money(l.unit_price), tax: qty(l.tax_rate) },
        net: money(l.net), tot: money(l.tot) })),
      totals: { subtotal: money(po.subtotal), tax: money(po.tax_total), total: money(po.total) },
    };
  }
  const d = a.d;
  return {
    no: d.delivery_no, date: d.delivery_date, party: d.customer, notes: d.note ?? '', grand: 0,
    meta: { order: d.order_no ?? '', invoice: d.invoice_no ?? '' },
    parties: { delivered_to: compact([d.customer, d.cust_phone, d.ship_to || d.cust_address]) },
    rows: (a.items as any[]).map((l) => {
      const part = l.parent_id !== null;
      return { part, cells: { item: (part ? '↳ ' : '') + l.item_name + (l.vname ? ` — ${l.vname}` : ''), sku: l.sku ?? '', hsn: l.hsn_code ?? '', roll: l.batch_no ?? '', rack: l.is_bundle ? '' : l.rack ?? '', qty: `${qty(l.qty)} ${l.unit}`, amount: part ? '' : money(l.tot) } };
    }),
    totals: {},
  };
}

/** Made-up document for a layout that has no real document to be tried with yet. */
export function sample(kind: DocKind): DocData {
  const rows: Row[] = [
    { cells: { item: 'Royal Velvet Wine 54in', sku: 'RV-WINE', hsn: '5407', roll: 'B-0012', rack: 'A-01', their: 'RV-54W', qty: '24 m', price: money(480), disc: money(57.6), tax: `${money(54.72)} (5%)`, amount: money(1094.4) }, net: money(1094.4), tot: money(1149.12) },
    { cells: { item: 'Floral Wallpaper Cream 53cm', sku: 'WP1', hsn: '4814', roll: 'B-0013', rack: 'B-03', their: 'FW-53', qty: '3 Roll', price: money(1450), disc: '', tax: `${money(217.5)} (5%)`, amount: money(4350) }, net: money(4350), tot: money(4567.5) },
    { cells: { item: 'Cotton Bedsheet King Blue', sku: 'BS-K-BL', hsn: '6302', roll: '', rack: 'C-02', their: '', qty: '4 Piece', price: money(1199), disc: money(95.92), tax: `${money(215.82)} (5%)`, amount: money(4700.08) }, net: money(4700.08), tot: money(4915.9) },
  ];
  const inv = kind === 'invoice';
  return {
    no: inv ? 'INV-0001' : kind === 'purchase_order' ? 'PO-0001' : 'DLV-0001', date: new Date().toISOString().slice(0, 10), party: 'Sample Customer', notes: 'Thank you. Goods once sold are not returnable.', grand: 10644.52,
    meta: { due: fdate(new Date(Date.now() + 15 * 864e5).toISOString().slice(0, 10)), order: 'SO-0001', invoice: 'INV-0001', expected: fdate(new Date(Date.now() + 7 * 864e5).toISOString().slice(0, 10)) },
    parties: { billed_to: ['Sample Customer', '12, Market Road, Surat', '98765 43210', 'Tax no. 24ABCDE1234F1Z5'], ship_to: ['Site address: 5, Green Park, Surat'], delivered_to: ['Sample Customer', '98765 43210', '5, Green Park, Surat'],
      supplier: ['Sample Mills', 'Plot 7, Industrial Area, Surat', '99887 76655'], deliver_to: ['Main Warehouse'] },
    rows,
    totals: { gross: money(10194.4), discount: `− ${money(153.52)}`, tax: money(488.04), delivery: money(150), installation: null, total: money(10644.52), returns: null, paid: money(5000), balance: money(5644.52), subtotal: money(10194.4) },
  };
}

const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
const below100 = (n: number) => (n < 20 ? ONES[n] : TENS[Math.floor(n / 10)] + (n % 10 ? ' ' + ONES[n % 10] : ''));
const below1000 = (n: number) => (n >= 100 ? ONES[Math.floor(n / 100)] + ' Hundred' + (n % 100 ? ' ' + below100(n % 100) : '') : below100(n));
/** 1,23,456 in the Indian way: crore, lakh, thousand. */
function words(n: number): string {
  if (n === 0) return 'Zero';
  const parts: string[] = [];
  for (const [div, name] of [[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as const) { if (n >= div) { parts.push(below1000(Math.floor(n / div)) + ' ' + name); n %= div; } }
  if (n > 0) parts.push(below1000(n));
  return parts.join(' ');
}
/** "Rupees One Thousand Two Hundred Fifty and Fifty Paise Only" */
export function amountWords(v: number, prefix = 'Rupees'): string {
  const cents = Math.round(Math.abs(v) * 100); const whole = Math.floor(cents / 100); const sub = cents % 100;
  return `${prefix ? prefix + ' ' : ''}${words(whole)}${sub ? ` and ${words(sub)} Paise` : ''} Only`;
}

/** Placeholders usable in the company's own text blocks. */
export const fillText = (t: string, d: DocData) => t.replace(/\{no\}/g, d.no).replace(/\{date\}/g, fdate(d.date)).replace(/\{party\}/g, d.party);
