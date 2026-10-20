import { LucideIcon } from 'lucide-react';

interface KpiCardProps {
  titulo: string;
  valor: string;
  rodape?: string;
  rodapeColor?: string;
  hint?: string;
  Icon: LucideIcon;
  iconBg: string;
  iconColor: string;
}

export default function KpiCard({
  titulo,
  valor,
  rodape,
  rodapeColor = 'text-gray-500 dark:text-slate-400',
  hint,
  Icon,
  iconBg,
  iconColor,
}: KpiCardProps) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-5 shadow-xs" title={hint}>
      <div className="flex items-start justify-between">
        <span className="text-xs font-medium text-gray-500 dark:text-slate-400">{titulo}</span>
        <div className={`w-9 h-9 rounded-lg flex items-center justify-center ${iconBg}`}>
          <Icon className={`w-[18px] h-[18px] ${iconColor}`} />
        </div>
      </div>
      <div className="mt-3 text-2xl font-bold text-gray-900 dark:text-slate-100">{valor}</div>
      {rodape && <div className={`mt-2 text-xs ${rodapeColor}`}>{rodape}</div>}
    </div>
  );
}
