import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useParams } from 'react-router-dom';
import { getAuthToken } from '@/lib/authToken';
import ErrorPage from '@/pages/ErrorPage';
import ProductLayout from '@/components/ProductLayout';
import PublicLayout from '@/components/public/PublicLayout';
import { useSession } from '@/lib/session';

const Login = lazy(() => import('@/pages/Login'));
const Register = lazy(() => import('@/pages/Register'));
const ForgotPassword = lazy(() => import('@/pages/ForgotPassword'));
const ResetPassword = lazy(() => import('@/pages/ResetPassword'));
const VerifyEmail = lazy(() => import('@/pages/VerifyEmail'));
const PublicBroker = lazy(() => import('@/pages/PublicBroker'));
const PublicAgency = lazy(() => import('@/pages/PublicAgency'));
const PublicListing = lazy(() => import('@/pages/PublicBroker').then((module) => ({ default: module.PublicListing })));
const Inicio = lazy(() => import('@/pages/account/Inicio'));
const Produtos = lazy(() => import('@/pages/account/Produtos'));
const Assinaturas = lazy(() => import('@/pages/account/Assinaturas'));
const Configuracoes = lazy(() => import('@/pages/Configuracoes'));
const Ajuda = lazy(() => import('@/pages/Ajuda'));
const CorretorDashboard = lazy(() => import('@/pages/corretor/Dashboard'));
const Oportunidades = lazy(() => import('@/pages/Oportunidades'));
const Carteira = lazy(() => import('@/pages/Carteira'));
const CarteiraFicha = lazy(() => import('@/pages/CarteiraFicha'));
const SiteCorretor = lazy(() => import('@/pages/SiteCorretor'));
const CrmContatos = lazy(() => import('@/pages/CrmContatos'));
const PersonalKanban = lazy(() => import('@/pages/corretor/PersonalKanban'));
const Calendario = lazy(() => import('@/pages/Calendario'));
const Propostas = lazy(() => import('@/pages/Propostas'));
const IAInteligente = lazy(() => import('@/pages/IAInteligente'));
const MapaInteligente = lazy(() => import('@/pages/MapaInteligente'));
const ImobiliariaDashboard = lazy(() => import('@/pages/imobiliaria/Dashboard'));
const CrmKanban = lazy(() => import('@/pages/imobiliaria/CrmKanban'));
const Atendimentos = lazy(() => import('@/pages/imobiliaria/Atendimentos'));
const Equipes = lazy(() => import('@/pages/imobiliaria/Equipes'));
const Filiais = lazy(() => import('@/pages/imobiliaria/Filiais'));
const Comissoes = lazy(() => import('@/pages/imobiliaria/Comissoes'));
const Contratos = lazy(() => import('@/pages/imobiliaria/Contratos'));
const AgencySite = lazy(() => import('@/pages/imobiliaria/Site'));
const FlipperDashboard = lazy(() => import('@/pages/house-flipper/Dashboard'));
const Auctions = lazy(() => import('@/pages/house-flipper/Auctions'));
const CrmContatoFicha = lazy(() => import('@/pages/CrmContatoFicha'));
const CrmDealDetail = lazy(() => import('@/pages/imobiliaria/CrmDealDetail'));
const Roleta = lazy(() => import('@/pages/imobiliaria/Roleta'));
const Projetos = lazy(() => import('@/pages/Projetos'));
const AnaliseImovel = lazy(() => import('@/pages/AnaliseImovel'));
const PropertyViewer = lazy(() => import('@/pages/PropertyViewer'));
const Obras = lazy(() => import('@/pages/Obras'));
const Financeiro = lazy(() => import('@/pages/Financeiro'));
const Despesas = lazy(() => import('@/pages/Despesas'));
const Tarefas = lazy(() => import('@/pages/Tarefas'));
const Fornecedores = lazy(() => import('@/pages/Fornecedores'));
const Documentos = lazy(() => import('@/pages/Documentos'));
const Relatorios = lazy(() => import('@/pages/Relatorios'));
const ImportarItbi = lazy(() => import('@/pages/ImportarItbi'));
const AnaliseMercadoOportunidade = lazy(() => import('@/pages/AnaliseMercadoOportunidade'));
const CentralFinanceira = lazy(() => import('@/pages/CentralFinanceira'));
const WizardFinanceiro = lazy(() => import('@/pages/WizardFinanceiro'));
const PlatformConsole = lazy(() => import('@/pages/platform/PlatformConsole'));
const Onboarding = lazy(() => import('@/pages/onboarding/Onboarding'));
const Home = lazy(() => import('@/pages/public/Home'));
const SolucoesCorretor = lazy(() => import('@/pages/public/SolucoesCorretor'));
const SolucoesImobiliaria = lazy(() => import('@/pages/public/SolucoesImobiliaria'));
const SolucoesHouseFlipper = lazy(() => import('@/pages/public/SolucoesHouseFlipper'));
const Funcionalidades = lazy(() => import('@/pages/public/Funcionalidades'));
const CompararProdutos = lazy(() => import('@/pages/public/CompararProdutos'));
const Precos = lazy(() => import('@/pages/public/Precos'));
const Sobre = lazy(() => import('@/pages/public/Sobre'));
const Contato = lazy(() => import('@/pages/public/Contato'));
const SolicitarDemonstracao = lazy(() => import('@/pages/public/SolicitarDemonstracao'));
const AjudaPublica = lazy(() => import('@/pages/public/AjudaPublica'));
const Termos = lazy(() => import('@/pages/public/Termos'));
const Privacidade = lazy(() => import('@/pages/public/Privacidade'));

