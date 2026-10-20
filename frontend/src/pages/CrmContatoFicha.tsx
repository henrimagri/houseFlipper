import { FormEvent, useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import api from '@/lib/api';
import { contactsPath } from '@/lib/products';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';

export default function CrmContatoFicha() {
  const { id } = useParams();
  const location = useLocation();
  const [item, setItem] = useState<any>(null);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', phone: '', notes: '', status: 'new', next_action_at: '' });

  async function load() {
    try {
      const { data } = await api.get(`/crm/contacts/${id}`);
      const contact = data.data;
      setItem(contact);
      setForm({
        name: contact.name ?? '',
        email: contact.email ?? '',
        phone: contact.phone ?? '',
        notes: contact.notes ?? '',
        status: contact.status ?? 'new',
        next_action_at: contact.next_action_at ? contact.next_action_at.slice(0, 16) : '',
      });
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Contato não encontrado.');
    }
  }
  useEffect(() => { void load(); }, [id]);

  async function save(event: FormEvent) {
    event.preventDefault();
    setSaving(true); setError('');
    try {
      await api.patch(`/crm/contacts/${id}`, { ...form, next_action_at: form.next_action_at || null });
      await load();
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Falha ao salvar.');
    } finally { setSaving(false); }
  }

  if (!item && !error) return <p>Carregando ficha...</p>;
  return (
    <div className="space-y-5">
      <Link to={contactsPath(location.pathname)} className="text-sm text-primary-600">← Contatos</Link>
      <PageHeader title={item?.name || 'Contato'} subtitle={`Origem ${item?.source || 'manual'}`} />
      {error && <ErrorState message={error} />}
      {item && (
        <form onSubmit={save} className="bg-white dark:bg-slate-900 border rounded-xl p-4 grid md:grid-cols-2 gap-3">
          <label className="text-xs">Nome<input className="input mt-1" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
          <label className="text-xs">E-mail<input className="input mt-1" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
          <label className="text-xs">Telefone<input className="input mt-1" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></label>
          <label className="text-xs">Status
            <select className="input mt-1" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
              {['new', 'contacted', 'qualified', 'visit', 'proposal', 'won', 'lost'].map((status) => <option key={status} value={status}>{status}</option>)}
            </select>
          </label>
          <label className="text-xs">Próxima ação<input type="datetime-local" className="input mt-1" value={form.next_action_at} onChange={(e) => setForm({ ...form, next_action_at: e.target.value })} /></label>
          <label className="text-xs md:col-span-2">Notas<textarea className="input mt-1 min-h-24" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></label>
          <p className="text-sm text-gray-500 md:col-span-2">Imóvel de interesse: {item.listing?.title || '—'}</p>
          <button disabled={saving} className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">Salvar ficha</button>
        </form>
      )}
    </div>
  );
}
