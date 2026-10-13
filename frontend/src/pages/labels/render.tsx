import { useEffect, useLayoutEffect, useRef, useState, type CSSProperties, type PointerEvent as RPointerEvent, type ReactElement } from 'react';
import { apiUrl } from '../../lib/api';
import { useSession } from '../../lib/session';

export type El = {
  id: string; type: 'text' | 'barcode' | 'qr' | 'box' | 'image'; x: number; y: number; w: number; h: number; rot: number;
  text?: string; font?: string; size?: number; bold?: boolean; italic?: boolean; underline?: boolean; upper?: boolean; align?: 'left' | 'center' | 'right'; valign?: 'top' | 'middle' | 'bottom';
  color?: string; bg?: string; lh?: number; ls?: number; maxlines?: number; fit?: boolean;
  sym?: string; value?: string; showtext?: boolean; tsize?: number; ecc?: 'L' | 'M';
  border?: number; fill?: string; radius?: number; src?: string;
};
export type Page = { mode: 'roll' | 'sheet'; paper: string; paper_w: number; paper_h: number; cols: number; rows: number; margin_top: number | null; margin_left: number | null; gap_x: number; gap_y: number; outline: boolean };
export type Template = { id: string | number | null; name: string; for: 'items' | 'rolls' | 'any'; w: number; h: number; page: Page; elements: El[]; builtin?: boolean; is_default?: boolean };
export type Values = Record<string, string>;

export const FONT_STACK: Record<string, string> = {
  Arial: 'Arial, Helvetica, sans-serif', Verdana: 'Verdana, Geneva, sans-serif', Tahoma: 'Tahoma, Geneva, sans-serif', 'Trebuchet MS': '"Trebuchet MS", Helvetica, sans-serif',
  'Times New Roman': '"Times New Roman", Times, serif', Georgia: 'Georgia, serif', 'Courier New': '"Courier New", Courier, monospace', Impact: 'Impact, "Arial Black", sans-serif',
};
const PT = 25.4 / 72;   // millimetres in one point

/** Fills {field} placeholders in a text. */
export const fill = (text: string, values: Values) => text.replace(/\{(\w+)\}/g, (_, k) => values[k] ?? '');

/** Barcode pictures, fetched once per (symbology, value). */
const svgCache = new Map<string, string>();
function useBarcode(sym: string, value: string, ecc: string): string {
  const key = `${sym}|${ecc}|${value}`;
  const [svg, setSvg] = useState(svgCache.get(key) ?? '');
  useEffect(() => {
    if (!value.trim()) { setSvg(''); return; }
    const hit = svgCache.get(key);
    if (hit !== undefined) { setSvg(hit); return; }
    let live = true;
    fetch(apiUrl(`/labels/barcode?sym=${encodeURIComponent(sym)}&ecc=${ecc}&value=${encodeURIComponent(value)}`), { credentials: 'same-origin' })
      .then((r) => r.text()).then((t) => { svgCache.set(key, t); if (live) setSvg(t); }).catch(() => undefined);
    return () => { live = false; };
  }, [key, sym, value, ecc]);
  return svg;
}

function FitSpan({ el, text, scale }: { el: El; text: string; scale: number }) {
  const box = useRef<HTMLDivElement>(null);
  const span = useRef<HTMLSpanElement>(null);
  const base = (el.size ?? 8) * PT * scale;
  useLayoutEffect(() => {
    const b = box.current, s = span.current;
    if (!b || !s) return;
    let size = base;
    s.style.fontSize = size + 'px';
    if (!el.fit) return;
    const min = 4 * PT * scale;
    for (let i = 0; i < 80 && size > min && (s.scrollHeight > b.clientHeight + 0.5 || s.scrollWidth > b.clientWidth + 0.5); i++) { size -= 0.25 * PT * scale; s.style.fontSize = size + 'px'; }
  }, [base, el.fit, el.font, el.bold, el.lh, el.ls, el.w, el.h, text, scale]);
  const clamp: CSSProperties = el.maxlines && !el.fit ? { display: '-webkit-box', WebkitBoxOrient: 'vertical', WebkitLineClamp: el.maxlines } : {};
  return (
    <div ref={box} style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column', overflow: 'hidden', justifyContent: el.valign === 'middle' ? 'center' : el.valign === 'bottom' ? 'flex-end' : 'flex-start' }}>
      <span ref={span} style={{ width: '100%', overflow: 'hidden', wordBreak: 'break-word', whiteSpace: 'pre-wrap', ...clamp }}>{text}</span>
    </div>
  );
}

