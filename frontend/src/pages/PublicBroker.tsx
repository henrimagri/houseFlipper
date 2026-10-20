import { FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import axios from 'axios';
import { ArrowRight, Building2, Instagram, MessageCircle, Phone } from 'lucide-react';

const publicApi = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } });

type Listing = {
  slug: string;
  title: string;
  asking_price_cents?: number | null;
  city?: string;
  neighborhood?: string;
  photos?: { id: number; url: string }[];
  path: string;
};
type Section = { id: string; type: string; visible: boolean; title: string; body: string };
type BrokerTheme = { display_name?: string; headline?: string; bio?: string; creci?: string; phone?: string; whatsapp?: string; instagram?: string; primary_color?: string; accent_color?: string; background_color?: string; surface_color?: string; text_color?: string; font_family?: string; hero_style?: string; card_style?: string; button_style?: string; footer_text?: string; sections?: Section[] };
type Broker = { slug: string; theme: BrokerTheme; listings: Listing[] };

const money = (cents?: number | null) => cents == null ? 'Sob consulta' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function PublicBroker() {
  const { slug } = useParams();
  const [broker, setBroker] = useState<Broker | null>(null);
  const [missing, setMissing] = useState(false);
  const [query, setQuery] = useState('');

  useEffect(() => {
    if (!slug) return;
    publicApi.get(`/public/brokers/${slug}`).then(({ data }) => setBroker(data.data)).catch(() => setMissing(true));
  }, [slug]);

  if (missing) {
    return <div className="min-h-screen grid place-items-center bg-slate-50 text-slate-600 p-8">Esta página pública não está disponível.</div>;
  }
  if (!broker) return <div className="min-h-screen grid place-items-center">Carregando...</div>;
  const theme = broker.theme ?? {};
  const color = theme.primary_color || '#4F46E5';
  const accent = theme.accent_color || '#0F172A';
  const background = theme.background_color || '#F8FAFC';
  const surface = theme.surface_color || '#FFFFFF';
  const textColor = theme.text_color || '#0F172A';
  const buttonRadius = theme.button_style === 'pill' ? '999px' : theme.button_style === 'square' ? '4px' : '12px';
  const cardRadius = theme.card_style === 'square' ? '4px' : theme.card_style === 'elevated' ? '24px' : '16px';
  const defaultSections: Section[] = [
    { id: 'hero', type: 'hero', visible: true, title: 'Encontre seu próximo imóvel', body: 'Imóveis selecionados e atendimento próximo.' },
    { id: 'featured', type: 'featured', visible: true, title: 'Imóveis em destaque', body: 'Conheça as oportunidades disponíveis.' },
    { id: 'about', type: 'about', visible: true, title: 'Atendimento que entende você', body: '' },
    { id: 'contact', type: 'contact', visible: true, title: 'Vamos conversar?', body: 'Entre em contato para receber atendimento personalizado.' },
  ];
  const visibleSections = (theme.sections?.length ? theme.sections : defaultSections).filter((section) => section.visible);
  const listings = broker.listings.filter((listing) => [listing.title, listing.city, listing.neighborhood].filter(Boolean).join(' ').toLowerCase().includes(query.toLowerCase()));
  const fontFamily = theme.font_family === 'serif' ? 'Georgia, serif' : 'system-ui, sans-serif';

  return (
    <div className="min-h-screen" style={{ background, color: textColor, fontFamily }}>
      <nav className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5"><div><strong className="text-lg">{theme.display_name || 'Corretor'}</strong>{theme.creci && <span className="ml-2 text-xs opacity-60">CRECI {theme.creci}</span>}</div><a href="#contato" className="px-4 py-2 text-sm font-bold text-white" style={{ background: accent, borderRadius: buttonRadius }}>Falar comigo</a></nav>
      {visibleSections.map((section) => {
        if (section.type === 'hero') return <header key={section.id} className="px-6 py-20" style={{ color: theme.hero_style === 'minimal' ? textColor : '#fff', background: theme.hero_style === 'minimal' ? background : theme.hero_style === 'gradient' ? `linear-gradient(135deg, ${color}, ${accent})` : color }}><div className="mx-auto max-w-6xl"><p className="text-xs font-bold uppercase tracking-[0.25em] opacity-75">{theme.display_name || 'Consultoria imobiliária'}</p><h1 className="mt-5 max-w-3xl text-4xl font-black leading-tight md:text-6xl">{theme.headline || section.title}</h1><p className="mt-5 max-w-2xl text-lg opacity-80">{section.body}</p><a href="#imoveis" className="mt-8 inline-flex items-center gap-2 px-5 py-3 font-bold text-white" style={{ background: accent, borderRadius: buttonRadius }}>Ver imóveis <ArrowRight className="h-4 w-4"/></a></div></header>;
        if (section.type === 'featured') return <section id="imoveis" key={section.id} className="mx-auto max-w-6xl px-6 py-16"><div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><h2 className="text-3xl font-black">{section.title}</h2><p className="mt-2 opacity-65">{section.body}</p></div><input aria-label="Buscar imóveis" value={query} onChange={(e)=>setQuery(e.target.value)} placeholder="Busque por bairro ou cidade" className="w-full rounded-xl border border-black/10 bg-transparent px-4 py-3 md:max-w-sm"/></div><div className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">{listings.length === 0 && <p className="col-span-full rounded-2xl border border-dashed p-10 text-center opacity-60">Nenhum imóvel autorizado encontrado.</p>}{listings.map((listing) => <Link key={listing.slug} to={listing.path} className={theme.card_style === 'elevated' ? 'overflow-hidden shadow-xl transition hover:-translate-y-1' : 'overflow-hidden border border-black/10 transition hover:-translate-y-1'} style={{ background: surface, borderRadius: cardRadius }}>{listing.photos?.[0] ? <img src={listing.photos[0].url} alt={listing.title} className="h-52 w-full object-cover"/> : <div className="grid h-52 place-items-center opacity-20" style={{ background: color }}><Building2 className="h-12 w-12"/></div>}<div className="p-5"><h3 className="font-bold">{listing.title}</h3><p className="mt-1 text-sm opacity-60">{[listing.neighborhood,listing.city].filter(Boolean).join(' · ')}</p><p className="mt-4 text-lg font-black" style={{color}}>{money(listing.asking_price_cents)}</p></div></Link>)}</div></section>;
        if (section.type === 'about') return <section key={section.id} className="px-6 py-16" style={{ background: surface }}><div className="mx-auto max-w-4xl text-center"><h2 className="text-3xl font-black">{section.title}</h2><p className="mt-4 text-lg opacity-70">{theme.bio || section.body}</p></div></section>;
        if (section.type === 'contact') return <section id="contato" key={section.id} className="mx-auto max-w-6xl px-6 py-16"><div className="p-8 text-white md:p-12" style={{background:accent,borderRadius:cardRadius}}><h2 className="text-3xl font-black">{section.title}</h2><p className="mt-3 opacity-75">{section.body}</p><div className="mt-6 flex flex-wrap gap-3">{theme.whatsapp && <a href={`https://wa.me/${theme.whatsapp.replace(/\D/g,'')}`} className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 font-bold text-slate-900"><MessageCircle className="h-4 w-4"/>WhatsApp</a>}{theme.phone && <a href={`tel:${theme.phone}`} className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-4 py-3 font-bold"><Phone className="h-4 w-4"/>{theme.phone}</a>}{theme.instagram && <span className="inline-flex items-center gap-2 px-4 py-3"><Instagram className="h-4 w-4"/>{theme.instagram}</span>}</div></div></section>;
        return null;
      })}
      <footer className="border-t border-black/10 p-6 text-center text-xs opacity-60">{theme.footer_text || `© ${new Date().getFullYear()} ${theme.display_name || 'Corretor'}`}</footer>
    </div>
  );
}

