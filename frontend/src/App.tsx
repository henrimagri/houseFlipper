import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { getAuthToken } from '@/lib/authToken';
import AppLayout from '@/components/AppLayout';
import ErrorPage from '@/pages/ErrorPage';

const Dashboard = lazy(() => import('@/pages/Dashboard'));
const Login = lazy(() => import('@/pages/Login'));
const Obras = lazy(() => import('@/pages/Obras'));
const AnaliseImovel = lazy(() => import('@/pages/AnaliseImovel'));
const Despesas = lazy(() => import('@/pages/Despesas'));
const Documentos = lazy(() => import('@/pages/Documentos'));
const Relatorios = lazy(() => import('@/pages/Relatorios'));
const Calendario = lazy(() => import('@/pages/Calendario'));
const Fornecedores = lazy(() => import('@/pages/Fornecedores'));
const Projetos = lazy(() => import('@/pages/Projetos'));
const Financeiro = lazy(() => import('@/pages/Financeiro'));
const Tarefas = lazy(() => import('@/pages/Tarefas'));
const Oportunidades = lazy(() => import('@/pages/Oportunidades'));
const CentralFinanceira = lazy(() => import('@/pages/CentralFinanceira'));
const WizardFinanceiro = lazy(() => import('@/pages/WizardFinanceiro'));
const IAInteligente = lazy(() => import('@/pages/IAInteligente'));
const AnaliseMercadoOportunidade = lazy(() => import('@/pages/AnaliseMercadoOportunidade'));
const ImportarItbi = lazy(() => import('@/pages/ImportarItbi'));
const Configuracoes = lazy(() => import('@/pages/Configuracoes'));
const Register = lazy(() => import('@/pages/Register'));
const VerifyEmail = lazy(() => import('@/pages/VerifyEmail'));
const Ajuda = lazy(() => import('@/pages/Ajuda'));
const ForgotPassword = lazy(() => import('@/pages/ForgotPassword'));
const ResetPassword = lazy(() => import('@/pages/ResetPassword'));
const PropertyViewer = lazy(() => import('@/pages/PropertyViewer'));

function PrivateRoute({ children }: { children: JSX.Element }) {
  const token = getAuthToken();
  return token ? children : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <Suspense fallback={<div role="status" className="min-h-screen grid place-items-center bg-slate-50 text-slate-600 dark:bg-slate-950 dark:text-slate-300">Carregando página...</div>}>
      <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/cadastro" element={<Register />} />
      <Route path="/esqueci-senha" element={<ForgotPassword />} />
      <Route path="/redefinir-senha" element={<ResetPassword />} />
      <Route path="/confirmar-email" element={<VerifyEmail />} />
      <Route
        element={
          <PrivateRoute>
            <AppLayout />
          </PrivateRoute>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="/ia-inteligente" element={<IAInteligente />} />
        <Route path="/ia-inteligente/analise/:id" element={<AnaliseMercadoOportunidade />} />
        <Route path="/importar-itbi" element={<ImportarItbi />} />
        <Route path="/projetos" element={<Projetos />} />
        <Route path="/imoveis" element={<AnaliseImovel />} />
        <Route path="/imoveis/:id/3d" element={<PropertyViewer />} />
        <Route path="/leads/:id/3d" element={<PropertyViewer />} />
        <Route path="/obras" element={<Obras />} />
        <Route path="/financeiro" element={<Financeiro />} />
        <Route path="/despesas" element={<Despesas />} />
        <Route path="/tarefas" element={<Tarefas />} />
        <Route path="/fornecedores" element={<Fornecedores />} />
        <Route path="/documentos" element={<Documentos />} />
        <Route path="/relatorios" element={<Relatorios />} />
        <Route path="/calendario" element={<Calendario />} />
        <Route path="/leads" element={<Oportunidades />} />
        <Route path="/analises-financeiras/:id" element={<CentralFinanceira />} />
        <Route path="/analises-financeiras/:id/wizard" element={<WizardFinanceiro />} />
        <Route path="/configuracoes" element={<Configuracoes />} />
        <Route path="/ajuda" element={<Ajuda />} />
      </Route>
      <Route path="*" element={<ErrorPage />} />
      </Routes>
    </Suspense>
  );
}
