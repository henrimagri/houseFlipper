import { Activity, CheckCircle2, Clock3, Layers3, MessageSquare, ShieldAlert, Sparkles, XCircle } from 'lucide-react';
import { EmptyState, MetricCard, PlatformCard, SectionHeader, StatusBadge, metricValue } from './PlatformPrimitives';

/** These summaries never extrapolate a paginated response into platform totals. */
export function PageSummary({ rows, field, values }: { rows: any[]; field: string; values: string[] }) {
  return <section aria-label="Resumo dos registros desta página" className="sol-page-summary"><p>Somente os {rows.length} registros disponíveis nesta página · não são totais do período.</p><div className="sol-metrics">{values.map(value => <PlatformCard key={value}><StatusBadge value={value}/><strong>{rows.filter(row => row[field] === value).length}</strong><small>Nesta página</small></PlatformCard>)}</div></section>;
}

export function FunnelPanel({ data }: { data: any }) {
  if (!Array.isArray(data.stages) || !data.stages.length) return <PlatformCard><EmptyState title="Funil indisponível">Nenhuma etapa foi devolvida nesta consulta.</EmptyState></PlatformCard>;
  return <section className="sol-funnel" aria-label="Funil de conversão observado">{data.stages.map((stage: any, index: number) => <PlatformCard key={stage.stage}><small>ETAPA {index + 1}</small><h2>{stage.stage}</h2><strong>{metricValue(stage.value)}</strong><p>{stage.value == null ? 'Sem medição disponível; não representa zero.' : 'Contagem da coorte consultada.'}</p></PlatformCard>)}</section>;
}

const serviceNames:Record<string,string>={laravel:'Laravel',database:'Banco de dados',ollama:'Ollama',qdrant:'Qdrant',fastapi:'Worker FastAPI',storage:'Armazenamento',queue:'Fila'};
const healthLabels:Record<string,string>={version:'Versão',driver:'Driver',last_migration:'Última migration',http_status:'HTTP',free_bytes:'Espaço livre (bytes)',total_bytes:'Capacidade (bytes)',note:'Observação',reason:'Motivo'};
export function HealthGrid({ services }: { services: any }) {
  if (!services || !Object.keys(services).length) return <PlatformCard><EmptyState title="Checagens indisponíveis">O servidor não retornou estados dos serviços.</EmptyState></PlatformCard>;
  return <section className="sol-health-grid" aria-label="Saúde dos serviços">{Object.entries(services).map(([name, raw]: [string, any]) => {
    const service = raw && typeof raw === 'object' ? raw : { status: raw };
    return <PlatformCard key={name}><SectionHeader title={serviceNames[name]??name} action={<Activity size={17}/>}/><StatusBadge value={service.status}/><dl>{Object.entries(service).filter(([key]) => key !== 'status').map(([key, value]) => <div key={key}><dt>{healthLabels[key]??key}</dt><dd>{metricValue(value != null && typeof value === 'object' ? JSON.stringify(value) : value)}</dd></div>)}</dl></PlatformCard>;
  })}</section>;
}

export function AiSummary({ data }: { data: any }) {
  return <div className="sol-metrics sol-metrics-pair"><MetricCard title="Conversas" value={data.sessions} icon={<MessageSquare size={17}/>} detail="Contagem devolvida pelo servidor para este escopo."/><MetricCard title="Mensagens" value={data.messages} icon={<Sparkles size={17}/>} detail="Somente quantidade; nenhum conteúdo privado é exibido."/></div>;
}

export function JobsPanel({ rows, onOpen }: { rows: any[]; onOpen: (row: any) => void }) {
  return <><PageSummary rows={rows} field="status" values={['queued', 'processing', 'completed', 'failed']}/>{rows.length > 0 && <section className="sol-job-grid" aria-label="Jobs disponíveis">{rows.map((row, index) => {
    const progress = typeof row.progress === 'number' && Number.isFinite(row.progress) && row.progress >= 0 && row.progress <= 100 ? row.progress : null;
    const Icon = row.status === 'completed' ? CheckCircle2 : row.status === 'failed' ? XCircle : row.status === 'processing' ? Activity : Clock3;
    return <PlatformCard key={row.id ?? index}><SectionHeader title={'Job ' + (row.id ?? 'sem ID')} action={<Icon size={17}/>}/><StatusBadge value={row.status}/><dl><div><dt>Tenant</dt><dd>{metricValue(row.tenant_id)}</dd></div><div><dt>Método efetivo</dt><dd>{metricValue(row.actual_method)}</dd></div><div><dt>Duração (s)</dt><dd>{metricValue(row.duration_seconds)}</dd></div></dl>{progress == null ? <p className="sol-observation">Progresso não medido</p> : <div className="sol-job-progress"><label>Progresso informado <strong>{progress}%</strong><progress max={100} value={progress}/></label></div>}<button type="button" className="sol-button" onClick={() => onOpen(row)}>Inspecionar job <Layers3 size={13}/></button></PlatformCard>;
  })}</section>}<p className="sol-observation"><ShieldAlert size={13}/> Métricas de GPU indisponíveis nesta consulta. Estado concluído não comprova aprovação geométrica ou visual.</p></>;
}
