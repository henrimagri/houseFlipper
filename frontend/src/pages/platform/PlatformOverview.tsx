import { Activity, ArrowUpRight, Building2, CheckCircle2, CircleDollarSign, ShieldCheck, Users, Zap } from 'lucide-react';
import { ResponsiveContainer, AreaChart, Area, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';
import { EmptyState, MetricCard, PlatformCard, SectionHeader, metricValue as count } from './PlatformPrimitives';
export { EmptyState, MetricCard, OverviewSkeleton, PlatformCard, SectionHeader } from './PlatformPrimitives';

const productNames: Record<string, string> = { corretor: 'Corretor', imobiliaria: 'Imobiliária', house_flipper: 'House Flipper' };
const statusNames: Record<string, string> = { active: 'Ativas', trial: 'Em teste', cancelled: 'Canceladas', past_due: 'Inadimplentes' };

export default function PlatformOverview({ data }: { data: any }) {
  const subscriptionsKnown = Array.isArray(data.subscriptions);
  const securityKnown = Array.isArray(data.security);
  const activityKnown = Array.isArray(data.activity_series);
  const subscriptions: { product_slug: string; status: string; total: number }[] = subscriptionsKnown ? data.subscriptions : [];
  const security: { severity: string; total: number }[] = securityKnown ? data.security : [];
  const maxSignals = Math.max(1, ...security.map(row => Number(row.total)));
  const severityNames: Record<string, string> = { critical: 'Crítico', high: 'Alto', medium: 'Médio', low: 'Baixo', info: 'Informativo' };
  const signals = !securityKnown ? null : security.reduce((sum, row) => sum + Number(row.total), 0);
  return <div className="sol-theme sol-overview">
    <div className="sol-metrics">
      <MetricCard title="Usuários cadastrados" value={data.users?.total} icon={<Users size={18} />} detail="Base total no escopo selecionado" />
      <MetricCard title="Ativos observados · 24h" value={data.active_observed?.['1']} icon={<Activity size={18} />} detail="Requisições autenticadas bem-sucedidas" />
      <MetricCard title="Requests no período" value={data.requests} icon={<Zap size={18} />} detail="Sem comparação disponível" />
      <MetricCard title="Novos no período" value={data.new_users} icon={<Building2 size={18} />} detail="Cadastros no intervalo selecionado" />
    </div>
    <div className="sol-overview-main">
      <PlatformCard className="sol-chart-card"><SectionHeader title="Atividade da plataforma" description="Usuários ativos observados por dia · período selecionado" action={<span className="sol-chart-legend"><i />Usuários</span>} />
        {activityKnown && data.activity_series.length > 1 ? <div className="sol-chart"><ResponsiveContainer><AreaChart data={data.activity_series} margin={{ top: 16, right: 8, left: -20, bottom: 0 }}><defs><linearGradient id="sol-activity-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#9a7bff" stopOpacity={0.22} /><stop offset="100%" stopColor="#9a7bff" stopOpacity={0} /></linearGradient></defs><CartesianGrid vertical={false} stroke="rgba(255,255,255,.05)" /><XAxis dataKey="day" tickLine={false} axisLine={false} stroke="#9ca8bd" fontSize={11} tickMargin={14} /><YAxis allowDecimals={false} tickLine={false} axisLine={false} stroke="#9ca8bd" fontSize={11} /><Tooltip contentStyle={{ background: '#101624', border: '1px solid #303849', borderRadius: 12, color: '#f7f8fc', fontSize: 12 }} /><Area isAnimationActive={false} type="monotone" dataKey="total" name="Usuários" stroke="#a68aff" strokeWidth={2} fill="url(#sol-activity-fill)" dot={false} activeDot={{ r: 4, strokeWidth: 2, stroke: '#101624' }} /></AreaChart></ResponsiveContainer></div> : <EmptyState title={!activityKnown ? 'Atividade indisponível nesta consulta.' : data.activity_series.length === 1 ? 'Poucos dados no período para mostrar tendência.' : 'Sem observações no período.'} icon={<Activity size={22} />}>O histórico aparece conforme a telemetria é coletada.</EmptyState>}
      </PlatformCard>
      <PlatformCard className={'sol-security ' + (Number(data.critical_open) > 0 ? 'sol-security-alert' : '')}>
        <SectionHeader title="Segurança" description="Sinais observados, sem presumir incidentes" action={<ShieldCheck size={22} />} />
        <div className="sol-security-value"><strong>{count(data.critical_open)}</strong><span>incidentes críticos abertos<br /><small>Contagem global da plataforma</small></span></div>
        <div className="sol-security-bars">{['critical', 'high', 'medium', 'low', 'info'].map(severity => { const value = security.find(row => row.severity === severity)?.total; return <div key={severity}><span>{severityNames[severity]}</span><div className="sol-bar-track"><i style={{ width: `${Number(value ?? 0) / maxSignals * 100}%` }} /></div><b>{!securityKnown ? '—' : count(value ?? 0)}</b></div>; })}</div>
        <a className="sol-link" href="/platform/security">{securityKnown ? count(signals)+' sinais no período' : 'Dados de segurança indisponíveis nesta consulta'} <ArrowUpRight size={15} /></a>
      </PlatformCard>
    </div>
    <SectionHeader title="Produtos independentes" description="Assinaturas por produto e status · fotografia atual da base" />
    <div className="sol-products">{Object.entries(productNames).map(([slug, name]) => {
      const rows = subscriptions.filter(row => row.product_slug === slug);
      return <PlatformCard key={slug}><div className="sol-product-title"><span className="sol-icon"><Building2 size={18} /></span><h3>{name}</h3><a href={'/platform/subscriptions'} aria-label={'Consultar assinaturas de ' + name} className="sol-icon-link"><ArrowUpRight size={17} /></a></div><div className="sol-product-count">{!subscriptionsKnown ? 'Não medido' : count(rows.reduce((sum, row) => sum + Number(row.total), 0))}<span>assinaturas</span></div><div className="sol-product-statuses">{rows.length ? rows.map(row => <div key={row.status}><span>{statusNames[row.status] ?? row.status}</span><b>{count(row.total)}</b></div>) : <p>{!subscriptionsKnown ? 'Consulta indisponível.' : 'Nenhuma assinatura neste escopo.'}</p>}</div></PlatformCard>;
    })}</div>
    <div className="sol-overview-main">
      <PlatformCard><SectionHeader title="Agora na plataforma" description="Janelas de atividade observada; sem inferência de presença online" /><div className="sol-now">{[['Contas verificadas', data.users?.verified], ['Contas suspensas', data.users?.suspended], ['Ativos observados · 7 dias', data.active_observed?.['7']], ['Ativos observados · 30 dias', data.active_observed?.['30']]].map(([label, value]) => <div key={String(label)}><span>{label}</span><strong>{count(value)}</strong></div>)}</div></PlatformCard>
      <PlatformCard><SectionHeader title="Receita conciliada" action={<CircleDollarSign size={20} />} />{data.revenue?.mrr == null && data.revenue?.arr == null ? <EmptyState title="Ainda não há indicadores financeiros conciliados." icon={<CircleDollarSign size={22} />}>Consulte o ledger e a configuração do provedor para verificar a evidência de recebimentos recorrentes.<a className="sol-link" href="/platform/billing">Consultar billing <ArrowUpRight size={15} /></a></EmptyState> : <div className="sol-now"><div><span>MRR</span><strong>{count(data.revenue?.mrr)}</strong></div><div><span>ARR</span><strong>{count(data.revenue?.arr)}</strong></div></div>}</PlatformCard>
    </div>
    {data.readiness && <PlatformCard label="Prontidão operacional" className="sol-readiness"><span className="sol-icon"><CheckCircle2 size={20} /></span><div><h2>Prontidão operacional <strong>{data.readiness.score == null ? 'Não medido' : `${data.readiness.score}%`}</strong></h2><p>{data.readiness.blocking_checks ?? 'Não medido'} bloqueios restantes · Checklist de pesos iguais; não é certificação comercial.</p></div><a className="sol-link" href="/platform/readiness">Ver critérios <ArrowUpRight size={15} /></a></PlatformCard>}
    {data.revenue?.reason && <p className="sol-observation">Receita · {data.revenue.reason}</p>}
    <footer className="sol-observation">Atividade representa requisições autenticadas observadas, não prova de engajamento. Coleta desde: {data.observed_since ?? 'Não medido'}. Atualização da fonte: {data.checked_at ?? 'Não medido'}. Histórico anterior à coleta não foi inventado.</footer>
  </div>;
}
