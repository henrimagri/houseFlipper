import { FormEvent, useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronUp, Eye, EyeOff, ExternalLink, LayoutTemplate, Monitor, Palette, Save, Smartphone, Tablet } from 'lucide-react';
import api from '@/lib/api';
import { formatPhone } from '@/lib/brFields';
import PageHeader from '@/ui/PageHeader';

type Section = { id: string; type: string; visible: boolean; title: string; body: string };
type Theme = {
  display_name: string; headline: string; bio: string; creci: string; phone: string; whatsapp: string; instagram: string;
  primary_color: string; accent_color: string; background_color: string; surface_color: string; text_color: string;
  font_family: 'sans' | 'serif' | 'modern'; hero_style: 'solid' | 'gradient' | 'minimal';
  card_style: 'soft' | 'elevated' | 'square'; button_style: 'rounded' | 'pill' | 'square';
  footer_text: string; sections: Section[];
};
type Site = { slug: string; status_label: string; live: boolean; public_path: string; views_count: number; theme: Partial<Theme> };

const defaultSections: Section[] = [
  { id: 'hero', type: 'hero', visible: true, title: 'Encontre um imóvel para chamar de seu', body: 'Atendimento próximo e apoio em cada etapa da negociação.' },
  { id: 'featured', type: 'featured', visible: true, title: 'Imóveis em destaque', body: 'Conheça as oportunidades selecionadas para você.' },
  { id: 'about', type: 'about', visible: true, title: 'Atendimento que entende você', body: 'Experiência e segurança para encontrar o imóvel certo.' },
  { id: 'contact', type: 'contact', visible: true, title: 'Vamos conversar?', body: 'Envie uma mensagem e receba atendimento personalizado.' },
];
const initialTheme: Theme = { display_name: '', headline: '', bio: '', creci: '', phone: '', whatsapp: '', instagram: '', primary_color: '#4F46E5', accent_color: '#0F172A', background_color: '#F8FAFC', surface_color: '#FFFFFF', text_color: '#0F172A', font_family: 'sans', hero_style: 'gradient', card_style: 'soft', button_style: 'rounded', footer_text: '', sections: defaultSections };
const templates: { name: string; description: string; swatches: string[]; values: Partial<Theme> }[] = [
  { name: 'Essencial', description: 'Claro, direto e acolhedor.', swatches: ['#4F46E5', '#0F172A', '#F8FAFC'], values: { primary_color: '#4F46E5', accent_color: '#0F172A', background_color: '#F8FAFC', surface_color: '#FFFFFF', text_color: '#0F172A', font_family: 'sans', hero_style: 'gradient', card_style: 'soft', button_style: 'rounded' } },
  { name: 'Premium', description: 'Elegante, sóbrio e sofisticado.', swatches: ['#B58B35', '#172033', '#F5F1E8'], values: { primary_color: '#172033', accent_color: '#B58B35', background_color: '#F5F1E8', surface_color: '#FFFFFF', text_color: '#172033', font_family: 'serif', hero_style: 'solid', card_style: 'elevated', button_style: 'square' } },
  { name: 'Urbano', description: 'Contemporâneo e contrastante.', swatches: ['#06B6D4', '#7C3AED', '#07111F'], values: { primary_color: '#06B6D4', accent_color: '#7C3AED', background_color: '#07111F', surface_color: '#101C2C', text_color: '#F8FAFC', font_family: 'modern', hero_style: 'minimal', card_style: 'square', button_style: 'pill' } },
];
const cleanSlug = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
const cardRadius = (style: Theme['card_style']) => style === 'square' ? '4px' : style === 'elevated' ? '24px' : '16px';

