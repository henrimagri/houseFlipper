import { useState } from 'react';
import {
  ArrowLeft,
  ChevronDown,
  MoreHorizontal,
  Maximize2,
  BedDouble,
  Bath,
  Car,
  CalendarDays,
  DollarSign,
  Wrench,
  Tag,
  TrendingUp,
  Percent,
  FileText,
  Save,
  Pencil,
  CheckCircle2,
  Circle,
  Info,
} from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';

type CenarioId = 'pessimista' | 'realista' | 'otimista';

const cenarios: Record<
  CenarioId,
  { titulo: string; venda: number; lucro: number; roi: number; margem: number; payback: string; tema: string }
> = {
  pessimista: {
    titulo: 'Pessimista',
    venda: 780000,
    lucro: 128417,
    roi: 22.1,
    margem: 16.5,
    payback: '10,8 meses',
    tema: 'red',
  },
  realista: {
    titulo: 'Realista',
    venda: 850000,
    lucro: 198417,
    roi: 34.2,
    margem: 23.3,
    payback: '7,6 meses',
    tema: 'emerald',
  },
  otimista: {
    titulo: 'Otimista',
    venda: 920000,
    lucro: 268417,
    roi: 46.2,
    margem: 29.2,
    payback: '5,5 meses',
    tema: 'emerald',
  },
};

const composicao = [
  { nome: 'Valor do Imóvel', valor: 420000, pct: 72.4, cor: '#1e40af' },
  { nome: 'Custo de Reforma', valor: 98750, pct: 17.0, cor: '#3b82f6' },
  { nome: 'Custos de Compra', valor: 32300, pct: 5.6, cor: '#60a5fa' },
  { nome: 'Custos de Venda', valor: 17200, pct: 3.0, cor: '#22c55e' },
  { nome: 'Reserva / Contingência', valor: 11000, pct: 1.9, cor: '#a855f7' },
];

const linhaTempo = [
  { etapa: 'Compra do Imóvel', data: '01/06/2024', estado: 'concluida' },
  { etapa: 'Início da Reforma', data: '05/06/2024', estado: 'andamento' },
  { etapa: 'Término da Reforma', data: '05/08/2024', estado: 'pendente' },
  { etapa: 'Anúncio para Venda', data: '10/08/2024', estado: 'pendente' },
  { etapa: 'Venda Estimada', data: '10/09/2024', estado: 'pendente' },
];

const comparaveis = [
  { titulo: 'Rua das Orquídeas, 45', bairro: 'Jardim Paulista', detalhes: '123 m² · 3 dorm · 2 vagas', preco: 'R$ 875.000', m2: 'R$ 7.114/m²' },
  { titulo: 'Av. Paulista, 2000', bairro: 'Jardim Paulista', detalhes: '118 m² · 3 dorm · 2 vagas', preco: 'R$ 840.000', m2: 'R$ 7.119/m²' },
  { titulo: 'Rua Itapeva, 150', bairro: 'Jardim Paulista', detalhes: '125 m² · 3 dorm · 2 vagas', preco: 'R$ 890.000', m2: 'R$ 7.120/m²' },
  { titulo: 'Al. Santos, 300', bairro: 'Jardim Paulista', detalhes: '115 m² · 3 dorm · 2 vagas', preco: 'R$ 815.000', m2: 'R$ 7.087/m²' },
];

const fmtBrl = (v: number, comCentavos = true) =>
  v.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: comCentavos ? 2 : 0,
    maximumFractionDigits: comCentavos ? 2 : 0,
  });

type TabId = 'resumo' | 'custos' | 'projecao' | 'fluxo' | 'indicadores' | 'documentos';

const tabs: { id: TabId; label: string }[] = [
  { id: 'resumo', label: 'Resumo da Análise' },
  { id: 'custos', label: 'Custos de Reforma' },
  { id: 'projecao', label: 'Projeção de Venda' },
  { id: 'fluxo', label: 'Fluxo de Caixa' },
  { id: 'indicadores', label: 'Indicadores' },
  { id: 'documentos', label: 'Documentos' },
];

