import { useMemo, useState } from 'react';
import {
  Search,
  SlidersHorizontal,
  Star,
  Phone,
  Video,
  MoreHorizontal,
  Paperclip,
  Image as ImageIcon,
  FileText,
  Smile,
  Send,
  Edit3,
  Settings,
  Mail,
  CalendarDays,
  CheckCheck,
  Plus,
} from 'lucide-react';

type AbaConv = 'todas' | 'nao_lidas' | 'favoritas' | 'arquivadas';

type Conversa = {
  id: number;
  nome: string;
  tipo: 'cliente' | 'fornecedor' | 'equipe' | 'arquiteto';
  ultimaMsg: string;
  hora: string;
  online?: boolean;
  naoLidas?: number;
  iniciais?: string;
  corAvatar?: string;
  favorita?: boolean;
};

const conversas: Conversa[] = [
  { id: 1, nome: 'João Ferreira', tipo: 'cliente', ultimaMsg: 'Perfeito! A visita está confirmada então.', hora: '14:32', online: true, naoLidas: 2, iniciais: 'JF', corAvatar: 'bg-blue-500' },
  { id: 2, nome: 'ConstruMais Materiais', tipo: 'fornecedor', ultimaMsg: 'Segue em anexo a cotação solicitada.', hora: '14:20', naoLidas: 1, iniciais: 'CM', corAvatar: 'bg-amber-500' },
  { id: 3, nome: 'Carlos Almeida', tipo: 'cliente', ultimaMsg: 'Conseguimos ajustar o valor do imóvel?', hora: '13:45', iniciais: 'CA', corAvatar: 'bg-emerald-500' },
  { id: 4, nome: 'Hidráulica Silva', tipo: 'fornecedor', ultimaMsg: 'Serviço concluído conforme combinado.', hora: '11:30', iniciais: 'HS', corAvatar: 'bg-cyan-500' },
  { id: 5, nome: 'Ana Beatriz (Arquiteta)', tipo: 'arquiteto', ultimaMsg: 'Enviei a planta atualizada por e-mail.', hora: '10:15', naoLidas: 1, iniciais: 'AB', corAvatar: 'bg-pink-500' },
  { id: 6, nome: 'Portobello Shop', tipo: 'fornecedor', ultimaMsg: 'Nova coleção de pisos disponível.', hora: 'Ontem', iniciais: 'PS', corAvatar: 'bg-orange-500' },
  { id: 7, nome: 'Lucas Martins', tipo: 'cliente', ultimaMsg: 'Vamos agendar uma reunião?', hora: 'Ontem', iniciais: 'LM', corAvatar: 'bg-indigo-500' },
  { id: 8, nome: 'Elétrica Luz Forte', tipo: 'fornecedor', ultimaMsg: 'Preciso de mais informações sobre o quadro.', hora: 'Ontem', naoLidas: 1, iniciais: 'EL', corAvatar: 'bg-yellow-500' },
  { id: 9, nome: 'Mercado Imobiliário', tipo: 'equipe', ultimaMsg: 'Novas oportunidades disponíveis!', hora: '2 dias', iniciais: 'MI', corAvatar: 'bg-rose-500' },
  { id: 10, nome: 'Equipe Interna', tipo: 'equipe', ultimaMsg: 'Atualização do cronograma da obra.', hora: '3 dias', iniciais: 'EI', corAvatar: 'bg-violet-500' },
];

type Msg = {
  id: number;
  autor: 'eu' | 'eles';
  texto: string;
  hora: string;
  lida?: boolean;
};

const mensagens: Msg[] = [
  { id: 1, autor: 'eles', texto: 'Olá Matheus, tudo bem?', hora: '09:15' },
  { id: 2, autor: 'eles', texto: 'Gostaria de saber se ainda está disponível para fazermos uma visita ao imóvel.', hora: '09:16' },
  { id: 3, autor: 'eu', texto: 'Olá João! Tudo bem e com você?\nEstá sim! Qual dia e horário você tem disponível?', hora: '09:18', lida: true },
  { id: 4, autor: 'eles', texto: 'Tenho disponibilidade amanhã (11/05) a partir das 14h.', hora: '09:20' },
  { id: 5, autor: 'eu', texto: 'Perfeito! Amanhã às 14h então.\nConfirmo a visita e te envio o endereço.', hora: '09:21', lida: true },
  { id: 6, autor: 'eles', texto: 'Perfeito! A visita está confirmada então.\nObrigado!', hora: '09:22' },
  { id: 7, autor: 'eu', texto: 'Disponha! Qualquer dúvida é só falar. 👍', hora: '09:23', lida: true },
];

