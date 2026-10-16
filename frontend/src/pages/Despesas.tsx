import { useMemo, useState } from 'react';
import {
  Download,
  Plus,
  SlidersHorizontal,
  Search,
  ChevronDown,
  MoreHorizontal,
  X,
  CalendarDays,
  CreditCard,
  Wallet,
  Banknote,
  ArrowLeftRight,
  FileText,
  ShoppingCart,
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Wallet2,
} from 'lucide-react';

type StatusDespesa = 'pago' | 'a_pagar' | 'vencido';
type Categoria = 'Material' | 'Mão de Obra' | 'Serviço' | 'Outros';
type FormaPagamento = 'PIX' | 'Transferência' | 'Cartão de Crédito' | 'Boleto' | 'Dinheiro';

interface Despesa {
  id: number;
  data: string;
  descricao: string;
  categoria: Categoria;
  fornecedor: string;
  forma: FormaPagamento;
  valor: number;
  status: StatusDespesa;
}

const despesasMock: Despesa[] = [
  { id: 1, data: '10/05/2024', descricao: 'Cimento CPII 50kg (30 un)', categoria: 'Material', fornecedor: 'Depósito ConstruMais', forma: 'PIX', valor: 450, status: 'pago' },
  { id: 2, data: '09/05/2024', descricao: 'Mão de obra - Alvenaria (diária)', categoria: 'Mão de Obra', fornecedor: 'João Pedreiro', forma: 'Transferência', valor: 600, status: 'pago' },
  { id: 3, data: '08/05/2024', descricao: "Caixa d'água 500L", categoria: 'Material', fornecedor: 'Hidráulica Silva', forma: 'Cartão de Crédito', valor: 320, status: 'pago' },
  { id: 4, data: '07/05/2024', descricao: 'Areia média - 5m³', categoria: 'Material', fornecedor: 'Depósito ConstruMais', forma: 'PIX', valor: 280, status: 'pago' },
  { id: 5, data: '06/05/2024', descricao: 'Elétrica - Pontos e fiação', categoria: 'Mão de Obra', fornecedor: 'Eletricista Carlos', forma: 'Transferência', valor: 1250, status: 'pago' },
  { id: 6, data: '05/05/2024', descricao: 'Tinta acrílica 18L (2 un)', categoria: 'Material', fornecedor: 'Leroy Merlin', forma: 'Cartão de Crédito', valor: 358, status: 'a_pagar' },
  { id: 7, data: '03/05/2024', descricao: 'Piso porcelanato 60x60 (45m²)', categoria: 'Material', fornecedor: 'Portobello Shop', forma: 'Boleto', valor: 2250, status: 'a_pagar' },
  { id: 8, data: '02/05/2024', descricao: 'Mão de obra - Reboco', categoria: 'Mão de Obra', fornecedor: 'João Pedreiro', forma: 'Dinheiro', valor: 900, status: 'vencido' },
  { id: 9, data: '01/05/2024', descricao: 'Janela alumínio 1,20x1,00 (2 un)', categoria: 'Material', fornecedor: 'Alumínios São Paulo', forma: 'Boleto', valor: 1180, status: 'vencido' },
  { id: 10, data: '30/04/2024', descricao: 'Caixa de passagem 4x2 (10 un)', categoria: 'Material', fornecedor: 'Hidráulica Silva', forma: 'PIX', valor: 95, status: 'pago' },
  { id: 11, data: '28/04/2024', descricao: 'Impermeabilizante 18L', categoria: 'Material', fornecedor: 'Leroy Merlin', forma: 'Cartão de Crédito', valor: 620, status: 'pago' },
  { id: 12, data: '25/04/2024', descricao: 'Mão de obra - Pintura', categoria: 'Mão de Obra', fornecedor: 'Pintor Rivaldo', forma: 'Transferência', valor: 1100, status: 'a_pagar' },
];

type AbaDespesas = 'todas' | 'a_pagar' | 'vencidas' | 'pagas';

const fmtBrl = (v: number) =>
  v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });

const statusBadge = (s: StatusDespesa) => {
  switch (s) {
    case 'pago':
      return { label: 'Pago', cls: 'bg-emerald-100 text-emerald-700' };
    case 'a_pagar':
      return { label: 'A Pagar', cls: 'bg-amber-100 text-amber-700' };
    case 'vencido':
      return { label: 'Vencido', cls: 'bg-red-100 text-red-700' };
  }
};

