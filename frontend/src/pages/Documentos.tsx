import { useMemo, useState } from 'react';
import {
  FileText,
  FolderOpen,
  Folder,
  PieChart as PieIcon,
  CalendarDays,
  ChevronDown,
  Search,
  Filter,
  MoreHorizontal,
  X,
  Eye,
  Download,
  Share2,
  ChevronLeft,
  ChevronRight,
  FileSpreadsheet,
  FileImage,
  Plus,
  Files,
} from 'lucide-react';

type StatusDoc = 'assinado' | 'aprovado' | 'valido' | 'pago' | 'em_analise' | 'sem';
type Categoria = 'Contratos' | 'Documentação Legal' | 'Orçamentos' | 'Projetos' | 'Seguros' | 'Impostos' | 'Fotos';
type TipoIcone = 'pdf' | 'xls' | 'doc' | 'img';

interface Documento {
  id: number;
  nome: string;
  iconeTipo: TipoIcone;
  categoria: Categoria;
  tipo: string;
  obra: string;
  obraCod: string;
  data: string;
  status: StatusDoc;
}

const docs: Documento[] = [
  { id: 1, nome: 'Contrato de Compra e Venda.pdf', iconeTipo: 'pdf', categoria: 'Contratos', tipo: 'Contrato', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '10/05/2024', status: 'assinado' },
  { id: 2, nome: 'Escritura do Imóvel.pdf', iconeTipo: 'pdf', categoria: 'Documentação Legal', tipo: 'Escritura', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '08/05/2024', status: 'assinado' },
  { id: 3, nome: 'Planilha Orçamentária.xlsx', iconeTipo: 'xls', categoria: 'Orçamentos', tipo: 'Planilha', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '07/05/2024', status: 'aprovado' },
  { id: 4, nome: 'Alvará de Reforma.docx', iconeTipo: 'doc', categoria: 'Documentação Legal', tipo: 'Alvará', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '06/05/2024', status: 'valido' },
  { id: 5, nome: 'Planta Baixa.png', iconeTipo: 'img', categoria: 'Projetos', tipo: 'Imagem', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '06/05/2024', status: 'aprovado' },
  { id: 6, nome: 'ART - Responsável Técnico.pdf', iconeTipo: 'pdf', categoria: 'Documentação Legal', tipo: 'ART', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '05/05/2024', status: 'valido' },
  { id: 7, nome: 'Apólice de Seguro.pdf', iconeTipo: 'pdf', categoria: 'Seguros', tipo: 'Seguro', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '01/05/2024', status: 'valido' },
  { id: 8, nome: 'Comprovante de IPTU.pdf', iconeTipo: 'pdf', categoria: 'Impostos', tipo: 'Comprovante', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '28/04/2024', status: 'pago' },
  { id: 9, nome: 'Foto Fachada Atual.jpg', iconeTipo: 'img', categoria: 'Fotos', tipo: 'Imagem', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '27/04/2024', status: 'sem' },
  { id: 10, nome: 'Proposta do Arquiteto.pdf', iconeTipo: 'pdf', categoria: 'Projetos', tipo: 'Proposta', obra: 'Casa Jardim Paulista', obraCod: 'Obra #2024-001', data: '25/04/2024', status: 'em_analise' },
];

const statusBadge = (s: StatusDoc) => {
  switch (s) {
    case 'assinado':
      return { label: 'Assinado', cls: 'bg-emerald-100 text-emerald-700' };
    case 'aprovado':
      return { label: 'Aprovado', cls: 'bg-emerald-100 text-emerald-700' };
    case 'valido':
      return { label: 'Válido', cls: 'bg-emerald-100 text-emerald-700' };
    case 'pago':
      return { label: 'Pago', cls: 'bg-emerald-100 text-emerald-700' };
    case 'em_analise':
      return { label: 'Em análise', cls: 'bg-amber-100 text-amber-700' };
    case 'sem':
      return { label: '-', cls: 'text-slate-400' };
  }
};

