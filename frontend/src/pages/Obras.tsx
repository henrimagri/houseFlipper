// Componente para etapa 2 — Orçamento
function EtapaOrcamento({ orcamentoTotal, setOrcamentoTotal, orcamentoCategorias, setOrcamentoCategorias }: any) {
  // Categorias padrão
  const categoriasPadrao = ['Demolição', 'Elétrica', 'Hidráulica', 'Acabamento'];

  // Atualiza valor de uma categoria
  const handleCategoriaChange = (cat: string, valor: string) => {
    setOrcamentoCategorias((prev: any) => ({ ...prev, [cat]: valor.replace(/[^0-9.,]/g, '') }));
  };

  // Distribui orçamento total igualmente se usuário preencher só o total
  const distribuirTotal = () => {
    if (!orcamentoTotal) return;
    const valor = parseFloat(orcamentoTotal.replace(',', '.')) || 0;
    const porCategoria = (valor / categoriasPadrao.length).toFixed(2);
    const novo = Object.fromEntries(categoriasPadrao.map((c) => [c, porCategoria]));
    setOrcamentoCategorias(novo);
  };

  return (
    <div className="space-y-6">
      <div>
        <label className="block text-sm font-medium mb-1">Orçamento Total (R$)</label>
        <input
          className="input"
          placeholder="Ex: 100000"
          value={orcamentoTotal}
          onChange={e => setOrcamentoTotal(e.target.value.replace(/[^0-9.,]/g, ''))}
          onBlur={distribuirTotal}
        />
        <span className="text-xs text-slate-500">Preencha o total ou detalhe por categoria abaixo.</span>
      </div>
      <div>
        <label className="block text-sm font-medium mb-1">Orçamento por Categoria</label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          {categoriasPadrao.map((cat) => (
            <div key={cat} className="flex items-center gap-2">
              <span className="w-28">{cat}</span>
              <input
                className="input flex-1"
                placeholder="0,00"
                value={orcamentoCategorias[cat] || ''}
                onChange={e => handleCategoriaChange(cat, e.target.value)}
                type="text"
              />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
import { propriedadesApi } from '@/lib/obras';

function ImovelSelect() {
  const [imoveis, setImoveis] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    propriedadesApi
      .listar()
      .then((data) => {
        setImoveis(data.data || data);
        setLoading(false);
      })
      .catch(() => {
        setErro('Erro ao carregar imóveis');
        setLoading(false);
      });
  }, []);

  if (loading) return <select className="input" disabled><option>Carregando...</option></select>;
  if (erro) return <select className="input" disabled><option>{erro}</option></select>;

  return (
    <select className="input">
      <option value="">Selecione um imóvel</option>
      {imoveis.map((imovel) => (
        <option key={imovel.id} value={imovel.id}>
          {imovel.apelido || imovel.nome || `Imóvel #${imovel.id}`}
        </option>
      ))}
    </select>
  );
}
import { useMemo, useState, useEffect } from 'react';
import {
  Search,
  Filter,
  Plus,
  Bell,
  MoreHorizontal,
  ChevronRight,
  ArrowLeft,
  CheckCircle2,
  Clock,
  Hammer,
  Wrench,
  Paintbrush,
  Package,
  X,
  Calendar,
  Upload,
  FileText,
  ClipboardList,
} from 'lucide-react';

type StatusObra = 'em_andamento' | 'planejada' | 'atrasada' | 'concluida';

interface Obra {
  id: string;
  nome: string;
  endereco: string;
  projeto: string;
  status: StatusObra;
  progresso: number;
  custoOrcado: number;
  custoReal: number;
  inicio: string | null;
  fim: string | null;
  atrasada?: boolean;
}

const obrasMock: Obra[] = [
  { id: '2024-001', nome: 'Obra #2024-001', endereco: 'Casa Jardim Paulista', projeto: 'Projeto #2024-001', status: 'em_andamento', progresso: 65, custoOrcado: 235000, custoReal: 152750, inicio: '10/04/2024', fim: '25/06/2024' },
  { id: '2024-002', nome: 'Obra #2024-002', endereco: 'Sobrado Vila Madalena', projeto: 'Projeto #2024-002', status: 'em_andamento', progresso: 40, custoOrcado: 485000, custoReal: 210300, inicio: '15/04/2024', fim: '30/07/2024' },
  { id: '2024-003', nome: 'Obra #2024-003', endereco: 'Casa Campo Belo', projeto: 'Projeto #2024-003', status: 'planejada', progresso: 0, custoOrcado: 290000, custoReal: 0, inicio: null, fim: null },
  { id: '2024-004', nome: 'Obra #2024-004', endereco: 'Casa Interlagos', projeto: 'Projeto #2024-004', status: 'em_andamento', progresso: 80, custoOrcado: 360000, custoReal: 298500, inicio: '05/03/2024', fim: '20/05/2024' },
  { id: '2024-005', nome: 'Obra #2024-005', endereco: 'Sobrado Moema', projeto: 'Projeto #2024-005', status: 'atrasada', progresso: 90, custoOrcado: 550000, custoReal: 521800, inicio: '10/02/2024', fim: '10/05/2024', atrasada: true },
  { id: '2024-006', nome: 'Obra #2024-006', endereco: 'Casa Brooklin', projeto: 'Projeto #2024-006', status: 'concluida', progresso: 100, custoOrcado: 185000, custoReal: 172900, inicio: '15/11/2023', fim: '20/02/2024' },
];

const etapasResumo = [
  { nome: '1. Demolição', status: 'Concluída', progresso: 100, icon: CheckCircle2, cor: 'text-emerald-600 bg-emerald-50', barra: 'bg-emerald-500' },
  { nome: '2. Alvenaria', status: 'Em andamento', progresso: 70, icon: Hammer, cor: 'text-blue-600 bg-blue-50', barra: 'bg-blue-500' },
  { nome: '3. Instalações', status: 'Em andamento', progresso: 40, icon: Wrench, cor: 'text-blue-600 bg-blue-50', barra: 'bg-blue-500' },
  { nome: '4. Revestimentos', status: 'Pendente', progresso: 0, icon: Paintbrush, cor: 'text-slate-500 bg-slate-100', barra: 'bg-slate-300' },
  { nome: '5. Acabamentos', status: 'Pendente', progresso: 0, icon: Package, cor: 'text-slate-500 bg-slate-100', barra: 'bg-slate-300' },
];

const etapasDetalhadas = [
  { nome: '1. Demolição', status: 'Concluída', progresso: 100, custoOrcado: 15000, custoReal: 12500 },
  { nome: '2. Alvenaria', status: 'Em andamento', progresso: 70, custoOrcado: 45000, custoReal: 31500 },
  { nome: '3. Instalações', status: 'Em andamento', progresso: 40, custoOrcado: 55000, custoReal: 22000 },
  { nome: '4. Revestimentos', status: 'Pendente', progresso: 0, custoOrcado: 60000, custoReal: 0 },
  { nome: '5. Acabamentos', status: 'Pendente', progresso: 0, custoOrcado: 60000, custoReal: 0 },
];

type FiltroAba = 'todas' | 'em_andamento' | 'planejadas' | 'atrasadas' | 'concluidas';

const abas: { id: FiltroAba; label: string }[] = [
  { id: 'todas', label: 'Todas' },
  { id: 'em_andamento', label: 'Em andamento' },
  { id: 'planejadas', label: 'Planejadas' },
  { id: 'atrasadas', label: 'Atrasadas' },
  { id: 'concluidas', label: 'Concluídas' },
];

const fmtBrl = (v: number) =>
  v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });

const statusBadge = (s: StatusObra) => {
  switch (s) {
    case 'em_andamento':
      return { label: 'Em andamento', cls: 'bg-blue-100 text-blue-700' };
    case 'planejada':
      return { label: 'Planejado', cls: 'bg-amber-100 text-amber-700' };
    case 'atrasada':
      return { label: 'Atrasada', cls: 'bg-red-100 text-red-700' };
    case 'concluida':
      return { label: 'Concluída', cls: 'bg-emerald-100 text-emerald-700' };
  }
};

const barraCor = (s: StatusObra) =>
  s === 'atrasada' ? 'bg-red-500' : s === 'concluida' ? 'bg-emerald-500' : 'bg-blue-500';

export default function Obras() {
  // ...existing code...
  // Estado para etapa 2 — Orçamento
  const [orcamentoTotal, setOrcamentoTotal] = useState('');
  const [orcamentoCategorias, setOrcamentoCategorias] = useState<any>({});
  const [aba, setAba] = useState<FiltroAba>('todas');
  const [busca, setBusca] = useState('');
  const [view, setView] = useState<'lista' | 'nova' | 'detalhe'>('lista');
  const [obraSelecionadaId, setObraSelecionadaId] = useState<string>('2024-001');
  const [novaDespesaAberta, setNovaDespesaAberta] = useState(false);

  const obras = useMemo(() => {
    return obrasMock.filter((o) => {
      if (aba === 'em_andamento' && o.status !== 'em_andamento') return false;
      if (aba === 'planejadas' && o.status !== 'planejada') return false;
      if (aba === 'atrasadas' && o.status !== 'atrasada') return false;
      if (aba === 'concluidas' && o.status !== 'concluida') return false;
      if (busca && !`${o.nome} ${o.endereco}`.toLowerCase().includes(busca.toLowerCase()))
        return false;
      return true;
    });
  }, [aba, busca]);

  const obraSelecionada = obrasMock.find((o) => o.id === obraSelecionadaId) ?? obrasMock[0];

  if (view === 'nova') {
    return <NovaObraWizard onCancelar={() => setView('lista')} />;
  }

  if (view === 'detalhe') {
    return (
      <ObraDetalhe
        obra={obraSelecionada}
        onVoltar={() => setView('lista')}
        onNovaDespesa={() => setNovaDespesaAberta(true)}
        despesaAberta={novaDespesaAberta}
        onFecharDespesa={() => setNovaDespesaAberta(false)}
      />
    );
  }

  return (
    <div className="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-6">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-semibold text-slate-900">Obras</h1>
            <p className="text-sm text-slate-500">
              Acompanhe e gerencie todas as obras dos seus projetos
            </p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={() => setView('nova')}
              className="inline-flex items-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2.5 shadow-sm"
            >
              <Plus className="h-4 w-4" /> Nova Obra
            </button>
            <button className="relative h-10 w-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-700">
              <Bell className="h-5 w-5" />
              <span className="absolute -top-1 -right-1 h-4 min-w-[1rem] rounded-full bg-red-500 text-[10px] font-semibold text-white grid place-items-center px-1">
                2
              </span>
            </button>
          </div>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiObra
            label="Total de Obras"
            valor="8"
            ajuda="Ativas: 6 · Concluídas: 2"
            icon={ClipboardList}
            iconCls="bg-primary-50 text-primary-600"
          />
          <KpiObra label="Custo Orçado (Total)" valor={fmtBrl(1248750)} />
          <KpiObra
            label="Custo Real (Total)"
            valor={<span className="text-emerald-600">{fmtBrl(1034250)}</span>}
          />
          <KpiObra
            label="Economia (Total)"
            valor={<span className="text-emerald-600">{fmtBrl(214500)}</span>}
          />
          <KpiObra
            label="Atrasadas"
            valor={<span className="text-red-500">2</span>}
            ajuda="Obras com atraso"
          />
        </div>

        {/* Filtros */}
        <div className="bg-white rounded-xl border border-slate-200 p-2 flex flex-wrap items-center gap-2">
          <div className="flex flex-wrap gap-1">
            {abas.map((a) => (
              <button
                key={a.id}
                onClick={() => setAba(a.id)}
                className={`text-sm px-3 py-1.5 rounded-lg font-medium transition ${
                  aba === a.id
                    ? 'bg-primary-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100'
                }`}
              >
                {a.label}
              </button>
            ))}
          </div>
          <div className="ml-auto flex items-center gap-2 flex-1 max-w-md min-w-[200px]">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar obra..."
                className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-200"
              />
            </div>
            <button className="inline-flex items-center gap-2 text-sm text-slate-600 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
              <Filter className="h-4 w-4" /> Filtros
            </button>
          </div>
        </div>

        {/* Tabela */}
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                  <th className="text-left px-5 py-3 font-medium">Obra</th>
                  <th className="text-left px-3 py-3 font-medium">Projeto / Imóvel</th>
                  <th className="text-left px-3 py-3 font-medium">Status</th>
                  <th className="text-left px-3 py-3 font-medium">Progresso</th>
                  <th className="text-left px-3 py-3 font-medium">Custo Orçado</th>
                  <th className="text-left px-3 py-3 font-medium">Custo Real</th>
                  <th className="text-left px-3 py-3 font-medium">Início</th>
                  <th className="text-left px-3 py-3 font-medium">Previsão de Término</th>
                  <th className="px-3 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {obras.map((o) => {
                  const badge = statusBadge(o.status);
                  return (
                    <tr
                      key={o.id}
                      onClick={() => {
                        setObraSelecionadaId(o.id);
                        setView('detalhe');
                      }}
                      className="hover:bg-slate-50 cursor-pointer"
                    >
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-3">
                          <ThumbObra />
                          <div>
                            <div className="font-medium text-slate-900">{o.nome}</div>
                            <div className="text-xs text-slate-500">{o.endereco}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-3 py-3 text-slate-600">{o.projeto}</td>
                      <td className="px-3 py-3">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${badge.cls}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-3 py-3">
                        <div className="flex items-center gap-2 min-w-[140px]">
                          <div className="h-1.5 w-28 rounded-full bg-slate-100 overflow-hidden">
                            <div
                              className={`h-full ${barraCor(o.status)}`}
                              style={{ width: `${o.progresso}%` }}
                            />
                          </div>
                          <span className="text-xs text-slate-600 w-9">{o.progresso}%</span>
                        </div>
                      </td>
                      <td className="px-3 py-3 text-slate-700">{fmtBrl(o.custoOrcado)}</td>
                      <td
                        className={`px-3 py-3 font-medium ${
                          o.status === 'atrasada'
                            ? 'text-red-600'
                            : o.custoReal > 0
                              ? 'text-emerald-600'
                              : 'text-slate-500'
                        }`}
                      >
                        {fmtBrl(o.custoReal)}
                      </td>
                      <td className="px-3 py-3 text-slate-600">{o.inicio ?? '-'}</td>
                      <td
                        className={`px-3 py-3 ${
                          o.status === 'atrasada' ? 'text-red-600' : 'text-slate-600'
                        }`}
                      >
                        {o.fim ?? '-'}
                      </td>
                      <td className="px-3 py-3 text-slate-400">
                        <MoreHorizontal className="h-4 w-4" />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Painel lateral - Resumo da Obra */}
      <ResumoObraPanel obra={obraSelecionada} />
    </div>
  );
}

function KpiObra({
  label,
  valor,
  ajuda,
  icon: Icon,
  iconCls,
}: {
  label: string;
  valor: React.ReactNode;
  ajuda?: string;
  icon?: typeof ClipboardList;
  iconCls?: string;
}) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="text-sm text-slate-500">{label}</div>
        {Icon && (
          <div className={`h-8 w-8 rounded-lg grid place-items-center ${iconCls ?? 'bg-slate-100 text-slate-500'}`}>
            <Icon className="h-4 w-4" />
          </div>
        )}
      </div>
      <div className="mt-2 text-2xl font-semibold text-slate-900">{valor}</div>
      {ajuda && <div className="text-xs text-slate-500 mt-1">{ajuda}</div>}
    </div>
  );
}

