import { Eye } from 'lucide-react';
import { Link } from 'react-router-dom';

interface Projeto {
  id: string;
  nome: string;
  numero: string;
  cidade: string;
  investido: number;
  estimado: number;
  roi: number | null;
  status: string;
  statusColor: string;
}


const fmtBRL = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ProjetosTable({ projetos }: { projetos: Projeto[] }) {
  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-700 p-5 shadow-xs">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-semibold text-gray-900 dark:text-slate-100">Projetos em Andamento</h3>
        <a href="/projetos" className="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">Ver todas</a>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-gray-500 dark:text-slate-400 border-b border-gray-100 dark:border-slate-800">
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
              <tr key={p.id} className="border-b border-gray-50 dark:border-slate-800 last:border-0 hover:bg-gray-50/50 dark:hover:bg-slate-800">
                <td className="py-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-linear-to-br from-amber-100 to-amber-200 flex items-center justify-center text-lg">🏠</div>
                    <div>
                      <div className="font-semibold text-gray-900 dark:text-slate-100 text-sm">{p.nome}</div>
                      <div className="text-xs text-gray-500 dark:text-slate-400">Projeto {p.numero}</div>
                    </div>
                  </div>
                </td>
                <td className="py-3 text-gray-700 dark:text-slate-200">{p.cidade}</td>
                <td className="py-3 text-gray-900 dark:text-slate-100 font-medium">{fmtBRL(p.investido)}</td>
                <td className="py-3 text-gray-900 dark:text-slate-100 font-medium">{fmtBRL(p.estimado)}</td>
                <td className="py-3 text-emerald-600 dark:text-emerald-400 font-semibold">{p.roi === null ? '—' : `${p.roi.toFixed(1).replace('.', ',')}%`}</td>
                <td className="py-3">
                  <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${p.statusColor}`}>{p.status}</span>
                </td>
                <td className="py-3">
                  <Link to={`/projetos?projeto=${p.id}`} aria-label={`Abrir projeto ${p.nome}`} className="inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                    <Eye className="w-4 h-4" />
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
