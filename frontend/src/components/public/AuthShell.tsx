import type { ReactNode } from 'react';
import { BarChart3, Building2, Check, Hexagon, ShieldCheck, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import ThemeSelector from '@/components/ThemeSelector';

export default function AuthShell({ children, eyebrow, title, description, wide = false }: { children: ReactNode; eyebrow: string; title: string; description: string; wide?: boolean }) {
  return <main className="dark relative min-h-screen overflow-hidden bg-[#050816] text-white">
    <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(circle_at_16%_20%,rgba(124,58,237,.25),transparent_34%),radial-gradient(circle_at_82%_78%,rgba(6,182,212,.16),transparent_32%)]" />
    <div aria-hidden="true" className="absolute inset-0 opacity-25 [background-image:linear-gradient(rgba(255,255,255,.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.04)_1px,transparent_1px)] [background-size:46px_46px] [mask-image:linear-gradient(to_bottom,black,transparent_82%)]" />
    <div className="absolute right-4 top-4 z-20"><ThemeSelector /></div>
    <div className={`relative mx-auto grid min-h-screen w-full ${wide ? 'max-w-[1480px] lg:grid-cols-[.72fr_1.28fr]' : 'max-w-7xl lg:grid-cols-[1.05fr_.95fr]'}`}>
      <section className="relative hidden overflow-hidden border-r border-white/10 px-10 py-12 lg:flex lg:flex-col lg:justify-between xl:px-16">
        <Link to="/" className="inline-flex w-fit items-center gap-3 text-sm font-black"><span className="grid size-10 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-violet-500/25"><Hexagon className="size-5" /></span>FlipControl</Link>
        <div className="max-w-xl py-12"><p className="text-xs font-black uppercase tracking-[.24em] text-cyan-300">{eyebrow}</p><h1 className="mt-5 text-4xl font-black leading-[1.02] tracking-[-.045em] xl:text-6xl">{title}</h1><p className="mt-6 max-w-lg text-base leading-7 text-slate-300">{description}</p><div className="mt-8 grid gap-3 sm:grid-cols-2">{['Conta única', 'Produtos independentes', 'Permissões por assinatura', 'Ambiente seguro'].map(item => <span key={item} className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/[.045] px-3 py-2.5 text-xs font-semibold text-slate-200 backdrop-blur"><Check className="size-3.5 text-cyan-300" />{item}</span>)}</div></div>
        <div className="relative rounded-[2rem] border border-white/10 bg-white/[.055] p-5 shadow-2xl shadow-black/25 backdrop-blur-xl"><div className="flex items-center justify-between"><span className="text-xs font-bold text-slate-300">Visão da operação</span><span className="rounded-full bg-emerald-400/10 px-2 py-1 text-[9px] font-black text-emerald-300">AMBIENTE PROTEGIDO</span></div><div className="mt-4 grid grid-cols-3 gap-3">{[[Building2,'Carteira'],[BarChart3,'Indicadores'],[Sparkles,'Inteligência']].map(([Icon,label]) => { const Item = Icon as typeof Building2; return <div key={String(label)} className="rounded-2xl border border-white/8 bg-slate-950/55 p-3"><Item className="size-4 text-cyan-300"/><p className="mt-4 text-[10px] font-bold text-slate-300">{String(label)}</p><div className="mt-2 h-1.5 rounded-full bg-gradient-to-r from-violet-500 to-cyan-400"/></div>; })}</div><div className="mt-3 flex items-center gap-2 text-[10px] text-slate-400"><ShieldCheck className="size-3.5 text-emerald-300" />Fluxos identificados, sem simular integrações externas.</div></div>
      </section>
      <section className="flex min-w-0 items-center justify-center px-4 py-16 sm:px-8 lg:px-10 xl:px-16"><div className={`w-full ${wide ? 'max-w-4xl' : 'max-w-lg'}`}><Link to="/" className="mb-8 inline-flex items-center gap-2 font-black lg:hidden"><Hexagon className="size-6 text-cyan-300" />FlipControl</Link>{children}</div></section>
    </div>
  </main>;
}
