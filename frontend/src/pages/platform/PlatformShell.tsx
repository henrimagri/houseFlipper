import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import ThemeSelector from '@/components/ThemeSelector';
import { Activity, ArrowLeft, BarChart3, Building2, ChevronLeft, ChevronRight, CreditCard, Database, FileText, LayoutDashboard, Menu, Search, Settings2, ShieldCheck, Sparkles, Users, X, RefreshCw } from 'lucide-react';

export type PlatformNavigationGroup = { label: string; items: string[][] };
const preference = 'flipcontrol:platform-sidebar-collapsed';
const itemIcons: Record<string, typeof Activity> = { overview: LayoutDashboard, clients: Users, leads: Users, funnel: BarChart3, security: ShieldCheck, mfa: ShieldCheck, audit: FileText, billing: CreditCard, subscriptions: CreditCard, ai: Sparkles, rag: Database, health: Activity, administrators: Users, controls: Settings2, jobs: Building2 };
export default function PlatformShell({ groups, section, title, name, search, onSearchChange, onSearch, onRefresh, loading, receivedAt, children }: {
  groups: PlatformNavigationGroup[]; section: string; title: string; name: string; search: string;
  onSearchChange: (value: string) => void; onSearch: (event: FormEvent) => void; onRefresh: () => void;
  loading: boolean; receivedAt: number | null; children: ReactNode;
}) {
  const [collapsed, setCollapsed] = useState(() => { try { return localStorage.getItem(preference) === 'true'; } catch { return false; } });
  const [mobile, setMobile] = useState(false);
  const [clock, setClock] = useState(Date.now());
  const searchField = useRef<HTMLInputElement>(null), drawer = useRef<HTMLElement>(null), workspace = useRef<HTMLDivElement>(null), menuButton = useRef<HTMLButtonElement>(null);
  const group = groups.find(value => value.items.some(item => item[0] === section));
  useEffect(() => { setMobile(false); }, [section]);
  useEffect(() => { try { localStorage.setItem(preference, String(collapsed)); } catch { /* Preference is optional. */ } }, [collapsed]);
  useEffect(() => { const timer = window.setInterval(() => { if (!document.hidden) setClock(Date.now()); }, 15000); return () => clearInterval(timer); }, []);
  useEffect(() => {
    const shortcut = (event: KeyboardEvent) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && !document.querySelector('[aria-modal="true"]')) { event.preventDefault(); searchField.current?.focus(); } };
    document.addEventListener('keydown', shortcut); return () => document.removeEventListener('keydown', shortcut);
  }, []);
  useEffect(() => {
    if (!mobile) return;
    const previous = document.activeElement as HTMLElement | null;
    workspace.current?.setAttribute('inert', '');
    const controls = () => Array.from(drawer.current?.querySelectorAll<HTMLElement>('a[href],button') ?? []);
    controls()[0]?.focus();
    const keys = (event: KeyboardEvent) => {
      if (event.key === 'Escape') { event.preventDefault(); setMobile(false); }
      if (event.key === 'Tab') { const items = controls(), first = items[0], last = items[items.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); } }
    };
    const resize = () => { if (window.innerWidth >= 1024) setMobile(false); };
    document.addEventListener('keydown', keys); window.addEventListener('resize', resize);
    return () => { workspace.current?.removeAttribute('inert'); document.removeEventListener('keydown', keys); window.removeEventListener('resize', resize); if (previous?.isConnected) previous.focus(); };
  }, [mobile]);
  const seconds = receivedAt == null ? null : Math.max(0, Math.round((Math.max(clock, Date.now()) - receivedAt) / 1000));
  const hostname = typeof window === 'undefined' ? '' : window.location.hostname;
  return <div className="sol-shell" data-collapsed={collapsed}>
    {mobile && <button type="button" className="sol-nav-backdrop" aria-label="Fechar navegação administrativa" onClick={() => setMobile(false)} />}
    <aside ref={drawer} className={'sol-sidebar ' + (mobile ? 'sol-sidebar-open' : '')} role={mobile ? 'dialog' : undefined} aria-modal={mobile || undefined} aria-label="Menu administrativo">
      <div className="sol-brand"><span className="sol-brand-mark"><ShieldCheck size={23} /></span><div className="sol-nav-label"><strong>FlipControl</strong><small>Central da plataforma</small></div><button type="button" className="sol-mobile-close" aria-label="Fechar menu" onClick={() => setMobile(false)}><X size={18} /></button></div>
      <button type="button" className="sol-collapse" aria-label={collapsed ? 'Expandir sidebar' : 'Recolher sidebar'} aria-expanded={!collapsed} onClick={() => setCollapsed(value => !value)}>{collapsed ? <ChevronRight size={16} /> : <><ChevronLeft size={16} /><span className="sol-nav-label">Recolher navegação</span></>}</button>
      <nav aria-label="Navegação administrativa">{groups.map((value, index) => <div key={value.label} className="sol-nav-group" role="group" aria-label={value.label}><p className="sol-nav-label">{value.label}</p>{value.items.map(([key, label]) => {
        const Icon = itemIcons[key] ?? [Activity, ShieldCheck, Database, Sparkles, CreditCard, Settings2][index % 6];
        return <Link key={key} to={key === 'overview' ? '/platform' : '/platform/' + key} title={label} aria-label={label} aria-current={section === key ? 'page' : undefined}><Icon size={17} strokeWidth={1.6} /><span className="sol-nav-label">{label}</span></Link>;
      })}</div>)}</nav>
      <Link to="/app/inicio" className="sol-sidebar-footer" title="Voltar à conta"><ArrowLeft size={16} /><span className="sol-nav-label">Voltar à conta</span></Link>
    </aside>
    <div className="sol-workspace" ref={workspace}>
      <header className="sol-topbar"><button type="button" ref={menuButton} className="sol-menu-toggle sol-button" aria-label="Abrir navegação administrativa" aria-expanded={mobile} onClick={() => setMobile(true)}><Menu size={19} /></button><div className="sol-breadcrumb"><span>{group?.label ?? 'CENTRAL'}</span><ChevronRight size={12} /><strong>{title}</strong></div>
        <form onSubmit={onSearch} className="sol-global-search"><label><span className="sr-only">Busca global administrativa</span><Search size={15} /><input ref={searchField} value={search} maxLength={180} onChange={event => onSearchChange(event.target.value)} placeholder="Buscar ID, e-mail, tenant, request ou job…" /><kbd>Ctrl K</kbd></label><button className="sol-button" disabled={!search.trim()}>Buscar</button></form>
        <button type="button" className="sol-button sol-refresh" onClick={onRefresh} disabled={loading} aria-label="Atualizar"><RefreshCw size={15} /><span>Atualizar</span></button><span className="sol-environment" title="Endereço da interface atual">{['127.0.0.1', 'localhost', '[::1]'].includes(hostname) ? 'Local' : hostname}</span><ThemeSelector compact /><div className="sol-operator" title={name + ' · Platform Admin'}><span className="sol-avatar">{name.split(' ').filter(Boolean).slice(0, 2).map(part => part[0]).join('') || 'PA'}</span><span>{name || 'Administrador'}<small>Platform Admin</small></span></div>
      </header>
      <main className="sol-main"><div className="sol-received" aria-live="off">{loading ? 'Consultando registros…' : seconds == null ? 'Aguardando consulta' : seconds < 60 ? `Consulta recebida há ${seconds}s` : `Consulta recebida há ${Math.floor(seconds / 60)} min`}</div>{children}</main>
    </div>
  </div>;
}
