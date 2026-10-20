import { FormEvent, useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import api from '@/lib/api';
import DialogShell from '@/ui/DialogShell';
import ErrorState from '@/ui/ErrorState';
import PageHeader from '@/ui/PageHeader';

type Status = 'backlog' | 'todo' | 'doing' | 'waiting' | 'done';
type Priority = 'low' | 'normal' | 'high';
type Task = { id: number; title: string; description?: string; status: Status; priority: Priority; due_date?: string; position: number };
const COLUMNS: { value: Status; label: string; help: string; color: string }[] = [
  { value: 'backlog', label: 'Ideias', help: 'Anote o que deseja organizar', color: '#64748B' },
  { value: 'todo', label: 'A fazer', help: 'Próximas atividades', color: '#0EA5E9' },
  { value: 'doing', label: 'Em andamento', help: 'O que está sendo executado', color: '#8B5CF6' },
  { value: 'waiting', label: 'Aguardando', help: 'Dependências e retornos', color: '#F59E0B' },
  { value: 'done', label: 'Concluído', help: 'Atividades finalizadas', color: '#10B981' },
];
const emptyForm = { title: '', description: '', status: 'backlog' as Status, priority: 'normal' as Priority, due_date: '' };
const priorityLabel = { low: 'Baixa', normal: 'Normal', high: 'Alta' };

export default function PersonalKanban() {
  const [tasks, setTasks] = useState<Task[]>([]);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true); const [saving, setSaving] = useState(false); const [error, setError] = useState('');
  const [dialog, setDialog] = useState(false); const [editing, setEditing] = useState<Task | null>(null);
  const [form, setForm] = useState(emptyForm); const [dragging, setDragging] = useState<number | null>(null);

  async function load() {
    setLoading(true); setError('');
    try { const { data } = await api.get('/personal-board/tasks'); setTasks(data.data ?? []); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível carregar seu organizador.'); }
    finally { setLoading(false); }
  }
  useEffect(() => { void load(); }, []);
  const filtered = useMemo(() => {
    const term = query.trim().toLocaleLowerCase('pt-BR');
    return term ? tasks.filter((task) => `${task.title} ${task.description ?? ''}`.toLocaleLowerCase('pt-BR').includes(term)) : tasks;
  }, [tasks, query]);

  function openNew(status: Status = 'backlog') { setEditing(null); setForm({ ...emptyForm, status }); setDialog(true); }
  function openEdit(task: Task) { setEditing(task); setForm({ title: task.title, description: task.description ?? '', status: task.status, priority: task.priority, due_date: task.due_date?.slice(0, 10) ?? '' }); setDialog(true); }
  async function submit(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      if (editing) {
        const { data } = await api.patch(`/personal-board/tasks/${editing.id}`, form);
        setTasks((current) => current.map((task) => task.id === editing.id ? data.data : task));
      } else {
        const { data } = await api.post('/personal-board/tasks', form);
        setTasks((current) => [...current, data.data]);
      }
      setDialog(false); setEditing(null); setForm(emptyForm);
    } catch (exception: any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar a tarefa.'); }
    finally { setSaving(false); }
  }
  async function move(task: Task, status: Status) {
    if (task.status === status) return;
    const previous = task.status; setTasks((current) => current.map((item) => item.id === task.id ? { ...item, status } : item));
    try { await api.patch(`/personal-board/tasks/${task.id}`, { status }); }
    catch (exception: any) { setTasks((current) => current.map((item) => item.id === task.id ? { ...item, status: previous } : item)); setError(exception.response?.data?.message ?? 'Não foi possível mover a tarefa.'); }
  }
  async function remove(task: Task) {
    if (!window.confirm(`Remover “${task.title}”?`)) return;
    try { await api.delete(`/personal-board/tasks/${task.id}`); setTasks((current) => current.filter((item) => item.id !== task.id)); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível remover a tarefa.'); }
  }
  function shift(task: Task, amount: -1 | 1) { const index = COLUMNS.findIndex((column) => column.value === task.status); const target = COLUMNS[index + amount]; if (target) void move(task, target.value); }

  return <div className="space-y-5">
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><PageHeader title="Meu organizador" subtitle="Organize suas próprias atividades em um quadro visual. Este espaço é pessoal e não altera clientes ou leads."/><button type="button" onClick={()=>openNew()} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white"><Plus className="h-4 w-4"/>Nova tarefa</button></div>
    {error && <ErrorState message={error}/>}
    <label className="relative block max-w-xl"><span className="sr-only">Buscar tarefas</span><Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400"/><input className="input pl-9" value={query} onChange={(event)=>setQuery(event.target.value)} placeholder="Buscar nas suas tarefas"/></label>
    {loading ? <p className="py-12 text-center text-slate-500">Carregando seu quadro...</p> : <div className="overflow-x-auto pb-4"><div className="flex min-w-max items-start gap-3">{COLUMNS.map((column, columnIndex) => {
      const cards = filtered.filter((task)=>task.status===column.value);
      return <section key={column.value} onDragOver={(event)=>event.preventDefault()} onDrop={()=>{const task=tasks.find((item)=>item.id===dragging);if(task)void move(task,column.value);setDragging(null);}} className="w-72 shrink-0 rounded-2xl bg-slate-100 p-3 dark:bg-slate-900">
        <header className="mb-3"><div className="flex items-center justify-between"><div className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full" style={{background:column.color}}/><h2 className="font-bold">{column.label}</h2></div><span className="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-500 dark:bg-slate-800">{cards.length}</span></div><p className="mt-1 text-xs text-slate-500">{column.help}</p></header>
        <div className="space-y-3">{cards.map((task)=><article key={task.id} draggable onDragStart={()=>setDragging(task.id)} onDragEnd={()=>setDragging(null)} className="cursor-grab rounded-xl border border-slate-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800"><div className="flex items-start justify-between gap-2"><h3 className="font-bold">{task.title}</h3><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${task.priority==='high'?'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300':task.priority==='low'?'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200':'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'}`}>{priorityLabel[task.priority]}</span></div>{task.description&&<p className="mt-2 line-clamp-3 text-sm text-slate-600 dark:text-slate-300">{task.description}</p>}{task.due_date&&<p className="mt-3 flex items-center gap-1.5 text-xs text-slate-500"><CalendarDays className="h-3.5 w-3.5"/>{new Date(task.due_date+'T12:00:00').toLocaleDateString('pt-BR')}</p>}<div className="mt-3 flex items-center justify-between border-t pt-2 dark:border-slate-700"><button type="button" aria-label="Mover para a coluna anterior" disabled={columnIndex===0} onClick={()=>shift(task,-1)} className="rounded-lg p-1.5 disabled:opacity-20"><ChevronLeft className="h-4 w-4"/></button><span><button type="button" aria-label="Editar tarefa" onClick={()=>openEdit(task)} className="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700"><Pencil className="h-4 w-4"/></button><button type="button" aria-label="Excluir tarefa" onClick={()=>void remove(task)} className="rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-950"><Trash2 className="h-4 w-4"/></button></span><button type="button" aria-label="Mover para a próxima coluna" disabled={columnIndex===COLUMNS.length-1} onClick={()=>shift(task,1)} className="rounded-lg p-1.5 disabled:opacity-20"><ChevronRight className="h-4 w-4"/></button></div></article>)}<button type="button" onClick={()=>openNew(column.value)} className="flex w-full items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 p-3 text-xs font-semibold text-slate-500 hover:border-primary-400 hover:text-primary-600 dark:border-slate-700"><Plus className="h-3.5 w-3.5"/>Adicionar tarefa</button></div>
      </section>;
    })}</div></div>}
    {dialog&&<DialogShell title={editing?'Editar tarefa':'Nova tarefa'} description="Registre uma atividade pessoal e mova-a pelo quadro conforme avançar." onClose={()=>setDialog(false)} footer={<div className="flex justify-end gap-2"><button type="button" onClick={()=>setDialog(false)} className="rounded-xl border px-4 py-2.5 font-semibold">Cancelar</button><button type="submit" form="personal-task-form" disabled={saving} className="rounded-xl bg-primary-600 px-4 py-2.5 font-bold text-white disabled:opacity-60">{saving?'Salvando...':'Salvar tarefa'}</button></div>}><form id="personal-task-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-medium sm:col-span-2">Nome da tarefa<input autoFocus required maxLength={180} className="input mt-1" value={form.title} onChange={(event)=>setForm({...form,title:event.target.value})}/></label><label className="text-sm font-medium sm:col-span-2">Descrição<textarea maxLength={3000} rows={4} className="input mt-1" value={form.description} onChange={(event)=>setForm({...form,description:event.target.value})}/></label><label className="text-sm font-medium">Coluna<select className="input mt-1" value={form.status} onChange={(event)=>setForm({...form,status:event.target.value as Status})}>{COLUMNS.map((column)=><option key={column.value} value={column.value}>{column.label}</option>)}</select></label><label className="text-sm font-medium">Prioridade<select className="input mt-1" value={form.priority} onChange={(event)=>setForm({...form,priority:event.target.value as Priority})}><option value="low">Baixa</option><option value="normal">Normal</option><option value="high">Alta</option></select></label><label className="text-sm font-medium sm:col-span-2">Prazo<input type="date" className="input mt-1" value={form.due_date} onChange={(event)=>setForm({...form,due_date:event.target.value})}/></label></form></DialogShell>}
  </div>;
}
