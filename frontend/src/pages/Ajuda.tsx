import { Bot, Briefcase, CircleDollarSign, FileQuestion, Home, Lightbulb } from 'lucide-react';
import { Link } from 'react-router-dom';

const guides = [
  { icon: Home, title: 'Cadastrar uma oportunidade', text: 'Registre o imóvel, endereço, valores e fotos para começar a análise.', action: 'Abrir oportunidades', to: '/leads' },
  { icon: Bot, title: 'Avaliar com a IA', text: 'Compare o imóvel com dados disponíveis. A estimativa usa regras e comparáveis e deve ser revisada.', action: 'Abrir IA inteligente', to: '/ia-inteligente' },
  { icon: Briefcase, title: 'Criar e acompanhar um projeto', text: 'Converta uma análise financeira ou cadastre um projeto manual ligado a um imóvel.', action: 'Abrir projetos', to: '/projetos' },
  { icon: CircleDollarSign, title: 'Controlar despesas', text: 'Lance gastos por projeto e etapa, acompanhe pagos, pendentes, vencidos e cancelados.', action: 'Abrir despesas', to: '/despesas' },
];

export default function Ajuda() {
  return <div className="space-y-6 max-w-6xl mx-auto">
    <section className="rounded-3xl bg-slate-950 text-white p-6 sm:p-9"><div className="w-12 h-12 rounded-2xl bg-white/10 grid place-items-center"><Lightbulb className="w-6 h-6 text-amber-300" /></div><h1 className="text-3xl font-black mt-5">Central de ajuda</h1><p className="text-slate-300 mt-2 max-w-2xl">Atalhos para os fluxos principais do HouseFlipper e orientações sobre o que cada área registra.</p></section>
    <section className="grid md:grid-cols-2 gap-4">{guides.map(({ icon: Icon, title, text, action, to }) => <article key={to} className="rounded-2xl border bg-white dark:bg-slate-900 p-5"><div className="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="w-5 h-5" /></div><h2 className="font-black text-lg mt-4">{title}</h2><p className="text-sm text-gray-500 dark:text-slate-400 mt-2">{text}</p><Link to={to} className="inline-flex mt-4 text-sm font-bold text-primary-600 dark:text-primary-400">{action} →</Link></article>)}</section>
    <section className="rounded-2xl border bg-white dark:bg-slate-900 p-5 flex gap-4"><FileQuestion className="w-6 h-6 text-primary-600 dark:text-primary-400 shrink-0" /><div><h2 className="font-bold">Configuração da conta</h2><p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Tema, integrações disponíveis e preferências ficam em Configurações.</p><Link to="/configuracoes" className="inline-flex mt-3 text-sm font-bold text-primary-600 dark:text-primary-400">Abrir configurações →</Link></div></section>
  </div>;
}
