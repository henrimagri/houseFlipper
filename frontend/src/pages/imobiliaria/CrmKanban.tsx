import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';
import SearchInput from '@/ui/SearchInput';
import StatusBadge from '@/ui/StatusBadge';
import DialogShell from '@/ui/DialogShell';

const STAGES = ['novo', 'contato', 'qualificado', 'sugeridos', 'visita', 'proposta', 'negociacao', 'fechamento', 'perdido'];
const LABELS: Record<string, string> = {
  novo: 'Novo lead', contato: 'Primeiro contato', qualificado: 'Qualificação', sugeridos: 'Imóveis sugeridos',
  visita: 'Visita agendada', proposta: 'Proposta', negociacao: 'Negociação', fechamento: 'Fechado', perdido: 'Perdido',
};

export default function CrmKanban() {
  const [deals, setDeals] = useState<any[]>([]);
  const [title, setTitle] = useState('');
  const [query, setQuery] = useState('');
  const [error, setError] = useState('');
  const [dragging, setDragging] = useState<number | null>(null);
  const [selected, setSelected] = useState<any>(null);

  async function load() {
    const { data } = await api.get('/imobiliaria/deals');
    setDeals(data.data ?? []);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'CRM indisponível.')); }, []);

  async function create() {
    if (!title.trim()) return;
    await api.post('/imobiliaria/deals', { title });
    setTitle('');
    await load();
  }

  async function move(id: number, stage: string, lockVersion?: number) {
    const previous = deals;
    setDeals((current) => current.map((deal) => deal.id === id ? { ...deal, stage } : deal));
    try {
      await api.patch(`/imobiliaria/deals/${id}/stage`, { stage, lock_version: lockVersion });
      await load();
    } catch (exception: any) {
      setDeals(previous);
      setError(exception.response?.status === 409 ? 'Outra pessoa moveu este card. Recarregue o quadro.' : (exception.response?.data?.message ?? 'Falha ao mover.'));
    }
  }

  async function openDeal(id: number) {
    const { data } = await api.get(`/imobiliaria/deals/${id}`);
    setSelected(data.data);
  }

  const visible = useMemo(
    () => deals.filter((deal) => !query || String(deal.title).toLowerCase().includes(query.toLowerCase()) || String(deal.contact?.name || '').toLowerCase().includes(query.toLowerCase())),
    [deals, query],
  );

  return (
    <div className="space-y-4">
      <PageHeader title="Funil comercial" subtitle="Arraste o card para mudar a etapa. A alteração é persistida. Teclado: selecione o card e use os botões da coluna." />
      <div className="flex flex-wrap gap-2">
        <input className="input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Novo negócio" />
        <button type="button" onClick={create} className="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">Criar</button>
        <SearchInput value={query} onChange={setQuery} placeholder="Buscar card" />
      </div>
      {error && <ErrorState message={error} />}
      <div className="flex gap-3 overflow-x-auto pb-4">
        {STAGES.map((stage) => {
          const column = visible.filter((deal) => deal.stage === stage);
          return (
            <section
              key={stage}
              onDragOver={(event) => event.preventDefault()}
              onDrop={() => {
                const deal = deals.find((item) => item.id === dragging);
                if (deal && deal.stage !== stage) void move(deal.id, stage, deal.lock_version);
                setDragging(null);
              }}
              className="min-w-[16rem] max-h-[70vh] overflow-y-auto bg-slate-50 dark:bg-slate-900 border rounded-2xl p-3"
            >
              <div className="flex items-center justify-between mb-2">
                <h2 className="text-xs uppercase tracking-wide text-gray-500">{LABELS[stage]}</h2>
                <span className="text-[11px] rounded-full bg-white text-slate-700 dark:bg-slate-800 dark:text-slate-200 border px-2">{column.length}</span>
              </div>
              {column.map((deal) => (
                <article
                  key={deal.id}
                  draggable
                  onDragStart={() => setDragging(deal.id)}
                  className="border bg-white dark:bg-slate-950 rounded-lg p-3 mb-2 text-sm space-y-1 cursor-grab active:cursor-grabbing"
                >
                  <button type="button" className="font-medium block text-left w-full" onClick={() => void openDeal(deal.id)}>{deal.title}</button>
                  <p className="text-xs text-gray-500">{deal.contact?.name || 'sem cliente'} · {deal.assigned_user?.name || 'sem corretor'}</p>
                  <p className="text-xs text-gray-500">{deal.listing?.title || 'sem imóvel'}</p>
                  {deal.estimated_value_cents ? <p className="text-xs">{(deal.estimated_value_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</p> : null}
                  <StatusBadge value={deal.priority || 'normal'} />
                  <div className="flex gap-1 pt-1">
                    {STAGES.filter((item) => item !== stage).slice(0, 2).map((item) => (
                      <button key={item} type="button" className="text-[10px] border rounded px-1.5 py-0.5" onClick={() => void move(deal.id, item, deal.lock_version)}>
                        {LABELS[item]}
                      </button>
                    ))}
                  </div>
                </article>
              ))}
              {column.length === 0 && <p className="text-xs text-gray-400">Vazio</p>}
            </section>
          );
        })}
      </div>
      {selected && (
        <DialogShell title={selected.title} description="Detalhes e histórico do negócio" onClose={() => setSelected(null)}>
          <div className="space-y-2">
            <p className="text-sm">Cliente: {selected.contact?.name || '—'}</p>
            <p className="text-sm">Imóvel: {selected.listing?.title || '—'}</p>
            <p className="text-sm">Responsável: {selected.assigned_user?.name || '—'}</p>
            <p className="text-sm">Etapa: {LABELS[selected.stage] || selected.stage} · versão {selected.lock_version}</p>
            <h3 className="font-medium pt-2">Histórico</h3>
            {(selected.activities ?? []).map((row: any) => (
              <p key={row.id} className="text-sm border-t py-2">{row.body || row.kind} · {row.from_stage} → {row.to_stage}</p>
            ))}
            <Link className="inline-block text-sm text-sky-700" to={`/app/imobiliaria/crm/${selected.id}`}>Abrir ficha completa</Link>
          </div>
        </DialogShell>
      )}
    </div>
  );
}