export default function AnaliseImovel() {
  const [tab, setTab] = useState<TabId>('resumo');
  const [cenario, setCenario] = useState<CenarioId>('realista');

  return (
    <div className="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-6">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-semibold text-slate-900">Análise de Imóvel</h1>
            <p className="text-sm text-slate-500">
              Avalie a viabilidade do negócio antes de fechar a compra
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
              <Save className="h-4 w-4" /> Salvar Análise
            </button>
            <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
              <FileText className="h-4 w-4" /> Gerar Relatório <ChevronDown className="h-4 w-4" />
            </button>
            <button className="h-9 w-9 rounded-lg border border-slate-200 bg-white grid place-items-center text-slate-500 hover:bg-slate-50">
              <MoreHorizontal className="h-4 w-4" />
            </button>
          </div>
        </div>

        <button className="text-sm text-slate-600 hover:text-slate-900 inline-flex items-center gap-1">
          <ArrowLeft className="h-4 w-4" /> Voltar para análises
        </button>

        {/* Cabeçalho do imóvel */}
        <div className="bg-white rounded-xl border border-slate-200 p-5">
          <div className="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] gap-5">
            <div className="relative h-40 rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 overflow-hidden">
              <span className="absolute top-2 left-2 bg-primary-600 text-white text-[10px] px-2 py-1 rounded-md font-medium">
                Em análise
              </span>
              <div className="absolute inset-0 grid place-items-center text-slate-400">
                <svg viewBox="0 0 24 24" className="h-14 w-14" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-900">Casa Jardim Paulista</h2>
              <p className="text-sm text-slate-500">
                Rua das Flores, 123 · Jardim Paulista
                <br />
                São Paulo · SP
              </p>
              <div className="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-xs text-slate-500">
                <Spec icon={Maximize2} valor="120 m²" label="Área construída" />
                <Spec icon={BedDouble} valor="3" label="Quartos" />
                <Spec icon={Bath} valor="2" label="Banheiros" />
                <Spec icon={Car} valor="2" label="Vagas" />
                <Spec icon={CalendarDays} valor="1985" label="Ano construção" />
              </div>
            </div>
            <div className="flex flex-col items-end justify-between">
              <div className="text-right">
                <div className="text-xs text-slate-500">Valor do Imóvel</div>
                <div className="text-2xl font-bold text-primary-600">{fmtBrl(420000, false)}</div>
              </div>
              <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
                <Pencil className="h-4 w-4" /> Editar dados
              </button>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-white rounded-xl border border-slate-200 p-5">
          <div className="border-b border-slate-200 flex gap-6 overflow-x-auto -mt-2">
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

          {tab === 'resumo' ? (
            <div className="mt-5 space-y-6">
              {/* KPIs */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <KpiAnalise label="Investimento Total" valor={fmtBrl(580250, false)} icon={DollarSign} cor="primary" link="Ver detalhes" />
                <KpiAnalise
                  label="Custo de Reforma"
                  valor={fmtBrl(98750, false)}
                  icon={Wrench}
                  cor="amber"
                  ajuda="16,8% do investimento"
                />
                <KpiAnalise
                  label="Preço de Venda Estimado"
                  valor={fmtBrl(850000, false)}
                  icon={Tag}
                  cor="blue"
                  link="Ver projeção detalhada"
                />
                <KpiAnalise
                  label="Lucro Líquido Estimado"
                  valor={fmtBrl(198417, false)}
                  icon={TrendingUp}
                  cor="emerald"
                  link="Ver composição"
                />
                <KpiAnalise
                  label="ROI (Retorno)"
                  valor="34,2%"
                  icon={Percent}
                  cor="emerald"
                  ajuda="Bom negócio"
                />
              </div>

              {/* Cenários */}
              <div>
                <h3 className="text-sm font-semibold text-slate-900 mb-3">Simulação de Cenários</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                  {(Object.keys(cenarios) as CenarioId[]).map((c) => (
                    <CenarioCard
                      key={c}
                      data={cenarios[c]}
                      ativo={c === cenario}
                      onClick={() => setCenario(c)}
                    />
                  ))}
                </div>
              </div>

              {/* Composição + Timeline */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <ComposicaoCard />
                <TimelineCard />
              </div>
            </div>
          ) : (
            <div className="mt-8 text-sm text-slate-500 text-center py-10">
              Conteúdo desta aba em breve.
            </div>
          )}
        </div>
      </div>

      {/* Coluna lateral */}
      <aside className="space-y-6">
        <ProjecaoVendaCard />
        <ComparaveisCard />
        <AnotacoesCard />
      </aside>
    </div>
  );
}

function Spec({
  icon: Icon,
  valor,
  label,
}: {
  icon: typeof Maximize2;
  valor: string;
  label: string;
}) {
  return (
    <div className="flex items-start gap-2">
      <Icon className="h-4 w-4 text-slate-400 mt-0.5" />
      <div>
        <div className="text-sm font-semibold text-slate-900">{valor}</div>
        <div className="text-[11px] text-slate-500">{label}</div>
      </div>
    </div>
  );
}

function KpiAnalise({
  label,
  valor,
  icon: Icon,
  cor,
  ajuda,
  link,
}: {
  label: string;
  valor: React.ReactNode;
  icon: typeof DollarSign;
  cor: 'primary' | 'blue' | 'emerald' | 'amber';
  ajuda?: string;
  link?: string;
}) {
  const map = {
    primary: 'bg-primary-50 text-primary-600',
    blue: 'bg-blue-50 text-blue-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
  } as const;
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="text-xs text-slate-500">{label}</div>
        <div className={`h-7 w-7 rounded-lg grid place-items-center ${map[cor]}`}>
          <Icon className="h-3.5 w-3.5" />
        </div>
      </div>
      <div className="mt-2 text-xl font-semibold text-slate-900">{valor}</div>
      {ajuda && <div className="text-[11px] text-slate-500 mt-1">{ajuda}</div>}
      {link && (
        <button className="mt-2 text-xs text-primary-600 hover:text-primary-700 font-medium">
          {link}
        </button>
      )}
    </div>
  );
}

function CenarioCard({
  data,
  ativo,
  onClick,
}: {
  data: (typeof cenarios)[CenarioId];
  ativo: boolean;
  onClick: () => void;
}) {
  const temaHeader =
    data.tema === 'red'
      ? 'bg-red-50 text-red-700'
      : data.tema === 'emerald' && ativo
        ? 'bg-primary-50 text-primary-700'
        : 'bg-emerald-50 text-emerald-700';
  const lucroCor = data.lucro >= 0 ? 'text-emerald-600' : 'text-red-600';
  const roiCor =
    data.roi >= 30 ? 'text-emerald-600' : data.roi >= 20 ? 'text-amber-600' : 'text-red-600';

  return (
    <div
      onClick={onClick}
      className={`rounded-xl border bg-white overflow-hidden cursor-pointer transition ${
        ativo ? 'border-primary-500 ring-2 ring-primary-100' : 'border-slate-200 hover:border-slate-300'
      }`}
    >
      <div className={`px-4 py-3 ${temaHeader} flex items-center justify-between`}>
        <div>
          <div className="text-sm font-semibold">{data.titulo}</div>
          <div className="text-[11px] opacity-80 mt-0.5">Venda: {fmtBrl(data.venda, false)}</div>
        </div>
        {ativo && (
          <span className="text-[10px] px-2 py-0.5 rounded-full bg-white text-primary-700 font-medium">
            Cenário base
          </span>
        )}
      </div>
      <div className="p-4 space-y-2 text-sm">
        <Linha label="Lucro Líquido" valor={fmtBrl(data.lucro, false)} valorCls={lucroCor} />
        <Linha label="ROI" valor={`${data.roi.toString().replace('.', ',')}%`} valorCls={roiCor} />
        <Linha label="Margem de Lucro" valor={`${data.margem.toString().replace('.', ',')}%`} />
        <Linha label="Payback" valor={data.payback} />
        <button
          className={`mt-3 w-full text-sm py-2 rounded-lg ${
            ativo
              ? 'bg-primary-600 text-white hover:bg-primary-700'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
          }`}
        >
          Ver detalhes
        </button>
      </div>
    </div>
  );
}

function Linha({ label, valor, valorCls }: { label: string; valor: string; valorCls?: string }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-slate-500">{label}</span>
      <span className={`font-medium text-slate-900 ${valorCls ?? ''}`}>{valor}</span>
    </div>
  );
}