function PrivateRoute({ children }: { children: JSX.Element }) {
  const token = getAuthToken();
  const session = useSession();
  if (!token) return <Navigate to="/entrar" replace />;
  if (session.loading) return <div role="status" className="grid min-h-screen place-items-center bg-slate-950 text-sm font-bold text-slate-300">Validando sua conta…</div>;
  return session.requiresEmailVerification ? <Navigate to="/confirmar-email" replace /> : children;
}

function RedirectParam({ to }: { to: string }) {
  const params = useParams();
  const path = to.replace(':id', String(params.id ?? ''));
  return <Navigate to={path} replace />;
}

export default function App() {
  return (
    <Suspense fallback={<div role="status" className="min-h-screen grid place-items-center bg-slate-50 text-slate-600 dark:bg-slate-950 dark:text-slate-300">Carregando página...</div>}>
      <Routes>
        <Route path="/c/:slug" element={<PublicBroker />} />
        <Route path="/c/:slug/:listingSlug" element={<PublicListing />} />
        <Route path="/corretor/:slug" element={<PublicBroker />} />
        <Route path="/corretor/:slug/imovel/:listingSlug" element={<PublicListing />} />
        <Route path="/i/:slug" element={<PublicAgency />} />
        <Route element={<PublicLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/solucoes/corretor" element={<SolucoesCorretor />} />
          <Route path="/solucoes/imobiliaria" element={<SolucoesImobiliaria />} />
          <Route path="/solucoes/house-flipper" element={<SolucoesHouseFlipper />} />
          <Route path="/funcionalidades" element={<Funcionalidades />} />
          <Route path="/comparar-produtos" element={<CompararProdutos />} />
          <Route path="/precos" element={<Precos />} />
          <Route path="/sobre" element={<Sobre />} />
          <Route path="/contato" element={<Contato />} />
          <Route path="/solicitar-demonstracao" element={<SolicitarDemonstracao />} />
          <Route path="/ajuda" element={<AjudaPublica />} />
          <Route path="/termos" element={<Termos />} />
          <Route path="/privacidade" element={<Privacidade />} />
        </Route>
        <Route path="/entrar" element={<Login />} />
        <Route path="/login" element={<Login />} />
        <Route path="/cadastro" element={<Register />} />
        <Route path="/recuperar-senha" element={<ForgotPassword />} />
        <Route path="/esqueci-senha" element={<ForgotPassword />} />
        <Route path="/redefinir-senha" element={<ResetPassword />} />
        <Route path="/confirmar-email" element={<VerifyEmail />} />

        <Route path="/platform" element={<PrivateRoute><PlatformConsole /></PrivateRoute>} />
        <Route path="/platform/:section" element={<PrivateRoute><PlatformConsole /></PrivateRoute>} />

        <Route element={<PrivateRoute><ProductLayout product="account" /></PrivateRoute>}>
          <Route path="/app/inicio" element={<Inicio />} />
          <Route path="/app/produtos" element={<Produtos />} />
          <Route path="/app/assinaturas" element={<Assinaturas />} />
          <Route path="/app/configuracoes" element={<Configuracoes />} />
          <Route path="/app/ajuda" element={<Ajuda />} />
          <Route path="/app/onboarding/:product" element={<Onboarding />} />

        </Route>

        <Route element={<PrivateRoute><ProductLayout product="corretor" /></PrivateRoute>}>
          <Route path="/app/corretor/dashboard" element={<CorretorDashboard />} />
          <Route path="/app/corretor/pesquisar" element={<Oportunidades />} />
          <Route path="/app/corretor/favoritos" element={<Oportunidades />} />
          <Route path="/app/corretor/captacao" element={<Oportunidades />} />
          <Route path="/app/corretor/carteira" element={<Carteira />} />
          <Route path="/app/corretor/carteira/:id" element={<CarteiraFicha />} />
          <Route path="/app/corretor/meu-site" element={<SiteCorretor />} />
          <Route path="/app/corretor/marketing" element={<Carteira />} />
          <Route path="/app/corretor/leads" element={<CrmContatos />} />
          <Route path="/app/corretor/comercial" element={<PersonalKanban />} />
          <Route path="/app/corretor/leads/:id" element={<CrmContatoFicha />} />
          <Route path="/app/corretor/agenda" element={<Calendario />} />
          <Route path="/app/corretor/propostas" element={<Propostas />} />
          <Route path="/app/corretor/mapa" element={<MapaInteligente />} />
          <Route path="/app/corretor/ia" element={<IAInteligente />} />
        </Route>

        <Route element={<PrivateRoute><ProductLayout product="imobiliaria" /></PrivateRoute>}>
          <Route path="/app/imobiliaria/dashboard" element={<ImobiliariaDashboard />} />
          <Route path="/app/imobiliaria/imoveis" element={<Carteira />} />
          <Route path="/app/imobiliaria/imoveis/:id" element={<CarteiraFicha />} />
          <Route path="/app/imobiliaria/clientes" element={<CrmContatos />} />
          <Route path="/app/imobiliaria/clientes/:id" element={<CrmContatoFicha />} />
          <Route path="/app/imobiliaria/proprietarios" element={<CrmContatos />} />
          <Route path="/app/imobiliaria/proprietarios/:id" element={<CrmContatoFicha />} />
          <Route path="/app/imobiliaria/atendimentos" element={<Atendimentos />} />
          <Route path="/app/imobiliaria/captacoes" element={<Oportunidades />} />
          <Route path="/app/imobiliaria/crm" element={<CrmKanban />} />
          <Route path="/app/imobiliaria/crm/:id" element={<CrmDealDetail />} />
          <Route path="/app/imobiliaria/roleta" element={<Roleta />} />
          <Route path="/app/imobiliaria/propostas" element={<Propostas />} />
          <Route path="/app/imobiliaria/equipes" element={<Equipes />} />
          <Route path="/app/imobiliaria/filiais" element={<Filiais />} />
          <Route path="/app/imobiliaria/comissoes" element={<Comissoes />} />
          <Route path="/app/imobiliaria/contratos" element={<Contratos />} />
          <Route path="/app/imobiliaria/site" element={<AgencySite />} />
          <Route path="/app/imobiliaria/mapa" element={<MapaInteligente />} />
          <Route path="/app/imobiliaria/relatorios" element={<ImobiliariaDashboard />} />
        </Route>

        <Route element={<PrivateRoute><ProductLayout product="house_flipper" /></PrivateRoute>}>
          <Route path="/app/house-flipper/dashboard" element={<FlipperDashboard />} />
          <Route path="/app/house-flipper/oportunidades" element={<Oportunidades />} />
          <Route path="/app/house-flipper/ia" element={<IAInteligente />} />
          <Route path="/app/house-flipper/ia/analise/:id" element={<AnaliseMercadoOportunidade />} />
          <Route path="/app/house-flipper/leiloes" element={<Auctions />} />
          <Route path="/app/house-flipper/mapa" element={<MapaInteligente />} />
          <Route path="/app/house-flipper/analises" element={<Oportunidades />} />
          <Route path="/app/house-flipper/simulador" element={<Financeiro />} />
          <Route path="/app/house-flipper/imoveis" element={<AnaliseImovel />} />
          <Route path="/app/house-flipper/imoveis/:id/3d" element={<PropertyViewer />} />
          <Route path="/app/house-flipper/projetos" element={<Projetos />} />
          <Route path="/app/house-flipper/projetos-3d" element={<AnaliseImovel />} />
          <Route path="/app/house-flipper/reformas" element={<Obras />} />
          <Route path="/app/house-flipper/financeiro" element={<Financeiro />} />
          <Route path="/app/house-flipper/despesas" element={<Despesas />} />
          <Route path="/app/house-flipper/obras" element={<Obras />} />
          <Route path="/app/house-flipper/tarefas" element={<Tarefas />} />
          <Route path="/app/house-flipper/fornecedores" element={<Fornecedores />} />
          <Route path="/app/house-flipper/documentos" element={<Documentos />} />
          <Route path="/app/house-flipper/relatorios" element={<Relatorios />} />
          <Route path="/app/house-flipper/calendario" element={<Calendario />} />
          <Route path="/app/house-flipper/importar-itbi" element={<ImportarItbi />} />
          <Route path="/app/house-flipper/analises-financeiras/:id" element={<CentralFinanceira />} />
          <Route path="/app/house-flipper/analises-financeiras/:id/wizard" element={<WizardFinanceiro />} />
        </Route>

        <Route path="/inicio" element={<Navigate to="/" replace />} />
        <Route path="/leads" element={<Navigate to="/app/corretor/pesquisar" replace />} />
        <Route path="/carteira" element={<Navigate to="/app/corretor/carteira" replace />} />
        <Route path="/carteira/:id" element={<RedirectParam to="/app/corretor/carteira/:id" />} />
        <Route path="/site-corretor" element={<Navigate to="/app/corretor/meu-site" replace />} />
        <Route path="/crm" element={<Navigate to="/app/corretor/leads" replace />} />
        <Route path="/mapa" element={<Navigate to="/app/corretor/mapa" replace />} />
        <Route path="/projetos" element={<Navigate to="/app/house-flipper/projetos" replace />} />
        <Route path="/imoveis" element={<Navigate to="/app/house-flipper/imoveis" replace />} />
        <Route path="/imoveis/:id/3d" element={<RedirectParam to="/app/house-flipper/imoveis/:id/3d" />} />
        <Route path="/obras" element={<Navigate to="/app/house-flipper/obras" replace />} />
        <Route path="/financeiro" element={<Navigate to="/app/house-flipper/financeiro" replace />} />
        <Route path="/despesas" element={<Navigate to="/app/house-flipper/despesas" replace />} />
        <Route path="/tarefas" element={<Navigate to="/app/house-flipper/tarefas" replace />} />
        <Route path="/documentos" element={<Navigate to="/app/house-flipper/documentos" replace />} />
        <Route path="/relatorios" element={<Navigate to="/app/house-flipper/relatorios" replace />} />
        <Route path="/calendario" element={<Navigate to="/app/house-flipper/calendario" replace />} />
        <Route path="/ia-inteligente" element={<Navigate to="/app/house-flipper/ia" replace />} />
        <Route path="/analises-financeiras/:id" element={<RedirectParam to="/app/house-flipper/analises-financeiras/:id" />} />
        <Route path="/analises-financeiras/:id/wizard" element={<RedirectParam to="/app/house-flipper/analises-financeiras/:id/wizard" />} />
        <Route path="/configuracoes" element={<Navigate to="/app/configuracoes" replace />} />
        <Route path="/app-ajuda" element={<Navigate to="/app/ajuda" replace />} />
        <Route path="*" element={<ErrorPage />} />
      </Routes>
    </Suspense>
  );
}
