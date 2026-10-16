import { useMemo, useState } from 'react';
import {
  Search,
  ChevronDown,
  SlidersHorizontal,
  Download,
  Plus,
  Filter,
  Star,
  X,
  Phone,
  Mail,
  MessageCircle,
  MoreHorizontal,
  MapPin,
  ChevronLeft,
  ChevronRight,
  Droplet,
  Zap,
  Paintbrush,
  Square,
  Hammer,
  Package,
  Layers,
  Wrench,
  Building2,
} from 'lucide-react';

type Categoria =
  | 'Material'
  | 'Hidráulica'
  | 'Elétrica'
  | 'Acabamento'
  | 'Vidraçaria'
  | 'Madeira'
  | 'Revestimento'
  | 'Ferragens'
  | 'Impermeabilização'
  | 'Geral';

type Fornecedor = {
  id: number;
  nome: string;
  subtitulo: string;
  categoria: Categoria;
  contato: string;
  telefone: string;
  email: string;
  avaliacao: number;
  status: 'Ativo' | 'Inativo';
  iconKey: keyof typeof iconMap;
  cor: string;
};

const iconMap = {
  building: Building2,
  drop: Droplet,
  bolt: Zap,
  brush: Paintbrush,
  square: Square,
  hammer: Hammer,
  package: Package,
  layers: Layers,
  wrench: Wrench,
};

const catCls: Record<Categoria, string> = {
  Material: 'bg-blue-100 text-blue-700',
  Hidráulica: 'bg-cyan-100 text-cyan-700',
  Elétrica: 'bg-amber-100 text-amber-700',
  Acabamento: 'bg-violet-100 text-violet-700',
  Vidraçaria: 'bg-sky-100 text-sky-700',
  Madeira: 'bg-orange-100 text-orange-700',
  Revestimento: 'bg-pink-100 text-pink-700',
  Ferragens: 'bg-slate-100 text-slate-700',
  Impermeabilização: 'bg-emerald-100 text-emerald-700',
  Geral: 'bg-zinc-100 text-zinc-700',
};

const fornecedores: Fornecedor[] = [
  { id: 1, nome: 'ConstruMais Materiais', subtitulo: 'Materiais de Construção', categoria: 'Material', contato: 'João Pedro', telefone: '(11) 99987-6543', email: 'vendas@construmais.com.br', avaliacao: 4.8, status: 'Ativo', iconKey: 'building', cor: 'bg-blue-100 text-blue-600' },
  { id: 2, nome: 'Hidráulica Silva', subtitulo: 'Hidráulica', categoria: 'Hidráulica', contato: 'Carlos Silva', telefone: '(11) 99876-1122', email: 'contato@hidraulicasilva.com.br', avaliacao: 4.6, status: 'Ativo', iconKey: 'drop', cor: 'bg-cyan-100 text-cyan-600' },
  { id: 3, nome: 'Elétrica Luz Forte', subtitulo: 'Materiais Elétricos', categoria: 'Elétrica', contato: 'Marcos Oliveira', telefone: '(11) 97555-3344', email: 'vendas@luzforte.com.br', avaliacao: 4.7, status: 'Ativo', iconKey: 'bolt', cor: 'bg-amber-100 text-amber-600' },
  { id: 4, nome: 'Tinta&Cia', subtitulo: 'Tintas e Acabamentos', categoria: 'Acabamento', contato: 'Fernanda Costa', telefone: '(11) 98444-7788', email: 'comercial@tintacia.com.br', avaliacao: 4.5, status: 'Ativo', iconKey: 'brush', cor: 'bg-violet-100 text-violet-600' },
  { id: 5, nome: 'Vidraçaria Paulista', subtitulo: 'Vidros e Espelhos', categoria: 'Vidraçaria', contato: 'Ricardo Almeida', telefone: '(11) 96666-8899', email: 'contato@vidracariapaulista.com.br', avaliacao: 4.4, status: 'Ativo', iconKey: 'square', cor: 'bg-sky-100 text-sky-600' },
  { id: 6, nome: 'Madeireira Santos', subtitulo: 'Madeira e MDF', categoria: 'Madeira', contato: 'Paulo Santos', telefone: '(11) 99777-6655', email: 'vendas@madeireirasantos.com.br', avaliacao: 4.3, status: 'Ativo', iconKey: 'hammer', cor: 'bg-orange-100 text-orange-600' },
  { id: 7, nome: 'Portobello Shop', subtitulo: 'Pisos e Revestimentos', categoria: 'Revestimento', contato: 'Juliana Ferraz', telefone: '(11) 95000-1234', email: 'contato@portobelloshop.com.br', avaliacao: 4.9, status: 'Ativo', iconKey: 'layers', cor: 'bg-pink-100 text-pink-600' },
  { id: 8, nome: 'Ferragens São Jorge', subtitulo: 'Ferragens e Ferramentas', categoria: 'Ferragens', contato: 'Roberto Jorge', telefone: '(11) 94333-2211', email: 'vendas@ferragenssaojorge.com.br', avaliacao: 4.2, status: 'Inativo', iconKey: 'wrench', cor: 'bg-slate-100 text-slate-600' },
  { id: 9, nome: 'Impermeabiliza Já', subtitulo: 'Impermeabilizantes', categoria: 'Impermeabilização', contato: 'André Lima', telefone: '(11) 98888-1212', email: 'contato@impermeabilizaja.com.br', avaliacao: 4.6, status: 'Ativo', iconKey: 'package', cor: 'bg-emerald-100 text-emerald-600' },
  { id: 10, nome: 'Leroy Merlin', subtitulo: 'Materiais Diversos', categoria: 'Geral', contato: 'Equipe Comercial', telefone: '(11) 4007-1380', email: 'comercial@leroymerlin.com.br', avaliacao: 4.7, status: 'Ativo', iconKey: 'building', cor: 'bg-emerald-100 text-emerald-600' },
];

