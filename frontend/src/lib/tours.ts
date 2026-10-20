import { ProductSlug } from '@/lib/products';

export type TourStep = {
  id: string;
  title: string;
  body: string;
  selector: string;
  route?: string;
};

export const TOURS: Record<ProductSlug, TourStep[]> = {
  corretor: [
    { id: 'profile', title: 'Painel do corretor', body: 'Aqui você acompanha carteira, captações, leads e visitas com números reais da conta.', selector: '[data-tour="page"]', route: '/app/corretor/dashboard' },
    { id: 'search', title: 'Pesquisa', body: 'Encontre imóveis e salve oportunidades. Anúncios de terceiros não podem ser republicados livremente.', selector: '[data-tour="page"]', route: '/app/corretor/pesquisar' },
    { id: 'save', title: 'Favoritos', body: 'Salve imóveis para analisar e iniciar a captação.', selector: '[data-tour="page"]', route: '/app/corretor/favoritos' },
    { id: 'capture', title: 'Captação', body: 'Solicite autorização, registre o grant e só então publique.', selector: '[data-tour="page"]', route: '/app/corretor/captacao' },
    { id: 'wallet', title: 'Carteira', body: 'Imóveis autorizados, status, fotos licenciadas e publicação.', selector: '[data-tour="page"]', route: '/app/corretor/carteira' },
    { id: 'site', title: 'Seu site', body: 'Monte sua vitrine profissional, personalize a identidade visual e publique somente os imóveis que você autorizar.', selector: '[data-tour="page"]', route: '/app/corretor/meu-site' },
    { id: 'crm', title: 'Contatos', body: 'Leads que chegam pelos formulários ficam organizados aqui.', selector: '[data-tour="page"]', route: '/app/corretor/leads' },
    { id: 'agenda', title: 'Agenda', body: 'Organize visitas, retornos e compromissos vinculados ao trabalho comercial.', selector: '[data-tour="page"]', route: '/app/corretor/agenda' },
    { id: 'proposal', title: 'Propostas', body: 'Registre propostas reais e acompanhe seu estado.', selector: '[data-tour="page"]', route: '/app/corretor/propostas' },
    { id: 'profile-menu', title: 'Conta e sistemas', body: 'Abra o avatar para trocar de produto, gerenciar assinatura, configurações e sessão.', selector: '[data-tour="profile-menu"]', route: '/app/corretor/dashboard' },
  ],
  imobiliaria: [
    { id: 'org', title: 'Painel empresarial', body: 'Indicadores da operação: imóveis, leads, atendimentos e fechamentos, filtráveis por período e filial.', selector: '[data-tour="page"]', route: '/app/imobiliaria/dashboard' },
    { id: 'listing', title: 'Imóveis', body: 'Estoque da imobiliária, disponibilidade e publicação autorizada.', selector: '[data-tour="page"]', route: '/app/imobiliaria/imoveis' },
    { id: 'client', title: 'Clientes', body: 'Cadastro de compradores e interessados da operação.', selector: '[data-tour="page"]', route: '/app/imobiliaria/clientes' },
    { id: 'ticket', title: 'Atendimentos', body: 'Central interna. WhatsApp Cloud continua not_wired — nada é enviado ao aplicativo.', selector: '[data-tour="page"]', route: '/app/imobiliaria/atendimentos' },
    { id: 'crm', title: 'Funil CRM', body: 'Arraste cards entre etapas. A mudança é persistida. Conflitos de versão pedem recarga.', selector: '[data-tour="page"]', route: '/app/imobiliaria/crm' },
    { id: 'team', title: 'Equipes', body: 'Permissões e visão consolidada autorizada da equipe.', selector: '[data-tour="page"]', route: '/app/imobiliaria/equipes' },
    { id: 'branch', title: 'Filiais', body: 'Unidades da imobiliária usadas nos filtros do painel.', selector: '[data-tour="page"]', route: '/app/imobiliaria/filiais' },
    { id: 'site', title: 'Site institucional', body: 'Editor por blocos, pré-visualização e publicação em /i/{slug}.', selector: '[data-tour="page"]', route: '/app/imobiliaria/site' },
    { id: 'proposal', title: 'Propostas e comissões', body: 'Acompanhe propostas, regras e projeções de comissão com dados persistidos.', selector: '[data-tour="page"]', route: '/app/imobiliaria/propostas' },
    { id: 'profile-menu', title: 'Conta e sistemas', body: 'Abra o avatar para trocar de produto, gerenciar assinatura, configurações e sessão.', selector: '[data-tour="profile-menu"]', route: '/app/imobiliaria/dashboard' },
  ],
  house_flipper: [
    { id: 'region', title: 'Painel de investimentos', body: 'Oportunidades, projetos, investimento projetado e ROI. Projeção não é lucro realizado.', selector: '[data-tour="page"]', route: '/app/house-flipper/dashboard' },
    { id: 'search', title: 'Oportunidades', body: 'Pesquise e salve imóveis para analisar.', selector: '[data-tour="page"]', route: '/app/house-flipper/oportunidades' },
    { id: 'save', title: 'Mapa Inteligente', body: 'Navegue, filtre e desenhe áreas. Lotes aparecem somente quando a planilha oficial contém coordenadas válidas.', selector: '[data-tour="page"]', route: '/app/house-flipper/mapa' },
    { id: 'auctions', title: 'Leilões e vendas públicas', body: 'Importe arquivos oficiais da CAIXA ou VendasGov. A modalidade original é preservada e nenhum lote é inventado.', selector: '[data-tour="page"]', route: '/app/house-flipper/leiloes' },
    { id: 'simulator', title: 'Simulador', body: 'Aquisição, custos, reforma e revenda. Os gráficos usam a análise salva.', selector: '[data-tour="page"]', route: '/app/house-flipper/simulador' },
    { id: 'project', title: 'Projetos', body: 'Crie o projeto, acompanhe obra e custos reais do FlipControl.', selector: '[data-tour="page"]', route: '/app/house-flipper/projetos' },
    { id: 'budget', title: 'Custos', body: 'Despesas e orçamento ligados aos projetos ativos.', selector: '[data-tour="page"]', route: '/app/house-flipper/despesas' },
    { id: 'viz3d', title: 'Visualização 3D', body: 'Visualização e simulação disponíveis. Não há fotogrametria fotorrealista.', selector: '[data-tour="page"]', route: '/app/house-flipper/projetos-3d' },
    { id: 'operations', title: 'Execução da reforma', body: 'Obras, tarefas, fornecedores e documentos mantêm a execução conectada ao projeto.', selector: '[data-tour="page"]', route: '/app/house-flipper/obras' },
    { id: 'profile-menu', title: 'Conta e sistemas', body: 'Abra o avatar para trocar de produto, gerenciar assinatura, configurações e sessão.', selector: '[data-tour="profile-menu"]', route: '/app/house-flipper/dashboard' },
  ],
};
