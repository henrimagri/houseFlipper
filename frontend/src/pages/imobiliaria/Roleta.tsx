import { useEffect, useState } from 'react';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';

export default function Roleta() {
  const [data, setData] = useState<any>(null);
  const [strategy, setStrategy] = useState('round_robin');
  const [contactId, setContactId] = useState('');
  const [error, setError] = useState('');
  const [result, setResult] = useState<any>(null);

  async function load() {
    const { data: payload } = await api.get('/imobiliaria/roulette');
    setData(payload);
    setStrategy(payload.data?.strategy ?? 'round_robin');
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Roleta indisponível.')); }, []);

  async function save() {
    await api.put('/imobiliaria/roulette', { strategy });
    await load();
  }
  async function assign() {
    setError('');
    try {
      const { data: payload } = await api.post('/imobiliaria/roulette', { strategy, crm_contact_id: contactId ? Number(contactId) : undefined });
      setResult(payload.data);
      await load();
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível atribuir.');
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title="Roleta de leads" subtitle="Não atribui usuários inativos nem de outra organização. A decisão fica registrada." />
      {error && <ErrorState message={error} />}
      <div className="bg-white dark:bg-slate-900 border rounded-xl p-4 grid md:grid-cols-3 gap-3">
        <label className="text-xs">Estratégia
          <select className="input mt-1" value={strategy} onChange={(e) => setStrategy(e.target.value)}>
            {['round_robin', 'team', 'branch', 'neighborhood', 'availability', 'specialty'].map((item) => <option key={item} value={item}>{item}</option>)}
          </select>
        </label>
        <label className="text-xs">ID do contato<input className="input mt-1" value={contactId} onChange={(e) => setContactId(e.target.value)} /></label>
        <div className="flex gap-2 self-end">
          <button type="button" onClick={save} className="border px-4 py-2 rounded-lg text-sm">Salvar configuração</button>
          <button type="button" onClick={assign} className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm">Distribuir</button>
        </div>
      </div>
      {result && <p className="text-sm bg-emerald-50 dark:bg-emerald-950 border rounded-xl p-3">{result.reason} → corretor #{result.to_user_id}</p>}
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {(data?.assignments ?? []).map((row: any) => (
          <li key={row.id} className="p-3 text-sm">{row.reason} · estratégia {row.strategy}</li>
        ))}
        {!(data?.assignments ?? []).length && <li className="p-3 text-sm text-gray-500">Nenhuma distribuição ainda.</li>}
      </ul>
    </div>
  );
}
