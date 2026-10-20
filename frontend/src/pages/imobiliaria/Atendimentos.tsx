import { useEffect, useState } from 'react';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import SearchInput from '@/ui/SearchInput';
import StatusBadge from '@/ui/StatusBadge';
import ErrorState from '@/ui/ErrorState';

const STATUSES = ['novo', 'em_andamento', 'aguardando_cliente', 'aguardando_corretor', 'encerrado'];
const FILTERS = [
  { id: '', label: 'Todos' },
  { id: 'novo', label: 'Novos' },
  { id: 'aguardando_cliente', label: 'Aguardando cliente' },
  { id: 'em_andamento', label: 'Em atendimento' },
  { id: 'aguardando_corretor', label: 'Pendentes' },
  { id: 'encerrado', label: 'Encerrados' },
];

export default function Atendimentos() {
  const [tickets, setTickets] = useState<any[]>([]);
  const [current, setCurrent] = useState<any>(null);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [kind, setKind] = useState('note');
  const [filter, setFilter] = useState('');
  const [priorityOnly, setPriorityOnly] = useState(false);
  const [q, setQ] = useState('');
  const [error, setError] = useState('');
  const [pane, setPane] = useState<'inbox' | 'thread' | 'context'>('inbox');

  async function load() {
    const { data } = await api.get('/imobiliaria/tickets', { params: filter ? { status: filter } : {} });
    setTickets(data.data ?? []);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Central indisponível.')); }, [filter]);

  async function openTicket(id: number) {
    const { data } = await api.get(`/imobiliaria/tickets/${id}`);
    setCurrent(data.data);
    setPane('thread');
  }
  async function create() {
    if (!subject.trim()) return;
    const { data } = await api.post('/imobiliaria/tickets', { subject, channel: 'internal' });
    setSubject('');
    await load();
    setCurrent(data.data);
    setPane('thread');
  }
  async function send() {
    if (!current || !body.trim()) return;
    const { data } = await api.post(`/imobiliaria/tickets/${current.id}/messages`, { body, kind });
    setBody('');
    setCurrent(data.data);
    await load();
  }
  async function patch(payload: Record<string, unknown>) {
    if (!current) return;
    const { data } = await api.patch(`/imobiliaria/tickets/${current.id}`, payload);
    setCurrent(data.data);
    await load();
  }
  async function convert() {
    if (!current) return;
    await api.post(`/imobiliaria/tickets/${current.id}/convert`);
    await openTicket(current.id);
  }

  const visible = tickets.filter((ticket) => {
    if (priorityOnly && ticket.priority !== 'alta') return false;
    return !q || String(ticket.subject).toLowerCase().includes(q.toLowerCase());
  });

  return (
    <div className="space-y-4">
      <PageHeader title="Central de atendimento" subtitle="Registros internos. WhatsApp permanece not_wired — nada é enviado para o aplicativo." />
      {error && <ErrorState message={error} />}
      <div className="lg:hidden flex gap-2">
        {(['inbox', 'thread', 'context'] as const).map((item) => (
          <button key={item} type="button" onClick={() => setPane(item)} className={`px-3 py-1.5 rounded-full text-xs ${pane === item ? 'bg-sky-600 text-white' : 'border'}`}>
            {item === 'inbox' ? 'Atendimentos' : item === 'thread' ? 'Conversa' : 'Detalhes'}
          </button>
        ))}
      </div>
      <div className="grid lg:grid-cols-3 gap-4 min-h-[70vh]">
        <section className={`bg-white dark:bg-slate-900 border rounded-2xl p-3 space-y-2 ${pane === 'inbox' ? 'block' : 'hidden lg:block'}`}>
          <div className="flex gap-2">
            <input className="input" value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Novo atendimento" />
            <button type="button" onClick={create} className="px-3 bg-sky-600 text-white rounded-lg text-sm">Criar</button>
          </div>
          <SearchInput value={q} onChange={setQ} placeholder="Buscar assunto" />
          <div className="flex flex-wrap gap-1">
            {FILTERS.map((item) => (
              <button key={item.id} type="button" onClick={() => setFilter(item.id)} className={`text-[11px] rounded-full px-2 py-1 ${filter === item.id ? 'bg-sky-600 text-white' : 'border'}`}>{item.label}</button>
            ))}
            <button type="button" onClick={() => setPriorityOnly((value) => !value)} className={`text-[11px] rounded-full px-2 py-1 ${priorityOnly ? 'bg-rose-600 text-white' : 'border'}`}>Prioritários</button>
          </div>
          {visible.map((ticket) => (
            <button key={ticket.id} type="button" onClick={() => openTicket(ticket.id)} className={`w-full text-left p-3 rounded-lg border ${current?.id === ticket.id ? 'border-sky-500' : ''}`}>
              <p className="text-sm font-medium">{ticket.subject}</p>
              <p className="text-xs text-gray-500">{ticket.status} · {ticket.channel} · {ticket.assigned_user?.name || 'sem responsável'}</p>
            </button>
          ))}
        </section>
        <section className={`bg-white dark:bg-slate-900 border rounded-2xl p-4 space-y-3 ${pane === 'thread' ? 'block' : 'hidden lg:block'}`}>
          {!current && <p className="text-sm text-gray-500">Selecione um atendimento. Mensagens ficam no histórico interno.</p>}
          {current && (
            <>
              <h2 className="font-semibold">{current.subject}</h2>
              <div className="space-y-2 max-h-80 overflow-y-auto">
                {(current.messages ?? []).map((message: any) => (
                  <div key={message.id} className="text-sm border rounded-lg p-2">
                    <p className="text-[11px] text-gray-400">{message.kind} · {message.user?.name}</p>
                    <p>{message.body}</p>
                    {message.delivery?.status === 'not_wired' && <p className="text-xs text-amber-700">WhatsApp não enviado</p>}
                  </div>
                ))}
              </div>
              <select className="input" value={kind} onChange={(e) => setKind(e.target.value)}>
                <option value="note">Anotação interna</option>
                <option value="message">Mensagem (somente canal interno)</option>
              </select>
              <textarea className="input" value={body} onChange={(e) => setBody(e.target.value)} />
              <div className="flex flex-wrap gap-2">
                <button type="button" onClick={send} className="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">Registrar</button>
                <button type="button" onClick={convert} className="border px-3 py-2 rounded-lg text-sm">Virar negócio</button>
              </div>
            </>
          )}
        </section>
        <section className={`bg-white dark:bg-slate-900 border rounded-2xl p-4 text-sm space-y-2 ${pane === 'context' ? 'block' : 'hidden lg:block'}`}>
          <h2 className="font-semibold">Contexto</h2>
          {current ? (
            <>
              <p>Cliente: {current.contact?.name || '—'}</p>
              <p>Imóvel: {current.listing?.title || '—'}</p>
              <p>Corretor: {current.assigned_user?.name || '—'}</p>
              <p>Prioridade: <StatusBadge value={current.priority} /></p>
              <p>Canal: {current.channel} {current.channel !== 'internal' ? '(não configurado neste ambiente)' : ''}</p>
              <label className="block text-xs">Status
                <select className="input mt-1" value={current.status} onChange={(e) => void patch({ status: e.target.value })}>
                  {STATUSES.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
              </label>
              <label className="block text-xs">Prioridade
                <select className="input mt-1" value={current.priority} onChange={(e) => void patch({ priority: e.target.value })}>
                  {['baixa', 'normal', 'alta'].map((item) => <option key={item} value={item}>{item}</option>)}
                </select>
              </label>
              <button type="button" className="border rounded-lg px-3 py-2 text-sm" onClick={() => void patch({ assigned_user_id: null })}>Deixar sem responsável</button>
              <button type="button" className="border rounded-lg px-3 py-2 text-sm" onClick={() => void patch({ status: current.status === 'encerrado' ? 'em_andamento' : 'encerrado' })}>
                {current.status === 'encerrado' ? 'Reabrir' : 'Encerrar'}
              </button>
            </>
          ) : <p className="text-gray-500">Sem atendimento selecionado.</p>}
        </section>
      </div>
    </div>
  );
}
