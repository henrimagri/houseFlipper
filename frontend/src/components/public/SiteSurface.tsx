import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ArrowUpRight, Hexagon, Menu, Moon, Sun, X } from 'lucide-react';
import { useTheme } from '@/components/ThemeProvider';
import { useSession } from '@/lib/session';
import { getAuthToken } from '@/lib/authToken';
import AnalyticsConsent from './AnalyticsConsent';
import StippleOrb from './StippleOrb';
import '@/pages/public/stipple-landing.css';

function SiteHeader({ home }: { home: boolean }) {
  const { resolvedTheme, setMode } = useTheme();
  const { requiresEmailVerification, products } = useSession();
  const [open, setOpen] = useState(false), [stuck, setStuck] = useState(false);
  const button = useRef<HTMLButtonElement>(null), dialog = useRef<HTMLDialogElement>(null);
  const location = useLocation();
  const dark = resolvedTheme === 'dark';
  const accountHref = requiresEmailVerification ? '/confirmar-email' : products.length ? '/app/inicio' : '/app/produtos';
  const accountLabel = requiresEmailVerification ? 'Confirmar e-mail' : 'Minha conta';
  const links = home ? [
    { href: '#produtos', label: 'Soluções' }, { href: '#como-funciona', label: 'Como funciona' }, { href: '#duvidas', label: 'Dúvidas' },
  ] : [
    { href: '/#produtos', label: 'Soluções' }, { href: '/funcionalidades', label: 'Funcionalidades' }, { href: '/precos', label: 'Planos' }, { href: '/contato', label: 'Contato' },
  ];
  useEffect(() => {
    const scroll = () => setStuck(window.scrollY > 12);
    scroll(); window.addEventListener('scroll', scroll, { passive: true });
    return () => window.removeEventListener('scroll', scroll);
  }, []);
  useEffect(() => { setOpen(false); }, [location.pathname]);
  useEffect(() => {
    if (!open || !dialog.current) return;
    const element = dialog.current, previousOverflow = document.body.style.overflow;
    element.showModal(); document.body.style.overflow = 'hidden';
    const resize = () => { if (window.innerWidth > 960) setOpen(false); };
    window.addEventListener('resize', resize);
    return () => {
      if (element.open) element.close();
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('resize', resize); button.current?.focus();
    };
  }, [open]);
  return <>
    <header className="landing-nav" data-stuck={stuck ? '' : undefined}><div className="landing-wrap">
      <Link className="landing-brand" to="/" aria-label="FlipControl, início"><Hexagon size={23} strokeWidth={1.6} />FlipControl</Link>
      <nav className="landing-links" aria-label="Navegação principal">{links.map(item => <a key={item.href} href={item.href} aria-current={location.pathname === item.href ? 'page' : undefined}>{item.label}</a>)}</nav>
      <div className="landing-navr"><button type="button" className="landing-icon-btn" aria-label={dark ? 'Ativar tema claro' : 'Ativar tema escuro'} onClick={() => setMode(dark ? 'light' : 'dark')}>{dark ? <Sun size={17} /> : <Moon size={17} />}</button>
        <Link className="landing-login" to={getAuthToken() ? accountHref : '/entrar'}>{getAuthToken() ? accountLabel : 'Entrar'}</Link>
        <Link className="landing-btn landing-pill" to="/cadastro">Começar agora <ArrowUpRight size={14} /></Link>
        <button ref={button} type="button" className="landing-icon-btn landing-burger" aria-label="Abrir menu" aria-expanded={open} aria-controls="site-mobile-menu" onClick={() => setOpen(true)}><Menu size={20} /></button>
      </div>
    </div></header>
    {open && <dialog ref={dialog} id="site-mobile-menu" className="landing-sheet" aria-label="Menu de navegação" onCancel={event => { event.preventDefault(); setOpen(false); }}>
      <div className="landing-sheet-top"><span className="landing-brand"><Hexagon size={23} />FlipControl</span><button type="button" autoFocus className="landing-icon-btn" aria-label="Fechar menu" onClick={() => setOpen(false)}><X size={20} /></button></div>
      <ul>{links.map(item => <li key={item.href}><a href={item.href} onClick={() => setOpen(false)}>{item.label}<ArrowUpRight size={20} /></a></li>)}
        {home && <li><Link to="/precos" onClick={() => setOpen(false)}>Planos<ArrowUpRight size={20} /></Link></li>}
        <li><Link to={getAuthToken() ? accountHref : '/entrar'} onClick={() => setOpen(false)}>{getAuthToken() ? accountLabel : 'Entrar'}<ArrowUpRight size={20} /></Link></li>
      </ul><Link className="landing-btn" to="/cadastro" onClick={() => setOpen(false)}>Começar agora<ArrowUpRight size={15} /></Link>
    </dialog>}
  </>;
}

function SiteFooter() {
  const { resolvedTheme } = useTheme();
  return <>
    <footer className="landing-sec landing-foot"><p className="landing-foot-tag landing-rv">FlipControl. Clareza para enxergar.<br /><span className="landing-muted">Controle para transformar.</span></p>
      <StippleOrb dark={resolvedTheme === 'dark'} horizon />
      <div className="landing-wrap landing-foot-row"><div className="landing-foot-cols"><div><h4>Explore</h4><ul><li><a href="/#produtos">Soluções</a></li><li><a href="/#como-funciona">Como funciona</a></li><li><Link to="/precos">Planos</Link></li><li><Link to="/contato">Contato</Link></li></ul></div>
        <div><h4>Saiba mais</h4><ul><li><Link to="/privacidade">Privacidade</Link></li><li><Link to="/termos">Termos de uso</Link></li><li><Link to="/ajuda">Ajuda</Link></li></ul></div></div>
        <div className="landing-foot-r"><p>Uma conta, produtos independentes.<br />Dados e permissões conforme a assinatura.</p><p>© {new Date().getFullYear()} FlipControl.</p></div>
      </div>
    </footer><AnalyticsConsent appearance="landing" />
  </>;
}

export default function SiteSurface({ home = false, children }: { home?: boolean; children: ReactNode }) {
  const { resolvedTheme } = useTheme();
  const root = useRef<HTMLDivElement>(null);
  const location = useLocation();
  useEffect(() => {
    const element = root.current;
    if (!element) return;
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      entry.target.toggleAttribute('data-in', entry.isIntersecting);
      if (entry.isIntersecting && !entry.target.classList.contains('landing-vig')) observer.unobserve(entry.target);
    }), { threshold: .10 });
    element.querySelectorAll('.landing-rv,.landing-vig').forEach(node => { node.setAttribute('data-armed', ''); observer.observe(node); });
    const motion = new IntersectionObserver(entries => entries.forEach(entry => entry.target.toggleAttribute('data-live', entry.isIntersecting)), { threshold: .05 });
    element.querySelectorAll('.landing-path').forEach(node => motion.observe(node));
    element.setAttribute('data-ready', '');
    // SPA route changes begin at the next page; native hash links keep their anchor behavior.
    if (!home && !location.hash) window.scrollTo({ top: 0, behavior: 'instant' });
    return () => { observer.disconnect(); motion.disconnect(); };
  }, [home, location.pathname]);
  return <div ref={root} className={`landing-root ${home ? '' : 'fc-public'}`} data-theme={resolvedTheme}>
    <a className="landing-skip" href="#inicio-conteudo">Pular para o conteúdo</a>
    <SiteHeader home={home} />{children}<SiteFooter />
  </div>;
}
