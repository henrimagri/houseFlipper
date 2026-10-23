import { useEffect, useId, useRef, useState, type CSSProperties } from 'react';
import { Link } from 'react-router-dom';
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUpRight, Building2, Check, ChevronDown, CircleDot, Hammer, Hexagon, Home as HomeIcon, Menu, Moon, MousePointer2, RotateCcw, Sun, X } from 'lucide-react';
import { useTheme } from '@/components/ThemeProvider';
import { getAuthToken } from '@/lib/authToken';
import { useSession } from '@/lib/session';
import AnalyticsConsent from '@/components/public/AnalyticsConsent';
import StippleOrb from '@/components/public/StippleOrb';
import './stipple-landing.css';

const products = [
  { title: 'Corretor', icon: HomeIcon, description: 'Da primeira conversa à carteira publicada.', role: 'Sua operação, organizada', image: 'corretor-interior', alt: 'Sala contemporânea com varanda, imagem ilustrativa gerada por IA', to: '/solucoes/corretor', points: ['Captações e imóveis autorizados', 'Contatos, propostas e presença digital', 'Um lugar para acompanhar cada oportunidade'] },
  { title: 'Imobiliária', icon: Building2, description: 'Contexto para a equipe. Clareza para a gestão.', role: 'O próximo passo de cada negócio', image: 'imobiliaria-building', alt: 'Edifício residencial com varandas e jardins, imagem ilustrativa gerada por IA', to: '/solucoes/imobiliaria', points: ['Atendimentos conectados ao funil', 'Equipes e filiais com permissões próprias', 'Histórico para dar continuidade às conversas'] },
  { title: 'House Flipper', icon: Hammer, description: 'Enxergue o projeto antes de transformar o imóvel.', role: 'Da análise à transformação', image: 'flipper-renovation', alt: 'Apartamento em reforma com amostras de materiais, imagem ilustrativa gerada por IA', to: '/solucoes/house-flipper', points: ['Oportunidades e análise de viabilidade', 'Projetos, obras e acompanhamento de custos', 'Simulações visuais e editor de ambientes'] },
];
const nav = [{ href: '#produtos', label: 'Soluções' }, { href: '#como-funciona', label: 'Como funciona' }, { href: '#duvidas', label: 'Dúvidas' }];
const brl = (value: number) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });

function Begin({ children = 'Começar agora' }: { children?: string }) {
  return <Link className="landing-btn" to="/cadastro">{children}<ArrowUpRight size={15} aria-hidden="true" /></Link>;
}

