import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ArrowLeft, ArrowRight, KanbanSquare, List, Mail, Phone, Plus } from 'lucide-react';
import api from '@/lib/api';
import { contactsPath } from '@/lib/products';
import PageHeader from '@/ui/PageHeader';
import SearchInput from '@/ui/SearchInput';
import StatusBadge from '@/ui/StatusBadge';
import EmptyState from '@/ui/EmptyState';
import ErrorState from '@/ui/ErrorState';
import DialogShell from '@/ui/DialogShell';
import { digits, formatPhone } from '@/lib/brFields';

type Contact = {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  message?: string;
  status: string;
  source: string;
  kind?: string;
  listing?: { title: string } | null;
  created_at: string;
};
const PIPELINE = [
  { value: 'new', label: 'Novos', color: '#64748B' },
  { value: 'contacted', label: 'Contato iniciado', color: '#0EA5E9' },
  { value: 'qualified', label: 'Qualificados', color: '#8B5CF6' },
  { value: 'visit', label: 'Visita', color: '#F59E0B' },
  { value: 'proposal', label: 'Proposta', color: '#F97316' },
  { value: 'won', label: 'Fechados', color: '#10B981' },
  { value: 'lost', label: 'Perdidos', color: '#EF4444' },
];

export default function CrmContatos() {
  const location = useLocation();
  const isOwners = location.pathname.includes('proprietarios');
  const isCommercial = location.pathname.endsWith('/comercial');
  const [items, setItems] = useState<Contact[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [q, setQ] = useState('');
  const [form, setForm] = useState({ name: '', email: '', phone: '', message: '', kind: isOwners ? 'owner' : 'client' });
  const [saving, setSaving] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [status, setStatus] = useState('');
  const [source, setSource] = useState('');
  const [formSource, setFormSource] = useState('manual');
  const [view, setView] = useState<'kanban' | 'list'>(isCommercial ? 'kanban' : 'list');
  const [dragging, setDragging] = useState<number | null>(null);

  async function load() {
    setLoading(true); setError('');
    try {
      const { data } = await api.get('/crm/contacts', { params: { q: q || undefined, kind: isOwners ? 'owner' : undefined, status: status || undefined, per_page: isCommercial ? 100 : 20 } });
      setItems(data.data ?? []);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'CRM disponível no plano Corretor ou Imobiliária.');
    } finally { setLoading(false); }
  }

  useEffect(() => { void load(); }, [q, isOwners, status]);

  async function create(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      await api.post('/crm/contacts', { ...form, phone: form.phone ? digits(form.phone) : null, source: formSource });
      setForm({ name: '', email: '', phone: '', message: '', kind: isOwners ? 'owner' : 'client' });
      setFormSource('manual');
      setFormOpen(false);
      await load();
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar.');
    } finally { setSaving(false); }
  }

  const filteredItems = useMemo(() => source ? items.filter((item) => item.source === source) : items, [items, source]);

  async function changeStatus(id: number, status: string) {
    setItems((current) => current.map((item) => item.id === id ? { ...item, status } : item));
    try {
      await api.patch(`/crm/contacts/${id}`, { status });
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível atualizar o contato.');
      await load();
    }
  }
  function shiftStatus(item: Contact, direction: -1 | 1) { const index = PIPELINE.findIndex((column) => column.value === item.status); const target = PIPELINE[index + direction]; if (target) void changeStatus(item.id, target.value); }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><PageHeader title={isCommercial ? 'Kanban comercial' : isOwners ? 'Proprietários' : 'Clientes e leads'} subtitle={isCommercial ? 'Acompanhe cada oportunidade e arraste os cartões entre as etapas.' : `${items.length} contato${items.length === 1 ? '' : 's'} nesta visualização. Site público e cadastro manual usam a mesma base.`} /><div className="flex flex-wrap gap-2">{isCommercial && <div className="flex rounded-xl border bg-white p-1 dark:bg-slate-900"><button type="button" aria-label="Ver Kanban" onClick={()=>setView('kanban')} className={`rounded-lg p-2 ${view==='kanban'?'bg-primary-600 text-white':''}`}><KanbanSquare className="h-4 w-4"/></button><button type="button" aria-label="Ver lista" onClick={()=>setView('list')} className={`rounded-lg p-2 ${view==='list'?'bg-primary-600 text-white':''}`}><List className="h-4 w-4"/></button></div>}<button type="button" onClick={() => setFormOpen(true)} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white"><Plus className="h-4 w-4" />Novo {isOwners ? 'proprietário' : 'cliente'}</button></div></div>
      {error && <ErrorState message={error} />}
      <section className="grid gap-3 rounded-2xl border bg-white p-4 dark:bg-slate-900 md:grid-cols-[minmax(0,1fr)_12rem_12rem]"><SearchInput value={q} onChange={setQ} placeholder="Nome, e-mail ou telefone" /><label className="sr-only" htmlFor="contact-status">Status</label><select id="contact-status" className="input" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Todos os status</option>{['new', 'contacted', 'qualified', 'visit', 'proposal', 'won', 'lost'].map((value) => <option key={value} value={value}>{value}</option>)}</select><label className="sr-only" htmlFor="contact-source">Origem</label><select id="contact-source" className="input" value={source} onChange={(event) => setSource(event.target.value)}><option value="">Todas as origens</option><option value="manual">Cadastro manual</option><option value="public_form">Site público</option><option value="referral">Indicação</option><option value="event">Evento</option></select></section>
      {view === 'kanban' && isCommercial ? <div className="overflow-x-auto pb-4"><div className="flex min-w-max items-start gap-3">{PIPELINE.map((column,columnIndex) => { const cards = filteredItems.filter((item)=>item.status===column.value); return <section key={column.value} onDragOver={(e)=>e.preventDefault()} onDrop={()=>{if(dragging!==null) void changeStatus(dragging,column.value);setDragging(null);}} className="w-72 shrink-0 rounded-2xl bg-slate-100 p-3 dark:bg-slate-900"><header className="mb-3 flex items-center justify-between"><div className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-full" style={{background:column.color}}/><h2 className="text-sm font-bold">{column.label}</h2></div><span className="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-slate-500 dark:bg-slate-800">{cards.length}</span></header><div className="space-y-3">{cards.map((item)=><article key={item.id} draggable onDragStart={()=>setDragging(item.id)} onDragEnd={()=>setDragging(null)} className="cursor-grab rounded-xl border border-slate-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800"><div className="mb-2 flex items-start justify-between gap-2"><Link className="font-bold hover:text-primary-600" to={contactsPath(location.pathname,item.id)}>{item.name}</Link><StatusBadge value={item.source}/></div>{item.listing?.title&&<p className="mb-2 text-xs font-medium text-primary-600">{item.listing.title}</p>}<div className="space-y-1 text-xs text-slate-500">{item.email&&<p className="flex items-center gap-1.5 truncate"><Mail className="h-3.5 w-3.5"/>{item.email}</p>}{item.phone&&<p className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5"/>{item.phone}</p>}</div>{item.message&&<p className="mt-3 line-clamp-2 text-xs text-slate-600 dark:text-slate-300">{item.message}</p>}<div className="mt-3 flex items-center justify-between border-t pt-2 dark:border-slate-700"><button type="button" aria-label="Mover para etapa anterior" disabled={columnIndex===0} onClick={()=>shiftStatus(item,-1)} className="rounded-lg p-1.5 disabled:opacity-20"><ArrowLeft className="h-4 w-4"/></button><span className="text-[10px] text-slate-400">{new Date(item.created_at).toLocaleDateString('pt-BR')}</span><button type="button" aria-label="Mover para próxima etapa" disabled={columnIndex===PIPELINE.length-1} onClick={()=>shiftStatus(item,1)} className="rounded-lg p-1.5 disabled:opacity-20"><ArrowRight className="h-4 w-4"/></button></div></article>)}{!loading&&cards.length===0&&<p className="rounded-xl border border-dashed border-slate-300 p-6 text-center text-xs text-slate-400 dark:border-slate-700">Solte um cartão aqui</p>}</div></section>})}</div></div> : <div className="bg-white dark:bg-slate-900 border rounded-xl overflow-x-auto">
        {loading ? <p className="p-8 text-center text-gray-400">Carregando...</p> : filteredItems.length === 0 ? (
          <EmptyState title="Nenhum contato encontrado" description="Ajuste os filtros ou cadastre o primeiro contato desta origem." />
        ) : (
          <table className="w-full text-sm min-w-[40rem]">
            <thead className="bg-gray-50 dark:bg-slate-950 text-[11px] uppercase text-gray-500"><tr>{['Nome', 'Contato', 'Imóvel', 'Status', 'Quando', ''].map((h) => <th key={h} className="text-left px-4 py-3">{h}</th>)}</tr></thead>
            <tbody className="divide-y">
              {filteredItems.map((item) => (
                <tr key={item.id}>
                  <td className="px-4 py-3 font-semibold">{item.name}<div className="text-xs text-gray-500">{item.message}</div></td>
                  <td className="px-4 py-3">{item.email || item.phone || '—'}</td>
                  <td className="px-4 py-3">{item.listing?.title ?? '—'}</td>
                  <td className="px-4 py-3">
                    <select className="input" value={item.status} onChange={(e) => void changeStatus(item.id, e.target.value)}>
                      {['new', 'contacted', 'qualified', 'visit', 'proposal', 'won', 'lost'].map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                  </td>
                  <td className="px-4 py-3">{new Date(item.created_at).toLocaleString('pt-BR')}</td>
                  <td className="px-4 py-3"><Link className="text-primary-600 font-semibold" to={contactsPath(location.pathname, item.id)}>Ficha</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>}
      {formOpen && <DialogShell title={isOwners ? 'Novo proprietário' : 'Novo cliente'} description="Cadastre os dados conhecidos. E-mail e telefone são opcionais, mas devem ser válidos quando informados." onClose={() => setFormOpen(false)} footer={<div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" onClick={() => setFormOpen(false)} className="rounded-xl border px-4 py-2.5 font-semibold">Cancelar</button><button type="submit" form="contact-form" disabled={saving} className="rounded-xl bg-primary-600 px-4 py-2.5 font-semibold text-white disabled:opacity-60">{saving ? 'Salvando...' : 'Salvar contato'}</button></div>}>
        <form id="contact-form" onSubmit={create} className="grid gap-4 sm:grid-cols-2">
          <label className="text-sm font-medium sm:col-span-2">Nome *<input autoFocus required autoComplete="name" maxLength={180} className="input mt-1" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
          <label className="text-sm font-medium">E-mail<input type="email" autoComplete="email" maxLength={180} className="input mt-1" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
          <label className="text-sm font-medium">Telefone<input type="tel" inputMode="tel" autoComplete="tel" maxLength={15} placeholder="(11) 99999-9999" className="input mt-1" value={form.phone} onChange={(e) => setForm({ ...form, phone: formatPhone(e.target.value) })} /></label>
          <label className="text-sm font-medium">Origem<select className="input mt-1" value={formSource} onChange={(event) => setFormSource(event.target.value)}><option value="manual">Cadastro manual</option><option value="referral">Indicação</option><option value="event">Evento</option></select></label>
          <label className="text-sm font-medium sm:col-span-2">Observações<textarea maxLength={5000} rows={5} className="input mt-1 resize-y" value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} /></label>
        </form>
      </DialogShell>}
    </div>
  );
}
