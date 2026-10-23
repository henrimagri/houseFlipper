import { Link } from 'react-router-dom';
import {
  ArrowRight, Bot, BriefcaseBusiness, Building2, Camera, ChartNoAxesCombined,
  Check, FileText, Globe2, Hammer, HomeIcon, LayoutGrid, MapPinned, Megaphone,
  Search, Sparkles, Users, type LucideIcon,
} from 'lucide-react';
import PublicSeo from '@/components/public/PublicSeo';
import { FEATURE_LABELS, usePublicCatalog } from '@/lib/publicCatalog';
import type { ProductSlug } from '@/lib/products';

const productStyle: Record<ProductSlug, { icon: LucideIcon; gradient: string; glow: string }> = {
  corretor: { icon: HomeIcon, gradient: 'from-primary-700 to-primary-900', glow: 'bg-primary-500/5' },
  imobiliaria: { icon: Building2, gradient: 'from-primary-700 to-primary-900', glow: 'bg-primary-500/5' },
  house_flipper: { icon: Hammer, gradient: 'from-primary-700 to-primary-900', glow: 'bg-primary-500/5' },
};

function featureIcon(feature: string): LucideIcon {
  if (feature.includes('map')) return MapPinned;
  if (feature.includes('crm') || feature.includes('lead') || feature.includes('team')) return Users;
  if (feature.includes('site') || feature.includes('listing')) return Globe2;
  if (feature.includes('photo') || feature.includes('3d')) return Camera;
  if (feature.includes('financial') || feature.includes('investment') || feature.includes('dashboard')) return ChartNoAxesCombined;
  if (feature.includes('renovation')) return Hammer;
  if (feature.includes('contract') || feature.includes('proposal')) return FileText;
  if (feature.includes('marketing')) return Megaphone;
  if (feature.includes('search') || feature.includes('recommendation')) return Search;
  if (feature.includes('ai') || feature.includes('conversation')) return Bot;
  return BriefcaseBusiness;
}

export default function Funcionalidades() {
  const { catalog, error } = usePublicCatalog();
  const total = catalog?.products.reduce((sum, product) => sum + product.features.length, 0) ?? 0;

  return <div className="overflow-hidden">
    <PublicSeo title="Funcionalidades" description="Recursos realmente cadastrados em Corretor, Imobiliária e House Flipper." path="/funcionalidades" />
    <section className="relative border-b border-slate-200/70 bg-white py-16 dark:border-slate-800 dark:bg-slate-950 sm:py-20">
      <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(circle_at_18%_10%,rgba(124,58,237,.14),transparent_34%),radial-gradient(circle_at_84%_18%,rgba(6,182,212,.12),transparent_30%)]" />
      <div className="relative mx-auto max-w-7xl px-4 sm:px-6">
        <span className="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-black uppercase tracking-[.16em] text-primary-700 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-200"><Sparkles className="size-3.5" />Recursos por produto</span>
        <div className="mt-6 grid items-end gap-8 lg:grid-cols-[1fr_auto]">
          <div><h1 className="max-w-4xl text-4xl font-black tracking-[-.04em] text-slate-950 dark:text-white sm:text-5xl">Tudo o que sua operação precisa, no produto certo.</h1><p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">Compare os módulos disponíveis em cada produto. A lista abaixo vem diretamente do catálogo oficial da plataforma.</p></div>
          <div className="flex gap-3"><div className="rounded-2xl border bg-white/80 px-5 py-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80"><strong className="block text-2xl font-black">3</strong><span className="text-xs text-slate-500">produtos</span></div><div className="rounded-2xl border bg-white/80 px-5 py-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80"><strong className="block text-2xl font-black">{total || '—'}</strong><span className="text-xs text-slate-500">recursos listados</span></div></div>
        </div>
      </div>
    </section>
    <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20">
      {error && <p role="alert" className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{error}</p>}
      {!catalog && !error && <div role="status" className="grid gap-6 lg:grid-cols-3">{Array.from({ length: 3 }).map((_, index) => <div key={index} className="h-96 animate-pulse rounded-[2rem] bg-slate-200 dark:bg-slate-800" />)}</div>}
      <div className="grid items-start gap-6 lg:grid-cols-3">
        {catalog?.products.map((product) => {
          const style = productStyle[product.slug]; const ProductIcon = style.icon;
          return <article key={product.slug} data-product-features={product.slug} className="group relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-white/10 dark:bg-slate-900 dark:shadow-black/20 sm:p-7">
            <div aria-hidden="true" className={`absolute -right-16 -top-16 size-44 rounded-full blur-3xl ${style.glow}`} />
            <div className="relative flex items-start justify-between gap-4"><span className={`grid size-12 place-items-center rounded-2xl bg-gradient-to-br ${style.gradient} text-white shadow-lg`}><ProductIcon className="size-5" /></span><span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">{product.features.length} recursos</span></div>
            <div className="relative mt-6"><p className="text-xs font-black uppercase tracking-[.18em] text-primary-600 dark:text-primary-300">Produto</p><h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">{product.label}</h2><p className="mt-3 min-h-12 text-sm leading-6 text-slate-600 dark:text-slate-300">{product.description}</p></div>
            <ul className="relative mt-6 grid gap-2.5">
              {product.features.map((feature) => { const Icon = featureIcon(feature); return <li key={feature} className="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-700 transition group-hover:border-slate-200 dark:border-white/5 dark:bg-white/[.035] dark:text-slate-200"><span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-primary-600 shadow-sm dark:bg-slate-800 dark:text-cyan-300"><Icon className="size-4" /></span><span>{FEATURE_LABELS[feature] ?? feature.replace(/_/g, ' ')}</span><Check className="ml-auto size-4 shrink-0 text-emerald-500" /></li>; })}
            </ul>
            <Link to={`/solucoes/${product.slug === 'house_flipper' ? 'house-flipper' : product.slug}`} className="relative mt-7 inline-flex items-center gap-2 text-sm font-black text-primary-600 dark:text-primary-300">Conhecer {product.label}<ArrowRight className="size-4 transition group-hover:translate-x-1" /></Link>
          </article>;
        })}
      </div>
      <div className="mt-10 flex flex-col items-start justify-between gap-5 rounded-[2rem] bg-slate-950 p-7 text-white sm:flex-row sm:items-center sm:p-9"><div><span className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[.16em] text-cyan-300"><LayoutGrid className="size-4" />Comparação completa</span><h2 className="mt-2 text-2xl font-black">Ainda está em dúvida entre os produtos?</h2><p className="mt-2 text-sm text-slate-300">Veja cada recurso lado a lado antes de escolher.</p></div><Link to="/comparar-produtos" className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-black text-slate-950">Comparar produtos<ArrowRight className="size-4" /></Link></div>
    </section>
  </div>;
}