/** What one element looks like at `scale` pixels per millimetre — the same picture the printout makes. */
export function ElementBody({ el, values, scale }: { el: El; values: Values; scale: number }) {
  const { session } = useSession();
  const val = fill(el.value ?? '', values);
  const sym = el.type === 'qr' ? 'qr' : el.sym ?? 'code128';
  const svg = useBarcode(sym, el.type === 'barcode' || el.type === 'qr' ? val : '', el.ecc ?? 'M');
  if (el.type === 'text') {
    let text = fill(el.text ?? '', values);
    if (el.upper) text = text.toUpperCase();
    const empty = text.trim() === '';
    return (
      <div style={{ width: '100%', height: '100%', fontFamily: FONT_STACK[el.font ?? 'Arial'], lineHeight: el.lh ?? 1.1, color: el.color ?? '#000', textAlign: el.align ?? 'left', fontWeight: el.bold ? 700 : 400, fontStyle: el.italic ? 'italic' : 'normal',
        textDecoration: el.underline ? 'underline' : 'none', background: el.bg || undefined, letterSpacing: el.ls ? `${el.ls * PT * scale}px` : undefined, opacity: empty ? 0.35 : 1 }}>
        <FitSpan el={el} text={empty ? (el.text ?? '') : text} scale={scale} />
      </div>
    );
  }
  if (el.type === 'barcode' || el.type === 'qr') {
    const shown = svg.includes('bc-bad') ? '' : val.trim();
    return (
      <div style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column' }}>
        <div style={{ flex: '1 1 auto', minHeight: 0 }} className="lbl-svg" dangerouslySetInnerHTML={{ __html: svg || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#eee"/></svg>' }} />
        {el.type === 'barcode' && el.showtext && shown && <div style={{ flex: '0 0 auto', textAlign: 'center', whiteSpace: 'nowrap', overflow: 'hidden', lineHeight: 1.1, fontSize: (el.tsize ?? 6) * PT * scale, fontFamily: FONT_STACK[el.font ?? 'Arial'], color: el.color ?? '#000' }}>{shown}</div>}
      </div>
    );
  }
  if (el.type === 'box') {
    return <div style={{ width: '100%', height: '100%', boxSizing: 'border-box', border: `${(el.border ?? 0) * scale}px solid ${el.color ?? '#000'}`, background: el.fill || undefined, borderRadius: (el.radius ?? 0) * scale }} />;
  }
  const logo = session?.tenant?.logo;
  return logo ? <img src={logo} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} /> : <div style={{ width: '100%', height: '100%', border: '1px dashed #aaa', color: '#888', fontSize: 10, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>Logo</div>;
}

type Handle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w';
const HANDLES: Handle[] = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
const HANDLE_POS: Record<Handle, CSSProperties> = {
  nw: { left: -4, top: -4, cursor: 'nwse-resize' }, n: { left: '50%', top: -4, marginLeft: -4, cursor: 'ns-resize' }, ne: { right: -4, top: -4, cursor: 'nesw-resize' }, e: { right: -4, top: '50%', marginTop: -4, cursor: 'ew-resize' },
  se: { right: -4, bottom: -4, cursor: 'nwse-resize' }, s: { left: '50%', bottom: -4, marginLeft: -4, cursor: 'ns-resize' }, sw: { left: -4, bottom: -4, cursor: 'nesw-resize' }, w: { left: -4, top: '50%', marginTop: -4, cursor: 'ew-resize' },
};

/** The label itself: static (previews, thumbnails) or editable (the designer drags and resizes the elements). */
export function LabelView({ w, h, elements, values, scale, selected, onSelect, onStart, guides, shadow = true }: {
  w: number; h: number; elements: El[]; values: Values; scale: number; selected?: string | null;
  onSelect?: (id: string | null) => void; onStart?: (el: El, mode: 'move' | Handle, e: RPointerEvent) => void; guides?: { x: number[]; y: number[] }; shadow?: boolean;
}) {
  const interactive = !!onStart;
  return (
    <div onPointerDown={() => onSelect?.(null)} style={{ position: 'relative', width: w * scale, height: h * scale, background: '#fff', boxShadow: shadow ? '0 0 0 1px #cfd4dc, 0 2px 8px rgba(0,0,0,.12)' : undefined, overflow: interactive ? 'visible' : 'hidden', flex: '0 0 auto' }}>
      <style>{'.lbl-svg svg{width:100%;height:100%;display:block}'}</style>
      <div style={{ position: 'absolute', inset: 0, overflow: 'hidden' }}>
        {elements.map((el) => (
          <div key={el.id} onPointerDown={interactive ? (e) => onStart!(el, 'move', e) : undefined}
            style={{ position: 'absolute', left: el.x * scale, top: el.y * scale, width: el.w * scale, height: el.h * scale, transform: el.rot ? `rotate(${el.rot}deg)` : undefined, overflow: 'hidden', cursor: interactive ? 'move' : undefined, touchAction: 'none' }}>
            <div style={{ pointerEvents: 'none', width: '100%', height: '100%' }}><ElementBody el={el} values={values} scale={scale} /></div>
          </div>
        ))}
      </div>
      {interactive && elements.map((el) => el.id === selected && (
        <div key={'sel' + el.id} style={{ position: 'absolute', left: el.x * scale, top: el.y * scale, width: el.w * scale, height: el.h * scale, transform: el.rot ? `rotate(${el.rot}deg)` : undefined, outline: '1.5px solid #4f46e5', pointerEvents: 'none' }}>
          {el.rot === 0 && HANDLES.map((hd) => (
            <span key={hd} onPointerDown={(e) => onStart!(el, hd, e)} style={{ position: 'absolute', width: 8, height: 8, background: '#fff', border: '1.5px solid #4f46e5', borderRadius: 2, pointerEvents: 'auto', touchAction: 'none', ...HANDLE_POS[hd] }} />
          ))}
        </div>
      ))}
      {interactive && guides && <>
        {guides.x.map((g, i) => <div key={'gx' + i} style={{ position: 'absolute', left: g * scale, top: -6, bottom: -6, width: 0, borderLeft: '1px dashed #e11d48', pointerEvents: 'none' }} />)}
        {guides.y.map((g, i) => <div key={'gy' + i} style={{ position: 'absolute', top: g * scale, left: -6, right: -6, height: 0, borderTop: '1px dashed #e11d48', pointerEvents: 'none' }} />)}
      </>}
    </div>
  );
}

/** A small picture of how the labels sit on the paper. */
export function SheetPreview({ w, h, page, skip = 0, count = 0 }: { w: number; h: number; page: Page; skip?: number; count?: number }) {
  const sheet = page.mode === 'sheet';
  const pw = sheet ? page.paper_w : page.cols * w + (page.cols - 1) * page.gap_x;
  const ph = sheet ? page.paper_h : h;
  const ml = sheet ? page.margin_left ?? Math.max(0, (pw - (page.cols * w + (page.cols - 1) * page.gap_x)) / 2) : 0;
  const mt = sheet ? page.margin_top ?? Math.max(0, (ph - (page.rows * h + (page.rows - 1) * page.gap_y)) / 2) : 0;
  const width = sheet ? 120 : 160, k = width / pw;
  const cells: ReactElement[] = [];
  for (let r = 0; r < page.rows; r++) for (let c = 0; c < page.cols; c++) {
    const i = r * page.cols + c;
    const used = i < skip, filled = i >= skip && i < skip + count;
    cells.push(<rect key={i} x={(ml + c * (w + page.gap_x)) * k} y={(mt + r * (h + page.gap_y)) * k} width={w * k} height={h * k} rx={1} fill={used ? '#f3f4f6' : filled ? '#c7d2fe' : '#fff'} stroke={used ? '#d1d5db' : '#6366f1'} strokeWidth={0.6} strokeDasharray={used ? '2 2' : undefined} />);
  }
  return <svg width={pw * k} height={ph * k} viewBox={`0 0 ${pw * k} ${ph * k}`} style={{ background: '#fff', boxShadow: '0 0 0 1px #cfd4dc', maxWidth: '100%' }}>{cells}</svg>;
}
