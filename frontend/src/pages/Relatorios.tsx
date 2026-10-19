import { useEffect, useRef, useState } from 'react';
import { BarChart2, Building2, CheckCircle2, ChevronLeft, ChevronRight, Download, Loader2, RefreshCw, TrendingUp, Wallet } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import { createLedgerReportCsv, createSummaryReportCsv, ledgerTypeLabel } from '@/lib/reportExport';

type Dashboard = {
  kpis: {
    active_projects: number; total_projects: number; realized_profit: number; sales_revenue: number;
    sold_properties: number; realized_roi: number; total_invested: number;
  };
  performance: Array<{ mes: string; investimento: number; faturamento: number; lucro: number }>;
  composition: Array<{ name: string; valor: number; color: string }>;
  projects: Array<{ id: string; nome: string; numero: string; cidade: string; investido: number; estimado: number; status: string; roi: number | null }>;
};

type LedgerEntry = { id: string; date: string; type: string; description: string; project: string | null; amount: number };
type Ledger = {
  data: LedgerEntry[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  totals: { inflows: number; outflows: number; balance: number };
};
type Project = { id: number; nome: string };

const PAGE_SIZE = 25;
const money = (value: number) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const shortMoney = (value: number) => `R$ ${(Number(value || 0) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} mil`;
const stamp = () => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date());

