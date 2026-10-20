import { Link } from 'react-router-dom';
import { PRODUCT_META, ProductSlug } from '@/lib/products';
import { useSession } from '@/lib/session';

export default function Inicio() {
  const { catalog, products, name } = useSession();
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Olá, {name.split(' ')[0] || 'conta'}</h1>
        <p className="text-sm text-gray-500 mt-1">Uma conta, três sistemas. Só entra o que estiver contratado.</p>
      </div>
      {products.length === 0 && (
        <div className="rounded-xl border bg-white dark:bg-slate-900 p-6">
          <p className="font-medium">Nenhum produto liberado.</p>
          <p className="text-sm text-gray-500 mt-1">Abra o catálogo para iniciar um teste de 7 dias. A contratação paga segue por confirmação comercial.</p>
          <Link to="/app/produtos" className="inline-block mt-4 px-4 py-2 rounded-lg bg-primary-600 text-white text-sm">Ver produtos</Link>
        </div>
      )}
      <div className="grid md:grid-cols-3 gap-4">
        {catalog.map((item) => {
          const slug = item.slug as ProductSlug;
          const entitled = products.includes(slug);
          return (
            <div key={slug} className="rounded-xl border bg-white dark:bg-slate-900 p-5 space-y-3">
              <h2 className="font-semibold">{item.label}</h2>
              <p className="text-sm text-gray-500">{item.description}</p>
              <p className="text-xs uppercase text-gray-400">{entitled ? item.subscription?.status : 'não contratado'}</p>
              <Link to={entitled ? PRODUCT_META[slug].path : '/app/produtos'} className="inline-block text-sm font-medium text-primary-700">
                {entitled ? 'Abrir sistema' : 'Contratar'}
              </Link>
            </div>
          );
        })}
      </div>
    </div>
  );
}
