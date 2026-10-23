import { Hammer } from 'lucide-react';
import { Link } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import PricingCards from '@/components/public/PricingCards';
import DemoPreview from '@/components/public/DemoPreview';
import { productBySlug, usePublicCatalog } from '@/lib/publicCatalog';

export default function SolucoesHouseFlipper() {
  const { catalog } = usePublicCatalog();
  const product = productBySlug(catalog, 'house_flipper');
  return (
    <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 space-y-20">
      <PublicSeo title="House Flipper" description="Encontre oportunidades, planeje reformas e analise investimentos imobiliários com o FlipControl." path="/solucoes/house-flipper" />
      <section className="grid gap-8 lg:grid-cols-2 lg:items-center">
        <div>
          <p className="eyebrow">Produto House Flipper</p>
          <h1 className="premium-heading text-4xl sm:text-5xl"><Hammer className="fc-public-heading-icon" aria-hidden="true" />Encontre oportunidades, planeje reformas e analise seus investimentos imobiliários.</h1>
          <p className="premium-copy text-lg">Uma plataforma desenvolvida para investidores que desejam organizar análises, projetos e custos de imóveis.</p>
          <div className="mt-6 flex gap-3">
            <Link to="/precos?produto=house_flipper" className="premium-button">Ver planos</Link>
            <Link to="/cadastro?produto=house_flipper" className="premium-button-secondary">Começar análise</Link>
          </div>
        </div>
        <DemoPreview product="house_flipper" title="Dashboard de investimento" />
      </section>
      <section>
        <h2 className="text-2xl font-bold">Encontrar oportunidades</h2>
        <p className="mt-3 text-slate-600 dark:text-slate-300">Pesquisa, filtros, Mapa Inteligente e comparações sobre a base disponível. O catálogo de leilões usa os dados oficiais importados e identifica a origem de cada lote.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Simulador financeiro</h2>
        <ul className="mt-3 grid gap-2 sm:grid-cols-3 text-sm">
          {['Valor de aquisição', 'Custos', 'Reforma', 'Revenda estimada', 'Margem', 'ROI projetado'].map((item) => (
            <li key={item} className="premium-card p-4">{item}</li>
          ))}
        </ul>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Projetos</h2>
        <p className="mt-3 text-slate-600">Imóveis em análise, projetos, obras, orçamentos e atividades no FlipControl.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Visualizações 3D</h2>
        <p className="mt-3 text-slate-600">Há visualização 3D, profundidade e segmentação neural, simulação de reforma e prévia fotogramétrica para capturas multivista adequadas. A prévia esparsa não é apresentada como levantamento métrico ou reconstrução densa fotorealística.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Mapa Inteligente</h2>
        <p className="mt-3 text-slate-600 dark:text-slate-300">Camadas geográficas para imóveis, oportunidades, preço/m², liquidez, visão investidor e lotes oficiais já importados.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Planos House Flipper</h2>
        <div className="mt-4">{product ? <PricingCards product={product} /> : <p>Carregando planos oficiais...</p>}</div>
      </section>
      <section className="rounded-2xl bg-slate-900 p-8 text-white">
        <h2 className="text-2xl font-bold">Comece a analisar suas oportunidades imobiliárias.</h2>
        <Link to="/cadastro?produto=house_flipper" className="mt-4 inline-block rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900">Criar conta</Link>
      </section>
    </div>
  );
}
