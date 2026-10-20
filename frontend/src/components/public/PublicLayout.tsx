import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet } from 'react-router-dom';
import { ArrowUpRight, ChevronDown, Hexagon, Menu, X } from 'lucide-react';
import { getAuthToken } from '@/lib/authToken';
import { useSession } from '@/lib/session';
import ThemeSelector from '@/components/ThemeSelector';

const solutions = [
  { to: '/solucoes/corretor', label: 'Corretor', text: 'Carteira, captações e presença digital.' },
  { to: '/solucoes/imobiliaria', label: 'Imobiliária', text: 'CRM, equipes, filiais e indicadores.' },
  { to: '/solucoes/house-flipper', label: 'House Flipper', text: 'Oportunidades, obras e viabilidade.' },
];

function NavItem({ to, children }: { to: string; children: string }) {
  return <NavLink to={to} className={({ isActive }) => `rounded-xl px-3 py-2 text-sm font-semibold transition ${isActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-200' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'}`}>{children}</NavLink>;
}

export default function PublicLayout() {
  const token = getAuthToken(); const session = useSession();
  const [open, setOpen] = useState(false); const [solutionsOpen, setSolutionsOpen] = useState(false); const [condensed, setCondensed] = useState(false);
  const solutionRoot = useRef<HTMLDivElement>(null);
  const entitled = session.products.length > 0; const accountHref = session.requiresEmailVerification ? '/confirmar-email' : entitled ? '/app/inicio' : '/app/produtos';
  const accountLabel = session.requiresEmailVerification ? 'Confirmar e-mail' : entitled ? 'Acessar produtos' : 'Ver catálogo';

  useEffect(() => {
    const onScroll = () => setCondensed(window.scrollY > 28); onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);
  useEffect(() => {
    if (!solutionsOpen) return;
    const close = (event: MouseEvent) => { if (!solutionRoot.current?.contains(event.target as Node)) setSolutionsOpen(false); };
    const key = (event: KeyboardEvent) => { if (event.key === 'Escape') setSolutionsOpen(false); };
    document.addEventListener('mousedown', close); document.addEventListener('keydown', key);
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', key); };
  }, [solutionsOpen]);

  return <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
    <header className={`sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl transition-all dark:border-slate-800/80 dark:bg-slate-950/75 ${condensed ? 'shadow-sm' : ''}`}>
      <div className={`mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 transition-all sm:px-6 ${condensed ? 'py-2' : 'py-3.5'}`}>
        <Link to="/" className="flex items-center gap-2.5 font-black tracking-tight"><span className="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-primary-500 to-cyan-500 text-white shadow-lg shadow-primary-600/20"><Hexagon className="size-5" /></span><span>FlipControl</span></Link>
        <nav aria-label="Navegação principal" className="hidden items-center gap-0.5 lg:flex">
          <NavItem to="/">Início</NavItem>
          <div ref={solutionRoot} className="relative"><button type="button" aria-haspopup="menu" aria-expanded={solutionsOpen} onClick={() => setSolutionsOpen(value => !value)} className="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Soluções <ChevronDown className={`size-4 transition ${solutionsOpen ? 'rotate-180' : ''}`} /></button>
            {solutionsOpen && <div role="menu" className="absolute left-0 mt-2 w-80 rounded-2xl border bg-white p-2 shadow-2xl dark:border-slate-700 dark:bg-slate-900">{solutions.map(item => <Link role="menuitem" key={item.to} to={item.to} onClick={() => setSolutionsOpen(false)} className="group flex items-start justify-between rounded-xl p-3 hover:bg-slate-50 dark:hover:bg-slate-800"><span><strong className="block text-sm">{item.label}</strong><span className="mt-0.5 block text-xs text-slate-500">{item.text}</span></span><ArrowUpRight className="mt-1 size-4 text-slate-300 transition group-hover:text-primary-500" /></Link>)}</div>}
          </div>
          <NavItem to="/funcionalidades">Funcionalidades</NavItem><NavItem to="/precos">Planos</NavItem><NavItem to="/sobre">Sobre</NavItem><NavItem to="/contato">Contato</NavItem>
        </nav>
        <div className="hidden items-center gap-2 lg:flex"><ThemeSelector compact />{token ? <Link to={accountHref} className="rounded-xl border px-3 py-2 text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-900">{accountLabel}</Link> : <Link to="/entrar" className="rounded-xl px-3 py-2 text-sm font-bold">Entrar</Link>}<Link to="/cadastro" className="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-lg dark:bg-white dark:text-slate-950">Começar agora</Link></div>
        <button type="button" className="rounded-xl p-2 lg:hidden" aria-label="Abrir menu" onClick={() => setOpen(true)}><Menu className="size-6" /></button>
      </div>
    </header>
    {open && <div className="fixed inset-0 z-50 bg-slate-950/55 backdrop-blur-sm lg:hidden" onClick={() => setOpen(false)}><div role="dialog" aria-modal="true" aria-label="Menu" className="absolute right-0 top-0 h-full w-80 max-w-[90vw] overflow-y-auto bg-white p-5 shadow-2xl dark:bg-slate-950" onClick={event => event.stopPropagation()}><div className="mb-7 flex items-center justify-between"><strong>Menu</strong><button type="button" className="rounded-lg p-2" aria-label="Fechar menu" onClick={() => setOpen(false)}><X /></button></div><div className="grid gap-1 text-sm"><Link className="public-mobile-link" to="/" onClick={() => setOpen(false)}>Início</Link>{solutions.map(item => <Link className="public-mobile-link" key={item.to} to={item.to} onClick={() => setOpen(false)}>{item.label}</Link>)}<Link className="public-mobile-link" to="/funcionalidades" onClick={() => setOpen(false)}>Funcionalidades</Link><Link className="public-mobile-link" to="/precos" onClick={() => setOpen(false)}>Planos</Link><Link className="public-mobile-link" to="/sobre" onClick={() => setOpen(false)}>Sobre</Link><Link className="public-mobile-link" to="/contato" onClick={() => setOpen(false)}>Contato</Link><div className="my-3 border-t" /><div className="flex items-center justify-between px-3 py-2"><span className="text-sm font-semibold">Aparência</span><ThemeSelector /></div><Link className="public-mobile-link" to={token ? accountHref : '/entrar'} onClick={() => setOpen(false)}>{token ? accountLabel : 'Entrar'}</Link><Link to="/cadastro" onClick={() => setOpen(false)} className="mt-2 rounded-xl bg-primary-600 px-4 py-3 text-center font-bold text-white">Começar agora</Link></div></div></div>}
    <main><Outlet /></main>
    <footer className="mt-16 border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"><div className="mx-auto grid max-w-7xl gap-9 px-4 py-12 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-4"><div><p className="flex items-center gap-2 font-black"><Hexagon className="size-5 text-primary-600"/>FlipControl</p><p className="mt-3 max-w-xs leading-6 text-slate-500">Tecnologia para operações imobiliárias que precisam de clareza, controle e decisões bem fundamentadas.</p></div><FooterLinks title="Soluções" links={[["Corretor","/solucoes/corretor"],["Imobiliária","/solucoes/imobiliaria"],["House Flipper","/solucoes/house-flipper"],["Comparar produtos","/comparar-produtos"]]} /><FooterLinks title="Empresa" links={[["Sobre","/sobre"],["Contato","/contato"],["Solicitar demonstração","/solicitar-demonstracao"],["Ajuda","/ajuda"]]} /><FooterLinks title="Legal" links={[["Termos de uso","/termos"],["Privacidade","/privacidade"],["Planos","/precos"]]} /></div><div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 border-t px-4 py-5 text-xs text-slate-500 sm:px-6"><span>© {new Date().getFullYear()} FlipControl</span><span>Produtos e permissões independentes por assinatura.</span></div></footer>
  </div>;
}

function FooterLinks({ title, links }: { title: string; links: string[][] }) { return <div><p className="font-bold">{title}</p><div className="mt-3 grid gap-2 text-slate-500">{links.map(([label, to]) => <Link key={to} to={to} className="hover:text-primary-600">{label}</Link>)}</div></div>; }
