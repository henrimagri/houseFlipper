import { useEffect, useMemo, useRef, useState } from 'react';
import { AlertTriangle, ChevronRight, CircleDollarSign, HardHat, Loader2, Plus, RefreshCw, Search, WalletCards } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import { deriveWorkStatus, matchesWorkFilter, summarizeWorks, type WorkFilter, type WorkProject, type WorkSummary } from '@/lib/workView';

const money = (value: unknown) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = (value: unknown) => `${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
const formatDate = (value?: string | null) => value
  ? new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`))
  : 'Não informada';

const filters: Array<{ id: WorkFilter; label: string }> = [
  { id: 'all', label: 'Todas' },
  { id: 'planning', label: 'Planejamento' },
  { id: 'in_progress', label: 'Em andamento' },
  { id: 'delayed', label: 'Atrasadas' },
  { id: 'completed', label: 'Concluídas' },
];

const stageStatuses = [
  { value: 'pendente', label: 'Pendente' },
  { value: 'em_andamento', label: 'Em andamento' },
  { value: 'bloqueada', label: 'Bloqueada' },
  { value: 'concluida', label: 'Concluída' },
];

function errorMessage(error: any, fallback: string) {
  return Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || fallback;
}

export default function Obras() {
  const navigate = useNavigate();
  const loader = useRef(new PaginatedCollection<WorkProject>());
  const summaryController = useRef<AbortController>();
  const loadSequence = useRef(0);
  const [projects, setProjects] = useState<WorkProject[]>([]);
  const [summaries, setSummaries] = useState<Record<number, WorkSummary>>({});
  const [summaryErrors, setSummaryErrors] = useState<number[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [filter, setFilter] = useState<WorkFilter>('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  async function load(silent = false) {
    const sequence = ++loadSequence.current;
    summaryController.current?.abort();
    const summariesRequest = new AbortController();
    summaryController.current = summariesRequest;
    if (silent) setRefreshing(true); else setLoading(true);
    setError('');
    try {
      const items = await loader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/projetos', { params: { page, per_page: perPage }, signal });
        return response.data;
      });
      if (!items || sequence !== loadSequence.current) return;
      const results = await Promise.all(items.map(async project => {
        try {
          const response = await api.get<WorkSummary>(`/projetos/${project.id}/resumo`, { signal: summariesRequest.signal });
          return [project.id, response.data] as const;
        } catch (exception: any) {
          if (summariesRequest.signal.aborted) throw exception;
          return [project.id, null] as const;
        }
      }));
      if (summariesRequest.signal.aborted || sequence !== loadSequence.current) return;
      setProjects(items);
      setSummaries(Object.fromEntries(results.filter((entry): entry is readonly [number, WorkSummary] => entry[1] !== null)));
      setSummaryErrors(results.filter(([, summary]) => summary === null).map(([id]) => id));
      setSelectedId(current => current && items.some(item => item.id === current) ? current : (items[0]?.id ?? null));
    } catch (exception: any) {
      if (sequence === loadSequence.current && !summariesRequest.signal.aborted) setError(errorMessage(exception, 'Não foi possível carregar as obras.'));
    } finally {
      if (sequence === loadSequence.current) { setLoading(false); setRefreshing(false); }
    }
  }

  useEffect(() => {
    void load();
    return () => { loadSequence.current++; loader.current.cancel(); summaryController.current?.abort(); };
  }, []);

  const today = new Date().toISOString().slice(0, 10);
  const visible = useMemo(() => projects.filter(project => matchesWorkFilter(project, filter, search, today)), [projects, filter, search, today]);
  const totals = useMemo(() => summarizeWorks(projects, summaries, today), [projects, summaries, today]);
  const selected = projects.find(project => project.id === selectedId) ?? null;
  const selectedSummary = selected ? summaries[selected.id] : undefined;
  const selectedSummaryFailed = selected ? summaryErrors.includes(selected.id) : false;

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-slate-500 dark:text-slate-400"><Loader2 className="h-8 w-8 mx-auto animate-spin text-primary-600" /><p className="mt-3">Carregando obras...</p></div></div>;

  return <div className="space-y-6">
    <header className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div><div className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Execução dos projetos</div><h1 className="text-3xl font-black mt-2 text-slate-900 dark:text-slate-100">Obras</h1><p className="text-slate-500 dark:text-slate-400 mt-1">Acompanhe custos, prazos e etapas usando os dados cadastrados nos seus projetos.</p></div>
      <div className="flex flex-wrap gap-2">
        <button type="button" onClick={() => void load(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-bold disabled:opacity-60"><RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Atualizar</button>
        <button type="button" onClick={() => navigate('/projetos?novo=1')} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 text-sm font-bold"><Plus className="h-4 w-4" /> Nova obra</button>
      </div>
    </header>

    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950 p-4 text-red-800 dark:text-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><span>{error}</span><button type="button" onClick={() => void load()} className="font-bold underline underline-offset-2 w-fit">Tentar novamente</button></div>}
    {summaryErrors.length > 0 && <div role="alert" className="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950 p-4 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><span>O resumo de {summaryErrors.length} obra{summaryErrors.length === 1 ? '' : 's'} está indisponível. Totais financeiros incompletos foram ocultados.</span><button type="button" onClick={() => void load(true)} className="font-bold underline underline-offset-2 w-fit">Recarregar</button></div>}

    <section aria-label="Indicadores das obras" className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <Metric icon={HardHat} label="Obras cadastradas" value={String(totals.total)} detail={`${totals.inProgress} em andamento`} />
      <Metric icon={AlertTriangle} label="Com prazo vencido" value={String(totals.delayed)} detail="Sem conclusão registrada" tone={totals.delayed ? 'danger' : 'default'} />
      <Metric icon={WalletCards} label="Orçamento total" value={totals.financialComplete ? money(totals.budget) : 'Indisponível'} detail={totals.financialComplete ? 'Soma dos projetos' : 'Há resumos não carregados'} />
      <Metric icon={CircleDollarSign} label="Custo realizado" value={totals.financialComplete ? money(totals.spent) : 'Indisponível'} detail={totals.financialComplete ? `${money(totals.budget - totals.spent)} de saldo` : 'Há resumos não carregados'} />
    </section>

    <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 flex flex-col lg:flex-row gap-3 lg:items-center">
      <div className="flex flex-wrap gap-1" role="group" aria-label="Filtrar obras por status">{filters.map(item => <button key={item.id} type="button" aria-pressed={filter === item.id} onClick={() => setFilter(item.id)} className={`rounded-lg px-3 py-2 text-sm font-semibold ${filter === item.id ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}`}>{item.label}</button>)}</div>
      <label className="relative lg:ml-auto min-w-0 lg:w-80"><span className="sr-only">Buscar obra</span><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Buscar por projeto ou imóvel" className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-2 text-sm" /></label>
    </section>

    {projects.length === 0 && !error ? <EmptyState onCreate={() => navigate('/projetos?novo=1')} /> : <div className="grid xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start">
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden" aria-label="Lista de obras">
        {visible.length === 0 ? <div className="p-10 text-center text-slate-500 dark:text-slate-400"><Search className="h-8 w-8 mx-auto mb-3" /><p className="font-semibold text-slate-700 dark:text-slate-200">Nenhuma obra corresponde aos filtros.</p><button type="button" onClick={() => { setFilter('all'); setSearch(''); }} className="mt-3 text-primary-600 dark:text-primary-400 font-bold">Limpar filtros</button></div> : <div className="divide-y divide-slate-100 dark:divide-slate-800">{visible.map(project => <WorkRow key={project.id} project={project} summary={summaries[project.id]} selected={selectedId === project.id} today={today} onSelect={() => setSelectedId(project.id)} />)}</div>}
      </section>
      <aside className="xl:sticky xl:top-6">{selected ? <WorkDetail project={selected} summary={selectedSummary} summaryFailed={selectedSummaryFailed} onSaved={() => load(true)} onOpenProject={() => navigate('/projetos')} /> : <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-8 text-center text-slate-500">Selecione uma obra para ver as etapas.</div>}</aside>
    </div>}
  </div>;
}

function WorkRow({ project, summary, selected, today, onSelect }: { project: WorkProject; summary?: WorkSummary; selected: boolean; today: string; onSelect: () => void }) {
  return <button type="button" onClick={onSelect} className={`w-full text-left p-5 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition ${selected ? 'bg-primary-50/70 dark:bg-primary-950/40' : ''}`}>
    <div className="flex items-start gap-4"><div className="h-11 w-11 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0"><HardHat className="h-5 w-5" /></div><div className="min-w-0 flex-1">
      <div className="flex flex-wrap items-start justify-between gap-2"><div><h2 className="font-bold text-slate-900 dark:text-slate-100 truncate">{project.nome}</h2><p className="text-sm text-slate-500 dark:text-slate-400 truncate">{project.propriedade?.apelido || 'Imóvel não identificado'}</p></div><StatusBadge status={deriveWorkStatus(project, today)} /></div>
      <div className="mt-4 flex items-center gap-3"><div className="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden flex-1"><div className="h-full bg-primary-600 rounded-full" style={{ width: `${Math.min(100, Math.max(0, Number(summary?.progresso_pct || 0)))}%` }} /></div><span className="text-xs font-bold tabular-nums text-slate-600 dark:text-slate-300">{summary ? percent(summary.progresso_pct) : '—'}</span></div>
      <div className="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-slate-500 dark:text-slate-400"><span>Início<br /><b className="text-slate-700 dark:text-slate-200">{formatDate(project.data_inicio)}</b></span><span>Previsão<br /><b className="text-slate-700 dark:text-slate-200">{formatDate(project.data_termino_prevista)}</b></span><span>Orçamento<br /><b className="text-slate-700 dark:text-slate-200">{summary ? money(summary.orcamento_total) : 'Indisponível'}</b></span><span>Realizado<br /><b className="text-slate-700 dark:text-slate-200">{summary ? money(summary.custo_real) : 'Indisponível'}</b></span></div>
    </div><ChevronRight className="h-5 w-5 text-slate-400 shrink-0 mt-2" /></div>
  </button>;
}

function WorkDetail({ project, summary, summaryFailed, onSaved, onOpenProject }: { project: WorkProject; summary?: WorkSummary; summaryFailed: boolean; onSaved: () => Promise<void>; onOpenProject: () => void }) {
  return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
    <div className="p-5 border-b border-slate-100 dark:border-slate-800">
      <div className="flex items-start justify-between gap-3"><div><p className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Detalhes da obra</p><h2 className="text-xl font-black mt-1 text-slate-900 dark:text-slate-100">{project.nome}</h2><p className="text-sm text-slate-500 dark:text-slate-400">{project.propriedade?.apelido || 'Imóvel não identificado'}</p></div><StatusBadge status={deriveWorkStatus(project, new Date().toISOString().slice(0, 10))} /></div>
      {summary && <div className="grid grid-cols-3 gap-2 mt-5 text-center"><SmallMetric label="Progresso" value={percent(summary.progresso_pct)} /><SmallMetric label="Orçado" value={money(summary.orcamento_total)} /><SmallMetric label="Realizado" value={money(summary.custo_real)} /></div>}
    </div>
    <div className="p-5">
      <div className="flex items-center justify-between gap-3 mb-4"><h3 className="font-bold text-slate-900 dark:text-slate-100">Etapas</h3><button type="button" onClick={onOpenProject} className="text-sm font-bold text-primary-600 dark:text-primary-400">Gerenciar projeto</button></div>
      {summaryFailed ? <div role="alert" className="rounded-xl bg-amber-50 dark:bg-amber-950 p-4 text-sm text-amber-900 dark:text-amber-200">As etapas não puderam ser carregadas. Use “Atualizar” para tentar novamente.</div> : !summary ? <div className="flex items-center gap-2 text-sm text-slate-500"><Loader2 className="h-4 w-4 animate-spin" /> Carregando etapas...</div> : summary.etapas.length === 0 ? <p className="text-sm text-slate-500 dark:text-slate-400">Nenhuma etapa cadastrada neste projeto.</p> : <div className="space-y-3">{summary.etapas.map(stage => <StageEditor key={`${stage.id}-${stage.status}-${stage.progresso_pct}`} stage={stage} onSaved={onSaved} />)}</div>}
    </div>
  </div>;
}

function StageEditor({ stage, onSaved }: { stage: WorkSummary['etapas'][number]; onSaved: () => Promise<void> }) {
  const [status, setStatus] = useState(stage.status);
  const [progress, setProgress] = useState(String(stage.progresso_pct));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const changed = status !== stage.status || Number(progress) !== Number(stage.progresso_pct);

  async function save() {
    setSaving(true); setError('');
    try {
      await api.patch(`/projetos-etapas/${stage.id}`, { status, progresso_pct: Number(progress) });
      await onSaved();
    } catch (exception: any) {
      setError(errorMessage(exception, 'Não foi possível atualizar a etapa.'));
    } finally { setSaving(false); }
  }

  return <div className="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
    <div className="flex justify-between gap-3"><div className="min-w-0"><h4 className="font-semibold text-sm text-slate-900 dark:text-slate-100 truncate">{stage.nome}</h4><p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{money(stage.custo_real)} de {money(stage.orcamento)}</p></div><span className="text-sm font-bold tabular-nums">{percent(progress)}</span></div>
    <div className="h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mt-3"><div className="h-full bg-primary-600" style={{ width: `${Math.min(100, Math.max(0, Number(progress) || 0))}%` }} /></div>
    <div className="grid grid-cols-[1fr_88px] gap-2 mt-3">
      <label><span className="sr-only">Status de {stage.nome}</span><select value={status} onChange={event => setStatus(event.target.value)} className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2 py-2 text-xs">{stageStatuses.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
      <label className="relative"><span className="sr-only">Progresso de {stage.nome}</span><input type="number" min="0" max="100" step="1" value={progress} onChange={event => setProgress(event.target.value)} className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2 pr-6 py-2 text-xs" /><span className="absolute right-2 top-2 text-xs text-slate-400">%</span></label>
    </div>
    {error && <p role="alert" className="text-xs text-red-700 dark:text-red-300 mt-2">{error}</p>}
    {changed && <button type="button" disabled={saving || !Number.isInteger(Number(progress)) || Number(progress) < 0 || Number(progress) > 100} onClick={() => void save()} className="w-full mt-3 rounded-lg bg-primary-600 text-white py-2 text-xs font-bold disabled:opacity-60">{saving ? 'Salvando...' : 'Salvar etapa'}</button>}
  </div>;
}

function StatusBadge({ status }: { status: ReturnType<typeof deriveWorkStatus> }) {
  const map = {
    planning: ['Planejamento', 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'],
    in_progress: ['Em andamento', 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200'],
    delayed: ['Atrasada', 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'],
    paused: ['Pausada', 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200'],
    completed: ['Concluída', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'],
    cancelled: ['Cancelada', 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
  } as const;
  const [label, classes] = map[status];
  return <span className={`rounded-full px-2.5 py-1 text-xs font-bold whitespace-nowrap ${classes}`}>{label}</span>;
}

function Metric({ icon: Icon, label, value, detail, tone = 'default' }: { icon: typeof HardHat; label: string; value: string; detail: string; tone?: 'default' | 'danger' }) {
  return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div className="flex items-start justify-between gap-3"><div><p className="text-sm text-slate-500 dark:text-slate-400">{label}</p><p className={`text-2xl font-black mt-2 ${tone === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'}`}>{value}</p></div><div className="h-10 w-10 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="h-5 w-5" /></div></div><p className="text-xs text-slate-500 dark:text-slate-400 mt-2">{detail}</p></div>;
}

function SmallMetric({ label, value }: { label: string; value: string }) {
  return <div className="rounded-xl bg-slate-50 dark:bg-slate-950 p-3"><p className="text-[11px] text-slate-500 dark:text-slate-400">{label}</p><p className="text-sm font-bold text-slate-900 dark:text-slate-100 mt-1 break-words">{value}</p></div>;
}

function EmptyState({ onCreate }: { onCreate: () => void }) {
  return <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 py-16 px-6 text-center"><div className="h-14 w-14 rounded-2xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center mx-auto"><HardHat className="h-7 w-7" /></div><h2 className="text-xl font-black mt-5 text-slate-900 dark:text-slate-100">Nenhuma obra cadastrada</h2><p className="text-slate-500 dark:text-slate-400 mt-2 max-w-md mx-auto">Crie um projeto vinculado a um imóvel para acompanhar orçamento, despesas e etapas da obra.</p><button type="button" onClick={onCreate} className="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary-600 text-white px-5 py-3 font-bold"><Plus className="h-4 w-4" /> Criar primeira obra</button></div>;
}
