import { WalletCards } from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';



const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ResumoFinanceiro({ data }: { data: { name: string; valor: number; color: string }[] }) { const total = data.reduce((sum, item) => sum + item.valor, 0);
  return (
    <div className="fc-finance-summary min-w-0 bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-5 shadow-xs">
      <div className="mb-4">
        <h3 className="text-base font-semibold text-gray-900 dark:text-slate-100"><WalletCards aria-hidden="true" />Resumo Financeiro</h3>
        <span className="text-xs text-gray-500 dark:text-slate-400">(Imóveis e despesas da empresa)</span>
      </div>
      <div className="fc-finance-summary-layout">
        <div className="relative w-44 h-44 shrink-0">
          <ResponsiveContainer>
            <PieChart>
              <Pie data={data} dataKey="valor" innerRadius={55} outerRadius={80} paddingAngle={2} stroke="none">
                {data.map((d) => (
                  <Cell key={d.name} fill={d.color} />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>
          <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <span className="text-xs text-gray-500 dark:text-slate-400">Total Investido</span>
            <span className="text-lg font-bold text-gray-900 dark:text-slate-100">{fmtBRL(total)}</span>
          </div>
        </div>
        <div className="w-full min-w-0 flex-1 space-y-3">
          {data.map((d) => {
            const pct = (total > 0 ? (d.valor / total) * 100 : 0).toFixed(1).replace('.', ',');
            return (
              <div key={d.name} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <div className="flex items-center gap-2">
                  <span className="w-2.5 h-2.5 shrink-0 rounded-full" style={{ background: d.color }} />
                  <span className="text-gray-700 dark:text-slate-200">{d.name}</span>
                </div>
                <div className="ml-auto text-right">
                  <span className="font-semibold text-gray-900 dark:text-slate-100">{fmtBRL(d.valor)}</span>
                  <span className="ml-2 text-xs text-gray-500 dark:text-slate-400">{pct}%</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
