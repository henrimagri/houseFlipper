import { Wallet, ShoppingCart, CheckCircle2, FileText, Home } from 'lucide-react';


export default function AtividadesRecentes({ atividades }: { atividades: { titulo: string; desc: string; tempo: string }[] }) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-5 shadow-xs h-full">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-semibold text-gray-900 dark:text-slate-100">Atividades Recentes</h3>
        <a href="/despesas" className="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">Ver todas</a>
      </div>
      <div className="space-y-4">
        {atividades.map((a, idx) => {
          const Icon = Wallet;
          return (
            <div key={idx} className="flex items-start gap-3">
              <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 bg-violet-100 dark:bg-violet-900`}>
                <Icon className={`w-[18px] h-[18px] text-violet-600 dark:text-violet-400`} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="text-sm font-semibold text-gray-900 dark:text-slate-100">{a.titulo}</div>
                  <div className="text-[11px] text-gray-400 whitespace-nowrap">{a.tempo}</div>
                </div>
                <div className="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{a.desc}</div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
