import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';

export default function CrmDealDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [deal, setDeal] = useState<any>(null);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ title: '', estimated_value_cents: '', priority: 'normal' });
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [saving, setSaving] = useState(false);

  async function load() {
    try {
      const { data } = await api.get(`/imobiliaria/deals/${id}`);
      setDeal(data.data);
      setForm({ title: data.data.title ?? '', estimated_value_cents: data.data.estimated_value_cents ? String(data.data.estimated_value_cents) : '', priority: data.data.priority ?? 'normal' });
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Negócio não encontrado.');
    }
  }
  useEffect(() => { void load(); }, [id]);

  async function project() {
    await api.post(`/imobiliaria/deals/${id}/commissions`);
    await load();
  }

  async function save() {
    setSaving(true); setError('');
    try {
      const { data } = await api.patch(`/imobiliaria/deals/${id}`, {
        title: form.title,
        estimated_value_cents: form.estimated_value_cents ? Number(form.estimated_value_cents) : null,
        priority: form.priority,
      });
      setDeal(data.data);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível salvar o negócio.');
    } finally { setSaving(false); }
  }

  async function archive() {
    setSaving(true); setError('');
    try {
      await api.delete(`/imobiliaria/deals/${id}`);
      navigate('/app/imobiliaria/crm');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível arquivar o negócio.');
      setConfirmArchive(false);
      setSaving(false);
    }
  }

  if (!deal && !error) return <p>Carregando negócio...</p>;
  return (
    <div className="space-y-5">
      <Link to="/app/imobiliaria/crm" className="text-sm text-primary-600">← Funil</Link>
      {error && <ErrorState message={error} />}
      {deal && (
        <>
          <PageHeader title={deal.title} subtitle={`${deal.stage} · versão ${deal.lock_version}`} />
          <div className="grid lg:grid-cols-3 gap-4">
            <section className="lg:col-span-2 bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-2">
              <label className="block text-xs font-medium">Título<input className="input mt-1" value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} /></label>
              <div className="grid sm:grid-cols-2 gap-3"><label className="block text-xs font-medium">Valor estimado em centavos<input className="input mt-1" inputMode="numeric" value={form.estimated_value_cents} onChange={(event) => setForm({ ...form, estimated_value_cents: event.target.value.replace(/\D/g, '') })} /></label><label className="block text-xs font-medium">Prioridade<select className="input mt-1" value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })}><option value="baixa">Baixa</option><option value="normal">Normal</option><option value="alta">Alta</option></select></label></div>
              <div className="flex flex-wrap gap-2"><button type="button" disabled={saving || !form.title.trim()} onClick={() => void save()} className="bg-primary-600 text-white px-3 py-2 rounded-lg text-sm disabled:opacity-50">{saving ? 'Salvando...' : 'Salvar alterações'}</button><button type="button" disabled={saving} onClick={() => setConfirmArchive(true)} className="border border-red-200 text-red-700 px-3 py-2 rounded-lg text-sm">Arquivar negócio</button></div>
              <p>Cliente: {deal.contact?.name || '—'}</p>
              <p>Imóvel: {deal.listing?.title || '—'}</p>
              <p>Responsável: {deal.assigned_user?.name || '—'}</p>
              <p>Valor: {deal.estimated_value_cents ? (deal.estimated_value_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'}</p>
              <button type="button" onClick={project} className="border px-3 py-2 rounded-lg text-sm">Projetar comissão</button>
              <h2 className="font-semibold pt-3">Histórico</h2>
              {(deal.activities ?? []).map((row: any) => (
                <p key={row.id} className="text-sm border-t py-2">{row.body || row.kind} · {row.user?.name} · {row.from_stage} → {row.to_stage}</p>
              ))}
            </section>
            <section className="bg-white dark:bg-slate-900 border rounded-xl p-4 space-y-2">
              <h2 className="font-semibold">Propostas</h2>
              {(deal.proposals ?? []).map((row: any) => <p key={row.id} className="text-sm">{row.title} · {row.status}</p>)}
              {!deal.proposals?.length && <p className="text-sm text-gray-500">Nenhuma proposta vinculada.</p>}
            </section>
          </div>
        </>
      )}
      {confirmArchive && <ConfirmActionDialog title="Arquivar este negócio?" description="O card sairá do funil e a ação ficará registrada no histórico." actionLabel="Arquivar" pendingLabel="Arquivando..." pending={saving} onCancel={() => setConfirmArchive(false)} onConfirm={archive} />}
    </div>
  );
}
