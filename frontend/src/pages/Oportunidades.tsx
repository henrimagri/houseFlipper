import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Archive, Bath, BedDouble, Building2, Calculator, ChevronLeft, ChevronRight, Eye, Filter,
  Heart, Home, MapPin, MoreVertical, Plus, Search, Star, Trash2, X,
} from 'lucide-react';
import api from '@/lib/api';
import { appendOpportunityFields } from '@/lib/opportunityForm';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';
import { safeExternalHttpsUrl } from '@/lib/externalUrl';

type Option = { value: string; label: string };
type Opportunity = {
  id: number; title: string; description?: string; property_type: string; property_type_label: string;
  source: string; source_label: string; source_url?: string; status: string; status_label: string;
  address?: string; neighborhood?: string; city?: string; state?: string; zip_code?: string;
  asking_price?: string; estimated_market_price?: string; area_m2?: string; bedrooms?: number;
  bathrooms?: number; parking_spaces?: number; conservation_status?: string; notes?: string;
  is_favorite: boolean; is_archived: boolean; discarded_reason?: string; cover_url?: string;
  photos?: { id: number; url: string; name: string; is_cover: boolean }[];
  status_histories?: { id: number; new_status_label: string; notes?: string; user: string; created_at: string }[];
  created_at: string;
  primary_analysis_id?: number;
};
type ApiResponse = {
  data: Opportunity[]; stats: { total: number; analyzing: number; favorites: number; discarded: number };
  meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
  options: { statuses: Record<string, Option>; property_types: Record<string, Option>; sources: Record<string, Option> };
};

const emptyForm = { title: '', description: '', property_type: 'house', source: 'manual', status: 'new', source_url: '', address: '', neighborhood: '', city: '', state: '', zip_code: '', asking_price: '', estimated_market_price: '', area_m2: '', bedrooms: '', bathrooms: '', parking_spaces: '', conservation_status: '', notes: '' };
const money = (value?: string) => value ? Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—';
const RADAR_STATE_KEY = 'ia-inteligente:radar-state';