const categoriaBadge = (c: Categoria) => {
  const map: Record<Categoria, string> = {
    Contratos: 'bg-purple-50 text-purple-600',
    'Documentação Legal': 'bg-blue-50 text-blue-600',
    Orçamentos: 'bg-emerald-50 text-emerald-600',
    Projetos: 'bg-amber-50 text-amber-600',
    Seguros: 'bg-indigo-50 text-indigo-600',
    Impostos: 'bg-orange-50 text-orange-600',
    Fotos: 'bg-cyan-50 text-cyan-600',
  };
  return map[c];
};

function IconeArquivo({ tipo, lg }: { tipo: TipoIcone; lg?: boolean }) {
  const tam = lg ? 'h-10 w-10' : 'h-5 w-5';
  if (tipo === 'pdf')
    return (
      <div className={`${lg ? 'h-32 w-28' : 'h-10 w-9'} rounded-md bg-red-50 text-red-600 grid place-items-center shrink-0`}>
        <div className="text-center">
          <FileText className={tam} />
          {lg && <div className="text-xs font-bold mt-1">PDF</div>}
        </div>
      </div>
    );
  if (tipo === 'xls')
    return (
      <div className={`${lg ? 'h-32 w-28' : 'h-10 w-9'} rounded-md bg-emerald-50 text-emerald-600 grid place-items-center shrink-0`}>
        <FileSpreadsheet className={tam} />
      </div>
    );
  if (tipo === 'img')
    return (
      <div className={`${lg ? 'h-32 w-28' : 'h-10 w-9'} rounded-md bg-amber-50 text-amber-600 grid place-items-center shrink-0`}>
        <FileImage className={tam} />
      </div>
    );
  return (
    <div className={`${lg ? 'h-32 w-28' : 'h-10 w-9'} rounded-md bg-blue-50 text-blue-600 grid place-items-center shrink-0`}>
      <FileText className={tam} />
    </div>
  );
}

