import { FormEvent, useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { LayoutGrid, List, Plus } from 'lucide-react';
import api from '@/lib/api';
import { walletPath } from '@/lib/products';
import PageHeader from '@/ui/PageHeader';
import SearchInput from '@/ui/SearchInput';
import StatusBadge from '@/ui/StatusBadge';
import EmptyState from '@/ui/EmptyState';
import ErrorState from '@/ui/ErrorState';
import LoadingSkeleton from '@/ui/LoadingSkeleton';

type Listing = {
  id: number;
  slug: string;
  title: string;
  city?: string;
  neighborhood?: string;
  status: string;
  status_label: string;
  asking_price_cents?: number | null;
  area_m2?: number | string | null;
  bedrooms?: number | null;
  updated_at?: string;
  photos?: { url: string; is_cover: boolean }[];
};

const money = (cents?: number | null) =>
  cents == null ? '—' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Carteira() {
  const location = useLocation();
  const [items, setItems] = useState<Listing[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [authId, setAuthId] = useState('');
  const [saving, setSaving] = useState(false);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [sort, setSort] = useState('latest');
  const [page, setPage] = useState(1);
  const [view, setView] = useState<'table' | 'grid'>('table');

  async function load() {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/commercial-listings', { params: { q: q || undefined, status: status || undefined, sort, page, per_page: 12 } });
      setItems(data.data ?? []);
      setMeta(data.meta ?? meta);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível carregar a carteira.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void load(); }, [q, status, sort, page]);

  async function create(event: FormEvent) {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      await api.post('/commercial-listings', { authorization_id: Number(authId) });
      setAuthId('');
      await load();
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível adicionar.');
    } finally {
      setSaving(false);
    }
  }

  const open = (id: number) => walletPath(location.pathname, id);

  return (
    <div className="space-y-5">
      <PageHeader title="Carteira comercial" subtitle="Só entram imóveis com autorização de comercialização." />
      {error && <ErrorState message={error} />}
      <form onSubmit={create} className="bg-white dark:bg-slate-900 border rounded-xl p-4 flex flex-col sm:flex-row gap-3">
        <label className="flex-1">
          <span className="text-xs font-medium">ID da autorização concedida</span>
          <input required type="number" min={1} className="input mt-1" value={authId} onChange={(e) => setAuthId(e.target.value)} />
        </label>
        <button disabled={saving} className="self-end bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 disabled:opacity-60">
          <Plus className="w-4 h-4" />Adicionar à carteira
        </button>
      </form>
      <div className="flex flex-wrap gap-2 items-end">
        <SearchInput value={q} onChange={(value) => { setPage(1); setQ(value); }} placeholder="Título, cidade ou bairro" />
        <label className="text-xs">Status
          <select className="input mt-1" value={status} onChange={(e) => { setPage(1); setStatus(e.target.value); }}>
            <option value="">Todos</option>
            {['draft', 'ready', 'published', 'paused', 'sold', 'rented', 'withdrawn'].map((item) => <option key={item} value={item}>{item}</option>)}
          </select>
        </label>
        <label className="text-xs">Ordenar
          <select className="input mt-1" value={sort} onChange={(e) => setSort(e.target.value)}>
            <option value="latest">Atualização</option>
            <option value="price_desc">Preço desc</option>
            <option value="price_asc">Preço asc</option>
            <option value="title">Título</option>
          </select>
        </label>
        <div className="ml-auto flex gap-1">
          <button type="button" aria-label="Tabela" onClick={() => setView('table')} className={`p-2 rounded-lg ${view === 'table' ? 'bg-primary-600 text-white' : 'border'}`}><List className="w-4 h-4" /></button>
          <button type="button" aria-label="Grade" onClick={() => setView('grid')} className={`p-2 rounded-lg ${view === 'grid' ? 'bg-primary-600 text-white' : 'border'}`}><LayoutGrid className="w-4 h-4" /></button>
        </div>
      </div>
      {loading ? <LoadingSkeleton /> : items.length === 0 ? (
        <EmptyState title="Nenhum imóvel autorizado" description="Capture uma oportunidade, obtenha a autorização e adicione o ID acima." />
      ) : view === 'grid' ? (
        <div className="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
          {items.map((item) => (
            <Link key={item.id} to={open(item.id)} className="bg-white dark:bg-slate-900 border rounded-xl overflow-hidden">
              <div className="h-36 bg-slate-100 dark:bg-slate-800">
                {item.photos?.[0] && <img src={item.photos.find((photo) => photo.is_cover)?.url ?? item.photos[0].url} alt="" className="w-full h-36 object-cover" />}
              </div>
              <div className="p-4 space-y-1">
                <p className="font-semibold">{item.title}</p>
                <p className="text-xs text-gray-500">{[item.neighborhood, item.city].filter(Boolean).join(' · ') || '—'}</p>
                <p className="text-sm">{money(item.asking_price_cents)} · {item.area_m2 ?? '—'} m² · {item.bedrooms ?? '—'} qts</p>
                <StatusBadge value={item.status} />
              </div>
            </Link>
          ))}
        </div>
      ) : (
        <div className="bg-white dark:bg-slate-900 border rounded-xl overflow-x-auto">
          <table className="w-full text-sm min-w-[40rem]">
            <thead className="bg-gray-50 dark:bg-slate-950 text-[11px] uppercase text-gray-500">
              <tr>{['Imóvel', 'Local', 'Preço', 'Área', 'Quartos', 'Status', 'Atualizado', ''].map((h) => <th key={h} className="text-left px-4 py-3">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-slate-800">
              {items.map((item) => (
                <tr key={item.id}>
                  <td className="px-4 py-3 font-semibold">{item.title}</td>
                  <td className="px-4 py-3">{[item.neighborhood, item.city].filter(Boolean).join(' · ') || '—'}</td>
                  <td className="px-4 py-3">{money(item.asking_price_cents)}</td>
                  <td className="px-4 py-3">{item.area_m2 ?? '—'}</td>
                  <td className="px-4 py-3">{item.bedrooms ?? '—'}</td>
                  <td className="px-4 py-3"><StatusBadge value={item.status} /></td>
                  <td className="px-4 py-3">{item.updated_at ? new Date(item.updated_at).toLocaleDateString('pt-BR') : '—'}</td>
                  <td className="px-4 py-3 text-right"><Link className="text-primary-600 font-semibold" to={open(item.id)}>Abrir</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {meta.last_page > 1 && (
        <div className="flex justify-end gap-2 text-sm">
          <button type="button" disabled={page <= 1} onClick={() => setPage(page - 1)} className="border px-3 py-1 rounded-lg disabled:opacity-40">Anterior</button>
          <span className="py-1">{meta.current_page} / {meta.last_page}</span>
          <button type="button" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)} className="border px-3 py-1 rounded-lg disabled:opacity-40">Próxima</button>
        </div>
      )}
    </div>
  );
}
