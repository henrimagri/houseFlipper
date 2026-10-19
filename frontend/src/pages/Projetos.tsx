import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { ArrowRight, Briefcase, Calendar, CircleDollarSign, Eye, Hammer, Home, ListChecks, Loader2, Pencil, Plus, Receipt, Search, SlidersHorizontal, Target, Trash2, TrendingUp, X } from 'lucide-react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import { normalizePaymentStatus, summarizeExpenses } from '@/lib/expenseSummary';
import { manualProjectPayload, type ManualProjectForm } from '@/lib/projectForm';
import { summarizeProjects } from '@/lib/projectSummary';

const money = (value: unknown) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = (value: unknown) => `${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;

type Projeto = {
  id: number;
  nome: string;
  descricao?: string;
  status?: string;
  orcamento_total?: number;
  propriedade?: { id: number; apelido: string };
  data_inicio?: string;
  data_termino_prevista?: string;
};

type ResumoProjeto = {
  orcamento_total: number;
  custo_real: number;
  economia: number;
  consumido_pct: number;
  progresso_pct: number;
  etapas: Array<{ id: number; nome: string; orcamento: number; custo_real: number; economia: number; status: string; progresso_pct: number }>;
  roi: { lucro: number; roi_pct: number; valor_aquisicao: number; total_despesas: number; custo_total: number; valor_liquido_venda: number; tem_venda: boolean };
};

type Categoria = { id: number; nome: string; tipo: string };
type Despesa = { id: number; descricao: string; valor: number; data_despesa: string; data_pagamento?: string | null; status_pagamento?: string; forma_pagamento?: string | null; documento_numero?: string | null; observacoes?: string | null; etapa_id?: number; categoria_despesa_id?: number; categoria?: { nome: string } };
type Propriedade = { id: number; apelido: string; tipo?: string; endereco?: { cidade?: string; estado?: string } };

export default function Projetos() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [projects, setProjects] = useState<Projeto[]>([]);
  const [summaries, setSummaries] = useState<Record<number, ResumoProjeto>>({});
  const [summaryErrors, setSummaryErrors] = useState<number[]>([]);
  const [projectCovers, setProjectCovers] = useState<Record<number, string>>({});
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Categoria[]>([]);
  const [expenses, setExpenses] = useState<Despesa[]>([]);
  const expenseLoader = useRef(new PaginatedCollection<Despesa>());
  const projectLoader = useRef(new PaginatedCollection<Projeto>());
  const expenseLoadSequence = useRef(0);
  const selectedProject = useRef(selectedId);
  selectedProject.current = selectedId;
  const [expenseError, setExpenseError] = useState('');
  const [expensesLoading, setExpensesLoading] = useState(false);
  const [showExpenses, setShowExpenses] = useState(false);
  const [expenseForm, setExpenseForm] = useState<any>({ descricao: '', valor: 0, etapa_id: '', categoria_despesa_id: '', data_despesa: new Date().toISOString().slice(0, 10), status_pagamento: 'pago' });
  const [newStage, setNewStage] = useState({ nome: '', orcamento: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [manualOpen, setManualOpen] = useState(false);
  const [editingProject, setEditingProject] = useState<Projeto | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [manualLoading, setManualLoading] = useState(false);
  const [manualError, setManualError] = useState('');
  const [properties, setProperties] = useState<Propriedade[]>([]);
  const propertyLoader = useRef(new PaginatedCollection<Propriedade>());
  const [projectForm, setProjectForm] = useState<ManualProjectForm>({ propriedade_id: '', nome: '', descricao: '', orcamento_total: 0, data_inicio: '', data_termino_prevista: '', status: 'planejamento' });

  async function load(refresh = false) {
    if (!refresh) setLoading(true);
    setError('');
    try {
      const items = await projectLoader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/projetos', { params: { page, per_page: perPage }, signal });
        return response.data;
      });
      if (!items) return false;
      const categoryResponse = await api.get('/categorias-despesa', { params: { tipo: 'reforma' } });
      const categoryItems = categoryResponse.data.data ?? [];
      setProjects(items);
      setCategories(categoryItems);
      setExpenseForm((current: any) => ({ ...current, categoria_despesa_id: current.categoria_despesa_id || categoryItems[0]?.id || '' }));
      const loaded = await Promise.all(items.map((project: Projeto) => api.get(`/projetos/${project.id}/resumo`).then(r => [project.id, r.data] as const).catch(() => [project.id, null] as const)));
      setSummaries(Object.fromEntries(loaded.filter((entry): entry is readonly [number, ResumoProjeto] => Boolean(entry[1]))));
      setSummaryErrors(loaded.filter(([, summary]) => !summary).map(([id]) => id));
      const covers = await Promise.all(items.map(async (project: Projeto) => {
        const analysisId = analysisIdFromDescription(project.descricao);
        if (!analysisId) return [project.id, ''];

        try {
          const analysis = await api.get(`/investment-analyses/${analysisId}`);
          return [project.id, analysis.data?.data?.opportunity?.cover_url ?? ''];
        } catch {
          return [project.id, ''];
        }
      }));
      setProjectCovers(Object.fromEntries(covers.filter(([, cover]) => cover)));
      return true;
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível carregar seus projetos.');
      return false;
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    return () => { propertyLoader.current.cancel(); projectLoader.current.cancel(); };
  }, []);

  useEffect(() => {
    if (searchParams.get('novo') === '1') openManualProject();
  }, [searchParams]);

  useEffect(() => {
    const requestedId = Number(searchParams.get('projeto'));
    if (Number.isInteger(requestedId) && requestedId > 0 && projects.some(project => project.id === requestedId)) {
      setSelectedId(requestedId);
    }
  }, [projects, searchParams]);

  async function loadProperties() {
    if (properties.length) return properties;
    const items = await propertyLoader.current.load(async (page, perPage, signal) => {
      const response = await api.get('/propriedades', { params: { page, per_page: perPage }, signal });
      return response.data;
    });
    if (items) setProperties(items);
    return items ?? [];
  }

  async function openManualProject() {
    setEditingProject(null);
    setProjectForm({ propriedade_id: String(properties[0]?.id ?? ''), nome: '', descricao: '', orcamento_total: 0, data_inicio: '', data_termino_prevista: '', status: 'planejamento' });
    setManualOpen(true); setManualError('');
    setManualLoading(true);
    try {
      const items = await loadProperties();
      if (items) {
        setProjectForm(current => ({ ...current, propriedade_id: current.propriedade_id || String(items[0]?.id ?? '') }));
      }
    } catch (exception: any) {
      setManualError(exception.response?.data?.message ?? 'Não foi possível carregar os imóveis.');
    } finally { setManualLoading(false); }
  }

  async function openEditProject(project: Projeto) {
    setEditingProject(project);
    setProjectForm({
      propriedade_id: String(project.propriedade?.id ?? ''),
      nome: project.nome,
      descricao: project.descricao ?? '',
      orcamento_total: Number(project.orcamento_total ?? 0),
      data_inicio: project.data_inicio ?? '',
      data_termino_prevista: project.data_termino_prevista ?? '',
      status: project.status ?? 'planejamento',
    });
    setManualOpen(true); setManualError(''); setManualLoading(true);
    try { await loadProperties(); }
    catch (exception: any) { setManualError(exception.response?.data?.message ?? 'Não foi possível carregar os imóveis.'); }
    finally { setManualLoading(false); }
  }

  function closeManualProject() {
    if (saving) return;
    setManualOpen(false); setManualError('');
    setEditingProject(null);
    const next = new URLSearchParams(searchParams);
    next.delete('novo');
    setSearchParams(next, { replace: true });
  }

  async function saveManualProject() {
    setSaving(true); setManualError('');
    try {
      const payload = manualProjectPayload(projectForm);
      const response = editingProject
        ? await api.patch(`/projetos/${editingProject.id}`, payload)
        : await api.post('/projetos', payload);
      const saved = response.data.data ?? response.data;
      setProjectForm({ propriedade_id: String(properties[0]?.id ?? ''), nome: '', descricao: '', orcamento_total: 0, data_inicio: '', data_termino_prevista: '', status: 'planejamento' });
      setManualOpen(false);
      setEditingProject(null);
      const next = new URLSearchParams(searchParams); next.delete('novo'); setSearchParams(next, { replace: true });
      const refreshed = await load(true);
      setSelectedId(saved.id);
      if (!refreshed) setError('Projeto salvo, mas a lista não pôde ser recarregada. Atualize antes de repetir a operação.');
    } catch (exception: any) {
      setManualError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || `Não foi possível ${editingProject ? 'atualizar' : 'criar'} o projeto.`);
    } finally { setSaving(false); }
  }

  async function deleteProject() {
    if (!selected) return;
    setSaving(true); setError('');
    try {
      await api.delete(`/projetos/${selected.id}`);
      setDeleteOpen(false);
      setSelectedId(null);
      const refreshed = await load(true);
      if (!refreshed) setError('Projeto excluído, mas a lista não pôde ser recarregada. Use Atualizar.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível excluir o projeto.');
      setDeleteOpen(false);
    } finally { setSaving(false); }
  }

  async function loadExpenses(projectId: number) {
    const request = ++expenseLoadSequence.current;
    setExpensesLoading(true);
    try {
      const items = await expenseLoader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/despesas', { params: { projeto_id: projectId, per_page: perPage, page }, signal });
        return response.data;
      });
      if (!items || request !== expenseLoadSequence.current || selectedProject.current !== projectId) return false;
      setExpenses(items);
      return true;
    } finally {
      if (request === expenseLoadSequence.current && selectedProject.current === projectId) setExpensesLoading(false);
    }
  }

  useEffect(() => {
    setExpenses([]);
    setExpenseError('');
    setExpenseForm((current: any) => ({ ...current, descricao: '', valor: 0, etapa_id: '', observacoes: '' }));
    if (selectedId) loadExpenses(selectedId).catch(() => {
      if (selectedProject.current === selectedId) setExpenseError('Não foi possível carregar as despesas. Selecione o projeto novamente para tentar.');
    });
    return () => { expenseLoadSequence.current++; expenseLoader.current.cancel(); };
  }, [selectedId]);

  async function addExpense() {
    if (!selected) return false;
    setSaving(true); setExpenseError('');
    try {
      await api.post('/despesas', {
        ...expenseForm,
        valor: Number(expenseForm.valor || 0),
        projeto_id: selected.id,
        propriedade_id: selected.propriedade?.id,
      });
      if (selectedProject.current !== selected.id) return true;
      setExpenseForm((current: any) => ({ ...current, descricao: '', valor: 0, observacoes: '' }));
      const projectsRefreshed = await load(true);
      if (selectedProject.current !== selected.id) return true;
      const expensesRefreshed = await loadExpenses(selected.id).catch(() => false);
      if (!projectsRefreshed || !expensesRefreshed) setExpenseError('Despesa salva, mas os dados do projeto não puderam ser recarregados. Reabra o projeto antes de repetir a operação.');
      setShowExpenses(true);
      return true;
    } catch (exception: any) {
      if (selectedProject.current === selected.id) setExpenseError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível lançar o gasto.');
      return false;
    } finally {
      setSaving(false);
    }
  }

  async function updateExpense(expenseId: number, draft: any) {
    if (!selected) return false;
    setSaving(true); setExpenseError('');
    try {
      await api.put(`/despesas/${expenseId}`, {
        ...draft,
        valor: Number(draft.valor || 0),
        categoria_despesa_id: Number(draft.categoria_despesa_id),
        etapa_id: draft.etapa_id ? Number(draft.etapa_id) : null,
        projeto_id: selected.id,
        propriedade_id: selected.propriedade?.id,
      });
      const projectsRefreshed = await load(true);
      const expensesRefreshed = selectedProject.current === selected.id ? await loadExpenses(selected.id).catch(() => false) : true;
      if (!projectsRefreshed || !expensesRefreshed) setExpenseError('Despesa atualizada, mas os dados do projeto não puderam ser recarregados. Reabra o projeto.');
      return true;
    } catch (exception: any) {
      setExpenseError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível atualizar a despesa.');
      return false;
    } finally { setSaving(false); }
  }

  async function updateStageBudget(stageId: number, value: number) {
    setSaving(true); setError('');
    try {
      await api.patch(`/projetos-etapas/${stageId}`, { orcamento: value });
      const refreshed = await load(true);
      if (!refreshed) setError('Orçamento atualizado, mas o projeto não pôde ser recarregado. Use Atualizar.');
      return true;
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível atualizar o orçamento da etapa.');
      return false;
    } finally { setSaving(false); }
  }

  async function addStage() {
    if (!selected || !newStage.nome.trim()) return;
    setSaving(true); setError('');
    try {
      await api.post(`/projetos/${selected.id}/etapas`, { nome: newStage.nome.trim(), orcamento: Number(newStage.orcamento || 0), status: 'pendente' });
      setNewStage({ nome: '', orcamento: 0 });
      const refreshed = await load();
      if (!refreshed) setError('Etapa criada, mas o projeto não pôde ser recarregado. Use Atualizar antes de repetir a operação.');
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível criar a categoria.');
    } finally {
      setSaving(false);
    }
  }

  const totals = useMemo(() => summarizeProjects(projects.map(project => project.id), summaries), [projects, summaries]);

  const selected = projects.find(project => project.id === selectedId) ?? null;
  const selectedSummary = selected ? summaries[selected.id] : null;
  const selectedAnalysisId = selected ? analysisIdFromDescription(selected.descricao) : undefined;

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-gray-500 dark:text-slate-400"><Loader2 className="w-8 h-8 animate-spin mx-auto text-primary-600 dark:text-primary-400" /><p className="mt-3">Carregando projetos...</p></div></div>;

  return <div className="space-y-6">
    <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <div className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Gestão da operação</div>
        <h1 className="text-3xl font-black mt-2">Projetos</h1>
        <p className="text-gray-500 dark:text-slate-400 mt-1">Acompanhe as oportunidades que viraram execução: obra, orçamento, custos e resultado.</p>
      </div>
      <button onClick={() => selectedAnalysisId ? navigate(`/analises-financeiras/${selectedAnalysisId}`) : openManualProject()} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold flex items-center gap-2 w-fit">
        {selectedAnalysisId ? <Eye className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
        {selectedAnalysisId ? 'Ver análise financeira de origem' : 'Novo projeto manual'}
      </button>
    </div>

    {error && <div role="alert" className="bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 border border-red-200 rounded-xl p-4">{error}</div>}
    {summaryErrors.length > 0 && <div role="alert" className="bg-amber-50 dark:bg-amber-950 text-amber-900 dark:text-amber-200 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><span>Não foi possível atualizar o resumo de {summaryErrors.length} projeto{summaryErrors.length === 1 ? '' : 's'}. Totais incompletos foram ocultados.</span><button type="button" onClick={() => void load(true)} className="font-bold underline underline-offset-2 w-fit">Tentar novamente</button></div>}

    {projects.length === 0 ? <EmptyProjects onStart={() => navigate('/leads')} /> : <>
      <section className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <Kpi title="Projetos cadastrados" value={projects.length} detail="originados de análises ou criados manualmente" icon={Briefcase} color="violet" />
        <Kpi title="Orçamento de obra" value={totals.complete ? money(totals.budget) : 'Indisponível'} detail="valor disponível para reforma" icon={CircleDollarSign} color="emerald" />
        <Kpi title="Gasto em obra" value={totals.complete ? money(totals.spent) : 'Indisponível'} detail="gastos já lançados na obra" icon={Hammer} color="amber" />
        <Kpi title="Consumo médio" value={totals.complete ? percent(totals.progress) : 'Indisponível'} detail="média do orçamento consumido" icon={TrendingUp} color="blue" />
      </section>

      {!selected ? <ProjectSelectionGrid projects={projects} summaries={summaries} summaryErrors={summaryErrors} covers={projectCovers} onSelect={(projectId) => { setShowExpenses(false); setSelectedId(projectId); }} /> : <div className="space-y-4"><button type="button" onClick={() => setSelectedId(null)} className="text-primary-600 dark:text-primary-400 font-bold text-sm flex items-center gap-2">← Voltar para todos os projetos</button><ProjectDetails project={selected} summary={selectedSummary} summaryError={summaryErrors.includes(selected.id)} onRetrySummary={() => void load(true)} coverUrl={projectCovers[selected.id]} key={selected.id} expenses={expenses} categories={categories} showExpenses={showExpenses} setShowExpenses={setShowExpenses} expenseForm={expenseForm} setExpenseForm={setExpenseForm} newStage={newStage} setNewStage={setNewStage} expenseError={expenseError} expensesLoading={expensesLoading} onAddExpense={addExpense} onUpdateExpense={updateExpense} onAddStage={addStage} onUpdateStageBudget={updateStageBudget} onEdit={() => void openEditProject(selected)} onDelete={() => setDeleteOpen(true)} saving={saving} /></div>}
    </>}
    {manualOpen && <ManualProjectDialog mode={editingProject ? 'edit' : 'create'} form={projectForm} setForm={setProjectForm} properties={properties} loading={manualLoading} saving={saving} error={manualError} onClose={closeManualProject} onSubmit={saveManualProject} onCreateProperty={() => navigate('/leads?novo=1')} />}
    {deleteOpen && selected && <DeleteProjectDialog project={selected} saving={saving} onClose={() => setDeleteOpen(false)} onConfirm={deleteProject} />}
  </div>;
}

function ProjectSelectionGrid({ projects, summaries, summaryErrors, covers, onSelect }: { projects: Projeto[]; summaries: Record<number, ResumoProjeto>; summaryErrors: number[]; covers: Record<number, string>; onSelect: (projectId: number) => void }) {
  return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h2 className="text-xl font-black">Selecione um projeto para acompanhar</h2>
        <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Escolha qual obra deseja abrir para ver metas, orçamento e gastos detalhados.</p>
      </div>
      <span className="text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-3 py-2 rounded-full font-semibold">{projects.length} projeto{projects.length === 1 ? '' : 's'}</span>
    </div>
    <div className="grid md:grid-cols-2 2xl:grid-cols-3 gap-4 mt-5">
      {projects.map(project => <ProjectCard key={project.id} project={project} summary={summaries[project.id]} summaryError={summaryErrors.includes(project.id)} coverUrl={covers[project.id]} active={false} onClick={() => onSelect(project.id)} />)}
    </div>
  </section>;
}

function EmptyProjects({ onStart }: { onStart: () => void }) {
  return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-10 text-center">
    <div className="w-16 h-16 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl grid place-items-center mx-auto"><Briefcase className="w-8 h-8" /></div>
    <h2 className="text-2xl font-black mt-5">Nenhum projeto ainda</h2>
    <p className="text-gray-500 dark:text-slate-400 mt-2 max-w-xl mx-auto">Quando uma análise financeira virar projeto, ela aparecerá aqui com o orçamento da obra, metas por categoria e gastos realizados.</p>
    <button onClick={onStart} className="mt-6 bg-primary-600 text-white px-5 py-3 rounded-xl font-bold">Ir para Leads & Oportunidades</button>
  </section>;
}

function ManualProjectDialog({ mode, form, setForm, properties, loading, saving, error, onClose, onSubmit, onCreateProperty }: { mode: 'create' | 'edit'; form: ManualProjectForm; setForm: (form: ManualProjectForm) => void; properties: Propriedade[]; loading: boolean; saving: boolean; error: string; onClose: () => void; onSubmit: () => Promise<void>; onCreateProperty: () => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    element?.showModal();
    return () => { element?.close(); trigger?.focus(); };
  }, []);
  const disabled = loading || saving || !form.propriedade_id || !form.nome.trim();
  const submit = (event: FormEvent) => { event.preventDefault(); if (!disabled) void onSubmit(); };

  return <dialog ref={dialog} aria-labelledby="manual-project-title" onCancel={event => { if (saving) event.preventDefault(); else onClose(); }} className="p-0 w-[calc(100%-2rem)] max-w-2xl max-h-[90vh] rounded-3xl bg-white dark:bg-slate-900 text-gray-900 dark:text-slate-100 backdrop:bg-slate-950/50 backdrop:backdrop-blur-xs">
    <form onSubmit={submit} className="overflow-y-auto max-h-[90vh]">
      <div className="p-5 border-b flex items-center justify-between gap-4"><div><h2 id="manual-project-title" className="text-xl font-black">{mode === 'edit' ? 'Editar projeto' : 'Novo projeto manual'}</h2><p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{mode === 'edit' ? 'Atualize os dados principais do projeto.' : 'Vincule o projeto a um imóvel já cadastrado.'}</p></div><button type="button" aria-label="Fechar cadastro de projeto" disabled={saving} onClick={onClose} className="w-9 h-9 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 grid place-items-center"><X className="w-5 h-5" /></button></div>
      {error && <p role="alert" className="mx-5 mt-4 rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">{error}</p>}
      <div className="p-5 grid md:grid-cols-2 gap-4">
        {loading ? <p role="status" className="md:col-span-2 flex items-center gap-2 text-sm text-gray-500"><Loader2 className="w-4 h-4 animate-spin" />Carregando imóveis...</p> : properties.length ? <label className="text-sm block md:col-span-2"><span>Imóvel</span><select required autoFocus className="input mt-1" value={form.propriedade_id} onChange={event => setForm({ ...form, propriedade_id: event.target.value })}><option value="">Selecione</option>{properties.map(property => <option key={property.id} value={property.id}>{property.apelido}{property.endereco?.cidade ? ` · ${property.endereco.cidade}${property.endereco.estado ? `/${property.endereco.estado}` : ''}` : ''}</option>)}</select></label> : <div className="md:col-span-2 rounded-2xl bg-amber-50 dark:bg-amber-950 p-4"><p className="text-sm text-amber-900 dark:text-amber-200">Nenhum imóvel está disponível. Cadastre uma oportunidade e conclua a análise financeira para convertê-la em projeto.</p><button type="button" onClick={onCreateProperty} className="mt-3 text-sm font-bold text-primary-600 dark:text-primary-400">Cadastrar oportunidade →</button></div>}
        <div className="md:col-span-2"><TextField label="Nome do projeto" value={form.nome} onChange={nome => setForm({ ...form, nome })} placeholder="Ex: Reforma Apartamento Centro" /></div>
        <MoneyInput label="Orçamento total" value={form.orcamento_total} onChange={orcamento_total => setForm({ ...form, orcamento_total })} />
        <label className="text-sm block"><span>Status inicial</span><select className="input mt-1" value={form.status} onChange={event => setForm({ ...form, status: event.target.value })}><option value="planejamento">Planejamento</option><option value="em_andamento">Em andamento</option><option value="pausado">Pausado</option></select></label>
        <TextField label="Data de início" type="date" value={form.data_inicio} onChange={data_inicio => setForm({ ...form, data_inicio })} />
        <label className="text-sm block"><span>Término previsto</span><input className="input mt-1" type="date" min={form.data_inicio || undefined} value={form.data_termino_prevista} onChange={event => setForm({ ...form, data_termino_prevista: event.target.value })} /></label>
        <label className="text-sm block md:col-span-2"><span>Descrição</span><textarea className="input mt-1 min-h-24 resize-y" value={form.descricao} maxLength={5000} onChange={event => setForm({ ...form, descricao: event.target.value })} placeholder="Objetivo, escopo ou observações do projeto" /></label>
      </div>
      <div className="p-5 border-t bg-slate-50 dark:bg-slate-950 flex justify-end gap-3"><button type="button" disabled={saving} onClick={onClose} className="border bg-white dark:bg-slate-900 px-5 py-3 rounded-xl font-bold disabled:opacity-50">Cancelar</button><button type="submit" disabled={disabled} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold disabled:opacity-50">{saving ? 'Salvando...' : mode === 'edit' ? 'Salvar alterações' : 'Criar projeto'}</button></div>
    </form>
  </dialog>;
}

function DeleteProjectDialog({ project, saving, onClose, onConfirm }: { project: Projeto; saving: boolean; onClose: () => void; onConfirm: () => Promise<void> }) {
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    element?.showModal();
    return () => { element?.close(); trigger?.focus(); };
  }, []);

  return <dialog ref={dialog} aria-labelledby="delete-project-title" aria-describedby="delete-project-description" onCancel={event => { if (saving) event.preventDefault(); else onClose(); }} className="p-0 w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white dark:bg-slate-900 text-gray-900 dark:text-slate-100 backdrop:bg-slate-950/50 backdrop:backdrop-blur-xs">
    <div className="p-6">
      <div className="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 grid place-items-center"><Trash2 className="w-6 h-6" /></div>
      <h2 id="delete-project-title" className="text-xl font-black mt-4">Excluir projeto?</h2>
      <p id="delete-project-description" className="text-sm text-gray-600 dark:text-slate-300 mt-2">O projeto <strong>{project.nome}</strong> será removido. Projetos com despesas ou documentos ativos não podem ser excluídos.</p>
      <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 mt-6">
        <button type="button" autoFocus disabled={saving} onClick={onClose} className="border px-5 py-3 rounded-xl font-bold disabled:opacity-50">Cancelar</button>
        <button type="button" disabled={saving} onClick={() => void onConfirm()} className="bg-red-600 text-white px-5 py-3 rounded-xl font-bold disabled:opacity-50">{saving ? 'Excluindo...' : 'Excluir projeto'}</button>
      </div>
    </div>
  </dialog>;
}

function ProjectCard({ project, summary, summaryError, coverUrl, active, onClick }: { project: Projeto; summary?: ResumoProjeto; summaryError: boolean; coverUrl?: string; active: boolean; onClick: () => void }) {
  const status = statusLabel(project.status);
  const origin = analysisIdFromDescription(project.descricao);

  return <button onClick={onClick} className={`w-full text-left bg-white dark:bg-slate-900 border rounded-2xl p-4 transition shadow-xs ${active ? 'border-primary-400 ring-2 ring-primary-100' : 'hover:border-gray-300'}`}>
    <div className="flex justify-between gap-3">
      <div className="min-w-0 flex items-center gap-3">
        <ProjectCover src={coverUrl} alt={project.nome} size="card" />
        <div className="min-w-0">
          <h3 className="font-black truncate">{project.nome}</h3>
          <p className="text-sm text-gray-500 dark:text-slate-400 truncate mt-1">{project.propriedade?.apelido ?? 'Sem propriedade vinculada'}</p>
        </div>
      </div>
      <span className={`text-[11px] font-bold px-2 py-1 rounded-full h-fit ${status.color}`}>{status.label}</span>
    </div>
    <div className="grid grid-cols-2 gap-3 mt-4 text-sm">
      <SmallMetric label="Orçamento" value={money(summary?.orcamento_total ?? project.orcamento_total)} />
      <SmallMetric label="Realizado" value={summaryError ? 'Indisponível' : money(summary?.custo_real)} />
      <SmallMetric label="Consumido" value={summaryError ? 'Indisponível' : percent(summary?.consumido_pct)} />
      <SmallMetric label="ROI" value={summaryError ? 'Indisponível' : summary?.roi?.tem_venda ? percent(summary.roi.roi_pct) : 'Em aberto'} />
    </div>
    <div className="flex items-center justify-between mt-4 text-xs text-gray-500 dark:text-slate-400">
      <span>{origin ? `Origem: análise #${origin}` : 'Origem: manual'}</span>
      <span className="text-primary-600 dark:text-primary-400 font-bold flex items-center gap-1">Acompanhar <ArrowRight className="w-4 h-4" /></span>
    </div>
  </button>;
}

