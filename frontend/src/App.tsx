import { Navigate, Route, Routes } from 'react-router-dom';
import AppLayout from '@/components/AppLayout';
import Dashboard from '@/pages/Dashboard';
import Login from '@/pages/Login';
import Obras from '@/pages/Obras';
import AnaliseImovel from '@/pages/AnaliseImovel';
import Despesas from '@/pages/Despesas';
import Documentos from '@/pages/Documentos';
import Relatorios from '@/pages/Relatorios';
import Calendario from '@/pages/Calendario';
import Mensagens from '@/pages/Mensagens';
import Fornecedores from '@/pages/Fornecedores';
import Projetos from '@/pages/Projetos';
import PageStub from '@/pages/PageStub';
import Oportunidades from '@/pages/Oportunidades';
import CentralFinanceira from '@/pages/CentralFinanceira';
import WizardFinanceiro from '@/pages/WizardFinanceiro';
import IAInteligente from '@/pages/IAInteligente';
import AnaliseMercadoOportunidade from '@/pages/AnaliseMercadoOportunidade';
import ImportarItbi from '@/pages/ImportarItbi';
import Configuracoes from '@/pages/Configuracoes';

function PrivateRoute({ children }: { children: JSX.Element }) {
  const token = localStorage.getItem('token');
  return token ? children : <Navigate to="/login" replace />;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
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
        <Route path="/obras" element={<Obras />} />
        <Route path="/financeiro" element={<PageStub titulo="Financeiro" />} />
        <Route path="/despesas" element={<Despesas />} />
        <Route path="/tarefas" element={<PageStub titulo="Tarefas" />} />
        <Route path="/fornecedores" element={<Fornecedores />} />
        <Route path="/documentos" element={<Documentos />} />
        <Route path="/relatorios" element={<Relatorios />} />
        <Route path="/calendario" element={<Calendario />} />
        <Route path="/leads" element={<Oportunidades />} />
        <Route path="/analises-financeiras/:id" element={<CentralFinanceira />} />
        <Route path="/analises-financeiras/:id/wizard" element={<WizardFinanceiro />} />
        <Route path="/mensagens" element={<Mensagens />} />
        <Route path="/configuracoes" element={<Configuracoes />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
