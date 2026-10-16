import { useMemo, useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  Plus,
  RefreshCw,
  Filter,
  Clock,
  MapPin,
} from 'lucide-react';

type Categoria = 'visita' | 'obra' | 'compra' | 'pagamento' | 'entrega' | 'outro';

type Evento = {
  id: number;
  dia: number; // dia do mês de Maio/2024 (1..31)
  hora: string;
  titulo: string;
  local: string;
  categoria: Categoria;
  endereco?: string;
};

const catMap: Record<Categoria, { label: string; cor: string; bg: string; border: string; dot: string; badge: string }> = {
  visita: {
    label: 'Visitas e Reuniões',
    cor: '#a855f7',
    bg: 'bg-violet-50',
    border: 'border-l-violet-400',
    dot: 'bg-violet-500',
    badge: 'bg-violet-100 text-violet-700',
  },
  obra: {
    label: 'Obras',
    cor: '#10b981',
    bg: 'bg-emerald-50',
    border: 'border-l-emerald-400',
    dot: 'bg-emerald-500',
    badge: 'bg-emerald-100 text-emerald-700',
  },
  compra: {
    label: 'Compras',
    cor: '#f59e0b',
    bg: 'bg-amber-50',
    border: 'border-l-amber-400',
    dot: 'bg-amber-500',
    badge: 'bg-amber-100 text-amber-700',
  },
  pagamento: {
    label: 'Pagamentos',
    cor: '#3b82f6',
    bg: 'bg-blue-50',
    border: 'border-l-blue-400',
    dot: 'bg-blue-500',
    badge: 'bg-blue-100 text-blue-700',
  },
  entrega: {
    label: 'Entregas',
    cor: '#f43f5e',
    bg: 'bg-rose-50',
    border: 'border-l-rose-400',
    dot: 'bg-rose-500',
    badge: 'bg-rose-100 text-rose-700',
  },
  outro: {
    label: 'Outros',
    cor: '#64748b',
    bg: 'bg-slate-50',
    border: 'border-l-slate-400',
    dot: 'bg-slate-500',
    badge: 'bg-slate-100 text-slate-700',
  },
};

const eventos: Evento[] = [
  { id: 1, dia: 29, hora: '09:00', titulo: 'Visita técnica', local: 'Casa Jardim Paulista', categoria: 'visita' },
  { id: 2, dia: 30, hora: '14:00', titulo: 'Reunião com cliente', local: 'Sobrados Moema', categoria: 'visita' },
  { id: 3, dia: 1, hora: '09:00', titulo: 'Entrega de materiais', local: 'Casa Interlagos', categoria: 'entrega' },
  { id: 4, dia: 3, hora: '11:00', titulo: 'Pagamento fornecedor', local: 'Hidráulica Silva', categoria: 'pagamento' },

  { id: 5, dia: 5, hora: '10:00', titulo: 'Fiscalização de obra', local: 'Casa Campo Belo', categoria: 'obra' },
  { id: 6, dia: 6, hora: '09:00', titulo: 'Início da demolição', local: 'Casa Brooklin', categoria: 'obra' },
  { id: 7, dia: 7, hora: '15:00', titulo: 'Aprovação de projeto', local: 'Sobrado Vila Madalena', categoria: 'visita' },
  { id: 8, dia: 8, hora: '09:30', titulo: 'Compra de materiais', local: 'Casa Jardim Paulista', categoria: 'compra' },
  { id: 9, dia: 9, hora: '14:00', titulo: 'Reunião de alinhamento', local: 'Casa Interlagos', categoria: 'visita' },
  {
    id: 10,
    dia: 10,
    hora: '08:00',
    titulo: 'Verificação de obra',
    local: 'Casa Jardim Paulista',
    categoria: 'obra',
    endereco: 'Rua das Flores, 123 - São Paulo, SP',
  },
  {
    id: 11,
    dia: 10,
    hora: '15:00',
    titulo: 'Entrega de piso',
    local: 'Portobello Shop',
    categoria: 'entrega',
    endereco: 'Av. Rebouças, 2100 - São Paulo, SP',
  },

  { id: 12, dia: 13, hora: '09:00', titulo: 'Pagamento equipe', local: 'Mão de Obra', categoria: 'pagamento' },
  { id: 13, dia: 14, hora: '10:30', titulo: 'Visita de inspeção', local: 'Casa Interlagos', categoria: 'visita' },
  { id: 14, dia: 15, hora: '14:00', titulo: 'Reunião com arquiteto', local: 'Studio A+', categoria: 'visita' },
  { id: 15, dia: 16, hora: '09:00', titulo: 'Entrega de esquadrias', local: 'Alumínios São Paulo', categoria: 'entrega' },
  { id: 16, dia: 17, hora: '11:00', titulo: 'Acompanhamento', local: 'Casa Brooklin', categoria: 'obra' },

  { id: 17, dia: 20, hora: '09:00', titulo: 'Compra de tintas', local: 'Casa Jardim Paulista', categoria: 'compra' },
  { id: 18, dia: 21, hora: '14:00', titulo: 'Teste hidráulico', local: 'Casa Campo Belo', categoria: 'obra' },
  { id: 19, dia: 22, hora: '10:00', titulo: 'Reunião com cliente', local: 'Sobrados Moema', categoria: 'visita' },
  { id: 20, dia: 23, hora: '09:30', titulo: 'Vistoria elétrica', local: 'Casa Interlagos', categoria: 'obra' },
  { id: 21, dia: 24, hora: '16:00', titulo: 'Entrega de formulário', local: 'Contabilidade', categoria: 'entrega' },

  { id: 22, dia: 27, hora: '09:00', titulo: 'Início da pintura', local: 'Casa Brooklin', categoria: 'obra' },
  { id: 23, dia: 28, hora: '11:00', titulo: 'Aprovação mobília', local: 'Cliente', categoria: 'visita' },
  { id: 24, dia: 29, hora: '09:00', titulo: 'Entrega de louças', local: 'Deca', categoria: 'entrega' },
  { id: 25, dia: 30, hora: '14:00', titulo: 'Vistoria final', local: 'Casa Jardim Paulista', categoria: 'obra' },
  { id: 26, dia: 31, hora: '15:00', titulo: 'Fotos para anúncios', local: 'Fotos Pro', categoria: 'compra' },
];

