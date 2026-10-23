import { ReactNode } from 'react';
import { Building2, CalendarDays, ChartNoAxesCombined, CircleHelp, FileText, FolderKanban, Hammer, House, Inbox, MapPinned, PanelTop, Search, Settings2, Sparkles, Users, Wallet, type LucideIcon } from 'lucide-react';

function titleIcon(title: string): LucideIcon {
  const value = title.toLocaleLowerCase('pt-BR');
  return [
    [/configura|aparência|fontes de dados/, Settings2], [/cliente|contato|proprietário|equipe|corretor/, Users],
    [/tarefa|funil|organizador|projeto/, FolderKanban], [/calend|agenda|visita/, CalendarDays],
    [/finance|despesa|comiss|assinatura|pagamento/, Wallet], [/análise|relatório|investimento/, ChartNoAxesCombined],
    [/imóve|carteira|imobiliária/, House], [/mapa|localiza/, MapPinned], [/pesquis|oportunidade|leil/, Search],
    [/intelig|consultor|assistente/, Sparkles], [/atendimento/, Inbox], [/obra|reforma|fornecedor/, Hammer],
    [/documento|proposta|contrato/, FileText], [/ajuda/, CircleHelp], [/operação/, Building2],
  ].find(([pattern]) => (pattern as RegExp).test(value))?.[1] as LucideIcon ?? PanelTop;
}

export default function PageHeader({ title, subtitle, actions, icon }: { title: string; subtitle?: string; actions?: ReactNode; icon?: LucideIcon }) {
  const Icon = icon ?? titleIcon(title);
  return <div className="fc-page-header flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
    <div className="flex min-w-0 items-start gap-3"><span className="fc-page-icon"><Icon size={21} strokeWidth={1.7} aria-hidden="true" /></span><div className="min-w-0">
      <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-slate-100">{title}</h1>
      {subtitle && <p className="text-sm text-gray-500 dark:text-slate-400 mt-1 max-w-3xl">{subtitle}</p>}
    </div></div>
    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
  </div>;
}
