import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { AlertTriangle, CalendarDays, CheckCircle2, CircleDollarSign, ClipboardCheck, Clock3, Edit3, Loader2, Plus, RefreshCw, Search, Trash2, UserRound, X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import { emptyTaskForm, isTaskOverdue, taskPayload, type TaskForm } from '@/lib/taskView';

type Project = { id: number; nome: string };
type Stage = { id: number; nome: string };
type User = { id: number; name: string };
type TaskStatus = TaskForm['status'];
type Priority = TaskForm['prioridade'];
type Task = {
  id: number; projeto_id: number; projeto?: Project | null; etapa_id?: number | null; etapa?: Stage | null;
  responsavel_user_id?: number | null; responsavel?: { id: number; nome: string } | null; titulo: string;
  descricao?: string | null; data_prevista?: string | null; data_conclusao?: string | null; prioridade: Priority;
  status: TaskStatus; custo_estimado?: number | null; custo_real?: number | null;
};

const statuses: Array<{ value: TaskStatus; label: string }> = [
  { value: 'pendente', label: 'Pendente' }, { value: 'em_andamento', label: 'Em andamento' },
  { value: 'concluida', label: 'Concluída' }, { value: 'cancelada', label: 'Cancelada' },
];
const priorities: Array<{ value: Priority; label: string }> = [
  { value: 'baixa', label: 'Baixa' }, { value: 'media', label: 'Média' }, { value: 'alta', label: 'Alta' }, { value: 'critica', label: 'Crítica' },
];
const today = () => new Date().toISOString().slice(0, 10);
const formatDate = (value?: string | null) => value ? new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`)) : 'Sem prazo';
const money = (value?: number | null) => value === null || value === undefined ? 'Não informado' : Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const apiError = (error: any, fallback: string) => Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || fallback;

function toForm(task: Task): TaskForm {
  const text = (value: unknown) => value === null || value === undefined ? '' : String(value);
  return { projeto_id: String(task.projeto_id), etapa_id: text(task.etapa_id), responsavel_user_id: text(task.responsavel_user_id), titulo: task.titulo, descricao: task.descricao ?? '', data_prevista: task.data_prevista ?? '', data_conclusao: task.data_conclusao ?? '', prioridade: task.prioridade, status: task.status, custo_estimado: text(task.custo_estimado), custo_real: text(task.custo_real) };
}

export default function Tarefas() {
  const navigate = useNavigate();
  const taskLoader = useRef(new PaginatedCollection<Task>());
  const projectLoader = useRef(new PaginatedCollection<Project>());
  const stageController = useRef<AbortController>();
  const sequence = useRef(0);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [me, setMe] = useState<User | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('todos');
  const [priority, setPriority] = useState('todas');
  const [projectId, setProjectId] = useState('todos');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dialog, setDialog] = useState<{ id: number | null; form: TaskForm } | null>(null);
  const [stages, setStages] = useState<Stage[]>([]);
  const [stagesLoading, setStagesLoading] = useState(false);
  const [formError, setFormError] = useState('');
  const [deleteId, setDeleteId] = useState<number | null>(null);

  async function load(silent = false) {
    const request = ++sequence.current;
    if (silent) setRefreshing(true); else setLoading(true);
    setError('');
    try {
      const [taskItems, projectItems, meResponse] = await Promise.all([
        taskLoader.current.load(async (page, perPage, signal) => (await api.get('/tarefas', { params: { page, per_page: perPage }, signal })).data),
        projectLoader.current.load(async (page, perPage, signal) => (await api.get('/projetos', { params: { page, per_page: perPage }, signal })).data),
        api.get<User>('/auth/me'),
      ]);
      if (request !== sequence.current || !taskItems || !projectItems) return false;
      setTasks(taskItems); setProjects(projectItems); setMe(meResponse.data);
      return true;
    } catch (exception: any) {
      if (request === sequence.current) setError(apiError(exception, 'Não foi possível carregar as tarefas.'));
      return false;
    } finally {
      if (request === sequence.current) { setLoading(false); setRefreshing(false); }
    }
  }

  useEffect(() => {
    void load();
    return () => { sequence.current++; taskLoader.current.cancel(); projectLoader.current.cancel(); stageController.current?.abort(); };
  }, []);

  async function loadStages(id: string, selectedStage = '') {
    stageController.current?.abort(); setStages([]);
    if (!id) return;
    const controller = new AbortController(); stageController.current = controller; setStagesLoading(true); setFormError('');
    try {
      const response = await api.get(`/projetos/${id}`, { signal: controller.signal });
      const items: Stage[] = response.data.data?.etapas ?? [];
      setStages(items);
      if (selectedStage && !items.some(item => String(item.id) === selectedStage)) setDialog(current => current ? { ...current, form: { ...current.form, etapa_id: '' } } : current);
    } catch (exception: any) {
      if (!controller.signal.aborted) setFormError(apiError(exception, 'Não foi possível carregar as etapas do projeto.'));
    } finally { if (!controller.signal.aborted) setStagesLoading(false); }
  }

  const visible = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase('pt-BR');
    return tasks.filter(task => {
      if (status !== 'todos' && task.status !== status) return false;
      if (priority !== 'todas' && task.prioridade !== priority) return false;
      if (projectId !== 'todos' && String(task.projeto_id) !== projectId) return false;
      return !needle || [task.titulo, task.descricao, task.projeto?.nome, task.etapa?.nome, task.responsavel?.nome].filter(Boolean).join(' ').toLocaleLowerCase('pt-BR').includes(needle);
    });
  }, [tasks, search, status, priority, projectId]);
  const overdue = tasks.filter(task => isTaskOverdue(task, today())).length;
  const deleteTarget = deleteId === null ? null : tasks.find(task => task.id === deleteId) ?? null;

  function openCreate() {
    const form = { ...emptyTaskForm, projeto_id: projects[0] ? String(projects[0].id) : '', responsavel_user_id: me ? String(me.id) : '' };
    setDialog({ id: null, form }); setFormError(''); setDeleteId(null); setNotice(''); void loadStages(form.projeto_id);
  }
  function openEdit(task: Task) { const form = toForm(task); setDialog({ id: task.id, form }); setFormError(''); setDeleteId(null); setNotice(''); void loadStages(form.projeto_id, form.etapa_id); }
  function setField<K extends keyof TaskForm>(field: K, value: TaskForm[K]) { setDialog(current => current ? { ...current, form: { ...current.form, [field]: value } } : current); }
  function changeProject(value: string) { setDialog(current => current ? { ...current, form: { ...current.form, projeto_id: value, etapa_id: '' } } : current); void loadStages(value); }

  async function save(event: FormEvent) {
    event.preventDefault(); if (!dialog) return;
    setSaving(true); setFormError(''); setNotice('');
    try {
      if (dialog.id) await api.put(`/tarefas/${dialog.id}`, taskPayload(dialog.form)); else await api.post('/tarefas', taskPayload(dialog.form));
      const message = dialog.id ? 'Tarefa atualizada.' : 'Tarefa criada.'; setDialog(null); const refreshed = await load(true); setNotice(refreshed ? message : `${message} A lista não pôde ser recarregada; use Atualizar antes de repetir a operação.`);
    } catch (exception: any) { setFormError(apiError(exception, 'Não foi possível salvar a tarefa.')); }
    finally { setSaving(false); }
  }

  async function complete(task: Task) {
    setSaving(true); setError(''); setNotice('');
    try { await api.put(`/tarefas/${task.id}`, taskPayload({ ...toForm(task), status: 'concluida', data_conclusao: today() })); const refreshed = await load(true); setNotice(refreshed ? 'Tarefa concluída.' : 'Tarefa concluída, mas a lista não pôde ser recarregada. Use Atualizar.'); }
    catch (exception: any) { setError(apiError(exception, 'Não foi possível concluir a tarefa.')); }
    finally { setSaving(false); }
  }
  async function remove(task: Task) {
    setSaving(true); setError(''); setNotice('');
    try { await api.delete(`/tarefas/${task.id}`); setDeleteId(null); const refreshed = await load(true); setNotice(refreshed ? 'Tarefa excluída.' : 'Tarefa excluída, mas a lista não pôde ser recarregada. Use Atualizar.'); }
    catch (exception: any) { setError(apiError(exception, 'Não foi possível excluir a tarefa.')); setDeleteId(null); }
    finally { setSaving(false); }
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-slate-500 dark:text-slate-400"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando tarefas...</p></div></div>;

  return <div className="space-y-6">
    <header className="flex flex-col lg:flex-row lg:items-end justify-between gap-4"><div><div className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Execução da operação</div><h1 className="text-3xl font-black mt-2">Tarefas</h1><p className="text-slate-500 dark:text-slate-400 mt-1">Organize atividades reais por projeto, etapa, prioridade, prazo e responsável.</p></div><div className="flex gap-2"><button type="button" onClick={() => void load(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-bold disabled:opacity-60"><RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Atualizar</button><button type="button" onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 text-white px-5 py-3 text-sm font-bold"><Plus className="h-4 w-4" /> Nova tarefa</button></div></header>
    {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-4 text-red-800 dark:text-red-200">{error}</div>}
    {notice && <div role="status" className="rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950 p-4 text-emerald-800 dark:text-emerald-200">{notice}</div>}
    <section aria-label="Indicadores das tarefas" className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4"><Metric icon={ClipboardCheck} label="Tarefas cadastradas" value={String(tasks.length)} detail="Registros ativos da conta" /><Metric icon={Clock3} label="Em andamento" value={String(tasks.filter(task => task.status === 'em_andamento').length)} detail="Execução iniciada" /><Metric icon={AlertTriangle} label="Atrasadas" value={String(overdue)} detail="Prazo vencido e não concluídas" danger={overdue > 0} /><Metric icon={CheckCircle2} label="Concluídas" value={String(tasks.filter(task => task.status === 'concluida').length)} detail="Com data de conclusão" /></section>
    <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 grid md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_160px_220px] gap-2"><label className="relative"><span className="sr-only">Buscar tarefas</span><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Buscar título, projeto ou etapa" className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-2 text-sm" /></label><FilterSelect label="Status" value={status} onChange={setStatus}><option value="todos">Todos os status</option>{statuses.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</FilterSelect><FilterSelect label="Prioridade" value={priority} onChange={setPriority}><option value="todas">Todas</option>{priorities.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</FilterSelect><FilterSelect label="Projeto" value={projectId} onChange={setProjectId}><option value="todos">Todos os projetos</option>{projects.map(item => <option key={item.id} value={item.id}>{item.nome}</option>)}</FilterSelect></section>
    {tasks.length === 0 && !error ? <EmptyState hasProjects={projects.length > 0} onCreate={openCreate} onProject={() => navigate('/projetos?novo=1')} /> : visible.length === 0 ? <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-10 text-center"><Search className="h-8 w-8 mx-auto text-slate-400" /><p className="mt-3 font-bold">Nenhuma tarefa corresponde aos filtros.</p><button type="button" onClick={() => { setSearch(''); setStatus('todos'); setPriority('todas'); setProjectId('todos'); }} className="mt-3 text-primary-600 dark:text-primary-400 font-bold">Limpar filtros</button></div> : <section aria-label="Lista de tarefas" className="grid lg:grid-cols-2 gap-4">{visible.map(task => <TaskCard key={task.id} task={task} saving={saving} onEdit={() => openEdit(task)} onComplete={() => void complete(task)} onAskDelete={() => setDeleteId(task.id)} />)}</section>}
    {dialog && <TaskDialog editing={dialog.id !== null} form={dialog.form} projects={projects} stages={stages} me={me} stagesLoading={stagesLoading} saving={saving} error={formError} onField={setField} onProject={changeProject} onClose={() => !saving && setDialog(null)} onSave={save} />}
    {deleteTarget && <ConfirmActionDialog title="Excluir tarefa?" description={`A tarefa ${deleteTarget.titulo} deixará de aparecer nas consultas.`} actionLabel="Excluir tarefa" pendingLabel="Excluindo..." pending={saving} onCancel={() => setDeleteId(null)} onConfirm={() => remove(deleteTarget)} />}
  </div>;
}

function TaskCard({ task, saving, onEdit, onComplete, onAskDelete }: { task: Task; saving: boolean; onEdit: () => void; onComplete: () => void; onAskDelete: () => void }) {
  const overdue = isTaskOverdue(task, today());
  return <article className={`rounded-2xl border bg-white dark:bg-slate-900 p-5 ${overdue ? 'border-red-300 dark:border-red-900' : 'border-slate-200 dark:border-slate-700'}`}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><PriorityBadge value={task.prioridade} /><StatusBadge value={task.status} />{overdue && <span className="rounded-full bg-red-100 dark:bg-red-950 text-red-800 dark:text-red-200 px-2 py-1 text-[11px] font-bold">Atrasada</span>}</div><h2 className="font-black text-lg mt-3 break-words">{task.titulo}</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{task.projeto?.nome || `Projeto #${task.projeto_id}`}{task.etapa ? ` · ${task.etapa.nome}` : ''}</p></div><div className="flex shrink-0"><button type="button" onClick={onEdit} aria-label={`Editar ${task.titulo}`} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"><Edit3 className="h-4 w-4" /></button><button type="button" onClick={onAskDelete} aria-label={`Excluir ${task.titulo}`} className="p-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950"><Trash2 className="h-4 w-4" /></button></div></div>
    {task.descricao && <p className="text-sm text-slate-600 dark:text-slate-300 mt-4 whitespace-pre-wrap break-words">{task.descricao}</p>}
    <div className="grid grid-cols-2 gap-3 mt-4 text-xs"><Info icon={CalendarDays} label="Prazo" value={formatDate(task.data_prevista)} danger={overdue} /><Info icon={UserRound} label="Responsável" value={task.responsavel?.nome || 'Não atribuído'} /><Info icon={CircleDollarSign} label="Estimado" value={money(task.custo_estimado)} /><Info icon={CircleDollarSign} label="Real" value={money(task.custo_real)} /></div>
    {task.status !== 'concluida' && task.status !== 'cancelada' && <button type="button" disabled={saving} onClick={onComplete} className="mt-4 w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white py-2 text-sm font-bold disabled:opacity-60 inline-flex items-center justify-center gap-2"><CheckCircle2 className="h-4 w-4" /> Marcar como concluída</button>}
  </article>;
}

