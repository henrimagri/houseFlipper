import { useEffect, useState } from 'react';
import { CalendarDays, FolderKanban, House, MessageSquare, ShieldCheck, ArrowUpRight } from 'lucide-react';
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
    <div className="fc-dashboard space-y-6">
      <PageHeader
        title="Minha operação" icon={House}
        subtitle={`Números reais da sua conta. Período ${data.period.from} a ${data.period.to}.`}
        actions={(
          <div className="flex gap-2">
            <label className="text-xs">De<input className="input mt-1" type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
            <label className="text-xs">Até<input className="input mt-1" type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
          </div>
        )}
      />
      <div className="fc-dashboard-metrics">
        <KpiCard titulo="Imóveis na carteira" valor={String(k.wallet)} hint={data.definitions.wallet} Icon={House} iconBg="bg-primary-50" iconColor="text-primary-600" details={[{label:'Publicados',value:String(k.published)},{label:'Captações pendentes',value:String(k.pending_authorization)}]} />
        <KpiCard titulo="Leads recebidos" valor={String(k.new_leads)} hint={data.definitions.new_leads} Icon={MessageSquare} iconBg="bg-primary-50" iconColor="text-primary-600" details={[{label:'Visitas agendadas',value:String(k.visits_scheduled)}]} />
        <KpiCard titulo="Propostas abertas" valor={String(k.open_proposals)} Icon={FolderKanban} iconBg="bg-primary-50" iconColor="text-primary-600" rodape="Propostas em andamento na sua conta" />
      </div>
      <div className="grid lg:grid-cols-2 gap-6">
        <div className="min-w-0"><DashboardMap product="corretor" href="/app/corretor/mapa" /></div>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3"><FolderKanban />Funil comercial</h2>
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={funnel}><XAxis dataKey="stage" tick={{ fontSize: 11 }} /><YAxis allowDecimals={false} /><Tooltip /><Bar dataKey="count" fill="var(--fc-accent)" radius={6} /></BarChart>
            </ResponsiveContainer>
          </div>
        </section>
      </div>
      <div className="grid xl:grid-cols-3 gap-4">
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3"><House />Imóveis recentes</h2>
          {data.recent_listings?.length ? data.recent_listings.map((row: any) => (
            <Link key={row.id} to={`/app/corretor/carteira/${row.id}`} className="block py-2 border-t first:border-0 text-sm">
              {row.title} · {row.city || '—'} · {money(row.asking_price_cents)}
            </Link>
          )) : <EmptyState title="Nenhum imóvel" description="Cadastre o primeiro imóvel autorizado na carteira." />}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3"><ShieldCheck />Captações aguardando autorização</h2>
          {data.activity.pending_captures.length ? data.activity.pending_captures.map((row: any) => (
            <Link key={row.id} to="/app/corretor/captacao" className="block py-2 border-t first:border-0 text-sm">{row.status} · {row.created_at}</Link>
          )) : <p className="text-sm text-gray-500">Nenhuma captação pendente.</p>}
        </section>
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4">
          <h2 className="font-semibold mb-3"><CalendarDays />Próximos atendimentos</h2>
          {data.activity.upcoming_visits.length ? data.activity.upcoming_visits.map((row: any) => (
            <Link key={row.id} to="/app/corretor/agenda" className="block py-2 border-t first:border-0 text-sm">{row.titulo}</Link>
          )) : <p className="text-sm text-gray-500">Nenhuma visita futura no calendário.</p>}
          <Link to="/app/corretor/meu-site" className="mt-3 inline-block text-sm font-medium text-sky-700">Divulgar no site <ArrowUpRight className="inline size-4" /></Link>
        </section>
      </div>
    </div>
  );
}
