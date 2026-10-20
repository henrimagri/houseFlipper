import { FormEvent, useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { walletPath } from '@/lib/products';
import api from '@/lib/api';

type Photo = { id: number; url: string; licensed_for_publication: boolean; is_cover: boolean };
type Listing = {
  id: number;
  slug: string;
  title: string;
  description?: string;
  status: string;
  status_label: string;
  asking_price_cents?: number | null;
  city?: string;
  neighborhood?: string;
  bedrooms?: number;
  bathrooms?: number;
  area_m2?: string;
  authorization_usable?: boolean;
  photos?: Photo[];
  source?: { provider: string; title: string; captured_at?: string } | null;
};

const styles = ['premium', 'luxo', 'popular', 'investimento', 'primeiro_imovel', 'lancamento', 'oportunidade'];

export default function CarteiraFicha() {
  const { id } = useParams();
  const location = useLocation();
  const [item, setItem] = useState<Listing | null>(null);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ title: '', description: '', asking_price_cents: '' });
  const [copy, setCopy] = useState<Record<string, string> | null>(null);
  const [style, setStyle] = useState('oportunidade');

  async function load() {
    setError('');
    try {
      const { data } = await api.get(`/commercial-listings/${id}`);
      const listing: Listing = data.data;
      setItem(listing);
      setForm({
        title: listing.title,
        description: listing.description ?? '',
        asking_price_cents: listing.asking_price_cents != null ? String(listing.asking_price_cents) : '',
      });
    } catch (exception: any) {
      setError(exception.response?.status === 404 ? 'Imóvel da carteira não encontrado.' : (exception.response?.data?.message ?? 'Falha ao carregar.'));
    }
  }

  useEffect(() => { void load(); }, [id]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      await api.patch(`/commercial-listings/${id}`, {
        title: form.title,
        description: form.description || null,
        asking_price_cents: form.asking_price_cents ? Number(form.asking_price_cents) : null,
      });
      await load();
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível salvar.');
    } finally { setSaving(false); }
  }

  async function publish() {
    setSaving(true); setError('');
    try { await api.post(`/commercial-listings/${id}/publish`); await load(); }
    catch (exception: any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Publicação recusada.'); }
    finally { setSaving(false); }
  }

  async function unpublish() {
    setSaving(true); setError('');
    try { await api.post(`/commercial-listings/${id}/unpublish`); await load(); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível despublicar.'); }
    finally { setSaving(false); }
  }

  async function upload(file: File, licensed: boolean) {
    if (!licensed) { setError('Só envie fotos com licença para divulgação.'); return; }
    setSaving(true); setError('');
    try {
      const payload = new FormData();
      payload.append('photo', file);
      payload.append('licensed_for_publication', '1');
      await api.post(`/commercial-listings/${id}/photos`, payload);
      await load();
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Upload recusado.');
    } finally { setSaving(false); }
  }

  async function makeCopy() {
    setSaving(true); setError('');
    try {
      const { data } = await api.post(`/commercial-listings/${id}/copy`, { style });
      setCopy(data.data);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Texto comercial indisponível neste plano.');
    } finally { setSaving(false); }
  }

  if (!item && !error) return <p className="text-gray-400">Carregando ficha...</p>;

  return (
    <div className="space-y-5">
      <Link to={walletPath(location.pathname)} className="text-sm text-primary-600">← Carteira</Link>
      {error && <div role="alert" className="bg-red-50 dark:bg-red-950 border border-red-200 text-red-700 dark:text-red-300 rounded-lg p-3 text-sm">{error}</div>}
      {item && (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 className="text-2xl font-bold">{item.title}</h1>
              <p className="text-sm text-gray-500">{item.status_label} · autorização {item.authorization_usable ? 'vigente' : 'indisponível'}</p>
            </div>
            <div className="flex gap-2">
              {item.status === 'published'
                ? <button disabled={saving} onClick={unpublish} className="border px-4 py-2 rounded-lg">Despublicar</button>
                : <button disabled={saving} onClick={publish} className="bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold">Publicar</button>}
            </div>
          </div>
          {item.source && <p className="text-xs text-gray-500">Origem preservada: {item.source.provider} · {item.source.title} · snapshot {item.source.captured_at ? new Date(item.source.captured_at).toLocaleString('pt-BR') : ''}</p>}
          <form onSubmit={save} className="bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-3">
            <label className="block"><span className="text-xs">Título comercial</span><input className="input mt-1" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></label>
            <label className="block"><span className="text-xs">Preço de divulgação (centavos)</span><input className="input mt-1" type="number" min={0} value={form.asking_price_cents} onChange={(e) => setForm({ ...form, asking_price_cents: e.target.value })} /></label>
            <label className="block"><span className="text-xs">Descrição própria</span><textarea className="input mt-1 min-h-24" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
            <button disabled={saving} className="bg-slate-900 text-white px-4 py-2 rounded-lg">Salvar apresentação</button>
          </form>
          <section className="bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-3">
            <h2 className="font-semibold">Fotos licenciadas</h2>
            <p className="text-xs text-gray-500">Fotos de portais não são copiadas. Envie arquivos com direito de uso.</p>
            <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => { const file = e.currentTarget.files?.[0]; if (file) void upload(file, true); }} />
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              {item.photos?.map((photo) => (
                <img key={photo.id} src={photo.url} alt="" className="h-28 w-full object-cover rounded-lg border" />
              ))}
            </div>
          </section>
          <section className="bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-3">
            <h2 className="font-semibold">Estúdio de texto</h2>
            <div className="flex gap-2">
              <select className="input" value={style} onChange={(e) => setStyle(e.target.value)}>{styles.map((itemStyle) => <option key={itemStyle} value={itemStyle}>{itemStyle}</option>)}</select>
              <button type="button" disabled={saving} onClick={makeCopy} className="border px-4 py-2 rounded-lg">Gerar com dados reais</button>
            </div>
            {copy && (
              <div className="text-sm space-y-2">
                <p><strong>Instagram</strong><br />{copy.instagram}</p>
                <p><strong>WhatsApp</strong><br />{copy.whatsapp}</p>
                {copy.missing && <p className="text-xs text-amber-700">Campos ausentes, não inventados: {String(copy.missing)}</p>}
              </div>
            )}
          </section>
        </>
      )}
    </div>
  );
}
