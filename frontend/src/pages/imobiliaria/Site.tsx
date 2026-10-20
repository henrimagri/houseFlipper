import { useEffect, useMemo, useState } from 'react';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import ErrorState from '@/ui/ErrorState';

type Section = { id: string; type: string; visible: boolean; title: string; body: string };

const DEFAULT_SECTIONS: Section[] = [
  { id: 'hero', type: 'hero', visible: true, title: 'Encontre o imóvel certo', body: 'Imóveis autorizados da imobiliária.' },
  { id: 'featured', type: 'featured', visible: true, title: 'Imóveis em destaque', body: '' },
  { id: 'about', type: 'about', visible: true, title: 'Sobre a imobiliária', body: '' },
  { id: 'team', type: 'team', visible: true, title: 'Equipe', body: '' },
  { id: 'branches', type: 'branches', visible: true, title: 'Filiais', body: '' },
  { id: 'contact', type: 'contact', visible: true, title: 'Contato', body: '' },
  { id: 'footer', type: 'footer', visible: true, title: 'Rodapé', body: '' },
];

export default function AgencySitePage() {
  const [site, setSite] = useState<any>(null);
  const [slug, setSlug] = useState('');
  const [theme, setTheme] = useState<any>({});
  const [sections, setSections] = useState<Section[]>(DEFAULT_SECTIONS);
  const [device, setDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [error, setError] = useState('');
  const [status, setStatus] = useState('');

  async function load() {
    const { data } = await api.get('/imobiliaria/site');
    setSite(data);
    const current = data.data?.theme_json ?? {};
    setSlug(data.data?.slug ?? '');
    setTheme(current);
    setSections(current.sections?.length ? current.sections : DEFAULT_SECTIONS);
  }
  useEffect(() => { load().catch((exception: any) => setError(exception.response?.data?.message ?? 'Editor indisponível.')); }, []);

  const payloadTheme = useMemo(() => ({
    ...theme,
    sections,
    published: Boolean(theme.published),
  }), [theme, sections]);

  async function save(action: 'draft' | 'publish' | 'unpublish' = 'draft') {
    setError(''); setStatus('');
    try {
      const body: Record<string, unknown> = { slug, theme: { ...payloadTheme, published: action === 'unpublish' ? false : payloadTheme.published } };
      if (action === 'publish') body.publish = true;
      if (action === 'unpublish') body.publish = false;
      const { data } = await api.put('/imobiliaria/site', body);
      setSite({ ...site, data: data.data, public_path: `/i/${data.data.slug}` });
      setTheme(data.data.theme_json ?? payloadTheme);
      setStatus(action === 'publish' ? 'Publicado.' : action === 'unpublish' ? 'Despublicado.' : 'Rascunho salvo.');
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível salvar.');
    }
  }

  function move(index: number, dir: number) {
    const next = [...sections];
    const target = index + dir;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    setSections(next);
  }

  if (!site && !error) return <p role="status">Carregando site...</p>;
  const width = device === 'desktop' ? '100%' : device === 'tablet' ? '768px' : '390px';

  return (
    <div className="space-y-4">
      <PageHeader title="Site da imobiliária" subtitle="Editor por blocos. A publicação em /i/{slug} exige assinatura com public_listing." />
      {error && <ErrorState message={error} />}
      {status && <p className="text-sm text-emerald-700">{status}</p>}
      <div className="grid lg:grid-cols-2 gap-4">
        <section className="bg-white dark:bg-slate-900 border rounded-2xl p-4 space-y-3">
          <label className="text-xs block">Endereço público
            <input className="input mt-1" value={slug} onChange={(e) => setSlug(e.target.value)} />
          </label>
          <label className="text-xs block">Nome comercial
            <input className="input mt-1" value={theme.display_name ?? ''} onChange={(e) => setTheme({ ...theme, display_name: e.target.value })} />
          </label>
          <label className="text-xs block">Headline
            <input className="input mt-1" value={theme.headline ?? ''} onChange={(e) => setTheme({ ...theme, headline: e.target.value })} />
          </label>
          <label className="text-xs block">Apresentação
            <textarea className="input mt-1" value={theme.bio ?? ''} onChange={(e) => setTheme({ ...theme, bio: e.target.value })} />
          </label>
          <div className="grid grid-cols-2 gap-2">
            <label className="text-xs">Cor principal<input className="input mt-1" value={theme.primary_color ?? '#4F46E5'} onChange={(e) => setTheme({ ...theme, primary_color: e.target.value })} /></label>
            <label className="text-xs">Cor de destaque<input className="input mt-1" value={theme.accent_color ?? '#0F172A'} onChange={(e) => setTheme({ ...theme, accent_color: e.target.value })} /></label>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <label className="text-xs">Telefone<input className="input mt-1" value={theme.phone ?? ''} onChange={(e) => setTheme({ ...theme, phone: e.target.value })} /></label>
            <label className="text-xs">WhatsApp<input className="input mt-1" value={theme.whatsapp ?? ''} onChange={(e) => setTheme({ ...theme, whatsapp: e.target.value })} /></label>
          </div>
          <div className="space-y-2">
            {sections.map((section, index) => (
              <article key={section.id} className="border rounded-xl p-3 space-y-2">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-medium">{section.title || section.type}</p>
                  <div className="flex gap-1">
                    <button type="button" className="text-xs border rounded px-2" onClick={() => move(index, -1)}>↑</button>
                    <button type="button" className="text-xs border rounded px-2" onClick={() => move(index, 1)}>↓</button>
                    <button type="button" className="text-xs border rounded px-2" onClick={() => setSections(sections.map((item, i) => i === index ? { ...item, visible: !item.visible } : item))}>{section.visible ? 'Ocultar' : 'Mostrar'}</button>
                  </div>
                </div>
                <input className="input" value={section.title} onChange={(e) => setSections(sections.map((item, i) => i === index ? { ...item, title: e.target.value } : item))} />
                <textarea className="input" value={section.body} onChange={(e) => setSections(sections.map((item, i) => i === index ? { ...item, body: e.target.value } : item))} />
              </article>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => void save('draft')} className="px-4 py-2 border rounded-lg text-sm">Salvar rascunho</button>
            <button type="button" onClick={() => void save('publish')} className="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">Publicar</button>
            <button type="button" onClick={() => void save('unpublish')} className="px-4 py-2 border rounded-lg text-sm">Despublicar</button>
            {site?.public_path && <a className="px-4 py-2 border rounded-lg text-sm" href={site.public_path} target="_blank" rel="noreferrer">Abrir /i/{slug}</a>}
          </div>
        </section>
        <section className="bg-slate-100 rounded-2xl p-4">
          <div className="flex gap-2 mb-3">
            {(['desktop', 'tablet', 'mobile'] as const).map((item) => (
              <button key={item} type="button" onClick={() => setDevice(item)} className={`text-xs px-3 py-1 rounded-full ${device === item ? 'bg-sky-600 text-white' : 'border bg-white'}`}>{item}</button>
            ))}
          </div>
          <div className="mx-auto bg-white rounded-xl overflow-hidden border shadow-sm" style={{ width, maxWidth: '100%' }}>
            <header className="p-6 text-white" style={{ background: theme.primary_color || '#4F46E5' }}>
              <p className="text-xl font-bold">{theme.display_name || 'Imobiliária'}</p>
              <p className="text-sm opacity-90">{theme.headline}</p>
            </header>
            {sections.filter((section) => section.visible).map((section) => (
              <div key={section.id} className="p-5 border-t">
                <h3 className="font-semibold">{section.title}</h3>
                {section.body && <p className="text-sm text-slate-600 mt-1 whitespace-pre-wrap">{section.body}</p>}
                {section.type === 'about' && theme.bio && <p className="text-sm text-slate-600 mt-2">{theme.bio}</p>}
                {section.type === 'contact' && <p className="text-sm mt-2">{theme.phone} {theme.whatsapp ? `· WhatsApp ${theme.whatsapp}` : ''}</p>}
              </div>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}