function ComposicaoCard() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <div className="flex items-start justify-between mb-4">
        <div>
          <h3 className="text-sm font-semibold text-slate-900">Composição do Investimento</h3>
          <p className="text-xs text-slate-500">Para o cenário realista</p>
        </div>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-[180px_1fr] items-center gap-5">
        <div className="relative h-44">
          <ResponsiveContainer>
            <PieChart>
              <Pie
                data={composicao}
                dataKey="valor"
                innerRadius={55}
                outerRadius={80}
                paddingAngle={1}
                stroke="none"
              >
                {composicao.map((c) => (
                  <Cell key={c.nome} fill={c.cor} />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>
          <div className="absolute inset-0 grid place-items-center pointer-events-none">
            <div className="text-center">
              <div className="text-base font-bold text-slate-900">R$ 580.250</div>
              <div className="text-[10px] text-slate-500">Total Investido</div>
            </div>
          </div>
        </div>
        <div className="space-y-2">
          {composicao.map((c) => (
            <div key={c.nome} className="flex items-center gap-3 text-xs">
              <span className="h-2 w-2 rounded-full" style={{ background: c.cor }} />
              <span className="text-slate-600 flex-1">{c.nome}</span>
              <span className="text-slate-900 font-medium">{fmtBrl(c.valor, false)}</span>
              <span className="text-slate-400 w-12 text-right">{c.pct.toString().replace('.', ',')}%</span>
            </div>
          ))}
        </div>
      </div>
      <button className="mt-4 text-xs text-primary-600 hover:text-primary-700 font-medium">
        Ver detalhes completos
      </button>
    </div>
  );
}

function TimelineCard() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <h3 className="text-sm font-semibold text-slate-900 mb-4">Linha do Tempo Estimada</h3>
      <ol className="relative ml-2">
        {linhaTempo.map((t, i) => {
          const Icon =
            t.estado === 'concluida' ? CheckCircle2 : t.estado === 'andamento' ? Info : Circle;
          const cor =
            t.estado === 'concluida'
              ? 'text-emerald-500'
              : t.estado === 'andamento'
                ? 'text-primary-500'
                : 'text-slate-300';
          return (
            <li key={t.etapa} className="relative pl-7 pb-4 last:pb-0">
              {i < linhaTempo.length - 1 && (
                <span className="absolute left-2 top-5 bottom-0 w-px bg-slate-200" />
              )}
              <Icon className={`absolute left-0 top-0.5 h-4 w-4 ${cor}`} />
              <div className="flex items-center justify-between gap-3">
                <div className="text-sm text-slate-900">{t.etapa}</div>
                <div className="text-xs text-slate-500">{t.data}</div>
              </div>
            </li>
          );
        })}
      </ol>
      <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
        <span className="text-slate-500">Duração total estimada</span>
        <span className="text-slate-900 font-medium">3 meses e 9 dias</span>
      </div>
    </div>
  );
}

