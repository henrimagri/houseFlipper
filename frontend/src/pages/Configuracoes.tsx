import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Camera, Database, Globe2, Landmark, Loader2, LockKeyhole, LogOut, MapPinned, MonitorSmartphone, RefreshCw, Settings2, TrendingUp, UserRound } from 'lucide-react';
import { Link } from 'react-router-dom';
import ThemeSelector from '@/components/ThemeSelector';
import api from '@/lib/api';
import { clearAuthToken } from '@/lib/authToken';
import ConfirmActionDialog from '@/components/ConfirmActionDialog';
import { useSession } from '@/lib/session';
import { digits, formatPhone } from '@/lib/brFields';

type SourceStatus = 'connected' | 'configured' | 'available' | 'no_data' | 'pending' | 'not_implemented' | 'unavailable';
type SourceItem = { id: string; group: string; name: string; description: string; status: SourceStatus; details?: { record_count?: number; last_import_at?: string | null } };
type SessionItem = { id: number; name: string; current: boolean; last_used_at: string | null; created_at: string | null; expires_at: string | null };

const groupIcons = { Mercado: Globe2, Geolocalização: MapPinned, 'Transações reais': Landmark, Leilões: Database, 'Dados econômicos': TrendingUp } as const;
const statusView: Record<SourceStatus, { label: string; color: string }> = {
  connected: { label: 'Conectado', color: 'bg-emerald-500' },
  configured: { label: 'Configurado', color: 'bg-cyan-500' },
  available: { label: 'Disponível', color: 'bg-blue-500' },
  no_data: { label: 'Sem dados', color: 'bg-slate-400' },
  pending: { label: 'Não configurado', color: 'bg-amber-500' },
  not_implemented: { label: 'Não implementado', color: 'bg-slate-500' },
  unavailable: { label: 'Indisponível', color: 'bg-red-500' },
};

