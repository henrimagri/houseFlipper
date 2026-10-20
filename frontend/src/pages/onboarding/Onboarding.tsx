import { useEffect, useState } from 'react';
import { Link, Navigate, useParams } from 'react-router-dom';
import api from '@/lib/api';
import { PRODUCT_META, ProductSlug } from '@/lib/products';
import { useSession } from '@/lib/session';
import ProductTour from '@/components/ProductTour';

type Payload = {
  product_slug: ProductSlug;
  current_step: number;
  completed_steps: string[];
  dismissed: boolean;
  completed_at: string | null;
  steps: { id: string; title: string; href: string }[];
};

export default function Onboarding() {
  const { product } = useParams();
  const slug = product as ProductSlug;
  const { products } = useSession();
  const [data, setData] = useState<Payload | null>(null);
  const [error, setError] = useState('');
  const [tour, setTour] = useState(false);

  useEffect(() => {
    if (!slug || !PRODUCT_META[slug]) return;
    api.get(`/onboarding/${slug}`).then(({ data: payload }) => setData(payload.data)).catch((exception: any) => {
      setError(exception.response?.data?.message ?? 'Não foi possível carregar o onboarding.');
    });
  }, [slug]);

  if (!slug || !PRODUCT_META[slug]) return <Navigate to="/app/produtos" replace />;
  if (!products.includes(slug)) return <Navigate to="/app/produtos" replace />;

  async function save(next: Partial<Payload> & { completed?: boolean }) {
    const { data: payload } = await api.put(`/onboarding/${slug}`, {
      current_step: next.current_step ?? data?.current_step,
      completed_steps: next.completed_steps ?? data?.completed_steps,
      dismissed: next.dismissed ?? data?.dismissed,
      completed: next.completed,
    });
    setData(payload.data);
  }

  if (error) return <p className="text-red-700" role="alert">{error}</p>;
  if (!data) return <p role="status">Carregando primeiros passos...</p>;
  const done = Boolean(data.completed_at) || data.completed_steps.length === data.steps.length;

  return (
    <div className="max-w-3xl space-y-5">
      <h1 className="text-2xl font-bold">Boas-vindas — {PRODUCT_META[slug].label}</h1>
      <p className="text-sm text-slate-600 dark:text-slate-400">Um tour destaca o painel e cada módulo na própria interface. O progresso já salvo neste produto é preservado.</p>
      <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <p className="font-medium">{done ? 'Tutorial já concluído nesta conta.' : 'Pronto para conhecer o produto?'}</p>
        <div className="flex flex-wrap gap-2">
          <button type="button" onClick={() => setTour(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Iniciar tour</button>
          <button type="button" onClick={() => save({ dismissed: true })} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800">Deixar para depois</button>
          <Link to={PRODUCT_META[slug].path} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-950">Ir ao dashboard</Link>
        </div>
      </div>
      <details className="rounded-2xl border border-slate-200 bg-white p-4 text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <summary className="cursor-pointer text-sm font-medium">Checklist técnico (mesmo progresso)</summary>
        <ol className="mt-3 space-y-2">
          {data.steps.map((item, index) => (
            <li key={item.id} className="text-sm flex items-center justify-between gap-2">
              <span>{index + 1}. {item.title}{data.completed_steps.includes(item.id) ? ' ✓' : ''}</span>
              <Link to={item.href} className="font-medium text-sky-700 dark:text-sky-400">Abrir</Link>
            </li>
          ))}
        </ol>
      </details>
      {tour && <ProductTour product={slug} force onClose={() => setTour(false)} />}
    </div>
  );
}
