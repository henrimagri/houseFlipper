import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '@/lib/api';
import { PRODUCT_META, ProductSlug } from '@/lib/products';
import { useSession } from '@/lib/session';

export default function Produtos() {
  const { catalog, products, refresh, selectProduct } = useSession();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const requestedCycle = ['monthly', 'quarterly', 'annual'].includes(params.get('ciclo') ?? '') ? params.get('ciclo')! : 'monthly';
  const [error, setError] = useState('');
  const [busy, setBusy] = useState('');

  async function startTrial(slug: ProductSlug) {
    setBusy(slug); setError('');
    try {
      await api.post('/billing/trial', { product: slug });
      await refresh();
      await selectProduct(slug);
      navigate(`/app/onboarding/${slug}`);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível iniciar o teste.');
    } finally {
      setBusy('');
    }
  }

  async function checkout(slug: ProductSlug, plan: string, billingCycle = 'monthly') {
    setBusy(plan); setError('');
    try {
      const { data } = await api.post('/billing/checkout', { product: slug, plan, billing_cycle: billingCycle });
      if (data.checkout_url) window.location.assign(data.checkout_url);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Checkout de pagamento não está ligado.');
    } finally {
      setBusy('');
    }
  }

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold">Catálogo</h1>
      <p className="text-sm text-gray-500">Teste de 7 dias por produto. Pagamentos exigem e-mail confirmado e um provedor configurado.</p>
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      <div className="grid md:grid-cols-3 gap-4">
        {catalog.map((item) => {
          const slug = item.slug as ProductSlug;
          const entitled = products.includes(slug);
          return (
            <article key={slug} className="rounded-xl border bg-white dark:bg-slate-900 p-5 space-y-3">
              <h2 className="font-semibold">{item.label}</h2>
              <p className="text-sm text-gray-500">{item.description}</p>
              {item.plans.map((plan) => (
                <div key={plan.slug} className="text-sm flex justify-between gap-2">
                  <span>{plan.name}</span>
                  <span>{plan.price > 0 ? (plan.price / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : 'Grátis'}</span>
                </div>
              ))}
              {entitled ? (
                <button type="button" onClick={() => { selectProduct(slug); navigate(PRODUCT_META[slug].path); }} className="w-full py-2 rounded-lg bg-primary-600 text-white text-sm">Abrir</button>
              ) : (
                <div className="space-y-2">
                  <button type="button" disabled={busy === slug} onClick={() => startTrial(slug)} className="w-full py-2 rounded-lg bg-primary-600 text-white text-sm disabled:opacity-60">{busy === slug ? 'Ativando...' : 'Teste de 7 dias'}</button>
                  {item.plans.filter((plan) => plan.price > 0).map((plan) => (
                    <button key={plan.slug} type="button" disabled={busy === plan.slug} onClick={() => checkout(slug, plan.slug, requestedCycle)} className="w-full rounded-lg border border-primary-300 py-2 text-sm font-bold text-primary-700 dark:border-primary-800 dark:text-primary-300">
                      {busy === plan.slug ? 'Abrindo pagamento…' : `Ir para pagamento · ${requestedCycle === 'annual' ? 'anual' : requestedCycle === 'quarterly' ? 'trimestral' : 'mensal'}`}
                    </button>
                  ))}
                </div>
              )}
            </article>
          );
        })}
      </div>
    </div>
  );
}
