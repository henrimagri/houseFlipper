import { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { Building2, GitBranch, Inbox, Users, Zap, Trophy, ArrowUpRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import KpiCard from '@/components/dashboard/KpiCard';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';
import LoadingSkeleton from '@/ui/LoadingSkeleton';
import DashboardMap from '@/maps/DashboardMap';

export default function ImobiliariaDashboard() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  const [from, setFrom] = useState(() => new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  const [branchId, setBranchId] = useState('');
  const [agentId, setAgentId] = useState('');

  function load() {
    setError('');
    api.get('/imobiliaria/dashboard', { params: { from, to, branch_id: branchId || undefined, assigned_user_id: agentId || undefined } })
      .then(({ data: payload }) => setData(payload))
      .catch((exception: any) => setError(exception.response?.data?.message ?? 'Painel indisponível.'));
  }
  useEffect(() => { load(); }, [from, to, branchId, agentId]);

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (!data) return <LoadingSkeleton label="Carregando dashboard da imobiliária" />;
  const k = data.kpis;
  const empty = k.listings === 0 && k.contacts === 0 && k.open_deals === 0;
  return (
    <div className="fc-dashboard space-y-6">
      <PageHeader
        title={data.organization?.name || 'Imobiliária'} icon={Building2}
        subtitle="Painel executivo. Indicadores e gráficos usam o mesmo período e filtros."
        actions={(
          <div className="flex flex-wrap gap-2">
            <label className="text-xs">De<input className="input mt-1" type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
            <label className="text-xs">Até<input className="input mt-1" type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
            <label className="text-xs">Filial
              <select className="input mt-1" value={branchId} onChange={(e) => setBranchId(e.target.value)} disabled={data.filters.locked_branch}>
                <option value="">Todas</option>
                {(data.branches_index ?? []).map((branch: any) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
              </select>
            </label>
            <label className="text-xs">Corretor
              <select className="input mt-1" value={agentId} onChange={(e) => setAgentId(e.target.value)}>
                <option value="">Todos</option>
                {(data.members_index ?? []).map((member: any) => <option key={member.id} value={member.user_id}>{member.user?.name}</option>)}
              </select>
            </label>
          </div>
        )}
      />
      <div className="fc-dashboard-metrics">
        <KpiCard titulo="Imóveis cadastrados" valor={String(k.listings)} hint={data.definitions.listings} Icon={Building2} iconBg="bg-primary-50" iconColor="text-primary-600" details={[{label:'Disponíveis',value:String(k.available)}]} />
        <KpiCard titulo="Leads novos" valor={String(k.new_leads)} hint={data.definitions.new_leads} Icon={Users} iconBg="bg-primary-50" iconColor="text-primary-600" details={[{label:'Atendimentos abertos',value:String(k.open_tickets)}]} />
        <KpiCard titulo="Propostas abertas" valor={String(k.open_deals)} Icon={GitBranch} iconBg="bg-primary-50" iconColor="text-primary-600" details={[{label:'Negócios concluídos',value:String(k.closed_deals)}]} />
      </div>
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="bg-white dark:bg-slate-900 border rounded-2xl p-5">
          <h2 className="font-semibold mb-3"><GitBranch />Funil de vendas</h2>
          {empty ? <p className="text-sm text-gray-500">Sem negócios ainda. Crie um lead no CRM.</p> : (
            <div className="h-64"><ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.funnel}><XAxis dataKey="stage" /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="var(--fc-accent)" radius={6} /></BarChart>
            </ResponsiveContainer></div>
          )}
        </div>
        <div className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3"><Users />Origem dos leads</h2>
          {(data.lead_sources ?? []).length ? data.lead_sources.map((row: any) => (
            <div key={row.source} className="mt-5"><div className="mb-2 flex items-center justify-between gap-3 text-sm"><span>{row.source === 'internal' ? 'Cadastro interno' : row.source || 'Manual'}</span><strong className="font-medium tabular-nums">{row.total}</strong></div><progress aria-label={`Leads de ${row.source || 'origem manual'}`} className="fc-dashboard-progress" value={Number(row.total)} max={Math.max(1, data.lead_sources.reduce((sum: number, item: any) => sum + Number(item.total), 0))} /></div>
          )) : <p className="text-sm text-gray-500">Sem origens no período.</p>}
        </div>
      </div>
      <DashboardMap product="imobiliaria" href="/app/imobiliaria/mapa" />
      <div className="grid md:grid-cols-3 gap-4">
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2"><Inbox />Atendimentos urgentes</h2>
          {(data.urgent.unassigned_tickets ?? []).map((row: any) => <Link key={row.id} to="/app/imobiliaria/atendimentos" className="block text-sm py-1">{row.subject}</Link>)}
          {!data.urgent.unassigned_tickets?.length && <p className="text-sm text-gray-500">Nenhum atendimento sem responsável.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2"><Trophy />Ranking de corretores</h2>
          <p className="text-[11px] text-slate-500 mb-2">Critério: negócios fechados no período, depois leads atendidos.</p>
          {(data.by_agent ?? []).slice(0, 6).map((row: any) => (
            <p key={row.assigned_user_id || row.assigned_user?.id} className="text-sm py-1 border-t first:border-0">{row.assigned_user?.name || 'Sem responsável'} · {row.deals} negócios</p>
          ))}
          {!data.by_agent?.length && <p className="text-sm text-gray-500">Sem ranking no período.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2"><Zap />Ações rápidas</h2>
          <div className="flex flex-col gap-2 text-sm">
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/crm">Abrir funil <ArrowUpRight className="inline size-4" /></Link>
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/atendimentos">Central de atendimento <ArrowUpRight className="inline size-4" /></Link>
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/clientes">Cadastrar cliente <ArrowUpRight className="inline size-4" /></Link>
          </div>
        </section>
      </div>
    </div>
  );
}