export default function Relatorios() {
  const projectLoader = useRef(new PaginatedCollection<Project>());
  const ledgerController = useRef<AbortController | undefined>(undefined);
  const [data, setData] = useState<Dashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [projects, setProjects] = useState<Project[]>([]);
  const [ledger, setLedger] = useState<Ledger | null>(null);
  const [ledgerLoading, setLedgerLoading] = useState(true);
  const [ledgerError, setLedgerError] = useState('');
  const [exporting, setExporting] = useState(false);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [projectId, setProjectId] = useState('');
  const [page, setPage] = useState(1);

  function ledgerParams(nextPage: number, perPage = PAGE_SIZE) {
    return {
      page: nextPage,
      per_page: perPage,
      ...(startDate ? { data_inicio: startDate } : {}),
      ...(endDate ? { data_fim: endDate } : {}),
      ...(projectId ? { projeto_id: Number(projectId) } : {}),
    };
  }

  async function loadDashboard() {
    setLoading(true); setError('');
    try { setData((await api.get('/dashboard')).data); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível carregar o relatório.'); setData(null); }
    finally { setLoading(false); }
  }

  async function loadLedger() {
    ledgerController.current?.abort();
    const controller = new AbortController();
    ledgerController.current = controller;
    setLedgerLoading(true); setLedgerError('');
    try {
      const response = await api.get('/reports/ledger', { params: ledgerParams(page), signal: controller.signal });
      if (!controller.signal.aborted) setLedger(response.data);
    } catch (exception: any) {
      if (controller.signal.aborted) return;
      setLedger(null);
      setLedgerError(exception.response?.data?.message ?? (Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || 'Não foi possível carregar a trilha contábil.'));
    } finally {
      if (!controller.signal.aborted) setLedgerLoading(false);
    }
  }

  useEffect(() => { loadDashboard(); }, []);
  useEffect(() => {
    projectLoader.current.load(async (nextPage, perPage, signal) => {
      const response = await api.get('/projetos', { params: { page: nextPage, per_page: perPage }, signal });
      return response.data;
    }).then(items => { if (items) setProjects(items); }).catch(() => {});
    return () => { projectLoader.current.cancel(); ledgerController.current?.abort(); };
  }, []);
  useEffect(() => { loadLedger(); return () => ledgerController.current?.abort(); }, [page, startDate, endDate, projectId]);

  function exportReport() {
    if (!data) return;
    const csv = createSummaryReportCsv({
      generatedAt: stamp(),
      totalInvested: data.kpis.total_invested,
      salesRevenue: data.kpis.sales_revenue,
      realizedProfit: data.kpis.realized_profit,
      realizedRoi: data.kpis.realized_roi,
      totalProjects: data.kpis.total_projects,
      soldProperties: data.kpis.sold_properties,
    });
    downloadCsv(csv, `relatorio-resumo-${new Date().toISOString().slice(0, 10)}.csv`);
  }

  async function exportLedger() {
    setExporting(true); setLedgerError('');
    try {
      const entries: LedgerEntry[] = [];
      let nextPage = 1;
      let lastPage = 1;
      let totals = { inflows: 0, outflows: 0, balance: 0 };
      do {
        const response = await api.get('/reports/ledger', { params: ledgerParams(nextPage, 100) });
        if (!Array.isArray(response.data.data) || !Number.isSafeInteger(response.data.meta?.last_page)) {
          throw new Error('Resposta de paginação inválida.');
        }
        entries.push(...response.data.data);
        totals = response.data.totals;
        lastPage = response.data.meta.last_page;
        nextPage += 1;
      } while (nextPage <= lastPage);
      const selected = projects.find(item => String(item.id) === projectId);
      downloadCsv(createLedgerReportCsv({
        generatedAt: stamp(),
        dataInicio: startDate || 'Todo o histórico',
        dataFim: endDate || 'Todo o histórico',
        projeto: selected?.nome || (projectId ? `Projeto #${projectId}` : 'Todos os projetos'),
        inflows: totals.inflows,
        outflows: totals.outflows,
        balance: totals.balance,
        entries,
      }), `relatorio-trilha-${new Date().toISOString().slice(0, 10)}.csv`);
    } catch (exception: any) {
      setLedgerError(exception.response?.data?.message ?? exception.message ?? 'Não foi possível exportar a trilha filtrada.');
    } finally { setExporting(false); }
  }

  function changeFilter(setter: (value: string) => void, value: string) {
    setter(value);
    setPage(1);
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center text-slate-500 dark:text-slate-400"><div className="text-center"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Calculando indicadores reais...</p></div></div>;
  if (!data) return <div className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-8 text-center text-red-700 dark:text-red-300"><p>{error || 'Relatório indisponível.'}</p><button type="button" onClick={loadDashboard} className="mt-4 font-semibold inline-flex items-center gap-2"><RefreshCw className="h-4 w-4" />Tentar novamente</button></div>;

  const compositionTotal = data.composition.reduce((sum, item) => sum + Number(item.valor || 0), 0);
  const lastPage = Math.max(1, ledger?.meta.last_page ?? 1);
  return <div className="space-y-6">
    <header className="flex items-start justify-between gap-4 flex-wrap"><div><h1 className="text-2xl font-semibold">Relatórios</h1><p className="text-sm text-slate-500 dark:text-slate-400">Indicadores acumulados da conta e trilha contábil filtrável pelos mesmos registros persistidos.</p></div><div className="flex gap-2 flex-wrap"><button type="button" onClick={() => { loadDashboard(); loadLedger(); }} className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 text-sm font-semibold inline-flex items-center gap-2"><RefreshCw className="h-4 w-4" />Atualizar</button><button type="button" onClick={exportReport} className="bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2 text-sm font-semibold inline-flex items-center gap-2"><Download className="h-4 w-4" />Exportar resumo</button></div></header>
    {error && <div role="alert" className="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 p-4 text-sm text-amber-700 dark:text-amber-300">{error}</div>}
    <p className="text-xs text-slate-500 dark:text-slate-400">O painel permanece acumulado. A trilha abaixo usa o período e o projeto selecionados; vendas consideram estados fechada e escriturada.</p>
    <section aria-label="Indicadores financeiros" className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
      <Metric icon={Wallet} label="Total investido" value={money(data.kpis.total_invested)} />
      <Metric icon={TrendingUp} label="Faturamento de vendas" value={money(data.kpis.sales_revenue)} />
      <Metric icon={BarChart2} label="Lucro realizado" value={money(data.kpis.realized_profit)} />
      <Metric icon={CheckCircle2} label="ROI realizado" value={`${Number(data.kpis.realized_roi || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`} />
      <Metric icon={Building2} label="Projetos cadastrados" value={String(data.kpis.total_projects)} />
      <Metric icon={Building2} label="Projetos em andamento" value={String(data.kpis.active_projects)} />
      <Metric icon={CheckCircle2} label="Imóveis vendidos" value={String(data.kpis.sold_properties)} />
    </section>

    <div className="grid xl:grid-cols-[minmax(0,1fr)_420px] gap-5">
      <ChartCard title="Investimento, faturamento e lucro — últimos seis meses">
        <ResponsiveContainer width="100%" height="100%"><LineChart data={data.performance} margin={{ top: 8, right: 15, left: 5, bottom: 5 }}><CartesianGrid stroke="#cbd5e1" strokeOpacity={0.3} strokeDasharray="3 3" vertical={false} /><XAxis dataKey="mes" tick={{ fontSize: 11, fill: '#94a3b8' }} /><YAxis tickFormatter={shortMoney} tick={{ fontSize: 11, fill: '#94a3b8' }} width={70} /><Tooltip formatter={(value: number) => money(value)} /><Legend /><Line type="monotone" dataKey="investimento" name="Investimento" stroke="#ef4444" strokeWidth={2} /><Line type="monotone" dataKey="faturamento" name="Faturamento" stroke="#10b981" strokeWidth={2} /><Line type="monotone" dataKey="lucro" name="Lucro" stroke="#7c3aed" strokeWidth={2} /></LineChart></ResponsiveContainer>
      </ChartCard>
      <ChartCard title="Composição acumulada do investimento">
        {compositionTotal > 0 ? <div className="grid grid-cols-[180px_1fr] items-center h-full"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={data.composition} dataKey="valor" nameKey="name" innerRadius={45} outerRadius={75} stroke="none">{data.composition.map(item => <Cell key={item.name} fill={item.color} />)}</Pie><Tooltip formatter={(value: number) => money(value)} /></PieChart></ResponsiveContainer><div className="space-y-3 text-xs">{data.composition.map(item => <div key={item.name}><div className="flex justify-between gap-3"><span className="text-slate-500 dark:text-slate-400">{item.name}</span><strong>{money(item.valor)}</strong></div><div className="h-1.5 bg-slate-100 dark:bg-slate-800 rounded mt-1"><div className="h-full rounded" style={{ width: `${compositionTotal ? item.valor / compositionTotal * 100 : 0}%`, backgroundColor: item.color }} /></div></div>)}</div></div> : <Empty message="Nenhum investimento registrado." />}
      </ChartCard>
    </div>

    <ChartCard title="Lucro realizado por mês"><ResponsiveContainer width="100%" height="100%"><BarChart data={data.performance}><CartesianGrid stroke="#cbd5e1" strokeOpacity={0.3} strokeDasharray="3 3" vertical={false} /><XAxis dataKey="mes" tick={{ fontSize: 11, fill: '#94a3b8' }} /><YAxis tickFormatter={shortMoney} tick={{ fontSize: 11, fill: '#94a3b8' }} width={70} /><Tooltip formatter={(value: number) => money(value)} /><Bar dataKey="lucro" name="Lucro" fill="#7c3aed" radius={[5, 5, 0, 0]} /></BarChart></ResponsiveContainer></ChartCard>

    <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"><div className="p-5 border-b border-slate-200 dark:border-slate-800"><h2 className="font-bold">Projetos em andamento mais recentes</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Até cinco projetos retornados pelo painel financeiro, sem filtro da trilha.</p></div>{data.projects.length ? <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="bg-slate-50 dark:bg-slate-950 text-left text-xs uppercase text-slate-500 dark:text-slate-400"><th className="px-5 py-3">Projeto</th><th className="px-3 py-3">Cidade</th><th className="px-3 py-3">Custo da obra</th><th className="px-3 py-3">Venda alvo</th><th className="px-3 py-3">ROI projetado</th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{data.projects.map(project => <tr key={project.id}><td className="px-5 py-3"><strong>{project.nome}</strong><span className="block text-xs text-slate-500 dark:text-slate-400">{project.numero}</span></td><td className="px-3 py-3">{project.cidade}</td><td className="px-3 py-3">{money(project.investido)}</td><td className="px-3 py-3">{money(project.estimado)}</td><td className="px-3 py-3">{project.roi === null ? 'Indisponível' : `${Number(project.roi).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`}</td></tr>)}</tbody></table></div> : <Empty message="Nenhum projeto em andamento." />}</section>

    <section className="space-y-4" aria-label="Trilha contábil">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div><h2 className="text-lg font-semibold">Trilha contábil</h2><p className="text-sm text-slate-500 dark:text-slate-400">Aquisições, despesas não canceladas e vendas finais da conta, no período e projeto escolhidos.</p></div>
        <button type="button" onClick={exportLedger} disabled={exporting || !ledger?.meta.total} className="border border-slate-300 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-semibold inline-flex items-center gap-2 disabled:opacity-50"><Download className="h-4 w-4" />{exporting ? 'Exportando...' : 'Exportar trilha filtrada'}</button>
      </div>
      <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-4 grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
        <label className="text-xs text-slate-500 dark:text-slate-400">Projeto<select className="input mt-1" value={projectId} onChange={event => changeFilter(setProjectId, event.target.value)}><option value="">Todos</option>{projects.map(project => <option key={project.id} value={project.id}>{project.nome}</option>)}</select></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">De<input className="input mt-1" type="date" value={startDate} max={endDate || undefined} onChange={event => changeFilter(setStartDate, event.target.value)} /></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Até<input className="input mt-1" type="date" value={endDate} min={startDate || undefined} onChange={event => changeFilter(setEndDate, event.target.value)} /></label>
        <p className="text-xs text-slate-500 dark:text-slate-400 self-end">Totais e CSV usam o mesmo conjunto filtrado, não apenas a página visível.</p>
      </div>
      {ledgerError && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300 flex justify-between gap-3"><span>{ledgerError}</span><button type="button" onClick={loadLedger} className="font-bold underline">Tentar novamente</button></div>}
      <div className="grid sm:grid-cols-3 gap-3">
        <Metric icon={TrendingUp} label="Entradas filtradas" value={money(ledger?.totals.inflows ?? 0)} />
        <Metric icon={Wallet} label="Saídas filtradas" value={money(ledger?.totals.outflows ?? 0)} />
        <Metric icon={BarChart2} label="Saldo filtrado" value={money(ledger?.totals.balance ?? 0)} />
      </div>
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
        {ledgerLoading ? <div className="p-10 text-center text-sm text-slate-500 dark:text-slate-400"><Loader2 className="h-6 w-6 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando trilha contábil...</p></div> : <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead><tr className="bg-slate-50 dark:bg-slate-950 text-left text-xs uppercase text-slate-500 dark:text-slate-400"><th className="px-5 py-3">Data</th><th className="px-3 py-3">Tipo</th><th className="px-3 py-3">Descrição</th><th className="px-3 py-3">Projeto</th><th className="px-3 py-3 text-right">Valor</th></tr></thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{ledger?.data.map(entry => <tr key={entry.id}><td className="px-5 py-3 whitespace-nowrap">{formatDate(entry.date)}</td><td className="px-3 py-3">{ledgerTypeLabel(entry.type)}</td><td className="px-3 py-3 font-medium">{entry.description}</td><td className="px-3 py-3">{entry.project || 'Sem projeto'}</td><td className={`px-3 py-3 text-right whitespace-nowrap font-semibold ${entry.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'}`}>{money(entry.amount)}</td></tr>)}</tbody>
            </table>
            {!ledger?.data.length && <Empty message="Nenhum lançamento no período e projeto selecionados." />}
          </div>
          <div className="flex items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
            <span>{ledger?.meta.total ? `${(page - 1) * PAGE_SIZE + 1}–${Math.min(page * PAGE_SIZE, ledger.meta.total)} de ${ledger.meta.total}` : '0 resultados'}</span>
            <div className="flex items-center gap-2">
              <button type="button" aria-label="Página anterior" disabled={page <= 1} onClick={() => setPage(value => value - 1)} className="p-2 rounded-lg border disabled:opacity-40"><ChevronLeft className="h-4 w-4" /></button>
              <span>Página {page} de {lastPage}</span>
              <button type="button" aria-label="Próxima página" disabled={page >= lastPage} onClick={() => setPage(value => value + 1)} className="p-2 rounded-lg border disabled:opacity-40"><ChevronRight className="h-4 w-4" /></button>
            </div>
          </div>
        </>}
      </div>
    </section>
  </div>;
}

function downloadCsv(csv: string, filename: string) {
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}
function formatDate(value: string) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR');
}
function Metric({ icon: Icon, label, value }: { icon: typeof Wallet; label: string; value: string }) { return <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3"><span className="p-3 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400"><Icon className="h-5 w-5" /></span><div className="min-w-0"><span className="text-xs text-slate-500 dark:text-slate-400">{label}</span><strong className="block text-lg truncate">{value}</strong></div></div>; }
function ChartCard({ title, children }: { title: string; children: React.ReactNode }) { return <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-5"><h2 className="font-bold text-sm mb-4">{title}</h2><div className="h-72">{children}</div></section>; }
function Empty({ message }: { message: string }) { return <div className="p-10 text-center text-sm text-slate-500 dark:text-slate-400">{message}</div>; }