export default function Oportunidades() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [result, setResult] = useState<ApiResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [filters, setFilters] = useState({ search: '', city: '', neighborhood: '', status: '', property_type: '', source: '', favorite: '', min_price: '', max_price: '' });
  const [applied, setApplied] = useState(filters);
  const [page, setPage] = useState(1);
  const [modal, setModal] = useState<'form' | 'detail' | null>(null);
  const [selected, setSelected] = useState<Opportunity | null>(null);
  const [form, setForm] = useState<Record<string, string>>(emptyForm);
  const [photos, setPhotos] = useState<File[]>([]);
  const [coverSelection, setCoverSelection] = useState('');
  const [saving, setSaving] = useState(false);
  const [confirmAction, setConfirmAction] = useState<{ item: Opportunity; action: 'archive' | 'delete' } | null>(null);
  const [capture, setCapture] = useState<{ id: number; status: string; status_label: string; usable: boolean } | null>(null);
  const [captureForm, setCaptureForm] = useState({ owner_name: '', owner_contact: '', evidence_notes: '' });
  const [cachedCovers, setCachedCovers] = useState<Record<string, string>>({});

  async function load() {
    setLoading(true); setError('');
    try {
      const params = Object.fromEntries(Object.entries({ ...applied, page }).filter(([, value]) => value !== ''));
      const { data } = await api.get<ApiResponse>('/opportunities', { params });
      setResult(data);
      return true;
    } catch (e: any) { setError(e.response?.data?.message ?? 'Não foi possível carregar as oportunidades.'); return false; }
    finally { setLoading(false); }
  }
  useEffect(() => { load(); }, [applied, page]);
  useEffect(() => {
    try {
      const snapshot = (() => {
        for (const storage of [sessionStorage, localStorage]) {
          try {
            const raw = storage.getItem(RADAR_STATE_KEY);
            if (raw) return JSON.parse(raw);
          } catch {
            // Continue to the other browser store.
          }
        }
        return null;
      })();
      const covers: Record<string, string> = {};
      for (const item of snapshot?.results ?? []) {
        const thumbnail = safeExternalHttpsUrl(item?.thumbnail);
        if (!thumbnail) continue;
        const sourceUrl = safeExternalHttpsUrl(item.source_url);
        if (sourceUrl) covers[`url:${sourceUrl}`] = thumbnail;
        if (item.title) covers[`title:${normalizeKey(item.title)}`] = thumbnail;
      }
      setCachedCovers(covers);
    } catch {
      setCachedCovers({});
    }
  }, []);

  const options = result?.options;
  const optionList = (items?: Record<string, Option>) => Object.values(items ?? {});
  const discount = (item: Opportunity) => item.asking_price && item.estimated_market_price
    ? `${((1 - Number(item.asking_price) / Number(item.estimated_market_price)) * 100).toFixed(1).replace('.', ',')}%` : '—';
  const coverFor = (item: Opportunity) => item.cover_url || (safeExternalHttpsUrl(item.source_url) ? cachedCovers[`url:${safeExternalHttpsUrl(item.source_url)}`] : undefined) || cachedCovers[`title:${normalizeKey(item.title)}`];

  function applyFilters(e: FormEvent) { e.preventDefault(); setPage(1); setApplied({ ...filters }); }
  function clearFilters() { const clean = { ...filters }; Object.keys(clean).forEach((key) => (clean[key as keyof typeof clean] = '')); setFilters(clean); setApplied(clean); setPage(1); }
  function openCreate() { setSelected(null); setForm(emptyForm); setPhotos([]); setCoverSelection(''); setModal('form'); }
  function closeModal() {
    setModal(null);
    if (searchParams.get('novo') === '1') {
      const next = new URLSearchParams(searchParams); next.delete('novo'); setSearchParams(next, { replace: true });
    }
  }
  useEffect(() => { if (searchParams.get('novo') === '1') openCreate(); }, [searchParams]);
  async function openEdit(item: Opportunity) {
    setSaving(true); setError('');
    try {
      const { data } = await api.get(`/opportunities/${item.id}`);
      const fullItem: Opportunity = data.data;
      setSelected(fullItem); setForm(Object.fromEntries(Object.keys(emptyForm).map((key) => [key, String((fullItem as any)[key] ?? '')]))); setPhotos([]);
      const currentCover = fullItem.photos?.find((photo) => photo.is_cover);
      setCoverSelection(currentCover ? `existing:${currentCover.id}` : ''); setModal('form');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível carregar a oportunidade para edição.');
    } finally { setSaving(false); }
  }
  async function openDetail(item: Opportunity) {
    setSaving(true); setError('');
    try {
      const { data } = await api.get(`/opportunities/${item.id}`);
      setSelected(data.data);
      setModal('detail');
      try {
        const captureRes = await api.get(`/opportunities/${item.id}/capture`);
        setCapture(captureRes.data.data);
      } catch {
        setCapture(null);
      }
    } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível carregar os detalhes da oportunidade.'); }
    finally { setSaving(false); }
  }

  async function startCapture(item: Opportunity) {
    setSaving(true); setError('');
    try {
      const { data } = await api.post(`/opportunities/${item.id}/captures`);
      setCapture(data.data);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível iniciar a captação.');
    } finally { setSaving(false); }
  }

  async function grantCapture() {
    if (!capture) return;
    setSaving(true); setError('');
    try {
      const payload = new FormData();
      payload.append('owner_name', captureForm.owner_name);
      payload.append('owner_contact', captureForm.owner_contact);
      payload.append('evidence_notes', captureForm.evidence_notes);
      const { data } = await api.post(`/captures/${capture.id}/grant`, payload);
      setCapture(data.data);
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Autorização recusada.');
    } finally { setSaving(false); }
  }

  async function addToWallet() {
    if (!capture) return;
    setSaving(true); setError('');
    try {
      const { data } = await api.post('/commercial-listings', { authorization_id: capture.id });
      navigate(`/carteira/${data.data.id}`);
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Só entra na carteira com autorização concedida.');
    } finally { setSaving(false); }
  }

  async function save(e: FormEvent) {
    e.preventDefault(); setSaving(true); setError('');
    try {
      const payload = new FormData();
      appendOpportunityFields(payload, form);
      photos.forEach((photo) => payload.append('photos[]', photo));
      if (coverSelection.startsWith('new:')) payload.append('cover_photo', coverSelection.split(':')[1]);
      if (coverSelection.startsWith('existing:')) payload.append('existing_cover_photo_id', coverSelection.split(':')[1]);
      if (selected) payload.append('_method', 'PUT');
      // Não definir Content-Type manualmente: o navegador precisa incluir o boundary
      // multipart correto para o PHP reconhecer os arquivos enviados.
      await api.post(selected ? `/opportunities/${selected.id}` : '/opportunities', payload);
      closeModal(); const refreshed = await load(); if (!refreshed) setError('Oportunidade salva, mas a lista não pôde ser recarregada. Use Atualizar antes de repetir a operação.');
    } catch (e: any) { setError(Object.values(e.response?.data?.errors ?? {}).flat().join(' ') || e.response?.data?.message || 'Não foi possível salvar.'); }
    finally { setSaving(false); }
  }
  async function favorite(item: Opportunity) {
    setSaving(true); setError('');
    try { await api.patch(`/opportunities/${item.id}/favorite`); const refreshed = await load(); if (refreshed && selected?.id === item.id) setSelected({ ...selected, is_favorite: !selected.is_favorite }); else if (!refreshed) setError('Favorito alterado, mas a lista não pôde ser recarregada. Use Atualizar.'); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível alterar o favorito.'); }
    finally { setSaving(false); }
  }
  async function archive(item: Opportunity) { setSaving(true); setError(''); try { await api.patch(`/opportunities/${item.id}/archive`); setConfirmAction(null); setModal(null); const refreshed = await load(); if (!refreshed) setError('Oportunidade arquivada, mas a lista não pôde ser recarregada. Use Atualizar.'); } catch (e: any) { setError(e.response?.data?.message ?? 'Não foi possível arquivar a oportunidade.'); setConfirmAction(null); } finally { setSaving(false); } }
  async function remove(item: Opportunity) { setSaving(true); setError(''); try { await api.delete(`/opportunities/${item.id}`); setConfirmAction(null); setModal(null); const refreshed = await load(); if (!refreshed) setError('Oportunidade excluída, mas a lista não pôde ser recarregada. Use Atualizar.'); } catch (e: any) { setError(e.response?.data?.message ?? 'Não foi possível excluir a oportunidade.'); setConfirmAction(null); } finally { setSaving(false); } }
  async function openFinancialAnalysis(item: Opportunity) {
    setSaving(true); setError('');
    try {
      if (item.primary_analysis_id) { navigate(`/analises-financeiras/${item.primary_analysis_id}`); return; }
      const { data } = await api.post(`/opportunities/${item.id}/investment-analysis`, { purchase_price: Number(item.asking_price ?? 0), down_payment: 0, expected_sale_price: Number(item.estimated_market_price ?? 0), expected_months_until_sale: 6 });
      navigate(`/analises-financeiras/${data.data.id}`);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível abrir a análise financeira.');
    } finally { setSaving(false); }
  }

  const cards = useMemo(() => [
    { label: 'Oportunidades', value: result?.stats.total ?? 0, help: 'Total cadastradas', icon: Home, color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950' },
    { label: 'Em análise', value: result?.stats.analyzing ?? 0, help: 'Aguardando análise', icon: Search, color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950' },
    { label: 'Favoritos', value: result?.stats.favorites ?? 0, help: 'Imóveis favoritos', icon: Star, color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950' },
    { label: 'Descartados', value: result?.stats.discarded ?? 0, help: 'Oportunidades descartadas', icon: Trash2, color: 'text-red-500', bg: 'bg-red-50 dark:bg-red-950' },
  ], [result]);

  return <div className="space-y-5">
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div><h1 className="text-2xl font-bold text-gray-900 dark:text-slate-100">Leads & Oportunidades</h1><p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Encontre, acompanhe e analise imóveis com potencial de flip.</p></div>
      <button onClick={openCreate} className="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-xs"><Plus className="w-4 h-4" />Nova Oportunidade</button>
    </div>
    {error && <div className="bg-red-50 dark:bg-red-950 border border-red-200 text-red-700 dark:text-red-300 rounded-lg p-3 text-sm">{error}</div>}
    {modal === 'detail' && selected && <div className="fixed z-60 bottom-8 left-1/2 -translate-x-1/2 flex gap-2"><button onClick={() => navigate(`/leads/${selected.id}/3d`)} className="bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-xl shadow-xl font-semibold">Explorar em 3D</button><button onClick={() => openFinancialAnalysis(selected)} className="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl shadow-xl font-semibold">Plano de Viabilidade</button></div>}
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">{cards.map(({ label, value, help, icon: Icon, color, bg }) => <div key={label} className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-4 shadow-xs"><div className={`w-12 h-12 ${bg} ${color} rounded-xl grid place-items-center`}><Icon className="w-6 h-6" /></div><div><div className="text-sm font-semibold text-gray-700 dark:text-slate-200">{label}</div><div className="text-2xl font-bold text-gray-900 dark:text-slate-100">{value}</div><div className="text-xs text-gray-400">{help}</div></div></div>)}</div>

    <form onSubmit={applyFilters} className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl p-4 shadow-xs">
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
        <label className="xl:col-span-2"><span className="text-xs font-medium text-gray-600 dark:text-slate-300">Buscar imóvel</span><div className="relative mt-1"><Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" /><input className="input pl-9" value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value })} placeholder="Título ou endereço..." /></div></label>
        <FilterInput label="Cidade" value={filters.city} onChange={(city) => setFilters({ ...filters, city })} />
        <FilterInput label="Bairro" value={filters.neighborhood} onChange={(neighborhood) => setFilters({ ...filters, neighborhood })} />
        <FilterSelect label="Status" value={filters.status} items={optionList(options?.statuses)} onChange={(status) => setFilters({ ...filters, status })} />
        <FilterSelect label="Tipo" value={filters.property_type} items={optionList(options?.property_types)} onChange={(property_type) => setFilters({ ...filters, property_type })} />
        <FilterSelect label="Origem" value={filters.source} items={optionList(options?.sources)} onChange={(source) => setFilters({ ...filters, source })} />
        <FilterSelect label="Favoritos" value={filters.favorite} items={[{ value: '1', label: 'Somente favoritos' }]} onChange={(favorite) => setFilters({ ...filters, favorite })} />
        <FilterInput label="Preço mínimo" type="number" value={filters.min_price} onChange={(min_price) => setFilters({ ...filters, min_price })} />
        <FilterInput label="Preço máximo" type="number" value={filters.max_price} onChange={(max_price) => setFilters({ ...filters, max_price })} />
        <div className="flex items-end gap-2 xl:col-span-2"><button className="bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-semibold flex gap-2"><Filter className="w-4 h-4" />Filtrar</button><button type="button" onClick={clearFilters} className="border border-gray-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm">Limpar</button></div>
      </div>
    </form>

    <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl shadow-xs overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm"><thead className="bg-gray-50 dark:bg-slate-950 text-[11px] uppercase text-gray-500 dark:text-slate-400"><tr>{['Imóvel','Localização','Preço pedido','Valor mercado','Desconto','Tipo','Status','Origem','Data','Ações'].map((h) => <th key={h} className="text-left px-4 py-3 whitespace-nowrap">{h}</th>)}</tr></thead><tbody className="divide-y divide-gray-100">
      {loading ? <tr><td colSpan={10} className="p-10 text-center text-gray-400">Carregando oportunidades...</td></tr> : result?.data.length ? result.data.map((item) => <tr key={item.id} className="hover:bg-gray-50/70 dark:hover:bg-slate-800">
        <td className="px-4 py-3"><div className="flex gap-3 min-w-[260px]"><OpportunityThumb src={coverFor(item)} alt={item.title} /><div><button onClick={() => openDetail(item)} className="font-semibold text-gray-900 dark:text-slate-100 hover:text-primary-600 text-left">{item.title}</button><div className="text-xs text-gray-400">ID: #INV-{String(item.id).padStart(6, '0')}</div><div className="text-xs text-gray-500 dark:text-slate-400">{item.area_m2 ? `${Number(item.area_m2).toLocaleString('pt-BR')} m²` : 'Área não informada'} · {item.bedrooms ?? 0} quartos</div></div></div></td>
        <td className="px-4 py-3 whitespace-nowrap"><div className="font-medium">{item.city || '—'}{item.state && ` - ${item.state}`}</div><div className="text-xs text-gray-500 dark:text-slate-400">{item.neighborhood}</div></td><td className="px-4 py-3 font-semibold whitespace-nowrap">{money(item.asking_price)}</td><td className="px-4 py-3 font-semibold whitespace-nowrap">{money(item.estimated_market_price)}</td><td className="px-4 py-3 font-semibold text-emerald-600 dark:text-emerald-400">{discount(item)}</td><td className="px-4 py-3"><span className="bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 px-2 py-1 rounded-md text-xs">{item.property_type_label}</span></td><td className="px-4 py-3"><span className="bg-violet-50 dark:bg-violet-950 text-primary-600 dark:text-primary-400 px-2 py-1 rounded-md text-xs whitespace-nowrap">{item.status_label}</span></td><td className="px-4 py-3">{item.source_label}</td><td className="px-4 py-3 whitespace-nowrap">{new Date(item.created_at).toLocaleDateString('pt-BR')}</td><td className="px-4 py-3"><div className="flex gap-1"><button title={item.primary_analysis_id ? 'Abrir Central Financeira' : 'Criar Análise Financeira'} onClick={() => openFinancialAnalysis(item)} className="p-2 border border-emerald-200 rounded-lg text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950"><Calculator className="w-4 h-4" /></button><button title="Favoritar" onClick={() => favorite(item)} className={`p-2 border rounded-lg ${item.is_favorite ? 'text-red-500 bg-red-50 dark:bg-red-950' : 'text-gray-500 dark:text-slate-400'}`}><Heart className="w-4 h-4" fill={item.is_favorite ? 'currentColor' : 'none'} /></button><button title="Visualizar" onClick={() => openDetail(item)} className="p-2 border rounded-lg text-gray-500 dark:text-slate-400"><Eye className="w-4 h-4" /></button><button title="Editar" onClick={() => openEdit(item)} className="p-2 border rounded-lg text-gray-500 dark:text-slate-400"><MoreVertical className="w-4 h-4" /></button></div></td>
      </tr>) : <tr><td colSpan={10} className="p-10 text-center text-gray-400">Nenhuma oportunidade encontrada. Clique em “Nova Oportunidade” para começar.</td></tr>}
    </tbody></table></div><div className="px-4 py-3 border-t flex items-center justify-between text-xs text-gray-500 dark:text-slate-400"><span>Mostrando {result?.meta.from ?? 0} a {result?.meta.to ?? 0} de {result?.meta.total ?? 0}</span><div className="flex gap-2"><button disabled={page <= 1} onClick={() => setPage(page - 1)} className="p-2 border rounded-lg disabled:opacity-40"><ChevronLeft className="w-4 h-4" /></button><span className="px-3 py-2 bg-primary-600 text-white rounded-lg">{page}</span><button disabled={page >= (result?.meta.last_page ?? 1)} onClick={() => setPage(page + 1)} className="p-2 border rounded-lg disabled:opacity-40"><ChevronRight className="w-4 h-4" /></button></div></div></div>

      {modal === 'form' && <Modal title={selected ? 'Editar oportunidade' : 'Nova oportunidade'} onClose={closeModal}><form onSubmit={save} className="space-y-4">{error && <div role="alert" className="bg-red-50 dark:bg-red-950 border border-red-200 text-red-700 dark:text-red-300 rounded-lg p-3 text-sm">{error}</div>}<div className="grid grid-cols-1 md:grid-cols-2 gap-4"><FormInput className="md:col-span-2" label="Título *" name="title" form={form} setForm={setForm} required /><FilterSelect label="Tipo *" value={form.property_type} items={optionList(options?.property_types)} onChange={(property_type) => setForm({ ...form, property_type })} /><FilterSelect label="Origem *" value={form.source} items={optionList(options?.sources)} onChange={(source) => setForm({ ...form, source })} /><FilterSelect label="Status" value={form.status} items={optionList(options?.statuses)} onChange={(status) => setForm({ ...form, status })} /><FormInput label="URL da origem" name="source_url" type="url" form={form} setForm={setForm} /><FormInput className="md:col-span-2" label="Endereço" name="address" form={form} setForm={setForm} /><FormInput label="Bairro" name="neighborhood" form={form} setForm={setForm} /><FormInput label="Cidade" name="city" form={form} setForm={setForm} /><FormInput label="UF" name="state" form={form} setForm={setForm} /><FormInput label="CEP" name="zip_code" form={form} setForm={setForm} />{[['asking_price','Preço pedido'],['estimated_market_price','Valor de mercado'],['area_m2','Área (m²)'],['bedrooms','Quartos'],['bathrooms','Banheiros'],['parking_spaces','Vagas']].map(([name,label]) => <FormInput key={name} label={label} name={name} type="number" form={form} setForm={setForm} />)}<PhotoPicker existing={selected?.photos ?? []} files={photos} selection={coverSelection} onSelection={setCoverSelection} onFiles={(files) => { setPhotos(files); if (files.length && !coverSelection) setCoverSelection('new:0'); }} /><label className="md:col-span-2"><span className="text-xs font-medium text-gray-600 dark:text-slate-300">Descrição</span><textarea className="input mt-1 min-h-24" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label></div><div className="flex justify-end gap-2"><button type="button" onClick={closeModal} className="border px-4 py-2 rounded-lg">Cancelar</button><button disabled={saving} className="bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold disabled:opacity-60">{saving ? 'Salvando...' : 'Salvar oportunidade'}</button></div></form></Modal>}

    {modal === 'detail' && selected && <Modal title={selected.title} onClose={() => setModal(null)} wide><div className="space-y-5">{error && <div role="alert" className="bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 rounded-lg p-3 text-sm">{error}</div>}{selected.photos?.length ? <PhotoGallery photos={selected.photos} title={selected.title} /> : <div className="h-32 rounded-xl bg-gray-50 dark:bg-slate-950 grid place-items-center text-gray-400"><Building2 className="w-8 h-8" /></div>}<div className="grid grid-cols-2 md:grid-cols-4 gap-4">{[['Status',selected.status_label],['Tipo',selected.property_type_label],['Preço pedido',money(selected.asking_price)],['Valor de mercado',money(selected.estimated_market_price)],['Localização',[selected.city,selected.state].filter(Boolean).join(' - ')],['Bairro',selected.neighborhood || '—'],['Área',selected.area_m2 ? `${selected.area_m2} m²` : '—'],['Origem',selected.source_label]].map(([label,value]) => <div key={label}><div className="text-xs text-gray-400">{label}</div><div className="font-semibold mt-1">{value}</div></div>)}</div><div className="flex gap-4 text-sm text-gray-600 dark:text-slate-300"><span className="flex gap-1"><BedDouble className="w-4 h-4" />{selected.bedrooms ?? 0} quartos</span><span className="flex gap-1"><Bath className="w-4 h-4" />{selected.bathrooms ?? 0} banheiros</span><span className="flex gap-1"><MapPin className="w-4 h-4" />{selected.address || 'Endereço não informado'}</span></div>{selected.description && <p className="text-sm text-gray-600 dark:text-slate-300">{selected.description}</p>}<div><h3 className="font-semibold mb-3">Histórico de status</h3><div className="space-y-3 border-l-2 border-violet-100 pl-4">{selected.status_histories?.map((history) => <div key={history.id}><div className="font-medium text-sm">{history.new_status_label}</div><div className="text-xs text-gray-400">{new Date(history.created_at).toLocaleString('pt-BR')} · {history.user}</div>{history.notes && <div className="text-xs text-gray-600 dark:text-slate-300">{history.notes}</div>}</div>)}</div></div><div className="border rounded-xl p-3 space-y-2 text-sm">
          <div className="font-semibold">Captação comercial</div>
          <p className="text-xs text-gray-500">Descobrir o anúncio não autoriza republicar fotos nem intermediar a venda.</p>
          <p>Estado: {capture ? capture.status_label : 'Descoberto'}</p>
          {!capture && <button disabled={saving} type="button" onClick={() => startCapture(selected)} className="border px-3 py-2 rounded-lg">Solicitar captação</button>}
          {capture && !capture.usable && (
            <div className="grid md:grid-cols-3 gap-2">
              <input className="input" placeholder="Nome do responsável" value={captureForm.owner_name} onChange={(e) => setCaptureForm({ ...captureForm, owner_name: e.target.value })} />
              <input className="input" placeholder="Contato (privado)" value={captureForm.owner_contact} onChange={(e) => setCaptureForm({ ...captureForm, owner_contact: e.target.value })} />
              <input className="input" placeholder="Notas da evidência" value={captureForm.evidence_notes} onChange={(e) => setCaptureForm({ ...captureForm, evidence_notes: e.target.value })} />
              <button disabled={saving} type="button" onClick={grantCapture} className="border px-3 py-2 rounded-lg">Registrar autorização</button>
            </div>
          )}
          {capture?.usable && <button disabled={saving} type="button" onClick={addToWallet} className="bg-primary-600 text-white px-3 py-2 rounded-lg">Adicionar à carteira</button>}
        </div>
        <div className="flex justify-end gap-2"><button disabled={saving} onClick={() => favorite(selected)} className="border px-3 py-2 rounded-lg flex gap-2 disabled:opacity-60"><Heart className="w-4 h-4" />Favoritar</button><button disabled={saving} onClick={() => setConfirmAction({ item: selected, action: 'archive' })} className="border px-3 py-2 rounded-lg flex gap-2 disabled:opacity-60"><Archive className="w-4 h-4" />Arquivar</button><button disabled={saving} onClick={() => openEdit(selected)} className="bg-primary-600 text-white px-4 py-2 rounded-lg disabled:opacity-60">Editar</button><button disabled={saving} aria-label={`Excluir ${selected.title}`} onClick={() => setConfirmAction({ item: selected, action: 'delete' })} className="border border-red-200 text-red-600 dark:text-red-400 px-3 py-2 rounded-lg disabled:opacity-60"><Trash2 className="w-4 h-4" /></button></div></div></Modal>}
    {confirmAction && <ConfirmActionDialog title={confirmAction.action === 'archive' ? 'Arquivar oportunidade?' : 'Excluir oportunidade?'} description={`A oportunidade ${confirmAction.item.title} será ${confirmAction.action === 'archive' ? 'arquivada e ocultada das listas ativas' : 'removida da sua conta'}.`} actionLabel={confirmAction.action === 'archive' ? 'Arquivar oportunidade' : 'Excluir oportunidade'} pending={saving} onCancel={() => setConfirmAction(null)} onConfirm={() => confirmAction.action === 'archive' ? archive(confirmAction.item) : remove(confirmAction.item)} />}
  </div>;
}

function FilterInput({ label, value, onChange, type = 'text' }: { label: string; value: string; onChange: (value: string) => void; type?: string }) { return <label><span className="text-xs font-medium text-gray-600 dark:text-slate-300">{label}</span><input type={type} className="input mt-1" value={value} onChange={(e) => onChange(e.target.value)} placeholder="Todos" /></label>; }
function FilterSelect({ label, value, items, onChange }: { label: string; value: string; items: Option[]; onChange: (value: string) => void }) { return <label><span className="text-xs font-medium text-gray-600 dark:text-slate-300">{label}</span><select className="input mt-1" value={value} onChange={(e) => onChange(e.target.value)}><option value="">Todos</option>{items.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>; }
function FormInput({ label, name, form, setForm, type = 'text', required, className = '' }: { label: string; name: string; form: Record<string,string>; setForm: (form: Record<string,string>) => void; type?: string; required?: boolean; className?: string }) { return <label className={className}><span className="text-xs font-medium text-gray-600 dark:text-slate-300">{label}</span><input required={required} type={type} step={type === 'number' ? '0.01' : undefined} className="input mt-1" value={form[name] ?? ''} onChange={(e) => setForm({ ...form, [name]: e.target.value })} /></label>; }
function normalizeKey(value?: string | null) {
  return (value ?? '').trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}
function OpportunityThumb({ src, alt }: { src?: string | null; alt: string }) {
  return <SafePhoto src={src} alt={alt} className="w-16 h-12 rounded-lg shrink-0" />;
}
function PhotoGallery({ photos, title }: { photos: NonNullable<Opportunity['photos']>; title: string }) {
  const initial = Math.max(0, photos.findIndex(photo => photo.is_cover));
  const [index, setIndex] = useState(initial);
  useEffect(() => setIndex(Math.max(0, photos.findIndex(photo => photo.is_cover))), [photos]);
  const selected = photos[index] ?? photos[0];
  const move = (offset: number) => setIndex(current => (current + offset + photos.length) % photos.length);
  return <section aria-label={`Galeria de fotos de ${title}`} className="space-y-3">
    <div className="relative rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-950">
      <SafePhoto src={selected.url} alt={selected.name || `${title}, foto ${index + 1}`} className="w-full h-72 md:h-96 rounded-xl" />
      {photos.length > 1 && <><button type="button" aria-label="Foto anterior" onClick={() => move(-1)} className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-slate-950/70 text-white p-2"><ChevronLeft className="h-5 w-5" /></button><button type="button" aria-label="Próxima foto" onClick={() => move(1)} className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-slate-950/70 text-white p-2"><ChevronRight className="h-5 w-5" /></button></>}
      <span className="absolute right-3 bottom-3 rounded-full bg-slate-950/70 text-white text-xs px-3 py-1">{index + 1} de {photos.length}</span>
    </div>
    {photos.length > 1 && <div className="flex gap-2 overflow-x-auto pb-1" role="list" aria-label="Miniaturas">{photos.map((photo, photoIndex) => <button type="button" role="listitem" key={photo.id} aria-label={`Abrir foto ${photoIndex + 1}`} aria-current={photoIndex === index ? 'true' : undefined} onClick={() => setIndex(photoIndex)} className={`shrink-0 rounded-lg border-2 ${photoIndex === index ? 'border-primary-600' : 'border-transparent'}`}><SafePhoto src={photo.url} alt="" className="h-16 w-24 rounded-md" /></button>)}</div>}
  </section>;
}
function SafePhoto({ src, alt, className }: { src?: string | null; alt: string; className: string }) {
  const [failed, setFailed] = useState(false);
  if (!src || failed) {
    return <div className={`${className} bg-violet-50 dark:bg-violet-950 grid place-items-center text-primary-600 dark:text-primary-400`}><Home className="w-5 h-5" /></div>;
  }

  return <img src={src} alt={alt} className={`${className} object-cover bg-gray-100 dark:bg-slate-800`} referrerPolicy="no-referrer" onError={() => setFailed(true)} />;
}
function PhotoPicker({ existing, files, selection, onSelection, onFiles }: { existing: NonNullable<Opportunity['photos']>; files: File[]; selection: string; onSelection: (value: string) => void; onFiles: (files: File[]) => void }) {
  const previews = useMemo(() => files.map((file) => ({ file, url: URL.createObjectURL(file) })), [files]);
  useEffect(() => () => previews.forEach((preview) => URL.revokeObjectURL(preview.url)), [previews]);
  return <div className="md:col-span-2"><div className="flex items-center justify-between"><span className="text-xs font-medium text-gray-600 dark:text-slate-300">Fotos</span><span className="text-xs text-gray-400">Clique em uma imagem para defini-la como capa</span></div><input className="input mt-1" type="file" multiple accept="image/jpeg,image/png,image/webp" onChange={(e) => onFiles([...(e.currentTarget.files ?? [])])} />
    {(existing.length > 0 || previews.length > 0) && <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3">
      {existing.map((photo) => <button type="button" key={`existing:${photo.id}`} onClick={() => onSelection(`existing:${photo.id}`)} className={`relative rounded-xl overflow-hidden border-2 aspect-4/3 ${selection === `existing:${photo.id}` ? 'border-primary-600 ring-2 ring-primary-100' : 'border-gray-200 dark:border-slate-700'}`}><img src={photo.url} className="w-full h-full object-cover" />{selection === `existing:${photo.id}` && <span className="absolute left-2 bottom-2 bg-primary-600 text-white text-[11px] font-bold px-2 py-1 rounded-md">★ Capa</span>}<span className="absolute right-2 top-2 bg-gray-900/60 text-white text-[10px] px-1.5 py-0.5 rounded-sm">Salva</span></button>)}
      {previews.map((preview, index) => <button type="button" key={`new:${index}`} onClick={() => onSelection(`new:${index}`)} className={`relative rounded-xl overflow-hidden border-2 aspect-4/3 ${selection === `new:${index}` ? 'border-primary-600 ring-2 ring-primary-100' : 'border-gray-200 dark:border-slate-700'}`}><img src={preview.url} className="w-full h-full object-cover" />{selection === `new:${index}` && <span className="absolute left-2 bottom-2 bg-primary-600 text-white text-[11px] font-bold px-2 py-1 rounded-md">★ Capa</span>}<span className="absolute right-2 top-2 bg-emerald-600 text-white text-[10px] px-1.5 py-0.5 rounded-sm">Nova</span></button>)}
    </div>}
    <div className="mt-2 text-xs text-gray-500 dark:text-slate-400">Até 20 fotos em JPG, PNG ou WebP, com no máximo 5 MB cada.</div>
  </div>;
}
function Modal({ title, onClose, children, wide }: { title: string; onClose: () => void; children: React.ReactNode; wide?: boolean }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const titleId = 'opportunity-modal-title';
  useEffect(() => {
    const element = dialog.current;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    element?.showModal();
    return () => { element?.close(); trigger?.focus(); };
  }, []);
  return <dialog ref={dialog} aria-labelledby={titleId} onCancel={onClose} onMouseDown={event => event.target === event.currentTarget && onClose()} className={`p-0 w-[calc(100%-2rem)] ${wide ? 'max-w-5xl' : 'max-w-3xl'} max-h-[92vh] rounded-2xl bg-white dark:bg-slate-900 text-gray-900 dark:text-slate-100 shadow-2xl backdrop:bg-gray-900/40 backdrop:backdrop-blur-[1px]`}><div className="max-h-[92vh] overflow-y-auto"><div className="sticky top-0 bg-white dark:bg-slate-900 z-10 px-6 py-4 border-b flex justify-between items-center"><h2 id={titleId} className="text-lg font-bold">{title}</h2><button type="button" aria-label="Fechar" onClick={onClose} className="p-2 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg"><X className="w-5 h-5" /></button></div><div className="p-6">{children}</div></div></dialog>;
}
