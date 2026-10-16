import { useState } from 'react';
import {
  CalendarDays,
  Filter,
  Download,
  TrendingUp,
  DollarSign,
  BarChart2,
  CheckCircle2,
  Tag,
  ChevronDown,
  MoreHorizontal,
  PlusCircle,
  Settings2,
} from 'lucide-react';
import {
  ResponsiveContainer,
  LineChart,
  Line,
  CartesianGrid,
  XAxis,
  YAxis,
  Tooltip,
  Legend,
  PieChart,
  Pie,
  Cell,
  BarChart,
  Bar,
} from 'recharts';

const fmtBrl = (v: number, comCentavos = true) =>
  v.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: comCentavos ? 2 : 0,
    maximumFractionDigits: comCentavos ? 2 : 0,
  });

const fmtKshort = (v: number) => `R$ ${(v / 1000).toFixed(0)}k`;

const meses = ['Jan/24', 'Fev/24', 'Mar/24', 'Abr/24', 'Mai/24', 'Jun/24', 'Jul/24', 'Ago/24', 'Set/24', 'Out/24', 'Nov/24', 'Dez/24'];

const serieFCL = meses.map((m, i) => {
  const fat = [120000, 145000, 165000, 180000, 240000, 230000, 280000, 360000, 320000, 380000, 410000, 405000][i];
  const cus = [80000, 105000, 115000, 130000, 160000, 175000, 200000, 240000, 220000, 250000, 270000, 260000][i];
  return { mes: m, Faturamento: fat, Custos: cus, 'Lucro Líquido': fat - cus };
});

const lucroPorMes = meses.map((m, i) => ({
  mes: m,
  valor: [38000, 42000, 48000, 52000, 78000, 65000, 88000, 145000, 100000, 130000, 142000, 110000][i],
}));

const composicaoCustos = [
  { nome: 'Custos de Reforma', valor: 987450, pct: 59.0, cor: '#10b981' },
  { nome: 'Compra de Imóveis', valor: 412500, pct: 24.6, cor: '#f59e0b' },
  { nome: 'Custos Operacionais', valor: 154320, pct: 9.2, cor: '#3b82f6' },
  { nome: 'Impostos e Taxas', valor: 78800, pct: 4.7, cor: '#f43f5e' },
  { nome: 'Outros Custos', valor: 42000, pct: 2.5, cor: '#a855f7' },
];

const projetos = [
  { nome: 'Casa Jardim Paulista', cod: '#2024-001', status: 'Concluído', investido: 235000, faturamento: 420000, lucro: 185000, roi: 34.2, margem: 44.0 },
  { nome: 'Sobrado Vila Madalena', cod: '#2024-002', status: 'Concluído', investido: 485000, faturamento: 895000, lucro: 310000, roi: 29.8, margem: 34.6 },
  { nome: 'Casa Campo Belo', cod: '#2024-003', status: 'Em andamento', investido: 290000, faturamento: 520000, lucro: 120000, roi: 20.7, margem: 23.1 },
  { nome: 'Casa Interlagos', cod: '#2024-004', status: 'Em andamento', investido: 360000, faturamento: 640000, lucro: 190000, roi: 26.4, margem: 29.7 },
  { nome: 'Sobrado Moema', cod: '#2024-005', status: 'Planejado', investido: 550000, faturamento: 920000, lucro: 210000, roi: 21.3, margem: 22.8 },
];

const statusCls = (s: string) => {
  if (s === 'Concluído') return 'bg-emerald-100 text-emerald-700';
  if (s === 'Em andamento') return 'bg-blue-100 text-blue-700';
  return 'bg-amber-100 text-amber-700';
};

type TabId = 'visao' | 'financeiro' | 'obras' | 'imoveis' | 'despesas' | 'fornecedores' | 'lucro' | 'impostos';

const tabs: { id: TabId; label: string }[] = [
  { id: 'visao', label: 'Visão Geral' },
  { id: 'financeiro', label: 'Financeiro' },
  { id: 'obras', label: 'Obras' },
  { id: 'imoveis', label: 'Imóveis' },
  { id: 'despesas', label: 'Despesas' },
  { id: 'fornecedores', label: 'Fornecedores' },
  { id: 'lucro', label: 'Lucro e ROI' },
  { id: 'impostos', label: 'Impostos' },
];

