import { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { Building2, GitBranch, Inbox, Users } from 'lucide-react';
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
    <div className="space-y-5">
      <PageHeader
        title={data.organization?.name || 'Imobiliária'}
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
      <div className="grid md:grid-cols-2 xl:grid-cols-6 gap-4">
        <KpiCard titulo="Imóveis cadastrados" valor={String(k.listings)} hint={data.definitions.listings} Icon={Building2} iconBg="bg-indigo-100" iconColor="text-indigo-700" />
        <KpiCard titulo="Disponíveis" valor={String(k.available)} Icon={Building2} iconBg="bg-sky-100" iconColor="text-sky-700" />
        <KpiCard titulo="Leads novos" valor={String(k.new_leads)} hint={data.definitions.new_leads} Icon={Users} iconBg="bg-sky-100" iconColor="text-sky-700" />
        <KpiCard titulo="Atendimentos abertos" valor={String(k.open_tickets)} hint={data.definitions.open_tickets} Icon={Inbox} iconBg="bg-amber-100" iconColor="text-amber-700" />
        <KpiCard titulo="Propostas abertas" valor={String(k.open_deals)} Icon={GitBranch} iconBg="bg-violet-100" iconColor="text-violet-700" />
        <KpiCard titulo="Negócios concluídos" valor={String(k.closed_deals)} hint={data.definitions.conversion} Icon={GitBranch} iconBg="bg-emerald-100" iconColor="text-emerald-700" />
      </div>
      <div className="grid xl:grid-cols-3 gap-4">
        <div className="xl:col-span-2 bg-white dark:bg-slate-900 border rounded-2xl p-4 h-80">
          <h2 className="font-semibold mb-3">Funil de vendas</h2>
          {empty ? <p className="text-sm text-gray-500">Sem negócios ainda. Crie um lead no CRM.</p> : (
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.funnel}><XAxis dataKey="stage" /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="#4f46e5" radius={6} /></BarChart>
            </ResponsiveContainer>
          )}
        </div>
        <div className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Origem dos leads</h2>
          {(data.lead_sources ?? []).length ? data.lead_sources.map((row: any) => (
            <p key={row.source} className="text-sm py-1 border-t first:border-0">{row.source || 'manual'} · {row.total}</p>
          )) : <p className="text-sm text-gray-500">Sem origens no período.</p>}
        </div>
      </div>
      <DashboardMap product="imobiliaria" href="/app/imobiliaria/mapa" />
      <div className="grid md:grid-cols-3 gap-4">
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2">Atendimentos urgentes</h2>
          {(data.urgent.unassigned_tickets ?? []).map((row: any) => <Link key={row.id} to="/app/imobiliaria/atendimentos" className="block text-sm py-1">{row.subject}</Link>)}
          {!data.urgent.unassigned_tickets?.length && <p className="text-sm text-gray-500">Nenhum atendimento sem responsável.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2">Ranking de corretores</h2>
          <p className="text-[11px] text-slate-500 mb-2">Critério: negócios fechados no período, depois leads atendidos.</p>
          {(data.by_agent ?? []).slice(0, 6).map((row: any) => (
            <p key={row.assigned_user_id || row.assigned_user?.id} className="text-sm py-1 border-t first:border-0">{row.assigned_user?.name || 'Sem responsável'} · {row.deals} negócios</p>
          ))}
          {!data.by_agent?.length && <p className="text-sm text-gray-500">Sem ranking no período.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-2">Ações rápidas</h2>
          <div className="flex flex-col gap-2 text-sm">
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/crm">Abrir funil</Link>
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/atendimentos">Central de atendimento</Link>
            <Link className="text-sky-700 font-medium" to="/app/imobiliaria/clientes">Cadastrar cliente</Link>
          </div>
        </section>
      </div>
    </div>
  );
}
