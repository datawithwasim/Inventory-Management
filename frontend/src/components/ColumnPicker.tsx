import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { get, legacyUrl, post } from '../lib/api';
import { Dropdown } from './ui';

type Cols = { options: Record<string, string>; visible: string[] };

export default function ColumnPicker({ list, entity, onApplied }: { list: string; entity: string; onApplied: () => void }) {
  const q = useQuery({ queryKey: ['columns', list], queryFn: async () => (await get(`/columns/${list}`)) as unknown as Cols });
  const [sel, setSel] = useState<string[] | null>(null);
  const cur = sel ?? q.data?.visible ?? [];
  const apply = async (reset = false, close?: () => void) => { await post('/prefs/columns', reset ? { list, reset: 1 } : { list, cols: cur }); setSel(null); await q.refetch(); onApplied(); close?.(); };
  return (
    <Dropdown button={() => <button type="button" className="btn btn-sm col-picker-btn" title="Choose columns" aria-label="Columns"><i className="bi bi-layout-three-columns" /></button>} menuClass="p-3 text-start">
      {(close) => <div style={{ minWidth: 240 }}>
        <div className="fw-semibold small mb-2">Show these columns</div>
        {Object.entries(q.data?.options ?? {}).map(([k, label]) => (
          <div className="form-check mb-1" key={k}><input className="form-check-input" type="checkbox" id={`col-${k}`} checked={cur.includes(k)} onChange={(e) => setSel(e.target.checked ? [...cur, k] : cur.filter((x) => x !== k))} /><label className="form-check-label" htmlFor={`col-${k}`}>{label}</label></div>
        ))}
        <a className="d-block small mt-3 pt-2 border-top" href={legacyUrl(`/settings/formdesign?form=${entity}&return=${encodeURIComponent(location.pathname)}`)}><i className="bi bi-plus-square me-1" />Add or remove your own columns</a>
        <div className="d-flex gap-2 mt-3"><button className="btn btn-sm btn-primary flex-grow-1" onClick={() => apply(false, close)}>Apply</button><button className="btn btn-sm btn-outline-secondary" onClick={() => apply(true, close)}>Reset</button></div>
      </div>}
    </Dropdown>
  );
}

