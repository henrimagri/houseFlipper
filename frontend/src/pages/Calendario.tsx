import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Clock, Loader2, MapPin, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-react';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';

type Category = 'visita' | 'obra' | 'compra' | 'pagamento' | 'entrega' | 'outro';
type EventStatus = 'agendado' | 'concluido' | 'cancelado';
type Project = { id: number; nome: string };
type CalendarEvent = {
  id: number; projeto_id: number | null; projeto?: Project | null; titulo: string; descricao?: string | null;
  categoria: Category; inicio_em: string; fim_em?: string | null; dia_inteiro: boolean; local?: string | null;
  endereco?: string | null; status: EventStatus; criado_por?: string | null;
};
type EventForm = {
  projeto_id: string; titulo: string; descricao: string; categoria: Category; inicio_em: string;
  fim_em: string; dia_inteiro: boolean; local: string; endereco: string; status: EventStatus;
};

const categories: Record<Category, { label: string; color: string; card: string; dot: string }> = {
  visita: { label: 'Visitas e reuniões', color: '#a855f7', card: 'bg-violet-50 dark:bg-violet-950 border-violet-400', dot: 'bg-violet-500' },
  obra: { label: 'Obras', color: '#10b981', card: 'bg-emerald-50 dark:bg-emerald-950 border-emerald-400', dot: 'bg-emerald-500' },
  compra: { label: 'Compras', color: '#f59e0b', card: 'bg-amber-50 dark:bg-amber-950 border-amber-400', dot: 'bg-amber-500' },
  pagamento: { label: 'Pagamentos', color: '#3b82f6', card: 'bg-blue-50 dark:bg-blue-950 border-blue-400', dot: 'bg-blue-500' },
  entrega: { label: 'Entregas', color: '#f43f5e', card: 'bg-rose-50 dark:bg-rose-950 border-rose-400', dot: 'bg-rose-500' },
  outro: { label: 'Outros', color: '#64748b', card: 'bg-slate-50 dark:bg-slate-950 border-slate-400', dot: 'bg-slate-500' },
};
const statusLabels: Record<EventStatus, string> = { agendado: 'Agendado', concluido: 'Concluído', cancelado: 'Cancelado' };
const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

