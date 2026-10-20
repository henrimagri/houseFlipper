import { useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';

const previews = {
  '/funcionalidades': { eyebrow: 'Visão geral', text: 'Conheça os módulos disponíveis e os limites de cada integração.' },
  '/solucoes/corretor': { eyebrow: 'Produto', text: 'Captações, carteira, anúncios autorizados e CRM individual.' },
  '/solucoes/imobiliaria': { eyebrow: 'Produto', text: 'Imóveis, CRM, equipes, filiais e operação comercial.' },
  '/solucoes/house-flipper': { eyebrow: 'Produto', text: 'Oportunidades, viabilidade, projetos, obras e visualização 3D.' },
} as const;

export default function SafeLinkPreview({ to, children, className = '' }: { to: keyof typeof previews; children: ReactNode; className?: string }) {
  const [open, setOpen] = useState(false); const preview = previews[to];
  return <span className="relative inline-flex" onMouseEnter={() => setOpen(true)} onMouseLeave={() => setOpen(false)} onFocus={() => setOpen(true)} onBlur={() => setOpen(false)}><Link to={to} className={className}>{children}</Link>{open && <span role="tooltip" className="absolute bottom-full left-0 z-20 mb-2 hidden w-64 rounded-xl border bg-white p-3 text-left shadow-xl sm:block dark:border-slate-700 dark:bg-slate-900"><span className="block text-[10px] font-black uppercase tracking-wider text-primary-600">{preview.eyebrow}</span><span className="mt-1 block text-xs font-normal leading-5 text-slate-600 dark:text-slate-300">{preview.text}</span></span>}</span>;
}
