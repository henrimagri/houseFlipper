import { Bell, HelpCircle, Loader2, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import ThemeSelector from './ThemeSelector';

interface TopbarProps { userName?: string; greeting?: string; subtitle?: string }
type Activity = { titulo: string; desc: string; tempo: string };

export default function Topbar({ userName: overrideName, greeting: overrideGreeting, subtitle = 'Bem-vindo ao seu painel de controle' }: TopbarProps) {
  const navigate = useNavigate();
  const activityPanel = useRef<HTMLDivElement>(null);
  const [profileName, setProfileName] = useState('');
  const [profileError, setProfileError] = useState(false);
  const [activities, setActivities] = useState<Activity[]>([]);
  const [activityOpen, setActivityOpen] = useState(false);
  const [activityLoading, setActivityLoading] = useState(false);
  const [activityError, setActivityError] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    api.get('/auth/me', { signal: controller.signal }).then(({ data }) => setProfileName(data.name)).catch(() => { if (!controller.signal.aborted) setProfileError(true); });
    return () => controller.abort();
  }, []);

  useEffect(() => {
    if (!activityOpen) return;
    const close = (event: MouseEvent) => { if (!activityPanel.current?.contains(event.target as Node)) setActivityOpen(false); };
    const escape = (event: KeyboardEvent) => { if (event.key === 'Escape') setActivityOpen(false); };
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', escape);
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', escape); };
  }, [activityOpen]);

  async function toggleActivities() {
    const opening = !activityOpen;
    setActivityOpen(opening);
    if (!opening) return;
    setActivityLoading(true); setActivityError('');
    try {
      const response = await api.get('/dashboard');
      setActivities(response.data.activities ?? []);
    } catch (exception: any) {
      setActivityError(exception.response?.data?.message ?? 'Não foi possível carregar as atividades.');
    } finally { setActivityLoading(false); }
  }

  const userName = overrideName ?? (profileName || (profileError ? 'Perfil indisponível' : 'Carregando perfil'));
  const greeting = overrideGreeting ?? (profileName ? `Olá, ${profileName.split(' ')[0]}! 👋` : 'Bem-vindo');

  return <header className="flex flex-col sm:flex-row gap-3 sm:items-center justify-between px-4 sm:px-8 py-5 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-700">
    <div><h1 className="text-xl font-semibold text-gray-900 dark:text-slate-100">{greeting}</h1><p className="text-sm text-gray-500 dark:text-slate-400 mt-0.5">{subtitle}</p></div>
    <div className="flex flex-wrap items-center gap-3">
      <ThemeSelector />
      <button type="button" onClick={() => navigate('/projetos?novo=1')} className="bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-xs"><Plus className="w-4 h-4" />Novo Projeto</button>
      <div ref={activityPanel} className="relative">
        <button type="button" aria-label="Atividades recentes" aria-expanded={activityOpen} aria-controls="recent-activities" onClick={toggleActivities} className="w-9 h-9 rounded-lg bg-gray-50 dark:bg-slate-950 hover:bg-gray-100 dark:hover:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-300"><Bell className="w-[18px] h-[18px]" /></button>
        {activityOpen && <div id="recent-activities" className="absolute right-0 top-11 z-50 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border bg-white dark:bg-slate-900 shadow-xl overflow-hidden">
          <div className="p-4 border-b"><h2 className="font-bold">Atividades recentes</h2><p className="text-xs text-gray-500 dark:text-slate-400 mt-1">Últimos lançamentos registrados na sua conta.</p></div>
          <div className="max-h-80 overflow-y-auto" aria-live="polite">
            {activityLoading ? <p className="p-5 text-sm text-gray-500 flex items-center gap-2"><Loader2 className="w-4 h-4 animate-spin" />Carregando...</p> : activityError ? <p role="alert" className="p-5 text-sm text-red-700 dark:text-red-300">{activityError}</p> : activities.length ? activities.map((activity, index) => <div key={`${activity.tempo}-${activity.desc}-${index}`} className="p-4 border-b last:border-0"><strong className="text-sm block">{activity.titulo}</strong><p className="text-sm text-gray-600 dark:text-slate-300 mt-1 wrap-break-word">{activity.desc}</p><time className="text-xs text-gray-400 mt-2 block">{activity.tempo}</time></div>) : <p className="p-5 text-sm text-gray-500 dark:text-slate-400">Nenhuma atividade registrada.</p>}
          </div>
          <button type="button" onClick={() => { setActivityOpen(false); navigate('/despesas'); }} className="w-full p-3 text-sm font-bold text-primary-600 dark:text-primary-400 border-t hover:bg-slate-50 dark:hover:bg-slate-800">Abrir despesas</button>
        </div>}
      </div>
      <button type="button" aria-label="Abrir central de ajuda" onClick={() => navigate('/ajuda')} className="w-9 h-9 rounded-lg bg-gray-50 dark:bg-slate-950 hover:bg-gray-100 dark:hover:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-300"><HelpCircle className="w-[18px] h-[18px]" /></button>
      <div className="flex items-center gap-3 pl-3 border-l border-gray-200 dark:border-slate-700"><div className="text-right"><div className="text-sm font-semibold text-gray-900 dark:text-slate-100">{userName}</div><div className="text-xs text-gray-500 dark:text-slate-400">Investidor</div></div><div aria-hidden="true" className="w-9 h-9 rounded-full bg-linear-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm">{userName.split(' ').map((name) => name[0]).slice(0, 2).join('')}</div></div>
    </div>
  </header>;
}
