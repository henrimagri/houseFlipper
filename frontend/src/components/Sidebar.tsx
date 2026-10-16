import { NavLink } from 'react-router-dom';
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
  MessageSquare,
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
  { to: '/imoveis', label: 'Análise de Imóveis', icon: Home },
  { to: '/obras', label: 'Obras', icon: HardHat },
  { to: '/financeiro', label: 'Financeiro', icon: DollarSign },
  { to: '/despesas', label: 'Despesas', icon: DollarSign },
  { to: '/tarefas', label: 'Tarefas', icon: CheckSquare },
  { to: '/fornecedores', label: 'Fornecedores', icon: Users },
  { to: '/documentos', label: 'Documentos', icon: FileText },
  { to: '/relatorios', label: 'Relatórios', icon: BarChart3 },
  { to: '/calendario', label: 'Calendário', icon: CalendarDays },
  { to: '/mensagens', label: 'Mensagens', icon: MessageSquare, badge: 5 },
  { to: '/configuracoes', label: 'Configurações', icon: Settings },
];

export default function Sidebar() {
  return (
    <aside className="w-64 bg-white border-r border-gray-200 flex flex-col h-screen sticky top-0">
      <div className="px-6 py-5 flex items-center gap-2 border-b border-gray-100">
        <div className="w-9 h-9 bg-primary-600 rounded-lg flex items-center justify-center">
          <Hexagon className="w-5 h-5 text-white" />
        </div>
        <div>
          <div className="font-bold text-gray-900 leading-tight">FlipControl</div>
          <div className="text-[11px] text-gray-500">House Flipper</div>
        </div>
      </div>

      <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        {menu.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-600 text-white shadow-sm'
                    : 'text-gray-700 hover:bg-gray-100'
                }`
              }
            >
              <Icon className="w-[18px] h-[18px]" />
              <span className="flex-1">{item.label}</span>
              {item.badge && (
                <span className="bg-red-500 text-white text-[11px] font-semibold px-1.5 py-0.5 rounded-full">
                  {item.badge}
                </span>
              )}
            </NavLink>
          );
        })}
      </nav>

      <div className="px-4 py-4 border-t border-gray-100">
        <div className="bg-gray-50 rounded-xl p-3 space-y-2">
          <div className="text-[11px] font-semibold text-gray-500">Resumo rápido</div>
          <div className="flex justify-between text-xs">
            <span className="text-gray-500">Investido Total</span>
            <span className="font-semibold text-gray-900">R$ 1.248.750,00</span>
          </div>
          <div className="flex justify-between text-xs">
            <span className="text-gray-500">Lucro Líquido Tot.</span>
            <span className="font-semibold text-emerald-600">R$ 642.380,00</span>
          </div>
          <div className="flex justify-between text-xs">
            <span className="text-gray-500">ROI Médio</span>
            <span className="font-semibold text-primary-600">28,4%</span>
          </div>
        </div>
      </div>
    </aside>
  );
}
