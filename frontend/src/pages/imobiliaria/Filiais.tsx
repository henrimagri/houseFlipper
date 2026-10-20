import { useEffect, useState } from 'react';
import api from '@/lib/api';

export default function Filiais() {
  const [branches, setBranches] = useState<any[]>([]);
  const [name, setName] = useState('');
  const [city, setCity] = useState('');
  const [error, setError] = useState('');
  async function load() {
    const { data } = await api.get('/imobiliaria/organization');
    setBranches(data.branches ?? []);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Filiais indisponíveis.')); }, []);
  async function create() {
    await api.post('/imobiliaria/branches', { name, city, state: 'SP' });
    setName(''); setCity('');
    await load();
  }
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Filiais</h1>
      {error && <p className="text-red-700 text-sm">{error}</p>}
      <div className="flex gap-2">
        <input className="input" value={name} onChange={(e) => setName(e.target.value)} placeholder="Nome" />
        <input className="input" value={city} onChange={(e) => setCity(e.target.value)} placeholder="Cidade" />
        <button type="button" onClick={create} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">Criar</button>
      </div>
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {branches.map((branch) => <li key={branch.id} className="p-4 text-sm">{branch.name} · {branch.city || '—'}</li>)}
        {branches.length === 0 && <li className="p-4 text-sm text-gray-500">Nenhuma filial. A operação usa a organização inteira até haver unidades.</li>}
      </ul>
    </div>
  );
}