function ConnectedFlow() {
  const [active, setActive] = useState(0);
  const element = useRef<HTMLDivElement>(null);
  const visible = useRef(true);
  const segments = ['Carteira', 'Atendimento', 'Projetos', 'Análises'];
  useEffect(() => {
    const media = window.matchMedia('(prefers-reduced-motion: reduce)');
    const observer = new IntersectionObserver(([entry]) => { visible.current = entry.isIntersecting; });
    if (element.current) observer.observe(element.current);
    const timer = window.setInterval(() => { if (!media.matches && visible.current && !document.hidden) setActive(value => (value + 1) % 4); }, 2600);
    return () => { clearInterval(timer); observer.disconnect(); };
  }, []);
  return <div ref={element} className="landing-flow" aria-label="Demonstração de fluxos conectados">
    <div className="landing-pool"><ul>{products.map(({ title, icon: Icon }, i) => <li key={title} data-hot={active % 3 === i ? '' : undefined}><span className="landing-product-icon"><Icon size={17} /></span><div><b>{title}</b><small>Fluxo independente</small></div></li>)}</ul><span className="landing-pool-more">Uma conta. Três produtos.</span></div>
    <svg className="landing-wire" viewBox="0 0 90 150" aria-hidden="true"><path d="M0 28 C55 28 30 75 90 75 M0 75 H90 M0 122 C55 122 30 75 90 75" /><path className="landing-run" d="M0 28 C55 28 30 75 90 75 M0 75 H90 M0 122 C55 122 30 75 90 75" /></svg>
    <div className="landing-node"><Hexagon size={22} /></div>
    <svg className="landing-wire landing-wire1" viewBox="0 0 70 4" aria-hidden="true"><path d="M0 2H70" /><path className="landing-run" d="M0 2H70" /></svg>
    <div className="landing-ring">
      <svg viewBox="0 0 300 300" aria-hidden="true"><circle cx="150" cy="150" r="98" fill="none" stroke="var(--landing-line)" strokeWidth="24" />{segments.map((_, i) => <circle key={i} cx="150" cy="150" r="98" fill="none" stroke={['var(--landing-mint)', 'var(--landing-cream)', 'var(--landing-sky)', 'var(--landing-accent)'][i]} strokeWidth="24" strokeDasharray="137 616" transform={`rotate(${i * 90 - 90} 150 150)`} opacity={i === active ? 1 : .45} />)}</svg>
      <div className="landing-ring-c"><span className="landing-mono">Contexto conectado</span><strong>{segments[active]}</strong><span className="landing-mono">Controle em cada etapa</span></div>
      {segments.map((name, i) => <button type="button" className="landing-tag" data-on={active === i ? '' : undefined} aria-pressed={active === i} onClick={() => setActive(i)} key={name} style={{ left: `${[78, 83, 27, 16][i]}%`, top: `${[15, 81, 91, 25][i]}%` }}><CircleDot size={12} />{name}</button>)}
    </div>
  </div>;
}

