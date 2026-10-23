import { Columns3 } from 'lucide-react';
import { Link } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import { COMPARISON_ROWS, usePublicCatalog } from '@/lib/publicCatalog';
import { PRODUCT_META, ProductSlug } from '@/lib/products';

const slugs: ProductSlug[] = ['corretor', 'imobiliaria', 'house_flipper'];

export default function CompararProdutos() {
  const { catalog, error } = usePublicCatalog();
  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <PublicSeo title="Comparar produtos" description="Compare Corretor, Imobiliária e House Flipper a partir do catálogo oficial de funcionalidades." path="/comparar-produtos" />
      <h1 className="text-3xl font-bold"><Columns3 className="fc-public-heading-icon" aria-hidden="true" />Qual produto combina com o seu trabalho?</h1>
      <p className="mt-3 text-slate-600">A tabela usa as funcionalidades cadastradas no backend. Não é uma segunda lista de permissões.</p>
      {error && <p className="mt-4 text-sm text-red-700">{error}</p>}
      <div className="mt-8 overflow-x-auto rounded-2xl border bg-white">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b bg-slate-50">
              <th className="p-4 text-left">Funcionalidade</th>
              {slugs.map((slug) => (
                <th key={slug} className="p-4 text-left"><Link to={`/solucoes/${slug === 'house_flipper' ? 'house-flipper' : slug}`} className="text-primary-700">{PRODUCT_META[slug].label}</Link></th>
              ))}
            </tr>
          </thead>
          <tbody>
            {COMPARISON_ROWS.map((row) => (
              <tr key={row.label} className="border-t">
                <td className="p-4 font-medium">{row.label}</td>
                {slugs.map((slug) => {
                  const features = catalog?.products.find((item) => item.slug === slug)?.features ?? [];
                  const has = row.features.some((feature) => features.includes(feature));
                  return <td key={slug} className="p-4">{has ? 'Sim' : (row.note?.[slug] ?? 'Não é foco')}</td>;
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="mt-4 text-xs text-slate-500">Contratar um produto não libera os outros. Visualização 3D e simulador aparecem só onde o catálogo inclui o recurso.</p>
    </div>
  );
}