export default function Configuracoes() {
  const session = useSession();
  const [sources, setSources] = useState<SourceItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [sessions, setSessions] = useState<SessionItem[]>([]);
  const [sessionsLoading, setSessionsLoading] = useState(true);
  const [sessionError, setSessionError] = useState('');
  const [sessionAction, setSessionAction] = useState<{ kind: 'one' | 'all' | 'others'; session?: SessionItem } | null>(null);
  const [sessionSaving, setSessionSaving] = useState(false);
  const [visibleSessions, setVisibleSessions] = useState(5);
  const [profile, setProfile] = useState({ name: '', email: '', phone: '', avatar_url: '', email_notifications: true, product_updates: true });
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarPreview, setAvatarPreview] = useState('');
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileNotice, setProfileNotice] = useState('');
  const [profileError, setProfileError] = useState('');
  const [password, setPassword] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [passwordNotice, setPasswordNotice] = useState('');
  const [passwordError, setPasswordError] = useState('');

  async function load(silent = false) {
    if (silent) setRefreshing(true); else setLoading(true);
    setError('');
    try {
      const response = await api.get<{ data: SourceItem[] }>('/data-sources/status');
      setSources(response.data.data);
    } catch (exception: any) {
      setSources([]);
      setError(exception.response?.data?.message || 'Não foi possível consultar o estado das fontes.');
    } finally {
      setLoading(false); setRefreshing(false);
    }
  }

  async function loadSessions() {
    setSessionsLoading(true); setSessionError('');
    try {
      const response = await api.get<{ data: SessionItem[] }>('/auth/sessions');
      setSessions(response.data.data);
    } catch (exception: any) {
      setSessionError(exception.response?.data?.message || 'Não foi possível consultar as sessões.');
    } finally { setSessionsLoading(false); }
  }

  async function loadProfile() {
    try {
      const { data } = await api.get('/auth/me');
      const preferences = data.registration_profile?.preferences ?? {};
      setProfile({
        name: data.name ?? '', email: data.email ?? '', phone: formatPhone(data.registration_profile?.phone ?? ''), avatar_url: data.avatar_url ?? '',
        email_notifications: preferences.email_notifications ?? true,
        product_updates: preferences.product_updates ?? true,
      });
    } catch (exception: any) { setProfileError(exception.response?.data?.message ?? 'Não foi possível carregar o perfil.'); }
  }

  async function saveProfile(event: React.FormEvent) {
    event.preventDefault(); setProfileSaving(true); setProfileError(''); setProfileNotice('');
    try {
      await api.patch('/auth/profile', { name: profile.name.trim(), phone: digits(profile.phone) || null, preferences: { email_notifications: profile.email_notifications, product_updates: profile.product_updates } });
      if (avatarFile) { const body = new FormData(); body.append('avatar', avatarFile); const { data } = await api.post('/auth/avatar', body); setProfile((current) => ({ ...current, avatar_url: data.avatar_url ?? current.avatar_url })); if (avatarPreview) URL.revokeObjectURL(avatarPreview); setAvatarPreview(''); setAvatarFile(null); }
      setProfileNotice('Perfil e preferências atualizados.'); await session.refresh();
    } catch (exception: any) { setProfileError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível atualizar o perfil.'); }
    finally { setProfileSaving(false); }
  }

  async function savePassword(event: React.FormEvent) {
    event.preventDefault(); setPasswordSaving(true); setPasswordError(''); setPasswordNotice('');
    try {
      await api.put('/auth/password', password);
      setPassword({ current_password: '', password: '', password_confirmation: '' }); setPasswordNotice('Senha alterada com sucesso.');
    } catch (exception: any) { setPasswordError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível alterar a senha.'); }
    finally { setPasswordSaving(false); }
  }

  async function revokeSessions() {
    if (!sessionAction) return;
    setSessionSaving(true); setSessionError('');
    try {
      if (sessionAction.kind === 'all') await api.post('/auth/logout-all');
      else if (sessionAction.kind === 'others') await api.post('/auth/logout-others');
      else await api.delete(`/auth/sessions/${sessionAction.session?.id}`);
      const endedCurrent = sessionAction.kind === 'all' || Boolean(sessionAction.session?.current);
      setSessionAction(null);
      if (endedCurrent) {
        clearAuthToken();
        window.location.assign('/login');
        return;
      }
      await loadSessions();
    } catch (exception: any) {
      setSessionError(exception.response?.data?.message || 'Não foi possível encerrar a sessão.');
      setSessionAction(null);
    } finally { setSessionSaving(false); }
  }

  useEffect(() => { void load(); void loadSessions(); void loadProfile(); }, []);
  const groups = useMemo(() => Object.entries(sources.reduce<Record<string, SourceItem[]>>((result, item) => {
    (result[item.group] ??= []).push(item);
    return result;
  }, {})), [sources]);
  const usable = sources.filter(item => ['connected', 'configured', 'available'].includes(item.status)).length;
  const attention = sources.filter(item => ['no_data', 'pending', 'unavailable'].includes(item.status)).length;
  const notImplemented = sources.filter(item => item.status === 'not_implemented').length;
  const currentSession = sessions.find((session) => session.current);
  const otherSessions = sessions.filter((session) => !session.current);

  return <div className="mx-auto max-w-7xl space-y-6">
    <header><p className="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400">Conta</p><h1 className="mt-1 text-3xl font-black">Configurações</h1><p className="mt-2 text-sm text-slate-500">Perfil, preferências, segurança e integrações da sua conta.</p><nav aria-label="Seções das configurações" className="mt-4 flex gap-2 overflow-x-auto pb-1"><a className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" href="#perfil">Perfil</a><a className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" href="#seguranca">Segurança</a><a className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" href="#aparencia">Aparência</a><a className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" href="#sessoes">Sessões</a><a className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" href="#integracoes">Integrações</a><Link className="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm" to="/app/assinaturas">Assinaturas</Link></nav></header>
    <section id="perfil" className="rounded-2xl border bg-white p-5 dark:bg-slate-900">
      <div className="flex items-center gap-3"><span className="grid h-11 w-11 place-items-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"><UserRound className="h-5 w-5" /></span><div><h2 className="text-lg font-bold">Perfil e preferências</h2><p className="text-sm text-slate-500 dark:text-slate-400">Dados exibidos na conta e comunicações que deseja receber.</p></div></div>
      {profileError && <p role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{profileError}</p>}
      {profileNotice && <p role="status" className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{profileNotice}</p>}
      <form onSubmit={saveProfile} className="mt-5 grid gap-4 sm:grid-cols-2">
        <div className="flex items-center gap-4 sm:col-span-2"><span className="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-900 text-lg font-bold text-white">{avatarPreview || profile.avatar_url ? <img src={avatarPreview || profile.avatar_url} alt="Prévia da foto do perfil" className="h-full w-full object-cover" /> : profile.name.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()}</span><label className="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm font-bold"><Camera className="h-4 w-4" />Escolher foto<input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => { const file = event.target.files?.[0] ?? null; if (avatarPreview) URL.revokeObjectURL(avatarPreview); setAvatarFile(file); setAvatarPreview(file ? URL.createObjectURL(file) : ''); }} /></label><span className="text-xs text-slate-500">JPG, PNG ou WebP, até 2 MB.</span></div>
        <label className="text-sm font-medium">Nome<input required maxLength={150} className="input mt-1" value={profile.name} onChange={(event) => setProfile((current) => ({ ...current, name: event.target.value }))} /></label>
        <label className="text-sm font-medium">E-mail<input readOnly className="input mt-1 cursor-not-allowed opacity-70" value={profile.email} /><span className="mt-1 block text-xs font-normal text-slate-500">A alteração de e-mail exige validação e fica indisponível neste ambiente.</span></label>
        <label className="text-sm font-medium">Telefone<input inputMode="tel" autoComplete="tel" maxLength={15} className="input mt-1" placeholder="(11) 99999-9999" value={profile.phone} onChange={(event) => setProfile((current) => ({ ...current, phone: formatPhone(event.target.value) }))} /></label>
        <fieldset className="space-y-2 rounded-xl border p-3"><legend className="px-1 text-sm font-medium">Notificações</legend><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={profile.email_notifications} onChange={(event) => setProfile((current) => ({ ...current, email_notifications: event.target.checked }))} />Alertas operacionais por e-mail</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={profile.product_updates} onChange={(event) => setProfile((current) => ({ ...current, product_updates: event.target.checked }))} />Novidades dos produtos contratados</label></fieldset>
        <div className="sm:col-span-2"><button disabled={profileSaving} className="rounded-xl bg-primary-600 px-5 py-2.5 font-bold text-white disabled:opacity-60">{profileSaving ? 'Salvando...' : 'Salvar perfil'}</button></div>
      </form>
    </section>
    <section id="seguranca" className="rounded-2xl border bg-white p-5 dark:bg-slate-900">
      <div className="flex items-center gap-3"><span className="grid h-11 w-11 place-items-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"><LockKeyhole className="h-5 w-5" /></span><div><h2 className="text-lg font-bold">Alterar senha</h2><p className="text-sm text-slate-500 dark:text-slate-400">Confirme a senha atual e use pelo menos 12 caracteres.</p></div></div>
      {passwordError && <p role="alert" className="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">{passwordError}</p>}
      {passwordNotice && <p role="status" className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{passwordNotice}</p>}
      <form onSubmit={savePassword} className="mt-5 grid gap-4 lg:grid-cols-3">
        <label className="text-sm font-medium">Senha atual<input required type="password" autoComplete="current-password" maxLength={72} className="input mt-1" value={password.current_password} onChange={(event) => setPassword((current) => ({ ...current, current_password: event.target.value }))} /></label>
        <label className="text-sm font-medium">Nova senha<input required type="password" autoComplete="new-password" minLength={12} maxLength={72} className="input mt-1" value={password.password} onChange={(event) => setPassword((current) => ({ ...current, password: event.target.value }))} /></label>
        <label className="text-sm font-medium">Confirmar nova senha<input required type="password" autoComplete="new-password" minLength={12} maxLength={72} className="input mt-1" value={password.password_confirmation} onChange={(event) => setPassword((current) => ({ ...current, password_confirmation: event.target.value }))} /></label>
        <div className="lg:col-span-3"><button disabled={passwordSaving} className="rounded-xl border px-5 py-2.5 font-bold disabled:opacity-60">{passwordSaving ? 'Alterando...' : 'Alterar senha'}</button></div>
      </form>
    </section>
    <section id="aparencia" className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-white dark:bg-slate-900 p-5">
      <div><h2 className="font-bold text-lg">Aparência</h2><p className="text-sm text-gray-500 dark:text-slate-400">Escolha o tema ou acompanhe a preferência do seu sistema. A escolha fica salva neste navegador.</p></div>
      <ThemeSelector />
    </section>
    <section id="sessoes" className="rounded-2xl border bg-white dark:bg-slate-900 p-5">
      <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4"><div><h2 className="font-bold text-lg">Sessões e dispositivos</h2><p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Revise os acessos ativos e encerre os que não reconhecer.</p></div><button type="button" disabled={sessionsLoading || sessionSaving || otherSessions.length === 0} onClick={() => setSessionAction({ kind: 'others' })} className="inline-flex items-center justify-center gap-2 border border-red-200 text-red-700 dark:text-red-300 px-4 py-2 rounded-xl font-bold disabled:opacity-50"><LogOut className="w-4 h-4" />Encerrar outras sessões</button></div>
      {sessionError && <div role="alert" className="mt-4 rounded-xl bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-900 p-3 text-sm text-red-700 dark:text-red-300">{sessionError} <button type="button" onClick={() => void loadSessions()} className="font-bold underline ml-1">Tentar novamente</button></div>}
      {sessionsLoading ? <div role="status" className="py-8 text-center text-sm text-slate-500"><Loader2 className="w-5 h-5 animate-spin mx-auto mb-2" />Carregando sessões...</div> : <div className="mt-4 space-y-5">{currentSession && <SessionRow session={currentSession} saving={sessionSaving} onRevoke={() => setSessionAction({ kind: 'one', session: currentSession })} />}<div><h3 className="text-xs font-black uppercase tracking-wide text-slate-400">Outros dispositivos</h3><div className="divide-y dark:divide-slate-800">{otherSessions.slice(0, visibleSessions).map(session => <SessionRow key={session.id} session={session} saving={sessionSaving} onRevoke={() => setSessionAction({ kind: 'one', session })} />)}{otherSessions.length === 0 && <p className="py-5 text-sm text-slate-500">Nenhum outro dispositivo ativo.</p>}</div>{otherSessions.length > visibleSessions && <button type="button" className="mt-2 text-sm font-bold text-primary-600" onClick={() => setVisibleSessions((value) => value + 5)}>Ver mais</button>}</div></div>}
    </section>
    <section id="integracoes" className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div><p className="text-primary-600 dark:text-primary-400 uppercase tracking-widest text-sm font-black">Configurações</p><h1 className="text-3xl font-black mt-2">Fontes de dados</h1><p className="text-gray-500 dark:text-slate-400 mt-2">Estado real da configuração local. Serviços externos só são confirmados durante uma consulta.</p></div>
      <div className="flex flex-wrap gap-3">
        <button type="button" onClick={() => void load(true)} disabled={refreshing} className="inline-flex items-center gap-2 bg-white dark:bg-slate-900 border px-5 py-3 rounded-xl font-bold disabled:opacity-60"><RefreshCw className={`w-4 h-4 ${refreshing ? 'animate-spin' : ''}`} />Atualizar</button>
        <Link to="/app/house-flipper/importar-itbi" className="inline-flex items-center gap-2 bg-primary-600 text-white px-5 py-3 rounded-xl font-bold shadow-xs"><Database className="w-4 h-4" />Importar ITBI</Link>
      </div>
    </section>

    {error && <div role="alert" className="rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-4 text-red-800 dark:text-red-200 flex items-start gap-3"><AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" /><div><strong>Falha ao consultar as fontes</strong><p className="text-sm mt-1">{error}</p><button type="button" onClick={() => void load()} className="font-bold text-sm underline mt-2">Tentar novamente</button></div></div>}

    <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div><h2 className="font-black text-lg">Diagnóstico das integrações</h2><p className="text-sm text-gray-500 dark:text-slate-400">Nenhuma chave, token, URL privada ou caminho interno é enviado ao navegador.</p></div>
        {!loading && !error && <div className="flex flex-wrap gap-2 text-xs font-black"><span className="bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 px-3 py-1 rounded-full">{usable} utilizáveis/configuradas</span><span className="bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 px-3 py-1 rounded-full">{attention} exigem atenção</span><span className="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3 py-1 rounded-full">{notImplemented} não implementadas</span></div>}
      </div>

      {loading ? <div className="min-h-48 grid place-items-center text-slate-500 dark:text-slate-400"><div className="text-center"><Loader2 className="w-7 h-7 animate-spin mx-auto text-primary-600" /><p className="mt-3 text-sm">Consultando configuração local...</p></div></div> : !error && <div className="grid md:grid-cols-2 xl:grid-cols-5 gap-4 mt-4">{groups.map(([title, items]) => <SourceGroup key={title} title={title} items={items ?? []} />)}</div>}
    </section>

    <section className="grid lg:grid-cols-3 gap-4">
      <InfoCard title="Estados sem sondagem externa" description="Configurado significa que a credencial ou endpoint existe. A saúde do provedor só é conhecida quando a funcionalidade o consulta." />
      <InfoCard title="Base ITBI local" description="A contagem exibida vem do banco atual. Importe uma planilha autorizada quando o estado estiver Sem dados." />
      <InfoCard title="Segredos no servidor" description="Credenciais ficam no ambiente do backend e nunca são devolvidas por esta tela ou gravadas no frontend." />
    </section>
    {sessionAction && <ConfirmActionDialog title={sessionAction.kind === 'all' ? 'Sair de todos os dispositivos?' : sessionAction.kind === 'others' ? 'Encerrar as outras sessões?' : 'Encerrar esta sessão?'} description={sessionAction.kind === 'all' ? 'Todos os tokens de acesso serão revogados, inclusive este navegador.' : sessionAction.kind === 'others' ? 'Os outros dispositivos serão desconectados. Esta sessão continuará ativa.' : `A sessão ${sessionAction.session?.name || 'selecionada'} será encerrada${sessionAction.session?.current ? ' e você voltará à tela de login' : ''}.`} actionLabel={sessionAction.kind === 'all' ? 'Sair de todos' : sessionAction.kind === 'others' ? 'Encerrar outras' : 'Encerrar sessão'} pendingLabel="Encerrando..." pending={sessionSaving} onCancel={() => setSessionAction(null)} onConfirm={revokeSessions} />}
  </div>;
}

function formatSessionDate(value: string | null) {
  if (!value) return 'ainda não registrado';
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
}

function SessionRow({ session, saving, onRevoke }: { session: SessionItem; saving: boolean; onRevoke: () => void }) {
  return <div className="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"><div className="flex items-start gap-3"><span className="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950 text-primary-600 dark:text-primary-400 grid place-items-center shrink-0"><MonitorSmartphone className="w-5 h-5" /></span><div><div className="flex flex-wrap items-center gap-2"><strong>{session.name || 'Dispositivo sem nome'}</strong>{session.current && <span className="text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded-full">Sessão atual</span>}</div><p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Criada {formatSessionDate(session.created_at)} · último uso {formatSessionDate(session.last_used_at)}</p><p className="text-xs text-slate-400 mt-1">Expira {formatSessionDate(session.expires_at)}</p></div></div><button type="button" disabled={saving} onClick={onRevoke} className="self-start sm:self-center border border-slate-300 dark:border-slate-700 px-3 py-2 rounded-lg text-sm font-bold disabled:opacity-50">Encerrar</button></div>;
}

function SourceGroup({ title, items }: { title: string; items: SourceItem[] }) {
  const Icon = groupIcons[title as keyof typeof groupIcons] ?? Database;
  return <div className="border rounded-2xl p-4 bg-slate-50 dark:bg-slate-950"><div className="flex items-center gap-2"><span className="w-9 h-9 rounded-xl bg-white dark:bg-slate-900 border grid place-items-center text-primary-600 dark:text-primary-400"><Icon className="w-4 h-4" /></span><h3 className="font-black">{title}</h3></div><div className="space-y-4 mt-4">{items.map(item => <div key={item.id}><div className="flex items-start justify-between gap-2"><span className="font-bold text-sm">{item.name}</span><StatusDot status={item.status} /></div><p className="text-xs text-gray-500 dark:text-slate-400 mt-1">{item.description}</p>{item.details?.record_count !== undefined && <p className="text-xs font-semibold mt-1">{item.details.record_count.toLocaleString('pt-BR')} transações{item.details.last_import_at ? ` · última importação ${new Intl.DateTimeFormat('pt-BR').format(new Date(item.details.last_import_at))}` : ''}</p>}</div>)}</div></div>;
}

function StatusDot({ status }: { status: SourceStatus }) {
  const view = statusView[status];
  return <span className="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide whitespace-nowrap"><span className={`w-2 h-2 rounded-full ${view.color}`} aria-hidden="true" />{view.label}</span>;
}

function InfoCard({ title, description }: { title: string; description: string }) {
  return <div className="bg-white dark:bg-slate-900 border rounded-2xl p-5 shadow-xs"><div className="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Settings2 className="w-5 h-5" /></div><h3 className="font-black mt-4">{title}</h3><p className="text-sm text-gray-500 dark:text-slate-400 mt-2">{description}</p></div>;
}
