import { NavLink, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import api from '@/lib/api';
import { clearAuthToken } from '@/lib/authToken';
import {
  LayoutDashboard,
  Briefcase,
  Home,
  HardHat,
  DollarSign,
  CheckSquare,
  Users,
  FileText,
  BarChart3,
  CalendarDays,
  Target,
  Settings,
  Hexagon,
  Sparkles,
  UploadCloud,
} from 'lucide-react';

const menu = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/ia-inteligente', label: 'IA Inteligente', icon: Sparkles },
  { to: '/importar-itbi', label: 'Importar ITBI', icon: UploadCloud },
  { to: '/leads', label: 'Leads & Oportunidades', icon: Target },
  { to: '/projetos', label: 'Projetos', icon: Briefcase },
  { to: '/imoveis', label: 'Imóveis', icon: Home },
  { to: '/obras', label: 'Obras', icon: HardHat },
  { to: '/financeiro', label: 'Financeiro', icon: DollarSign },
  { to: '/despesas', label: 'Despesas', icon: DollarSign },
  { to: '/tarefas', label: 'Tarefas', icon: CheckSquare },
  { to: '/fornecedores', label: 'Fornecedores', icon: Users },
  { to: '/documentos', label: 'Documentos', icon: FileText },
  { to: '/relatorios', label: 'Relatórios', icon: BarChart3 },
  { to: '/calendario', label: 'Calendário', icon: CalendarDays },
  { to: '/configuracoes', label: 'Configurações', icon: Settings },
];

export default function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const navigate = useNavigate();
  const [logoutError, setLogoutError] = useState('');
  const [loggingOut, setLoggingOut] = useState(false);
  const [summary, setSummary] = useState<{ total_invested: number; realized_profit: number; realized_roi: number } | null>(null);
  const [summaryError, setSummaryError] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    api.get('/dashboard', { signal: controller.signal }).then(({ data }) => setSummary(data.kpis)).catch(() => { if (!controller.signal.aborted) setSummaryError(true); });
    return () => controller.abort();
  }, []);
  const summaryMoney = (value?: number) => value === undefined ? '—' : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  async function logout() {
    setLoggingOut(true);
    setLogoutError('');
    try {
      await api.post('/auth/logout');
      clearAuthToken();
      navigate('/login', { replace: true });
    } catch (error: any) {
      if (error.response?.status === 401) {
        clearAuthToken();
        navigate('/login', { replace: true });
      } else {
        setLogoutError('Não foi possível encerrar a sessão. Tente novamente.');
      }
    } finally {
      setLoggingOut(false);
    }
  }
  return (
    <aside className="w-64 shrink-0 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-700 flex flex-col h-screen sticky top-0">
      <div className="px-6 py-5 flex items-center gap-2 border-b border-gray-100 dark:border-slate-800">
        <div className="w-9 h-9 bg-primary-600 rounded-lg flex items-center justify-center">
          <Hexagon className="w-5 h-5 text-white" />
        </div>
        <div>
          <div className="font-bold text-gray-900 dark:text-slate-100 leading-tight">FlipControl</div>
          <div className="text-[11px] text-gray-500 dark:text-slate-400">House Flipper</div>
        </div>
      </div>

      <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        {menu.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onNavigate}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-600 text-white shadow-xs'
                    : 'text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800'
                }`
              }
            >
              <Icon className="w-[18px] h-[18px]" />
              <span className="flex-1">{item.label}</span>
            </NavLink>
          );
        })}
      </nav>

      <div className="px-4 py-4 border-t border-gray-100 dark:border-slate-800">
        <button onClick={logout} disabled={loggingOut} className="w-full mb-3 px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 disabled:opacity-60">{loggingOut ? 'Saindo...' : 'Sair da conta'}</button>
        {logoutError && <p role="alert" className="text-sm text-red-700 dark:text-red-300 mb-3">{logoutError}</p>}
        <div className="bg-gray-50 dark:bg-slate-950 rounded-xl p-3 space-y-2">
          <div className="text-[11px] font-semibold text-gray-500 dark:text-slate-400">Resumo rápido</div>
          {summaryError && <p role="status" className="text-xs text-gray-500 dark:text-slate-400">Resumo indisponível</p>}
          <div className="flex justify-between text-xs">
            <span className="text-gray-500 dark:text-slate-400">Investido Total</span>
            <span className="font-semibold text-gray-900 dark:text-slate-100">{summaryMoney(summary?.total_invested)}</span>
          </div>
          <div className="flex justify-between text-xs">
            <span className="text-gray-500 dark:text-slate-400">Lucro Líquido Tot.</span>
            <span className="font-semibold text-emerald-600 dark:text-emerald-400">{summaryMoney(summary?.realized_profit)}</span>
          </div>
          <div className="flex justify-between text-xs">
            <span className="text-gray-500 dark:text-slate-400">ROI realizado</span>
            <span className="font-semibold text-primary-600 dark:text-primary-400">{typeof summary?.realized_roi === 'number' ? `${summary.realized_roi.toLocaleString('pt-BR')}%` : '—'}</span>
          </div>
        </div>
      </div>
    </aside>
  );
}
