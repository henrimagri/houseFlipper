import { Link, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { Accessibility, Bell, ChevronDown, CreditCard, HelpCircle, History, LifeBuoy, LogOut, MapPin, Menu, Palette, Settings, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ProductSidebar from './ProductSidebar';
import ProductTour from './ProductTour';
import { PRODUCT_META, ProductSlug } from '@/lib/products';
import { useSession } from '@/lib/session';
import ThemeSelector from './ThemeSelector';
import ProductSwitcher from './ProductSwitcher';
import api from '@/lib/api';
import { clearAuthToken } from '@/lib/authToken';
import AppSearchInput from '@/ui/AppSearchInput';
import CommandPalette from '@/ui/CommandPalette';

export default function ProductLayout({ product }: { product: ProductSlug | 'account' }) {
  const { loading, error, products, name, navigation, email, avatarUrl, locationLabel } = useSession();
  const [menuOpen, setMenuOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar_collapsed') === '1');
  const [notes, setNotes] = useState<{ id: string; title: string; body: string; href: string }[]>([]);
  const [openNotes, setOpenNotes] = useState(false);
  const [search, setSearch] = useState('');
  const [tourForce, setTourForce] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [commandsOpen, setCommandsOpen] = useState(false);
  const profileRoot = useRef<HTMLDivElement>(null);
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
  }, [collapsed]);

  useEffect(() => {
    if (product === 'account') return;
    api.get('/notifications').then(({ data }) => setNotes(data.data ?? [])).catch(() => setNotes([]));
  }, [product, location.pathname]);

  useEffect(() => {
    if (!profileOpen) return;
    const close = (event: MouseEvent) => {
      if (!profileRoot.current?.contains(event.target as Node)) setProfileOpen(false);
    };
    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setProfileOpen(false);
    };
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', escape);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', escape);
    };
  }, [profileOpen]);

  useEffect(() => {
    const openCommands = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); setCommandsOpen(true); }
    };
    document.addEventListener('keydown', openCommands);
    return () => document.removeEventListener('keydown', openCommands);
  }, []);

  async function logout() {
    try { await api.post('/auth/logout'); } catch { /* a sessão local ainda precisa terminar */ }
    clearAuthToken();
    navigate('/entrar');
  }

  if (loading) return <p className="p-8" role="status">Carregando ambiente...</p>;
  if (error) return <p className="p-8 text-red-700" role="alert">{error}</p>;
  if (product !== 'account' && !products.includes(product)) {
    return <Navigate to="/app/produtos" replace state={{ from: location.pathname, missing: product }} />;
  }
  const subtitle = product === 'account' ? 'Conta e assinaturas' : PRODUCT_META[product].label;
  const crumbs = product === 'account'
    ? [{ label: 'Conta', path: '/app/inicio' }]
    : (navigation[product] ?? []).filter((item) => location.pathname.startsWith(item.path)).slice(-1);
  const initials = (name || email || 'HF').split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase();
  const searchTarget = product === 'imobiliaria'
    ? `/app/imobiliaria/imoveis?q=${encodeURIComponent(search)}`
    : product === 'corretor'
      ? `/app/corretor/pesquisar?q=${encodeURIComponent(search)}`
      : `/app/house-flipper/oportunidades?q=${encodeURIComponent(search)}`;
  const commandItems = product === 'account'
    ? [{ label: 'Início da conta', path: '/app/inicio', section: 'Conta' }, { label: 'Produtos', path: '/app/produtos', section: 'Conta' }, { label: 'Assinaturas', path: '/app/assinaturas', section: 'Conta' }, { label: 'Configurações', path: '/app/configuracoes', section: 'Conta' }]
    : (navigation[product] ?? []).map(item => ({ label: item.label, path: item.path, section: subtitle }));

  return (
    <div className="app-canvas flex min-h-dvh w-full overflow-x-hidden" data-product={product}>
      {menuOpen && <button aria-label="Fechar menu" onClick={() => setMenuOpen(false)} className="fixed inset-0 bg-black/40 z-40 lg:hidden" />}
      <div className={`${menuOpen ? 'block' : 'hidden'} fixed inset-y-0 left-0 z-50 lg:block`}>
        <ProductSidebar product={product} collapsed={collapsed} onToggle={() => setCollapsed((value) => !value)} onNavigate={() => setMenuOpen(false)} />
      </div>
      <div className={`flex min-w-0 flex-1 flex-col transition-[margin] duration-300 ${collapsed ? 'lg:ml-[76px]' : 'lg:ml-72'}`}>
        <header className="app-surface sticky top-0 z-20 flex h-16 items-center gap-3 border-b px-4 backdrop-blur sm:px-6">
          <button aria-expanded={menuOpen} aria-label="Abrir menu" onClick={() => setMenuOpen(true)} className="lg:hidden p-2 rounded-lg hover:bg-slate-100">
            <Menu className="h-5 w-5" />
          </button>
          <AppSearchInput className="hidden max-w-xl flex-1 md:flex" value={search} onChange={setSearch} onSubmit={() => { if (product !== 'account') window.location.assign(searchTarget); }} placeholder="Pesquisar imóveis, clientes ou endereços" shortcut="⌘ K" />
          <button type="button" onClick={() => setCommandsOpen(true)} className="hidden rounded-lg border px-2 py-1 text-[11px] font-semibold text-slate-500 xl:block" title="Abrir comandos">Ctrl K</button>
          <div className="hidden xl:flex items-center gap-1 text-xs text-slate-500 rounded-lg border px-2 py-1">
            <MapPin className="h-3.5 w-3.5" />
            {locationLabel || 'Localização não informada'}
          </div>
          <div className="ml-auto flex items-center gap-2">
            <p className="hidden lg:block text-xs text-slate-400">{crumbs[0]?.label || subtitle}</p>
            {product !== 'account' && (
              <button type="button" className="p-2 rounded-lg hover:bg-slate-100" title="Rever tutorial" onClick={() => setTourForce(true)}>
                <HelpCircle className="h-5 w-5" />
              </button>
            )}
            <div className="relative">
              <button type="button" aria-label="Notificações" onClick={() => setOpenNotes((value) => !value)} className="relative p-2 rounded-lg hover:bg-slate-100">
                <Bell className="w-5 h-5" />
                {notes.length > 0 && <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-sky-600" />}
              </button>
              {openNotes && (
                <div className="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-900 border rounded-xl shadow-lg p-2 z-30">
                  {notes.length === 0 && <p className="text-sm text-gray-500 p-3">Nenhuma notificação neste produto.</p>}
                  {notes.map((note) => (
                    <a key={note.id} href={note.href} className="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800">
                      <p className="text-sm font-medium">{note.title}</p>
                      <p className="text-xs text-gray-500">{note.body}</p>
                    </a>
                  ))}
                </div>
              )}
            </div>
            <ThemeSelector />
            <div className="relative" ref={profileRoot}>
              <button type="button" data-tour="profile-menu" aria-haspopup="menu" aria-expanded={profileOpen} onClick={() => setProfileOpen((value) => !value)} className="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Abrir menu do perfil">
                {avatarUrl ? <img src={avatarUrl} alt="" className="h-9 w-9 rounded-full object-cover" /> : <span className="h-9 w-9 rounded-full bg-slate-900 text-white grid place-items-center text-xs font-semibold dark:bg-sky-600">{initials || 'HF'}</span>}
                <ChevronDown className={`hidden h-4 w-4 text-slate-500 sm:block transition ${profileOpen ? 'rotate-180' : ''}`} />
              </button>
              {profileOpen && (
                <div role="menu" aria-label="Perfil e produtos" className="fixed inset-x-3 top-[4.25rem] z-50 max-h-[calc(100dvh-5rem)] overflow-y-auto rounded-2xl border bg-white p-2 text-slate-900 shadow-2xl dark:bg-slate-900 dark:text-slate-100 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[23rem]">
                  <div className="flex items-center gap-3 p-3">
                    {avatarUrl ? <img src={avatarUrl} alt="Foto do perfil" className="h-11 w-11 rounded-full object-cover" /> : <span className="h-11 w-11 rounded-full bg-slate-900 text-white grid place-items-center text-sm font-bold dark:bg-sky-600">{initials || 'HF'}</span>}
                    <span className="min-w-0 flex-1"><strong className="block truncate text-sm">{name || 'Minha conta'}</strong><span className="block truncate text-xs text-slate-500 dark:text-slate-400">{email}</span><span className="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Conta ativa</span></span>
                  </div>
                  <div className="grid grid-cols-2 gap-1 border-y py-2">
                    <Link role="menuitem" to="/app/configuracoes#perfil" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><UserRound className="h-4 w-4" /> Perfil</Link>
                    <Link role="menuitem" to="/app/configuracoes" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><Settings className="h-4 w-4" /> Configurações</Link>
                    <Link role="menuitem" to="/app/configuracoes#aparencia" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><Palette className="h-4 w-4" /> Aparência</Link>
                    <Link role="menuitem" to="/app/configuracoes#acessibilidade" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><Accessibility className="h-4 w-4" /> Acessibilidade</Link>
                  </div>
                  <p className="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Meus sistemas</p>
                  <ProductSwitcher embedded activeProduct={product} onSelect={() => setProfileOpen(false)} />
                  <div className="mt-2 border-t pt-2">
                    <Link role="menuitem" to="/app/assinaturas" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><CreditCard className="h-4 w-4" /> Planos e assinaturas</Link>
                    <Link role="menuitem" to="/app/assinaturas#historico" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><History className="h-4 w-4" /> Histórico</Link>
                    <Link role="menuitem" to="/app/ajuda" onClick={() => setProfileOpen(false)} className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><LifeBuoy className="h-4 w-4" /> Ajuda</Link>
                    <button role="menuitem" type="button" onClick={() => void logout()} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950"><LogOut className="h-4 w-4" /> Sair</button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>
        <main className="flex-1 min-w-0 p-4 sm:p-6 overflow-x-hidden" data-tour="page">
          <Outlet />
        </main>
      </div>
      {product !== 'account' && (
        <ProductTour
          product={product}
          force={tourForce}
          onClose={() => setTourForce(false)}
        />
      )}
      <CommandPalette open={commandsOpen} onClose={() => setCommandsOpen(false)} items={commandItems} />
    </div>
  );
}