function ProjectDetails({ project, summary, summaryError, onRetrySummary, coverUrl, expenses, categories, showExpenses, setShowExpenses, expenseForm, setExpenseForm, newStage, setNewStage, onAddExpense, onUpdateExpense, onAddStage, onUpdateStageBudget, onEdit, onDelete, saving, expenseError, expensesLoading }: { project: Projeto; summary?: ResumoProjeto | null; summaryError: boolean; onRetrySummary: () => void; coverUrl?: string; expenses: Despesa[]; categories: Categoria[]; showExpenses: boolean; setShowExpenses: (value: boolean) => void; expenseForm: any; setExpenseForm: (value: any) => void; newStage: { nome: string; orcamento: number }; setNewStage: (value: { nome: string; orcamento: number }) => void; expenseError: string; expensesLoading: boolean; onAddExpense: () => Promise<boolean>; onUpdateExpense: (expenseId: number, draft: any) => Promise<boolean>; onAddStage: () => void; onUpdateStageBudget: (stageId: number, value: number) => Promise<boolean>; onEdit: () => void; onDelete: () => void; saving: boolean }) {
  const etapas = summary?.etapas ?? [];
  const origin = analysisIdFromDescription(project.descricao);
  const budget = Number(summary?.orcamento_total ?? project.orcamento_total ?? 0);
  const spent = summaryError ? null : Number(summary?.custo_real ?? 0);
  const available = spent === null ? null : Math.max(0, budget - spent);
  const originalBudget = originalWorkBudget(project, budget);
  const budgetDelta = budget - originalBudget;
  const budgetDeltaPct = originalBudget > 0 ? budgetDelta / originalBudget * 100 : 0;

  return <div className="bg-white dark:bg-slate-900 border rounded-3xl overflow-hidden shadow-xs">
    <div className="bg-slate-950 text-white p-6">
      <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div className="flex items-center gap-4 min-w-0">
          <ProjectCover src={coverUrl} alt={project.nome} size="hero" />
          <div className="min-w-0">
            <div className="text-xs uppercase tracking-widest text-violet-300 font-bold">Projeto em execução</div>
            <h2 className="text-2xl font-black mt-2 truncate">{project.nome}</h2>
            <p className="text-slate-300 mt-1 truncate">{project.propriedade?.apelido}{origin && ` · análise financeira #${origin}`}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className={`text-xs font-bold px-3 py-2 rounded-full ${statusLabel(project.status).color}`}>{statusLabel(project.status).label}</span>
          <button type="button" onClick={onEdit} className="inline-flex items-center gap-2 border border-slate-700 hover:bg-slate-800 px-3 py-2 rounded-xl text-sm font-bold"><Pencil className="w-4 h-4" />Editar</button>
          <button type="button" onClick={onDelete} className="inline-flex items-center gap-2 border border-red-800 text-red-300 hover:bg-red-950 px-3 py-2 rounded-xl text-sm font-bold"><Trash2 className="w-4 h-4" />Excluir</button>
        </div>
      </div>
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mt-6">
        <HeroMetric label="Orçamento da obra" value={money(budget)} />
        <HeroMetric label="Gasto lançado" value={spent === null ? 'Indisponível' : money(spent)} />
        <HeroMetric label="Disponível da obra" value={available === null ? 'Indisponível' : money(available)} />
        <HeroMetric label="% consumido" value={summaryError ? 'Indisponível' : percent(summary?.consumido_pct)} />
      </div>
      <p className="text-xs text-slate-400 mt-3">
        Valor definido na compra: <strong className="text-slate-200">{money(originalBudget)}</strong>
        {Math.abs(budgetDelta) > 0.009 && <> · orçamento atual: <strong className="text-slate-200">{money(budget)}</strong> ({budgetDelta > 0 ? '+' : ''}{percent(budgetDeltaPct)})</>}
      </p>
    </div>

    <div className="p-6 space-y-6">
      {summaryError && <div role="alert" className="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-950 p-4 text-sm text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><span>O resumo financeiro e as etapas deste projeto não puderam ser carregados.</span><button type="button" onClick={onRetrySummary} className="font-bold underline underline-offset-2 w-fit">Tentar novamente</button></div>}
      {project.descricao && <div className="bg-violet-50 dark:bg-violet-950 border border-violet-100 text-violet-900 dark:text-violet-200 rounded-2xl p-4 text-sm">{project.descricao}</div>}

      <section>
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <h3 className="font-black text-lg">Metas da obra por categoria</h3>
            <p className="text-sm text-gray-500 dark:text-slate-400">Defina quanto pretende gastar em Hidráulica, Elétrica, Demolição, Acabamento etc. Os gastos lançados consomem essas metas.</p>
          </div>
          <button type="button" onClick={() => setShowExpenses(!showExpenses)} className="border border-primary-200 text-primary-700 dark:text-primary-300 px-4 py-2.5 rounded-xl font-bold flex items-center gap-2 w-fit">{showExpenses ? <X className="w-4 h-4" /> : <Eye className="w-4 h-4" />}{showExpenses ? 'Ocultar gastos' : 'Lançar/ver gastos'}</button>
        </div>
        {!summaryError && <><div className="space-y-3 mt-4">
          {etapas.length ? etapas.map(etapa => <StageRow key={etapa.id} etapa={etapa} total={budget} onUpdateBudget={onUpdateStageBudget} />) : <div className="text-sm text-gray-500 dark:text-slate-400 border rounded-xl p-4">Nenhuma etapa cadastrada ainda.</div>}
        </div>
        <div className="mt-4 border border-dashed rounded-2xl p-4 bg-slate-50 dark:bg-slate-950">
          <h4 className="font-bold">Adicionar nova categoria</h4>
          <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">Se o orçamento original já foi todo distribuído, a nova meta aumenta o orçamento atual da obra.</p>
          <div className="grid md:grid-cols-[1fr_220px_auto] gap-3 mt-3 items-end">
            <TextField label="Nome da categoria" value={newStage.nome} onChange={(value: string) => setNewStage({ ...newStage, nome: value })} placeholder="Ex: Marcenaria" />
            <MoneyInput label="Meta/orçamento" value={newStage.orcamento} onChange={(value: number) => setNewStage({ ...newStage, orcamento: value })} />
            <button type="button" disabled={saving || !newStage.nome.trim()} onClick={onAddStage} className="bg-slate-950 text-white px-4 py-3 rounded-xl font-bold disabled:opacity-50">Adicionar</button>
          </div>
        </div></>}
      </section>

      {showExpenses && <ExpenseWorkspace project={project} budget={budget} etapas={etapas} expenses={expenses} categories={categories} expenseForm={expenseForm} setExpenseForm={setExpenseForm} expenseError={expenseError} expensesLoading={expensesLoading} onAddExpense={onAddExpense} onUpdateExpense={onUpdateExpense} saving={saving} />}

      <section className="grid md:grid-cols-3 gap-4">
        <InfoCard icon={Home} title="Imóvel" value={project.propriedade?.apelido ?? 'Não informado'} detail="Propriedade vinculada ao projeto" />
        <InfoCard icon={Calendar} title="Cronograma" value={project.data_termino_prevista ? 'Com prazo' : 'A definir'} detail={project.data_termino_prevista ?? 'Defina datas nas próximas etapas'} />
        <InfoCard icon={Target} title="Resultado" value={summaryError ? 'Indisponível' : summary?.roi?.tem_venda ? money(summary.roi.lucro) : 'Ainda em execução'} detail={summaryError ? 'O resumo precisa ser recarregado' : summary?.roi?.tem_venda ? `ROI ${percent(summary.roi.roi_pct)}` : 'ROI final aparece após a venda'} />
      </section>
    </div>
  </div>;
}

function StageRow({ etapa, total, onUpdateBudget }: { etapa: ResumoProjeto['etapas'][number]; total: number; onUpdateBudget: (stageId: number, value: number) => Promise<boolean> }) {
  const [draftBudget, setDraftBudget] = useState(etapa.orcamento);
  const [updating, setUpdating] = useState(false);
  const width = total > 0 ? Math.min(100, Math.max(4, Number(etapa.orcamento || 0) / total * 100)) : 4;
  const consumed = Number(etapa.orcamento) > 0 ? Math.min(100, Number(etapa.custo_real || 0) / Number(etapa.orcamento) * 100) : 0;
  const available = Number(etapa.orcamento || 0) - Number(etapa.custo_real || 0);

  return <div className="border rounded-2xl p-4">
    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <div>
        <h4 className="font-bold">{etapa.nome}</h4>
        <p className="text-xs text-gray-500 dark:text-slate-400">Status: {statusLabel(etapa.status).label}</p>
      </div>
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm md:text-right">
        <div className="text-left md:text-right"><span className="text-[11px] text-gray-500 dark:text-slate-400 block">Meta/orçamento</span><input aria-label={`Meta/orçamento de ${etapa.nome}`} disabled={updating} className="input mt-1 h-9 text-sm md:text-right disabled:opacity-60" value={money(draftBudget)} onBlur={async () => { if (updating || Number(draftBudget) === Number(etapa.orcamento)) return; setUpdating(true); const saved = await onUpdateBudget(etapa.id, Number(draftBudget || 0)); if (!saved) setDraftBudget(etapa.orcamento); setUpdating(false); }} onChange={event => { const digits = event.target.value.replace(/\D/g, ''); setDraftBudget(digits ? Number(digits) / 100 : 0); }} /></div>
        <SmallMetric label="Real" value={money(etapa.custo_real)} />
        <SmallMetric label="Disponível" value={money(available)} />
        <SmallMetric label="Consumido" value={percent(consumed)} />
      </div>
    </div>
    <div className="mt-4">
      <div className="h-3 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden"><div className="h-full bg-primary-500 rounded-full" style={{ width: `${width}%` }} /></div>
      <div className="h-1 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden mt-2"><div className="h-full bg-emerald-500 rounded-full" style={{ width: `${consumed}%` }} /></div>
    </div>
  </div>;
}

function ExpenseWorkspace({ project, budget, etapas, expenses, categories, expenseForm, setExpenseForm, onAddExpense, onUpdateExpense, saving, expenseError, expensesLoading }: { project: Projeto; budget: number; etapas: ResumoProjeto['etapas']; expenses: Despesa[]; categories: Categoria[]; expenseForm: any; setExpenseForm: (value: any) => void; expenseError: string; expensesLoading: boolean; onAddExpense: () => Promise<boolean>; onUpdateExpense: (expenseId: number, draft: any) => Promise<boolean>; saving: boolean }) {
  const [activeTab, setActiveTab] = useState<'todos' | 'pendente' | 'atrasado' | 'pago'>('todos');
  const [query, setQuery] = useState('');
  const [stageFilter, setStageFilter] = useState('');
  const [selectedExpenseId, setSelectedExpenseId] = useState<number | null>(expenses[0]?.id ?? null);
  const [isExpenseModalOpen, setIsExpenseModalOpen] = useState(false);
  const [editingExpenseId, setEditingExpenseId] = useState<number | null>(null);
  const [filtersOpen, setFiltersOpen] = useState(true);

  useEffect(() => {
    if (expenses.length && !expenses.some(expense => expense.id === selectedExpenseId)) {
      setSelectedExpenseId(expenses[0].id);
    }
  }, [expenses, selectedExpenseId]);

  const stats = useMemo(() => summarizeExpenses(expenses), [expenses]);

  const filteredExpenses = useMemo(() => expenses.filter(expense => {
    const status = normalizePaymentStatus(expense.status_pagamento);
    const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? expense.categoria?.nome ?? '';
    const matchesTab = activeTab === 'todos' || status === activeTab;
    const matchesQuery = !query || `${expense.descricao} ${stageName}`.toLowerCase().includes(query.toLowerCase());
    const matchesStage = !stageFilter || String(expense.etapa_id ?? '') === stageFilter;

    return matchesTab && matchesQuery && matchesStage;
  }), [activeTab, expenses, etapas, query, stageFilter]);

  const selectedExpense = filteredExpenses.find(expense => expense.id === selectedExpenseId) ?? filteredExpenses[0] ?? null;

  function openNewExpense() {
    setEditingExpenseId(null);
    setExpenseForm({ descricao: '', valor: 0, etapa_id: '', categoria_despesa_id: categories[0]?.id ?? '', data_despesa: new Date().toISOString().slice(0, 10), status_pagamento: 'pago', forma_pagamento: '', observacoes: '' });
    setIsExpenseModalOpen(true);
  }

  function openEditExpense(expense: Despesa) {
    setEditingExpenseId(expense.id);
    setExpenseForm(expenseDraft(expense));
    setIsExpenseModalOpen(true);
  }

  return <section className="space-y-4">
    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
      <div>
        <h3 className="text-2xl font-black">Despesas da obra</h3>
        <p className="text-sm text-gray-500 dark:text-slate-400">Controle todos os gastos deste projeto em um só lugar.</p>
      </div>
      <div className="flex gap-2">
        <button type="button" aria-expanded={filtersOpen} aria-controls="expense-filters" onClick={() => setFiltersOpen(value => !value)} className="border bg-white dark:bg-slate-900 px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 text-slate-700 dark:text-slate-200"><SlidersHorizontal className="w-4 h-4" />{filtersOpen ? 'Ocultar filtros' : 'Filtros'}</button>
        <button type="button" className="bg-primary-600 text-white px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2" onClick={openNewExpense}><Plus className="w-4 h-4" />Nova despesa</button>
      </div>
    </div>

    <div className="grid sm:grid-cols-2 xl:grid-cols-5 gap-3">
      <ExpenseKpi title="Total de despesas" value={expensesLoading || expenseError ? '—' : money(stats.total)} detail={`${expenses.length} lançamento${expenses.length === 1 ? '' : 's'}`} tone="violet" />
      <ExpenseKpi title="Pago" value={expensesLoading || expenseError ? '—' : money(stats.paid)} detail={`${budget > 0 ? percent(stats.paid / budget * 100) : '0,0%'} do orçamento`} tone="emerald" />
      <ExpenseKpi title="A pagar" value={expensesLoading || expenseError ? '—' : money(stats.pending)} detail="pendente de pagamento" tone="amber" />
      <ExpenseKpi title="Vencido" value={expensesLoading || expenseError ? '—' : money(stats.overdue)} detail="precisa de atenção" tone="red" />
      <ExpenseKpi title="Previsto (Orçado)" value={money(budget)} detail="orçamento total da obra" tone="blue" />
    </div>

    <div className="grid 2xl:grid-cols-[1fr_320px] gap-4">
      <div className="space-y-4 min-w-0">
        {filtersOpen && <div id="expense-filters" className="bg-white dark:bg-slate-900 border rounded-2xl p-3 grid lg:grid-cols-[240px_200px_1fr] gap-3">
          <select className="input" value={stageFilter} onChange={event => setStageFilter(event.target.value)}>
            <option value="">Todas as categorias</option>
            {etapas.map(etapa => <option key={etapa.id} value={etapa.id}>{etapa.nome}</option>)}
          </select>
          <select className="input" value={activeTab} onChange={event => setActiveTab(event.target.value as any)}>
            <option value="todos">Todos os status</option>
            <option value="pendente">A pagar</option>
            <option value="atrasado">Vencidas</option>
            <option value="pago">Pagas</option>
          </select>
          <div className="relative">
            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input className="input pl-9" placeholder="Buscar despesa..." value={query} onChange={event => setQuery(event.target.value)} />
          </div>
        </div>}

        <div className="bg-white dark:bg-slate-900 border rounded-2xl overflow-hidden">
          <div className="px-4 pt-4 flex flex-wrap gap-4 text-sm border-b">
            <ExpenseTab active={activeTab === 'todos'} onClick={() => setActiveTab('todos')}>Todas as Despesas ({expenses.length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'pendente'} onClick={() => setActiveTab('pendente')}>A Pagar ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pendente').length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'atrasado'} onClick={() => setActiveTab('atrasado')}>Vencidas ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'atrasado').length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'pago'} onClick={() => setActiveTab('pago')}>Pagas ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pago').length})</ExpenseTab>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 dark:bg-slate-950 text-xs text-gray-500 dark:text-slate-400 uppercase">
                <tr>
                  <th className="text-left p-3">Data</th>
                  <th className="text-left p-3">Descrição</th>
                  <th className="text-left p-3">Categoria</th>
                  <th className="text-left p-3">Tipo financeiro</th>
                  <th className="text-right p-3">Valor</th>
                  <th className="text-left p-3">Status</th>
                  <th className="text-center p-3">Ações</th>
                </tr>
              </thead>
              <tbody>
                {expensesLoading || expenseError ? <tr><td colSpan={7} role="status" className="p-8 text-center">{expensesLoading ? 'Carregando despesas...' : expenseError}</td></tr> : filteredExpenses.length ? filteredExpenses.map(expense => {
                  const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? '-';
                  const selected = selectedExpense?.id === expense.id;

                  return <tr key={expense.id} onClick={() => setSelectedExpenseId(expense.id)} className={`border-t cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 ${selected ? 'bg-primary-50/50' : ''}`}>
                    <td className="p-3 whitespace-nowrap">{formatDate(expense.data_despesa)}</td>
                    <td className="p-3 font-bold min-w-[220px]">{expense.descricao}</td>
                    <td className="p-3"><span className="bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-lg text-xs font-bold">{stageName}</span></td>
                    <td className="p-3 text-gray-600 dark:text-slate-300">{expense.categoria?.nome ?? '-'}</td>
                    <td className="p-3 text-right font-black whitespace-nowrap">{money(expense.valor)}</td>
                    <td className="p-3"><PaymentBadge status={expense.status_pagamento} /></td>
                    <td className="p-3 text-center"><button type="button" onClick={event => { event.stopPropagation(); setSelectedExpenseId(expense.id); }} aria-label={`Ver detalhes de ${expense.descricao}`} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"><Eye className="w-4 h-4 text-gray-500 mx-auto" /></button></td>
                  </tr>;
                }) : <tr><td colSpan={7} className="p-8 text-center text-gray-500 dark:text-slate-400">Nenhuma despesa encontrada com esses filtros.</td></tr>}
              </tbody>
            </table>
          </div>
          <div className="p-4 text-xs text-gray-500 dark:text-slate-400 border-t">Mostrando {filteredExpenses.length} de {expenses.length} despesas</div>
        </div>
      </div>

      <ExpenseDetailPanel project={project} expense={selectedExpense} etapas={etapas} saving={saving} onEdit={openEditExpense} onMarkPaid={async expense => { const draft = { ...expenseDraft(expense), status_pagamento: 'pago', data_pagamento: new Date().toISOString().slice(0, 10) }; await onUpdateExpense(expense.id, draft); }} />
    </div>

    {isExpenseModalOpen && <NewExpenseModal mode={editingExpenseId ? 'edit' : 'create'} error={expenseError} etapas={etapas} categories={categories} expenseForm={expenseForm} setExpenseForm={setExpenseForm} saving={saving} onClose={() => setIsExpenseModalOpen(false)} onSubmit={async () => { const saved = editingExpenseId ? await onUpdateExpense(editingExpenseId, expenseForm) : await onAddExpense(); if (saved) setIsExpenseModalOpen(false); }} />}
  </section>;
}

