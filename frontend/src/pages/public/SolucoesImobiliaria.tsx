import { Building2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import PricingCards from '@/components/public/PricingCards';
import DemoPreview from '@/components/public/DemoPreview';
import LeadForm from '@/components/public/LeadForm';
import { productBySlug, usePublicCatalog } from '@/lib/publicCatalog';

const blocks = [
  { t: 'Gestão de imóveis', d: 'Cadastro, disponibilidade, publicação, proprietários e informações comerciais da carteira da empresa.' },
  { t: 'CRM', d: 'Funil Kanban, leads, histórico, atividades e negociações, com concorrência controlada na movimentação de cards.' },
  { t: 'Central de atendimento', d: 'Organização de contatos, distribuição, acompanhamento, responsáveis e atendimento interno.' },
  { t: 'Gestão de equipe', d: 'Usuários, corretores, responsáveis e permissões da organização.' },
  { t: 'Filiais', d: 'Separação de unidades, indicadores e visão consolidada autorizada.' },
  { t: 'Site da imobiliária', d: 'Apresentação institucional, imóveis publicados e captação de contatos na identidade da empresa.' },
];

export default function SolucoesImobiliaria() {
  const { catalog } = usePublicCatalog();
  const product = productBySlug(catalog, 'imobiliaria');
  return (
    <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 space-y-20">
      <PublicSeo title="Imobiliária" description="Centralize imóveis, corretores, atendimentos e negociações em um sistema organizado para sua equipe." path="/solucoes/imobiliaria" />
      <section>
        <p className="eyebrow">Produto Imobiliária</p>
        <h1 className="premium-heading text-4xl sm:text-5xl"><Building2 className="fc-public-heading-icon" aria-hidden="true" />Toda a operação da sua imobiliária em uma única plataforma.</h1>
        <p className="premium-copy text-lg">Centralize imóveis, corretores, atendimentos e negociações em um sistema organizado para sua equipe.</p>
        <div className="mt-6 flex gap-3">
          <a href="#demonstracao" className="premium-button">Solicitar uma demonstração</a>
          <Link to="/precos?produto=imobiliaria" className="premium-button-secondary">Planos empresariais</Link>
        </div>
      </section>
      <DemoPreview product="imobiliaria" title="CRM da imobiliária" />
      <div className="grid gap-4 md:grid-cols-2">
        {blocks.map((block) => (
          <article key={block.t} className="premium-card">
            <h2 className="font-semibold">{block.t}</h2>
            <p className="mt-2 text-sm text-slate-600">{block.d}</p>
          </article>
        ))}
      </div>
      <section>
        <h2 className="text-2xl font-bold">Planos empresariais</h2>
        <div className="mt-4">{product ? <PricingCards product={product} /> : <p>Carregando planos oficiais...</p>}</div>
      </section>
      <section id="demonstracao" className="grid gap-6 lg:grid-cols-2">
        <div>
          <h2 className="text-2xl font-bold">Solicitar uma demonstração</h2>
          <p className="mt-2 text-slate-600">A solicitação é registrada na fila comercial. Não envia e-mail se o serviço transacional não estiver configurado.</p>
        </div>
        <LeadForm intent="demo" product="imobiliaria" sourcePath="/solucoes/imobiliaria" submitLabel="Registrar demonstração" />
      </section>
    </div>
  );
}
