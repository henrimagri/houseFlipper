import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Bath, BedDouble, Box, Building2, CalendarDays, Car, CircleDollarSign, Edit3, Home, Loader2, MapPin, Plus, RefreshCw, Search, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import { emptyPropertyForm, propertyPayload, type PropertyForm } from '@/lib/propertyForm';
import DialogShell from '@/ui/DialogShell';
import { digits, formatCep } from '@/lib/brFields';

type Address = { cep?: string | null; logradouro?: string | null; numero?: string | null; complemento?: string | null; bairro?: string | null; cidade?: string | null; estado?: string | null; pais?: string | null };
type Property = {
  id: number;
  apelido: string;
  tipo?: string | null;
  area_m2?: string | number | null;
  quartos?: number | null;
  banheiros?: number | null;
  vagas?: number | null;
  valor_aquisicao?: string | number | null;
  data_aquisicao?: string | null;
  valor_venda_alvo?: string | number | null;
  status: string;
  observacoes?: string | null;
  endereco?: Address | null;
};

const statuses = [
  { value: 'prospeccao', label: 'Prospecção' },
  { value: 'adquirida', label: 'Adquirida' },
  { value: 'em_reforma', label: 'Em reforma' },
  { value: 'a_venda', label: 'À venda' },
  { value: 'vendida', label: 'Vendida' },
];
const money = (value?: string | number | null) => value === null || value === undefined || value === '' ? 'Não informado' : Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const date = (value?: string | null) => value ? new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`)) : 'Não informada';
const statusLabel = (value?: string) => statuses.find(item => item.value === value)?.label || value || 'Não informado';

function errorMessage(error: any, fallback: string) {
  return Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || error.response?.data?.message || fallback;
}

function toForm(property: Property): PropertyForm {
  const address = property.endereco ?? {};
  const text = (value: unknown) => value === null || value === undefined ? '' : String(value);
  return {
    apelido: property.apelido, tipo: text(property.tipo), area_m2: text(property.area_m2), quartos: text(property.quartos),
    banheiros: text(property.banheiros), vagas: text(property.vagas), valor_aquisicao: text(property.valor_aquisicao),
    data_aquisicao: text(property.data_aquisicao), valor_venda_alvo: text(property.valor_venda_alvo), status: property.status,
    observacoes: text(property.observacoes), cep: text(address.cep), logradouro: text(address.logradouro), numero: text(address.numero),
    complemento: text(address.complemento), bairro: text(address.bairro), cidade: text(address.cidade), estado: text(address.estado), pais: text(address.pais || 'Brasil'),
  };
}

export default function AnaliseImovel() {
  const navigate = useNavigate();
  const loader = useRef(new PaginatedCollection<Property>());
  const sequence = useRef(0);
  const [properties, setProperties] = useState<Property[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('todos');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [dialog, setDialog] = useState<{ mode: 'create' | 'edit'; form: PropertyForm } | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [confirmDelete, setConfirmDelete] = useState(false);

  async function load(silent = false) {
    const request = ++sequence.current;
    if (silent) setRefreshing(true); else setLoading(true);
    setError('');
    try {
      const items = await loader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/propriedades', { params: { page, per_page: perPage }, signal });
        return response.data;
      });
      if (!items || request !== sequence.current) return false;
      setProperties(items);
      setSelectedId(current => current && items.some(item => item.id === current) ? current : (items[0]?.id ?? null));
      return true;
    } catch (exception: any) {
      if (request === sequence.current) setError(errorMessage(exception, 'Não foi possível carregar os imóveis.'));
      return false;
    } finally {
      if (request === sequence.current) { setLoading(false); setRefreshing(false); }
    }
  }

  useEffect(() => {
    void load();
    return () => { sequence.current++; loader.current.cancel(); };
  }, []);

  const visible = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase('pt-BR');
    return properties.filter(property => {
      if (status !== 'todos' && property.status !== status) return false;
      if (!needle) return true;
      const address = property.endereco;
      return [property.apelido, property.tipo, address?.logradouro, address?.bairro, address?.cidade, address?.estado].filter(Boolean).join(' ').toLocaleLowerCase('pt-BR').includes(needle);
    });
  }, [properties, search, status]);
  const selected = properties.find(item => item.id === selectedId) ?? null;
  const totalAcquisition = properties.reduce((cents, property) => cents + Math.round(Number(property.valor_aquisicao || 0) * 100), 0) / 100;

  function openCreate() { setDialog({ mode: 'create', form: { ...emptyPropertyForm } }); setFormError(''); setConfirmDelete(false); }
  function openEdit(property: Property) { setDialog({ mode: 'edit', form: toForm(property) }); setFormError(''); setConfirmDelete(false); }
  function setField<K extends keyof PropertyForm>(field: K, value: PropertyForm[K]) { setDialog(current => current ? { ...current, form: { ...current.form, [field]: value } } : current); }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!dialog) return;
    setSaving(true); setFormError('');
    try {
      const response = dialog.mode === 'create'
        ? await api.post('/propriedades', propertyPayload(dialog.form))
        : await api.put(`/propriedades/${selectedId}`, propertyPayload(dialog.form));
      const saved: Property = response.data.data ?? response.data;
      setDialog(null);
      const refreshed = await load(true);
      setSelectedId(saved.id);
      if (!refreshed) setError('Imóvel salvo, mas a lista não pôde ser recarregada. Use Atualizar antes de repetir a operação.');
    } catch (exception: any) {
      setFormError(errorMessage(exception, 'Não foi possível salvar o imóvel.'));
    } finally { setSaving(false); }
  }

  async function removeSelected() {
    if (!selected) return;
    setSaving(true); setError('');
    try {
      await api.delete(`/propriedades/${selected.id}`);
      setConfirmDelete(false);
      const refreshed = await load(true);
      if (!refreshed) setError('Imóvel excluído, mas a lista não pôde ser recarregada. Use Atualizar.');
    } catch (exception: any) {
      setError(errorMessage(exception, 'Não foi possível excluir o imóvel.'));
    } finally { setSaving(false); }
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center"><div className="text-center text-slate-500 dark:text-slate-400"><Loader2 className="h-8 w-8 mx-auto animate-spin text-primary-600" /><p className="mt-3">Carregando imóveis...</p></div></div>;

  return <div className="space-y-6">
    <header className="flex flex-col lg:flex-row lg:items-end justify-between gap-4"><div><div className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Patrimônio da conta</div><h1 className="text-3xl font-black mt-2 text-slate-900 dark:text-slate-100">Imóveis</h1><p className="text-slate-500 dark:text-slate-400 mt-1">Consulte e mantenha os dados reais dos imóveis vinculados às suas operações.</p></div><div className="flex flex-wrap gap-2"><button type="button" onClick={() => void load(true)} disabled={refreshing} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-bold disabled:opacity-60"><RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Atualizar</button><button type="button" onClick={openCreate} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 text-sm font-bold"><Plus className="h-4 w-4" /> Novo imóvel</button></div></header>

    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950 p-4 text-red-800 dark:text-red-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><span>{error}</span><button type="button" onClick={() => void load()} className="font-bold underline underline-offset-2 w-fit">Tentar novamente</button></div>}

    <section aria-label="Indicadores dos imóveis" className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4"><Metric icon={Home} label="Imóveis cadastrados" value={String(properties.length)} detail="Registros ativos da conta" /><Metric icon={Search} label="Em prospecção" value={String(properties.filter(item => item.status === 'prospeccao').length)} detail="Em avaliação" /><Metric icon={Building2} label="Em reforma" value={String(properties.filter(item => item.status === 'em_reforma').length)} detail="Execução em andamento" /><Metric icon={CircleDollarSign} label="Aquisição informada" value={money(totalAcquisition)} detail="Soma dos valores cadastrados" /></section>

    <section className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 flex flex-col md:flex-row gap-3"><label className="relative flex-1"><span className="sr-only">Buscar imóveis</span><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Buscar nome, tipo ou endereço" className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-2 text-sm" /></label><label><span className="sr-only">Filtrar por status</span><select value={status} onChange={event => setStatus(event.target.value)} className="w-full md:w-52 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm"><option value="todos">Todos os status</option>{statuses.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label></section>

    {properties.length === 0 && !error ? <EmptyState onCreate={openCreate} onOpportunity={() => navigate('/leads')} /> : <div className="grid xl:grid-cols-[minmax(0,1fr)_390px] gap-6 items-start"><section aria-label="Lista de imóveis" className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">{visible.length === 0 ? <div className="p-10 text-center text-slate-500"><Search className="h-8 w-8 mx-auto mb-3" /><p className="font-semibold text-slate-700 dark:text-slate-200">Nenhum imóvel corresponde aos filtros.</p><button type="button" onClick={() => { setSearch(''); setStatus('todos'); }} className="mt-3 font-bold text-primary-600 dark:text-primary-400">Limpar filtros</button></div> : <div className="divide-y divide-slate-100 dark:divide-slate-800">{visible.map(property => <PropertyRow key={property.id} property={property} selected={property.id === selectedId} onSelect={() => { setSelectedId(property.id); setConfirmDelete(false); }} />)}</div>}</section><aside className="xl:sticky xl:top-6">{selected && <PropertyDetail property={selected} confirmDelete={confirmDelete} saving={saving} onEdit={() => openEdit(selected)} onAskDelete={() => setConfirmDelete(true)} onCancelDelete={() => setConfirmDelete(false)} onDelete={() => void removeSelected()} />}</aside></div>}

    {dialog && <PropertyDialog mode={dialog.mode} form={dialog.form} saving={saving} error={formError} onField={setField} onClose={() => !saving && setDialog(null)} onSubmit={save} />}
  </div>;
}

function PropertyRow({ property, selected, onSelect }: { property: Property; selected: boolean; onSelect: () => void }) {
  const address = property.endereco;
  const location = [address?.bairro, address?.cidade, address?.estado].filter(Boolean).join(' · ') || 'Endereço não informado';
  return <button type="button" onClick={onSelect} className={`w-full p-5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60 ${selected ? 'bg-primary-50/70 dark:bg-primary-950/40' : ''}`}><div className="flex gap-4"><div className="h-12 w-12 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0"><Home className="h-6 w-6" /></div><div className="min-w-0 flex-1"><div className="flex flex-wrap justify-between gap-2"><div><h2 className="font-bold text-slate-900 dark:text-slate-100">{property.apelido}</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{location}</p></div><StatusBadge value={property.status} /></div><div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-xs text-slate-500 dark:text-slate-400"><span>Tipo<br /><b className="text-slate-700 dark:text-slate-200">{property.tipo || 'Não informado'}</b></span><span>Área<br /><b className="text-slate-700 dark:text-slate-200">{property.area_m2 ? `${Number(property.area_m2).toLocaleString('pt-BR')} m²` : 'Não informada'}</b></span><span>Aquisição<br /><b className="text-slate-700 dark:text-slate-200">{money(property.valor_aquisicao)}</b></span><span>Venda alvo<br /><b className="text-slate-700 dark:text-slate-200">{money(property.valor_venda_alvo)}</b></span></div></div></div></button>;
}

function PropertyDetail({ property, confirmDelete, saving, onEdit, onAskDelete, onCancelDelete, onDelete }: { property: Property; confirmDelete: boolean; saving: boolean; onEdit: () => void; onAskDelete: () => void; onCancelDelete: () => void; onDelete: () => void }) {
  const navigate = useNavigate();
  const address = property.endereco;
  const street = [address?.logradouro, address?.numero].filter(Boolean).join(', ');
  const location = [address?.bairro, address?.cidade, address?.estado].filter(Boolean).join(' · ');
  return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden"><div className="p-5 border-b border-slate-100 dark:border-slate-800"><div className="flex items-start justify-between gap-3"><div><p className="text-xs uppercase tracking-widest text-primary-600 dark:text-primary-400 font-bold">Detalhes do imóvel</p><h2 className="text-xl font-black mt-1 text-slate-900 dark:text-slate-100">{property.apelido}</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{property.tipo || 'Tipo não informado'}</p></div><StatusBadge value={property.status} /></div><div className="mt-5 flex gap-2"><button type="button" onClick={onEdit} className="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 text-white px-3 py-2 text-sm font-bold"><Edit3 className="h-4 w-4" /> Editar</button><button type="button" onClick={() => navigate(`/imoveis/${property.id}/3d`)} className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-bold"><Box className="h-4 w-4" /> 3D</button><button type="button" onClick={onAskDelete} aria-label="Excluir imóvel" className="rounded-lg border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 p-2.5"><Trash2 className="h-4 w-4" /></button></div></div>
    <div className="p-5 space-y-5"><section><h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2"><MapPin className="h-4 w-4 text-primary-600" /> Endereço</h3><p className="text-sm text-slate-600 dark:text-slate-300 mt-2">{street || 'Logradouro não informado'}{address?.complemento ? ` · ${address.complemento}` : ''}<br />{location || 'Localidade não informada'}{address?.cep ? <><br />CEP {address.cep}</> : null}</p></section>
      <section className="grid grid-cols-2 gap-3"><Spec icon={BedDouble} label="Quartos" value={property.quartos ?? '—'} /><Spec icon={Bath} label="Banheiros" value={property.banheiros ?? '—'} /><Spec icon={Car} label="Vagas" value={property.vagas ?? '—'} /><Spec icon={Building2} label="Área" value={property.area_m2 ? `${Number(property.area_m2).toLocaleString('pt-BR')} m²` : '—'} /></section>
      <section className="rounded-xl bg-slate-50 dark:bg-slate-950 p-4 space-y-3"><Line label="Valor de aquisição" value={money(property.valor_aquisicao)} /><Line label="Data de aquisição" value={date(property.data_aquisicao)} /><Line label="Valor de venda alvo" value={money(property.valor_venda_alvo)} /></section>
      {property.observacoes && <section><h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Observações</h3><p className="text-sm text-slate-600 dark:text-slate-300 mt-2 whitespace-pre-wrap break-words">{property.observacoes}</p></section>}
      {confirmDelete && <div role="alertdialog" aria-label="Confirmar exclusão do imóvel" className="rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-4"><p className="text-sm font-bold text-red-900 dark:text-red-200">Excluir “{property.apelido}”?</p><p className="text-xs text-red-800 dark:text-red-300 mt-1">O registro deixará de aparecer nas consultas. Projetos vinculados não são removidos por esta ação.</p><div className="flex gap-2 mt-3"><button type="button" onClick={onCancelDelete} disabled={saving} className="flex-1 rounded-lg border border-red-300 px-3 py-2 text-xs font-bold">Cancelar</button><button type="button" onClick={onDelete} disabled={saving} className="flex-1 rounded-lg bg-red-600 text-white px-3 py-2 text-xs font-bold disabled:opacity-60">{saving ? 'Excluindo...' : 'Confirmar exclusão'}</button></div></div>}
    </div></div>;
}

function PropertyDialog({ mode, form, saving, error, onField, onClose, onSubmit }: { mode: 'create' | 'edit'; form: PropertyForm; saving: boolean; error: string; onField: <K extends keyof PropertyForm>(field: K, value: PropertyForm[K]) => void; onClose: () => void; onSubmit: (event: FormEvent) => void }) {
  async function lookupCep() {
    const postalCode = digits(form.cep);
    if (postalCode.length !== 8) return;
    try {
      const { data } = await api.get(`/auth/postal-code/${postalCode}`);
      if (data.street) onField('logradouro', data.street);
      if (data.neighborhood) onField('bairro', data.neighborhood);
      if (data.city) onField('cidade', data.city);
      if (data.state) onField('estado', data.state);
    } catch { /* o preenchimento manual continua disponível */ }
  }
  return <DialogShell title={mode === 'create' ? 'Novo imóvel' : 'Editar imóvel'} description="Preencha somente informações conhecidas. Os valores ficam vinculados à sua conta." onClose={onClose} closeDisabled={saving} size="lg">
      <form onSubmit={onSubmit} className="space-y-6"><fieldset className="grid md:grid-cols-3 gap-4"><legend className="text-sm font-bold mb-3 md:col-span-3">Dados principais</legend><Field label="Nome do imóvel *"><input autoFocus required maxLength={120} value={form.apelido} onChange={event => onField('apelido', event.target.value)} className="input" /></Field><Field label="Tipo"><input maxLength={40} placeholder="Casa, apartamento, terreno" value={form.tipo} onChange={event => onField('tipo', event.target.value)} className="input" /></Field><Field label="Status"><select value={form.status} onChange={event => onField('status', event.target.value)} className="input">{statuses.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field><NumberField label="Área (m²)" value={form.area_m2} onChange={value => onField('area_m2', value)} step="0.01" max="99999999.99" /><NumberField label="Quartos" value={form.quartos} onChange={value => onField('quartos', value)} max="1000" /><NumberField label="Banheiros" value={form.banheiros} onChange={value => onField('banheiros', value)} max="1000" /><NumberField label="Vagas" value={form.vagas} onChange={value => onField('vagas', value)} max="1000" /><NumberField label="Valor de aquisição" value={form.valor_aquisicao} onChange={value => onField('valor_aquisicao', value)} step="0.01" max="999999999999" /><Field label="Data de aquisição"><input type="date" value={form.data_aquisicao} onChange={event => onField('data_aquisicao', event.target.value)} className="input" /></Field><NumberField label="Valor de venda alvo" value={form.valor_venda_alvo} onChange={value => onField('valor_venda_alvo', value)} step="0.01" max="999999999999" /></fieldset>
        <fieldset className="grid md:grid-cols-4 gap-4"><legend className="text-sm font-bold mb-3 md:col-span-4">Endereço</legend><Field label="CEP"><input inputMode="numeric" autoComplete="postal-code" maxLength={9} placeholder="00000-000" value={form.cep} onBlur={() => void lookupCep()} onChange={event => onField('cep', formatCep(event.target.value))} className="input" /></Field><div className="md:col-span-2"><Field label="Logradouro"><input autoComplete="street-address" maxLength={200} value={form.logradouro} onChange={event => onField('logradouro', event.target.value)} className="input" /></Field></div><Field label="Número"><input inputMode="numeric" maxLength={20} value={form.numero} onChange={event => onField('numero', event.target.value)} className="input" /></Field><Field label="Complemento"><input maxLength={100} value={form.complemento} onChange={event => onField('complemento', event.target.value)} className="input" /></Field><Field label="Bairro"><input maxLength={100} value={form.bairro} onChange={event => onField('bairro', event.target.value)} className="input" /></Field><Field label="Cidade"><input maxLength={100} value={form.cidade} onChange={event => onField('cidade', event.target.value)} className="input" /></Field><Field label="UF"><input maxLength={2} pattern="[A-Za-z]{2}" value={form.estado} onChange={event => onField('estado', event.target.value.toUpperCase())} className="input uppercase" /></Field></fieldset>
        <Field label="Observações"><textarea rows={3} maxLength={5000} value={form.observacoes} onChange={event => onField('observacoes', event.target.value)} className="input resize-y" /></Field>
        {error && <p role="alert" className="rounded-lg bg-red-50 dark:bg-red-950 p-3 text-sm text-red-800 dark:text-red-200">{error}</p>}
        <div className="flex justify-end gap-2 border-t border-slate-200 dark:border-slate-700 pt-4"><button type="button" onClick={onClose} disabled={saving} className="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-bold">Cancelar</button><button type="submit" disabled={saving} className="rounded-lg bg-primary-600 text-white px-5 py-2 text-sm font-bold disabled:opacity-60">{saving ? 'Salvando...' : mode === 'create' ? 'Cadastrar imóvel' : 'Salvar alterações'}</button></div></form></DialogShell>;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="block"><span className="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">{label}</span>{children}</label>; }
function NumberField({ label, value, onChange, step = '1', max }: { label: string; value: string; onChange: (value: string) => void; step?: string; max: string }) { return <Field label={label}><input type="number" min="0" max={max} step={step} value={value} onChange={event => onChange(event.target.value)} className="input" /></Field>; }
function Line({ label, value }: { label: string; value: string }) { return <div className="flex justify-between gap-3 text-sm"><span className="text-slate-500 dark:text-slate-400">{label}</span><b className="text-right text-slate-900 dark:text-slate-100">{value}</b></div>; }
function Spec({ icon: Icon, label, value }: { icon: typeof Home; label: string; value: string | number }) { return <div className="rounded-xl border border-slate-200 dark:border-slate-700 p-3"><Icon className="h-4 w-4 text-primary-600 dark:text-primary-400" /><p className="text-[11px] text-slate-500 dark:text-slate-400 mt-2">{label}</p><p className="font-bold text-sm mt-0.5">{value}</p></div>; }

function StatusBadge({ value }: { value: string }) {
  const classes: Record<string, string> = { prospeccao: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200', adquirida: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200', em_reforma: 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200', a_venda: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200', vendida: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' };
  return <span className={`rounded-full px-2.5 py-1 text-xs font-bold whitespace-nowrap ${classes[value] || 'bg-slate-100 text-slate-700'}`}>{statusLabel(value)}</span>;
}

function Metric({ icon: Icon, label, value, detail }: { icon: typeof Home; label: string; value: string; detail: string }) { return <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5"><div className="flex items-start justify-between gap-3"><div><p className="text-sm text-slate-500 dark:text-slate-400">{label}</p><p className="text-2xl font-black mt-2 text-slate-900 dark:text-slate-100">{value}</p></div><div className="h-10 w-10 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="h-5 w-5" /></div></div><p className="text-xs text-slate-500 dark:text-slate-400 mt-2">{detail}</p></div>; }

function EmptyState({ onCreate, onOpportunity }: { onCreate: () => void; onOpportunity: () => void }) { return <div className="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 py-16 px-6 text-center"><div className="h-14 w-14 rounded-2xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center mx-auto"><Home className="h-7 w-7" /></div><h2 className="text-xl font-black mt-5">Nenhum imóvel cadastrado</h2><p className="text-slate-500 dark:text-slate-400 mt-2 max-w-lg mx-auto">Cadastre um imóvel diretamente ou converta uma oportunidade após concluir a análise financeira.</p><div className="mt-5 flex flex-wrap justify-center gap-2"><button type="button" onClick={onCreate} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 text-white px-5 py-3 font-bold"><Plus className="h-4 w-4" /> Cadastrar imóvel</button><button type="button" onClick={onOpportunity} className="rounded-xl border border-slate-300 dark:border-slate-700 px-5 py-3 font-bold">Ver oportunidades</button></div></div>; }