function dateKey(date: Date) {
  const year = date.getFullYear(); const month = String(date.getMonth() + 1).padStart(2, '0'); const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function localInput(value: string) {
  const date = new Date(value); const offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
function emptyForm(date: Date): EventForm {
  const start = new Date(date); start.setHours(9, 0, 0, 0);
  return { projeto_id: '', titulo: '', descricao: '', categoria: 'visita', inicio_em: localInput(start.toISOString()), fim_em: '', dia_inteiro: false, local: '', endereco: '', status: 'agendado' };
}

export default function Calendario() {
  const eventLoader = useRef(new PaginatedCollection<CalendarEvent>());
  const projectLoader = useRef(new PaginatedCollection<Project>());
  const now = useMemo(() => new Date(), []);
  const [cursor, setCursor] = useState(() => new Date(now.getFullYear(), now.getMonth(), 1));
  const [selectedDate, setSelectedDate] = useState(() => new Date(now.getFullYear(), now.getMonth(), now.getDate()));
  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [enabled, setEnabled] = useState<Record<Category, boolean>>({ visita: true, obra: true, compra: true, pagamento: true, entrega: true, outro: true });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editingId, setEditingId] = useState<number | null | undefined>(undefined);
  const [deleteTarget, setDeleteTarget] = useState<CalendarEvent | null>(null);
  const [form, setForm] = useState<EventForm>(() => emptyForm(now));

  const grid = useMemo(() => {
    const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const gridStart = new Date(first); gridStart.setDate(first.getDate() - first.getDay());
    return Array.from({ length: 42 }, (_, index) => { const date = new Date(gridStart); date.setDate(gridStart.getDate() + index); return date; });
  }, [cursor]);

  async function loadEvents(showSpinner = true) {
    if (showSpinner) setLoading(true); setError('');
    try {
      const items = await eventLoader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/eventos-calendario', { params: { page, per_page: perPage, data_inicio: dateKey(grid[0]), data_fim: dateKey(grid[41]) }, signal });
        return response.data;
      });
      if (!items) return false;
      setEvents(items); return true;
    } catch (exception: any) { setError(exception.response?.data?.message ?? exception.message ?? 'Não foi possível carregar os eventos.'); return false; }
    finally { setLoading(false); }
  }
  useEffect(() => { loadEvents(); return () => eventLoader.current.cancel(); }, [cursor]);
  useEffect(() => {
    projectLoader.current.load(async (page, perPage, signal) => { const response = await api.get('/projetos', { params: { page, per_page: perPage }, signal }); return response.data; })
      .then(items => { if (items) setProjects(items); }).catch(() => undefined);
    return () => projectLoader.current.cancel();
  }, []);

  const visibleEvents = useMemo(() => events.filter(event => enabled[event.categoria]), [events, enabled]);
  function eventsForDate(date: Date) {
    const start = new Date(date); start.setHours(0, 0, 0, 0); const end = new Date(date); end.setHours(23, 59, 59, 999);
    return visibleEvents.filter(event => { const eventStart = new Date(event.inicio_em); const eventEnd = event.fim_em ? new Date(event.fim_em) : eventStart; return eventStart <= end && eventEnd >= start; });
  }
  const selectedEvents = eventsForDate(selectedDate);

  function moveMonth(offset: number) {
    const next = new Date(cursor.getFullYear(), cursor.getMonth() + offset, 1); setCursor(next); setSelectedDate(next);
  }
  function goToday() { const today = new Date(); setCursor(new Date(today.getFullYear(), today.getMonth(), 1)); setSelectedDate(today); }
  function openCreate(date = selectedDate) { setForm(emptyForm(date)); setEditingId(null); setError(''); setNotice(''); }
  function openEdit(event: CalendarEvent) {
    setForm({ projeto_id: event.projeto_id ? String(event.projeto_id) : '', titulo: event.titulo, descricao: event.descricao ?? '', categoria: event.categoria, inicio_em: localInput(event.inicio_em), fim_em: event.fim_em ? localInput(event.fim_em) : '', dia_inteiro: event.dia_inteiro, local: event.local ?? '', endereco: event.endereco ?? '', status: event.status });
    setEditingId(event.id); setError(''); setNotice('');
  }
  async function save(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError(''); setNotice('');
    const payload = { projeto_id: form.projeto_id ? Number(form.projeto_id) : null, titulo: form.titulo.trim(), descricao: form.descricao.trim() || null, categoria: form.categoria, inicio_em: new Date(form.inicio_em).toISOString(), fim_em: form.fim_em ? new Date(form.fim_em).toISOString() : null, dia_inteiro: form.dia_inteiro, local: form.local.trim() || null, endereco: form.endereco.trim() || null, status: form.status };
    try {
      if (editingId) await api.put(`/eventos-calendario/${editingId}`, payload); else await api.post('/eventos-calendario', payload);
      const message = editingId ? 'Evento atualizado.' : 'Evento criado.'; setEditingId(undefined);
      const refreshed = await loadEvents(false);
      setNotice(refreshed ? message : `${message} A agenda não pôde ser recarregada; use Atualizar antes de repetir a operação.`);
    } catch (exception: any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar o evento.'); }
    finally { setSaving(false); }
  }
  async function remove(event: CalendarEvent) {
    setSaving(true); setError(''); setNotice('');
    try { await api.delete(`/eventos-calendario/${event.id}`); setDeleteTarget(null); const refreshed = await loadEvents(false); setNotice(refreshed ? 'Evento excluído.' : 'Evento excluído, mas a agenda não pôde ser recarregada. Use Atualizar.'); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível excluir o evento.'); setDeleteTarget(null); }
    finally { setSaving(false); }
  }

  return <div className="space-y-6">
    <header className="flex items-start justify-between gap-4 flex-wrap"><div><h1 className="text-2xl font-semibold">Calendário</h1><p className="text-sm text-slate-500 dark:text-slate-400">Compromissos reais da sua conta e dos seus projetos.</p></div><div className="flex gap-2"><button type="button" disabled={loading} onClick={() => loadEvents()} className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 text-sm font-semibold inline-flex items-center gap-2 disabled:opacity-50"><RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />Atualizar</button><button type="button" onClick={() => openCreate()} className="bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2 text-sm font-semibold inline-flex items-center gap-2"><Plus className="h-4 w-4" />Novo evento</button></div></header>
    {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300">{error}</div>}
    {notice && <div role="status" className="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950 p-4 text-sm text-emerald-700 dark:text-emerald-300">{notice}</div>}
    <div className="grid xl:grid-cols-[minmax(0,1fr)_340px] gap-5">
      <main className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 min-w-0">
        <div className="flex items-center justify-between gap-3 flex-wrap mb-4"><div className="flex items-center gap-2"><button type="button" onClick={goToday} className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-1.5 text-sm font-semibold">Hoje</button><button type="button" aria-label="Mês anterior" onClick={() => moveMonth(-1)} className="p-2 rounded-lg border border-slate-300 dark:border-slate-700"><ChevronLeft className="h-4 w-4" /></button><button type="button" aria-label="Próximo mês" onClick={() => moveMonth(1)} className="p-2 rounded-lg border border-slate-300 dark:border-slate-700"><ChevronRight className="h-4 w-4" /></button></div><h2 className="font-bold capitalize">{cursor.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</h2></div>
        <div className="grid grid-cols-7 text-center text-xs text-slate-500 dark:text-slate-400">{weekDays.map(day => <div key={day} className="py-2 font-semibold">{day}</div>)}</div>
        <div className="grid grid-cols-7 gap-1.5">{grid.map(date => {
          const inMonth = date.getMonth() === cursor.getMonth(); const selected = dateKey(date) === dateKey(selectedDate); const dayEvents = eventsForDate(date);
          return <button type="button" key={dateKey(date)} onClick={() => setSelectedDate(date)} className={`min-h-24 sm:min-h-28 text-left p-1.5 rounded-lg border transition ${selected ? 'border-primary-500 ring-2 ring-primary-100 dark:ring-primary-900' : 'border-slate-100 dark:border-slate-800 hover:border-slate-300'} ${inMonth ? '' : 'opacity-40'}`}><span className={`text-xs ${selected ? 'h-6 w-6 grid place-items-center bg-primary-600 text-white rounded-full' : ''}`}>{date.getDate()}</span><span className="mt-1 block space-y-1">{dayEvents.slice(0, 2).map(item => <span key={item.id} className={`block border-l-2 rounded px-1 py-0.5 text-[10px] truncate ${categories[item.categoria].card} ${item.status === 'cancelado' ? 'line-through opacity-60' : ''}`}><strong>{item.dia_inteiro ? 'Dia inteiro' : new Date(item.inicio_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</strong> {item.titulo}</span>)}{dayEvents.length > 2 && <span className="block text-[10px] text-slate-500 dark:text-slate-400">+{dayEvents.length - 2} mais</span>}</span></button>;
        })}</div>
      </main>
      <aside className="space-y-5">
        <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4"><h2 className="font-bold">{selectedDate.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })}</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{selectedEvents.length} evento(s)</p>{loading ? <div className="py-10 grid place-items-center"><Loader2 className="h-6 w-6 animate-spin text-primary-600" /></div> : selectedEvents.length ? <div className="mt-4 space-y-3">{selectedEvents.map(item => <article key={item.id} className={`border-l-4 rounded-lg p-3 bg-slate-50 dark:bg-slate-950 ${categories[item.categoria].card}`}><div className="flex justify-between gap-2"><div className="min-w-0"><h3 className={`font-semibold text-sm ${item.status === 'cancelado' ? 'line-through' : ''}`}>{item.titulo}</h3><p className="text-xs text-slate-500 dark:text-slate-400">{categories[item.categoria].label} · {statusLabels[item.status]}</p></div><div className="flex"><button type="button" aria-label={`Editar ${item.titulo}`} onClick={() => openEdit(item)} className="p-1.5"><Pencil className="h-4 w-4" /></button><button type="button" aria-label={`Excluir ${item.titulo}`} disabled={saving} onClick={() => setDeleteTarget(item)} className="p-1.5 text-red-600 disabled:opacity-50"><Trash2 className="h-4 w-4" /></button></div></div><p className="mt-2 text-xs flex items-center gap-1"><Clock className="h-3.5 w-3.5" />{item.dia_inteiro ? 'Dia inteiro' : formatRange(item)}</p>{(item.local || item.endereco) && <p className="mt-1 text-xs flex items-start gap-1 text-slate-500 dark:text-slate-400"><MapPin className="h-3.5 w-3.5 shrink-0" />{[item.local, item.endereco].filter(Boolean).join(' · ')}</p>}{item.projeto && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Projeto: {item.projeto.nome}</p>}{item.descricao && <p className="mt-2 text-xs whitespace-pre-wrap">{item.descricao}</p>}</article>)}</div> : <div className="py-8 text-center"><CalendarDays className="h-8 w-8 mx-auto text-slate-300" /><p className="text-sm text-slate-500 dark:text-slate-400 mt-2">Nenhum evento neste dia.</p><button type="button" onClick={() => openCreate(selectedDate)} className="mt-3 text-primary-600 dark:text-primary-400 text-sm font-semibold">Criar evento</button></div>}</section>
        <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4"><h2 className="font-bold text-sm mb-3">Categorias</h2><div className="space-y-2">{(Object.keys(categories) as Category[]).map(key => <label key={key} className="flex items-center justify-between text-sm"><span className="inline-flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: categories[key].color }} />{categories[key].label}</span><input type="checkbox" checked={enabled[key]} onChange={event => setEnabled({ ...enabled, [key]: event.target.checked })} /></label>)}</div></section>
      </aside>
    </div>
    {editingId !== undefined && <EventDialog form={form} setForm={setForm} projects={projects} editing={editingId !== null} saving={saving} error={error} onClose={() => { if (!saving) { setEditingId(undefined); setError(''); } }} onSave={save} />}
    {deleteTarget && <ConfirmActionDialog title="Excluir evento?" description={`O evento ${deleteTarget.titulo} será removido do calendário.`} actionLabel="Excluir evento" pendingLabel="Excluindo..." pending={saving} onCancel={() => setDeleteTarget(null)} onConfirm={() => remove(deleteTarget)} />}
  </div>;
}

