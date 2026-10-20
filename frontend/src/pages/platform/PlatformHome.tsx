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
    </div>
  );
}
