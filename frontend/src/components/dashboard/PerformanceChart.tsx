import { ChartNoAxesCombined } from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend, CartesianGrid } from 'recharts';


const fmt = (v: number) => `R$ ${(v / 1000).toFixed(0)}k`;

export default function PerformanceChart({ data }: { data: { mes: string; investimento: number; faturamento: number; lucro: number }[] }) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-5 shadow-xs">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-base font-semibold text-gray-900 dark:text-slate-100"><ChartNoAxesCombined aria-hidden="true" />Movimentação mensal</h3>
          <span className="text-xs text-gray-500 dark:text-slate-400">(Últimos 6 meses)</span>
        </div>

      </div>
      <div className="h-72">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" vertical={false} />
            <XAxis dataKey="mes" tick={{ fontSize: 11, fill: '#6b7280' }} axisLine={false} tickLine={false} />
            <YAxis tickFormatter={fmt} tick={{ fontSize: 11, fill: '#6b7280' }} axisLine={false} tickLine={false} />
            <Tooltip formatter={(v: number) => `R$ ${v.toLocaleString('pt-BR')}`} />
            <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
            <Line type="monotone" dataKey="investimento" name="Investimento" stroke="#3b82f6" strokeWidth={2} dot={{ r: 3 }} />
            <Line type="monotone" dataKey="faturamento" name="Faturamento" stroke="#10b981" strokeWidth={2} dot={{ r: 3 }} />
            <Line type="monotone" dataKey="lucro" name="Saldo por competência" stroke="var(--fc-accent)" strokeWidth={2} dot={{ r: 3 }} />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
