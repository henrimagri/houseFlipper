import { useEffect, useState } from 'react';
import api from '@/lib/api';

const statuses = ['novo', 'em_contato', 'qualificado', 'demonstracao_agendada', 'proposta_enviada', 'convertido', 'perdido', 'arquivado'];

type Lead = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  product_slug?: string | null;
  intent: string;
  message?: string | null;
  status: string;
  notes?: string | null;
  source_path?: string | null;
  created_at: string;
  events?: { id: number; to_status: string; body?: string | null; created_at: string }[];
};

export default function SalesLeads() {
  const [rows, setRows] = useState<Lead[]>([]);
  const [error, setError] = useState('');
  const [product, setProduct] = useState('');
  const [status, setStatus] = useState('');
  const [notes, setNotes] = useState<Record<number, string>>({});

  async function load() {
    try {
      const { data } = await api.get('/platform/saas-leads', { params: { product: product || undefined, status: status || undefined } });
      setRows(data.data ?? []);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Sem permissão de operação.');
    }
  }

  useEffect(() => { load(); }, [product, status]);

  async function update(id: number, nextStatus: string) {
    await api.patch(`/platform/saas-leads/${id}`, { status: nextStatus, notes: notes[id] || null });
    await load();
  }

  if (error) return <p className="text-red-700">{error}</p>;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Interessados comerciais</h1>
      <p className="text-sm text-slate-500">Fila do SaaS — não é o CRM de compradores de imóveis.</p>
      <div className="flex gap-2">
        <select className="input max-w-48" value={product} onChange={(event) => setProduct(event.target.value)}>
          <option value="">Todos os produtos</option>
          <option value="corretor">Corretor</option>
          <option value="imobiliaria">Imobiliária</option>
          <option value="house_flipper">House Flipper</option>
        </select>
        <select className="input max-w-48" value={status} onChange={(event) => setStatus(event.target.value)}>
          <option value="">Todos os status</option>
          {statuses.map((item) => <option key={item} value={item}>{item}</option>)}
        </select>
      </div>
      <div className="space-y-3">
        {rows.map((row) => (
          <article key={row.id} className="rounded-xl border bg-white p-4 space-y-2">
            <div className="flex justify-between gap-3">
              <div>
                <p className="font-semibold">{row.name} · {row.email}</p>
                <p className="text-xs text-slate-500">{row.product_slug || 'produto não informado'} · {row.intent} · {row.source_path}</p>
              </div>
              <span className="text-xs uppercase">{row.status}</span>
            </div>
            {row.message && <p className="text-sm">{row.message}</p>}
            <textarea className="input" placeholder="Observação interna" value={notes[row.id] ?? ''} onChange={(event) => setNotes((current) => ({ ...current, [row.id]: event.target.value }))} />
            <div className="flex flex-wrap gap-2">
              {statuses.map((item) => (
                <button key={item} type="button" onClick={() => update(row.id, item)} className="rounded border px-2 py-1 text-xs">{item}</button>
              ))}
            </div>
          </article>
        ))}
        {rows.length === 0 && <p className="text-sm text-slate-500">Nenhuma solicitação nesta filtragem.</p>}
      </div>
    </div>
  );
}
