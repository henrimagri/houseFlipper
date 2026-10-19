import { useEffect, useState } from 'react';
import { ArrowRight, BriefcaseBusiness, CircleDollarSign, FileBarChart, Loader2, ReceiptText, RefreshCw, TrendingDown, TrendingUp, WalletCards } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { compositionShares, financialPosition } from '@/lib/financeView';

type DashboardData = {
  kpis: { active_projects: number; total_projects: number; realized_profit: number; sales_revenue: number; sold_properties: number; realized_roi: number; total_invested: number };
  performance: Array<{ mes: string; investimento: number; faturamento: number; lucro: number }>;
  composition: Array<{ name: string; valor: number; color: string }>;
  projects: Array<{ id: string; nome: string; numero: string; cidade: string; investido: number; estimado: number; status: string; roi: number | null }>;
  activities: Array<{ titulo: string; desc: string; tempo: string }>;
};

const money = (value: unknown) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = (value: unknown) => `${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

export default function Financeiro() {
  const navigate = useNavigate();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  async function load(silent = false, signal?: AbortSignal) {
    if (silent) setRefreshing(true); else setLoading(true);
    setError('');
    try {
      const response = await api.get<DashboardData>('/dashboard', { signal });
      setData(response.data);
    } catch (exception: any) {
      if (!signal?.aborted) setError(exception.response?.data?.message || 'Não foi possível carregar a posição financeira.');
    } finally {
      if (!signal?.aborted) { setLoading(false); setRefreshing(false); }
    }
  }

  useEffect(() => {
    const controller = new AbortController();
    void load(false, controller.signal);
    return () => controller.abort();
  }, []);

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-slate-500 dark:text-slate-400"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Calculando a posição financeira...</p></div></div>;
  if (!data) return <div role="alert" className="rounded-2xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-10 text-center text-red-800 dark:text-red-200"><p>{error || 'Posição financeira indisponível.'}</p><button type="button" onClick={() => void load()} className="mt-4 inline-flex items-center gap-2 font-bold underline underline-offset-2"><RefreshCw className="h-4 w-4" /> Tentar novamente</button></div>;

  const position = financialPosition(data.kpis);
  const composition = compositionShares(data.composition);
  const hasMovement = position.totalInvested !== 0 || position.salesRevenue !== 0 || data.activities.length > 0;

  return <div className="space-y-6">
    <header className="flex flex-col lg:flex-row lg:items-end justify-between gap-4"><div><div className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Controle financeiro</div><h1 className="text-3xl font-black mt-2 text-slate-900 dark:text-slate-100">Financeiro</h1><p className="text-slate-500 dark:text-slate-400 mt-1">Visão acumulada dos investimentos, vendas e resultados persistidos na sua conta.</p></div><button type="button" onClick={() => void load(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-bold w-fit disabled:opacity-60"><RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Atualizar</button></header>

    {error && <div role="alert" className="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 p-4 text-sm text-amber-900 dark:text-amber-200">{error} Os últimos dados carregados permanecem visíveis.</div>}
    <p className="text-xs text-slate-500 dark:text-slate-400">Valores acumulados. Lucro e ROI realizados consideram apenas vendas fechadas ou escrituradas; despesas canceladas são excluídas.</p>

    <section aria-label="Indicadores financeiros" className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <Metric icon={WalletCards} label="Total investido" value={money(position.totalInvested)} detail="Aquisições com data de compra e despesas não canceladas. Prospecção não entra." />
      <Metric icon={CircleDollarSign} label="Faturamento em vendas" value={money(position.salesRevenue)} detail={`${position.soldProperties} ${position.soldProperties === 1 ? 'imóvel vendido' : 'imóveis vendidos'}`} />
      <Metric icon={position.realizedProfit < 0 ? TrendingDown : TrendingUp} label="Lucro realizado" value={money(position.realizedProfit)} detail="Após custos dos imóveis vendidos" tone={position.realizedProfit < 0 ? 'danger' : 'default'} />
      <Metric icon={TrendingUp} label="ROI realizado" value={percent(position.realizedRoi)} detail="Lucro sobre custo realizado" tone={position.realizedRoi < 0 ? 'danger' : 'default'} />
    </section>

    <section aria-label="Ações financeiras" className="grid md:grid-cols-3 gap-4">
      <Action icon={ReceiptText} title="Despesas" description="Consulte, filtre, exporte e atualize pagamentos." onClick={() => navigate('/despesas')} />
      <Action icon={BriefcaseBusiness} title="Projetos" description="Lance despesas e acompanhe orçamentos de obra." onClick={() => navigate('/projetos')} />
      <Action icon={FileBarChart} title="Relatórios" description="Veja gráficos acumulados e exporte o resumo." onClick={() => navigate('/relatorios')} />
    </section>

    {!hasMovement && <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 p-10 text-center"><WalletCards className="h-10 w-10 mx-auto text-primary-600 dark:text-primary-400" /><h2 className="font-black text-lg mt-4">Nenhuma movimentação registrada</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-2">Cadastre um imóvel/projeto e registre despesas para formar a posição financeira.</p><button type="button" onClick={() => navigate('/projetos?novo=1')} className="mt-5 rounded-xl bg-primary-600 text-white px-5 py-3 font-bold">Criar projeto</button></div>}

    <div className="grid xl:grid-cols-2 gap-5">
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div className="flex justify-between gap-3"><div><h2 className="font-bold">Composição do investimento</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Valores acumulados por origem.</p></div><strong className="text-sm">{money(position.totalInvested)}</strong></div>{composition.some(item => item.valor > 0) ? <div className="space-y-4 mt-6">{composition.map(item => <div key={item.name}><div className="flex justify-between gap-3 text-sm"><span className="text-slate-600 dark:text-slate-300">{item.name}</span><b>{money(item.valor)}</b></div><div className="h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mt-2"><div className="h-full rounded-full" style={{ width: `${item.share}%`, backgroundColor: item.color }} /></div><p className="text-right text-[11px] text-slate-500 mt-1">{percent(item.share)}</p></div>)}</div> : <Empty message="Nenhum investimento para compor." />}</section>
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div><h2 className="font-bold">Últimos seis meses</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Movimentação por competência.</p></div><div className="overflow-x-auto mt-4"><table className="w-full text-sm"><thead><tr className="text-left text-xs uppercase text-slate-500 dark:text-slate-400"><th className="py-2">Mês</th><th className="py-2 text-right">Investimento</th><th className="py-2 text-right">Vendas</th><th className="py-2 text-right">Resultado</th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{data.performance.map(month => <tr key={month.mes}><td className="py-3 font-semibold">{month.mes}</td><td className="py-3 text-right">{money(month.investimento)}</td><td className="py-3 text-right">{money(month.faturamento)}</td><td className={`py-3 text-right font-bold ${Number(month.lucro) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'}`}>{money(month.lucro)}</td></tr>)}</tbody></table></div></section>
    </div>

    <div className="grid xl:grid-cols-2 gap-5">
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden"><div className="p-5 border-b border-slate-100 dark:border-slate-800"><h2 className="font-bold">Projetos em andamento</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{position.activeProjects} de {position.totalProjects} projetos cadastrados.</p></div>{data.projects.length ? <div className="divide-y divide-slate-100 dark:divide-slate-800">{data.projects.map(project => <button key={project.id} type="button" onClick={() => navigate('/projetos')} className="w-full text-left p-4 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-between gap-3"><div><b>{project.nome}</b><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{project.cidade} · Obra {money(project.investido)}</p></div><span className="text-sm font-bold">{project.roi === null ? 'ROI indisponível' : `ROI ${percent(project.roi)}`}</span></button>)}</div> : <Empty message="Nenhum projeto em andamento." />}</section>
      <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden"><div className="p-5 border-b border-slate-100 dark:border-slate-800"><h2 className="font-bold">Atividades financeiras recentes</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Até cinco despesas mais recentes.</p></div>{data.activities.length ? <div className="divide-y divide-slate-100 dark:divide-slate-800">{data.activities.map((activity, index) => <div key={`${activity.desc}-${activity.tempo}-${index}`} className="p-4"><div className="flex justify-between gap-3"><b className="text-sm">{activity.titulo}</b><span className="text-xs text-slate-500 dark:text-slate-400">{activity.tempo}</span></div><p className="text-sm text-slate-600 dark:text-slate-300 mt-1 break-words">{activity.desc}</p></div>)}</div> : <Empty message="Nenhuma despesa registrada." />}</section>
    </div>
  </div>;
}

