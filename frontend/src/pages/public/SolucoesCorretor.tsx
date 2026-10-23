import { House } from 'lucide-react';
import { Link } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import PricingCards from '@/components/public/PricingCards';
import DemoPreview from '@/components/public/DemoPreview';
import { productBySlug, usePublicCatalog } from '@/lib/publicCatalog';

export default function SolucoesCorretor() {
  const { catalog } = usePublicCatalog();
  const product = productBySlug(catalog, 'corretor');
  return (
    <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 space-y-20">
      <PublicSeo title="Corretor" description="Encontre, capte e divulgue imóveis com uma carteira comercial organizada. Solução FlipControl para corretores autônomos." path="/solucoes/corretor" />
      <section className="grid gap-10 lg:grid-cols-2 lg:items-center">
        <div>
          <p className="eyebrow">Produto Corretor</p>
          <h1 className="premium-heading text-4xl sm:text-5xl"><House className="fc-public-heading-icon" aria-hidden="true" />Encontre, capte e divulgue imóveis com muito mais organização.</h1>
          <p className="premium-copy text-lg">Uma solução para corretores que desejam transformar oportunidades imobiliárias em uma carteira comercial organizada.</p>
          <div className="mt-6 flex gap-3">
            <Link to="/precos?produto=corretor" className="premium-button">Ver planos</Link>
            <Link to="/cadastro?produto=corretor" className="premium-button-secondary">Começar teste</Link>
          </div>
        </div>
        <DemoPreview product="corretor" title="Carteira do corretor" />
      </section>
      <section>
        <h2 className="text-2xl font-bold">Descubra oportunidades</h2>
        <p className="mt-3 text-slate-600">A pesquisa usa os filtros do sistema para localizar imóveis. O corretor salva o que interessa. Anúncios encontrados em fontes de terceiros não podem ser republicados livremente.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Captação</h2>
        <ol className="mt-4 grid gap-3 md:grid-cols-5 text-sm">
          {['Encontra uma oportunidade', 'Salva o imóvel', 'Solicita captação', 'Registra autorização', 'Adiciona à carteira'].map((step, index) => (
            <li key={step} className="premium-card p-4"><span className="font-bold text-primary-600">{index + 1}.</span> {step}</li>
          ))}
        </ol>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Carteira comercial</h2>
        <p className="mt-3 text-slate-600">Imóveis captados, status, fotos autorizadas, descrições e publicação somente com grant, foto licenciada e site do corretor ativo.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Site do corretor</h2>
        <p className="mt-3 text-slate-600">Vitrine profissional personalizável para apresentar somente os imóveis que você autorizar.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Contatos e CRM</h2>
        <p className="mt-3 text-slate-600">Interessados chegam pelos formulários dos imóveis publicados e ficam organizados no CRM individual do corretor.</p>
      </section>
      <section>
        <h2 className="text-2xl font-bold">Planos do Corretor</h2>
        <div className="mt-4">{product ? <PricingCards product={product} /> : <p>Carregando planos oficiais...</p>}</div>
      </section>
      <section className="rounded-2xl bg-primary-700 p-8 text-white">
        <h2 className="text-2xl font-bold">Comece a organizar sua carteira imobiliária.</h2>
        <div className="mt-4 flex gap-3">
          <Link to="/precos?produto=corretor" className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary-700">Ver planos</Link>
          <Link to="/cadastro?produto=corretor" className="rounded-lg border border-white px-4 py-2 text-sm font-semibold">Começar teste</Link>
        </div>
      </section>
    </div>
  );
}