const categoriaBadge = (c: Categoria) => {
  switch (c) {
    case 'Material':
      return 'bg-blue-50 text-blue-600';
    case 'Mão de Obra':
      return 'bg-purple-50 text-purple-600';
    case 'Serviço':
      return 'bg-amber-50 text-amber-600';
    case 'Outros':
      return 'bg-slate-100 text-slate-600';
  }
};

const formaIcon = (f: FormaPagamento) => {
  switch (f) {
    case 'PIX':
      return ArrowLeftRight;
    case 'Transferência':
      return ArrowLeftRight;
    case 'Cartão de Crédito':
      return CreditCard;
    case 'Boleto':
      return FileText;
    case 'Dinheiro':
      return Banknote;
  }
};

export default function Despesas() {
  const [aba, setAba] = useState<AbaDespesas>('todas');
  const [busca, setBusca] = useState('');
  const [selecionadaId, setSelecionadaId] = useState<number | null>(6);

  const lista = useMemo(() => {
    return despesasMock.filter((d) => {
      if (aba === 'a_pagar' && d.status !== 'a_pagar') return false;
      if (aba === 'vencidas' && d.status !== 'vencido') return false;
      if (aba === 'pagas' && d.status !== 'pago') return false;
      if (busca && !`${d.descricao} ${d.fornecedor}`.toLowerCase().includes(busca.toLowerCase()))
        return false;
      return true;
    });
  }, [aba, busca]);

  const selecionada = despesasMock.find((d) => d.id === selecionadaId) ?? null;

  const contagens = {
    todas: despesasMock.length,
    a_pagar: despesasMock.filter((d) => d.status === 'a_pagar').length,
    vencidas: despesasMock.filter((d) => d.status === 'vencido').length,
    pagas: despesasMock.filter((d) => d.status === 'pago').length,
  };

  const abas: { id: AbaDespesas; label: string; n?: number }[] = [
    { id: 'todas', label: 'Todas as Despesas' },
    { id: 'a_pagar', label: 'A Pagar', n: contagens.a_pagar },
    { id: 'vencidas', label: 'Vencidas', n: contagens.vencidas },
    { id: 'pagas', label: 'Pagas', n: contagens.pagas },
  ];

  return (
    <div className="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-semibold text-slate-900">Despesas</h1>
            <p className="text-sm text-slate-500">
              Controle todos os gastos das suas obras em um só lugar
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
              <Download className="h-4 w-4" /> Exportar
            </button>
            <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
              <Plus className="h-4 w-4" /> Nova Despesa
            </button>
            <button className="h-9 w-9 rounded-lg border border-slate-200 bg-white grid place-items-center text-slate-500 hover:bg-slate-50">
              <SlidersHorizontal className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <KpiDespesa label="Total de Despesas" valor={fmtBrl(152750)} ajuda="100% do total" icon={Wallet2} cor="primary" />
          <KpiDespesa label="Pago" valor={<span className="text-emerald-600">{fmtBrl(98250)}</span>} ajuda="64,3% do total" icon={CheckCircle2} cor="emerald" />
          <KpiDespesa label="A Pagar" valor={<span className="text-amber-600">{fmtBrl(42350)}</span>} ajuda="27,7% do total" icon={Clock} cor="amber" />
          <KpiDespesa label="Vencido" valor={<span className="text-red-600">{fmtBrl(12150)}</span>} ajuda="8,0% do total" icon={AlertTriangle} cor="red" />
          <KpiDespesa label="Previsto (Orçado)" valor={fmtBrl(210300)} ajuda="Orçado total da obra" icon={ShoppingCart} cor="blue" />
        </div>

        {/* Filtros */}
        <div className="bg-white rounded-xl border border-slate-200 p-3 grid grid-cols-1 lg:grid-cols-[260px_180px_180px_180px_1fr] gap-2">
          <SelectFalso
            icone={
              <div className="h-7 w-9 rounded-md bg-slate-200 grid place-items-center text-slate-500 shrink-0">
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
            }
            titulo="Casa Jardim Paulista"
            subtitulo="Obra #2024-001"
          />
          <SelectFalso titulo="Todas as categorias" />
          <SelectFalso titulo="Todos os status" />
          <SelectFalso icone={<CalendarDays className="h-4 w-4 text-slate-400" />} titulo="01/01/2024 - 31/12/2024" />
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar despesa..."
              className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-200"
            />
          </div>
        </div>

        {/* Tabela */}
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div className="border-b border-slate-100 px-4 pt-2 flex gap-6 overflow-x-auto">
            {abas.map((a) => (
              <button
                key={a.id}
                onClick={() => setAba(a.id)}
                className={`text-sm pb-3 -mb-px border-b-2 whitespace-nowrap ${
                  aba === a.id
                    ? 'border-primary-600 text-primary-700 font-medium'
                    : 'border-transparent text-slate-500 hover:text-slate-700'
                }`}
              >
                {a.label}
                {typeof a.n === 'number' && (
                  <span className="ml-1 text-xs text-slate-400">({a.n})</span>
                )}
                {a.id === 'todas' && (
                  <span className="ml-1 text-xs text-slate-400">({contagens.todas})</span>
                )}
              </button>
            ))}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                  <th className="text-left px-5 py-3 font-medium">Data</th>
                  <th className="text-left px-3 py-3 font-medium">Descrição</th>
                  <th className="text-left px-3 py-3 font-medium">Categoria</th>
                  <th className="text-left px-3 py-3 font-medium">Fornecedor</th>
                  <th className="text-left px-3 py-3 font-medium">Forma de Pagamento</th>
                  <th className="text-left px-3 py-3 font-medium">Valor</th>
                  <th className="text-left px-3 py-3 font-medium">Status</th>
                  <th className="text-left px-3 py-3 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {lista.map((d) => {
                  const FormaIcon = formaIcon(d.forma);
                  const sb = statusBadge(d.status);
                  return (
                    <tr
                      key={d.id}
                      onClick={() => setSelecionadaId(d.id)}
                      className={`hover:bg-slate-50 cursor-pointer ${
                        selecionadaId === d.id ? 'bg-primary-50/30' : ''
                      }`}
                    >
                      <td className="px-5 py-3 text-slate-700 whitespace-nowrap">{d.data}</td>
                      <td className="px-3 py-3 text-slate-900">{d.descricao}</td>
                      <td className="px-3 py-3">
                        <span className={`px-2 py-0.5 rounded-md text-xs font-medium ${categoriaBadge(d.categoria)}`}>
                          {d.categoria}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-slate-700">{d.fornecedor}</td>
                      <td className="px-3 py-3 text-slate-700">
                        <span className="inline-flex items-center gap-2">
                          <FormaIcon className="h-4 w-4 text-slate-400" />
                          {d.forma}
                        </span>
                      </td>
                      <td className="px-3 py-3 font-medium text-slate-900">{fmtBrl(d.valor)}</td>
                      <td className="px-3 py-3">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${sb.cls}`}>
                          {sb.label}
                        </span>
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
          {/* Paginação */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
            <div>Mostrando 1 a 12 de 45 despesas</div>
            <div className="flex items-center gap-1">
              <BtnPag><ChevronLeft className="h-4 w-4" /></BtnPag>
              <BtnPag ativo>1</BtnPag>
              <BtnPag>2</BtnPag>
              <BtnPag>3</BtnPag>
              <span className="px-1">...</span>
              <BtnPag>4</BtnPag>
              <BtnPag>4</BtnPag>
              <BtnPag><ChevronRight className="h-4 w-4" /></BtnPag>
            </div>
          </div>
        </div>
      </div>

      {selecionada && (
        <DetalheDespesa
          despesa={selecionada}
          onFechar={() => setSelecionadaId(null)}
        />
      )}
    </div>
  );
}

function KpiDespesa({
  label,
  valor,
  ajuda,
  icon: Icon,
  cor,
}: {
  label: string;
  valor: React.ReactNode;
  ajuda?: string;
  icon: typeof Wallet;
  cor: 'primary' | 'emerald' | 'amber' | 'red' | 'blue';
}) {
  const map = {
    primary: 'bg-primary-50 text-primary-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    red: 'bg-red-50 text-red-600',
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
      <div className="mt-2 text-xl font-semibold text-slate-900">{valor}</div>
      {ajuda && <div className="text-[11px] text-slate-500 mt-1">{ajuda}</div>}
    </div>
  );
}

function SelectFalso({
  titulo,
  subtitulo,
  icone,
}: {
  titulo: string;
  subtitulo?: string;
  icone?: React.ReactNode;
}) {
  return (
    <button className="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-700 hover:bg-slate-100">
      {icone}
      <div className="flex-1 text-left min-w-0">
        <div className="text-sm text-slate-900 truncate">{titulo}</div>
        {subtitulo && <div className="text-[11px] text-slate-500 truncate">{subtitulo}</div>}
      </div>
      <ChevronDown className="h-4 w-4 text-slate-400 shrink-0" />
    </button>
  );
}

function BtnPag({
  children,
  ativo,
}: {
  children: React.ReactNode;
  ativo?: boolean;
}) {
  return (
    <button
      className={`h-7 min-w-7 px-2 rounded-md text-xs font-medium grid place-items-center ${
        ativo
          ? 'bg-primary-600 text-white'
          : 'text-slate-600 hover:bg-slate-100'
      }`}
    >
      {children}
    </button>
  );
}

function DetalheDespesa({
  despesa,
  onFechar,
}: {
  despesa: Despesa;
  onFechar: () => void;
}) {
  const sb = statusBadge(despesa.status);
  return (
    <aside className="bg-white rounded-xl border border-slate-200 self-start sticky top-4">
      <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-slate-900">Detalhes da Despesa</h3>
        <button onClick={onFechar} className="text-slate-400 hover:text-slate-700">
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="p-5 space-y-5">
        <div>
          <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${sb.cls}`}>
            {sb.label}
          </span>
        </div>

        <div className="flex items-start gap-3">
          <div className="h-10 w-10 rounded-full bg-primary-50 text-primary-600 grid place-items-center">
            <ShoppingCart className="h-4 w-4" />
          </div>
          <div>
            <div className="text-sm font-semibold text-slate-900">{despesa.descricao}</div>
            <div className="text-base font-bold text-slate-900 mt-1">{fmtBrl(despesa.valor)}</div>
          </div>
        </div>

        <div>
          <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
            Informações Gerais
          </h4>
          <div className="space-y-3 text-sm">
            <CampoInfo label="Data da despesa">
              <div className="input flex items-center justify-between bg-slate-50">
                <span>{despesa.data}</span>
                <CalendarDays className="h-4 w-4 text-slate-400" />
              </div>
            </CampoInfo>

            <CampoInfo label="Categoria">
              <span className={`inline-block px-2 py-0.5 rounded-md text-xs font-medium ${categoriaBadge(despesa.categoria)}`}>
                {despesa.categoria}
              </span>
            </CampoInfo>

            <CampoInfo label="Fornecedor">
              <div className="text-sm text-slate-900">{despesa.fornecedor}</div>
            </CampoInfo>

            <CampoInfo label="Forma de pagamento">
              <div className="text-sm text-slate-900">{despesa.forma}</div>
            </CampoInfo>

            <CampoInfo label="Vencimento">
              <div className="input flex items-center justify-between bg-slate-50">
                <span>15/05/2024</span>
                <CalendarDays className="h-4 w-4 text-slate-400" />
              </div>
            </CampoInfo>

            <CampoInfo label="Projeto / Obra">
              <div className="text-sm text-slate-900">Casa Jardim Paulista</div>
              <div className="text-[11px] text-slate-500">Obra #2024-001</div>
            </CampoInfo>

            <CampoInfo label="Observações">
              <div className="text-sm text-slate-700 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100">
                Tinta para paredes internas - Cor: Branca Neve
              </div>
            </CampoInfo>
          </div>
        </div>

        <div>
          <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
            Anexos
          </h4>
          <div className="grid grid-cols-2 gap-3">
            <div className="border border-slate-200 rounded-lg overflow-hidden">
              <div className="h-20 bg-slate-100 grid place-items-center text-slate-400">
                <FileText className="h-6 w-6" />
              </div>
              <div className="px-2 py-1.5">
                <div className="text-[11px] font-medium text-slate-700 truncate">nota_23562.pdf</div>
                <div className="text-[10px] text-slate-400">125 KB</div>
              </div>
            </div>
            <button className="border border-dashed border-slate-300 rounded-lg flex flex-col items-center justify-center gap-1 text-primary-600 hover:bg-primary-50/40">
              <Plus className="h-4 w-4" />
              <span className="text-[11px] font-medium">Adicionar</span>
              <span className="text-[10px] text-slate-400">Novo anexo</span>
            </button>
          </div>
        </div>
      </div>

      <div className="px-5 py-3 border-t border-slate-100 flex items-center gap-2">
        <button className="flex-1 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
          Editar
        </button>
        <button className="flex-1 text-sm text-white px-3 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
          Marcar como pago
        </button>
      </div>
    </aside>
  );
}

function CampoInfo({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs text-slate-500 mb-1">{label}</div>
      {children}
    </div>
  );
}
