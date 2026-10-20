import { useEffect } from 'react';

type Props = {
  title: string;
  description: string;
  path: string;
  noindex?: boolean;
};

function upsertMeta(attr: 'name' | 'property', key: string, content: string) {
  let node = document.head.querySelector(`meta[${attr}="${key}"]`) as HTMLMetaElement | null;
  if (!node) {
    node = document.createElement('meta');
    node.setAttribute(attr, key);
    document.head.appendChild(node);
  }
  node.content = content;
}

export default function PublicSeo({ title, description, path, noindex }: Props) {
  useEffect(() => {
    const url = `${window.location.origin}${path}`;
    document.title = `${title} | FlipControl`;
    upsertMeta('name', 'description', description);
    upsertMeta('name', 'robots', noindex ? 'noindex, nofollow' : 'index, follow');
    upsertMeta('property', 'og:title', `${title} | FlipControl`);
    upsertMeta('property', 'og:description', description);
    upsertMeta('property', 'og:type', 'website');
    upsertMeta('property', 'og:url', url);
    let canonical = document.head.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
    if (!canonical) {
      canonical = document.createElement('link');
      canonical.rel = 'canonical';
      document.head.appendChild(canonical);
    }
    canonical.href = url;
  }, [title, description, path, noindex]);

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: 'FlipControl',
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web',
    description,
    url: path,
    offers: { '@type': 'Offer', availability: 'https://schema.org/OnlineOnly' },
  };

  return noindex ? null : <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>;
}