function PlanningPanels() {
  const [price, setPrice] = useState(250000);
  const [fee, setFee] = useState(3);
  const [purchase, setPurchase] = useState(400000);
  const [works, setWorks] = useState(20);
  return <section id="planejamento" className="landing-sec landing-paths" aria-labelledby="planning-heading"><div className="landing-wrap">
    <h2 id="planning-heading" className="landing-h2 landing-rv">Negocie com contexto.<br /><span className="landing-muted">Transforme com planejamento.</span></h2>
    <div className="landing-paths-grid">
      <article className="landing-path landing-rv"><h3>Para quem negocia</h3><p>Organize imóveis, conversas e propostas. Explore um exemplo de cálculo e acompanhe a operação no produto adequado.</p><Link className="landing-link" to="/solucoes/imobiliaria">Conhecer o CRM <ArrowUpRight size={14} /></Link>
        <div className="landing-panel"><ul className="landing-panel-ul"><li>Carteira no mesmo contexto</li><li>Histórico de negociação</li><li>Próximos passos visíveis</li></ul><svg className="landing-art landing-growth" viewBox="0 0 540 338" aria-hidden="true"><defs><linearGradient id="landing-bars" x2="0" y2="1"><stop stopColor="var(--landing-accent)" stopOpacity=".8" /><stop offset="1" stopColor="var(--landing-accent)" stopOpacity=".18" /></linearGradient></defs>{Array.from({ length: 15 }, (_, i) => <rect style={{ '--i': i } as CSSProperties} key={i} x={26 + i * 34} y={285 - (i + 1) * (7 + fee * 1.2)} width="23" height={(i + 1) * (7 + fee * 1.2)} rx="3" fill="url(#landing-bars)" />)}<path d="M24 288H518" stroke="var(--landing-line2)" strokeDasharray="3 4" /></svg><span className="landing-panel-cap">Uma simulação, sem receita presumida</span></div>
        <div className="landing-ctrl"><span className="landing-lab" id="sale-price-label">Valor de venda</span><div role="group" aria-labelledby="sale-price-label" className="landing-seg-btns">{[150000, 250000, 1000000].map(value => <button type="button" aria-pressed={price === value} key={value} onClick={() => setPrice(value)}>{value === 1000000 ? 'R$ 1 mi' : `R$ ${value / 1000} mil`}</button>)}</div><label htmlFor="commission-rate">Comissão</label><div className="landing-range"><input id="commission-rate" type="range" min="1" max="6" step=".5" value={fee} onChange={e => setFee(Number(e.target.value))} style={{ '--p': `${(fee - 1) / 5 * 100}%` } as CSSProperties} /><output htmlFor="commission-rate">{fee.toLocaleString('pt-BR')}%</output></div><div className="landing-out" aria-live="polite"><div><strong key={`${price}-${fee}`}>{brl(price * fee / 100)}</strong><small>Comissão bruta ilustrativa, antes de taxas e divisão.</small></div><span className="landing-x">Simulação</span></div></div>
      </article>
      <article className="landing-path landing-rv" data-d="1"><h3>Para quem transforma</h3><p>Comece pela aquisição, planeje a reforma e acompanhe os custos. Cada hipótese deve ficar clara antes de uma decisão.</p><Link className="landing-link" to="/solucoes/house-flipper">Explorar House Flipper <ArrowUpRight size={14} /></Link>
        <div className="landing-panel landing-capital"><ul className="landing-panel-ul"><li>Análise de viabilidade</li><li>Custos por projeto</li><li>Simulação de ambientes</li></ul><div className="landing-panel-badge"><strong>{brl(purchase * works / 100)}</strong><span className="landing-mono">Reforma simulada</span></div><svg className="landing-art" viewBox="0 0 540 338" aria-hidden="true">{Array.from({ length: 25 }, (_, i) => <g style={{ '--i': i } as CSSProperties} key={i} transform={`translate(${270 + (i % 5 - 2) * 34 - Math.floor(i / 5) * 10},${145 + Math.floor(i / 5) * 19 + (i % 5) * 7})`}><path d="M0 0L29 -13L58 0L29 14Z" fill={i < Math.round(works / 2) ? 'var(--landing-accent)' : 'var(--landing-mint)'} opacity=".8" /><path d="M0 0V9L29 23L58 9V0L29 14Z" fill="var(--landing-soft)" opacity=".25" /></g>)}</svg><span className="landing-panel-cap">Aquisição + reforma, com premissas explícitas</span></div>
        <div className="landing-ctrl"><span className="landing-lab" id="purchase-label">Aquisição</span><div role="group" aria-labelledby="purchase-label" className="landing-seg-btns">{[200000, 400000, 800000].map(value => <button type="button" aria-pressed={purchase === value} key={value} onClick={() => setPurchase(value)}>R$ {value / 1000} mil</button>)}</div><label htmlFor="works-budget">Reforma</label><div className="landing-range"><input id="works-budget" type="range" min="5" max="40" value={works} onChange={e => setWorks(Number(e.target.value))} style={{ '--p': `${(works - 5) / 35 * 100}%` } as CSSProperties} /><output htmlFor="works-budget">{works}%</output></div><div className="landing-out" aria-live="polite"><div><strong key={`${purchase}-${works}`}>{brl(purchase * (1 + works / 100))}</strong><small>Capital ilustrativo. Não inclui tributos, taxas ou financiamento.</small></div><span className="landing-x">Sem previsão de retorno</span></div></div>
      </article>
    </div>
  </div></section>;
}

