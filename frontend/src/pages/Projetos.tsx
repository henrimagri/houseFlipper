import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { ArrowRight, Briefcase, Calendar, CircleDollarSign, Eye, Hammer, Home, ListChecks, Loader2, MoreHorizontal, Plus, Receipt, Search, SlidersHorizontal, Target, TrendingUp, X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';

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
type Despesa = { id: number; descricao: string; valor: number; data_despesa: string; status_pagamento?: string; etapa_id?: number; categoria?: { nome: string } };

export default function Projetos() {
  const navigate = useNavigate();
  const [projects, setProjects] = useState<Projeto[]>([]);
  const [summaries, setSummaries] = useState<Record<number, ResumoProjeto>>({});
  const [projectCovers, setProjectCovers] = useState<Record<number, string>>({});
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Categoria[]>([]);
  const [expenses, setExpenses] = useState<Despesa[]>([]);
  const [showExpenses, setShowExpenses] = useState(false);
  const [expenseForm, setExpenseForm] = useState<any>({ descricao: '', valor: 0, etapa_id: '', categoria_despesa_id: '', data_despesa: new Date().toISOString().slice(0, 10), status_pagamento: 'pago' });
  const [newStage, setNewStage] = useState({ nome: '', orcamento: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  async function load() {
    setLoading(true); setError('');
    try {
      const response = await api.get('/projetos', { params: { per_page: 100 } });
      const categoryResponse = await api.get('/categorias-despesa', { params: { tipo: 'reforma' } });
      const items = response.data.data ?? [];
      const categoryItems = categoryResponse.data.data ?? [];
      setProjects(items);
      setCategories(categoryItems);
      setExpenseForm((current: any) => ({ ...current, categoria_despesa_id: current.categoria_despesa_id || categoryItems[0]?.id || '' }));
      const loaded = await Promise.all(items.map((project: Projeto) => api.get(`/projetos/${project.id}/resumo`).then(r => [project.id, r.data]).catch(() => [project.id, null])));
      setSummaries(Object.fromEntries(loaded.filter(([, summary]) => summary)));
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
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível carregar seus projetos.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, []);

  async function loadExpenses(projectId: number) {
    const response = await api.get('/despesas', { params: { projeto_id: projectId, per_page: 200 } });
    setExpenses(response.data.data ?? []);
  }

  useEffect(() => { if (selectedId) loadExpenses(selectedId).catch(() => setExpenses([])); }, [selectedId]);

  async function addExpense() {
    if (!selected) return;
    setSaving(true); setError('');
    try {
      await api.post('/despesas', {
        ...expenseForm,
        valor: Number(expenseForm.valor || 0),
        projeto_id: selected.id,
        propriedade_id: selected.propriedade?.id,
      });
      setExpenseForm((current: any) => ({ ...current, descricao: '', valor: 0 }));
      await load();
      await loadExpenses(selected.id);
      setShowExpenses(true);
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível lançar o gasto.');
    } finally {
      setSaving(false);
    }
  }

  async function updateStageBudget(stageId: number, value: number) {
    await api.patch(`/projetos-etapas/${stageId}`, { orcamento: value });
    await load();
  }

  async function addStage() {
    if (!selected || !newStage.nome.trim()) return;
    setSaving(true); setError('');
    try {
      await api.post(`/projetos/${selected.id}/etapas`, { nome: newStage.nome.trim(), orcamento: Number(newStage.orcamento || 0), status: 'pendente' });
      setNewStage({ nome: '', orcamento: 0 });
      await load();
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível criar a categoria.');
    } finally {
      setSaving(false);
    }
  }

  const totals = useMemo(() => {
    const summariesList = Object.values(summaries);
    const budget = summariesList.reduce((sum, item) => sum + Number(item.orcamento_total || 0), 0);
    const spent = summariesList.reduce((sum, item) => sum + Number(item.custo_real || 0), 0);
    const profit = summariesList.reduce((sum, item) => sum + Number(item.roi?.lucro || 0), 0);
    const progress = summariesList.length ? summariesList.reduce((sum, item) => sum + Number(item.consumido_pct || 0), 0) / summariesList.length : 0;

    return { budget, spent, profit, progress };
  }, [summaries]);

  const selected = projects.find(project => project.id === selectedId) ?? null;
  const selectedSummary = selected ? summaries[selected.id] : null;
  const selectedAnalysisId = selected ? analysisIdFromDescription(selected.descricao) : undefined;

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-gray-500"><Loader2 className="w-8 h-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando projetos...</p></div></div>;

  return <div className="space-y-6">
    <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <div className="text-xs uppercase tracking-widest text-primary-600 font-bold">Gestão da operação</div>
        <h1 className="text-3xl font-black mt-2">Projetos</h1>
        <p className="text-gray-500 mt-1">Acompanhe as oportunidades que viraram execução: obra, orçamento, custos e resultado.</p>
      </div>
      <button onClick={() => selectedAnalysisId ? navigate(`/analises-financeiras/${selectedAnalysisId}`) : navigate('/leads')} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold flex items-center gap-2 w-fit">
        {selectedAnalysisId ? <Eye className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
        {selectedAnalysisId ? 'Ver análise financeira de origem' : 'Abrir análises financeiras'}
      </button>
    </div>

    {error && <div className="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4">{error}</div>}

    {projects.length === 0 ? <EmptyProjects onStart={() => navigate('/leads')} /> : <>
      <section className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <Kpi title="Projetos ativos" value={projects.length} detail="originados de análises ou criados manualmente" icon={Briefcase} color="violet" />
        <Kpi title="Orçamento de obra" value={money(totals.budget)} detail="valor disponível para reforma" icon={CircleDollarSign} color="emerald" />
        <Kpi title="Gasto em obra" value={money(totals.spent)} detail="gastos já lançados na obra" icon={Hammer} color="amber" />
        <Kpi title="Consumo médio" value={percent(totals.progress)} detail="média do orçamento consumido" icon={TrendingUp} color="blue" />
      </section>

      {!selected ? <ProjectSelectionGrid projects={projects} summaries={summaries} covers={projectCovers} onSelect={(projectId) => { setShowExpenses(false); setSelectedId(projectId); }} /> : <div className="space-y-4"><button type="button" onClick={() => setSelectedId(null)} className="text-primary-600 font-bold text-sm flex items-center gap-2">← Voltar para todos os projetos</button><ProjectDetails project={selected} summary={selectedSummary} coverUrl={projectCovers[selected.id]} expenses={expenses} categories={categories} showExpenses={showExpenses} setShowExpenses={setShowExpenses} expenseForm={expenseForm} setExpenseForm={setExpenseForm} newStage={newStage} setNewStage={setNewStage} onAddExpense={addExpense} onAddStage={addStage} onUpdateStageBudget={updateStageBudget} saving={saving} /></div>}
    </>}
  </div>;
}

function ProjectSelectionGrid({ projects, summaries, covers, onSelect }: { projects: Projeto[]; summaries: Record<number, ResumoProjeto>; covers: Record<number, string>; onSelect: (projectId: number) => void }) {
  return <section className="bg-white border rounded-3xl p-5 shadow-sm">
    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h2 className="text-xl font-black">Selecione um projeto para acompanhar</h2>
        <p className="text-sm text-gray-500 mt-1">Escolha qual obra deseja abrir para ver metas, orçamento e gastos detalhados.</p>
      </div>
      <span className="text-xs bg-slate-100 text-slate-600 px-3 py-2 rounded-full font-semibold">{projects.length} projeto{projects.length === 1 ? '' : 's'}</span>
    </div>
    <div className="grid md:grid-cols-2 2xl:grid-cols-3 gap-4 mt-5">
      {projects.map(project => <ProjectCard key={project.id} project={project} summary={summaries[project.id]} coverUrl={covers[project.id]} active={false} onClick={() => onSelect(project.id)} />)}
    </div>
  </section>;
}

function EmptyProjects({ onStart }: { onStart: () => void }) {
  return <section className="bg-white border rounded-3xl p-10 text-center">
    <div className="w-16 h-16 bg-primary-50 text-primary-600 rounded-2xl grid place-items-center mx-auto"><Briefcase className="w-8 h-8" /></div>
    <h2 className="text-2xl font-black mt-5">Nenhum projeto ainda</h2>
    <p className="text-gray-500 mt-2 max-w-xl mx-auto">Quando uma análise financeira virar projeto, ela aparecerá aqui com o orçamento da obra, metas por categoria e gastos realizados.</p>
    <button onClick={onStart} className="mt-6 bg-primary-600 text-white px-5 py-3 rounded-xl font-bold">Ir para Leads & Oportunidades</button>
  </section>;
}

function ProjectCard({ project, summary, coverUrl, active, onClick }: { project: Projeto; summary?: ResumoProjeto; coverUrl?: string; active: boolean; onClick: () => void }) {
  const status = statusLabel(project.status);
  const origin = analysisIdFromDescription(project.descricao);

  return <button onClick={onClick} className={`w-full text-left bg-white border rounded-2xl p-4 transition shadow-sm ${active ? 'border-primary-400 ring-2 ring-primary-100' : 'hover:border-gray-300'}`}>
    <div className="flex justify-between gap-3">
      <div className="min-w-0 flex items-center gap-3">
        <ProjectCover src={coverUrl} alt={project.nome} size="card" />
        <div className="min-w-0">
          <h3 className="font-black truncate">{project.nome}</h3>
          <p className="text-sm text-gray-500 truncate mt-1">{project.propriedade?.apelido ?? 'Sem propriedade vinculada'}</p>
        </div>
      </div>
      <span className={`text-[11px] font-bold px-2 py-1 rounded-full h-fit ${status.color}`}>{status.label}</span>
    </div>
    <div className="grid grid-cols-2 gap-3 mt-4 text-sm">
      <SmallMetric label="Orçamento" value={money(summary?.orcamento_total ?? project.orcamento_total)} />
      <SmallMetric label="Realizado" value={money(summary?.custo_real)} />
      <SmallMetric label="Consumido" value={percent(summary?.consumido_pct)} />
      <SmallMetric label="ROI" value={summary?.roi?.tem_venda ? percent(summary.roi.roi_pct) : 'Em aberto'} />
    </div>
    <div className="flex items-center justify-between mt-4 text-xs text-gray-500">
      <span>{origin ? `Origem: análise #${origin}` : 'Origem: manual'}</span>
      <span className="text-primary-600 font-bold flex items-center gap-1">Acompanhar <ArrowRight className="w-4 h-4" /></span>
    </div>
  </button>;
}

function ProjectDetails({ project, summary, coverUrl, expenses, categories, showExpenses, setShowExpenses, expenseForm, setExpenseForm, newStage, setNewStage, onAddExpense, onAddStage, onUpdateStageBudget, saving }: { project: Projeto; summary?: ResumoProjeto | null; coverUrl?: string; expenses: Despesa[]; categories: Categoria[]; showExpenses: boolean; setShowExpenses: (value: boolean) => void; expenseForm: any; setExpenseForm: (value: any) => void; newStage: { nome: string; orcamento: number }; setNewStage: (value: { nome: string; orcamento: number }) => void; onAddExpense: () => void; onAddStage: () => void; onUpdateStageBudget: (stageId: number, value: number) => void; saving: boolean }) {
  const etapas = summary?.etapas ?? [];
  const origin = analysisIdFromDescription(project.descricao);
  const budget = Number(summary?.orcamento_total ?? project.orcamento_total ?? 0);
  const spent = Number(summary?.custo_real ?? 0);
  const available = Math.max(0, budget - spent);
  const originalBudget = originalWorkBudget(project, budget);
  const budgetDelta = budget - originalBudget;
  const budgetDeltaPct = originalBudget > 0 ? budgetDelta / originalBudget * 100 : 0;

  return <div className="bg-white border rounded-3xl overflow-hidden shadow-sm">
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
        <span className={`text-xs font-bold px-3 py-2 rounded-full ${statusLabel(project.status).color}`}>{statusLabel(project.status).label}</span>
      </div>
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mt-6">
        <HeroMetric label="Orçamento da obra" value={money(budget)} />
        <HeroMetric label="Gasto lançado" value={money(spent)} />
        <HeroMetric label="Disponível da obra" value={money(available)} />
        <HeroMetric label="% consumido" value={percent(summary?.consumido_pct)} />
      </div>
      <p className="text-xs text-slate-400 mt-3">
        Valor definido na compra: <strong className="text-slate-200">{money(originalBudget)}</strong>
        {Math.abs(budgetDelta) > 0.009 && <> · orçamento atual: <strong className="text-slate-200">{money(budget)}</strong> ({budgetDelta > 0 ? '+' : ''}{percent(budgetDeltaPct)})</>}
      </p>
    </div>

    <div className="p-6 space-y-6">
      {project.descricao && <div className="bg-violet-50 border border-violet-100 text-violet-900 rounded-2xl p-4 text-sm">{project.descricao}</div>}

      <section>
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <h3 className="font-black text-lg">Metas da obra por categoria</h3>
            <p className="text-sm text-gray-500">Defina quanto pretende gastar em Hidráulica, Elétrica, Demolição, Acabamento etc. Os gastos lançados consomem essas metas.</p>
          </div>
          <button type="button" onClick={() => setShowExpenses(!showExpenses)} className="border border-primary-200 text-primary-700 px-4 py-2.5 rounded-xl font-bold flex items-center gap-2 w-fit">{showExpenses ? <X className="w-4 h-4" /> : <Eye className="w-4 h-4" />}{showExpenses ? 'Ocultar gastos' : 'Lançar/ver gastos'}</button>
        </div>
        <div className="space-y-3 mt-4">
          {etapas.length ? etapas.map(etapa => <StageRow key={etapa.id} etapa={etapa} total={budget} onUpdateBudget={onUpdateStageBudget} />) : <div className="text-sm text-gray-500 border rounded-xl p-4">Nenhuma etapa cadastrada ainda.</div>}
        </div>
        <div className="mt-4 border border-dashed rounded-2xl p-4 bg-slate-50">
          <h4 className="font-bold">Adicionar nova categoria</h4>
          <p className="text-xs text-gray-500 mt-1">Se o orçamento original já foi todo distribuído, a nova meta aumenta o orçamento atual da obra.</p>
          <div className="grid md:grid-cols-[1fr_220px_auto] gap-3 mt-3 items-end">
            <TextField label="Nome da categoria" value={newStage.nome} onChange={(value: string) => setNewStage({ ...newStage, nome: value })} placeholder="Ex: Marcenaria" />
            <MoneyInput label="Meta/orçamento" value={newStage.orcamento} onChange={(value: number) => setNewStage({ ...newStage, orcamento: value })} />
            <button type="button" disabled={saving || !newStage.nome.trim()} onClick={onAddStage} className="bg-slate-950 text-white px-4 py-3 rounded-xl font-bold disabled:opacity-50">Adicionar</button>
          </div>
        </div>
      </section>

      {showExpenses && <ExpenseWorkspace project={project} budget={budget} etapas={etapas} expenses={expenses} categories={categories} expenseForm={expenseForm} setExpenseForm={setExpenseForm} onAddExpense={onAddExpense} saving={saving} />}

      <section className="grid md:grid-cols-3 gap-4">
        <InfoCard icon={Home} title="Imóvel" value={project.propriedade?.apelido ?? 'Não informado'} detail="Propriedade vinculada ao projeto" />
        <InfoCard icon={Calendar} title="Cronograma" value={project.data_termino_prevista ? 'Com prazo' : 'A definir'} detail={project.data_termino_prevista ?? 'Defina datas nas próximas etapas'} />
        <InfoCard icon={Target} title="Resultado" value={summary?.roi?.tem_venda ? money(summary.roi.lucro) : 'Ainda em execução'} detail={summary?.roi?.tem_venda ? `ROI ${percent(summary.roi.roi_pct)}` : 'ROI final aparece após a venda'} />
      </section>
    </div>
  </div>;
}

function StageRow({ etapa, total, onUpdateBudget }: { etapa: ResumoProjeto['etapas'][number]; total: number; onUpdateBudget: (stageId: number, value: number) => void }) {
  const [draftBudget, setDraftBudget] = useState(etapa.orcamento);
  const width = total > 0 ? Math.min(100, Math.max(4, Number(etapa.orcamento || 0) / total * 100)) : 4;
  const consumed = Number(etapa.orcamento) > 0 ? Math.min(100, Number(etapa.custo_real || 0) / Number(etapa.orcamento) * 100) : 0;
  const available = Number(etapa.orcamento || 0) - Number(etapa.custo_real || 0);

  return <div className="border rounded-2xl p-4">
    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
      <div>
        <h4 className="font-bold">{etapa.nome}</h4>
        <p className="text-xs text-gray-500">Status: {statusLabel(etapa.status).label}</p>
      </div>
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm md:text-right">
        <div className="text-left md:text-right"><span className="text-[11px] text-gray-500 block">Meta/orçamento</span><input className="input mt-1 h-9 text-sm md:text-right" value={money(draftBudget)} onBlur={() => onUpdateBudget(etapa.id, Number(draftBudget || 0))} onChange={event => { const digits = event.target.value.replace(/\D/g, ''); setDraftBudget(digits ? Number(digits) / 100 : 0); }} /></div>
        <SmallMetric label="Real" value={money(etapa.custo_real)} />
        <SmallMetric label="Disponível" value={money(available)} />
        <SmallMetric label="Consumido" value={percent(consumed)} />
      </div>
    </div>
    <div className="mt-4">
      <div className="h-3 bg-gray-100 rounded-full overflow-hidden"><div className="h-full bg-primary-500 rounded-full" style={{ width: `${width}%` }} /></div>
      <div className="h-1 bg-gray-100 rounded-full overflow-hidden mt-2"><div className="h-full bg-emerald-500 rounded-full" style={{ width: `${consumed}%` }} /></div>
    </div>
  </div>;
}

function ExpenseWorkspace({ project, budget, etapas, expenses, categories, expenseForm, setExpenseForm, onAddExpense, saving }: { project: Projeto; budget: number; etapas: ResumoProjeto['etapas']; expenses: Despesa[]; categories: Categoria[]; expenseForm: any; setExpenseForm: (value: any) => void; onAddExpense: () => void; saving: boolean }) {
  const [activeTab, setActiveTab] = useState<'todos' | 'pendente' | 'atrasado' | 'pago'>('todos');
  const [query, setQuery] = useState('');
  const [stageFilter, setStageFilter] = useState('');
  const [selectedExpenseId, setSelectedExpenseId] = useState<number | null>(expenses[0]?.id ?? null);
  const [isExpenseModalOpen, setIsExpenseModalOpen] = useState(false);

  useEffect(() => {
    if (expenses.length && !expenses.some(expense => expense.id === selectedExpenseId)) {
      setSelectedExpenseId(expenses[0].id);
    }
  }, [expenses, selectedExpenseId]);

  const stats = useMemo(() => {
    const total = expenses.reduce((sum, expense) => sum + Number(expense.valor || 0), 0);
    const paid = expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pago').reduce((sum, expense) => sum + Number(expense.valor || 0), 0);
    const pending = expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pendente').reduce((sum, expense) => sum + Number(expense.valor || 0), 0);
    const overdue = expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'atrasado').reduce((sum, expense) => sum + Number(expense.valor || 0), 0);

    return { total, paid, pending, overdue };
  }, [expenses]);

  const filteredExpenses = useMemo(() => expenses.filter(expense => {
    const status = normalizePaymentStatus(expense.status_pagamento);
    const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? expense.categoria?.nome ?? '';
    const matchesTab = activeTab === 'todos' || status === activeTab;
    const matchesQuery = !query || `${expense.descricao} ${stageName}`.toLowerCase().includes(query.toLowerCase());
    const matchesStage = !stageFilter || String(expense.etapa_id ?? '') === stageFilter;

    return matchesTab && matchesQuery && matchesStage;
  }), [activeTab, expenses, etapas, query, stageFilter]);

  const selectedExpense = expenses.find(expense => expense.id === selectedExpenseId) ?? filteredExpenses[0] ?? null;

  return <section className="space-y-4">
    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
      <div>
        <h3 className="text-2xl font-black">Despesas da obra</h3>
        <p className="text-sm text-gray-500">Controle todos os gastos deste projeto em um só lugar.</p>
      </div>
      <div className="flex gap-2">
        <button type="button" className="border bg-white px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 text-slate-700"><SlidersHorizontal className="w-4 h-4" />Filtros</button>
        <button type="button" className="bg-primary-600 text-white px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2" onClick={() => setIsExpenseModalOpen(true)}><Plus className="w-4 h-4" />Nova despesa</button>
      </div>
    </div>

    <div className="grid sm:grid-cols-2 xl:grid-cols-5 gap-3">
      <ExpenseKpi title="Total de despesas" value={money(stats.total)} detail={`${expenses.length} lançamento${expenses.length === 1 ? '' : 's'}`} tone="violet" />
      <ExpenseKpi title="Pago" value={money(stats.paid)} detail={`${budget > 0 ? percent(stats.paid / budget * 100) : '0,0%'} do orçamento`} tone="emerald" />
      <ExpenseKpi title="A pagar" value={money(stats.pending)} detail="pendente de pagamento" tone="amber" />
      <ExpenseKpi title="Vencido" value={money(stats.overdue)} detail="precisa de atenção" tone="red" />
      <ExpenseKpi title="Previsto (Orçado)" value={money(budget)} detail="orçamento total da obra" tone="blue" />
    </div>

    <div className="grid 2xl:grid-cols-[1fr_320px] gap-4">
      <div className="space-y-4 min-w-0">
        <div className="bg-white border rounded-2xl p-3 grid lg:grid-cols-[240px_200px_1fr] gap-3">
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
        </div>

        <div className="bg-white border rounded-2xl overflow-hidden">
          <div className="px-4 pt-4 flex flex-wrap gap-4 text-sm border-b">
            <ExpenseTab active={activeTab === 'todos'} onClick={() => setActiveTab('todos')}>Todas as Despesas ({expenses.length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'pendente'} onClick={() => setActiveTab('pendente')}>A Pagar ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pendente').length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'atrasado'} onClick={() => setActiveTab('atrasado')}>Vencidas ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'atrasado').length})</ExpenseTab>
            <ExpenseTab active={activeTab === 'pago'} onClick={() => setActiveTab('pago')}>Pagas ({expenses.filter(expense => normalizePaymentStatus(expense.status_pagamento) === 'pago').length})</ExpenseTab>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-xs text-gray-500 uppercase">
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
                {filteredExpenses.length ? filteredExpenses.map(expense => {
                  const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? '-';
                  const selected = selectedExpense?.id === expense.id;

                  return <tr key={expense.id} onClick={() => setSelectedExpenseId(expense.id)} className={`border-t cursor-pointer hover:bg-slate-50 ${selected ? 'bg-primary-50/50' : ''}`}>
                    <td className="p-3 whitespace-nowrap">{formatDate(expense.data_despesa)}</td>
                    <td className="p-3 font-bold min-w-[220px]">{expense.descricao}</td>
                    <td className="p-3"><span className="bg-blue-50 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold">{stageName}</span></td>
                    <td className="p-3 text-gray-600">{expense.categoria?.nome ?? '-'}</td>
                    <td className="p-3 text-right font-black whitespace-nowrap">{money(expense.valor)}</td>
                    <td className="p-3"><PaymentBadge status={expense.status_pagamento} /></td>
                    <td className="p-3 text-center"><MoreHorizontal className="w-4 h-4 text-gray-400 mx-auto" /></td>
                  </tr>;
                }) : <tr><td colSpan={7} className="p-8 text-center text-gray-500">Nenhuma despesa encontrada com esses filtros.</td></tr>}
              </tbody>
            </table>
          </div>
          <div className="p-4 text-xs text-gray-500 border-t">Mostrando {filteredExpenses.length} de {expenses.length} despesas</div>
        </div>
      </div>

      <ExpenseDetailPanel project={project} expense={selectedExpense} etapas={etapas} />
    </div>

    {isExpenseModalOpen && <NewExpenseModal etapas={etapas} categories={categories} expenseForm={expenseForm} setExpenseForm={setExpenseForm} saving={saving} onClose={() => setIsExpenseModalOpen(false)} onSubmit={async () => { await Promise.resolve(onAddExpense()); setIsExpenseModalOpen(false); }} />}
  </section>;
}

function NewExpenseModal({ etapas, categories, expenseForm, setExpenseForm, saving, onClose, onSubmit }: { etapas: ResumoProjeto['etapas']; categories: Categoria[]; expenseForm: any; setExpenseForm: (value: any) => void; saving: boolean; onClose: () => void; onSubmit: () => void }) {
  const disabled = saving || !expenseForm.descricao || !expenseForm.valor || !expenseForm.etapa_id || !expenseForm.categoria_despesa_id;

  return <div className="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div className="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden">
      <div className="p-5 border-b flex items-center justify-between">
        <div>
          <h3 className="font-black text-xl">Nova despesa da obra</h3>
          <p className="text-sm text-gray-500 mt-1">Cadastre um gasto e vincule à categoria/meta correta.</p>
        </div>
        <button type="button" onClick={onClose} className="w-9 h-9 rounded-xl hover:bg-gray-100 grid place-items-center"><X className="w-5 h-5 text-gray-500" /></button>
      </div>

      <div className="p-5 grid md:grid-cols-2 gap-4">
        <div className="md:col-span-2">
          <TextField label="Descrição" value={expenseForm.descricao} onChange={(value: string) => setExpenseForm({ ...expenseForm, descricao: value })} placeholder="Ex: Compra de cano PVC" />
        </div>
        <MoneyInput label="Valor" value={expenseForm.valor} onChange={(value: number) => setExpenseForm({ ...expenseForm, valor: value })} />
        <TextField label="Data" type="date" value={expenseForm.data_despesa} onChange={(value: string) => setExpenseForm({ ...expenseForm, data_despesa: value })} />
        <label className="text-sm block"><span>Categoria/meta da obra</span><select className="input mt-1" value={expenseForm.etapa_id} onChange={event => setExpenseForm({ ...expenseForm, etapa_id: event.target.value })}><option value="">Selecione</option>{etapas.map(etapa => <option key={etapa.id} value={etapa.id}>{etapa.nome}</option>)}</select></label>
        <label className="text-sm block"><span>Tipo financeiro</span><select className="input mt-1" value={expenseForm.categoria_despesa_id} onChange={event => setExpenseForm({ ...expenseForm, categoria_despesa_id: event.target.value })}>{categories.map(category => <option key={category.id} value={category.id}>{category.nome}</option>)}</select></label>
        <label className="text-sm block"><span>Status</span><select className="input mt-1" value={expenseForm.status_pagamento} onChange={event => setExpenseForm({ ...expenseForm, status_pagamento: event.target.value })}><option value="pago">Pago</option><option value="pendente">A pagar</option><option value="atrasado">Vencido</option></select></label>
        <label className="text-sm block"><span>Fornecedor</span><input className="input mt-1" placeholder="Opcional" /></label>
        <label className="text-sm block md:col-span-2"><span>Observações</span><textarea className="input mt-1 min-h-24 resize-none" placeholder="Ex: compra feita para manutenção hidráulica" /></label>
      </div>

      <div className="p-5 border-t bg-slate-50 flex justify-end gap-3">
        <button type="button" onClick={onClose} className="border bg-white px-5 py-3 rounded-xl font-bold">Cancelar</button>
        <button type="button" disabled={disabled} onClick={onSubmit} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold disabled:opacity-50">{saving ? 'Salvando...' : 'Adicionar despesa'}</button>
      </div>
    </div>
  </div>;
}

function ExpenseKpi({ title, value, detail, tone }: { title: string; value: string; detail: string; tone: 'violet' | 'emerald' | 'amber' | 'red' | 'blue' }) {
  const tones = {
    violet: 'text-primary-600 bg-primary-50',
    emerald: 'text-emerald-600 bg-emerald-50',
    amber: 'text-amber-600 bg-amber-50',
    red: 'text-red-600 bg-red-50',
    blue: 'text-blue-600 bg-blue-50',
  };

  return <div className="bg-white border rounded-2xl p-4">
    <div className="flex items-start justify-between gap-3">
      <span className="text-xs text-gray-500">{title}</span>
      <span className={`w-8 h-8 rounded-lg grid place-items-center ${tones[tone]}`}><Receipt className="w-4 h-4" /></span>
    </div>
    <strong className={`block text-xl mt-3 ${tone === 'emerald' ? 'text-emerald-600' : tone === 'amber' ? 'text-amber-600' : tone === 'red' ? 'text-red-600' : ''}`}>{value}</strong>
    <p className="text-xs text-gray-500 mt-1">{detail}</p>
  </div>;
}

function ExpenseTab({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
  return <button type="button" onClick={onClick} className={`pb-3 border-b-2 font-bold ${active ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>{children}</button>;
}

function ExpenseDetailPanel({ project, expense, etapas }: { project: Projeto; expense: Despesa | null; etapas: ResumoProjeto['etapas'] }) {
  if (!expense) {
    return <aside className="bg-white border rounded-2xl p-5 min-h-[320px]">
      <h3 className="font-black">Detalhes da despesa</h3>
      <p className="text-sm text-gray-500 mt-2">Selecione uma despesa na tabela para ver os detalhes.</p>
    </aside>;
  }

  const stageName = etapas.find(etapa => etapa.id === expense.etapa_id)?.nome ?? expense.categoria?.nome ?? '-';

  return <aside className="bg-white border rounded-2xl overflow-hidden h-fit">
    <div className="p-5 border-b flex justify-between items-center">
      <h3 className="font-black">Detalhes da despesa</h3>
      <X className="w-4 h-4 text-gray-400" />
    </div>
    <div className="p-5 space-y-5">
      <PaymentBadge status={expense.status_pagamento} />
      <div className="flex gap-3 items-center">
        <div className="w-12 h-12 bg-primary-50 text-primary-600 rounded-2xl grid place-items-center"><Receipt className="w-6 h-6" /></div>
        <div>
          <h4 className="font-black">{expense.descricao}</h4>
          <strong className="text-xl">{money(expense.valor)}</strong>
        </div>
      </div>
      <div>
        <div className="text-xs uppercase font-bold text-gray-500 mb-3">Informações gerais</div>
        <DateBox label="Data da despesa" value={formatDate(expense.data_despesa)} />
        <DetailRow label="Categoria" value={stageName} badge />
        <DetailRow label="Fornecedor" value="Não informado" />
        <DetailRow label="Forma de pagamento" value="Não informado" />
        <DateBox label="Vencimento" value={formatDate(expense.data_despesa)} />
        <DetailRow label="Projeto / Obra" value={project.nome} hint={project.propriedade?.apelido ?? 'Obra vinculada'} />
      </div>
      <div className="bg-slate-50 rounded-2xl p-4">
        <div className="text-xs text-gray-500">Observação</div>
        <p className="text-sm mt-1">Despesa vinculada ao orçamento da categoria {stageName}.</p>
      </div>
      <div>
        <div className="text-xs uppercase font-bold text-gray-500 mb-3">Anexos</div>
        <div className="grid grid-cols-2 gap-3">
          <div className="border rounded-xl overflow-hidden">
            <div className="h-20 bg-slate-100 grid place-items-center text-slate-400"><Receipt className="w-7 h-7" /></div>
            <div className="p-2 text-xs"><strong>nota_fiscal.pdf</strong><span className="block text-gray-400">Opcional</span></div>
          </div>
          <button type="button" className="border border-dashed rounded-xl min-h-28 text-primary-600 font-bold text-sm grid place-items-center">
            <span><Plus className="w-5 h-5 mx-auto mb-1" />Adicionar<br /><small className="font-normal text-gray-400">Novo anexo</small></span>
          </button>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <button type="button" className="border rounded-xl py-3 font-bold text-sm">Editar</button>
        <button type="button" className="bg-primary-600 text-white rounded-xl py-3 font-bold text-sm">Marcar como pago</button>
      </div>
    </div>
  </aside>;
}

function DetailRow({ label, value, hint, badge = false }: { label: string; value: string; hint?: string; badge?: boolean }) {
  return <div className="py-2 border-b last:border-0">
    <span className="block text-xs text-gray-500">{label}</span>
    {badge ? <span className="inline-flex bg-blue-50 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold mt-1">{value}</span> : <strong className="block text-sm mt-0.5">{value}</strong>}
    {hint && <span className="block text-xs text-gray-400 mt-0.5">{hint}</span>}
  </div>;
}

function DateBox({ label, value }: { label: string; value: string }) {
  return <div className="py-2">
    <span className="block text-xs text-gray-500 mb-1">{label}</span>
    <div className="border bg-slate-50 rounded-xl px-3 py-2 flex items-center justify-between text-sm font-semibold">
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
  const colors: Record<string, string> = { violet: 'bg-violet-50 text-violet-700', emerald: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', blue: 'bg-blue-50 text-blue-700' };
  return <div className="bg-white border rounded-2xl p-5 shadow-sm flex items-start gap-4"><div className={`w-12 h-12 rounded-xl grid place-items-center ${colors[color]}`}><Icon className="w-6 h-6" /></div><div><span className="text-xs text-gray-500">{title}</span><strong className="block text-2xl mt-1">{value}</strong><p className="text-xs text-gray-400 mt-1">{detail}</p></div></div>;
}

function HeroMetric({ label, value }: { label: string; value: string }) {
  return <div className="bg-white/10 border border-white/10 rounded-2xl p-4"><span className="text-xs text-slate-300">{label}</span><strong className="block text-xl mt-1">{value}</strong></div>;
}

function SmallMetric({ label, value }: { label: string; value: string }) {
  return <div><span className="text-[11px] text-gray-500 block">{label}</span><strong className="block mt-0.5">{value}</strong></div>;
}

function InfoCard({ icon: Icon, title, value, detail }: any) {
  return <div className="border rounded-2xl p-4 flex gap-3"><div className="w-10 h-10 bg-slate-50 text-slate-600 rounded-xl grid place-items-center shrink-0"><Icon className="w-5 h-5" /></div><div><span className="text-xs text-gray-500">{title}</span><strong className="block mt-1">{value}</strong><p className="text-xs text-gray-400 mt-1">{detail}</p></div></div>;
}

function ProjectCover({ src, alt, size }: { src?: string; alt: string; size: 'card' | 'hero' }) {
  const classes = size === 'hero' ? 'w-20 h-16 rounded-2xl' : 'w-16 h-14 rounded-xl';

  if (src) {
    return <img src={src} alt={alt} className={`${classes} object-cover border border-white/20 bg-slate-100 shrink-0`} />;
  }

  return <div className={`${classes} bg-primary-50 text-primary-600 grid place-items-center shrink-0`}>
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
  if (normalized === 'em_andamento') return { label: 'Em andamento', color: 'bg-blue-100 text-blue-700' };
  if (normalized === 'concluido' || normalized === 'concluida') return { label: 'Concluído', color: 'bg-emerald-100 text-emerald-700' };
  if (normalized === 'pausado') return { label: 'Pausado', color: 'bg-amber-100 text-amber-700' };
  if (normalized === 'cancelado') return { label: 'Cancelado', color: 'bg-red-100 text-red-700' };
  if (normalized === 'pendente') return { label: 'Pendente', color: 'bg-gray-100 text-gray-700' };
  if (normalized === 'pago') return { label: 'Pago', color: 'bg-emerald-100 text-emerald-700' };
  if (normalized === 'atrasado') return { label: 'Atrasado', color: 'bg-red-100 text-red-700' };
  return { label: 'Planejamento', color: 'bg-violet-100 text-violet-700' };
}

function normalizePaymentStatus(status?: string) {
  const normalized = String(status ?? '').toLowerCase();
  if (['pago', 'paid'].includes(normalized)) return 'pago';
  if (['atrasado', 'vencido', 'overdue'].includes(normalized)) return 'atrasado';
  return 'pendente';
}

function PaymentBadge({ status }: { status?: string }) {
  const normalized = normalizePaymentStatus(status);
  const labels = {
    pago: 'Pago',
    pendente: 'A pagar',
    atrasado: 'Vencido',
  };
  const colors = {
    pago: 'bg-emerald-100 text-emerald-700',
    pendente: 'bg-amber-100 text-amber-700',
    atrasado: 'bg-red-100 text-red-700',
  };

  return <span className={`inline-flex px-2 py-1 rounded-full text-xs font-bold ${colors[normalized]}`}>{labels[normalized]}</span>;
}

function formatDate(date?: string) {
  if (!date) return '-';

  return new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR');
}
