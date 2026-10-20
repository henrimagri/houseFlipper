import { useEffect, useState } from 'react';
import api from '@/lib/api';
import { ProductSlug } from '@/lib/products';

export type PublicPlan = {
  slug: string;
  name: string;
  billing_interval: string;
  price: number;
  currency: string;
  enabled: boolean;
  checkout: string;
};

export type PublicProduct = {
  slug: ProductSlug;
  code: string;
  label: string;
  description: string;
  path: string;
  trial_days: number;
  features: string[];
  plans: PublicPlan[];
};

export type PublicCatalog = {
  products: PublicProduct[];
  billing_cycles: Record<string, { label: string; months: number; discount_percent: number }>;
  checkout: string;
  gateways: { mercado_pago: string; stripe: string };
};

export const FEATURE_LABELS: Record<string, string> = {
  conversational_search: 'Pesquisa conversacional',
  recommendations: 'Recomendações',
  capture: 'Captação com autorização',
  commercial_wallet: 'Carteira comercial',
  broker_site: 'Site do corretor',
  public_listing: 'Publicação de anúncios autorizados',
  crm_contacts: 'Contatos e CRM',
  marketing_copy: 'Conteúdo de marketing',
  map_listings: 'Mapa de imóveis',
  agency_dashboard: 'Painel empresarial',
  crm_pipeline: 'Funil comercial (Kanban)',
  crm_inbox: 'Central de atendimentos',
  teams: 'Gestão de equipes',
  branches: 'Filiais',
  commissions: 'Comissões',
  lead_roulette: 'Roleta de leads',
  agency_site: 'Site da imobiliária',
  agency_contracts: 'Contratos (módulo em construção)',
  lead_matching: 'Distribuição inteligente de leads',
  broker_agenda: 'Agenda comercial',
  broker_proposals: 'Gestão de propostas',
  broker_dashboard: 'Painel do corretor',
  visualization_3d: 'Visualização 3D disponível',
  photo_analysis: 'Análise de fotos',
  renovation_simulation: 'Simulação de reforma',
  financial_simulator: 'Simulador financeiro',
  investment_wallet: 'Carteira de investimentos',
  flipper_dashboard: 'Dashboard de investimento',
  itbi_import: 'Importação de ITBI',
  map_mode_properties: 'Mapa: imóveis',
  map_mode_opportunities: 'Mapa: oportunidades',
  map_mode_price_m2: 'Mapa: preço/m²',
  map_mode_liquidity: 'Mapa: liquidez',
  map_mode_auctions: 'Mapa: modo leilões (conector ainda não ligado)',
  map_mode_flipper: 'Mapa: visão investidor',
  map_mode_lead_heat: 'Mapa: calor de leads',
  map_mode_capture_potential: 'Mapa: potencial de captação',
  map_mode_capture: 'Mapa: potencial de captação',
};

export const FEATURE_DESCRIPTIONS: Record<string, string> = {
  conversational_search: 'Buscas em linguagem natural com critérios organizados.',
  recommendations: 'Sugestões relacionadas ao contexto disponível na conta.',
  visualization_3d: 'Visualização de projetos e imóveis em ambiente tridimensional.',
  photo_analysis: 'Leitura assistida de imagens para apoiar a análise do imóvel.',
  capture: 'Fluxo de captação com registro de autorização.',
  commercial_wallet: 'Organização dos imóveis disponíveis para comercialização.',
  public_listing: 'Publicação controlada dos anúncios autorizados.',
  crm_contacts: 'Histórico, situação e próximas ações de cada contato.',
  map_listings: 'Visualização geográfica de imóveis e oportunidades.',
  crm_pipeline: 'Etapas comerciais organizadas em um funil visual.',
  crm_inbox: 'Atendimentos centralizados com histórico por contato.',
  teams: 'Organização de usuários, funções e operação da equipe.',
  branches: 'Gestão separada das filiais da imobiliária.',
  financial_simulator: 'Cenários de aquisição, custos e resultado esperado.',
  investment_wallet: 'Acompanhamento das oportunidades em avaliação ou execução.',
  renovation_simulation: 'Planejamento de reforma, etapas e custos estimados.',
  itbi_import: 'Importação estruturada de dados municipais de ITBI.',
};

