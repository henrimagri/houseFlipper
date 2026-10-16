import { Home, TrendingUp, DollarSign, PieChart } from 'lucide-react';
import KpiCard from '@/components/dashboard/KpiCard';
import PerformanceChart from '@/components/dashboard/PerformanceChart';
import ResumoFinanceiro from '@/components/dashboard/ResumoFinanceiro';
import ProjetosTable from '@/components/dashboard/ProjetosTable';
import AtividadesRecentes from '@/components/dashboard/AtividadesRecentes';

export default function Dashboard() {
  return (
    <div className="space-y-5">
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <KpiCard
          titulo="Projetos em Andamento"
          valor="7"
          rodape="de 12 no total"
          Icon={Home}
          iconBg="bg-violet-100"
          iconColor="text-violet-600"
        />
        <KpiCard
          titulo="Lucro Líquido (em andamento)"
          valor="R$ 342.780,00"
          rodape="Margem média: 27,6%"
          rodapeColor="text-emerald-600"
          Icon={TrendingUp}
          iconBg="bg-emerald-100"
          iconColor="text-emerald-600"
        />
        <KpiCard
          titulo="Faturamento com Vendas"
          valor="R$ 2.317.450,00"
          rodape="de 8 imóveis vendidos"
          Icon={DollarSign}
          iconBg="bg-blue-100"
          iconColor="text-blue-600"
        />
        <KpiCard
          titulo="ROI Médio Geral"
          valor="28,4%"
          rodape="acima da meta (20%)"
          rodapeColor="text-orange-600"
          Icon={PieChart}
          iconBg="bg-orange-100"
          iconColor="text-orange-600"
        />
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <PerformanceChart />
        <ResumoFinanceiro />
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div className="xl:col-span-2">
          <ProjetosTable />
        </div>
        <AtividadesRecentes />
      </div>
    </div>
  );
}
