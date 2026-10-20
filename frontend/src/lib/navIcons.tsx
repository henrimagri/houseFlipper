import {
  LayoutDashboard, Building2, House, Users, UserRound, MessagesSquare, CalendarDays, Map,
  ChartNoAxesCombined, Wallet, ClipboardList, FileText, Settings, BriefcaseBusiness,
  Layers3, HandCoins, Wrench, Search, Heart, Globe, Sparkles, Landmark, Box, ListTodo,
  FolderKanban, CreditCard, HelpCircle, GanttChart, Import,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const BY_PATH: Record<string, LucideIcon> = {
  '/app/inicio': LayoutDashboard,
  '/app/produtos': Layers3,
  '/app/assinaturas': CreditCard,
  '/app/configuracoes': Settings,
  '/app/corretor/dashboard': LayoutDashboard,
  '/app/corretor/pesquisar': Search,
  '/app/corretor/favoritos': Heart,
  '/app/corretor/captacao': ClipboardList,
  '/app/corretor/carteira': Wallet,
  '/app/corretor/meu-site': Globe,
  '/app/corretor/marketing': BriefcaseBusiness,
  '/app/corretor/leads': Users,
  '/app/corretor/agenda': CalendarDays,
  '/app/corretor/propostas': FileText,
  '/app/corretor/mapa': Map,
  '/app/corretor/ia': Sparkles,
  '/app/imobiliaria/dashboard': LayoutDashboard,
  '/app/imobiliaria/imoveis': Building2,
  '/app/imobiliaria/captacoes': ClipboardList,
  '/app/imobiliaria/clientes': Users,
  '/app/imobiliaria/proprietarios': UserRound,
  '/app/imobiliaria/atendimentos': MessagesSquare,
  '/app/imobiliaria/crm': FolderKanban,
  '/app/imobiliaria/propostas': FileText,
  '/app/imobiliaria/equipes': Users,
  '/app/imobiliaria/filiais': Landmark,
  '/app/imobiliaria/comissoes': HandCoins,
  '/app/imobiliaria/roleta': GanttChart,
  '/app/imobiliaria/contratos': FileText,
  '/app/imobiliaria/site': Globe,
  '/app/imobiliaria/mapa': Map,
  '/app/imobiliaria/relatorios': ChartNoAxesCombined,
  '/app/house-flipper/dashboard': LayoutDashboard,
  '/app/house-flipper/oportunidades': Search,
  '/app/house-flipper/mapa': Map,
  '/app/house-flipper/leiloes': Landmark,
  '/app/house-flipper/ia': Sparkles,
  '/app/house-flipper/analises': ChartNoAxesCombined,
  '/app/house-flipper/simulador': Wallet,
  '/app/house-flipper/imoveis': House,
  '/app/house-flipper/projetos': Box,
  '/app/house-flipper/obras': Wrench,
  '/app/house-flipper/reformas': Wrench,
  '/app/house-flipper/projetos-3d': Layers3,
  '/app/house-flipper/financeiro': Wallet,
  '/app/house-flipper/despesas': CreditCard,
  '/app/house-flipper/tarefas': ListTodo,
  '/app/house-flipper/documentos': FileText,
  '/app/house-flipper/relatorios': ChartNoAxesCombined,
  '/app/house-flipper/calendario': CalendarDays,
  '/app/house-flipper/importar-itbi': Import,
};

export function navIcon(path: string): LucideIcon {
  return BY_PATH[path] ?? ClipboardList;
}

export function HelpIcon() {
  return HelpCircle;
}
