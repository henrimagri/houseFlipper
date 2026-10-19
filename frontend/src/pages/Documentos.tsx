import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { CalendarDays, Download, File, FileImage, FileSpreadsheet, FileText, Files, FolderOpen, Loader2, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import api from '@/lib/api';
import { PaginatedCollection } from '@/lib/paginatedCollection';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';

type Project = { id: number; nome: string };
type Document = {
  id: number; projeto_id: number | null; projeto?: Project | null; nome: string; mime_type: string;
  tamanho_bytes: number; categoria: string; status: 'ativo' | 'em_revisao' | 'aprovado' | 'arquivado';
  descricao?: string | null; data_vencimento?: string | null; enviado_por?: string | null; data_inclusao?: string | null;
};
type DocumentForm = { arquivo: File | null; projeto_id: string; categoria: string; status: Document['status']; descricao: string; data_vencimento: string };
const emptyForm: DocumentForm = { arquivo: null, projeto_id: '', categoria: '', status: 'ativo', descricao: '', data_vencimento: '' };
const statusLabels: Record<Document['status'], string> = { ativo: 'Ativo', em_revisao: 'Em revisão', aprovado: 'Aprovado', arquivado: 'Arquivado' };

export default function Documentos() {
  const documentLoader = useRef(new PaginatedCollection<Document>());
  const projectLoader = useRef(new PaginatedCollection<Project>());
  const [documents, setDocuments] = useState<Document[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('');
  const [projectId, setProjectId] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null | undefined>(undefined);
  const [deleteTarget, setDeleteTarget] = useState<Document | null>(null);
  const [form, setForm] = useState<DocumentForm>(emptyForm);

  async function load(showSpinner = true) {
    if (showSpinner) setLoading(true);
    setError('');
    try {
      const [loadedDocuments, loadedProjects] = await Promise.all([
        documentLoader.current.load(async (page, perPage, signal) => {
          const response = await api.get('/documentos', { params: { page, per_page: perPage }, signal }); return response.data;
        }),
        projectLoader.current.load(async (page, perPage, signal) => {
          const response = await api.get('/projetos', { params: { page, per_page: perPage }, signal }); return response.data;
        }),
      ]);
      if (!loadedDocuments || !loadedProjects) return false;
      setDocuments(loadedDocuments);
      setSelectedId(current => current && loadedDocuments.some(item => item.id === current) ? current : null);
      setProjects(loadedProjects);
      return true;
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? exception.message ?? 'Não foi possível carregar os documentos.'); return false;
    } finally { setLoading(false); }
  }

  useEffect(() => { load(); return () => { documentLoader.current.cancel(); projectLoader.current.cancel(); }; }, []);

  const categories = useMemo(() => [...new Set(documents.map(item => item.categoria))].sort((a, b) => a.localeCompare(b, 'pt-BR')), [documents]);
  const filtered = useMemo(() => {
    const query = search.trim().toLocaleLowerCase('pt-BR');
    return documents.filter(item => {
      if (category && item.categoria !== category) return false;
      if (status && item.status !== status) return false;
      if (projectId && String(item.projeto_id ?? '') !== projectId) return false;
      if (!query) return true;
      return [item.nome, item.categoria, item.descricao, item.projeto?.nome].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(query));
    });
  }, [documents, search, category, status, projectId]);
  const selected = documents.find(item => item.id === selectedId) ?? null;
  const totalBytes = documents.reduce((sum, item) => sum + Number(item.tamanho_bytes || 0), 0);
  const reviewCount = documents.filter(item => item.status === 'em_revisao').length;
  const today = new Date();
  const inThirtyDays = new Date(today); inThirtyDays.setDate(today.getDate() + 30);
  const expiringCount = documents.filter(item => {
    if (!item.data_vencimento) return false;
    const date = new Date(`${item.data_vencimento}T12:00:00`); return date >= today && date <= inThirtyDays;
  }).length;

  function openCreate() { setForm(emptyForm); setEditingId(null); setError(''); setNotice(''); }
  function openEdit(document: Document) {
    setForm({ arquivo: null, projeto_id: document.projeto_id ? String(document.projeto_id) : '', categoria: document.categoria, status: document.status, descricao: document.descricao ?? '', data_vencimento: document.data_vencimento ?? '' });
    setEditingId(document.id); setError(''); setNotice('');
  }
  async function save(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError(''); setNotice('');
    const fields = { projeto_id: form.projeto_id ? Number(form.projeto_id) : null, categoria: form.categoria.trim(), status: form.status, descricao: form.descricao.trim() || null, data_vencimento: form.data_vencimento || null };
    try {
      if (editingId) await api.put(`/documentos/${editingId}`, fields);
      else {
        const payload = new FormData(); if (form.arquivo) payload.append('arquivo', form.arquivo);
        Object.entries(fields).forEach(([key, value]) => { if (value !== null) payload.append(key, String(value)); });
        await api.post('/documentos', payload);
      }
      const wasEditing = Boolean(editingId); setEditingId(undefined);
      const success = wasEditing ? 'Documento atualizado.' : 'Documento enviado com segurança.';
      const refreshed = await load(false);
      setNotice(refreshed ? success : `${success} A lista não pôde ser recarregada; use Atualizar antes de repetir a operação.`);
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar o documento.');
    } finally { setSaving(false); }
  }
  async function download(document: Document) {
    setDownloadingId(document.id); setError('');
    try {
      const response = await api.get(`/documentos/${document.id}/download`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data); const link = window.document.createElement('a');
      link.href = url; link.download = document.nome; link.click(); URL.revokeObjectURL(url);
    } catch (exception: any) {
      setError(exception.response?.status === 404 ? 'O arquivo não foi encontrado.' : 'Não foi possível baixar o documento.');
    } finally { setDownloadingId(null); }
  }
  async function remove(document: Document) {
    setSaving(true); setError(''); setNotice('');
    try { await api.delete(`/documentos/${document.id}`); setDeleteTarget(null); setSelectedId(null); const refreshed = await load(false); setNotice(refreshed ? 'Documento excluído.' : 'Documento excluído, mas a lista não pôde ser recarregada. Use Atualizar.'); }
    catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível excluir o documento.'); setDeleteTarget(null); }
    finally { setSaving(false); }
  }

  if (loading) return <Loading />;
  return <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6">
    <main className="space-y-6 min-w-0">
      <header className="flex items-start justify-between gap-4 flex-wrap"><div><h1 className="text-2xl font-semibold">Documentos</h1><p className="text-sm text-slate-500 dark:text-slate-400">Arquivos privados vinculados à sua conta e aos seus projetos.</p></div><button type="button" onClick={openCreate} className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center gap-2"><Plus className="h-4 w-4" />Novo documento</button></header>
      {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300 flex justify-between gap-3"><span>{error}</span>{editingId === undefined && <button type="button" onClick={() => load()} className="font-bold underline">Tentar novamente</button>}</div>}
      {notice && <div role="status" className="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950 p-4 text-sm text-emerald-700 dark:text-emerald-300">{notice}</div>}
      <section aria-label="Resumo dos documentos" className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3"><Metric icon={Files} label="Documentos" value={String(documents.length)} /><Metric icon={FolderOpen} label="Espaço utilizado" value={formatBytes(totalBytes)} /><Metric icon={FileText} label="Em revisão" value={String(reviewCount)} /><Metric icon={CalendarDays} label="Vencem em 30 dias" value={String(expiringCount)} /></section>
      <section aria-label="Filtros de documentos" className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_180px_180px] gap-3">
        <FilterField label="Buscar"><span className="relative block"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" /><input className="input pl-9" value={search} onChange={event => setSearch(event.target.value)} placeholder="Nome ou descrição..." /></span></FilterField>
        <FilterField label="Projeto"><select className="input" value={projectId} onChange={event => setProjectId(event.target.value)}><option value="">Todos</option>{projects.map(item => <option value={item.id} key={item.id}>{item.nome}</option>)}</select></FilterField>
        <FilterField label="Categoria"><select className="input" value={category} onChange={event => setCategory(event.target.value)}><option value="">Todas</option>{categories.map(item => <option key={item}>{item}</option>)}</select></FilterField>
        <FilterField label="Status"><select className="input" value={status} onChange={event => setStatus(event.target.value)}><option value="">Todos</option>{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></FilterField>
      </section>
      {filtered.length ? <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden"><div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950"><th className="px-5 py-3">Documento</th><th className="px-3 py-3">Categoria</th><th className="px-3 py-3">Projeto</th><th className="px-3 py-3">Enviado em</th><th className="px-3 py-3">Status</th><th className="px-3 py-3 text-right">Ações</th></tr></thead><tbody className="divide-y divide-slate-100 dark:divide-slate-800">{filtered.map(document => <tr key={document.id} onClick={() => setSelectedId(document.id)} className={`cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 ${selectedId === document.id ? 'bg-primary-50/50 dark:bg-primary-950/30' : ''}`}>
        <td className="px-5 py-3"><div className="flex items-center gap-3"><FileIcon mime={document.mime_type} /><div className="min-w-0"><strong className="block truncate max-w-72">{document.nome}</strong><span className="text-xs text-slate-500 dark:text-slate-400">{formatBytes(document.tamanho_bytes)}</span></div></div></td><td className="px-3 py-3">{document.categoria}</td><td className="px-3 py-3">{document.projeto?.nome ?? 'Sem projeto'}</td><td className="px-3 py-3 whitespace-nowrap">{formatDate(document.data_inclusao, true)}</td><td className="px-3 py-3"><StatusBadge status={document.status} /></td>
        <td className="px-3 py-3"><div className="flex justify-end gap-1"><button type="button" aria-label={`Baixar ${document.nome}`} disabled={downloadingId === document.id} onClick={event => { event.stopPropagation(); download(document); }} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-50">{downloadingId === document.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}</button><button type="button" aria-label={`Editar ${document.nome}`} onClick={event => { event.stopPropagation(); openEdit(document); }} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"><Pencil className="h-4 w-4" /></button><button type="button" aria-label={`Excluir ${document.nome}`} disabled={saving} onClick={event => { event.stopPropagation(); setDeleteTarget(document); }} className="p-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950 disabled:opacity-50"><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody></table></div><div className="px-5 py-3 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">{filtered.length} de {documents.length} documento(s)</div></section> : <Empty onCreate={openCreate} hasFilters={Boolean(search || category || status || projectId)} />}
    </main>
    {selected && <aside aria-label="Detalhes do documento" className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl self-start xl:sticky xl:top-4 overflow-hidden"><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between gap-3"><div className="min-w-0"><h2 className="font-bold truncate">{selected.nome}</h2><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{formatBytes(selected.tamanho_bytes)}</p></div><button type="button" aria-label="Fechar detalhes" onClick={() => setSelectedId(null)}><X className="h-5 w-5 text-slate-400" /></button></div><dl className="p-5 space-y-4"><Detail label="Categoria" value={selected.categoria} /><Detail label="Status" value={statusLabels[selected.status]} /><Detail label="Projeto" value={selected.projeto?.nome ?? 'Sem projeto'} /><Detail label="Descrição" value={selected.descricao || 'Sem descrição'} /><Detail label="Vencimento" value={formatDate(selected.data_vencimento)} /><Detail label="Enviado por" value={selected.enviado_por || 'Usuário removido'} /><Detail label="Data de envio" value={formatDate(selected.data_inclusao, true)} /><Detail label="Tipo" value={selected.mime_type} /></dl><div className="p-5 border-t border-slate-200 dark:border-slate-800 grid grid-cols-2 gap-2"><button type="button" disabled={downloadingId === selected.id} onClick={() => download(selected)} className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 font-semibold inline-flex items-center justify-center gap-2 disabled:opacity-50"><Download className="h-4 w-4" />Baixar</button><button type="button" onClick={() => openEdit(selected)} className="border border-slate-300 dark:border-slate-700 rounded-lg px-3 py-2 font-semibold inline-flex items-center justify-center gap-2"><Pencil className="h-4 w-4" />Editar</button></div></aside>}
    {editingId !== undefined && <DocumentDialog form={form} setForm={setForm} projects={projects} editing={editingId !== null} saving={saving} error={error} onClose={() => { if (!saving) { setEditingId(undefined); setError(''); } }} onSave={save} />}
    {deleteTarget && <ConfirmActionDialog title="Excluir documento?" description={`O documento ${deleteTarget.nome} e seu arquivo serão removidos.`} actionLabel="Excluir documento" pendingLabel="Excluindo..." pending={saving} onCancel={() => setDeleteTarget(null)} onConfirm={() => remove(deleteTarget)} />}
  </div>;
}

function DocumentDialog({ form, setForm, projects, editing, saving, error, onClose, onSave }: { form: DocumentForm; setForm: (form: DocumentForm) => void; projects: Project[]; editing: boolean; saving: boolean; error: string; onClose: () => void; onSave: (event: FormEvent) => void }) {
  const dialog = useRef<HTMLDialogElement>(null); const firstField = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    dialog.current?.showModal();
    firstField.current?.focus();
    return () => { dialog.current?.close(); trigger?.focus(); };
  }, []);
  return <dialog ref={dialog} aria-labelledby="document-dialog-title" onCancel={event => { event.preventDefault(); onClose(); }} className="m-auto w-[min(680px,calc(100%-2rem))] max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl p-0 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xl backdrop:bg-slate-950/60"><form onSubmit={onSave}><div className="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between"><div><h2 id="document-dialog-title" className="font-bold text-lg">{editing ? 'Editar documento' : 'Novo documento'}</h2><p className="text-sm text-slate-500 dark:text-slate-400">PDF, imagens, CSV, XLSX e DOCX, até 10 MB.</p></div><button type="button" aria-label="Fechar formulário" onClick={onClose}><X className="h-5 w-5" /></button></div><div className="p-5 grid sm:grid-cols-2 gap-4">
    {error && <div role="alert" className="sm:col-span-2 rounded-lg bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 p-3 text-sm">{error}</div>}
    {!editing && <Field label="Arquivo *" full><input ref={firstField} required type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv,.xlsx,.docx" className="input mt-1 file:mr-3 file:border-0 file:bg-transparent" onChange={event => setForm({ ...form, arquivo: event.target.files?.[0] ?? null })} /></Field>}
    <Field label="Categoria *"><input ref={editing ? firstField : undefined} required maxLength={80} className="input mt-1" value={form.categoria} onChange={event => setForm({ ...form, categoria: event.target.value })} placeholder="Ex: Contratos" /></Field><Field label="Projeto"><select className="input mt-1" value={form.projeto_id} onChange={event => setForm({ ...form, projeto_id: event.target.value })}><option value="">Sem projeto</option>{projects.map(item => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></Field><Field label="Status"><select className="input mt-1" value={form.status} onChange={event => setForm({ ...form, status: event.target.value as Document['status'] })}>{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field><Field label="Data de vencimento"><input type="date" className="input mt-1" value={form.data_vencimento} onChange={event => setForm({ ...form, data_vencimento: event.target.value })} /></Field><Field label="Descrição" full><textarea maxLength={5000} className="input mt-1 min-h-24 resize-y" value={form.descricao} onChange={event => setForm({ ...form, descricao: event.target.value })} /></Field>
    </div><div className="p-5 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3"><button type="button" onClick={onClose} disabled={saving} className="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 disabled:opacity-50">Cancelar</button><button type="submit" disabled={saving} className="px-4 py-2 rounded-lg bg-primary-600 text-white font-semibold disabled:opacity-50 inline-flex items-center gap-2">{saving && <Loader2 className="h-4 w-4 animate-spin" />}{editing ? 'Salvar alterações' : 'Enviar documento'}</button></div></form></dialog>;
}

function Loading() { return <div className="min-h-[50vh] grid place-items-center text-slate-500 dark:text-slate-400"><div className="text-center"><Loader2 className="h-8 w-8 animate-spin mx-auto text-primary-600" /><p className="mt-3">Carregando documentos...</p></div></div>; }
function Metric({ icon: Icon, label, value }: { icon: typeof Files; label: string; value: string }) { return <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-4 flex items-center gap-3"><span className="p-3 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400"><Icon className="h-5 w-5" /></span><div><span className="text-xs text-slate-500 dark:text-slate-400">{label}</span><strong className="block text-xl">{value}</strong></div></div>; }
function FilterField({ label, children }: { label: string; children: React.ReactNode }) { return <label className="text-xs text-slate-500 dark:text-slate-400"><span className="block mb-1">{label}</span>{children}</label>; }
function Field({ label, children, full = false }: { label: string; children: React.ReactNode; full?: boolean }) { return <label className={`text-sm ${full ? 'sm:col-span-2' : ''}`}><span>{label}</span>{children}</label>; }
function Detail({ label, value }: { label: string; value: string }) { return <div><dt className="text-xs text-slate-500 dark:text-slate-400">{label}</dt><dd className="font-medium mt-0.5 break-words">{value}</dd></div>; }
function Empty({ onCreate, hasFilters }: { onCreate: () => void; hasFilters: boolean }) { return <section className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-12 text-center"><Files className="h-10 w-10 mx-auto text-slate-300" /><h2 className="font-bold mt-3">{hasFilters ? 'Nenhum documento encontrado' : 'Nenhum documento cadastrado'}</h2><p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{hasFilters ? 'Ajuste os filtros para ampliar a busca.' : 'Envie o primeiro arquivo para começar.'}</p>{!hasFilters && <button type="button" onClick={onCreate} className="mt-4 text-primary-600 dark:text-primary-400 font-semibold">Enviar documento</button>}</section>; }
function FileIcon({ mime }: { mime: string }) { const Icon = mime.startsWith('image/') ? FileImage : mime.includes('spreadsheet') || mime.includes('csv') ? FileSpreadsheet : mime.includes('pdf') || mime.includes('word') ? FileText : File; return <span className="h-10 w-10 rounded-lg bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0"><Icon className="h-5 w-5" /></span>; }
function StatusBadge({ status }: { status: Document['status'] }) { const color = status === 'aprovado' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' : status === 'em_revisao' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : status === 'arquivado' ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' : 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300'; return <span className={`px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${color}`}>{statusLabels[status]}</span>; }
function formatBytes(value: number) { if (!value) return '0 B'; const units = ['B', 'KB', 'MB', 'GB']; const index = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1); return `${(value / 1024 ** index).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} ${units[index]}`; }
function formatDate(value?: string | null, time = false) { if (!value) return 'Não informado'; const date = new Date(value.length === 10 ? `${value}T12:00:00` : value); if (Number.isNaN(date.getTime())) return 'Data inválida'; return new Intl.DateTimeFormat('pt-BR', time ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' }).format(date); }