function ProjecaoVendaCard() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <div className="flex items-start justify-between mb-3">
        <div>
          <h3 className="text-sm font-semibold text-slate-900">Projeção de Venda</h3>
          <p className="text-xs text-slate-500">Cenário realista</p>
        </div>
      </div>
      <div className="space-y-3 text-sm">
        <Linha label="Valor por m² na região" valor="R$ 7.083,33" />
        <Linha label="Valor de mercado (120 m²)" valor="R$ 850.000,00" />
        <Linha label="Ajustes" valor="R$ 0,00" />
        <div className="border-t border-slate-100 pt-3">
          <Linha label="Preço de venda estimado" valor="R$ 850.000,00" valorCls="text-emerald-600" />
        </div>
      </div>
      <div className="mt-4 bg-primary-50 rounded-lg px-3 py-2 flex items-start gap-2 text-xs text-primary-700">
        <Info className="h-4 w-4 mt-0.5" />
        <div>
          <div className="font-medium">Baseado em 8 imóveis comparáveis</div>
          <div className="opacity-80">Análise realizada em 10/05/2024</div>
        </div>
      </div>
    </div>
  );
}

function ComparaveisCard() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-sm font-semibold text-slate-900">Imóveis Comparáveis</h3>
        <button className="text-xs text-primary-600 hover:text-primary-700 font-medium">
          Ver todos
        </button>
      </div>
      <div className="space-y-3">
        {comparaveis.map((c) => (
          <div key={c.titulo} className="flex gap-3 items-start">
            <div className="h-14 w-16 rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500 shrink-0">
              <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor">
                <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
              </svg>
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-medium text-slate-900 truncate">{c.titulo}</div>
              <div className="text-[11px] text-slate-500">{c.bairro}</div>
              <div className="text-[11px] text-slate-500">{c.detalhes}</div>
              <div className="flex items-center justify-between mt-1">
                <span className="text-sm font-semibold text-slate-900">{c.preco}</span>
                <span className="text-[11px] text-slate-500">{c.m2}</span>
              </div>
            </div>
          </div>
        ))}
      </div>
      <button className="mt-4 w-full text-sm py-2 rounded-lg border border-primary-200 text-primary-600 hover:bg-primary-50">
        Ver todos os comparáveis
      </button>
    </div>
  );
}

function AnotacoesCard() {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5">
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-semibold text-slate-900">Anotações</h3>
        <button className="text-xs text-primary-600 hover:text-primary-700 font-medium inline-flex items-center gap-1">
          <Pencil className="h-3 w-3" /> Editar
        </button>
      </div>
      <p className="text-xs text-slate-600 leading-relaxed">
        Imóvel com ótimo potencial de valorização.
        <br />
        Rua tranquila e com alta demanda na região.
        <br />
        Reforma cosmética, sem necessidade de estrutura.
        <br />
        Foco em acabamento de cozinha e banheiros.
      </p>
      <div className="text-[10px] text-slate-400 mt-3 border-t border-slate-100 pt-2">
        Atualizado em 10/05/2024 14:30 por Matheus Silva
      </div>
    </div>
  );
}
