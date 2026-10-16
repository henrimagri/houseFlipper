import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';

const data = [
  { name: 'Compra de Imóveis', valor: 720000, color: '#3b82f6' },
  { name: 'Reformas', valor: 328500, color: '#10b981' },
  { name: 'Custos Operacionais', valor: 125250, color: '#f59e0b' },
  { name: 'Outros Custos', valor: 75000, color: '#8b5cf6' },
];

const total = data.reduce((acc, d) => acc + d.valor, 0);
const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ResumoFinanceiro() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
      <div className="mb-4">
        <h3 className="text-base font-semibold text-gray-900">Resumo Financeiro</h3>
        <span className="text-xs text-gray-500">(Todos os projetos)</span>
      </div>
      <div className="flex items-center gap-4">
        <div className="relative w-44 h-44 flex-shrink-0">
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
            <span className="text-xs text-gray-500">Total Investido</span>
            <span className="text-lg font-bold text-gray-900">R$ 1,248M</span>
          </div>
        </div>
        <div className="flex-1 space-y-3">
          {data.map((d) => {
            const pct = ((d.valor / total) * 100).toFixed(1).replace('.', ',');
            return (
              <div key={d.name} className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                  <span className="w-2.5 h-2.5 rounded-full" style={{ background: d.color }} />
                  <span className="text-gray-700">{d.name}</span>
                </div>
                <div className="text-right">
                  <span className="font-semibold text-gray-900">{fmtBRL(d.valor)}</span>
                  <span className="ml-2 text-xs text-gray-500">{pct}%</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