const anexos = [
  { nome: 'Planta baixa atualizada.pdf', tamanho: '1.2 MB', data: '08/05/2024', cor: 'red', ext: 'PDF' },
  { nome: 'Orçamento reforma.xlsx', tamanho: '850 KB', data: '07/05/2024', cor: 'emerald', ext: 'XLS' },
  { nome: 'Fotos do imóvel.zip', tamanho: '24.5 MB', data: '05/05/2024', cor: 'amber', ext: 'ZIP' },
];

export default function Mensagens() {
  const [aba, setAba] = useState<AbaConv>('todas');
  const [convId, setConvId] = useState<number>(1);
  const [busca, setBusca] = useState('');
  const [texto, setTexto] = useState('');

  const conv = conversas.find((c) => c.id === convId)!;

  const conversasFiltradas = useMemo(() => {
    let lista = conversas;
    if (aba === 'nao_lidas') lista = lista.filter((c) => !!c.naoLidas);
    if (aba === 'favoritas') lista = lista.filter((c) => c.favorita);
    if (busca.trim()) lista = lista.filter((c) => c.nome.toLowerCase().includes(busca.toLowerCase()));
    return lista;
  }, [aba, busca]);

  const totalNaoLidas = conversas.reduce((s, c) => s + (c.naoLidas || 0), 0);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Mensagens</h1>
          <p className="text-sm text-slate-500">
            Centralize todas as conversas relacionadas aos seus projetos e contatos.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
            <Edit3 className="h-4 w-4" /> Nova mensagem
          </button>
          <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <Settings className="h-4 w-4" /> Configurações
          </button>
        </div>
      </div>

      {/* Layout 3 colunas */}
      <div className="grid grid-cols-1 xl:grid-cols-[320px_1fr_320px] gap-5">
        {/* Coluna 1: Conversas */}
        <div className="bg-white rounded-xl border border-slate-200 flex flex-col overflow-hidden">
          <div className="p-3 border-b border-slate-100">
            <div className="flex items-center gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <input
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Buscar conversas..."
                  className="w-full pl-9 pr-3 py-2 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-primary-200 focus:border-primary-400 outline-none"
                />
              </div>
              <button className="h-9 w-9 grid place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                <SlidersHorizontal className="h-4 w-4" />
              </button>
            </div>
            <div className="flex gap-4 mt-3 text-sm border-b border-slate-100 -mx-3 px-3">
              <AbaBtn ativo={aba === 'todas'} onClick={() => setAba('todas')} label="Todas" />
              <AbaBtn ativo={aba === 'nao_lidas'} onClick={() => setAba('nao_lidas')} label="Não lidas" badge={totalNaoLidas} />
              <AbaBtn ativo={aba === 'favoritas'} onClick={() => setAba('favoritas')} label="Favoritas" />
              <AbaBtn ativo={aba === 'arquivadas'} onClick={() => setAba('arquivadas')} label="Arquivadas" />
            </div>
          </div>
          <div className="flex-1 overflow-y-auto divide-y divide-slate-100 max-h-[720px]">
            {conversasFiltradas.map((c) => (
              <button
                key={c.id}
                onClick={() => setConvId(c.id)}
                className={`w-full text-left px-3 py-3 hover:bg-slate-50 transition flex items-start gap-3 ${
                  c.id === convId ? 'bg-primary-50/60 border-l-2 border-l-primary-500' : ''
                }`}
              >
                <div className="relative shrink-0">
                  <div className={`h-10 w-10 rounded-full ${c.corAvatar} grid place-items-center text-white text-xs font-semibold`}>
                    {c.iniciais}
                  </div>
                  {c.online && (
                    <span className="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2">
                    <div className="text-sm font-medium text-slate-900 truncate">{c.nome}</div>
                    <div className="text-[11px] text-slate-400 shrink-0">{c.hora}</div>
                  </div>
                  <div className="flex items-center justify-between gap-2 mt-0.5">
                    <div className="text-xs text-slate-500 truncate flex-1">{c.ultimaMsg}</div>
                    {c.naoLidas && (
                      <span className="h-4 min-w-[16px] px-1 rounded-full bg-primary-600 text-white text-[10px] font-semibold grid place-items-center">
                        {c.naoLidas}
                      </span>
                    )}
                  </div>
                </div>
              </button>
            ))}
          </div>
          <div className="p-3 border-t border-slate-100">
            <button className="w-full text-sm text-primary-600 font-medium px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50">
              Ver mais conversas
            </button>
          </div>
        </div>

        {/* Coluna 2: Chat */}
        <div className="bg-white rounded-xl border border-slate-200 flex flex-col overflow-hidden min-h-[720px]">
          {/* Header do chat */}
          <div className="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="relative">
                <div className={`h-10 w-10 rounded-full ${conv.corAvatar} grid place-items-center text-white text-xs font-semibold`}>
                  {conv.iniciais}
                </div>
                {conv.online && (
                  <span className="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white" />
                )}
              </div>
              <div>
                <div className="text-sm font-semibold text-slate-900">{conv.nome}</div>
                <div className="text-xs text-emerald-600 inline-flex items-center gap-1">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                  {conv.online ? 'Online' : 'Offline'}
                </div>
              </div>
            </div>
            <div className="flex items-center gap-1 text-slate-500">
              <button className="h-9 w-9 grid place-items-center rounded-lg hover:bg-slate-100"><Phone className="h-4 w-4" /></button>
              <button className="h-9 w-9 grid place-items-center rounded-lg hover:bg-slate-100"><Video className="h-4 w-4" /></button>
              <button className="h-9 w-9 grid place-items-center rounded-lg hover:bg-slate-100"><Star className="h-4 w-4" /></button>
              <button className="h-9 w-9 grid place-items-center rounded-lg hover:bg-slate-100"><MoreHorizontal className="h-4 w-4" /></button>
            </div>
          </div>

          {/* Card de imóvel relacionado */}
          <div className="px-5 py-3 border-b border-slate-100">
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center gap-3">
              <div className="h-14 w-20 rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500 shrink-0">
                <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-sm font-semibold text-slate-900">Casa Jardim Paulista</div>
                <div className="text-xs text-slate-500 truncate">Rua das Flores, 123 - Jardim Paulista, São Paulo - SP</div>
                <div className="text-[11px] text-slate-400">Projeto: #2024-001</div>
              </div>
              <button className="text-xs text-primary-600 font-medium px-3 py-1.5 rounded-lg border border-primary-200 hover:bg-primary-50">
                Ver projeto
              </button>
            </div>
          </div>

          {/* Mensagens */}
          <div className="flex-1 overflow-y-auto px-5 py-4 space-y-3">
            <div className="text-center">
              <span className="text-[11px] text-slate-400 px-3 py-1 bg-slate-50 rounded-full">Hoje</span>
            </div>
            {mensagens.map((m) => (
              <Bolha key={m.id} m={m} conv={conv} />
            ))}
          </div>

          {/* Input */}
          <div className="border-t border-slate-100 px-4 py-3">
            <div className="flex items-center gap-2 mb-2 text-slate-400">
              <button className="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"><Paperclip className="h-4 w-4" /></button>
              <button className="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"><ImageIcon className="h-4 w-4" /></button>
              <button className="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"><FileText className="h-4 w-4" /></button>
              <button className="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"><Smile className="h-4 w-4" /></button>
            </div>
            <div className="flex items-center gap-2">
              <input
                value={texto}
                onChange={(e) => setTexto(e.target.value)}
                placeholder="Digite sua mensagem..."
                className="flex-1 px-3 py-2.5 text-sm rounded-lg bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-primary-200 focus:border-primary-400 outline-none"
              />
              <button className="h-10 w-10 grid place-items-center rounded-lg bg-primary-600 text-white hover:bg-primary-700">
                <Send className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        {/* Coluna 3: Sobre o contato */}
        <div className="space-y-5">
          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="text-sm font-semibold text-slate-900 mb-3">Sobre o contato</div>
            <div className="flex items-start gap-3">
              <div className={`h-12 w-12 rounded-full ${conv.corAvatar} grid place-items-center text-white text-sm font-semibold`}>
                {conv.iniciais}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <div className="text-sm font-semibold text-slate-900">{conv.nome}</div>
                  <Star className="h-3.5 w-3.5 text-amber-400 fill-amber-400" />
                </div>
                <span className="inline-block text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 mt-1">
                  Cliente
                </span>
              </div>
            </div>
            <div className="mt-4 space-y-2 text-sm">
              <LinhaInfo icon={Phone} label="(11) 99876-5432" />
              <LinhaInfo icon={Mail} label="joao.ferreira@email.com" />
              <LinhaInfo icon={CalendarDays} label="Cliente desde 03/04/2024" />
            </div>
            <button className="mt-4 text-sm text-primary-600 font-medium hover:text-primary-700 inline-flex items-center gap-1">
              Ver detalhes <span aria-hidden>›</span>
            </button>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-semibold text-slate-900">Projetos relacionados</div>
              <button className="text-xs text-primary-600 hover:text-primary-700">Ver todos</button>
            </div>
            <div className="flex items-start gap-3">
              <div className="h-12 w-16 rounded-md bg-gradient-to-br from-slate-200 to-slate-300 grid place-items-center text-slate-500 shrink-0">
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor">
                  <path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z" />
                </svg>
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium text-slate-900">Casa Jardim Paulista</div>
                <div className="text-[11px] text-slate-500">Projeto #2024-001</div>
                <span className="inline-block text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 mt-1">
                  Em andamento
                </span>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-semibold text-slate-900">Anexos compartilhados</div>
              <button className="text-xs text-primary-600 hover:text-primary-700">Ver todos</button>
            </div>
            <div className="space-y-3">
              {anexos.map((a) => (
                <div key={a.nome} className="flex items-center gap-3">
                  <div
                    className={`h-10 w-10 rounded-lg grid place-items-center text-[10px] font-bold text-white ${
                      a.cor === 'red' ? 'bg-red-500' : a.cor === 'emerald' ? 'bg-emerald-500' : 'bg-amber-500'
                    }`}
                  >
                    {a.ext}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-medium text-slate-900 truncate">{a.nome}</div>
                    <div className="text-[11px] text-slate-500">{a.tamanho} • {a.data}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-semibold text-slate-900">Notas</div>
              <button className="text-xs text-primary-600 hover:text-primary-700">Editar</button>
            </div>
            <div className="text-sm text-slate-600 leading-relaxed">
              Cliente interessado em fechar negócio até final de junho.
              <br />
              Orçamento máximo: R$ 850.000
            </div>
            <button className="mt-3 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700">
              <Plus className="h-3.5 w-3.5" /> Adicionar nota...
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function AbaBtn({
  ativo,
  onClick,
  label,
  badge,
}: {
  ativo: boolean;
  onClick: () => void;
  label: string;
  badge?: number;
}) {
  return (
    <button
      onClick={onClick}
      className={`pb-2 -mb-px border-b-2 inline-flex items-center gap-1.5 ${
        ativo
          ? 'border-primary-600 text-primary-700 font-medium'
          : 'border-transparent text-slate-500 hover:text-slate-700'
      }`}
    >
      {label}
      {!!badge && badge > 0 && (
        <span
          className={`text-[10px] px-1.5 py-0.5 rounded-full ${
            ativo ? 'bg-primary-100 text-primary-700' : 'bg-slate-100 text-slate-600'
          }`}
        >
          {badge}
        </span>
      )}
    </button>
  );
}

function Bolha({ m, conv }: { m: Msg; conv: Conversa }) {
  const eu = m.autor === 'eu';
  return (
    <div className={`flex items-end gap-2 ${eu ? 'justify-end' : 'justify-start'}`}>
      {!eu && (
        <div className={`h-7 w-7 rounded-full ${conv.corAvatar} grid place-items-center text-white text-[10px] font-semibold shrink-0`}>
          {conv.iniciais}
        </div>
      )}
      <div
        className={`max-w-[70%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-line ${
          eu
            ? 'bg-primary-600 text-white rounded-br-sm'
            : 'bg-slate-100 text-slate-800 rounded-bl-sm'
        }`}
      >
        <div>{m.texto}</div>
        <div className={`mt-1 flex items-center justify-end gap-1 text-[10px] ${eu ? 'text-primary-100' : 'text-slate-500'}`}>
          <span>{m.hora}</span>
          {eu && m.lida && <CheckCheck className="h-3 w-3" />}
        </div>
      </div>
    </div>
  );
}

function LinhaInfo({ icon: Icon, label }: { icon: typeof Phone; label: string }) {
  return (
    <div className="flex items-center gap-2 text-sm text-slate-600">
      <Icon className="h-4 w-4 text-slate-400" />
      <span className="truncate">{label}</span>
    </div>
  );
}