function Steps() {
  const [replays, setReplays] = useState([0, 0, 0]);
  return <section id="como-funciona" className="landing-sec landing-steps" aria-labelledby="steps-heading"><div className="landing-wrap"><h2 id="steps-heading" className="landing-h2 landing-rv">Do primeiro acesso<br /><span className="landing-muted">à sua próxima decisão.</span></h2><div className="landing-steps-grid">
    {['Crie sua conta', 'Escolha seu produto', 'Conduza sua operação'].map((title, i) => <article className="landing-step landing-rv" data-d={i} key={title}><h3><span className="landing-muted">0{i + 1} / </span>{title}</h3><button className="landing-vig" type="button" aria-label={`Reproduzir animação: ${title}`} onClick={() => setReplays(previous => previous.map((value, index) => index === i ? value + 1 : value))}>
      <div className="landing-vig-content" key={replays[i]}><span className="landing-guide" /><span className="landing-guide-h" />
        {i === 0 ? <><div className="landing-card landing-q"><span className="landing-mono">Sua próxima etapa</span><div className="landing-field"><span className="landing-type">Organizar minha operação</span><span className="landing-caret" /></div></div><span className="landing-join">Começar no FlipControl <i><Check size={10} /></i></span><MousePointer2 className="landing-cursor landing-c1" style={{ '--cx': '68%', '--cy': '69%' } as CSSProperties} /></> : i === 1 ? <><span className="landing-mono landing-meta">Produtos independentes</span><div className="landing-stack"><div className="landing-card" /><div className="landing-card" /><div className="landing-card" /><div className="landing-card landing-app"><Building2 size={24} /><div><small>Seu produto</small><b>Imobiliária</b></div></div></div><span className="landing-stamp">Seu espaço</span><MousePointer2 className="landing-cursor landing-c2" style={{ '--cx': '62%', '--cy': '59%' } as CSSProperties} /></> : <><span className="landing-mono landing-meta">Próxima oportunidade</span><div className="landing-card landing-demo-deal"><span className="landing-product-icon"><HomeIcon size={20} /></span><div><b>Visita ao imóvel</b><small>Próximo passo definido</small></div></div><span className="landing-back">Acompanhar</span><div className="landing-mini-funnel"><span /><span /><span /></div><span className="landing-toast">Tudo no mesmo contexto <Check size={11} /></span><MousePointer2 className="landing-cursor landing-c3" style={{ '--cx': '70%', '--cy': '31%' } as CSSProperties} /></>}
      </div><span className="landing-vig-replay"><RotateCcw size={11} />Reproduzir</span></button><p>{['Cadastre-se e confirme seu e-mail para acessar sua conta.', 'Corretor, Imobiliária ou House Flipper: cada produto tem seus planos e permissões.', 'Organize sua carteira, acompanhe negócios ou planeje projetos no produto contratado.'][i]}</p></article>)}
  </div></div></section>;
}

