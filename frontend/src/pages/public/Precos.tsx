import { useMemo, useState, type ComponentType } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Building2, Check, Hammer, HomeIcon, ShieldCheck, Sparkles } from 'lucide-react';
import PublicSeo from '@/components/public/PublicSeo';
import PricingCards from '@/components/public/PricingCards';
import { usePublicCatalog } from '@/lib/publicCatalog';
import { ProductSlug } from '@/lib/products';

const tabs: { slug: ProductSlug; label: string; icon: ComponentType<{ className?: string }> }[] = [
  { slug: 'corretor', label: 'Corretor', icon: HomeIcon },
  { slug: 'imobiliaria', label: 'Imobiliária', icon: Building2 },
  { slug: 'house_flipper', label: 'House Flipper', icon: Hammer },
];

export default function Precos() {
  const { catalog, error } = usePublicCatalog();
  const [params] = useSearchParams();
  const initial = (params.get('produto') as ProductSlug) || 'corretor';
  const [slug, setSlug] = useState<ProductSlug>(tabs.some((tab) => tab.slug === initial) ? initial : 'corretor');
  const product = useMemo(() => catalog?.products.find((item) => item.slug === slug), [catalog, slug]);

  return <div className="overflow-hidden">
    <PublicSeo title="Planos e preços" description="Planos oficiais de Corretor, Imobiliária e House Flipper, com teste de 7 dias por produto." path="/precos" />
    <section className="relative border-b border-slate-200/70 bg-white py-16 dark:border-slate-800 dark:bg-slate-950 sm:py-20">
      <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(circle_at_20%_12%,rgba(124,58,237,.16),transparent_34%),radial-gradient(circle_at_82%_12%,rgba(6,182,212,.13),transparent_32%)]" />
      <div className="relative mx-auto max-w-7xl px-4 text-center sm:px-6">
        <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase tracking-[.16em] text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"><Sparkles className="size-3.5" />7 dias para experimentar</span>
        <h1 className="mx-auto mt-6 max-w-4xl text-4xl font-black tracking-[-.04em] text-slate-950 dark:text-white sm:text-5xl">Escolha o plano para a sua forma de trabalhar.</h1>
        <p className="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">Teste um produto gratuitamente durante 7 dias. Cada assinatura é independente e você mantém controle sobre o que deseja ativar.</p>
        <div className="mt-7 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{['Sem cobrança no teste', 'Um teste por produto', 'Cancelamento independente'].map(item => <span key={item} className="inline-flex items-center gap-2"><Check className="size-4 text-emerald-500" />{item}</span>)}</div>
      </div>
    </section>

    <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
      {error && <p role="alert" className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{error}</p>}
      <div role="tablist" aria-label="Escolha o produto" className="mx-auto grid max-w-2xl grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-3">
        {tabs.map((tab) => { const Icon = tab.icon; const selected = slug === tab.slug; return <button key={tab.slug} role="tab" aria-selected={selected} type="button" onClick={() => setSlug(tab.slug)} className={`inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black transition ${selected ? 'bg-slate-950 text-white shadow-lg dark:bg-white dark:text-slate-950' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'}`}><Icon className="size-4" />{tab.label}</button>; })}
      </div>
      <div className="mt-10">{product ? <PricingCards product={product} billingCycles={catalog?.billing_cycles} /> : !error && <div role="status" className="grid gap-5 md:grid-cols-2"><div className="h-96 animate-pulse rounded-[2rem] bg-slate-200 dark:bg-slate-800" /><div className="h-96 animate-pulse rounded-[2rem] bg-slate-200 dark:bg-slate-800" /></div>}</div>
      <div className="mt-8 flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"><ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary-500" /><p><strong className="text-slate-900 dark:text-white">Contratação transparente.</strong> O teste não ativa outros produtos automaticamente. Planos pagos são liberados somente após confirmação da contratação.</p></div>
    </section>
  </div>;
}
