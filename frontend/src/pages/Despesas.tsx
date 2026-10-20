import { useEffect, useMemo, useRef, useState } from 'react';
import {
  AlertTriangle, ArrowLeftRight, Banknote, CheckCircle2, ChevronLeft, ChevronRight,
  Clock, CreditCard, Download, FileText, Loader2, Plus, Search, ShoppingCart, Wallet2, X,
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { createExpenseCsv } from '@/lib/expenseExport';
import { normalizePaymentStatus, summarizeExpenses } from '@/lib/expenseSummary';
import { PaginatedCollection } from '@/lib/paginatedCollection';

type Status = 'pago' | 'pendente' | 'atrasado' | 'cancelado';
type Tab = 'todas' | Status;
type Category = { id: number; nome: string; tipo: string };
type Project = { id: number; nome: string; propriedade?: { apelido?: string } };
type Expense = {
  id: number;
  categoria_despesa_id: number;
  categoria?: { id: number; nome: string; tipo: string };
  propriedade_id?: number | null;
  projeto_id?: number | null;
  etapa_id?: number | null;
  tarefa_id?: number | null;
  descricao: string;
  valor: number | string;
  data_despesa: string;
  data_pagamento?: string | null;
  forma_pagamento?: string | null;
  status_pagamento?: string;
  documento_numero?: string | null;
  observacoes?: string | null;
};

const PAGE_SIZE = 25;
const money = (value: unknown) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Despesas() {
  const navigate = useNavigate();
  const expenseLoader = useRef(new PaginatedCollection<Expense>());
  const projectLoader = useRef(new PaginatedCollection<Project>());
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [tab, setTab] = useState<Tab>('todas');
  const [search, setSearch] = useState('');
  const [projectId, setProjectId] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [page, setPage] = useState(1);

  async function load(showSpinner = true) {
    if (showSpinner) setLoading(true);
    setError('');
    try {
      const [expenseItems, projectItems, categoryResponse] = await Promise.all([
        expenseLoader.current.load(async (nextPage, perPage, signal) => {
          const response = await api.get('/despesas', { params: { page: nextPage, per_page: perPage }, signal });
          return response.data;
        }),
        projectLoader.current.load(async (nextPage, perPage, signal) => {
          const response = await api.get('/projetos', { params: { page: nextPage, per_page: perPage }, signal });
          return response.data;
        }),
        api.get('/categorias-despesa'),
      ]);
      if (!expenseItems || !projectItems) return false;
      setExpenses(expenseItems);
      setProjects(projectItems);
      setCategories(categoryResponse.data.data ?? []);
      return true;
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? exception.message ?? 'Não foi possível carregar as despesas.'); return false;
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    return () => { expenseLoader.current.cancel(); projectLoader.current.cancel(); };
  }, []);

  const projectById = useMemo(() => new Map(projects.map(item => [item.id, item])), [projects]);
  const normalizedSearch = search.trim().toLocaleLowerCase('pt-BR');
  const baseFiltered = useMemo(() => expenses.filter(expense => {
    if (projectId && String(expense.projeto_id ?? '') !== projectId) return false;
    if (categoryId && String(expense.categoria_despesa_id) !== categoryId) return false;
    if (startDate && expense.data_despesa < startDate) return false;
    if (endDate && expense.data_despesa > endDate) return false;
    if (!normalizedSearch) return true;
    const project = expense.projeto_id ? projectById.get(expense.projeto_id) : undefined;
    return [expense.descricao, expense.categoria?.nome, project?.nome, expense.documento_numero, expense.observacoes]
      .some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(normalizedSearch));
  }), [expenses, projectId, categoryId, startDate, endDate, normalizedSearch, projectById]);

  const filtered = useMemo(() => baseFiltered.filter(expense => tab === 'todas' || normalizePaymentStatus(expense.status_pagamento) === tab), [baseFiltered, tab]);
  const totals = useMemo(() => summarizeExpenses(baseFiltered), [baseFiltered]);
  const counts = useMemo(() => ({
    todas: baseFiltered.length,
    pago: baseFiltered.filter(item => normalizePaymentStatus(item.status_pagamento) === 'pago').length,
    pendente: baseFiltered.filter(item => normalizePaymentStatus(item.status_pagamento) === 'pendente').length,
    atrasado: baseFiltered.filter(item => normalizePaymentStatus(item.status_pagamento) === 'atrasado').length,
    cancelado: baseFiltered.filter(item => normalizePaymentStatus(item.status_pagamento) === 'cancelado').length,
  }), [baseFiltered]);
  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const visible = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
  const selected = expenses.find(item => item.id === selectedId) ?? null;

  useEffect(() => setPage(1), [tab, search, projectId, categoryId, startDate, endDate]);
  useEffect(() => { if (page > pageCount) setPage(pageCount); }, [page, pageCount]);

  function exportCsv() {
    if (!filtered.length) return;
    const csv = createExpenseCsv(filtered.map(expense => ({
      data: expense.data_despesa,
      descricao: expense.descricao,
      categoria: expense.categoria?.nome ?? 'Sem categoria',
      projeto: expense.projeto_id ? projectById.get(expense.projeto_id)?.nome ?? `Projeto #${expense.projeto_id}` : 'Sem projeto',
      formaPagamento: paymentMethod(expense.forma_pagamento),
      valor: expense.valor,
      status: statusInfo(expense.status_pagamento).label,
    })));
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `despesas-${new Date().toISOString().slice(0, 10)}.csv`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  async function markPaid(expense: Expense) {
    setSaving(true); setError(''); setNotice('');
    try {
      await api.put(`/despesas/${expense.id}`, {
        categoria_despesa_id: expense.categoria_despesa_id,
        descricao: expense.descricao,
        valor: Number(expense.valor),
        data_despesa: expense.data_despesa,
        propriedade_id: expense.propriedade_id ?? null,
        projeto_id: expense.projeto_id ?? null,
        etapa_id: expense.etapa_id ?? null,
        tarefa_id: expense.tarefa_id ?? null,
        data_pagamento: new Date().toISOString().slice(0, 10),
        forma_pagamento: expense.forma_pagamento ?? null,
        status_pagamento: 'pago',
        documento_numero: expense.documento_numero ?? null,
        observacoes: expense.observacoes ?? null,
      });
      const refreshed = await load(false);
      setNotice(refreshed ? 'Despesa marcada como paga.' : 'Despesa marcada como paga, mas a lista não pôde ser recarregada. Use Atualizar.');
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível atualizar a despesa.');
    } finally { setSaving(false); }
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center text-slate-500 dark:text-slate-400"><div className="text-center"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando despesas...</p></div></div>;

  return <div className="grid w-full min-w-0 max-w-full grid-cols-1 gap-6 overflow-x-hidden xl:grid-cols-[minmax(0,1fr)_340px]">
    <main className="space-y-6 min-w-0">
      <header className="flex items-start justify-between gap-4 flex-wrap">
        <div><h1 className="text-2xl font-semibold">Despesas</h1><p className="text-sm text-slate-500 dark:text-slate-400">Valores registrados na API para seus projetos e imóveis.</p></div>
        <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row">
          <button type="button" onClick={exportCsv} disabled={!filtered.length} className="btn-secondary inline-flex items-center justify-center gap-2 disabled:opacity-50"><Download className="h-4 w-4" />Exportar CSV</button>
          <button type="button" onClick={() => navigate('/app/house-flipper/projetos')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"><Plus className="h-4 w-4" />Lançar em projeto</button>
        </div>
      </header>

      {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300 flex justify-between gap-3"><span>{error}</span><button type="button" onClick={() => load()} className="font-bold underline">Tentar novamente</button></div>}
      {notice && <div role="status" className="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950 p-4 text-sm text-emerald-700 dark:text-emerald-300">{notice}</div>}

      <section aria-label="Resumo das despesas" className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <Kpi label="Total válido" value={money(totals.total)} icon={Wallet2} color="primary" />
        <Kpi label="Pago" value={money(totals.paid)} icon={CheckCircle2} color="emerald" />
        <Kpi label="A pagar" value={money(totals.pending)} icon={Clock} color="amber" />
        <Kpi label="Vencido" value={money(totals.overdue)} icon={AlertTriangle} color="red" />
      </section>

      <section aria-label="Filtros de despesas" className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-4 grid sm:grid-cols-2 xl:grid-cols-5 gap-3">
        <label className="text-xs text-slate-500 dark:text-slate-400">Projeto<select className="input mt-1" value={projectId} onChange={event => setProjectId(event.target.value)}><option value="">Todos</option>{projects.map(project => <option key={project.id} value={project.id}>{project.nome}</option>)}</select></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Categoria<select className="input mt-1" value={categoryId} onChange={event => setCategoryId(event.target.value)}><option value="">Todas</option>{categories.map(category => <option key={category.id} value={category.id}>{category.nome}</option>)}</select></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">De<input className="input mt-1" type="date" value={startDate} max={endDate || undefined} onChange={event => setStartDate(event.target.value)} /></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Até<input className="input mt-1" type="date" value={endDate} min={startDate || undefined} onChange={event => setEndDate(event.target.value)} /></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Buscar<span className="relative block mt-1"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input className="input pl-9" value={search} onChange={event => setSearch(event.target.value)} placeholder="Descrição, projeto..." /></span></label>
      </section>

      <section className="min-w-0 max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <div className="border-b border-slate-200 dark:border-slate-800 px-4 pt-3 flex gap-5 overflow-x-auto" role="tablist" aria-label="Status da despesa">
          {([['todas', 'Todas'], ['pendente', 'A pagar'], ['atrasado', 'Vencidas'], ['pago', 'Pagas'], ['cancelado', 'Canceladas']] as Array<[Tab, string]>).map(([id, label]) => <button key={id} type="button" role="tab" aria-selected={tab === id} onClick={() => setTab(id)} className={`pb-3 whitespace-nowrap border-b-2 text-sm ${tab === id ? 'border-primary-600 text-primary-700 dark:text-primary-300 font-semibold' : 'border-transparent text-slate-500 dark:text-slate-400'}`}>{label} <span className="text-xs">({counts[id]})</span></button>)}
        </div>
        <div className="max-w-full overflow-x-auto overscroll-x-contain">
          <table className="min-w-[52rem] w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-950 text-xs uppercase text-slate-500 dark:text-slate-400"><tr><th className="text-left px-5 py-3">Data</th><th className="text-left px-3 py-3">Descrição</th><th className="text-left px-3 py-3">Categoria</th><th className="text-left px-3 py-3">Projeto</th><th className="text-left px-3 py-3">Pagamento</th><th className="text-right px-3 py-3">Valor</th><th className="text-left px-3 py-3">Status</th><th className="px-3 py-3"><span className="sr-only">Ações</span></th></tr></thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {visible.map(expense => { const info = statusInfo(expense.status_pagamento); const Icon = paymentIcon(expense.forma_pagamento); const project = expense.projeto_id ? projectById.get(expense.projeto_id) : undefined; return <tr key={expense.id} className={selectedId === expense.id ? 'bg-primary-50/60 dark:bg-primary-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'}>
                <td className="px-5 py-3 whitespace-nowrap">{formatDate(expense.data_despesa)}</td><td className="px-3 py-3 font-medium">{expense.descricao}</td><td className="px-3 py-3">{expense.categoria?.nome ?? 'Sem categoria'}</td><td className="px-3 py-3">{project?.nome ?? (expense.projeto_id ? `Projeto #${expense.projeto_id}` : 'Sem projeto')}</td><td className="px-3 py-3"><span className="inline-flex items-center gap-2"><Icon className="h-4 w-4 text-slate-400" />{paymentMethod(expense.forma_pagamento)}</span></td><td className="px-3 py-3 text-right font-semibold whitespace-nowrap">{money(expense.valor)}</td><td className="px-3 py-3"><span className={`px-2 py-1 rounded-full text-xs font-semibold ${info.className}`}>{info.label}</span></td><td className="px-3 py-3"><button type="button" onClick={() => setSelectedId(expense.id)} className="text-primary-600 dark:text-primary-400 font-semibold hover:underline">Detalhes</button></td>
              </tr>; })}
            </tbody>
          </table>
          {!visible.length && <div className="p-10 text-center"><ShoppingCart className="h-9 w-9 mx-auto text-slate-300" /><p className="mt-3 font-semibold">Nenhuma despesa encontrada</p><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Ajuste os filtros ou lance uma despesa em um projeto.</p></div>}
        </div>
        <div className="flex items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400"><span>{filtered.length ? `${(page - 1) * PAGE_SIZE + 1}–${Math.min(page * PAGE_SIZE, filtered.length)} de ${filtered.length}` : '0 resultados'}</span><div className="flex items-center gap-2"><button type="button" aria-label="Página anterior" disabled={page === 1} onClick={() => setPage(value => value - 1)} className="p-2 rounded-lg border disabled:opacity-40"><ChevronLeft className="h-4 w-4" /></button><span>Página {page} de {pageCount}</span><button type="button" aria-label="Próxima página" disabled={page === pageCount} onClick={() => setPage(value => value + 1)} className="p-2 rounded-lg border disabled:opacity-40"><ChevronRight className="h-4 w-4" /></button></div></div>
      </section>
    </main>

    {selected && <ExpenseDetail expense={selected} project={selected.projeto_id ? projectById.get(selected.projeto_id) : undefined} saving={saving} onClose={() => setSelectedId(null)} onMarkPaid={() => markPaid(selected)} />}
  </div>;
}

function Kpi({ label, value, icon: Icon, color }: { label: string; value: string; icon: typeof Wallet2; color: 'primary' | 'emerald' | 'amber' | 'red' }) {
  const classes = { primary: 'bg-primary-50 dark:bg-primary-950 text-primary-600', emerald: 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600', amber: 'bg-amber-50 dark:bg-amber-950 text-amber-600', red: 'bg-red-50 dark:bg-red-950 text-red-600' };
  return <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4"><div className="flex justify-between gap-2"><span className="text-xs text-slate-500 dark:text-slate-400">{label}</span><span className={`p-2 rounded-lg ${classes[color]}`}><Icon className="h-4 w-4" /></span></div><strong className="block text-xl mt-2">{value}</strong></div>;
}

function ExpenseDetail({ expense, project, saving, onClose, onMarkPaid }: { expense: Expense; project?: Project; saving: boolean; onClose: () => void; onMarkPaid: () => void }) {
  const info = statusInfo(expense.status_pagamento);
  const status = normalizePaymentStatus(expense.status_pagamento);
  return <aside aria-label="Detalhes da despesa" className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl self-start xl:sticky xl:top-4 overflow-hidden">
    <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between"><h2 className="font-semibold">Detalhes da despesa</h2><button type="button" aria-label="Fechar detalhes" onClick={onClose}><X className="h-5 w-5 text-slate-400" /></button></div>
    <div className="p-5 space-y-5"><span className={`inline-flex px-2 py-1 rounded-full text-xs font-semibold ${info.className}`}>{info.label}</span><div><h3 className="font-bold">{expense.descricao}</h3><strong className="text-xl block mt-1">{money(expense.valor)}</strong></div><dl className="space-y-3 text-sm"><Detail label="Data da despesa" value={formatDate(expense.data_despesa)} /><Detail label="Data do pagamento" value={formatDate(expense.data_pagamento)} /><Detail label="Categoria" value={expense.categoria?.nome ?? 'Sem categoria'} /><Detail label="Forma de pagamento" value={paymentMethod(expense.forma_pagamento)} /><Detail label="Projeto" value={project?.nome ?? (expense.projeto_id ? `Projeto #${expense.projeto_id}` : 'Sem projeto')} /><Detail label="Imóvel" value={project?.propriedade?.apelido ?? 'Não vinculado'} /><Detail label="Documento" value={expense.documento_numero || 'Não informado'} /><Detail label="Observações" value={expense.observacoes || 'Sem observações'} /></dl></div>
    {status !== 'pago' && status !== 'cancelado' && <div className="p-5 border-t border-slate-200 dark:border-slate-800"><button type="button" disabled={saving} onClick={onMarkPaid} className="w-full bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2 font-semibold disabled:opacity-60 inline-flex justify-center items-center gap-2">{saving && <Loader2 className="h-4 w-4 animate-spin" />}Marcar como paga</button></div>}
  </aside>;
}

function Detail({ label, value }: { label: string; value: string }) { return <div><dt className="text-xs text-slate-500 dark:text-slate-400">{label}</dt><dd className="font-medium mt-0.5 break-words">{value}</dd></div>; }
function formatDate(value?: string | null) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR') : 'Não informada'; }
function paymentMethod(value?: string | null) { const labels: Record<string, string> = { pix: 'PIX', boleto: 'Boleto', cartao: 'Cartão', cartao_credito: 'Cartão de crédito', transferencia: 'Transferência', dinheiro: 'Dinheiro' }; return value ? labels[value.toLowerCase()] ?? value : 'Não informada'; }
function paymentIcon(value?: string | null) { const normalized = String(value ?? '').toLowerCase(); if (normalized.includes('cartao')) return CreditCard; if (normalized === 'boleto') return FileText; if (normalized === 'dinheiro') return Banknote; return ArrowLeftRight; }
function statusInfo(value?: string) { const status = normalizePaymentStatus(value); const map = { pago: { label: 'Pago', className: 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' }, pendente: { label: 'A pagar', className: 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' }, atrasado: { label: 'Vencido', className: 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }, cancelado: { label: 'Cancelado', className: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' } }; return map[status]; }
