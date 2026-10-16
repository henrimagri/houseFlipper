import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend, CartesianGrid } from 'recharts';

const data = [
  { mes: 'Dez/23', investimento: 120000, faturamento: 95000, lucro: 30000 },
  { mes: 'Jan/24', investimento: 180000, faturamento: 220000, lucro: 60000 },
  { mes: 'Fev/24', investimento: 240000, faturamento: 310000, lucro: 95000 },
  { mes: 'Mar/24', investimento: 320000, faturamento: 420000, lucro: 130000 },
  { mes: 'Abr/24', investimento: 380000, faturamento: 520000, lucro: 160000 },
  { mes: 'Mai/24', investimento: 450000, faturamento: 590000, lucro: 195000 },
];

const fmt = (v: number) => `R$ ${(v / 1000).toFixed(0)}k`;

export default function PerformanceChart() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-base font-semibold text-gray-900">Performance</h3>
          <span className="text-xs text-gray-500">(Últimos 6 meses)</span>
        </div>
        <select className="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700">
          <option>6 meses</option>
          <option>12 meses</option>
        </select>
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
            <Line type="monotone" dataKey="lucro" name="Lucro Líquido" stroke="#8b5cf6" strokeWidth={2} dot={{ r: 3 }} />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