export function PublicListing() {
  const { listingSlug } = useParams();
  const [payload, setPayload] = useState<any>(null);
  const [missing, setMissing] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ name: '', email: '', phone: '', message: '', consent: false });

  useEffect(() => {
    if (!listingSlug) return;
    publicApi.get(`/public/listings/${listingSlug}`).then(({ data }) => setPayload(data.data)).catch(() => setMissing(true));
  }, [listingSlug]);

  async function send(event: FormEvent) {
    event.preventDefault();
    setError('');
    try {
      await publicApi.post(`/public/listings/${listingSlug}/leads`, { ...form, consent: form.consent ? '1' : '' });
      setSent(true);
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível enviar o interesse.');
    }
  }

  if (missing) return <div className="min-h-screen grid place-items-center p-8">Imóvel indisponível ou página suspensa.</div>;
  if (!payload) return <div className="min-h-screen grid place-items-center">Carregando...</div>;
  const listing = payload.listing;
  const theme = payload.broker?.theme ?? {};
  const color = theme.primary_color || '#4F46E5';

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="px-6 py-4 text-white" style={{ background: color }}>
        <Link to={`/corretor/${payload.broker.slug}`} className="text-sm opacity-90">← {theme.display_name || 'Corretor'}</Link>
        <h1 className="text-2xl font-bold mt-2">{listing.title}</h1>
      </header>
      <main className="max-w-3xl mx-auto p-6 space-y-6">
        {listing.photos?.[0] && <img src={listing.photos[0].url} alt="" className="w-full h-72 object-cover rounded-2xl" />}
        <p className="text-xl font-bold">{money(listing.asking_price_cents)}</p>
        <p className="text-slate-600">{listing.description}</p>
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <div><dt className="text-slate-400">Cidade</dt><dd>{listing.city || '—'}</dd></div>
          <div><dt className="text-slate-400">Bairro</dt><dd>{listing.neighborhood || '—'}</dd></div>
          <div><dt className="text-slate-400">Quartos</dt><dd>{listing.bedrooms ?? '—'}</dd></div>
          <div><dt className="text-slate-400">Área</dt><dd>{listing.area_m2 ? `${listing.area_m2} m²` : '—'}</dd></div>
        </dl>
        {sent ? <p className="bg-emerald-50 text-emerald-800 p-4 rounded-xl">Interesse enviado.</p> : (
          <form onSubmit={send} className="bg-white border rounded-2xl p-4 space-y-3">
            {error && <p className="text-sm text-red-700">{error}</p>}
            <input required className="input" placeholder="Nome" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            <input className="input" type="email" placeholder="E-mail" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            <input className="input" placeholder="Telefone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
            <textarea className="input min-h-20" placeholder="Mensagem" value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} />
            <label className="flex gap-2 text-sm"><input type="checkbox" checked={form.consent} onChange={(e) => setForm({ ...form, consent: e.target.checked })} required />Autorizo o contato sobre este imóvel.</label>
            <button className="text-white px-4 py-2 rounded-lg font-semibold" style={{ background: color }}>Enviar interesse</button>
          </form>
        )}
      </main>
    </div>
  );
}
