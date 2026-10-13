import type { CSSProperties, ReactNode } from 'react';
import { fdate } from '../lib/format';
import { amountWords, fillText, META_LABELS, type Block, type Cfg, type DocData, type Head } from '../lib/printdoc';
import { FONT_STACK } from '../pages/labels/render';

/** A printed document drawn from a designed layout. Used by the print pages and by the designer's preview. */
export default function DocView({ cfg, title, d, head, picked, onPick }: { cfg: Cfg; title: string; d: DocData; head: Head; picked?: string | null; onPick?: (id: string) => void }) {
  const { page, header } = cfg; const accent = page.accent;
  const titleColor = header.title_color === 'accent' ? accent : header.title_color;
  const base: CSSProperties = { fontFamily: FONT_STACK[page.font] ?? FONT_STACK.Arial, fontSize: `${page.fsize}pt`, color: page.color, lineHeight: 1.35, position: 'relative', textAlign: 'left' };
  const muted = '#6b7280';
  const rule = header.rule === 'none' ? 'none' : `${header.rule_w}px ${header.rule} ${accent}`;
  const logo = header.logo !== 'none' && head.logo ? <img src={head.logo} alt="" style={{ height: `${header.logo_h}mm`, maxWidth: '60mm', objectFit: 'contain' }} /> : null;
  const meta = header.meta.map((k) => [k, d.meta[k]] as const).filter(([, v]) => v);
  const company = (
    <div>
      <div style={{ fontSize: `${header.name_size}pt`, fontWeight: 600, lineHeight: 1.2 }}>{head.name}</div>
      {header.show_address && head.address ? <div style={{ whiteSpace: 'pre-line', fontSize: '.9em' }}>{head.address}</div> : null}
      {(header.show_phone && head.phone) || (header.show_email && head.email) || (header.show_website && head.website)
        ? <div style={{ color: muted, fontSize: '.9em' }}>{[header.show_phone && head.phone, header.show_email && head.email, header.show_website && head.website].filter(Boolean).join(' · ')}</div> : null}
      {header.show_tax && head.tax_no ? <div style={{ fontSize: '.9em' }}>Tax no. {head.tax_no}</div> : null}
    </div>
  );
  const titleBlock = (align: 'left' | 'right' | 'center') => (
    <div style={{ textAlign: align }}>
      <div style={{ fontSize: `${header.title_size}pt`, color: titleColor, lineHeight: 1.15 }}>{header.title || title}</div>
      <div style={{ fontWeight: 700 }}>{d.no}</div>
      <div style={{ color: muted, fontSize: '.9em' }}>{fdate(d.date)}</div>
      {meta.map(([k, v]) => <div key={k} style={{ color: muted, fontSize: '.9em' }}>{META_LABELS[k] ?? k}: {v}</div>)}
    </div>
  );

  const blockWrap = (b: Block, node: ReactNode) => (
    <div key={b.id} data-block={b.id} onClick={onPick ? () => onPick(b.id) : undefined}
      style={{ marginTop: '4mm', outline: picked === b.id ? `2px solid #4f46e5` : undefined, outlineOffset: 3, cursor: onPick ? 'pointer' : undefined, breakInside: b.type === 'signature' ? 'avoid' : undefined }}>{node}</div>
  );

  const renderBlock = (b: Block): ReactNode => {
    switch (b.type) {
      case 'parties': {
        const list = b.items.filter((p) => p.show && (d.parties[p.k] ?? []).length);
        if (!list.length) return null;
        return <div style={{ display: 'grid', gridTemplateColumns: `repeat(${b.cols}, minmax(0, 1fr))`, gap: '4mm 8mm' }}>{list.map((p) => (
          <div key={p.k}><div style={{ color: muted, fontSize: '.85em' }}>{p.label}</div>
            {d.parties[p.k].map((l, n) => <div key={n} style={n === 0 ? { fontWeight: 700 } : { fontSize: '.95em' }}>{l}</div>)}</div>))}</div>;
      }
      case 'items': {
        const cols = b.columns.filter((c) => c.show);
        const taxShown = b.columns.some((c) => c.k === 'tax' && c.show);
        const pad = b.compact ? '1px 5px' : '5px 7px';
        const grid = b.style === 'grid'; const lines = b.style === 'lines';
        const headBg = b.head_bg === 'accent' ? accent : b.head_bg === 'light' ? '#f3f4f6' : 'transparent';
        const headInk = b.head_bg === 'accent' ? '#fff' : page.color;
        const cell = (extra: CSSProperties): CSSProperties => ({ padding: pad, border: grid ? '1px solid #9ca3af' : undefined, borderBottom: grid ? '1px solid #9ca3af' : lines ? '1px solid #e5e7eb' : undefined, verticalAlign: 'top', ...extra });
        return (
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead><tr>
              {b.row_no ? <th style={cell({ background: headBg, color: headInk, width: '8mm', textAlign: 'left', borderBottom: b.style === 'plain' && b.head_bg === 'none' ? `1px solid ${accent}` : lines ? `2px solid ${accent}` : undefined })}>#</th> : null}
              {cols.map((c) => <th key={c.k} style={cell({ background: headBg, color: headInk, textAlign: c.align, width: c.w ? `${c.w}%` : undefined, fontWeight: 600, fontSize: '.88em', borderBottom: lines ? `2px solid ${accent}` : grid ? '1px solid #9ca3af' : b.style === 'plain' && b.head_bg === 'none' ? `1px solid ${accent}` : undefined })}>{c.label}</th>)}
            </tr></thead>
            <tbody>
              {d.rows.map((r, n) => (
                <tr key={n} style={{ background: b.zebra && n % 2 === 1 ? '#f9fafb' : undefined, color: r.part ? muted : undefined, breakInside: 'avoid' }}>
                  {b.row_no ? <td style={cell({})}>{r.part ? '' : d.rows.slice(0, n + 1).filter((x) => !x.part).length}</td> : null}
                  {cols.map((c) => {
                    const v = c.k === 'amount' && r.net !== undefined ? (taxShown ? r.net : r.tot) : r.cells[c.k] ?? '';
                    return <td key={c.k} style={cell({ textAlign: c.align })}>{v}</td>;
                  })}
                </tr>))}
              {!d.rows.length && <tr><td colSpan={cols.length + (b.row_no ? 1 : 0)} style={cell({ color: muted })}>No items.</td></tr>}
            </tbody>
          </table>
        );
      }
      case 'totals': {
        const rows = b.rows.filter((r) => r.show && d.totals[r.k] != null);
        if (!rows.length) return null;
        return (
          <>
            <div style={{ width: `${b.width}%`, marginLeft: b.side === 'right' ? 'auto' : 0 }}>
              <table style={{ width: '100%', borderCollapse: 'collapse' }}><tbody>
                {rows.map((r) => { const strong = r.k === 'total' || r.k === 'balance'; return (
                  <tr key={r.k} style={{ fontWeight: strong ? 700 : 400, borderTop: r.k === 'total' ? `1px solid ${accent}` : undefined }}>
                    <td style={{ padding: '2px 7px' }}>{r.label}</td><td style={{ padding: '2px 7px', textAlign: 'right' }}>{d.totals[r.k]}</td></tr>); })}
              </tbody></table>
            </div>
            {b.words && d.grand > 0 ? <div style={{ marginTop: '2mm', fontSize: '.9em' }}><b>Amount in words:</b> {amountWords(d.grand, b.words_prefix)}</div> : null}
          </>
        );
      }
      case 'notes': return d.notes ? <div style={{ fontSize: '.9em' }}><b>{b.title ? `${b.title}: ` : ''}</b>{d.notes}</div> : null;
      case 'bank': return head.bank ? <div style={{ fontSize: '.9em', whiteSpace: 'pre-line' }}>{b.title ? <><b>{b.title}</b><br /></> : null}{head.bank}</div> : null;
      case 'terms': return b.text.trim() ? <div style={{ fontSize: '.85em', color: muted, whiteSpace: 'pre-line' }}>{b.title ? <><b>{b.title}</b><br /></> : null}{fillText(b.text, d)}</div> : null;
      case 'text': return b.text.trim() || b.title ? (
        <div style={{ textAlign: b.align, fontSize: `${b.size}pt`, whiteSpace: 'pre-line', border: b.boxed ? '1px solid #9ca3af' : undefined, padding: b.boxed ? '3mm' : undefined }}>{b.title ? <div style={{ fontWeight: 700 }}>{b.title}</div> : null}{fillText(b.text, d)}</div>) : null;
      case 'signature': return (
        <div style={{ display: 'flex', gap: '8mm', marginTop: '4mm' }}>{b.boxes.map((l, n) => (
          <div key={n} style={{ flex: 1, paddingTop: `${b.space}mm` }}><div style={{ borderTop: '1px solid #333', paddingTop: 3, textAlign: 'center', fontSize: '.85em' }}>{l}</div></div>))}</div>);
      case 'footer': return b.text.trim() ? <div style={{ textAlign: b.align, color: muted, fontSize: '.85em', whiteSpace: 'pre-line' }}>{fillText(b.text, d)}</div> : null;
    }
  };

  return (
    <div style={base}>
      {page.wm_text ? (
        <div aria-hidden style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none', overflow: 'hidden' }}>
          <div style={{ transform: 'rotate(-30deg)', fontSize: '72pt', fontWeight: 800, letterSpacing: 6, color: accent, opacity: page.wm_opacity / 100, whiteSpace: 'nowrap' }}>{page.wm_text}</div>
        </div>) : null}
      <div style={{ position: 'relative' }}>
        {header.layout === 'center' ? (
          <div style={{ textAlign: 'center', paddingBottom: '3mm', borderBottom: rule }}>
            {logo && header.logo !== 'none' ? <div style={{ marginBottom: '2mm' }}>{logo}</div> : null}
            <div style={{ display: 'inline-block' }}>{company}</div>
            <div style={{ marginTop: '3mm' }}>{titleBlock('center')}</div>
          </div>
        ) : (
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '6mm', paddingBottom: '3mm', borderBottom: rule }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '4mm' }}>{header.logo === 'left' ? logo : null}{company}</div>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '4mm' }}>{titleBlock('right')}{header.logo === 'right' ? logo : null}</div>
          </div>
        )}
        {cfg.blocks.filter((b) => b.show).map((b) => { const n = renderBlock(b); return n ? blockWrap(b, n) : null; })}
      </div>
    </div>
  );
}