export const COMPARISON_ROWS: { label: string; features: string[]; note?: Partial<Record<ProductSlug, string>> }[] = [
  { label: 'Captação individual', features: ['capture'], note: { house_flipper: 'Não é foco' } },
  { label: 'Carteira comercial', features: ['commercial_wallet'], note: { house_flipper: 'Não é foco' } },
  { label: 'Site de divulgação', features: ['broker_site', 'agency_site', 'public_listing'], note: { house_flipper: 'Não é foco' } },
  { label: 'CRM de contatos', features: ['crm_contacts'] },
  { label: 'CRM empresarial / funil', features: ['crm_pipeline', 'crm_inbox'], note: { corretor: 'Individual', house_flipper: 'Não é foco' } },
  { label: 'Gestão de equipe', features: ['teams'], note: { corretor: 'Não é foco', house_flipper: 'Não é foco' } },
  { label: 'Filiais', features: ['branches'], note: { corretor: 'Não é foco', house_flipper: 'Não é foco' } },
  { label: 'Investimentos', features: ['investment_wallet', 'flipper_dashboard'], note: { corretor: 'Limitado', imobiliaria: 'Limitado' } },
  { label: 'Simulador financeiro', features: ['financial_simulator'] },
  { label: 'Obras e reformas', features: ['renovation_simulation'], note: { corretor: 'Não é foco' } },
  { label: 'Visualização 3D', features: ['visualization_3d'] },
  { label: 'Mapa Inteligente', features: ['map_listings'] },
];

export const PUBLIC_FAQ = [
  { q: 'O que é a plataforma?', a: 'FlipControl reúne três produtos independentes para o mercado imobiliário: Corretor, Imobiliária e House Flipper. Cada um tem módulos, permissões e assinatura próprios.' },
  { q: 'Qual produto devo escolher?', a: 'Corretor é para profissionais autônomos que captam e divulgam imóveis. Imobiliária é para operação com equipe, filiais e CRM. House Flipper é para investidores analisarem compra, reforma e revenda.' },
  { q: 'Posso contratar mais de um?', a: 'Sim. A mesma conta pode ter um, dois ou três produtos. Contratar um não libera os outros automaticamente.' },
  { q: 'Preciso criar outra conta?', a: 'Não. Use a mesma conta e ative o produto adicional no catálogo.' },
  { q: 'Existe teste gratuito?', a: 'Há teste de 7 dias por produto, uma vez por conta, quando o plano de teste está habilitado no catálogo.' },
  { q: 'Posso cancelar?', a: 'Sim. O cancelamento encerra o acesso daquele produto e não remove os demais.' },
  { q: 'O que acontece com os dados?', a: 'Os dados permanecem isolados por conta (tenant). A política de privacidade descreve finalidades e retenção.' },
  { q: 'Posso mudar de plano?', a: 'A troca de plano pago depende do checkout. Enquanto Mercado Pago e Stripe estiverem not_wired, use o teste local ou solicite uma proposta.' },
  { q: 'Como funciona o atendimento?', a: 'Visitantes enviam solicitação pelo site. A equipe consulta a fila interna. Não há chat ao vivo neste momento.' },
  { q: 'Existe aplicativo?', a: 'Não há aplicativo nativo publicado. O sistema é responsivo no navegador.' },
  { q: 'Como funcionam as integrações?', a: 'Integrações existem somente quando configuradas. Portais, WhatsApp, leilões nacionais e cobrança automática não estão ligados nesta versão.' },
];

export function formatPlanPrice(plan: PublicPlan): string {
  if (!plan.enabled) return 'Indisponível';
  if (plan.billing_interval === 'trial' || plan.price === 0) return 'Teste sem cobrança';
  if (plan.checkout === 'not_wired') {
    return `${(plan.price / 100).toLocaleString('pt-BR', { style: 'currency', currency: plan.currency || 'BRL' })} / mês — contratação comercial`;
  }
  return (plan.price / 100).toLocaleString('pt-BR', { style: 'currency', currency: plan.currency || 'BRL' });
}

export function usePublicCatalog() {
  const [catalog, setCatalog] = useState<PublicCatalog | null>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    api.get('/public/catalog')
      .then(({ data }) => setCatalog(data.data))
      .catch(() => setError('Não foi possível carregar o catálogo oficial.'));
  }, []);
  return { catalog, error };
}

export function productBySlug(catalog: PublicCatalog | null, slug: ProductSlug) {
  return catalog?.products.find((item) => item.slug === slug);
}
