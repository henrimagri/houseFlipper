import type { FormEvent } from 'react';
import { SlidersHorizontal } from 'lucide-react';
export type PlatformFilters = { from: string; to: string; product: string; tenant_id: string; user_id: string; status: string; severity: string; search: string; plan: string };
export function presetPeriod(value: PlatformFilters, days: number, today = new Date()): PlatformFilters {
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate()); start.setDate(start.getDate() - days + 1);
  const format = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  return { ...value, from: format(start), to: format(today) };
}
export default function PlatformFilterBar({ allowed, value, onChange, onApply, onClear }: { allowed: string[]; value: PlatformFilters; onChange: (value: PlatformFilters) => void; onApply: (value: PlatformFilters) => void; onClear: () => void }) {
  if (!allowed.length) return null;
  const advanced = (['status', 'severity', 'search', 'plan'] as const).filter(key => allowed.includes(key));
  const labels: Record<keyof PlatformFilters, string> = { from: 'De', to: 'Até', product: 'Produto', tenant_id: 'Tenant', user_id: 'Usuário', status: 'Status', severity: 'Severidade', search: 'Pesquisar', plan: 'Plano' };
  const field = (key: keyof PlatformFilters) => <label key={key} className={'sol-filter-field sol-filter-' + key}>{labels[key]}{key === 'product' ? <select aria-label="Filtro produto" className="sol-input" value={value[key]} onChange={event => onChange({ ...value, [key]: event.target.value })}><option value="">Todos</option><option value="corretor">Corretor</option><option value="imobiliaria">Imobiliária</option><option value="house_flipper">House Flipper</option></select> : key === 'severity' ? <select aria-label="Filtro severidade" className="sol-input" value={value[key]} onChange={event => onChange({ ...value, [key]: event.target.value })}><option value="">Todas</option>{['info', 'low', 'medium', 'high', 'critical'].map(level => <option key={level}>{level}</option>)}</select> : <input aria-label={'Filtro ' + labels[key]} className="sol-input" type={key === 'from' || key === 'to' ? 'date' : 'text'} value={value[key]} onChange={event => onChange({ ...value, [key]: event.target.value })} />}</label>;
  const submit = (event: FormEvent) => { event.preventDefault(); onApply(value); };
  return <form onSubmit={submit} aria-label="Filtros administrativos" className="sol-filter-bar">
    <div className="sol-filter-main">{(['from', 'to', 'product', 'tenant_id', 'user_id'] as const).filter(key => allowed.includes(key)).map(field)}<div className="sol-filter-actions"><button className="sol-button sol-button-primary">Aplicar</button><button type="button" className="sol-button" onClick={onClear}>Limpar</button></div></div>
    <div className="sol-filter-bottom">{allowed.includes('from') && allowed.includes('to') && <div className="sol-quick-filters" aria-label="Atalhos de período">{[1, 7, 30, 90].map(days => { const next = presetPeriod(value, days); return <button key={days} type="button" aria-pressed={value.from === next.from && value.to === next.to} onClick={() => { onChange(next); onApply(next); }}>{days === 1 ? 'Hoje' : `${days} dias`}</button>; })}</div>}{advanced.length > 0 && <details className="sol-advanced-filters"><summary><SlidersHorizontal size={13} />Filtros avançados{advanced.some(key => value[key]) && <span aria-label="Há filtros avançados preenchidos"> ·</span>}</summary><div>{advanced.map(field)}</div></details>}</div>
  </form>;
}
