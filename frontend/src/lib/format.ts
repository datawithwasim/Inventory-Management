import type { Session } from './session';

// Numbers and dates the way the company set them up (Settings → Preferences); mirrors the PHP money() / fdate() helpers.
let fmt: NonNullable<Session['fmt']> = { decimals: 2, grouping: '', symbol: '', position: 'before', date: 'Y-m-d' };
export const setFormat = (f?: Session['fmt']) => { if (f) fmt = f; };

export function money(v: unknown): string {
  const n = Number(v ?? 0); const d = fmt.decimals;
  const neg = n < 0;
  const fixed = Math.abs(n).toFixed(d);
  let [int, dec] = fixed.split('.');
  if (fmt.grouping === 'indian') int = int.length > 3 ? int.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + int.slice(-3) : int;
  else int = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  let s = int + (dec ? '.' + dec : '');
  if (fmt.symbol) s = fmt.position === 'after' ? `${s} ${fmt.symbol}` : `${fmt.symbol} ${s}`;
  return (neg ? '-' : '') + s;
}

const SYMBOLS: Record<string, string> = { USD: '$', EUR: '€', GBP: '£', CNY: 'CN¥', JPY: 'JP¥' };

/** An amount in its own currency: rupees the way the company writes money, other currencies with their symbol (or code). */
export function moneyIn(v: unknown, code?: string | null): string {
  if (!code || code === 'INR') return money(v);
  const n = Number(v ?? 0);
  return `${SYMBOLS[code] ?? code} ${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Quantities: up to 3 decimals, no trailing zeros. */
export function qty(v: unknown): string {
  const s = Number(v ?? 0).toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
  return s === '' ? '0' : s;
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const pad = (n: number) => String(n).padStart(2, '0');

/** Dates (and date-times) with the company's PHP date format letters (Y m d j n M F y D l H i). */
export function fdate(v: unknown): string {
  if (typeof v !== 'string' || v === '' || v.startsWith('0000')) return '';
  const d = new Date(v.length <= 10 ? v + 'T00:00:00' : v.replace(' ', 'T'));
  if (isNaN(d.getTime())) return v;
  const map: Record<string, string> = {
    Y: String(d.getFullYear()), y: String(d.getFullYear()).slice(2), m: pad(d.getMonth() + 1), n: String(d.getMonth() + 1), d: pad(d.getDate()), j: String(d.getDate()),
    M: MONTHS[d.getMonth()].slice(0, 3), F: MONTHS[d.getMonth()], D: DAYS[d.getDay()].slice(0, 3), l: DAYS[d.getDay()],
  };
  return fmt.date.replace(/[YymndjMFDl]/g, (c) => map[c]);
}