export default function Relatorios() {
  const [tab, setTab] = useState<TabId>('visao');

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Relatórios</h1>
          <p className="text-sm text-slate-500">
            Acompanhe o desempenho dos seus projetos e obras
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <CalendarDays className="h-4 w-4 text-slate-400" /> 01/01/2024 - 31/12/2024
          </button>
          <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <Filter className="h-4 w-4" /> Filtros
          </button>
          <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
            <Download className="h-4 w-4" /> Exportar relatório
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200 flex gap-6 overflow-x-auto">
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`text-sm pb-3 -mb-px border-b-2 whitespace-nowrap ${
              tab === t.id
                ? 'border-primary-600 text-primary-700 font-medium'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab !== 'visao' ? (
        <div className="bg-white rounded-xl border border-slate-200 p-10 text-center text-sm text-slate-500">
          Conteúdo desta aba em breve.
        </div>
      ) : (
        <>
          {/* KPIs */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <KpiRel label="Faturamento (Vendas)" valor={<span className="text-emerald-600">{fmtBrl(2317450)}</span>} delta="↑ 28,6% vs período anterior" icon={TrendingUp} cor="emerald" />
            <KpiRel label="Lucro Líquido" valor={<span className="text-emerald-600">{fmtBrl(642380)}</span>} delta="↑ 35,4% vs período anterior" icon={DollarSign} cor="primary" />
            <KpiRel label="ROI Médio" valor={<span className="text-primary-600">28,4%</span>} delta="↑ 4,7 p.p. vs período anterior" icon={BarChart2} cor="primary" />
            <KpiRel label="Projetos Concluídos" valor={<span className="text-amber-600">12</span>} delta="↑ 20% vs período anterior" icon={CheckCircle2} cor="amber" />
            <KpiRel label="Ticket Médio de Venda" valor={<span className="text-slate-900">{fmtBrl(193120.83)}</span>} delta="↑ 12,1% vs período anterior" icon={Tag} cor="blue" />
          </div>

          {/* Linha + Donut */}
          <div className="grid grid-cols-1 xl:grid-cols-[1fr_460px] gap-5">
            <div className="bg-white rounded-xl border border-slate-200 p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-semibold text-slate-900">Faturamento, Custos e Lucro Líquido</h3>
                <SelectFalso titulo="Mensal" pequeno />
              </div>
              <div className="flex items-center gap-5 text-xs text-slate-500 mb-2">
                <Legenda cor="#10b981" label="Faturamento" />
                <Legenda cor="#ef4444" label="Custos" />
                <Legenda cor="#3b82f6" label="Lucro Líquido" />
              </div>
              <div className="h-72">
                <ResponsiveContainer>
                  <LineChart data={serieFCL} margin={{ top: 5, right: 10, left: 0, bottom: 5 }}>
                    <CartesianGrid stroke="#eef2f7" strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="mes" axisLine={false} tickLine={false} tick={{ fontSize: 11, fill: '#94a3b8' }} />
                    <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 11, fill: '#94a3b8' }} tickFormatter={fmtKshort} width={56} />
                    <Tooltip formatter={(v: number) => fmtBrl(v)} />
                    <Legend wrapperStyle={{ display: 'none' }} />
                    <Line type="monotone" dataKey="Faturamento" stroke="#10b981" strokeWidth={2} dot={{ r: 3, fill: '#10b981' }} />
                    <Line type="monotone" dataKey="Custos" stroke="#ef4444" strokeWidth={2} dot={{ r: 3, fill: '#ef4444' }} />
                    <Line type="monotone" dataKey="Lucro Líquido" stroke="#3b82f6" strokeWidth={2} dot={{ r: 3, fill: '#3b82f6' }} />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 p-5">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-semibold text-slate-900">Composição dos Custos</h3>
                <SelectFalso titulo="Todos os projetos" pequeno />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-[180px_1fr] items-center gap-4">
                <div className="relative h-44">
                  <ResponsiveContainer>
                    <PieChart>
                      <Pie data={composicaoCustos} dataKey="valor" innerRadius={55} outerRadius={80} paddingAngle={1} stroke="none">
                        {composicaoCustos.map((c) => (
                          <Cell key={c.nome} fill={c.cor} />
                        ))}
                      </Pie>
                    </PieChart>
                  </ResponsiveContainer>
                  <div className="absolute inset-0 grid place-items-center pointer-events-none">
                    <div className="text-center">
                      <div className="text-base font-bold text-slate-900">R$ 1.675.070,00</div>
                      <div className="text-[10px] text-slate-500">Total de Custos</div>
                    </div>
                  </div>
                </div>
                <div className="space-y-2">
                  {composicaoCustos.map((c) => (
                    <div key={c.nome} className="flex items-center gap-2 text-xs">
                      <span className="h-2 w-2 rounded-full" style={{ background: c.cor }} />
                      <span className="text-slate-600 flex-1 truncate">{c.nome}</span>
                      <span className="text-slate-900 font-medium whitespace-nowrap">{fmtBrl(c.valor)}</span>
                      <span className="text-slate-400 w-12 text-right">{c.pct.toString().replace('.', ',')}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Tabela + Barras/Resumo */}
          <div className="grid grid-cols-1 xl:grid-cols-[1fr_460px] gap-5">
            <div className="bg-white rounded-xl border border-slate-200 p-5">
              <h3 className="text-sm font-semibold text-slate-900 mb-3">Desempenho dos Projetos</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-xs uppercase tracking-wide text-slate-500">
                      <th className="text-left py-2 font-medium">Projeto / Imóvel</th>
                      <th className="text-left py-2 font-medium">Status</th>
                      <th className="text-left py-2 font-medium">Investido</th>
                      <th className="text-left py-2 font-medium">Faturamento</th>
                      <th className="text-left py-2 font-medium">Lucro Líquido</th>
                      <th className="text-left py-2 font-medium">ROI</th>
                      <th className="text-left py-2 font-medium">Margem de Lucro</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {projetos.map((p) => (
                      <tr key={p.cod} className="hover:bg-slate-50">
                        <td className="py-3 pr-3">
                          <div className="flex items-center gap-3">
                            <div className="h-9 w-12 rounded-md bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500 shrink-0">
                              <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor">
                                <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                              </svg>
                            </div>
                            <div>
                              <div className="font-medium text-slate-900">{p.nome}</div>
                              <div className="text-[11px] text-slate-500">{p.cod}</div>
                            </div>
                          </div>
                        </td>
                        <td className="py-3 pr-3">
                          <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusCls(p.status)}`}>
                            {p.status}
                          </span>
                        </td>
                        <td className="py-3 pr-3 text-slate-700">{fmtBrl(p.investido)}</td>
                        <td className="py-3 pr-3 text-slate-700">{fmtBrl(p.faturamento)}</td>
                        <td className="py-3 pr-3 text-emerald-600 font-medium">{fmtBrl(p.lucro)}</td>
                        <td className="py-3 pr-3 text-emerald-600 font-medium">
                          {p.roi.toString().replace('.', ',')}%
                        </td>
                        <td className="py-3 pr-3 text-slate-700">
                          {p.margem.toString().replace('.', ',')}%
                        </td>
                        <td className="py-3 text-slate-400">
                          <MoreHorizontal className="h-4 w-4" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <button className="mt-4 text-sm text-primary-600 font-medium hover:text-primary-700">
                Ver relatório completo de projetos →
              </button>
            </div>

            <div className="space-y-5">
              <div className="bg-white rounded-xl border border-slate-200 p-5">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="text-sm font-semibold text-slate-900">Lucro Líquido por Mês</h3>
                  <SelectFalso titulo="Mensal" pequeno />
                </div>
                <div className="h-44">
                  <ResponsiveContainer>
                    <BarChart data={lucroPorMes} margin={{ top: 5, right: 5, left: 0, bottom: 5 }}>
                      <CartesianGrid stroke="#eef2f7" strokeDasharray="3 3" vertical={false} />
                      <XAxis dataKey="mes" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#94a3b8' }} interval={0} />
                      <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: '#94a3b8' }} tickFormatter={fmtKshort} width={50} />
                      <Tooltip formatter={(v: number) => fmtBrl(v)} />
                      <Bar dataKey="valor" fill="#7c3aed" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>

              <div className="bg-white rounded-xl border border-slate-200 p-5">
                <h3 className="text-sm font-semibold text-slate-900 mb-4">Resumo Financeiro do Período</h3>
                <div className="grid grid-cols-1 sm:grid-cols-[1fr_120px] gap-4 items-center">
                  <div className="space-y-2 text-sm">
                    <LinhaResumo label="Faturamento (Vendas)" valor={fmtBrl(2317450)} cor="text-emerald-600" />
                    <LinhaResumo label="(-) Custos Totais" valor={fmtBrl(1675070)} cor="text-red-600" />
                    <LinhaResumo label="(=) Lucro Bruto" valor={fmtBrl(642380)} cor="text-emerald-600 font-semibold" />
                    <LinhaResumo label="(-) Despesas Operacionais" valor={fmtBrl(98250)} cor="text-red-600" />
                    <div className="border-t border-slate-100 pt-2">
                      <LinhaResumo label="(=) Lucro Líquido" valor={fmtBrl(544130)} cor="text-emerald-600 font-semibold" />
                    </div>
                  </div>
                  <RingMargem pct={23.5} />
                </div>
                <button className="mt-4 text-sm text-primary-600 font-medium hover:text-primary-700">
                  Ver relatório financeiro completo →
                </button>
              </div>
            </div>
          </div>

          {/* Card relatórios personalizados */}
          <div className="bg-white rounded-xl border border-slate-200 px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 rounded-lg bg-primary-50 text-primary-600 grid place-items-center">
                <Settings2 className="h-5 w-5" />
              </div>
              <div>
                <div className="text-sm font-semibold text-slate-900">Relatórios personalizados</div>
                <div className="text-xs text-slate-500">
                  Crie relatórios personalizados com os indicadores que realmente importam para o seu negócio.
                </div>
              </div>
            </div>
            <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
              <PlusCircle className="h-4 w-4" /> Criar novo relatório
            </button>
          </div>
        </>
      )}
    </div>
  );
}

