import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Building2, Check, ChevronDown, Hammer, Home, Settings } from 'lucide-react';
import { useSession } from '@/lib/session';
import { PRODUCT_META, ProductSlug } from '@/lib/products';

const ICONS = {
  corretor: Home,
  imobiliaria: Building2,
  house_flipper: Hammer,
} as const;

const COPY: Record<ProductSlug, string> = {
  corretor: 'Captação e gestão comercial.',
  imobiliaria: 'CRM e gestão empresarial.',
  house_flipper: 'Investimentos e reformas.',
};

function badge(status?: string, active?: boolean) {
  if (status === 'trial' || status === 'trialing') return { label: 'Assinatura em teste', cls: 'bg-sky-500/15 text-sky-200' };
  if (status === 'active' || (active && status === 'grace')) return { label: 'Assinatura ativa', cls: 'bg-emerald-500/15 text-emerald-200' };
  if (status === 'past_due') return { label: 'Pagamento pendente', cls: 'bg-amber-500/20 text-amber-100' };
  if (status === 'canceled' || status === 'cancelled' || status === 'expired' || status === 'suspended') return { label: 'Encerrada', cls: 'bg-white/10 text-slate-300' };
  if (active) return { label: 'Assinatura ativa', cls: 'bg-emerald-500/15 text-emerald-200' };
  return { label: 'Sem assinatura', cls: 'bg-white/10 text-slate-300' };
}

export default function ProductSwitcher({
  compact = false,
  activeProduct,
  embedded = false,
  onSelect,
}: {
  compact?: boolean;
  activeProduct?: ProductSlug | 'account';
  embedded?: boolean;
  onSelect?: () => void;
}) {
  const { catalog, products, selectProduct } = useSession();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const current = activeProduct !== 'account' && activeProduct ? PRODUCT_META[activeProduct] : null;

  useEffect(() => {
    const close = (event: MouseEvent) => {
      if (!root.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  if (compact) {
    return (
      <div ref={root} className="relative" data-tour="product-switcher">
        <button
          type="button"
          aria-haspopup="menu"
          aria-expanded={open}
          onClick={() => setOpen((value) => !value)}
          className="inline-flex max-w-[12rem] items-center gap-2 rounded-xl border bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-xs dark:bg-slate-900 dark:text-slate-100"
        >
          <span className="truncate">{current?.label ?? 'Meus produtos'}</span>
          <ChevronDown className={`h-4 w-4 shrink-0 transition ${open ? 'rotate-180' : ''}`} />
        </button>
        {open && (
          <div role="menu" className="fixed inset-x-3 top-16 z-50 rounded-2xl border bg-white p-2 shadow-2xl dark:bg-slate-900 sm:absolute sm:inset-x-auto sm:left-0 sm:top-full sm:mt-2 sm:w-80">
            {catalog.map((item) => {
              const slug = item.slug as ProductSlug;
              const meta = PRODUCT_META[slug];
              if (!meta) return null;
              const Icon = ICONS[slug];
              const granted = products.includes(slug);
              const active = activeProduct === slug;
              const chip = badge(item.subscription?.status, item.subscription?.active);
              return (
                <button
                  role="menuitem"
                  key={slug}
                  type="button"
                  disabled={!granted}
                  onClick={() => {
                    if (!granted) return;
                    setOpen(false);
                    void selectProduct(slug);
                    navigate(meta.path);
                  }}
                  className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-slate-50 disabled:opacity-50 dark:hover:bg-slate-800"
                >
                  <Icon className="h-5 w-5 text-sky-600" />
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold">{meta.label}</span>
                    <span className="block text-xs text-slate-500">{chip.label}</span>
                  </span>
                  {active && <Check className="h-4 w-4 text-emerald-600" />}
                </button>
              );
            })}
            <button role="menuitem" type="button" onClick={() => { setOpen(false); navigate('/app/produtos'); }} className="mt-1 flex w-full items-center gap-3 border-t px-3 py-3 text-left text-sm font-semibold text-sky-700 dark:text-sky-300">
              <Settings className="h-4 w-4" /> Gerenciar meus produtos
            </button>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className={compact ? 'space-y-1.5' : 'space-y-2'} data-tour="product-switcher">
      {!compact && !embedded && <p className="text-[10px] uppercase tracking-[0.18em] text-slate-400 px-1">Produtos</p>}
      {catalog.map((item) => {
        const slug = item.slug as ProductSlug;
        const meta = PRODUCT_META[slug];
        if (!meta) return null;
        const Icon = ICONS[slug];
        const granted = products.includes(slug);
        const active = activeProduct === slug;
        const chip = badge(item.subscription?.status, item.subscription?.active);
        return (
          <button
            key={slug}
            type="button"
            disabled={!granted}
            onClick={() => {
              if (!granted) return;
              void selectProduct(slug);
              navigate(meta.path);
              onSelect?.();
            }}
            className={`w-full text-left rounded-xl border px-3 py-2.5 transition ${embedded ? (
              active
                ? 'border-sky-300 bg-sky-50 text-slate-900 dark:border-sky-700 dark:bg-sky-950/60 dark:text-white'
                : granted
                  ? 'border-transparent text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800'
                  : 'border-transparent text-slate-400 cursor-not-allowed dark:text-slate-500'
            ) : (
              active
                ? 'border-sky-400/60 bg-sky-500/15 text-white'
                : granted
                  ? 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
                  : 'border-white/5 bg-transparent text-slate-500 cursor-not-allowed'
            )}`}
          >
            <span className="flex items-start gap-2.5">
              <span className={`mt-0.5 rounded-lg p-1.5 ${active ? 'bg-sky-500 text-white' : embedded ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300' : 'bg-white/10 text-slate-300'}`}>
                <Icon className="h-4 w-4" strokeWidth={1.75} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                  <span className="text-sm font-semibold">{meta.label}</span>
                  {active && <span className="h-1.5 w-1.5 rounded-full bg-sky-400" />}
                </span>
                {!compact && <span className="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">{COPY[slug]}</span>}
                <span className={`mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium ${chip.cls}`}>{chip.label}</span>
                {!granted && (
                  <a href="/app/produtos" className="mt-1 block text-[11px] text-sky-300 hover:underline" onClick={(e) => e.stopPropagation()}>
                    Contratar
                  </a>
                )}
              </span>
            </span>
          </button>
        );
      })}
    </div>
  );
}
