import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Building2, CheckCircle2, Loader2, Mail, Pencil, Phone, Plus, Search, Star, Trash2, Users, X } from 'lucide-react';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';

type Supplier = {
  id: number;
  nome: string;
  categoria: string;
  contato?: string | null;
  telefone?: string | null;
  email?: string | null;
  avaliacao?: number | string | null;
  observacoes?: string | null;
  ativo_flg: boolean;
};

type SupplierForm = {
  nome: string;
  categoria: string;
  contato: string;
  telefone: string;
  email: string;
  avaliacao: string;
  observacoes: string;
  ativo_flg: boolean;
};

const emptyForm: SupplierForm = { nome: '', categoria: '', contato: '', telefone: '', email: '', avaliacao: '', observacoes: '', ativo_flg: true };

export default function Fornecedores() {
  const loader = useRef(new PaginatedCollection<Supplier>());
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null | undefined>(undefined);
  const [deleteTarget, setDeleteTarget] = useState<Supplier | null>(null);
  const [form, setForm] = useState<SupplierForm>(emptyForm);

  async function load(showSpinner = true) {
    if (showSpinner) setLoading(true);
    setError('');
    try {
      const items = await loader.current.load(async (page, perPage, signal) => {
        const response = await api.get('/fornecedores', { params: { page, per_page: perPage }, signal });
        return response.data;
      });
      if (!items) return false;
      setSuppliers(items);
      setSelectedId(current => current && items.some(item => item.id === current) ? current : null);
      return true;
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? exception.message ?? 'Não foi possível carregar os fornecedores.'); return false;
    } finally { setLoading(false); }
  }

  useEffect(() => { load(); return () => loader.current.cancel(); }, []);

  const categories = useMemo(() => [...new Set(suppliers.map(item => item.categoria))].sort((a, b) => a.localeCompare(b, 'pt-BR')), [suppliers]);
  const filtered = useMemo(() => {
    const query = search.trim().toLocaleLowerCase('pt-BR');
    return suppliers.filter(item => {
      if (category && item.categoria !== category) return false;
      if (status === 'ativo' && !item.ativo_flg) return false;
      if (status === 'inativo' && item.ativo_flg) return false;
      if (!query) return true;
      return [item.nome, item.categoria, item.contato, item.email, item.telefone].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(query));
    });
  }, [suppliers, search, category, status]);
  const selected = suppliers.find(item => item.id === selectedId) ?? null;
  const active = suppliers.filter(item => item.ativo_flg).length;
  const rated = suppliers.filter(item => item.avaliacao !== null && item.avaliacao !== undefined);
  const average = rated.length ? rated.reduce((sum, item) => sum + Number(item.avaliacao), 0) / rated.length : null;

  function openCreate() { setForm(emptyForm); setEditingId(null); setError(''); setNotice(''); }
  function openEdit(supplier: Supplier) {
    setForm({ nome: supplier.nome, categoria: supplier.categoria, contato: supplier.contato ?? '', telefone: supplier.telefone ?? '', email: supplier.email ?? '', avaliacao: supplier.avaliacao === null || supplier.avaliacao === undefined ? '' : String(supplier.avaliacao), observacoes: supplier.observacoes ?? '', ativo_flg: supplier.ativo_flg });
    setEditingId(supplier.id); setError(''); setNotice('');
  }

  async function save(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError(''); setNotice('');
    const payload = {
      ...form,
      nome: form.nome.trim(), categoria: form.categoria.trim(),
      contato: form.contato.trim() || null, telefone: form.telefone.trim() || null,
      email: form.email.trim() || null, avaliacao: form.avaliacao === '' ? null : Number(form.avaliacao),
      observacoes: form.observacoes.trim() || null,
    };
    try {
      if (editingId) await api.put(`/fornecedores/${editingId}`, payload);
      else await api.post('/fornecedores', payload);
      setEditingId(undefined);
      const success = editingId ? 'Fornecedor atualizado.' : 'Fornecedor cadastrado.';
      const refreshed = await load(false);
      setNotice(refreshed ? success : `${success} A lista não pôde ser recarregada; use Atualizar antes de repetir a operação.`);
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar o fornecedor.');
    } finally { setSaving(false); }
  }

  async function remove(supplier: Supplier) {
    setSaving(true); setError(''); setNotice('');
    try {
      await api.delete(`/fornecedores/${supplier.id}`);
      setDeleteTarget(null); setSelectedId(null); const refreshed = await load(false); setNotice(refreshed ? 'Fornecedor excluído.' : 'Fornecedor excluído, mas a lista não pôde ser recarregada. Use Atualizar.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível excluir o fornecedor.');
      setDeleteTarget(null);
    } finally { setSaving(false); }
  }

  if (loading) return <div className="min-h-[50vh] grid place-items-center text-slate-500 dark:text-slate-400"><div className="text-center"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando fornecedores...</p></div></div>;

  return <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6">
    <main className="space-y-6 min-w-0">
      <header className="flex items-start justify-between gap-4 flex-wrap"><div><h1 className="text-2xl font-semibold">Fornecedores</h1><p className="text-sm text-slate-500 dark:text-slate-400">Cadastre e consulte contatos vinculados à sua conta.</p></div><button type="button" onClick={openCreate} className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center gap-2"><Plus className="h-4 w-4" />Novo fornecedor</button></header>

      {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300 flex justify-between gap-3"><span>{error}</span>{editingId === undefined && <button type="button" onClick={() => load()} className="font-bold underline">Tentar novamente</button>}</div>}
      {notice && <div role="status" className="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950 p-4 text-sm text-emerald-700 dark:text-emerald-300">{notice}</div>}

      <section aria-label="Resumo dos fornecedores" className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <Metric icon={Users} label="Cadastrados" value={String(suppliers.length)} />
        <Metric icon={CheckCircle2} label="Ativos" value={String(active)} />
        <Metric icon={Building2} label="Categorias" value={String(categories.length)} />
        <Metric icon={Star} label="Avaliação média" value={average === null ? 'Sem avaliações' : average.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} />
      </section>

      <section aria-label="Filtros de fornecedores" className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid md:grid-cols-[1fr_220px_180px] gap-3">
        <label className="text-xs text-slate-500 dark:text-slate-400">Buscar<span className="relative block mt-1"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input className="input pl-9" value={search} onChange={event => setSearch(event.target.value)} placeholder="Nome, contato, e-mail..." /></span></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Categoria<select className="input mt-1" value={category} onChange={event => setCategory(event.target.value)}><option value="">Todas</option>{categories.map(item => <option key={item}>{item}</option>)}</select></label>
        <label className="text-xs text-slate-500 dark:text-slate-400">Status<select className="input mt-1" value={status} onChange={event => setStatus(event.target.value)}><option value="">Todos</option><option value="ativo">Ativos</option><option value="inativo">Inativos</option></select></label>
      </section>

      {filtered.length ? <section className="grid sm:grid-cols-2 2xl:grid-cols-3 gap-4">{filtered.map(supplier => <article key={supplier.id} className={`bg-white dark:bg-slate-900 border rounded-xl p-5 ${selectedId === supplier.id ? 'border-primary-500 ring-2 ring-primary-100 dark:ring-primary-900' : 'border-slate-200 dark:border-slate-700'}`}>
        <div className="flex justify-between gap-3"><div className="min-w-0"><h2 className="font-bold truncate">{supplier.nome}</h2><p className="text-sm text-slate-500 dark:text-slate-400 truncate">{supplier.categoria}</p></div><span className={`h-fit px-2 py-1 rounded-full text-xs font-semibold ${supplier.ativo_flg ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'}`}>{supplier.ativo_flg ? 'Ativo' : 'Inativo'}</span></div>
        <div className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">{supplier.contato && <p>{supplier.contato}</p>}{supplier.telefone && <a href={`tel:${supplier.telefone}`} className="flex items-center gap-2 hover:text-primary-600"><Phone className="h-4 w-4" />{supplier.telefone}</a>}{supplier.email && <a href={`mailto:${supplier.email}`} className="flex items-center gap-2 hover:text-primary-600 truncate"><Mail className="h-4 w-4 shrink-0" />{supplier.email}</a>}{supplier.avaliacao !== null && supplier.avaliacao !== undefined && <p className="flex items-center gap-2"><Star className="h-4 w-4 text-amber-500 fill-amber-500" />{Number(supplier.avaliacao).toLocaleString('pt-BR', { minimumFractionDigits: 1 })}</p>}</div>
        <div className="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-between gap-2"><button type="button" onClick={() => setSelectedId(supplier.id)} className="text-sm font-semibold text-primary-600 dark:text-primary-400">Ver detalhes</button><div className="flex gap-2"><button type="button" aria-label={`Editar ${supplier.nome}`} onClick={() => openEdit(supplier)} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"><Pencil className="h-4 w-4" /></button><button type="button" aria-label={`Excluir ${supplier.nome}`} disabled={saving} onClick={() => setDeleteTarget(supplier)} className="p-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950 disabled:opacity-50"><Trash2 className="h-4 w-4" /></button></div></div>
      </article>)}</section> : <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-12 text-center"><Users className="h-10 w-10 mx-auto text-slate-300" /><h2 className="font-bold mt-3">Nenhum fornecedor encontrado</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Cadastre um contato ou ajuste os filtros.</p><button type="button" onClick={openCreate} className="mt-4 text-primary-600 dark:text-primary-400 font-semibold">Cadastrar fornecedor</button></section>}
    </main>

    {selected && <aside aria-label="Detalhes do fornecedor" className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl self-start xl:sticky xl:top-4 overflow-hidden"><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between"><h2 className="font-bold">Detalhes</h2><button type="button" aria-label="Fechar detalhes" onClick={() => setSelectedId(null)}><X className="h-5 w-5 text-slate-400" /></button></div><div className="p-5 space-y-4"><Detail label="Nome" value={selected.nome} /><Detail label="Categoria" value={selected.categoria} /><Detail label="Contato" value={selected.contato || 'Não informado'} /><Detail label="Telefone" value={selected.telefone || 'Não informado'} /><Detail label="E-mail" value={selected.email || 'Não informado'} /><Detail label="Avaliação" value={selected.avaliacao === null || selected.avaliacao === undefined ? 'Não avaliado' : Number(selected.avaliacao).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} /><Detail label="Observações" value={selected.observacoes || 'Sem observações'} /></div><div className="p-5 border-t border-slate-200 dark:border-slate-800"><button type="button" onClick={() => openEdit(selected)} className="w-full border border-slate-300 dark:border-slate-700 rounded-lg px-4 py-2 font-semibold inline-flex items-center justify-center gap-2"><Pencil className="h-4 w-4" />Editar</button></div></aside>}

    {editingId !== undefined && <SupplierDialog form={form} setForm={setForm} editing={editingId !== null} saving={saving} error={error} onClose={() => { if (!saving) { setEditingId(undefined); setError(''); } }} onSave={save} />}
    {deleteTarget && <ConfirmActionDialog title="Excluir fornecedor?" description={`O fornecedor ${deleteTarget.nome} será removido da sua conta.`} actionLabel="Excluir fornecedor" pendingLabel="Excluindo..." pending={saving} onCancel={() => setDeleteTarget(null)} onConfirm={() => remove(deleteTarget)} />}
  </div>;
}

function SupplierDialog({ form, setForm, editing, saving, error, onClose, onSave }: { form: SupplierForm; setForm: (form: SupplierForm) => void; editing: boolean; saving: boolean; error: string; onClose: () => void; onSave: (event: FormEvent) => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const firstField = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.current?.showModal();
    firstField.current?.focus();
    return () => { dialog.current?.close(); trigger?.focus(); };
  }, []);
  return <dialog ref={dialog} aria-labelledby="supplier-dialog-title" onCancel={event => { event.preventDefault(); onClose(); }} className="m-auto w-[min(680px,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl p-0 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xl backdrop:bg-slate-950/60">
    <form onSubmit={onSave}><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between"><div><h2 id="supplier-dialog-title" className="font-bold text-lg">{editing ? 'Editar fornecedor' : 'Novo fornecedor'}</h2><p className="text-sm text-slate-500 dark:text-slate-400">Campos obrigatórios estão marcados com *.</p></div><button type="button" aria-label="Fechar formulário" onClick={onClose}><X className="h-5 w-5" /></button></div>
      <div className="p-5 grid sm:grid-cols-2 gap-4">
        {error && <div role="alert" className="sm:col-span-2 rounded-lg bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 p-3 text-sm">{error}</div>}
        <Field label="Nome *"><input ref={firstField} required maxLength={160} className="input mt-1" value={form.nome} onChange={event => setForm({ ...form, nome: event.target.value })} /></Field>
        <Field label="Categoria *"><input required maxLength={80} className="input mt-1" value={form.categoria} onChange={event => setForm({ ...form, categoria: event.target.value })} placeholder="Ex: Materiais" /></Field>
        <Field label="Contato"><input maxLength={160} className="input mt-1" value={form.contato} onChange={event => setForm({ ...form, contato: event.target.value })} /></Field>
        <Field label="Telefone"><input maxLength={30} className="input mt-1" type="tel" value={form.telefone} onChange={event => setForm({ ...form, telefone: event.target.value })} placeholder="(11) 99999-9999" /></Field>
        <Field label="E-mail"><input maxLength={254} className="input mt-1" type="email" value={form.email} onChange={event => setForm({ ...form, email: event.target.value })} /></Field>
        <Field label="Avaliação (0 a 5)"><input className="input mt-1" type="number" min="0" max="5" step="0.1" value={form.avaliacao} onChange={event => setForm({ ...form, avaliacao: event.target.value })} /></Field>
        <Field label="Observações" full><textarea maxLength={5000} className="input mt-1 min-h-24 resize-y" value={form.observacoes} onChange={event => setForm({ ...form, observacoes: event.target.value })} /></Field>
        <label className="sm:col-span-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={form.ativo_flg} onChange={event => setForm({ ...form, ativo_flg: event.target.checked })} />Fornecedor ativo</label>
      </div><div className="p-5 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3"><button type="button" onClick={onClose} disabled={saving} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 disabled:opacity-50">Cancelar</button><button type="submit" disabled={saving} className="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold disabled:opacity-50 inline-flex items-center gap-2">{saving && <Loader2 className="h-4 w-4 animate-spin" />}{editing ? 'Salvar alterações' : 'Cadastrar'}</button></div>
    </form>
  </dialog>;
}

function Metric({ icon: Icon, label, value }: { icon: typeof Users; label: string; value: string }) { return <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3"><span className="p-3 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400"><Icon className="h-5 w-5" /></span><div><span className="text-xs text-slate-500 dark:text-slate-400">{label}</span><strong className="block text-xl">{value}</strong></div></div>; }
function Detail({ label, value }: { label: string; value: string }) { return <div><dt className="text-xs text-slate-500 dark:text-slate-400">{label}</dt><dd className="font-medium mt-0.5 break-words">{value}</dd></div>; }
function Field({ label, children, full = false }: { label: string; children: React.ReactNode; full?: boolean }) { return <label className={`text-sm ${full ? 'sm:col-span-2' : ''}`}><span>{label}</span>{children}</label>; }
