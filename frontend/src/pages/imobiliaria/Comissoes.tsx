import { useEffect, useState } from 'react';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import StatusBadge from '@/ui/StatusBadge';
import ErrorState from '@/ui/ErrorState';

const NEXT: Record<string, string[]> = {
  projected: ['approved', 'cancelled'],
  approved: ['awaiting_payment', 'cancelled'],
  awaiting_payment: ['partially_paid', 'paid', 'cancelled'],
  partially_paid: ['paid', 'cancelled'],
  paid: [],
  cancelled: [],
};

export default function Comissoes() {
  const [data, setData] = useState<any>(null);
  const [broker, setBroker] = useState('60');
  const [agency, setAgency] = useState('40');
  const [error, setError] = useState('');

  async function load() {
    const { data: payload } = await api.get('/imobiliaria/commissions');
    setData(payload);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Comissões indisponíveis.')); }, []);

  async function save() {
    await api.post('/imobiliaria/commission-rules', { broker_percent: Number(broker), agency_percent: Number(agency), branch_percent: 0 });
    await load();
  }
  async function change(id: number, status: string) {
    try {
      await api.patch(`/imobiliaria/commissions/${id}`, { status });
      await load();
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Transição recusada.');
    }
  }

  if (!data && !error) return <p role="status">Carregando comissões...</p>;
  return (
    <div className="space-y-4">
      <PageHeader title="Comissões" subtitle="Valores projetados nunca são marcados como pagos automaticamente." />
      {error && <ErrorState message={error} />}
      <div className="flex flex-wrap gap-2">
        <label className="text-xs">% corretor<input className="input mt-1" value={broker} onChange={(e) => setBroker(e.target.value)} /></label>
        <label className="text-xs">% imobiliária<input className="input mt-1" value={agency} onChange={(e) => setAgency(e.target.value)} /></label>
        <button type="button" onClick={save} className="self-end px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">Salvar regra</button>
      </div>
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {(data?.items ?? []).map((row: any) => (
          <li key={row.id} className="p-4 text-sm flex flex-wrap items-center gap-3">
            <span>{row.kind} · {(row.amount_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
            <StatusBadge value={row.status} />
            {(NEXT[row.status] ?? []).map((status) => (
              <button key={status} type="button" onClick={() => change(row.id, status)} className="border px-2 py-1 rounded text-xs">{status}</button>
            ))}
          </li>
        ))}
        {(data?.items ?? []).length === 0 && <li className="p-4 text-sm text-gray-500">Nenhuma comissão projetada. Feche um negócio no CRM.</li>}
      </ul>
    </div>
  );
}
