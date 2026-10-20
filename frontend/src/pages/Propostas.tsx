import { useEffect, useState } from 'react';
import api from '@/lib/api';

export default function Propostas() {
  const [rows, setRows] = useState<any[]>([]);
  const [title, setTitle] = useState('');
  const [amount, setAmount] = useState('');
  async function load() {
    const { data } = await api.get('/proposals');
    setRows(data.data ?? []);
  }
  useEffect(() => { load(); }, []);
  async function create() {
    await api.post('/proposals', { title, amount_cents: amount ? Math.round(Number(amount) * 100) : null });
    setTitle(''); setAmount('');
    await load();
  }
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Propostas</h1>
      <div className="flex gap-2">
        <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Título" />
        <input className="input max-w-[10rem]" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="Valor" />
        <button type="button" onClick={create} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">Criar rascunho</button>
      </div>
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {rows.map((row) => <li key={row.id} className="p-4 text-sm">{row.title} · {row.status} · {row.amount_cents ? (row.amount_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'}</li>)}
        {rows.length === 0 && <li className="p-4 text-sm text-gray-500">Nenhuma proposta.</li>}
      </ul>
    </div>
  );
}