function Estrelas({ valor, mostrarNumero = true, tamanho = 'sm' }: { valor: number; mostrarNumero?: boolean; tamanho?: 'sm' | 'md' }) {
  const h = tamanho === 'md' ? 'h-4 w-4' : 'h-3 w-3';
  return (
    <div className="inline-flex items-center gap-1">
      <Star className={`${h} text-amber-400 fill-amber-400`} />
      {mostrarNumero && <span className="text-xs text-slate-700">{valor.toString().replace('.', ',')}</span>}
    </div>
  );
}

export default function Fornecedores() {
  const [busca, setBusca] = useState('');
  const [fornecedorId, setFornecedorId] = useState<number | null>(1);

  const lista = useMemo(() => {
    if (!busca.trim()) return fornecedores;
    return fornecedores.filter((f) => f.nome.toLowerCase().includes(busca.toLowerCase()));
  }, [busca]);

  const selecionado = fornecedores.find((f) => f.id === fornecedorId) ?? null;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Fornecedores</h1>
          <p className="text-sm text-slate-500">Gerencie seus fornecedores e contatos parceiros.</p>
        </div>
        <div className="flex items-center gap-2">
          <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <Download className="h-4 w-4" /> Exportar
          </button>
          <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
            <Plus className="h-4 w-4" /> Novo fornecedor
          </button>
          <button className="h-9 w-9 grid place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
            <Filter className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className={`grid grid-cols-1 ${selecionado ? 'xl:grid-cols-[1fr_360px]' : ''} gap-5`}>
        <div className="space-y-5">
          {/* KPIs */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <KpiForn label="Total de Fornecedores" valor="86" sub="cadastrados" icon={Building2} cor="primary" />
            <KpiForn label="Fornecedores Ativos" valor="72" sub="83,7% do total" icon={CheckIcon} cor="emerald" />
            <KpiForn label="Avaliação Média" valor="4,6" sub="baseado em 128 avaliações" icon={Star} cor="amber" abaixo={<Estrelas valor={4.6} mostrarNumero={false} />} />
            <KpiForn label="Compras no Mês" valor="R$ 126.450,00" sub="com 23 fornecedores" icon={CartIcon} cor="orange" />
          </div>

          {/* Filtros */}
          <div className="bg-white rounded-xl border border-slate-200 p-3 grid grid-cols-1 md:grid-cols-[1fr_180px_180px_180px_150px] gap-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                placeholder="Buscar fornecedor..."
                className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-primary-200 focus:border-primary-400 outline-none"
              />
            </div>
            <SelectFake label="Todas as categorias" />
            <SelectFake label="Todos os status" />
            <SelectFake label="Avaliação" />
            <button className="inline-flex items-center justify-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
              <SlidersHorizontal className="h-4 w-4" /> Mais filtros
            </button>
          </div>

          {/* Tabela */}
          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-slate-50/50">
                  <tr className="text-xs uppercase tracking-wide text-slate-500">
                    <th className="text-left px-5 py-3 font-medium">Fornecedor</th>
                    <th className="text-left px-3 py-3 font-medium">Categoria</th>
                    <th className="text-left px-3 py-3 font-medium">Contato</th>
                    <th className="text-left px-3 py-3 font-medium">Telefone</th>
                    <th className="text-left px-3 py-3 font-medium">E-mail</th>
                    <th className="text-left px-3 py-3 font-medium">Avaliação</th>
                    <th className="text-left px-3 py-3 font-medium">Status</th>
                    <th className="text-left px-3 py-3 font-medium">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {lista.map((f) => {
                    const Icon = iconMap[f.iconKey];
                    return (
                      <tr
                        key={f.id}
                        onClick={() => setFornecedorId(f.id)}
                        className={`cursor-pointer hover:bg-slate-50 ${f.id === fornecedorId ? 'bg-primary-50/40' : ''}`}
                      >
                        <td className="px-5 py-3">
                          <div className="flex items-center gap-3">
                            <div className={`h-9 w-9 rounded-lg grid place-items-center shrink-0 ${f.cor}`}>
                              <Icon className="h-4 w-4" />
                            </div>
                            <div>
                              <div className="font-medium text-slate-900">{f.nome}</div>
                              <div className="text-[11px] text-slate-500">{f.subtitulo}</div>
                            </div>
                          </div>
                        </td>
                        <td className="px-3 py-3">
                          <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${catCls[f.categoria]}`}>
                            {f.categoria}
                          </span>
                        </td>
                        <td className="px-3 py-3 text-slate-700">{f.contato}</td>
                        <td className="px-3 py-3 text-slate-600">{f.telefone}</td>
                        <td className="px-3 py-3 text-slate-600">{f.email}</td>
                        <td className="px-3 py-3"><Estrelas valor={f.avaliacao} /></td>
                        <td className="px-3 py-3">
                          <span
                            className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                              f.status === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                            }`}
                          >
                            {f.status}
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
            <div className="px-5 py-3 flex items-center justify-between text-sm text-slate-500 border-t border-slate-100">
              <div>Mostrando 1 a {lista.length} de 86 fornecedores</div>
              <Paginacao />
            </div>
          </div>
        </div>

        {/* Painel lateral detalhes */}
        {selecionado && <PainelFornecedor f={selecionado} onClose={() => setFornecedorId(null)} />}
      </div>
    </div>
  );
}

function PainelFornecedor({ f, onClose }: { f: Fornecedor; onClose: () => void }) {
  const Icon = iconMap[f.iconKey];
  return (
    <aside className="bg-white rounded-xl border border-slate-200 p-5 self-start sticky top-4 h-fit">
      <div className="flex items-center justify-between">
        <div className="text-base font-semibold text-slate-900">{f.nome}</div>
        <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="mt-4 h-32 rounded-lg bg-slate-50 grid place-items-center text-slate-400 border border-slate-100">
        <div className="flex flex-col items-center gap-2">
          <div className={`h-12 w-12 rounded-lg grid place-items-center ${f.cor}`}>
            <Icon className="h-6 w-6" />
          </div>
          <div className="text-[11px] uppercase tracking-wider font-semibold text-slate-500">
            {f.nome}
          </div>
        </div>
      </div>

      <div className="mt-4 flex items-center justify-between">
        <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> {f.status}
        </span>
        <div className="inline-flex items-center gap-2">
          <Estrelas valor={f.avaliacao} mostrarNumero={false} tamanho="md" />
          <span className="text-xs text-slate-600">{f.avaliacao.toString().replace('.', ',')} (32 avaliações)</span>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-4 gap-2 text-xs">
        <BtnAcao icon={Phone} label="Ligar" />
        <BtnAcao icon={Mail} label="E-mail" />
        <BtnAcao icon={MessageCircle} label="WhatsApp" />
        <BtnAcao icon={MoreHorizontal} label="Mais" />
      </div>

      <Section titulo="Informações">
        <Linha label="Categoria" valor={f.subtitulo} />
        <Linha label="CNPJ" valor="12.345.678/0001-90" />
        <Linha label="Inscrição Estadual" valor="123.456.789.112" />
        <Linha
          label="Endereço"
          valor={
            <>
              Rua das Pedras, 123 - Brás<br />São Paulo, SP - 03026-000
            </>
          }
        />
        <button className="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 mt-1">
          <MapPin className="h-3 w-3" /> Ver no mapa
        </button>
      </Section>

      <Section titulo="Contato Principal">
        <LinhaIcon icon={UserIcon} valor={f.contato} />
        <LinhaIcon icon={Mail} valor={f.email} />
        <LinhaIcon icon={Phone} valor="(11) 99987-6543" />
      </Section>

      <Section titulo="Sobre o fornecedor">
        <p className="text-sm text-slate-600 leading-relaxed">
          Fornecedor de materiais de construção em geral, com foco em qualidade e agilidade na entrega.
        </p>
      </Section>

      <Section titulo="Estatísticas">
        <Linha label="Compras realizadas" valor="28" />
        <Linha label="Total comprado" valor="R$ 248.750,00" />
        <Linha label="Última compra" valor="08/05/2024" />
      </Section>

      <button className="mt-5 w-full text-sm text-primary-600 font-medium px-3 py-2.5 rounded-lg border border-primary-200 hover:bg-primary-50">
        Ver histórico de compras
      </button>
    </aside>
  );
}

function KpiForn({
  label,
  valor,
  sub,
  icon: Icon,
  cor,
  abaixo,
}: {
  label: string;
  valor: string;
  sub: string;
  icon: React.ComponentType<{ className?: string }>;
  cor: 'primary' | 'emerald' | 'amber' | 'orange';
  abaixo?: React.ReactNode;
}) {
  const map = {
    primary: 'bg-primary-50 text-primary-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    orange: 'bg-orange-50 text-orange-600',
  } as const;
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4">
      <div className="flex items-start justify-between">
        <div className="text-xs text-slate-500">{label}</div>
        <div className={`h-8 w-8 rounded-lg grid place-items-center ${map[cor]}`}>
          <Icon className="h-4 w-4" />
        </div>
      </div>
      <div className="mt-2 text-2xl font-bold text-slate-900">{valor}</div>
      {abaixo && <div className="mt-1">{abaixo}</div>}
      <div className="mt-1 text-[11px] text-slate-500">{sub}</div>
    </div>
  );
}

function SelectFake({ label }: { label: string }) {
  return (
    <button className="inline-flex items-center justify-between gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 hover:bg-slate-100 w-full">
      <span className="truncate">{label}</span>
      <ChevronDown className="h-4 w-4 text-slate-400 shrink-0" />
    </button>
  );
}

function Section({ titulo, children }: { titulo: string; children: React.ReactNode }) {
  return (
    <div className="mt-5 pt-4 border-t border-slate-100">
      <div className="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">{titulo}</div>
      <div className="space-y-1.5">{children}</div>
    </div>
  );
}

function Linha({ label, valor }: { label: string; valor: React.ReactNode }) {
  return (
    <div>
      <div className="text-[11px] text-slate-500">{label}</div>
      <div className="text-sm text-slate-800">{valor}</div>
    </div>
  );
}

function LinhaIcon({ icon: Icon, valor }: { icon: React.ComponentType<{ className?: string }>; valor: string }) {
  return (
    <div className="flex items-center gap-2 text-sm text-slate-700">
      <Icon className="h-4 w-4 text-slate-400" />
      <span className="truncate">{valor}</span>
    </div>
  );
}

function BtnAcao({ icon: Icon, label }: { icon: React.ComponentType<{ className?: string }>; label: string }) {
  return (
    <button className="flex flex-col items-center gap-1 px-2 py-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700">
      <Icon className="h-4 w-4 text-slate-500" />
      <span className="text-[11px]">{label}</span>
    </button>
  );
}

function Paginacao() {
  const [pag, setPag] = useState(1);
  const paginas = [1, 2, 3, '...', 9] as const;
  return (
    <div className="inline-flex items-center gap-1">
      <button className="h-7 w-7 grid place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50">
        <ChevronLeft className="h-3.5 w-3.5" />
      </button>
      {paginas.map((p, i) =>
        p === '...' ? (
          <span key={i} className="px-2 text-slate-400">…</span>
        ) : (
          <button
            key={i}
            onClick={() => setPag(p as number)}
            className={`h-7 w-7 grid place-items-center rounded-md text-sm ${
              pag === p ? 'bg-primary-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {p}
          </button>
        ),
      )}
      <button className="h-7 w-7 grid place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50">
        <ChevronRight className="h-3.5 w-3.5" />
      </button>
    </div>
  );
}

function CheckIcon(props: React.ComponentProps<'svg'>) {
  return (
    <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20 6 9 17l-5-5" />
    </svg>
  );
}

function CartIcon(props: React.ComponentProps<'svg'>) {
  return (
    <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </svg>
  );
}

function UserIcon(props: React.ComponentProps<'svg'>) {
  return (
    <svg {...props} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="12" cy="7" r="4" />
    </svg>
  );
}
