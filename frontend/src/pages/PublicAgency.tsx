import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import axios from 'axios';

const publicApi = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } });
const money = (cents?: number | null) => cents == null ? 'Sob consulta' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function PublicAgency() {
  const { slug } = useParams();
  const [payload, setPayload] = useState<any>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    if (!slug) return;
    publicApi.get(`/public/agencies/${slug}`).then(({ data }) => setPayload(data)).catch(() => setMissing(true));
  }, [slug]);

  if (missing) return <div className="min-h-screen grid place-items-center bg-slate-50 text-slate-600 p-8">Esta página pública não está disponível.</div>;
  if (!payload) return <div className="min-h-screen grid place-items-center">Carregando...</div>;
  const theme = payload.site?.theme ?? {};
  const color = theme.primary_color || '#4F46E5';
  const sections = (theme.sections ?? []).filter((section: any) => section.visible !== false);

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="text-white px-6 py-12" style={{ background: color }}>
        <h1 className="text-3xl font-bold">{theme.display_name || 'Imobiliária'}</h1>
        {theme.headline && <p className="mt-2 max-w-2xl">{theme.headline}</p>}
      </header>
      {sections.map((section: any) => (
        <section key={section.id} className="max-w-5xl mx-auto px-6 py-8">
          <h2 className="text-xl font-semibold">{section.title}</h2>
          {section.body && <p className="mt-2 text-slate-600 whitespace-pre-wrap">{section.body}</p>}
          {section.type === 'about' && theme.bio && <p className="mt-2 text-slate-600">{theme.bio}</p>}
          {section.type === 'featured' && (
            <div className="mt-4 grid md:grid-cols-2 gap-4">
              {(payload.listings ?? []).map((listing: any) => (
                <Link key={listing.id || listing.slug} to={listing.slug ? `/c/${slug}/${listing.slug}` : '#'} className="bg-white rounded-2xl border p-4">
                  <h3 className="font-semibold">{listing.title}</h3>
                  <p className="text-sm text-slate-500">{[listing.neighborhood, listing.city].filter(Boolean).join(' · ')}</p>
                  <p className="mt-2 font-bold" style={{ color }}>{money(listing.asking_price_cents)}</p>
                </Link>
              ))}
              {!payload.listings?.length && <p className="text-sm text-slate-500">Nenhum imóvel autorizado publicado.</p>}
            </div>
          )}
          {section.type === 'contact' && (
            <p className="mt-2 text-sm">{theme.phone} {theme.whatsapp ? `· WhatsApp ${theme.whatsapp}` : ''}</p>
          )}
        </section>
      ))}
      {!sections.length && (
        <main className="max-w-5xl mx-auto p-6 grid gap-4 md:grid-cols-2">
          {(payload.listings ?? []).map((listing: any) => (
            <article key={listing.id} className="bg-white rounded-2xl border p-4">
              <h2 className="font-semibold">{listing.title}</h2>
              <p className="text-sm text-slate-500">{listing.city}</p>
              <p className="mt-2 font-bold" style={{ color }}>{money(listing.asking_price_cents)}</p>
            </article>
          ))}
        </main>
      )}
      <footer className="text-center text-xs text-slate-500 p-6">{theme.footer_text || 'Página institucional'}</footer>
    </div>
  );
}