export default function SiteCorretor() {
  const [site, setSite] = useState<Site | null>(null);
  const [error, setError] = useState(''); const [success, setSuccess] = useState(''); const [saving, setSaving] = useState(false);
  const [theme, setTheme] = useState<Theme>(initialTheme); const [slug, setSlug] = useState('');
  const [device, setDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const publicUrl = useMemo(() => site ? `${window.location.origin}/corretor/${slug || site.slug}` : '', [site, slug]);

  async function load() {
    setError('');
    try {
      const { data } = await api.get('/broker-site'); const loaded = data.data as Site;
      setSite(loaded); setSlug(loaded.slug);
      setTheme({ ...initialTheme, ...loaded.theme, sections: loaded.theme.sections?.length ? loaded.theme.sections : defaultSections });
    } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível carregar o site.'); }
  }
  useEffect(() => { void load(); }, []);
  async function save(event: FormEvent) {
    event.preventDefault(); setSaving(true); setError(''); setSuccess('');
    try {
      const { data } = await api.put('/broker-site', { slug, theme }); setSite(data.data); setSlug(data.data.slug);
      setTheme({ ...initialTheme, ...data.data.theme }); setSuccess('Site salvo. A vitrine pública já usa estas configurações.');
    } catch (exception: any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar.'); }
    finally { setSaving(false); }
  }
  async function trial() { setSaving(true); setError(''); try { await api.post('/billing/trial', { product: 'corretor' }); await load(); } catch (exception: any) { setError(exception.response?.data?.message ?? 'Não foi possível iniciar o teste.'); } finally { setSaving(false); } }
  function updateSection(index: number, value: Partial<Section>) { setTheme((current) => ({ ...current, sections: current.sections.map((item, i) => i === index ? { ...item, ...value } : item) })); }
  function moveSection(index: number, direction: -1 | 1) { const target = index + direction; if (target < 0 || target >= theme.sections.length) return; const next = [...theme.sections]; [next[index], next[target]] = [next[target], next[index]]; setTheme({ ...theme, sections: next }); }

  return <div className="space-y-6">
    <PageHeader title="Meu site" subtitle="Crie uma vitrine profissional com sua identidade. Altere cores, estilo, conteúdo e a ordem das seções." />
    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">{error}</div>}
    {success && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{success}</div>}
    {site && <section className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
      <div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">Sua vitrine</p><p className="mt-1 font-semibold">{site.status_label} · {site.views_count} visualizações</p><a href={site.public_path} target="_blank" rel="noreferrer" className="mt-1 inline-flex items-center gap-1 break-all text-sm font-medium text-primary-600 dark:text-primary-400">{publicUrl}<ExternalLink className="h-3.5 w-3.5 shrink-0"/></a></div>
      {!site.live && <button disabled={saving} onClick={trial} className="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white">Ativar teste de 7 dias</button>}
    </section>}
    <section className="space-y-3"><div className="flex items-center gap-2"><LayoutTemplate className="h-5 w-5 text-primary-600"/><h2 className="text-lg font-bold">Modelos prontos</h2></div><p className="text-sm text-slate-600 dark:text-slate-400">Escolha uma base e personalize cada detalhe depois.</p>
      <div className="grid gap-3 md:grid-cols-3">{templates.map((template) => <button type="button" key={template.name} onClick={() => setTheme({ ...theme, ...template.values })} className="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"><div className="mb-4 flex gap-2">{template.swatches.map((color) => <span key={color} className="h-8 w-8 rounded-full border border-white/40 shadow" style={{ backgroundColor: color }}/>)}</div><strong>{template.name}</strong><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{template.description}</p></button>)}</div>
    </section>
    <form onSubmit={save} className="grid gap-5 xl:grid-cols-[minmax(22rem,0.8fr)_minmax(0,1.2fr)]">
      <div className="space-y-5">
        <section className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-2">
          <h2 className="flex items-center gap-2 text-lg font-bold sm:col-span-2"><Palette className="h-5 w-5 text-primary-600"/>Identidade e aparência</h2>
          <label className="text-sm font-medium sm:col-span-2">Endereço personalizado<div className="mt-1 flex overflow-hidden rounded-xl border border-slate-300 bg-slate-50 focus-within:ring-2 focus-within:ring-primary-500 dark:border-slate-600 dark:bg-slate-950"><span className="hidden items-center border-r px-3 text-xs text-slate-500 sm:flex">/corretor/</span><input aria-label="Endereço personalizado" className="min-w-0 flex-1 bg-transparent px-3 py-2.5 outline-none" value={slug} onChange={(e) => setSlug(cleanSlug(e.target.value))}/></div><span className="mt-1 block text-xs font-normal text-slate-500">Espaços e acentos viram hífens automaticamente.</span></label>
          <label className="text-sm font-medium">Nome profissional<input className="input mt-1" value={theme.display_name} onChange={(e) => setTheme({ ...theme, display_name: e.target.value })}/></label>
          <label className="text-sm font-medium">CRECI<input className="input mt-1" maxLength={40} value={theme.creci} onChange={(e) => setTheme({ ...theme, creci: e.target.value.toUpperCase() })}/></label>
          <label className="text-sm font-medium sm:col-span-2">Chamada principal<input className="input mt-1" maxLength={180} value={theme.headline} onChange={(e) => setTheme({ ...theme, headline: e.target.value })}/></label>
          <label className="text-sm font-medium">Telefone<input type="tel" className="input mt-1" value={theme.phone} onChange={(e) => setTheme({ ...theme, phone: formatPhone(e.target.value) })}/></label>
          <label className="text-sm font-medium">WhatsApp<input type="tel" className="input mt-1" value={theme.whatsapp} onChange={(e) => setTheme({ ...theme, whatsapp: formatPhone(e.target.value) })}/></label>
          <label className="text-sm font-medium sm:col-span-2">Instagram<input className="input mt-1" value={theme.instagram} onChange={(e) => setTheme({ ...theme, instagram: e.target.value })}/></label>
          <label className="text-sm font-medium sm:col-span-2">Apresentação<textarea className="input mt-1 min-h-24" maxLength={800} value={theme.bio} onChange={(e) => setTheme({ ...theme, bio: e.target.value })}/></label>
          {([['primary_color','Cor principal'],['accent_color','Cor de destaque'],['background_color','Fundo'],['surface_color','Cards'],['text_color','Texto']] as const).map(([key,label]) => <label key={key} className="text-sm font-medium">{label}<div className="mt-1 flex gap-2"><input type="color" aria-label={label} className="h-11 w-12 cursor-pointer rounded-lg border bg-transparent p-1" value={theme[key]} onChange={(e) => setTheme({ ...theme, [key]: e.target.value.toUpperCase() })}/><input className="input" value={theme[key]} onChange={(e) => setTheme({ ...theme, [key]: e.target.value })}/></div></label>)}
          <label className="text-sm font-medium">Tipografia<select className="input mt-1" value={theme.font_family} onChange={(e) => setTheme({ ...theme, font_family: e.target.value as Theme['font_family'] })}><option value="sans">Clássica</option><option value="modern">Moderna</option><option value="serif">Editorial</option></select></label>
          <label className="text-sm font-medium">Cabeçalho<select className="input mt-1" value={theme.hero_style} onChange={(e) => setTheme({ ...theme, hero_style: e.target.value as Theme['hero_style'] })}><option value="gradient">Degradê</option><option value="solid">Cor sólida</option><option value="minimal">Minimalista</option></select></label>
          <label className="text-sm font-medium">Cards<select className="input mt-1" value={theme.card_style} onChange={(e) => setTheme({ ...theme, card_style: e.target.value as Theme['card_style'] })}><option value="soft">Suaves</option><option value="elevated">Elevados</option><option value="square">Retos</option></select></label>
          <label className="text-sm font-medium">Botões<select className="input mt-1" value={theme.button_style} onChange={(e) => setTheme({ ...theme, button_style: e.target.value as Theme['button_style'] })}><option value="rounded">Arredondados</option><option value="pill">Pílula</option><option value="square">Retos</option></select></label>
          <label className="text-sm font-medium sm:col-span-2">Rodapé<input className="input mt-1" value={theme.footer_text} onChange={(e) => setTheme({ ...theme, footer_text: e.target.value })}/></label>
        </section>
        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><h2 className="text-lg font-bold">Seções do site</h2><p className="text-sm text-slate-500">Edite, esconda e reordene os componentes.</p>{theme.sections.map((section,index) => <div key={section.id} className="rounded-xl border border-slate-200 p-3 dark:border-slate-700"><div className="flex items-center gap-2"><button type="button" aria-label={section.visible?'Ocultar seção':'Exibir seção'} onClick={() => updateSection(index,{visible:!section.visible})} className="rounded-lg border p-2">{section.visible?<Eye className="h-4 w-4"/>:<EyeOff className="h-4 w-4"/>}</button><strong className="flex-1 capitalize">{section.type}</strong><button type="button" aria-label="Mover para cima" onClick={() => moveSection(index,-1)} className="p-2" disabled={index===0}><ChevronUp className="h-4 w-4"/></button><button type="button" aria-label="Mover para baixo" onClick={() => moveSection(index,1)} className="p-2" disabled={index===theme.sections.length-1}><ChevronDown className="h-4 w-4"/></button></div><input aria-label={`Título da seção ${section.type}`} className="input mt-3" value={section.title} onChange={(e)=>updateSection(index,{title:e.target.value})}/><textarea aria-label={`Texto da seção ${section.type}`} className="input mt-2 min-h-20" value={section.body} onChange={(e)=>updateSection(index,{body:e.target.value})}/></div>)}</section>
        <button disabled={saving} className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-3 font-bold text-white disabled:opacity-60"><Save className="h-4 w-4"/>{saving?'Salvando...':'Salvar e publicar alterações'}</button>
      </div>
      <section className="self-start rounded-2xl bg-slate-100 p-4 dark:bg-slate-800 xl:sticky xl:top-24"><div className="mb-3 flex items-center justify-between"><strong>Prévia ao vivo</strong><div className="flex gap-1">{([{v:'desktop',I:Monitor},{v:'tablet',I:Tablet},{v:'mobile',I:Smartphone}] as const).map(({v,I})=><button type="button" aria-label={`Visualização ${v}`} key={v} onClick={()=>setDevice(v)} className={`rounded-lg p-2 ${device===v?'bg-primary-600 text-white':'bg-white text-slate-600 dark:bg-slate-900 dark:text-slate-300'}`}><I className="h-4 w-4"/></button>)}</div></div>
        <div className="mx-auto overflow-hidden border border-slate-300 shadow-xl transition-all" style={{ width: device==='desktop'?'100%':device==='tablet'?720:390, maxWidth:'100%', background:theme.background_color, color:theme.text_color, fontFamily:theme.font_family==='serif'?'Georgia, serif':'system-ui, sans-serif', borderRadius:cardRadius(theme.card_style) }}>
          {theme.sections.filter(s=>s.visible).map((section) => section.type==='hero'?<div key={section.id} className="p-8" style={{color:theme.hero_style==='minimal'?theme.text_color:'#fff', background:theme.hero_style==='minimal'?theme.background_color:theme.hero_style==='gradient'?`linear-gradient(135deg, ${theme.primary_color}, ${theme.accent_color})`:theme.primary_color}}><p className="text-xs font-bold uppercase tracking-widest opacity-75">{theme.display_name||'Seu nome'} {theme.creci&&`· ${theme.creci}`}</p><h2 className="mt-4 text-3xl font-black">{theme.headline||section.title}</h2><p className="mt-3 max-w-xl text-sm opacity-85">{section.body}</p><button type="button" className="mt-5 px-4 py-2 text-sm font-bold text-white" style={{background:theme.accent_color,borderRadius:theme.button_style==='pill'?'999px':theme.button_style==='square'?'4px':'12px'}}>Ver imóveis</button></div>:section.type==='featured'?<div key={section.id} className="p-6"><h3 className="text-xl font-bold">{section.title}</h3><p className="mt-1 text-sm opacity-65">{section.body}</p><div className="mt-4 grid grid-cols-2 gap-3">{[1,2].map(item=><div key={item} className={theme.card_style==='elevated'?'shadow-lg':''} style={{background:theme.surface_color,borderRadius:cardRadius(theme.card_style)}}><div className="h-20 opacity-15" style={{background:theme.primary_color}}/><div className="p-3"><div className="h-2 w-2/3 rounded bg-current opacity-20"/><div className="mt-2 h-2 w-1/2 rounded bg-current opacity-10"/></div></div>)}</div></div>:<div key={section.id} className="p-6" style={{background:section.type==='contact'?theme.surface_color:'transparent'}}><h3 className="text-xl font-bold">{section.title}</h3><p className="mt-2 text-sm opacity-70">{section.type==='about'?(theme.bio||section.body):section.body}</p></div>)}
          <footer className="border-t border-black/10 p-4 text-center text-xs opacity-60">{theme.footer_text||`© ${new Date().getFullYear()} ${theme.display_name||'Corretor'}`}</footer>
        </div>
      </section>
    </form>
  </div>;
}
