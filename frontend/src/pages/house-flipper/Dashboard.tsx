import { useEffect, useState } from 'react';
import { Calculator, Home, PieChart, TrendingUp } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import KpiCard from '@/components/dashboard/KpiCard';
import PerformanceChart from '@/components/dashboard/PerformanceChart';
import ResumoFinanceiro from '@/components/dashboard/ResumoFinanceiro';
import ProjetosTable from '@/components/dashboard/ProjetosTable';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';
import LoadingSkeleton from '@/ui/LoadingSkeleton';
import DashboardMap from '@/maps/DashboardMap';

const money = (value: number) => (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function FlipperDashboard() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  function load() {
    api.get('/house-flipper/dashboard')
      .then(({ data: payload }) => setData(payload))
      .catch((exception: any) => setError(exception.response?.data?.message ?? 'Painel indisponível.'));
  }
  useEffect(() => { load(); }, []);
  if (error) return <ErrorState message={error} onRetry={load} />;
  if (!data) return <LoadingSkeleton label="Carregando dashboard de investimentos" />;
  const k = data.kpis;
  const estimated = data.estimated ?? {};
  const realized = data.realized ?? {};
  return (
    <div className="space-y-5">
      <PageHeader title="Análise de investimentos" subtitle={data.disclaimer} />
      <div className="grid md:grid-cols-2 xl:grid-cols-5 gap-4">
        <KpiCard titulo="Oportunidades analisadas" valor={String(k.opportunities ?? 0)} rodape={`${k.favorites ?? 0} favoritas`} Icon={Home} iconBg="bg-violet-100" iconColor="text-violet-700" />
        <KpiCard titulo="Projetos ativos" valor={String(k.active_projects ?? 0)} rodape={`${k.completed_projects ?? 0} concluídos`} Icon={Calculator} iconBg="bg-indigo-100" iconColor="text-indigo-700" />
        <KpiCard titulo="Investimento projetado" valor={money((estimated.acquisition ?? 0) + (estimated.renovation_budget ?? 0))} Icon={TrendingUp} iconBg="bg-sky-100" iconColor="text-sky-700" />
        <KpiCard titulo="Lucro estimado" valor={money(estimated.projected_profit ?? 0)} rodape="Premissas cadastradas" Icon={TrendingUp} iconBg="bg-emerald-100" iconColor="text-emerald-700" />
        <KpiCard titulo="ROI médio projetado" valor={`${(estimated.projected_roi_pct ?? 0).toLocaleString('pt-BR')}%`} rodape={`Realizado ${money(realized.profit ?? 0)}`} Icon={PieChart} iconBg="bg-orange-100" iconColor="text-orange-700" />
      </div>
      <div className="grid xl:grid-cols-3 gap-5">
        <div className="xl:col-span-2"><DashboardMap product="house_flipper" mode="opportunities" href="/app/house-flipper/mapa" /></div>
        <ResumoFinanceiro data={data.composition ?? []} />
      </div>
      <PerformanceChart data={data.performance ?? []} />
      <div className="grid xl:grid-cols-3 gap-4">
        <section className="xl:col-span-2 bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Oportunidades recentes</h2>
          {(data.recent_opportunities ?? []).map((row: any) => (
            <Link key={row.id} to="/app/house-flipper/oportunidades" className="flex justify-between py-2 border-t first:border-0 text-sm">
              <span>{row.title} · {row.city || '—'}</span>
              <span>{row.asking_price ? money(Number(row.asking_price)) : '—'}</span>
            </Link>
          ))}
          {!data.recent_opportunities?.length && <p className="text-sm text-gray-500">Nenhuma oportunidade salva.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4 space-y-3">
          <h2 className="font-semibold">Projeto em destaque</h2>
          {(data.projects ?? [])[0] ? (
            <>
              <p className="font-medium">{data.projects[0].nome}</p>
              <p className="text-sm text-slate-500">{data.projects[0].cidade} · {data.projects[0].status}</p>
              <p className="text-sm">Investido {money(data.projects[0].investido)} · Alvo {money(data.projects[0].estimado)}</p>
              <div className="flex flex-wrap gap-2 text-sm">
                <Link to="/app/house-flipper/projetos" className="text-sky-700 font-medium">Projeto</Link>
                <Link to="/app/house-flipper/obras" className="text-sky-700 font-medium">Obra</Link>
                <Link to="/app/house-flipper/projetos-3d" className="text-violet-700 font-medium">3D</Link>
                <Link to="/app/house-flipper/financeiro" className="text-sky-700 font-medium">Financeiro</Link>
              </div>
            </>
          ) : <p className="text-sm text-gray-500">Nenhum projeto em andamento.</p>}
        </section>
      </div>
      <div className="xl:col-span-2">{(data.projects ?? []).length ? <ProjetosTable projetos={data.projects} /> : null}</div>
    </div>
  );
}
