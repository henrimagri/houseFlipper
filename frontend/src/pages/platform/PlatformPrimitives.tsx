import type { ReactNode } from 'react';
import { Layers3 } from 'lucide-react';

export function metricValue(value: unknown): string {
  if (value == null) return 'Não medido';
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
  if (typeof value === 'number') return Number.isFinite(value) ? value.toLocaleString('pt-BR') : 'Não medido';
  return String(value);
}

export function PlatformCard({ children, className = '', label }: { children: ReactNode; className?: string; label?: string }) {
  return <section aria-label={label} className={'sol-card ' + className}>{children}</section>;
}

export function SectionHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return <div className="sol-section-header"><div><h2>{title}</h2>{description && <p>{description}</p>}</div>{action}</div>;
}

export function EmptyState({ title, children, icon = <Layers3 size={22} /> }: { title: string; children?: ReactNode; icon?: ReactNode }) {
  return <div className="sol-empty"><span className="sol-icon">{icon}</span><h3>{title}</h3>{children && <div>{children}</div>}</div>;
}

export function MetricCard({ title, value, icon, detail }: { title: string; value: unknown; icon: ReactNode; detail: string }) {
  return <PlatformCard className="sol-metric"><div className="sol-metric-heading"><span>{title}</span><span className="sol-icon">{icon}</span></div><strong>{metricValue(value)}</strong><p>{detail}</p></PlatformCard>;
}

export function OverviewSkeleton() {
  return <div className="sol-theme sol-overview" role="status" aria-label="Carregando visão geral"><span className="sr-only">Consultando registros…</span><div className="sol-metrics">{[0, 1, 2, 3].map(key => <PlatformCard key={key}><div className="sol-skeleton sol-skeleton-label" /><div className="sol-skeleton sol-skeleton-value" /><div className="sol-skeleton sol-skeleton-label" /></PlatformCard>)}</div><div className="sol-overview-main">{[0, 1].map(key => <PlatformCard key={key}><div className="sol-skeleton sol-skeleton-label" /><div className="sol-skeleton sol-skeleton-chart" /></PlatformCard>)}</div></div>;
}

export function ModuleSkeleton() {
  return <PlatformCard className="p-5"><div role="status" aria-label="Carregando módulo"><span className="sr-only">Consultando registros…</span><div className="sol-skeleton sol-skeleton-label" /><div className="sol-skeleton sol-skeleton-chart" /></div></PlatformCard>;
}

const statusLabels: Record<string, string> = {
  approved: 'Aprovado', partial: 'Parcial', blocked: 'Bloqueado', unconfigured: 'Não configurado', timeout: 'Tempo esgotado', disabled: 'Desabilitado', delayed: 'Atrasado',
  info: 'Informativo', low: 'Baixo', medium: 'Médio', high: 'Alto', critical: 'Crítico', active: 'Ativo', trial: 'Em teste', cancelled: 'Cancelado', suspended: 'Suspenso', past_due: 'Inadimplente',
  queued: 'Na fila', processing: 'Processando', completed: 'Concluído', failed: 'Falhou', cancelled_by_user: 'Cancelado pelo usuário', online: 'Online', offline: 'Offline', degraded: 'Degradado', not_configured: 'Não configurado',
  open: 'Aberto', investigating: 'Em investigação', mitigated: 'Mitigado', resolved: 'Resolvido', false_positive: 'Falso positivo', confirmed: 'Confirmado', probable: 'Provável', expected: 'Esperado',
};

export function StatusBadge({ value }: { value: unknown }) {
  const key = String(value);
  const numeric = typeof value === 'number' ? value : typeof value === 'string' && /^\d{3}$/.test(value) ? Number(value) : null;
  const http = numeric != null && numeric >= 100 && numeric <= 599;
  const variant = ['critical', 'high', 'failed', 'offline', 'suspended', 'past_due', 'blocked'].includes(key) || (http && numeric >= 400) ? 'bad'
    : ['online', 'active', 'completed', 'resolved', 'approved'].includes(key) || (http && numeric >= 200 && numeric < 300) ? 'good'
      : ['partial', 'medium', 'delayed', 'degraded', 'timeout', 'investigating', 'probable'].includes(key) || (http && numeric >= 300) ? 'warn' : 'muted';
  return <span className={'sol-badge sol-badge--' + variant}>{http ? `HTTP ${numeric}` : statusLabels[key] ?? metricValue(value)}</span>;
}