function Products() {
  const track = useRef<HTMLDivElement>(null);
  const [position, setPosition] = useState(0);
  const move = (direction: number) => {
    const element = track.current;
    if (!element) return;
    const item = element.querySelector<HTMLElement>('article');
    element.scrollBy({ left: direction * ((item?.offsetWidth ?? 350) + 22), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  };
  return <section id="produtos" className="landing-sec landing-stories" aria-labelledby="products-heading"><div className="landing-wrap"><div className="landing-stories-head"><h2 id="products-heading" className="landing-h2">Três formas de trabalhar.<br /><span className="landing-muted">Um ponto de partida.</span></h2><div className="landing-carousel-actions"><button type="button" className="landing-icon-btn" aria-label="Produto anterior" onClick={() => move(-1)} disabled={position <= 0}><ArrowLeft size={17} /></button><button type="button" className="landing-icon-btn" aria-label="Próximo produto" onClick={() => move(1)} disabled={position >= 1}><ArrowRight size={17} /></button></div></div><div className="landing-track" ref={track} onScroll={() => { const el = track.current; if (el) setPosition(el.scrollLeft / Math.max(1, el.scrollWidth - el.clientWidth)); }}>
    {products.map(({ title, icon: Icon, image, alt, to, description, role, points }) => <article className="landing-story" key={title}><div className="landing-story-h"><h3>{title}</h3><Icon size={17} /></div><p className="landing-story-reach">{description}</p><div className="landing-photo"><img src={`/assets/landing/${image}.webp`} width="1536" height="1024" alt={alt} loading="lazy" /><div className="landing-spark"><div><span className="landing-mono">Seu próximo passo</span><b>{role}</b></div><svg viewBox="0 0 400 50" aria-hidden="true"><path className="landing-spark-line" pathLength="1" d="M0 42C40 44 30 33 70 35S100 13 140 25S190 19 220 21S280 2 310 13S370 2 400 5" fill="none" stroke="var(--landing-accent)" strokeWidth="2" /></svg></div></div><ul className="landing-story-points">{points.map(point => <li key={point}><Check size={13} />{point}</li>)}</ul><Link className="landing-story-link" to={to}>Conhecer {title}<ArrowUpRight size={15} /></Link></article>)}
  </div><p className="landing-photo-note">Cenas ilustrativas criadas por IA, sem vínculo com imóveis anunciados. Cada produto tem seus próprios módulos e assinatura.</p></div></section>;
}

const questions = [
  ['Sobre a plataforma', [
    ['O que é o FlipControl?', 'Uma plataforma imobiliária com produtos próprios para corretores, imobiliárias e quem trabalha com transformação de imóveis.'],
    ['Uma assinatura libera todos os produtos?', 'Não. Corretor, Imobiliária e House Flipper possuem planos e acessos independentes. Você pode reuni-los na mesma conta.'],
    ['Posso conhecer antes de contratar?', 'Consulte a página de planos para conferir o teste de 7 dias e as condições de cada produto.'],
    ['Onde vejo os valores?', 'Os valores e períodos disponíveis ficam na página de planos. Confira as condições antes de contratar.'],
  ]],
  ['Sobre sua operação', [
    ['Preciso confirmar meu e-mail?', 'Sim. A confirmação faz parte do acesso à conta e não é dispensada por links do site.'],
    ['A IA substitui minha decisão?', 'Não. A IA apoia a pesquisa e a organização com o contexto e as fontes disponíveis. Revise análises e hipóteses antes de decidir.'],
    ['As simulações são valores de mercado?', 'Não. Os controles desta página usam valores ilustrativos e cálculos simples. Eles não representam avaliações, receitas ou retornos reais.'],
    ['Como escolho o produto ideal?', 'Compare as soluções ou solicite uma demonstração para entender quais módulos atendem à sua rotina.'],
  ]],
] as const;
function Questions() {
  const [open, setOpen] = useState<string | null>(null);
  const id = useId();
  return <section id="duvidas" className="landing-sec landing-faq" aria-labelledby="faq-heading"><div className="landing-wrap"><h2 id="faq-heading" className="landing-h2 landing-rv">Perguntas, respondidas.</h2><div className="landing-faq-grid">{questions.map(([title, items], column) => <div className="landing-rv" key={title} data-d={column + 1}><h3>{title}</h3>{items.map(([question, answer], row) => { const key = `${column}-${row}`; const expanded = key === open; return <div className="landing-qa" key={question} data-open={expanded ? '' : undefined}><button type="button" aria-expanded={expanded} aria-controls={`${id}-${key}`} id={`${id}-button-${key}`} onClick={() => setOpen(expanded ? null : key)}>{question}<ChevronDown size={15} /></button><div className="landing-ans" id={`${id}-${key}`} role="region" aria-labelledby={`${id}-button-${key}`} aria-hidden={!expanded}><div><p>{answer}</p></div></div></div>; })}</div>)}</div></div></section>;
}

export default function StippleLanding() {
  const root = useRef<HTMLDivElement>(null), content = useRef<HTMLDivElement>(null), menuRoot = useRef<HTMLDivElement>(null), menuButton = useRef<HTMLButtonElement>(null);
  const { mode, setMode } = useTheme();
  const [systemDark, setSystemDark] = useState(() => window.matchMedia('(prefers-color-scheme: dark)').matches);
  const dark = mode === 'dark' || (mode === 'system' && systemDark);
  const [menu, setMenu] = useState(false), [stuck, setStuck] = useState(false);
  const session = useSession();
  const accountHref = session.requiresEmailVerification ? '/confirmar-email' : session.products.length ? '/app/inicio' : '/app/produtos';
  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const update = () => setSystemDark(media.matches);
    media.addEventListener('change', update);
    const scroll = () => setStuck(window.scrollY > 12);
    scroll(); window.addEventListener('scroll', scroll, { passive: true });
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      entry.target.toggleAttribute('data-in', entry.isIntersecting);
      if (entry.isIntersecting && !entry.target.classList.contains('landing-vig')) observer.unobserve(entry.target);
    }), { threshold: .10 });
    root.current?.querySelectorAll('.landing-rv,.landing-vig').forEach(element => { element.setAttribute('data-armed', ''); observer.observe(element); });
    const motionObserver = new IntersectionObserver(entries => entries.forEach(entry => entry.target.toggleAttribute('data-live', entry.isIntersecting)), { threshold: .05 });
    root.current?.querySelectorAll('.landing-path').forEach(element => motionObserver.observe(element));
    root.current?.setAttribute('data-ready', '');
    return () => { media.removeEventListener('change', update); window.removeEventListener('scroll', scroll); observer.disconnect(); motionObserver.disconnect(); };
  }, []);
  useEffect(() => {
    if (!menu) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden'; content.current?.setAttribute('inert', '');
    menuRoot.current?.querySelector<HTMLButtonElement>('button')?.focus();
    const key = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setMenu(false);
      if (event.key !== 'Tab') return;
      const elements = Array.from(menuRoot.current?.querySelectorAll<HTMLElement>('a,button') ?? []);
      const first = elements[0], last = elements[elements.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    };
    const resize = () => { if (window.innerWidth > 960) setMenu(false); };
    document.addEventListener('keydown', key); window.addEventListener('resize', resize);
    return () => { document.body.style.overflow = previousOverflow; content.current?.removeAttribute('inert'); document.removeEventListener('keydown', key); window.removeEventListener('resize', resize); menuButton.current?.focus(); };
  }, [menu]);
  const themeButton = <button type="button" className="landing-icon-btn" aria-label={dark ? 'Ativar tema claro' : 'Ativar tema escuro'} onClick={() => setMode(dark ? 'light' : 'dark')}>{dark ? <Sun size={17} /> : <Moon size={17} />}</button>;

  return <div ref={root} className="landing-root" data-theme={dark ? 'dark' : 'light'} style={{ '--landing-h': '100svh', '--landing-accent': '#1aa59a', '--landing-button': '#123b3a', '--landing-on-button': '#ffffff', '--landing-mint': '#d2efe9', '--landing-cream': '#efeccd', '--landing-sky': '#d3e6f3', '--landing-mono': 'ui-monospace, "SF Mono", Menlo, Consolas, monospace', fontFamily: 'Inter, "Inter Tight", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif' } as CSSProperties}>
    <div ref={content}><a className="landing-skip" href="#inicio-conteudo">Pular para o conteúdo</a>
      <header className="landing-nav" data-stuck={stuck ? '' : undefined}><div className="landing-wrap"><Link className="landing-brand" to="/" aria-label="FlipControl, início"><Hexagon size={23} strokeWidth={1.6} />FlipControl</Link><nav className="landing-links" aria-label="Navegação principal">{nav.map(item => <a href={item.href} key={item.href}>{item.label}</a>)}</nav><div className="landing-navr">{themeButton}<Link className="landing-login" to={getAuthToken() ? accountHref : '/entrar'}>{getAuthToken() ? 'Minha conta' : 'Entrar'}</Link><Link className="landing-btn landing-pill" to="/cadastro">Começar agora <ArrowUpRight size={14} /></Link><button ref={menuButton} className="landing-icon-btn landing-burger" type="button" aria-label="Abrir menu" aria-expanded={menu} aria-controls="landing-mobile-menu" onClick={() => setMenu(true)}><Menu size={20} /></button></div></div></header>
      <main id="inicio-conteudo">
        <section className="landing-hero" aria-labelledby="landing-heading"><div className="landing-wrap landing-hero-grid"><div className="landing-hero-copy"><h1 className="landing-h1" id="landing-heading"><span className="landing-muted">Veja o potencial.</span><span>Faça acontecer.</span></h1><p className="landing-hero-text">Imóveis, pessoas e projetos no mesmo contexto. Conheça uma nova forma de trabalhar com o mercado imobiliário.</p><div className="landing-hero-cta"><Begin /><a className="landing-link" href="#produtos">Explore as soluções <ArrowDown size={14} /></a></div><div className="landing-faces landing-hero-proof"><span className="landing-role-icons" aria-hidden="true">{products.map(({ title, icon: Icon }) => <i key={title}><Icon size={13} /></i>)}</span><span>Para quem move o mercado.</span></div></div><StippleOrb dark={dark} /></div><div className="landing-wrap landing-backers"><span className="landing-mono">Uma plataforma. Seu jeito de trabalhar.</span><ul>{products.map(({ title, icon: Icon, to }) => <li key={title}><Link to={to}><Icon size={17} />{title}</Link></li>)}</ul></div></section>
        <section className="landing-sec landing-thesis"><div className="landing-wrap landing-thesis-grid"><div className="landing-rv"><h2>Boas oportunidades precisam de visão. <span className="landing-muted">Bons resultados começam com uma operação organizada.</span></h2><Link className="landing-btn" to="/comparar-produtos">Encontre seu produto <ArrowUpRight size={15} /></Link></div><div className="landing-rv" data-d="1"><ConnectedFlow /></div></div></section>
        <PlanningPanels /><Steps /><Products />
        <section className="landing-sec landing-cta" onPointerMove={event => { const bounds = event.currentTarget.getBoundingClientRect(); event.currentTarget.style.setProperty('--mx', `${event.clientX - bounds.left}px`); event.currentTarget.style.setProperty('--my', `${event.clientY - bounds.top}px`); event.currentTarget.style.setProperty('--glow', '1'); }} onPointerLeave={event => event.currentTarget.style.setProperty('--glow', '0')}><div className="landing-wrap landing-rv"><h2>O próximo imóvel.<br /><span className="landing-muted">A próxima conversa.</span><br />Uma nova possibilidade.</h2><Begin children="Comece pelo seu próximo passo" /></div></section>
        <Questions />
      </main>
      <footer className="landing-sec landing-foot"><p className="landing-foot-tag landing-rv">FlipControl. Clareza para enxergar.<br /><span className="landing-muted">Controle para transformar.</span></p><StippleOrb dark={dark} horizon /><div className="landing-wrap landing-foot-row"><div className="landing-foot-cols"><div><h4>Explore</h4><ul><li><a href="#produtos">Soluções</a></li><li><a href="#como-funciona">Como funciona</a></li><li><Link to="/precos">Planos</Link></li><li><Link to="/contato">Contato</Link></li></ul></div><div><h4>Saiba mais</h4><ul><li><Link to="/privacidade">Privacidade</Link></li><li><Link to="/termos">Termos de uso</Link></li><li><Link to="/ajuda">Ajuda</Link></li></ul></div></div><div className="landing-foot-r"><p>Uma conta, produtos independentes.<br />Dados e permissões conforme a assinatura.</p><p>© {new Date().getFullYear()} FlipControl.</p></div></div></footer><AnalyticsConsent appearance="landing" />
    </div>
    {menu && <div id="landing-mobile-menu" className="landing-sheet" role="dialog" aria-modal="true" aria-label="Menu de navegação" ref={menuRoot}><div className="landing-sheet-top"><span className="landing-brand"><Hexagon size={23} />FlipControl</span><button className="landing-icon-btn" type="button" aria-label="Fechar menu" onClick={() => setMenu(false)}><X size={20} /></button></div><ul>{nav.map(item => <li key={item.href}><a href={item.href} onClick={() => setMenu(false)}>{item.label}<ArrowUpRight size={20} /></a></li>)}<li><Link to="/precos">Planos<ArrowUpRight size={20} /></Link></li><li><Link to={getAuthToken() ? accountHref : '/entrar'}>{getAuthToken() ? 'Minha conta' : 'Entrar'}<ArrowUpRight size={20} /></Link></li></ul><Begin /></div>}
  </div>;
}
