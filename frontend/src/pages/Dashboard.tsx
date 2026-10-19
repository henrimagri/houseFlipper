import { useEffect, useState } from 'react';
import { Home, TrendingUp, DollarSign, PieChart } from 'lucide-react';
import api from '@/lib/api';
import KpiCard from '@/components/dashboard/KpiCard';
import PerformanceChart from '@/components/dashboard/PerformanceChart';
import ResumoFinanceiro from '@/components/dashboard/ResumoFinanceiro';
import ProjetosTable from '@/components/dashboard/ProjetosTable';
import AtividadesRecentes from '@/components/dashboard/AtividadesRecentes';

type DashboardData = {
  kpis: { active_projects: number; total_projects: number; realized_profit: number; sales_revenue: number; sold_properties: number; realized_roi: number; total_invested: number };
  performance: { mes: string; investimento: number; faturamento: number; lucro: number }[];
  composition: { name: string; valor: number; color: string }[];
  projects: { id: string; nome: string; numero: string; cidade: string; investido: number; estimado: number; roi: number | null; status: string; statusColor: string }[];
  activities: { titulo: string; desc: string; tempo: string }[];
};
const money = (value: number) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Dashboard() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState('');
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    setError('');
    api.get<DashboardData>('/dashboard', { signal: controller.signal })
      .then(response => setData(response.data))
      .catch(() => { if (!controller.signal.aborted) setError('Não foi possível carregar o painel.'); });
    return () => controller.abort();
  }, [attempt]);
  if (error) return <div role="alert" className="p-5 rounded-xl bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300">{error} <button className="underline" onClick={() => setAttempt(value => value + 1)}>Tentar novamente</button></div>;
  if (!data) return <p role="status">Carregando painel...</p>;
  const k = data.kpis;
  return <div className="space-y-5">
    <p className="text-sm text-gray-500 dark:text-slate-400">Imóveis, obras e despesas cadastrados na sua empresa. O lucro e o ROI realizados consideram vendas concluídas; a movimentação mensal usa a data de aquisição, despesa e fechamento da venda, por competência.</p>
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      <KpiCard titulo="Projetos em andamento" valor={String(k.active_projects)} rodape={`de ${k.total_projects} cadastrados`} Icon={Home} iconBg="bg-violet-100 dark:bg-violet-900" iconColor="text-violet-600 dark:text-violet-400" />
      <KpiCard titulo="Lucro líquido realizado" valor={money(k.realized_profit)} rodape="Vendas fechadas e escrituradas" Icon={TrendingUp} iconBg="bg-emerald-100 dark:bg-emerald-900" iconColor="text-emerald-600 dark:text-emerald-400" />
      <KpiCard titulo="Faturamento com vendas" valor={money(k.sales_revenue)} rodape={`${k.sold_properties} imóveis vendidos`} Icon={DollarSign} iconBg="bg-blue-100 dark:bg-blue-900" iconColor="text-blue-600 dark:text-blue-400" />
      <KpiCard titulo="ROI realizado consolidado" valor={`${k.realized_roi.toLocaleString('pt-BR')}%`} rodape="Lucro / custo dos imóveis vendidos" Icon={PieChart} iconBg="bg-orange-100 dark:bg-orange-900" iconColor="text-orange-600 dark:text-orange-400" />
    </div>
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-5"><PerformanceChart data={data.performance} /><ResumoFinanceiro data={data.composition} /></div>
    <div className="grid grid-cols-1 xl:grid-cols-3 gap-5"><div className="xl:col-span-2">{data.projects.length ? <ProjetosTable projetos={data.projects} /> : <p className="p-5 bg-white dark:bg-slate-900 rounded-xl border">Nenhum projeto em andamento.</p>}</div>{data.activities.length ? <AtividadesRecentes atividades={data.activities} /> : <p className="p-5 bg-white dark:bg-slate-900 rounded-xl border">Nenhuma despesa registrada.</p>}</div>
  </div>;
}