function EventDialog({ form, setForm, projects, editing, saving, error, onClose, onSave }: { form: EventForm; setForm: (form: EventForm) => void; projects: Project[]; editing: boolean; saving: boolean; error: string; onClose: () => void; onSave: (event: FormEvent) => void }) {
  const dialog = useRef<HTMLDialogElement>(null); const firstField = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.current?.showModal();
    firstField.current?.focus();
    return () => { dialog.current?.close(); trigger?.focus(); };
  }, []);
  return <dialog ref={dialog} aria-labelledby="event-dialog-title" onCancel={event => { event.preventDefault(); onClose(); }} className="m-auto w-[min(720px,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl p-0 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xl backdrop:bg-slate-950/60"><form onSubmit={onSave}><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between"><div><h2 id="event-dialog-title" className="font-bold text-lg">{editing ? 'Editar evento' : 'Novo evento'}</h2><p className="text-sm text-slate-500 dark:text-slate-400">Campos obrigatórios estão marcados com *.</p></div><button type="button" aria-label="Fechar formulário" onClick={onClose}><X className="h-5 w-5" /></button></div><div className="p-5 grid sm:grid-cols-2 gap-4">{error && <div role="alert" className="sm:col-span-2 rounded-lg bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 p-3 text-sm">{error}</div>}
    <Field label="Título *" full><input ref={firstField} required maxLength={180} className="input mt-1" value={form.titulo} onChange={event => setForm({ ...form, titulo: event.target.value })} /></Field><Field label="Categoria *"><select required className="input mt-1" value={form.categoria} onChange={event => setForm({ ...form, categoria: event.target.value as Category })}>{Object.entries(categories).map(([value, info]) => <option key={value} value={value}>{info.label}</option>)}</select></Field><Field label="Projeto"><select className="input mt-1" value={form.projeto_id} onChange={event => setForm({ ...form, projeto_id: event.target.value })}><option value="">Sem projeto</option>{projects.map(item => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></Field><Field label="Início *"><input required type="datetime-local" className="input mt-1" value={form.inicio_em} onChange={event => setForm({ ...form, inicio_em: event.target.value })} /></Field><Field label="Término"><input type="datetime-local" min={form.inicio_em} className="input mt-1" value={form.fim_em} onChange={event => setForm({ ...form, fim_em: event.target.value })} /></Field><Field label="Status"><select className="input mt-1" value={form.status} onChange={event => setForm({ ...form, status: event.target.value as EventStatus })}>{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field><Field label="Local"><input maxLength={180} className="input mt-1" value={form.local} onChange={event => setForm({ ...form, local: event.target.value })} /></Field><Field label="Endereço" full><input maxLength={500} className="input mt-1" value={form.endereco} onChange={event => setForm({ ...form, endereco: event.target.value })} /></Field><Field label="Descrição" full><textarea maxLength={5000} className="input mt-1 min-h-24 resize-y" value={form.descricao} onChange={event => setForm({ ...form, descricao: event.target.value })} /></Field><label className="sm:col-span-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={form.dia_inteiro} onChange={event => setForm({ ...form, dia_inteiro: event.target.checked })} />Evento de dia inteiro</label>
    </div><div className="p-5 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3"><button type="button" onClick={onClose} disabled={saving} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 disabled:opacity-50">Cancelar</button><button type="submit" disabled={saving} className="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold disabled:opacity-50 inline-flex items-center gap-2">{saving && <Loader2 className="h-4 w-4 animate-spin" />}{editing ? 'Salvar alterações' : 'Criar evento'}</button></div></form></dialog>;
}

function Field({ label, children, full = false }: { label: string; children: React.ReactNode; full?: boolean }) { return <label className={`text-sm ${full ? 'sm:col-span-2' : ''}`}><span>{label}</span>{children}</label>; }
function formatRange(event: CalendarEvent) { const start = new Date(event.inicio_em); const end = event.fim_em ? new Date(event.fim_em) : null; const startText = start.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }); return end ? `${startText} – ${end.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}` : startText; }