function Metric({ icon: Icon, label, value, detail, tone = 'default' }: { icon: typeof WalletCards; label: string; value: string; detail: string; tone?: 'default' | 'danger' }) { return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div className="flex justify-between gap-3"><div><p className="text-sm text-slate-500 dark:text-slate-400">{label}</p><p className={`text-2xl font-black mt-2 ${tone === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'}`}>{value}</p></div><span className="h-10 w-10 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="h-5 w-5" /></span></div><p className="text-xs text-slate-500 dark:text-slate-400 mt-2">{detail}</p></div>; }
function Action({ icon: Icon, title, description, onClick }: { icon: typeof ReceiptText; title: string; description: string; onClick: () => void }) { return <button type="button" onClick={onClick} className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 text-left hover:border-primary-300 dark:hover:border-primary-700 flex items-center gap-4"><span className="h-11 w-11 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0"><Icon className="h-5 w-5" /></span><span className="min-w-0 flex-1"><b className="block">{title}</b><span className="block text-xs text-slate-500 dark:text-slate-400 mt-1">{description}</span></span><ArrowRight className="h-5 w-5 text-slate-400" /></button>; }
function Empty({ message }: { message: string }) { return <p className="p-8 text-center text-sm text-slate-500 dark:text-slate-400">{message}</p>; }
