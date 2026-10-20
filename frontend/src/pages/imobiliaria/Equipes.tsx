import { useEffect, useState } from 'react';
import api from '@/lib/api';

export default function Equipes() {
  const [org, setOrg] = useState<any>(null);
  const [userId, setUserId] = useState('');
  const [error, setError] = useState('');
  async function load() {
    const { data } = await api.get('/imobiliaria/organization');
    setOrg(data);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Equipe indisponível.')); }, []);
  async function add() {
    await api.post('/imobiliaria/members', { user_id: Number(userId), role: 'agent' });
    setUserId('');
    await load();
  }
  if (error) return <p className="text-red-700">{error}</p>;
  if (!org) return <p role="status">Carregando equipe...</p>;
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">{org.data?.name}</h1>
      <div className="flex gap-2">
        <input className="input" value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="ID do usuário do mesmo tenant" />
        <button type="button" onClick={add} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">Vincular</button>
      </div>
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {(org.members ?? []).map((member: any) => (
          <li key={member.id} className="p-4 text-sm">{member.user?.name} · {member.role} · {member.status}</li>
        ))}
      </ul>
    </div>
  );
}