function NewExpenseModal({ mode, error, etapas, categories, expenseForm, setExpenseForm, saving, onClose, onSubmit }: { mode: 'create' | 'edit'; error: string; etapas: ResumoProjeto['etapas']; categories: Categoria[]; expenseForm: any; setExpenseForm: (value: any) => void; saving: boolean; onClose: () => void; onSubmit: () => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    element?.showModal();
    return () => { element?.close(); trigger?.focus(); };
  }, []);
  const disabled = saving || !expenseForm.descricao || !expenseForm.valor || !expenseForm.etapa_id || !expenseForm.categoria_despesa_id;

  return <dialog ref={dialog} aria-labelledby="new-expense-title" onCancel={event => { if (saving) event.preventDefault(); else onClose(); }} className="p-0 w-[calc(100%-2rem)] max-w-2xl max-h-[90vh] rounded-3xl bg-white dark:bg-slate-900 text-gray-900 dark:text-slate-100 backdrop:bg-slate-950/50 backdrop:backdrop-blur-xs">
    <div className="shadow-2xl w-full">
      <div className="p-5 border-b flex items-center justify-between">
        <div>
          <h3 id="new-expense-title" className="font-black text-xl">{mode === 'edit' ? 'Editar despesa' : 'Nova despesa da obra'}</h3>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{mode === 'edit' ? 'Atualize os dados persistidos deste lançamento.' : 'Cadastre um gasto e vincule à categoria/meta correta.'}</p>
        </div>
        <button type="button" disabled={saving} aria-label="Fechar nova despesa" onClick={onClose} className="w-9 h-9 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 grid place-items-center"><X className="w-5 h-5 text-gray-500 dark:text-slate-400" /></button>
      </div>

      {error && <p role="alert" className="mx-5 mt-4 text-red-700 dark:text-red-300">{error}</p>}<div className="p-5 grid md:grid-cols-2 gap-4">
        <div className="md:col-span-2">
          <TextField label="Descrição" value={expenseForm.descricao} onChange={(value: string) => setExpenseForm({ ...expenseForm, descricao: value })} placeholder="Ex: Compra de cano PVC" />
        </div>
        <MoneyInput label="Valor" value={expenseForm.valor} onChange={(value: number) => setExpenseForm({ ...expenseForm, valor: value })} />
        <TextField label="Data" type="date" value={expenseForm.data_despesa} onChange={(value: string) => setExpenseForm({ ...expenseForm, data_despesa: value })} />
        <label className="text-sm block"><span>Categoria/meta da obra</span><select className="input mt-1" value={expenseForm.etapa_id} onChange={event => setExpenseForm({ ...expenseForm, etapa_id: event.target.value })}><option value="">Selecione</option>{etapas.map(etapa => <option key={etapa.id} value={etapa.id}>{etapa.nome}</option>)}</select></label>
        <label className="text-sm block"><span>Tipo financeiro</span><select className="input mt-1" value={expenseForm.categoria_despesa_id} onChange={event => setExpenseForm({ ...expenseForm, categoria_despesa_id: event.target.value })}>{categories.map(category => <option key={category.id} value={category.id}>{category.nome}</option>)}</select></label>
        <label className="text-sm block"><span>Status</span><select className="input mt-1" value={expenseForm.status_pagamento} onChange={event => setExpenseForm({ ...expenseForm, status_pagamento: event.target.value })}><option value="pago">Pago</option><option value="pendente">A pagar</option><option value="atrasado">Vencido</option><option value="cancelado">Cancelado</option></select></label>
        <label className="text-sm block"><span>Forma de pagamento</span><select className="input mt-1" value={expenseForm.forma_pagamento ?? ''} onChange={event => setExpenseForm({ ...expenseForm, forma_pagamento: event.target.value })}><option value="">Não informada</option><option value="pix">PIX</option><option value="boleto">Boleto</option><option value="cartao">Cartão</option><option value="transferencia">Transferência</option><option value="dinheiro">Dinheiro</option></select></label>
        <label className="text-sm block md:col-span-2"><span>Observações</span><textarea className="input mt-1 min-h-24 resize-none" value={expenseForm.observacoes ?? ''} onChange={event => setExpenseForm({ ...expenseForm, observacoes: event.target.value })} placeholder="Ex: compra feita para manutenção hidráulica" /></label>
      </div>

      <div className="p-5 border-t bg-slate-50 dark:bg-slate-950 flex justify-end gap-3">
        <button type="button" disabled={saving} onClick={onClose} className="border bg-white dark:bg-slate-900 px-5 py-3 rounded-xl font-bold">Cancelar</button>
        <button type="button" disabled={disabled} onClick={onSubmit} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold disabled:opacity-50">{saving ? 'Salvando...' : mode === 'edit' ? 'Salvar alterações' : 'Adicionar despesa'}</button>
      </div>
    </div>
  </dialog>;
}

