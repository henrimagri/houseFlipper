import { MoreVertical } from 'lucide-react';

interface Projeto {
  id: string;
  nome: string;
  numero: string;
  cidade: string;
  investido: number;
  estimado: number;
  roi: number;
  status: string;
  statusColor: string;
}

const projetos: Projeto[] = [
  { id: '1', nome: 'Casa Jardim Paulista', numero: '#2024-001', cidade: 'São Paulo, SP', investido: 135000, estimado: 235000, roi: 32.1, status: 'Em Reforma', statusColor: 'bg-blue-100 text-blue-700' },
  { id: '2', nome: 'Sobrado Vila Madalena', numero: '#2024-002', cidade: 'São Paulo, SP', investido: 280000, estimado: 485000, roi: 29.8, status: 'Elétrica', statusColor: 'bg-yellow-100 text-yellow-700' },
  { id: '3', nome: 'Casa Campo Belo', numero: '#2024-003', cidade: 'São Paulo, SP', investido: 175000, estimado: 290000, roi: 26.3, status: 'Acabamento', statusColor: 'bg-emerald-100 text-emerald-700' },
  { id: '4', nome: 'Casa Interlagos', numero: '#2024-004', cidade: 'São Paulo, SP', investido: 210000, estimado: 360000, roi: 28.6, status: 'Em Reforma', statusColor: 'bg-blue-100 text-blue-700' },
  { id: '5', nome: 'Sobrado Moema', numero: '#2024-005', cidade: 'São Paulo, SP', investido: 320000, estimado: 550000, roi: 30.2, status: 'Demolição', statusColor: 'bg-rose-100 text-rose-700' },
];

const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ProjetosTable() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-semibold text-gray-900">Projetos em Andamento</h3>
        <a href="/projetos" className="text-xs font-medium text-primary-600 hover:underline">Ver todas</a>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
              <th className="py-3 font-medium">Projeto</th>
              <th className="py-3 font-medium">Localização</th>
              <th className="py-3 font-medium">Investido</th>
              <th className="py-3 font-medium">Valor Estimado de Venda</th>
              <th className="py-3 font-medium">ROI Estimado</th>
              <th className="py-3 font-medium">Status</th>
              <th className="py-3"></th>
            </tr>
          </thead>
          <tbody>
            {projetos.map((p) => (
              <tr key={p.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                <td className="py-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 flex items-center justify-center text-lg">🏠</div>
                    <div>
                      <div className="font-semibold text-gray-900 text-sm">{p.nome}</div>
                      <div className="text-xs text-gray-500">Projeto {p.numero}</div>
                    </div>
                  </div>
                </td>
                <td className="py-3 text-gray-700">{p.cidade}</td>
                <td className="py-3 text-gray-900 font-medium">{fmtBRL(p.investido)}</td>
                <td className="py-3 text-gray-900 font-medium">{fmtBRL(p.estimado)}</td>
                <td className="py-3 text-emerald-600 font-semibold">{p.roi.toFixed(1).replace('.', ',')}%</td>
                <td className="py-3">
                  <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${p.statusColor}`}>{p.status}</span>
                </td>
                <td className="py-3">
                  <button className="text-gray-400 hover:text-gray-700">
                    <MoreVertical className="w-4 h-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
