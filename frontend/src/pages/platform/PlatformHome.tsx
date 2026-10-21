import { useEffect, useState } from 'react';
import api from '@/lib/api';

export default function PlatformHome() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  useEffect(() => {
    api.get('/platform/overview').then(({ data: payload }) => setData(payload)).catch((exception: any) => setError(exception.response?.data?.message ?? 'Sem permissão de operação.'));
  }, []);
  if (error) return <p className="text-red-700">{error}</p>;
  if (!data) return <p role="status">Carregando operação...</p>;
  return (
    <div className="space-y-3">
      <h1 className="text-2xl font-bold">Super Admin</h1>
      <p className="text-sm text-gray-500">{data.note}</p>
      <p><a href="/platform/leads" className="text-sm font-semibold text-primary-700">Fila de interessados comerciais</a></p>
      <p>Tenants: {data.tenants}</p>
      <p>Usuários: {data.users}</p>
      <p>Admins de plataforma: {data.platform_admins}</p>
      {data.ai && <section className="mt-6 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
        <h2 className="font-bold">Qualidade da IA · últimos 30 dias</h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-3 text-sm">
          <p>Conversas: <strong>{data.ai.sessions_30d}</strong></p>
          <p>Ferramentas executadas: <strong>{data.ai.tool_runs_30d}</strong></p>
          <p>Falhas: <strong>{data.ai.tool_failures_30d}</strong></p>
          <p>Latência média: <strong>{data.ai.average_latency_ms_30d} ms</strong></p>
          <p>Úteis: <strong>{data.ai.helpful_30d}</strong></p>
          <p>Não úteis: <strong>{data.ai.not_helpful_30d}</strong></p>
        </div>
      </section>}
    </div>
  );
}
