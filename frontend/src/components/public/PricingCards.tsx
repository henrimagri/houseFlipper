import { useState, type ComponentType } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, Bot, CalendarDays, Camera, Check, CreditCard, MapPinned, Search, ShieldCheck, Sparkles } from 'lucide-react';
import { FEATURE_DESCRIPTIONS, FEATURE_LABELS, PublicCatalog, PublicProduct } from '@/lib/publicCatalog';
import { getAuthToken } from '@/lib/authToken';

type Cycle = 'monthly' | 'quarterly' | 'annual';
const fallbackCycles: Record<Cycle, { label: string; months: number; discount_percent: number }> = {
  monthly: { label: 'Mensal', months: 1, discount_percent: 0 },
  quarterly: { label: 'Trimestral', months: 3, discount_percent: 10 },
  annual: { label: 'Anual', months: 12, discount_percent: 20 },
};

function money(value: number, currency: string) {
  const hasCents = value % 100 !== 0;
  return (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: currency || 'BRL', minimumFractionDigits: hasCents ? 2 : 0, maximumFractionDigits: 2 });
}

function featureIcon(feature: string): ComponentType<{ className?: string }> {
  if (feature.includes('map')) return MapPinned;
  if (feature.includes('photo') || feature.includes('3d')) return Camera;
  if (feature.includes('search')) return Search;
  if (feature.includes('conversation') || feature.includes('recommendation')) return Bot;
  return Check;
}