function ThumbObra() {
  return (
    <div className="h-10 w-12 rounded-md bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-slate-500 shrink-0">
      <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor">
        <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
      </svg>
    </div>
  );
}

function ResumoObraPanel({ obra }: { obra: Obra }) {
  const badge = statusBadge(obra.status);
  const economia = obra.custoOrcado - obra.custoReal;
  return (
    <aside className="space-y-6">
      <div className="bg-white rounded-xl border border-slate-200 p-5">
        <div className="flex items-start justify-between mb-4">
          <h3 className="text-sm font-semibold text-slate-900">Resumo da Obra</h3>
        </div>
        <div className="flex items-start gap-3">
          <div className="h-14 w-16 rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500">
            <svg viewBox="0 0 24 24" className="h-7 w-7" fill="currentColor">
              <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
            </svg>
          </div>
          <div className="flex-1">
            <div className="flex items-center justify-between gap-2">
              <div className="text-sm font-medium text-slate-900">{obra.nome}</div>
              <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${badge.cls}`}>
                {badge.label}
              </span>
            </div>
            <div className="text-xs text-slate-500 mt-0.5">{obra.endereco}</div>
          </div>
        </div>

        <div className="mt-5">
          <div className="flex items-center justify-between text-xs text-slate-600 mb-1">
            <span>Progresso Geral</span>
            <span className="font-medium">{obra.progresso}%</span>
          </div>
          <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div className={`h-full ${barraCor(obra.status)}`} style={{ width: `${obra.progresso}%` }} />
          </div>
        </div>

        <div className="grid grid-cols-3 gap-3 mt-5 text-center">
          <div>
            <div className="text-[11px] text-slate-500">Custo Orçado</div>
            <div className="text-sm font-semibold text-slate-900 mt-0.5">{fmtBrl(obra.custoOrcado)}</div>
          </div>
          <div>
            <div className="text-[11px] text-slate-500">Custo Real</div>
            <div className="text-sm font-semibold text-emerald-600 mt-0.5">{fmtBrl(obra.custoReal)}</div>
          </div>
          <div>
            <div className="text-[11px] text-slate-500">Economia</div>
            <div className="text-sm font-semibold text-emerald-600 mt-0.5">{fmtBrl(economia)}</div>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-3 mt-5 text-center border-t border-slate-100 pt-4">
          <div>
            <div className="text-[11px] text-slate-500">Início da Obra</div>
            <div className="text-sm font-medium text-slate-900 mt-0.5">{obra.inicio ?? '-'}</div>
          </div>
          <div>
            <div className="text-[11px] text-slate-500">Previsão de Término</div>
            <div className="text-sm font-medium text-slate-900 mt-0.5">{obra.fim ?? '-'}</div>
          </div>
          <div>
            <div className="text-[11px] text-slate-500">Dias Restantes</div>
            <div className="text-sm font-medium text-slate-900 mt-0.5">18 dias</div>
          </div>
        </div>

        <button className="mt-5 text-sm text-primary-600 font-medium inline-flex items-center gap-1 hover:text-primary-700">
          Ver detalhes da obra <ChevronRight className="h-4 w-4" />
        </button>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 p-5">
        <h3 className="text-sm font-semibold text-slate-900 mb-4">Etapas da Obra</h3>
        <div className="space-y-4">
          {etapasResumo.map((e) => {
            const Icon = e.icon;
            return (
              <div key={e.nome}>
                <div className="flex items-center gap-3">
                  <div className={`h-7 w-7 rounded-lg grid place-items-center ${e.cor}`}>
                    <Icon className="h-3.5 w-3.5" />
                  </div>
                  <div className="flex-1">
                    <div className="text-xs font-medium text-slate-900">{e.nome}</div>
                    <div className="text-[11px] text-slate-500">{e.status}</div>
                  </div>
                  <div className="text-xs text-slate-600 font-medium">{e.progresso}%</div>
                </div>
                <div className="h-1 mt-2 ml-10 rounded-full bg-slate-100 overflow-hidden">
                  <div className={`h-full ${e.barra}`} style={{ width: `${e.progresso}%` }} />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </aside>
  );
}

/* -------------------- Nova Obra Wizard -------------------- */

function NovaObraWizard({ onCancelar }: { onCancelar: () => void }) {
  const [salvando, setSalvando] = useState(false);
  const [mensagem, setMensagem] = useState<string|null>(null);

  async function handleSalvarObra() {
    setSalvando(true);
    setMensagem(null);
    try {
      // Monta payload conforme esperado pelo backend
      const payload = {
        nome: obraNome,
        descricao: obraDescricao,
        propriedade_id: obraImovel,
        data_inicio: obraDataInicio,
        data_termino_prevista: obraDataTermino,
        responsavel: obraResponsavel,
        orcamento_total: orcamentoTotal,
        orcamento_categorias: orcamentoCategorias,
      };
      // Chamada à API (ajuste a URL/token conforme necessário)
      // Busca token do localStorage (ajuste a chave conforme seu login)
      const token = localStorage.getItem('token');
      const resp = await fetch('/api/projetos', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(payload),
      });
      if (!resp.ok) throw new Error('Erro ao salvar obra');
      setMensagem('Obra salva com sucesso!');
      // Opcional: resetar wizard ou redirecionar
      // onCancelar();
    } catch (e: any) {
      setMensagem(e.message || 'Erro ao salvar obra');
    } finally {
      setSalvando(false);
    }
  }

  const [passo, setPasso] = useState(1);
  // Estado dos campos principais
  const [obraNome, setObraNome] = useState('');
  const [obraDescricao, setObraDescricao] = useState('');
  const [obraImovel, setObraImovel] = useState('');
  const [obraDataInicio, setObraDataInicio] = useState('');
  const [obraDataTermino, setObraDataTermino] = useState('');
  const [obraResponsavel, setObraResponsavel] = useState('');
  const [orcamentoTotal, setOrcamentoTotal] = useState('');
  const [orcamentoCategorias, setOrcamentoCategorias] = useState<any>({});
  const passos = [
    { n: 1, t: 'Informações Gerais' },
    { n: 2, t: 'Orçamento' },
    { n: 3, t: 'Etapas' },
    { n: 4, t: 'Revisão' },
  ];

  // Etapas padrão para exibir na etapa 3 e revisão
  const etapasPadrao = [
    'Demolição',
    'Elétrica',
    'Hidráulica',
    'Acabamento',
  ];

  // Monta array de etapas para exibir na etapa 3 e revisão
  const etapas = etapasPadrao.map((nome, idx) => ({
    nome,
    status: 'Pendente',
    progresso: 0,
    custoOrcado: Number(orcamentoCategorias[nome] || 0),
    custoReal: 0,
    ordem: idx + 1,
  }));

  return (
    <div className="space-y-6">
      <div className="text-xs text-slate-500 flex items-center gap-1">
        <span>Obras</span>
        <ChevronRight className="h-3 w-3" />
        <span className="text-slate-700">Nova Obra</span>
      </div>
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">Nova Obra</h1>
        <p className="text-sm text-slate-500">
          Preencha as informações para criar uma nova obra
        </p>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 p-6 grid grid-cols-1 md:grid-cols-[200px_1fr] gap-8">
        <ol className="space-y-3">
          {passos.map((p) => {
            const ativo = p.n === passo;
            return (
              <li key={p.n}>
                <button
                  onClick={() => setPasso(p.n)}
                  className={`w-full flex items-center gap-3 text-left text-sm px-3 py-2 rounded-lg ${
                    ativo ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <span
                    className={`h-6 w-6 rounded-full grid place-items-center text-xs font-semibold ${
                      ativo
                        ? 'bg-primary-600 text-white'
                        : 'bg-slate-100 text-slate-500'
                    }`}
                  >
                    {p.n}
                  </span>
                  <span>{p.t}</span>
                </button>
              </li>
            );
          })}
        </ol>

        <div>
          <h3 className="text-base font-semibold text-slate-900 mb-5">{passos.find(p => p.n === passo)?.t}</h3>

          {/* Renderiza etapa conforme passo atual */}
          {passo === 1 && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Campo label="Projeto / Imóvel *">
                <input
                  className="input"
                  placeholder="Ex: Casa Jardim Paulista"
                  value={obraImovel}
                  onChange={e => setObraImovel(e.target.value)}
                />
              </Campo>
              <Campo label="Nome da Obra *">
                <input
                  className="input"
                  placeholder="Ex: Reforma Casa Jardim Paulista"
                  value={obraNome}
                  onChange={e => setObraNome(e.target.value)}
                />
              </Campo>
              <div className="md:col-span-2">
                <Campo label="Descrição">
                  <textarea
                    rows={4}
                    className="input resize-none"
                    placeholder="Descreva informações importantes sobre esta obra..."
                    value={obraDescricao}
                    onChange={e => setObraDescricao(e.target.value)}
                  />
                </Campo>
              </div>
              <Campo label="Data de Início *">
                <input
                  className="input"
                  placeholder="dd/mm/aaaa"
                  value={obraDataInicio}
                  onChange={e => setObraDataInicio(e.target.value)}
                />
              </Campo>
              <Campo label="Previsão de Término *">
                <input
                  className="input"
                  placeholder="dd/mm/aaaa"
                  value={obraDataTermino}
                  onChange={e => setObraDataTermino(e.target.value)}
                />
              </Campo>
              <Campo label="Responsável *">
                <input
                  className="input"
                  placeholder="Nome do responsável"
                  value={obraResponsavel}
                  onChange={e => setObraResponsavel(e.target.value)}
                />
              </Campo>
            </div>
          )}
          {passo === 2 && (
            <EtapaOrcamento
              orcamentoTotal={orcamentoTotal}
              setOrcamentoTotal={setOrcamentoTotal}
              orcamentoCategorias={orcamentoCategorias}
              setOrcamentoCategorias={setOrcamentoCategorias}
            />
          )}
          {passo === 3 && (
            <div>
              <h4 className="text-sm font-semibold mb-3">Etapas da Obra</h4>
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                    <th className="text-left px-4 py-2 font-medium">Etapa</th>
                    <th className="text-left px-3 py-2 font-medium">Status</th>
                    <th className="text-left px-3 py-2 font-medium">Progresso</th>
                    <th className="text-left px-3 py-2 font-medium">Custo Orçado</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {etapas.map((e) => (
                    <tr key={e.nome}>
                      <td className="px-4 py-3 font-medium text-slate-900">{e.nome}</td>
                      <td className="px-3 py-3">
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{e.status}</span>
                      </td>
                      <td className="px-3 py-3">
                        <div className="flex items-center gap-2 min-w-[100px]">
                          <div className="h-1.5 w-20 rounded-full bg-slate-100 overflow-hidden">
                            <div className="h-full bg-slate-300" style={{ width: `0%` }} />
                          </div>
                          <span className="text-xs text-slate-600 w-9">0%</span>
                        </div>
                      </td>
                      <td className="px-3 py-3 text-slate-700">R$ {Number(e.custoOrcado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {passo === 4 && (
            <div className="space-y-6">
              <h4 className="text-base font-semibold">Revisão Final</h4>
              <div>
                <h5 className="text-sm font-semibold mb-2">Dados da Obra</h5>
                <ul className="text-sm text-slate-700 space-y-1">
                  <li><b>Nome:</b> {obraNome}</li>
                  <li><b>Imóvel:</b> {obraImovel}</li>
                  <li><b>Data de Início:</b> {obraDataInicio}</li>
                  <li><b>Previsão de Término:</b> {obraDataTermino}</li>
                  <li><b>Responsável:</b> {obraResponsavel}</li>
                  <li><b>Descrição:</b> {obraDescricao}</li>
                </ul>
              </div>
              <div>
                <h5 className="text-sm font-semibold mb-2">Orçamento</h5>
                <ul className="text-sm text-slate-700 space-y-1">
                  <li><b>Total:</b> R$ {Number(orcamentoTotal).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</li>
                  {etapas.map((e) => (
                    <li key={e.nome}><b>{e.nome}:</b> R$ {Number(e.custoOrcado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</li>
                  ))}
                </ul>
              </div>
              <div>
                <h5 className="text-sm font-semibold mb-2">Etapas</h5>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                      <th className="text-left px-4 py-2 font-medium">Etapa</th>
                      <th className="text-left px-3 py-2 font-medium">Status</th>
                      <th className="text-left px-3 py-2 font-medium">Progresso</th>
                      <th className="text-left px-3 py-2 font-medium">Custo Orçado</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {etapas.map((e) => (
                      <tr key={e.nome}>
                        <td className="px-4 py-3 font-medium text-slate-900">{e.nome}</td>
                        <td className="px-3 py-3">
                          <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{e.status}</span>
                        </td>
                        <td className="px-3 py-3">
                          <div className="flex items-center gap-2 min-w-[100px]">
                            <div className="h-1.5 w-20 rounded-full bg-slate-100 overflow-hidden">
                              <div className="h-full bg-slate-300" style={{ width: `0%` }} />
                            </div>
                            <span className="text-xs text-slate-600 w-9">0%</span>
                          </div>
                        </td>
                        <td className="px-3 py-3 text-slate-700">R$ {Number(e.custoOrcado).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="flex flex-col items-end mt-6 gap-2">
                {mensagem && (
                  <div className={`text-sm ${mensagem.includes('sucesso') ? 'text-emerald-600' : 'text-red-600'}`}>{mensagem}</div>
                )}
                <button
                  className="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-6 py-2 rounded-lg disabled:opacity-60"
                  onClick={handleSalvarObra}
                  disabled={salvando}
                >
                  {salvando ? 'Salvando...' : 'Salvar Obra'}
                </button>
              </div>
            </div>
          )}

          <div className="flex items-center justify-between mt-8 pt-5 border-t border-slate-100">
            <button
              onClick={onCancelar}
              className="text-sm text-slate-600 hover:text-slate-900 px-3 py-2"
            >
              Cancelar
            </button>
            <button
              onClick={() => setPasso((p) => Math.min(4, p + 1))}
              className="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-5 py-2 rounded-lg"
              disabled={passo === 4}
            >
              {passo === 4 ? 'Final' : 'Próximo'} <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Campo({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="block text-xs font-medium text-slate-600 mb-1">{label}</span>
      {children}
    </label>
  );
}

function DateInput({ value }: { value?: string }) {
  return (
    <div className="relative">
      <input className="input pr-10" defaultValue={value} placeholder="dd/mm/aaaa" />
      <Calendar className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
    </div>
  );
}

/* -------------------- Detalhe da Obra (tabs Etapas) -------------------- */

function ObraDetalhe({
  obra,
  onVoltar,
  onNovaDespesa,
  despesaAberta,
  onFecharDespesa,
}: {
  obra: Obra;
  onVoltar: () => void;
  onNovaDespesa: () => void;
  despesaAberta: boolean;
  onFecharDespesa: () => void;
}) {
  const [tab, setTab] = useState<'visao' | 'etapas' | 'despesas' | 'fornecedores' | 'documentos' | 'relatorios'>(
    'etapas',
  );
  const tabs = [
    { id: 'visao', label: 'Visão Geral' },
    { id: 'etapas', label: 'Etapas' },
    { id: 'despesas', label: 'Despesas' },
    { id: 'fornecedores', label: 'Fornecedores' },
    { id: 'documentos', label: 'Documentos' },
    { id: 'relatorios', label: 'Relatórios' },
  ] as const;

  return (
    <div className="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-6">
      <div className="space-y-5">
        <div className="text-xs text-slate-500 flex items-center gap-1">
          <button onClick={onVoltar} className="hover:text-slate-700">
            Obras
          </button>
          <ChevronRight className="h-3 w-3" />
          <span className="text-slate-700">{obra.nome}</span>
        </div>

        <div className="bg-white rounded-xl border border-slate-200 p-5">
          <div className="flex items-center justify-between gap-4 flex-wrap">
            <div className="flex items-center gap-3">
              <div className="h-12 w-14 rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500">
                <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
              <div>
                <div className="text-base font-semibold text-slate-900">{obra.nome}</div>
                <div className="text-xs text-slate-500">{obra.endereco}</div>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
                <FileText className="h-4 w-4" /> Editar Obra
              </button>
              <button
                onClick={onNovaDespesa}
                className="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-3 py-2 rounded-lg"
              >
                <Plus className="h-4 w-4" /> Nova Etapa
              </button>
            </div>
          </div>

          <div className="border-b border-slate-200 mt-5 flex gap-6 overflow-x-auto">
            {tabs.map((t) => (
              <button
                key={t.id}
                onClick={() => setTab(t.id)}
                className={`text-sm pb-3 -mb-px border-b-2 ${
                  tab === t.id
                    ? 'border-primary-600 text-primary-700 font-medium'
                    : 'border-transparent text-slate-500 hover:text-slate-700'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          {tab === 'etapas' ? (
            <div className="mt-5">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold text-slate-900">Etapas da Obra</h3>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                      <th className="text-left px-4 py-2 font-medium">Etapa</th>
                      <th className="text-left px-3 py-2 font-medium">Status</th>
                      <th className="text-left px-3 py-2 font-medium">Progresso</th>
                      <th className="text-left px-3 py-2 font-medium">Custo Orçado</th>
                      <th className="text-left px-3 py-2 font-medium">Custo Real</th>
                      <th className="text-left px-3 py-2 font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {etapasDetalhadas.map((e) => {
                      const cls =
                        e.status === 'Concluída'
                          ? 'bg-emerald-100 text-emerald-700'
                          : e.status === 'Em andamento'
                            ? 'bg-blue-100 text-blue-700'
                            : 'bg-amber-100 text-amber-700';
                      const barra =
                        e.status === 'Concluída'
                          ? 'bg-emerald-500'
                          : e.status === 'Em andamento'
                            ? 'bg-blue-500'
                            : 'bg-slate-300';
                      return (
                        <tr key={e.nome}>
                          <td className="px-4 py-3 font-medium text-slate-900">{e.nome}</td>
                          <td className="px-3 py-3">
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${cls}`}>
                              {e.status}
                            </span>
                          </td>
                          <td className="px-3 py-3">
                            <div className="flex items-center gap-2 min-w-[140px]">
                              <div className="h-1.5 w-28 rounded-full bg-slate-100 overflow-hidden">
                                <div
                                  className={`h-full ${barra}`}
                                  style={{ width: `${e.progresso}%` }}
                                />
                              </div>
                              <span className="text-xs text-slate-600 w-9">{e.progresso}%</span>
                            </div>
                          </td>
                          <td className="px-3 py-3 text-slate-700">{fmtBrl(e.custoOrcado)}</td>
                          <td className="px-3 py-3 text-emerald-600 font-medium">
                            {fmtBrl(e.custoReal)}
                          </td>
                          <td className="px-3 py-3 text-slate-400">
                            <MoreHorizontal className="h-4 w-4" />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="mt-8 text-sm text-slate-500 text-center py-8">
              Conteúdo desta aba em breve.
            </div>
          )}
        </div>
      </div>

      {despesaAberta && <NovaDespesaDrawer onFechar={onFecharDespesa} />}
    </div>
  );
}

/* -------------------- Drawer Nova Despesa -------------------- */

function NovaDespesaDrawer({ onFechar }: { onFechar: () => void }) {
  return (
    <aside className="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
      <div className="bg-primary-600 text-white px-5 py-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <button onClick={onFechar} className="hover:opacity-80">
            <ArrowLeft className="h-4 w-4" />
          </button>
          <h3 className="text-sm font-semibold">Nova Despesa</h3>
        </div>
        <button onClick={onFechar} className="hover:opacity-80">
          <X className="h-4 w-4" />
        </button>
      </div>
      <div className="p-5 space-y-4 overflow-y-auto">
        <Campo label="Etapa *">
          <select className="input">
            <option>Selecione a etapa</option>
          </select>
        </Campo>
        <Campo label="Descrição *">
          <input className="input" placeholder="Ex: Compra de cimento" />
        </Campo>
        <Campo label="Fornecedor">
          <select className="input">
            <option>Selecione o fornecedor</option>
          </select>
        </Campo>
        <Campo label="Valor (R$) *">
          <input className="input" placeholder="0,00" />
        </Campo>
        <Campo label="Data *">
          <DateInput value="10/05/2024" />
        </Campo>
        <div>
          <span className="block text-xs font-medium text-slate-600 mb-1">Comprovante</span>
          <label className="flex flex-col items-center justify-center gap-1 border border-dashed border-slate-300 rounded-lg py-6 cursor-pointer hover:border-primary-400 hover:bg-primary-50/30">
            <Upload className="h-5 w-5 text-primary-600" />
            <span className="text-xs text-slate-600">Adicionar comprovante</span>
            <span className="text-[10px] text-slate-400">JPG, PNG ou PDF (máx. 10MB)</span>
            <input type="file" className="hidden" />
          </label>
        </div>
      </div>
      <div className="border-t border-slate-200 px-5 py-3 flex items-center justify-end gap-2">
        <button
          onClick={onFechar}
          className="text-sm text-slate-600 hover:text-slate-900 px-3 py-2"
        >
          Cancelar
        </button>
        <button className="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
          Salvar
        </button>
      </div>
    </aside>
  );
}

/* indicador para evitar warning de import não usado */
const _icons = { Clock };
void _icons;
