import { useEffect, useMemo, useState } from 'react';
import { Building2, Check, Clock3, CreditCard, Hammer, Home } from 'lucide-react';
import api from '@/lib/api';
import { useSession } from '@/lib/session';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';

const icons = { corretor: Home, imobiliaria: Building2, house_flipper: Hammer } as const;

export default function Assinaturas() {
  const { refresh } = useSession();
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [cancelTarget, setCancelTarget] = useState<any>(null);
  const [saving, setSaving] = useState(false);

  async function load() {
    const { data: payload } = await api.get('/billing/subscription');
    setData(payload.data);
  }

  useEffect(() => { load().catch((exception:any) => setError(exception.response?.data?.message ?? 'Falha ao carregar assinaturas.')); }, []);

  async function cancel() {
    if (!cancelTarget) return;
    setSaving(true); setError('');
    try {
      await api.post('/billing/cancel', { product: cancelTarget.product_slug });
      await refresh(); await load();
      setNotice(`Assinatura ${cancelTarget.label} cancelada.`);
      setCancelTarget(null);
    } catch (exception:any) {
      setError(exception.response?.data?.message ?? 'Não foi possível cancelar a assinatura.');
    } finally { setSaving(false); }
  }

  const subscriptions = useMemo(() => Object.fromEntries((data?.subscriptions ?? []).map((row:any) => [row.product_slug, row])), [data]);
  if (error && !data) return <p role="alert" className="text-red-700">{error}</p>;
  if (!data) return <p role="status">Carregando assinaturas...</p>;
  const checkoutReady = data.checkout !== 'not_wired';

  return <div className="space-y-6">
    <header><p className="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Conta</p><h1 className="mt-1 text-3xl font-black">Assinaturas</h1><p className="mt-2 text-sm text-slate-500 dark:text-slate-400">Planos, períodos de teste, recursos e limites de cada sistema.</p></header>
    {error && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{error}</p>}
    {notice && <p role="status" className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{notice}</p>}
    {!checkoutReady && <section className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"><CreditCard className="mt-0.5 h-5 w-5 shrink-0" /><div><strong>Contratação assistida</strong><p className="mt-1 text-sm">Mercado Pago e Stripe ainda não estão configurados. Nenhuma cobrança será simulada; a contratação paga segue pelo contato comercial.</p></div></section>}
    <section className="grid gap-4 lg:grid-cols-3">{(data.catalog ?? []).map((product:any) => {
      const row = subscriptions[product.slug];
      const Icon = icons[product.slug as keyof typeof icons] ?? Home;
      const trialEnd = row?.trial_ends_at ? new Date(row.trial_ends_at) : null;
      const remaining = trialEnd ? Math.max(0, Math.ceil((trialEnd.getTime() - Date.now()) / 86400000)) : null;
      return <article key={product.slug} className="flex flex-col rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900"><div className="flex items-start justify-between gap-3"><span className="rounded-xl bg-primary-50 p-2.5 text-primary-700 dark:bg-primary-950 dark:text-primary-300"><Icon className="h-5 w-5" /></span><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${row?.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'}`}>{row?.active ? ['trial', 'trialing'].includes(row.status) ? 'Em teste' : 'Ativa' : row?.status === 'cancelled' ? 'Cancelada' : 'Não contratada'}</span></div><h2 className="mt-4 text-xl font-black">{product.label}</h2><p className="mt-1 min-h-10 text-sm text-slate-500 dark:text-slate-400">{product.description}</p>{remaining !== null && <div className="mt-4"><div className="flex justify-between text-xs"><span className="inline-flex items-center gap-1"><Clock3 className="h-3.5 w-3.5" />Teste</span><strong>{remaining} dia{remaining === 1 ? '' : 's'} restante{remaining === 1 ? '' : 's'}</strong></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span className="block h-full bg-sky-500" style={{ width: `${Math.min(100, Math.max(4, remaining / 7 * 100))}%` }} /></div></div>}<ul className="mt-4 flex-1 space-y-2">{(product.features ?? []).slice(0, 5).map((feature:string) => <li key={feature} className="flex gap-2 text-sm"><Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /><span>{String(feature).replace(/_/g, ' ')}</span></li>)}</ul><div className="mt-5 border-t pt-4">{row?.active ? <button type="button" onClick={() => setCancelTarget(row)} className="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 dark:border-red-900 dark:text-red-300">Gerenciar ou cancelar</button> : <a href="/contato" className="block w-full rounded-xl bg-primary-600 px-4 py-2.5 text-center text-sm font-bold text-white">Solicitar contratação</a>}</div></article>;
    })}</section>
    {cancelTarget && <ConfirmActionDialog title={`Cancelar ${cancelTarget.label}?`} description="O acesso ao produto será encerrado. Projetos e carteira permanecem armazenados conforme as regras da conta." actionLabel="Cancelar assinatura" pending={saving} onCancel={() => setCancelTarget(null)} onConfirm={cancel} />}
  </div>;
}