function ExpenseKpi({ title, value, detail, tone }: { title: string; value: string; detail: string; tone: 'violet' | 'emerald' | 'amber' | 'red' | 'blue' }) {
  const tones = {
    violet: 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950',
    emerald: 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950',
    amber: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950',
    red: 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950',
    blue: 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950',
  };

  return <div className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
    <div className="flex items-start justify-between gap-3">
      <span className="text-xs text-gray-500 dark:text-slate-400">{title}</span>
      <span className={`w-8 h-8 rounded-lg grid place-items-center ${tones[tone]}`}><Receipt className="w-4 h-4" /></span>
    </div>
    <strong className={`block text-xl mt-3 ${tone === 'emerald' ? 'text-emerald-600 dark:text-emerald-400' : tone === 'amber' ? 'text-amber-600 dark:text-amber-400' : tone === 'red' ? 'text-red-600 dark:text-red-400' : ''}`}>{value}</strong>
    <p className="text-xs text-gray-500 dark:text-slate-400 mt-1">{detail}</p>
  </div>;
}

function ExpenseTab({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
  return <button type="button" onClick={onClick} className={`pb-3 border-b-2 font-bold ${active ? 'border-primary-600 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-slate-400 hover:text-gray-800'}`}>{children}</button>;
}

function ExpenseDetailPanel({ project, expense, etapas, saving, onEdit, onMarkPaid }: { project: Projeto; expense: Despesa | null; etapas: ResumoProjeto['etapas']; saving: boolean; onEdit: (expense: Despesa) => void; onMarkPaid: (expense: Despesa) => Promise<void> }) {
  if (!expense) {
    return <aside className="bg-white dark:bg-slate-900 border rounded-2xl p-5 min-h-[320px]">
      <h3 className="font-black">Detalhes da despesa</h3>
      <p className="text-sm text-gray-500 dark:text-slate-400 mt-2">Selecione uma despesa na tabela para ver os detalhes.</p>
    </aside>;
  }

  const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? expense.categoria?.nome ?? '-';
  const paymentStatus = normalizePaymentStatus(expense.status_pagamento);

  return <aside className="bg-white dark:bg-slate-900 border rounded-2xl overflow-hidden h-fit">
    <div className="p-5 border-b">
      <h3 className="font-black">Detalhes da despesa</h3>
    </div>
    <div className="p-5 space-y-5">
      <PaymentBadge status={expense.status_pagamento} />
      <div className="flex gap-3 items-center">
        <div className="w-12 h-12 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl grid place-items-center"><Receipt className="w-6 h-6" /></div>
        <div>
          <h4 className="font-black">{expense.descricao}</h4>
          <strong className="text-xl">{money(expense.valor)}</strong>
        </div>
      </div>
      <div>
        <div className="text-xs uppercase font-bold text-gray-500 dark:text-slate-400 mb-3">Informações gerais</div>
        <DateBox label="Data da despesa" value={formatDate(expense.data_despesa)} />
        <DetailRow label="Categoria" value={stageName} badge />
        <DetailRow label="Forma de pagamento" value={paymentMethodLabel(expense.forma_pagamento)} />
        {expense.data_pagamento && <DateBox label="Data do pagamento" value={formatDate(expense.data_pagamento)} />}
        <DetailRow label="Projeto / Obra" value={project.nome} hint={project.propriedade?.apelido ?? 'Obra vinculada'} />
      </div>
      <div className="bg-slate-50 dark:bg-slate-950 rounded-2xl p-4">
        <div className="text-xs text-gray-500 dark:text-slate-400">Observação</div>
        <p className="text-sm mt-1">{expense.observacoes?.trim() || `Despesa vinculada ao orçamento da categoria ${stageName}.`}</p>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <button type="button" disabled={saving} onClick={() => onEdit(expense)} className="border rounded-xl py-3 font-bold text-sm disabled:opacity-50">Editar</button>
        <button type="button" disabled={saving || paymentStatus === 'pago' || paymentStatus === 'cancelado'} onClick={() => void onMarkPaid(expense)} className="bg-primary-600 text-white rounded-xl py-3 font-bold text-sm disabled:opacity-50">{paymentStatus === 'pago' ? 'Pagamento registrado' : paymentStatus === 'cancelado' ? 'Despesa cancelada' : 'Marcar como pago'}</button>
      </div>
    </div>
  </aside>;
}

function expenseDraft(expense: Despesa) {
  return {
    descricao: expense.descricao,
    valor: Number(expense.valor || 0),
    etapa_id: expense.etapa_id ? String(expense.etapa_id) : '',
    categoria_despesa_id: expense.categoria_despesa_id ? String(expense.categoria_despesa_id) : '',
    data_despesa: expense.data_despesa,
    data_pagamento: expense.data_pagamento ?? undefined,
    status_pagamento: expense.status_pagamento ?? 'pendente',
    forma_pagamento: expense.forma_pagamento ?? '',
    documento_numero: expense.documento_numero ?? '',
    observacoes: expense.observacoes ?? '',
  };
}

function paymentMethodLabel(value?: string | null) {
  const labels: Record<string, string> = { pix: 'PIX', boleto: 'Boleto', cartao: 'Cartão', transferencia: 'Transferência', dinheiro: 'Dinheiro' };
  return value ? labels[value] ?? value : 'Não informada';
}

function DetailRow({ label, value, hint, badge = false }: { label: string; value: string; hint?: string; badge?: boolean }) {
  return <div className="py-2 border-b last:border-0">
    <span className="block text-xs text-gray-500 dark:text-slate-400">{label}</span>
    {badge ? <span className="inline-flex bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-lg text-xs font-bold mt-1">{value}</span> : <strong className="block text-sm mt-0.5">{value}</strong>}
    {hint && <span className="block text-xs text-gray-400 mt-0.5">{hint}</span>}
  </div>;
}

function DateBox({ label, value }: { label: string; value: string }) {
  return <div className="py-2">
    <span className="block text-xs text-gray-500 dark:text-slate-400 mb-1">{label}</span>
    <div className="border bg-slate-50 dark:bg-slate-950 rounded-xl px-3 py-2 flex items-center justify-between text-sm font-semibold">
      {value}
      <Calendar className="w-4 h-4 text-gray-400" />
    </div>
  </div>;
}

function MoneyInput({ label, value, onChange }: { label: string; value: unknown; onChange: (value: number) => void }) {
  return <label className="text-sm block"><span>{label}</span><input className="input mt-1" inputMode="numeric" value={money(value)} onChange={event => { const digits = event.target.value.replace(/\D/g, ''); onChange(digits ? Number(digits) / 100 : 0); }} /></label>;
}

function TextField({ label, value, onChange, placeholder, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; placeholder?: string; type?: string }) {
  return <label className="text-sm block"><span>{label}</span><input className="input mt-1" type={type} value={value ?? ''} placeholder={placeholder} onChange={event => onChange(event.target.value)} /></label>;
}

function Kpi({ title, value, detail, icon: Icon, color }: any) {
  const colors: Record<string, string> = { violet: 'bg-violet-50 dark:bg-violet-950 text-violet-700 dark:text-violet-300', emerald: 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300', amber: 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300', blue: 'bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300' };
  return <div className="bg-white dark:bg-slate-900 border rounded-2xl p-5 shadow-xs flex items-start gap-4"><div className={`w-12 h-12 rounded-xl grid place-items-center ${colors[color]}`}><Icon className="w-6 h-6" /></div><div><span className="text-xs text-gray-500 dark:text-slate-400">{title}</span><strong className="block text-2xl mt-1">{value}</strong><p className="text-xs text-gray-400 mt-1">{detail}</p></div></div>;
}

function HeroMetric({ label, value }: { label: string; value: string }) {
  return <div className="bg-white/10 border border-white/10 rounded-2xl p-4"><span className="text-xs text-slate-300">{label}</span><strong className="block text-xl mt-1">{value}</strong></div>;
}

function SmallMetric({ label, value }: { label: string; value: string }) {
  return <div><span className="text-[11px] text-gray-500 dark:text-slate-400 block">{label}</span><strong className="block mt-0.5">{value}</strong></div>;
}

function InfoCard({ icon: Icon, title, value, detail }: any) {
  return <div className="border rounded-2xl p-4 flex gap-3"><div className="w-10 h-10 bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-300 rounded-xl grid place-items-center shrink-0"><Icon className="w-5 h-5" /></div><div><span className="text-xs text-gray-500 dark:text-slate-400">{title}</span><strong className="block mt-1">{value}</strong><p className="text-xs text-gray-400 mt-1">{detail}</p></div></div>;
}

function ProjectCover({ src, alt, size }: { src?: string; alt: string; size: 'card' | 'hero' }) {
  const classes = size === 'hero' ? 'w-20 h-16 rounded-2xl' : 'w-16 h-14 rounded-xl';

  if (src) {
    return <img src={src} alt={alt} className={`${classes} object-cover border border-white/20 bg-slate-100 dark:bg-slate-800 shrink-0`} />;
  }

  return <div className={`${classes} bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0`}>
    <Home className="w-6 h-6" />
  </div>;
}

function analysisIdFromDescription(description?: string) {
  return String(description ?? '').match(/análise financeira #(\d+)/i)?.[1];
}

function originalWorkBudget(project: Projeto, fallback: number) {
  const match = String(project.descricao ?? '').match(/Orçamento de obra importado:\s*R\$\s*([\d.,]+)/i);
  if (!match) return fallback;

  return Number(match[1].replace(/\./g, '').replace(',', '.')) || fallback;
}

function statusLabel(status?: string) {
  const normalized = String(status ?? '').toLowerCase();
  if (normalized === 'em_andamento') return { label: 'Em andamento', color: 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' };
  if (normalized === 'concluido' || normalized === 'concluida') return { label: 'Concluído', color: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' };
  if (normalized === 'pausado') return { label: 'Pausado', color: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' };
  if (normalized === 'cancelado') return { label: 'Cancelado', color: 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' };
  if (normalized === 'pendente') return { label: 'Pendente', color: 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200' };
  if (normalized === 'pago') return { label: 'Pago', color: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' };
  if (normalized === 'atrasado') return { label: 'Atrasado', color: 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' };
  return { label: 'Planejamento', color: 'bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300' };
}

function PaymentBadge({ status }: { status?: string }) {
  const normalized = normalizePaymentStatus(status);
  const labels = {
    pago: 'Pago',
    pendente: 'A pagar',
    atrasado: 'Vencido',
    cancelado: 'Cancelado',
  };
  const colors = {
    pago: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
    pendente: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
    atrasado: 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300',
    cancelado: 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300',
  };

  return <span className={`inline-flex px-2 py-1 rounded-full text-xs font-bold ${colors[normalized]}`}>{labels[normalized]}</span>;
}

function formatDate(date?: string) {
  if (!date) return '-';

  return new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR');
}