export default function Documentos() {
  const [busca, setBusca] = useState('');
  const [selecionadoId, setSelecionadoId] = useState<number | null>(1);
  const [tab, setTab] = useState<'detalhes' | 'historico'>('detalhes');

  const lista = useMemo(
    () =>
      docs.filter((d) =>
        busca ? `${d.nome} ${d.categoria}`.toLowerCase().includes(busca.toLowerCase()) : true,
      ),
    [busca],
  );

  const selecionado = docs.find((d) => d.id === selecionadoId) ?? null;

  return (
    <div className="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-semibold text-slate-900">Documentos</h1>
            <p className="text-sm text-slate-500">
              Todos os arquivos e documentos dos seus projetos e obras
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
              <Plus className="h-4 w-4" /> Novo documento
            </button>
            <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-4 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
              <Folder className="h-4 w-4" /> Pastas
            </button>
          </div>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <KpiDoc label="Total de Documentos" valor="128" ajuda="arquivos cadastrados" icon={Files} cor="primary" />
          <KpiDoc
            label="Espaço Utilizado"
            valor="2,45 GB"
            ajuda="de 10 GB utilizados"
            icon={FolderOpen}
            cor="emerald"
            barra={24.5}
          />
          <KpiDoc label="Pastas" valor="18" ajuda="pastas criadas" icon={Folder} cor="amber" />
          <KpiDoc label="Pendentes de Revisão" valor="7" ajuda="documentos" icon={PieIcon} cor="rose" />
          <KpiDoc label="Vencendo em 30 dias" valor="3" ajuda="documentos" icon={CalendarDays} cor="red" />
        </div>

        {/* Filtros */}
        <div className="bg-white rounded-xl border border-slate-200 p-3 grid grid-cols-1 lg:grid-cols-[260px_180px_180px_180px_1fr_120px] gap-2">
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
          <SelectFalso titulo="Todos os tipos" />
          <SelectFalso titulo="Todos os status" />
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar documento..."
              className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-primary-200"
            />
          </div>
          <button className="inline-flex items-center justify-center gap-2 text-sm text-slate-600 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <Filter className="h-4 w-4" /> Filtros
          </button>
        </div>

        {/* Tabela */}
        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-xs uppercase tracking-wide text-slate-500 bg-slate-50">
                  <th className="text-left px-5 py-3 font-medium">Nome do Documento</th>
                  <th className="text-left px-3 py-3 font-medium">Categoria</th>
                  <th className="text-left px-3 py-3 font-medium">Tipo</th>
                  <th className="text-left px-3 py-3 font-medium">Projeto / Obra</th>
                  <th className="text-left px-3 py-3 font-medium">Data</th>
                  <th className="text-left px-3 py-3 font-medium">Status</th>
                  <th className="text-left px-3 py-3 font-medium">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {lista.map((d) => {
                  const sb = statusBadge(d.status);
                  return (
                    <tr
                      key={d.id}
                      onClick={() => setSelecionadoId(d.id)}
                      className={`hover:bg-slate-50 cursor-pointer ${
                        selecionadoId === d.id ? 'bg-primary-50/30' : ''
                      }`}
                    >
                      <td className="px-5 py-3">
                        <div className="flex items-center gap-3">
                          <IconeArquivo tipo={d.iconeTipo} />
                          <span className="font-medium text-slate-900">{d.nome}</span>
                        </div>
                      </td>
                      <td className="px-3 py-3">
                        <span className={`px-2 py-0.5 rounded-md text-xs font-medium ${categoriaBadge(d.categoria)}`}>
                          {d.categoria}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-slate-700">{d.tipo}</td>
                      <td className="px-3 py-3">
                        <div className="text-slate-900">{d.obra}</div>
                        <div className="text-xs text-slate-500">{d.obraCod}</div>
                      </td>
                      <td className="px-3 py-3 text-slate-600 whitespace-nowrap">{d.data}</td>
                      <td className="px-3 py-3">
                        {d.status === 'sem' ? (
                          <span className="text-slate-400">-</span>
                        ) : (
                          <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${sb.cls}`}>
                            {sb.label}
                          </span>
                        )}
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
          <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
            <div>Mostrando 1 a 10 de 128 documentos</div>
            <div className="flex items-center gap-1">
              <BtnPag><ChevronLeft className="h-4 w-4" /></BtnPag>
              <BtnPag ativo>1</BtnPag>
              <BtnPag>2</BtnPag>
              <BtnPag>3</BtnPag>
              <span className="px-1">...</span>
              <BtnPag>13</BtnPag>
              <BtnPag><ChevronRight className="h-4 w-4" /></BtnPag>
            </div>
          </div>
        </div>
      </div>

      {selecionado && (
        <DetalheDoc
          doc={selecionado}
          tab={tab}
          onTab={setTab}
          onFechar={() => setSelecionadoId(null)}
        />
      )}
    </div>
  );
}

function KpiDoc({
  label,
  valor,
  ajuda,
  icon: Icon,
  cor,
  barra,
}: {
  label: string;
  valor: React.ReactNode;
  ajuda?: string;
  icon: typeof Files;
  cor: 'primary' | 'emerald' | 'amber' | 'red' | 'rose';
  barra?: number;
}) {
  const map = {
    primary: 'bg-primary-50 text-primary-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber: 'bg-amber-50 text-amber-600',
    red: 'bg-red-50 text-red-600',
    rose: 'bg-rose-50 text-rose-600',
  } as const;
  return (
    <div className="bg-white rounded-xl border border-slate-200 p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="text-xs text-slate-500">{label}</div>
        <div className={`h-7 w-7 rounded-lg grid place-items-center ${map[cor]}`}>
          <Icon className="h-3.5 w-3.5" />
        </div>
      </div>
      <div className="mt-2 text-2xl font-bold text-slate-900">{valor}</div>
      {ajuda && <div className="text-[11px] text-slate-500 mt-1">{ajuda}</div>}
      {typeof barra === 'number' && (
        <div className="h-1 mt-3 rounded-full bg-slate-100 overflow-hidden">
          <div className="h-full bg-primary-500" style={{ width: `${barra}%` }} />
        </div>
      )}
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

function BtnPag({ children, ativo }: { children: React.ReactNode; ativo?: boolean }) {
  return (
    <button
      className={`h-7 min-w-7 px-2 rounded-md text-xs font-medium grid place-items-center ${
        ativo ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'
      }`}
    >
      {children}
    </button>
  );
}

function DetalheDoc({
  doc,
  tab,
  onTab,
  onFechar,
}: {
  doc: Documento;
  tab: 'detalhes' | 'historico';
  onTab: (t: 'detalhes' | 'historico') => void;
  onFechar: () => void;
}) {
  const sb = statusBadge(doc.status);
  return (
    <aside className="bg-white rounded-xl border border-slate-200 self-start sticky top-4">
      <div className="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
        <h3 className="text-sm font-semibold text-slate-900 truncate">{doc.nome}</h3>
        <button onClick={onFechar} className="text-slate-400 hover:text-slate-700 shrink-0">
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="px-5 pt-5 flex justify-center">
        <IconeArquivo tipo={doc.iconeTipo} lg />
      </div>

      <div className="px-5 py-4 border-b border-slate-100">
        <div className="grid grid-cols-4 gap-2 text-center text-[11px] text-slate-500">
          <AcaoBtn icon={Eye} label="Visualizar" />
          <AcaoBtn icon={Download} label="Download" />
          <AcaoBtn icon={Share2} label="Compartilhar" />
          <AcaoBtn icon={MoreHorizontal} label="Mais" />
        </div>
      </div>

      <div className="px-5 border-b border-slate-100 flex gap-6">
        {(['detalhes', 'historico'] as const).map((t) => (
          <button
            key={t}
            onClick={() => onTab(t)}
            className={`text-sm py-3 -mb-px border-b-2 ${
              tab === t
                ? 'border-primary-600 text-primary-700 font-medium'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {t === 'detalhes' ? 'Detalhes' : 'Histórico'}
          </button>
        ))}
      </div>

      {tab === 'detalhes' ? (
        <div className="p-5 space-y-4 text-sm">
          <Campo label="Categoria">
            <span className={`inline-block px-2 py-0.5 rounded-md text-xs font-medium ${categoriaBadge(doc.categoria)}`}>
              {doc.categoria}
            </span>
          </Campo>
          <Campo label="Tipo">
            <div className="text-slate-900">{doc.tipo}</div>
          </Campo>
          <Campo label="Projeto / Obra">
            <div className="flex items-start gap-2">
              <div className="h-8 w-9 rounded-md bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500 shrink-0">
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
              <div>
                <div className="text-slate-900">{doc.obra}</div>
                <div className="text-[11px] text-slate-500">{doc.obraCod}</div>
              </div>
            </div>
          </Campo>
          <Campo label="Descrição">
            <div className="text-slate-700 text-sm">
              Contrato de compra e venda do imóvel localizado na Rua das Flores, 123.
            </div>
          </Campo>
          <Campo label="Data de envio">
            <div className="text-slate-900">10/05/2024 14:30</div>
          </Campo>
          <Campo label="Enviado por">
            <div className="flex items-center gap-2">
              <div className="h-7 w-7 rounded-full bg-slate-300" />
              <span className="text-slate-900">Matheus Silva</span>
            </div>
          </Campo>
          <Campo label="Status">
            <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${sb.cls}`}>
              {sb.label}
            </span>
          </Campo>
          <Campo label="Vencimento">
            <div className="text-slate-500">-</div>
          </Campo>
          <Campo label="Tamanho">
            <div className="text-slate-900">1,2 MB</div>
          </Campo>
        </div>
      ) : (
        <div className="p-5 text-sm text-slate-500">Sem registros de histórico.</div>
      )}
    </aside>
  );
}

function AcaoBtn({ icon: Icon, label }: { icon: typeof Eye; label: string }) {
  return (
    <button className="flex flex-col items-center gap-1 text-slate-600 hover:text-primary-600">
      <span className="h-9 w-9 rounded-lg bg-slate-50 grid place-items-center">
        <Icon className="h-4 w-4" />
      </span>
      <span className="text-[10px]">{label}</span>
    </button>
  );
}

function Campo({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div className="text-xs text-slate-500 mb-1">{label}</div>
      {children}
    </div>
  );
}