function KpiRel({
  label,
  valor,
  delta,
  icon: Icon,
  cor,
}: {
  label: string;
  valor: React.ReactNode;
  delta: string;
  icon: typeof TrendingUp;
  cor: 'primary' | 'emerald' | 'amber' | 'blue';
}) {
  const map = {
    primary: 'bg-primary-50 text-primary-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    blue: 'bg-blue-50 text-blue-600',
  } as const;
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="text-xs text-slate-500">{label}</div>
        <div className={`h-7 w-7 rounded-lg grid place-items-center ${map[cor]}`}>
          <Icon className="h-3.5 w-3.5" />
        </div>
      </div>
      <div className="mt-2 text-xl font-bold">{valor}</div>
      <div className="text-[11px] text-emerald-600 mt-1">{delta}</div>
    </div>
  );
}

function SelectFalso({ titulo, pequeno }: { titulo: string; pequeno?: boolean }) {
  return (
    <button
      className={`inline-flex items-center gap-2 ${
        pequeno ? 'px-2 py-1 text-xs' : 'px-3 py-2 text-sm'
      } rounded-lg bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100`}
    >
      <span>{titulo}</span>
      <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
    </button>
  );
}

function Legenda({ cor, label }: { cor: string; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className="h-2 w-2 rounded-full" style={{ background: cor }} />
      {label}
    </span>
  );
}

function LinhaResumo({ label, valor, cor }: { label: string; valor: string; cor?: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-slate-500">{label}</span>
      <span className={`text-sm ${cor ?? 'text-slate-900'}`}>{valor}</span>
    </div>
  );
}

function RingMargem({ pct }: { pct: number }) {
  const dash = (pct / 100) * 251.2; // 2*pi*40
  return (
    <div className="relative h-28 w-28 mx-auto">
      <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90">
        <circle cx="50" cy="50" r="40" fill="none" stroke="#eef2f7" strokeWidth="10" />
        <circle
          cx="50"
          cy="50"
          r="40"
          fill="none"
          stroke="#7c3aed"
          strokeWidth="10"
          strokeLinecap="round"
          strokeDasharray={`${dash} 251.2`}
        />
      </svg>
      <div className="absolute inset-0 grid place-items-center text-center">
        <div>
          <div className="text-lg font-bold text-slate-900">{pct.toString().replace('.', ',')}%</div>
          <div className="text-[10px] text-slate-500">Margem Líquida</div>
        </div>
      </div>
    </div>
  );
}