export default function PricingCards({ product, billingCycles }: { product: PublicProduct; billingCycles?: PublicCatalog['billing_cycles'] }) {
  const token = getAuthToken();
  const [cycle, setCycle] = useState<Cycle>('monthly');
  const cycles = (Object.keys(fallbackCycles) as Cycle[]).map(id => ({ id, ...(billingCycles?.[id] ?? fallbackCycles[id]) }));
  const selectedCycle = cycles.find(item => item.id === cycle)!;
  const included = product.features.slice(0, 7);

  return <div className="grid items-stretch gap-5 md:grid-cols-2">
    {product.plans.map((plan) => {
      const trial = plan.billing_interval === 'trial' || plan.price === 0;
      const href = trial
        ? (token ? `/app/produtos?produto=${product.slug}` : `/cadastro?produto=${product.slug}&plano=${plan.slug}`)
        : (token ? `/app/produtos?produto=${product.slug}&plano=${plan.slug}&ciclo=${cycle}&intent=payment` : `/cadastro?produto=${product.slug}&plano=${plan.slug}&ciclo=${cycle}&intent=payment`);
      const originalTotal = plan.price * selectedCycle.months;
      const cycleTotal = Math.round(originalTotal * (1 - selectedCycle.discount_percent / 100));
      const monthlyEquivalent = Math.round(cycleTotal / selectedCycle.months);
      const savings = originalTotal - cycleTotal;

      return <article key={plan.slug} data-pricing-plan={plan.slug} className={`relative flex min-h-[520px] flex-col overflow-hidden rounded-[2rem] border p-6 shadow-xl sm:p-8 ${trial ? 'border-primary-500/30 bg-slate-950 text-white shadow-primary-950/20' : 'border-slate-200 bg-white text-slate-950 shadow-slate-900/5 dark:border-white/10 dark:bg-slate-900 dark:text-white'}`}>
        {trial && <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(circle_at_85%_8%,rgba(6,182,212,.22),transparent_32%),radial-gradient(circle_at_12%_88%,rgba(124,58,237,.2),transparent_36%)]" />}
        <div className="relative flex items-start justify-between gap-3"><span className={`grid size-11 place-items-center rounded-2xl ${trial ? 'bg-white/10 text-cyan-300' : 'bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300'}`}>{trial ? <CalendarDays className="size-5" /> : <CreditCard className="size-5" />}</span>{trial ? <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-black text-emerald-300"><Sparkles className="size-3.5" />Recomendado para começar</span> : <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">Pagamento recorrente</span>}</div>
        <div className="relative mt-6"><p className={`text-xs font-black uppercase tracking-[.18em] ${trial ? 'text-cyan-300' : 'text-primary-600 dark:text-primary-300'}`}>{product.label}</p><h2 className="mt-2 text-2xl font-black tracking-tight">{trial ? 'Teste gratuito' : product.label}</h2></div>
        <div className="relative mt-5 flex items-end gap-2"><strong className="text-4xl font-black tracking-[-.04em]">{trial ? 'R$ 0' : money(monthlyEquivalent, plan.currency)}</strong><span className={`pb-1 text-sm ${trial ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'}`}>{trial ? `por ${product.trial_days} dias` : '/ mês'}</span></div>
        {!trial && <div className="relative mt-5"><p className="mb-2 text-xs font-black uppercase tracking-[.14em] text-slate-500 dark:text-slate-400">Período de pagamento</p><div className="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">{cycles.map(item => <button key={item.id} type="button" onClick={() => setCycle(item.id)} className={`flex min-h-11 flex-col items-center justify-center rounded-lg px-1.5 py-1.5 text-xs font-bold transition ${cycle === item.id ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white' : 'text-slate-500 dark:text-slate-300'}`}><span>{item.label}</span>{item.discount_percent > 0 && <span className={`text-[10px] font-black ${cycle === item.id ? 'text-emerald-600 dark:text-emerald-400' : 'text-emerald-600 dark:text-emerald-400'}`}>-{item.discount_percent}%</span>}</button>)}</div><p className="mt-2 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">{cycle === 'monthly' ? 'Cobrança mensal, sem fidelidade.' : <><strong className="text-emerald-600 dark:text-emerald-400">Economize {selectedCycle.discount_percent}% ({money(savings, plan.currency)})</strong><br />{money(cycleTotal, plan.currency)} cobrados no plano {selectedCycle.label.toLowerCase()}.</>}</p></div>}
        <p className={`relative mt-4 text-sm leading-6 ${trial ? 'text-slate-300' : 'text-slate-600 dark:text-slate-300'}`}>{trial ? `Explore os recursos de ${product.label} durante ${product.trial_days} dias, sem cobrança e sem ativar os outros produtos.` : 'Escolha a periodicidade e continue para o ambiente seguro de pagamento. A ativação ocorre após a confirmação do provedor.'}</p>
        <div className={`relative my-6 border-t ${trial ? 'border-white/10' : 'border-slate-200 dark:border-white/10'}`} />
        <p className={`relative text-xs font-black uppercase tracking-[.14em] ${trial ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'}`}>Inclui neste produto</p>
        <ul className="relative mt-4 flex-1 space-y-3">{included.map(feature => { const Icon = featureIcon(feature); return <li key={feature} className="flex gap-3"><span className={`mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg ${trial ? 'bg-cyan-400/10 text-cyan-300' : 'bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300'}`}><Icon className="size-4" /></span><span><strong className={`block text-sm ${trial ? 'text-white' : 'text-slate-900 dark:text-white'}`}>{FEATURE_LABELS[feature] ?? feature.replace(/_/g, ' ')}</strong><span className={`mt-0.5 block text-xs leading-5 ${trial ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'}`}>{FEATURE_DESCRIPTIONS[feature] ?? 'Recurso disponível conforme as permissões deste produto.'}</span></span></li>; })}</ul>
        <Link to={href} className={`relative mt-7 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-black transition hover:-translate-y-0.5 ${trial ? 'bg-white text-slate-950 shadow-lg shadow-black/20' : 'bg-primary-600 text-white shadow-lg shadow-primary-900/20'}`}>{trial ? (token ? 'Iniciar meus 7 dias' : 'Criar conta e testar 7 dias') : 'Continuar para pagamento'}<ArrowRight className="size-4" /></Link>
        <p className={`relative mt-3 flex items-center justify-center gap-1.5 text-center text-xs ${trial ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'}`}><ShieldCheck className="size-3.5" />{trial ? 'Sem cobrança durante o teste' : 'Pagamento liberado somente para conta verificada'}</p>
      </article>;
    })}
  </div>;
}
