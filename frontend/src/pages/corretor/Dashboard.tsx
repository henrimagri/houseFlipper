import { useEffect, useState } from 'react';
import { CalendarDays, FolderKanban, Globe, Heart, MessageSquare } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import api from '@/lib/api';
import KpiCard from '@/components/dashboard/KpiCard';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';
import LoadingSkeleton from '@/ui/LoadingSkeleton';
import EmptyState from '@/ui/EmptyState';
import DashboardMap from '@/maps/DashboardMap';

const money = (cents?: number | null) => cents == null ? '—' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function CorretorDashboard() {
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState('');
  const [from, setFrom] = useState(() => new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));

  function load() {
    setError('');
    api.get('/corretor/dashboard', { params: { from, to } })
      .then(({ data: payload }) => setData(payload))
      .catch((exception: any) => setError(exception.response?.data?.message ?? 'Painel indisponível.'));
  }
  useEffect(() => { load(); }, [from, to]);

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (!data) return <LoadingSkeleton label="Carregando dashboard do corretor" />;
  const k = data.kpis;
  const funnel = [
    { stage: 'Carteira', count: k.wallet },
    { stage: 'Publicados', count: k.published },
    { stage: 'Leads', count: k.new_leads },
    { stage: 'Visitas', count: k.visits_scheduled },
    { stage: 'Propostas', count: k.open_proposals },
  ];
  return (
    <div className="space-y-5">
      <PageHeader
        title="Minha operação"
        subtitle={`Números reais da sua conta. Período ${data.period.from} a ${data.period.to}.`}
        actions={(
          <div className="flex gap-2">
            <label className="text-xs">De<input className="input mt-1" type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
            <label className="text-xs">Até<input className="input mt-1" type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
          </div>
        )}
      />
      <div className="grid md:grid-cols-2 xl:grid-cols-6 gap-4">
        <KpiCard titulo="Imóveis na carteira" valor={String(k.wallet)} hint={data.definitions.wallet} Icon={FolderKanban} iconBg="bg-sky-100" iconColor="text-sky-700" />
        <KpiCard titulo="Publicados" valor={String(k.published)} hint={data.definitions.published} Icon={Globe} iconBg="bg-emerald-100" iconColor="text-emerald-700" />
        <KpiCard titulo="Captações pendentes" valor={String(k.pending_authorization)} hint={data.definitions.pending_authorization} Icon={Heart} iconBg="bg-amber-100" iconColor="text-amber-700" />
        <KpiCard titulo="Leads recebidos" valor={String(k.new_leads)} hint={data.definitions.new_leads} Icon={MessageSquare} iconBg="bg-sky-100" iconColor="text-sky-700" />
        <KpiCard titulo="Visitas agendadas" valor={String(k.visits_scheduled)} hint={data.definitions.visits} Icon={CalendarDays} iconBg="bg-emerald-100" iconColor="text-emerald-700" />
        <KpiCard titulo="Propostas abertas" valor={String(k.open_proposals)} Icon={FolderKanban} iconBg="bg-violet-100" iconColor="text-violet-700" />
      </div>
      <div className="grid xl:grid-cols-3 gap-4">
        <div className="xl:col-span-2"><DashboardMap product="corretor" href="/app/corretor/mapa" /></div>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Funil comercial</h2>
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={funnel}><XAxis dataKey="stage" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="#0ea5e9" radius={6} /></BarChart>
            </ResponsiveContainer>
          </div>
        </section>
      </div>
      <div className="grid xl:grid-cols-3 gap-4">
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Imóveis recentes</h2>
          {data.recent_listings?.length ? data.recent_listings.map((row: any) => (
            <Link key={row.id} to={`/app/corretor/carteira/${row.id}`} className="block py-2 border-t first:border-0 text-sm">
              {row.title} · {row.city || '—'} · {money(row.asking_price_cents)}
            </Link>
          )) : <EmptyState title="Nenhum imóvel" description="Cadastre o primeiro imóvel autorizado na carteira." />}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Captações aguardando autorização</h2>
          {data.activity.pending_captures.length ? data.activity.pending_captures.map((row: any) => (
            <Link key={row.id} to="/app/corretor/captacao" className="block py-2 border-t first:border-0 text-sm">{row.status} · {row.created_at}</Link>
          )) : <p className="text-sm text-gray-500">Nenhuma captação pendente.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3">Próximos atendimentos</h2>
          {data.activity.upcoming_visits.length ? data.activity.upcoming_visits.map((row: any) => (
            <Link key={row.id} to="/app/corretor/agenda" className="block py-2 border-t first:border-0 text-sm">{row.titulo}</Link>
          )) : <p className="text-sm text-gray-500">Nenhuma visita futura no calendário.</p>}
          <Link to="/app/corretor/meu-site" className="mt-3 inline-block text-sm font-medium text-sky-700">Divulgar no site</Link>
        </section>
      </div>
    </div>
  );
}