function TaskDialog({ editing, form, projects, stages, me, stagesLoading, saving, error, onField, onProject, onClose, onSave }: { editing: boolean; form: TaskForm; projects: Project[]; stages: Stage[]; me: User | null; stagesLoading: boolean; saving: boolean; error: string; onField: <K extends keyof TaskForm>(field: K, value: TaskForm[K]) => void; onProject: (value: string) => void; onClose: () => void; onSave: (event: FormEvent) => void }) {
  const dialog = useRef<HTMLDialogElement>(null); const first = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.current?.showModal();
    first.current?.focus();
    return () => { dialog.current?.close(); trigger?.focus(); };
  }, []);
  return <dialog ref={dialog} aria-labelledby="task-dialog-title" onCancel={event => { event.preventDefault(); onClose(); }} className="m-auto w-[min(760px,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl p-0 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xl backdrop:bg-slate-950/60"><form onSubmit={onSave}><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between gap-3"><div><h2 id="task-dialog-title" className="font-black text-xl">{editing ? 'Editar tarefa' : 'Nova tarefa'}</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Campos obrigatórios estão marcados com *.</p></div><button type="button" onClick={onClose} aria-label="Fechar formulário"><X className="h-5 w-5" /></button></div><div className="p-5 grid sm:grid-cols-2 gap-4">
    {error && <p role="alert" className="sm:col-span-2 rounded-lg bg-red-50 dark:bg-red-950 p-3 text-sm text-red-800 dark:text-red-200">{error}</p>}
    {projects.length === 0 && <div className="sm:col-span-2 rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 p-4 text-sm text-amber-900 dark:text-amber-200">Cadastre um projeto antes de criar tarefas.</div>}
    <Field label="Título *" full><input ref={first} required maxLength={200} value={form.titulo} onChange={event => onField('titulo', event.target.value)} className="input mt-1" /></Field>
    <Field label="Projeto *"><select required value={form.projeto_id} onChange={event => onProject(event.target.value)} className="input mt-1"><option value="">Selecione</option>{projects.map(item => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></Field>
    <Field label="Etapa"><select disabled={!form.projeto_id || stagesLoading} value={form.etapa_id} onChange={event => onField('etapa_id', event.target.value)} className="input mt-1 disabled:opacity-60"><option value="">{stagesLoading ? 'Carregando...' : 'Sem etapa'}</option>{stages.map(item => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></Field>
    <Field label="Prioridade *"><select required value={form.prioridade} onChange={event => onField('prioridade', event.target.value as Priority)} className="input mt-1">{priorities.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field>
    <Field label="Status *"><select required value={form.status} onChange={event => onField('status', event.target.value as TaskStatus)} className="input mt-1">{statuses.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field>
    <Field label="Responsável"><select value={form.responsavel_user_id} onChange={event => onField('responsavel_user_id', event.target.value)} className="input mt-1"><option value="">Sem responsável</option>{me && <option value={me.id}>{me.name} (eu)</option>}{form.responsavel_user_id && me && form.responsavel_user_id !== String(me.id) && <option value={form.responsavel_user_id}>Responsável atual</option>}</select></Field>
    <Field label="Prazo"><input type="date" value={form.data_prevista} onChange={event => onField('data_prevista', event.target.value)} className="input mt-1" /></Field>
    {form.status === 'concluida' && <Field label="Data de conclusão"><input type="date" value={form.data_conclusao} onChange={event => onField('data_conclusao', event.target.value)} className="input mt-1" /></Field>}
    <Field label="Custo estimado"><input type="number" min="0" max="999999999999" step="0.01" value={form.custo_estimado} onChange={event => onField('custo_estimado', event.target.value)} className="input mt-1" /></Field>
    <Field label="Custo real"><input type="number" min="0" max="999999999999" step="0.01" value={form.custo_real} onChange={event => onField('custo_real', event.target.value)} className="input mt-1" /></Field>
    <Field label="Descrição" full><textarea maxLength={5000} rows={4} value={form.descricao} onChange={event => onField('descricao', event.target.value)} className="input mt-1 resize-y" /></Field>
  </div><div className="p-5 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-2"><button type="button" onClick={onClose} disabled={saving} className="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-bold">Cancelar</button><button type="submit" disabled={saving || projects.length === 0 || stagesLoading} className="rounded-lg bg-primary-600 text-white px-5 py-2 text-sm font-bold disabled:opacity-60">{saving ? 'Salvando...' : editing ? 'Salvar alterações' : 'Criar tarefa'}</button></div></form></dialog>;
}

function Field({ label, children, full = false }: { label: string; children: React.ReactNode; full?: boolean }) { return <label className={`text-sm ${full ? 'sm:col-span-2' : ''}`}><span className="font-semibold text-slate-600 dark:text-slate-300">{label}</span>{children}</label>; }
function FilterSelect({ label, value, onChange, children }: { label: string; value: string; onChange: (value: string) => void; children: React.ReactNode }) { return <label><span className="sr-only">{label}</span><select aria-label={label} value={value} onChange={event => onChange(event.target.value)} className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm">{children}</select></label>; }
function Info({ icon: Icon, label, value, danger = false }: { icon: typeof CalendarDays; label: string; value: string; danger?: boolean }) { return <div className="rounded-lg bg-slate-50 dark:bg-slate-950 p-3"><Icon className={`h-4 w-4 ${danger ? 'text-red-600' : 'text-primary-600 dark:text-primary-400'}`} /><p className="text-[11px] text-slate-500 dark:text-slate-400 mt-2">{label}</p><p className={`text-sm font-bold mt-0.5 ${danger ? 'text-red-600 dark:text-red-400' : ''}`}>{value}</p></div>; }
function Metric({ icon: Icon, label, value, detail, danger = false }: { icon: typeof ClipboardCheck; label: string; value: string; detail: string; danger?: boolean }) { return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div className="flex justify-between gap-3"><div><p className="text-sm text-slate-500 dark:text-slate-400">{label}</p><p className={`text-2xl font-black mt-2 ${danger ? 'text-red-600 dark:text-red-400' : ''}`}>{value}</p></div><span className="h-10 w-10 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="h-5 w-5" /></span></div><p className="text-xs text-slate-500 dark:text-slate-400 mt-2">{detail}</p></div>; }
function StatusBadge({ value }: { value: TaskStatus }) { const labels: Record<TaskStatus, string> = { pendente: 'Pendente', em_andamento: 'Em andamento', concluida: 'Concluída', cancelada: 'Cancelada' }; const classes: Record<TaskStatus, string> = { pendente: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300', em_andamento: 'bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-200', concluida: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-200', cancelada: 'bg-red-100 dark:bg-red-950 text-red-800 dark:text-red-200' }; return <span className={`rounded-full px-2 py-1 text-[11px] font-bold ${classes[value]}`}>{labels[value]}</span>; }
function PriorityBadge({ value }: { value: Priority }) { const labels: Record<Priority, string> = { baixa: 'Baixa', media: 'Média', alta: 'Alta', critica: 'Crítica' }; const classes: Record<Priority, string> = { baixa: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300', media: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200', alta: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200', critica: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' }; return <span className={`rounded-full px-2 py-1 text-[11px] font-bold ${classes[value]}`}>{labels[value]}</span>; }
function EmptyState({ hasProjects, onCreate, onProject }: { hasProjects: boolean; onCreate: () => void; onProject: () => void }) { return <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-14 text-center"><ClipboardCheck className="h-10 w-10 mx-auto text-primary-600 dark:text-primary-400" /><h2 className="font-black text-xl mt-4">Nenhuma tarefa cadastrada</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-2">{hasProjects ? 'Crie a primeira tarefa e vincule-a a um projeto ou etapa.' : 'Cadastre um projeto antes de organizar suas tarefas.'}</p><button type="button" onClick={hasProjects ? onCreate : onProject} className="mt-5 rounded-xl bg-primary-600 text-white px-5 py-3 font-bold">{hasProjects ? 'Criar primeira tarefa' : 'Criar projeto'}</button></div>; }