// Maio/2024 começa numa quarta-feira (1=qua). Mostraremos uma grade 6x7 começando no domingo anterior (28/abr).
// Linha 1: 28(dom abr) 29 30 1 2 3 4
// Linha 2..6: 5..31 e 1 de junho na última célula.
const gridDias: { dia: number; mesAtual: boolean; isJun?: boolean }[] = [
  { dia: 28, mesAtual: false },
  { dia: 29, mesAtual: false },
  { dia: 30, mesAtual: false },
  { dia: 1, mesAtual: true },
  { dia: 2, mesAtual: true },
  { dia: 3, mesAtual: true },
  { dia: 4, mesAtual: true },
  ...Array.from({ length: 28 }, (_, i) => ({ dia: i + 5, mesAtual: true })),
  { dia: 1, mesAtual: false, isJun: true },
];

const semanaCabecalho = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const semanaMini = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

type Visao = 'mes' | 'semana' | 'dia' | 'agenda';

export default function Calendario() {
  const [visao, setVisao] = useState<Visao>('mes');
  const [diaSelecionado, setDiaSelecionado] = useState<number>(10);
  const [filtros, setFiltros] = useState<Record<Categoria, boolean>>({
    visita: true,
    obra: true,
    compra: true,
    pagamento: true,
    entrega: true,
    outro: true,
  });

  const eventosVisiveis = useMemo(
    () => eventos.filter((e) => filtros[e.categoria]),
    [filtros],
  );

  const eventosDoDia = useMemo(
    () => eventosVisiveis.filter((e) => e.dia === diaSelecionado),
    [eventosVisiveis, diaSelecionado],
  );

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Calendário</h1>
          <p className="text-sm text-slate-500">
            Acompanhe prazos, tarefas e compromissos das suas obras
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button className="inline-flex items-center gap-2 text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 bg-white">
            <RefreshCw className="h-4 w-4" /> Sincronizar
          </button>
          <button className="inline-flex items-center gap-2 text-sm text-white px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700">
            <Plus className="h-4 w-4" /> Novo evento
          </button>
          <button className="h-9 w-9 grid place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50">
            <Filter className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-5">
        {/* Calendário principal */}
        <div className="bg-white rounded-xl border border-slate-200 p-4">
          <div className="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div className="flex items-center gap-2">
              <button className="text-sm text-slate-700 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
                Hoje
              </button>
              <button className="h-8 w-8 grid place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button className="h-8 w-8 grid place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                <ChevronRight className="h-4 w-4" />
              </button>
              <button className="text-sm font-medium text-slate-900 px-2 py-1 hover:bg-slate-50 rounded-lg inline-flex items-center gap-1">
                Maio de 2024
                <ChevronRight className="h-3 w-3 rotate-90 text-slate-400" />
              </button>
            </div>
            <div className="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs">
              {(['mes', 'semana', 'dia', 'agenda'] as Visao[]).map((v) => (
                <button
                  key={v}
                  onClick={() => setVisao(v)}
                  className={`px-3 py-1.5 rounded-md capitalize transition ${
                    visao === v ? 'bg-white text-primary-700 shadow-sm font-medium' : 'text-slate-500 hover:text-slate-700'
                  }`}
                >
                  {v === 'mes' ? 'Mês' : v}
                </button>
              ))}
            </div>
          </div>

          {/* Cabeçalho da semana */}
          <div className="grid grid-cols-7 text-center text-xs font-medium text-slate-500 mb-2">
            {semanaCabecalho.map((d) => (
              <div key={d} className="py-2">{d}</div>
            ))}
          </div>

          {/* Grade */}
          <div className="grid grid-cols-7 gap-1.5">
            {gridDias.map((cel, idx) => {
              const isSelecionado = cel.mesAtual && cel.dia === diaSelecionado;
              const evs = cel.mesAtual ? eventosVisiveis.filter((e) => e.dia === cel.dia) : [];
              return (
                <button
                  key={idx}
                  onClick={() => cel.mesAtual && setDiaSelecionado(cel.dia)}
                  className={`min-h-[110px] rounded-lg border text-left p-1.5 transition relative ${
                    isSelecionado
                      ? 'border-primary-400 ring-2 ring-primary-200 bg-white'
                      : 'border-slate-100 bg-white hover:border-slate-200'
                  }`}
                >
                  <div className="flex items-center justify-between mb-1">
                    <span
                      className={`text-xs ${
                        cel.mesAtual ? 'text-slate-700' : 'text-slate-300'
                      }`}
                    >
                      {cel.dia}
                    </span>
                    {isSelecionado && (
                      <span className="h-5 w-5 grid place-items-center rounded-full bg-primary-600 text-white text-[10px] font-semibold">
                        {cel.dia}
                      </span>
                    )}
                  </div>
                  <div className="space-y-1">
                    {evs.slice(0, 2).map((e) => {
                      const c = catMap[e.categoria];
                      return (
                        <div
                          key={e.id}
                          className={`text-[10px] leading-tight px-1.5 py-1 rounded border-l-2 ${c.bg} ${c.border} text-slate-700`}
                        >
                          <div className="font-medium">{e.hora}</div>
                          <div className="truncate">{e.titulo}</div>
                          <div className="truncate text-slate-500">{e.local}</div>
                        </div>
                      );
                    })}
                    {evs.length > 2 && (
                      <div className="text-[10px] text-slate-500 px-1">+{evs.length - 2} mais</div>
                    )}
                  </div>
                </button>
              );
            })}
          </div>

          {/* Legenda + toggle */}
          <div className="flex items-center justify-between gap-3 flex-wrap pt-4 mt-3 border-t border-slate-100">
            <div className="flex items-center gap-4 flex-wrap text-xs text-slate-600">
              <Legenda cor={catMap.visita.cor} label="Visitas e Reuniões" />
              <Legenda cor={catMap.obra.cor} label="Obra" />
              <Legenda cor={catMap.compra.cor} label="Compras" />
              <Legenda cor={catMap.pagamento.cor} label="Pagamentos" />
              <Legenda cor={catMap.entrega.cor} label="Entregas" />
              <Legenda cor={catMap.outro.cor} label="Outros" />
            </div>
            <label className="inline-flex items-center gap-2 text-xs text-slate-500">
              Mostrar apenas meus eventos
              <span className="relative inline-flex h-5 w-9 items-center rounded-full bg-slate-200 cursor-pointer">
                <input type="checkbox" className="sr-only peer" />
                <span className="absolute left-0.5 h-4 w-4 rounded-full bg-white shadow transition peer-checked:translate-x-4" />
              </span>
            </label>
          </div>
        </div>

        {/* Lateral direita */}
        <div className="space-y-5">
          {/* Mini calendário */}
          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-semibold text-slate-900">Maio de 2024</div>
              <div className="flex items-center gap-1">
                <button className="h-6 w-6 grid place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50">
                  <ChevronLeft className="h-3 w-3" />
                </button>
                <button className="h-6 w-6 grid place-items-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50">
                  <ChevronRight className="h-3 w-3" />
                </button>
              </div>
            </div>
            <div className="grid grid-cols-7 text-center text-[10px] text-slate-400 mb-1">
              {semanaMini.map((d, i) => (
                <div key={i} className="py-1">{d}</div>
              ))}
            </div>
            <div className="grid grid-cols-7 gap-y-1 text-center text-xs">
              {gridDias.map((cel, idx) => {
                const isSelecionado = cel.mesAtual && cel.dia === diaSelecionado;
                return (
                  <button
                    key={idx}
                    onClick={() => cel.mesAtual && setDiaSelecionado(cel.dia)}
                    className={`h-7 w-7 mx-auto grid place-items-center rounded-full transition ${
                      isSelecionado
                        ? 'bg-primary-600 text-white font-semibold'
                        : cel.mesAtual
                        ? 'text-slate-700 hover:bg-slate-100'
                        : 'text-slate-300'
                    }`}
                  >
                    {cel.dia}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Agenda do dia */}
          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="mb-3">
              <div className="text-sm font-semibold text-slate-900">
                Sexta-feira, {diaSelecionado} de Maio de 2024
              </div>
              <div className="text-xs text-slate-500">{eventosDoDia.length} eventos</div>
            </div>

            <div className="relative">
              <div className="space-y-4">
                {eventosDoDia.length === 0 && (
                  <div className="text-xs text-slate-400 py-6 text-center">
                    Nenhum evento neste dia.
                  </div>
                )}
                {eventosDoDia.map((e) => {
                  const c = catMap[e.categoria];
                  return (
                    <div key={e.id} className="flex gap-3">
                      <div className="text-[11px] text-slate-500 w-10 pt-1">{e.hora}</div>
                      <div className="flex-1">
                        <div className="flex items-start gap-2">
                          <span className={`h-2 w-2 rounded-full mt-1.5 ${c.dot}`} />
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between gap-2">
                              <div className="text-xs text-slate-500">{e.hora}</div>
                              <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${c.badge}`}>
                                {e.categoria === 'visita'
                                  ? 'Obra'
                                  : e.categoria === 'pagamento'
                                  ? 'Pagamento'
                                  : e.categoria === 'entrega'
                                  ? 'Entrega'
                                  : e.categoria === 'obra'
                                  ? 'Obra'
                                  : e.categoria === 'compra'
                                  ? 'Compra'
                                  : 'Reunião'}
                              </span>
                            </div>
                            <div className="text-sm font-medium text-slate-900">{e.titulo}</div>
                            <div className="text-xs text-slate-500">{e.local}</div>
                            {e.endereco && (
                              <div className="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-500">
                                <MapPin className="h-3 w-3" />
                                {e.endereco}
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
              {eventosDoDia.length > 0 && (
                <div className="absolute left-12 top-2 bottom-2 w-px bg-slate-100 -z-0" aria-hidden />
              )}
            </div>
          </div>

          {/* Filtros rápidos */}
          <div className="bg-white rounded-xl border border-slate-200 p-4">
            <div className="text-sm font-semibold text-slate-900 mb-3">Filtros rápidos</div>
            <div className="space-y-2">
              {(Object.keys(catMap) as Categoria[]).map((k) => {
                const c = catMap[k];
                return (
                  <label
                    key={k}
                    className="flex items-center justify-between text-sm text-slate-700 cursor-pointer"
                  >
                    <span className="inline-flex items-center gap-2">
                      <span className={`h-2.5 w-2.5 rounded-full ${c.dot}`} />
                      {c.label}
                    </span>
                    <input
                      type="checkbox"
                      checked={filtros[k]}
                      onChange={(ev) => setFiltros({ ...filtros, [k]: ev.target.checked })}
                      className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    />
                  </label>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
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

// silence unused warning
void Clock;
