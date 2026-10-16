import puppeteer from 'puppeteer-core';

const input = JSON.parse(process.argv[2] || '{}');
const limit = Math.min(250, Math.max(1, Number(input.limit || 250)));
const perPortalTimeoutMs = Math.min(12000, Math.max(4000, Number(input.perPortalTimeoutMs || 8000)));
const minEnoughResults = Math.min(limit, Math.max(20, Number(input.minEnoughResults || 60)));
const city = String(input.city || '').trim();
const neighborhood = String(input.neighborhood || '').trim();
const state = String(input.state || 'SP').trim().toUpperCase() || 'SP';
const stateSlug = state.toLowerCase();
const propertyType = ['apartment', 'house', 'commercial'].includes(input.propertyType) ? input.propertyType : 'apartment';
const region = `${neighborhood} ${city}`.trim() || 'São Paulo';
const enrichDetails = input.enrichDetails === true;

const slug = (value) => String(value || '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-|-$/g, '');

const propertyTerms = {
  apartment: {
    query: 'apartamento',
    zap: 'apartamentos',
    vivareal: 'apartamento_residencial',
    olx: 'apartamentos',
    imovelweb: 'apartamentos',
    chaves: 'apartamentos',
    casamineira: 'apartamento',
    loft: 'apartamentos',
    quintoandar: 'apartamento',
    mercadolivre: 'apartamento',
  },
  house: {
    query: 'casa',
    zap: 'casas',
    vivareal: 'casa_residencial',
    olx: 'casas',
    imovelweb: 'casas',
    chaves: 'casas',
    casamineira: 'casa',
    loft: 'casas',
    quintoandar: 'casa',
    mercadolivre: 'casa',
  },
  commercial: {
    query: 'imóvel comercial',
    zap: 'imoveis-comerciais',
    vivareal: 'imovel_comercial',
    olx: 'comerciais',
    imovelweb: 'comerciais',
    chaves: 'comerciais',
    casamineira: 'imovel-comercial',
    loft: 'comerciais',
    quintoandar: 'comercial',
    mercadolivre: 'imovel-comercial',
  },
};

const terms = propertyTerms[propertyType];
const citySlug = slug(city || region);
const neighborhoodSlug = slug(neighborhood);
const zapLocation = neighborhoodSlug ? `${stateSlug}+${citySlug}+${neighborhoodSlug}` : `${stateSlug}+${citySlug}`;
const pathLocation = neighborhoodSlug ? `${citySlug}/${neighborhoodSlug}` : citySlug;
const dashLocation = neighborhoodSlug ? `${neighborhoodSlug}-${citySlug}` : citySlug;

const targets = [
  {
    name: 'OLX',
    source_key: 'olx',
    url: `https://www.olx.com.br/imoveis/venda/${terms.olx}/estado-${stateSlug}?q=${encodeURIComponent(region)}`,
  },
  {
    name: 'ZAP Imóveis',
    source_key: 'zap',
    url: `https://www.zapimoveis.com.br/venda/${terms.zap}/${zapLocation}/`,
  },
  {
    name: 'VivaReal',
    source_key: 'vivareal',
    url: `https://www.vivareal.com.br/venda/${stateSlug}/${pathLocation}/${terms.vivareal}/`,
  },
  {
    name: 'ImovelWeb',
    source_key: 'other',
    url: `https://www.imovelweb.com.br/${terms.imovelweb}-venda-${dashLocation}-${stateSlug}.html`,
  },
  {
    name: 'Chaves na Mão',
    source_key: 'other',
    url: `https://www.chavesnamao.com.br/${terms.chaves}-a-venda/${stateSlug}-${slug(city || region)}/`,
  },
  {
    name: 'Casa Mineira',
    source_key: 'realtor',
    url: `https://www.casamineira.com.br/venda/${terms.casamineira}/${slug(city || region)}_${stateSlug}`,
  },
  {
    name: 'Loft',
    source_key: 'other',
    url: `https://loft.com.br/venda/imoveis/${stateSlug}/${slug(city || region)}/${terms.loft}`,
  },
  {
    name: 'QuintoAndar',
    source_key: 'quintoandar',
    url: `https://www.quintoandar.com.br/comprar/imovel/${slug(city || region)}-${stateSlug}-brasil/${terms.quintoandar}`,
  },
  {
    name: 'Mercado Livre',
    source_key: 'other',
    url: `https://lista.mercadolivre.com.br/imoveis/${terms.mercadolivre}-venda-${slug(`${region} ${state}`)}`,
  },
  {
    name: 'Facebook Marketplace',
    source_key: 'facebook',
    url: `https://www.facebook.com/marketplace/search/?query=${encodeURIComponent(`${terms.query} venda ${region} ${state}`)}`,
  },
];

const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
  headless: 'new',
  args: [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--disable-blink-features=AutomationControlled',
    '--disable-crash-reporter',
    '--disable-crashpad',
    '--disable-features=Crashpad',
    '--no-zygote',
    '--window-size=1440,1100',
  ],
});

const diagnostics = [];
const items = [];

try {
  for (const target of targets) {
    if (items.length >= minEnoughResults || items.length >= limit) break;

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0 Safari/537.36');
    await page.setExtraHTTPHeaders({ 'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7' });
    await speedUpPage(page);

    try {
      const response = await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: perPortalTimeoutMs });
      await new Promise(resolve => setTimeout(resolve, 500));
      await autoScroll(page);

      const found = await page.evaluate(({ target, city, neighborhood, state }) => {
        const moneyToNumber = (value) => {
          const match = String(value || '').match(/R\$\s*([\d.]+)(?:,(\d{2}))?/);
          if (!match) return 0;
          return Number(match[1].replace(/\./g, '')) + (match[2] ? Number(`0.${match[2]}`) : 0);
        };

        const numberFrom = (text, regex) => {
          const match = String(text || '').match(regex);
          return match ? Number(String(match[1]).replace(',', '.')) : null;
        };

        const inferCompactSpecs = (text) => {
          const compact = String(text || '').match(/(?:^|\s)(\d+)\s+(\d+(?:[,.]\d+)?)m²\s+(\d+)\s+(\d+)(?:\s|R\$)/i);
          if (!compact) return {};

          return {
            bedrooms: Number(compact[1]),
            area_m2: Number(String(compact[2]).replace(',', '.')),
            bathrooms: Number(compact[3]),
            parking_spaces: Number(compact[4]),
          };
        };

        const absolutize = (url) => {
          try { return new URL(url, window.location.origin).href; } catch { return url || null; }
        };

        const titleFrom = (text) => {
          const clean = String(text || '').replace(/\s+/g, ' ').trim();
          const beforePrice = clean.split('R$')[0]?.trim();
          return (beforePrice || clean || 'Imóvel anunciado').slice(0, 120);
        };

        const normalizeSearchText = (value) => String(value || '')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, ' ')
          .trim();

        const imageFrom = (href, node) => {
          const candidates = [];
          const sameHrefAnchors = [...document.querySelectorAll('a[href]')]
            .filter(item => item.getAttribute('href') === href || item.href === href);

          for (const anchor of sameHrefAnchors) {
            candidates.push(...anchor.querySelectorAll('img'));
          }

          let current = node;
          for (let i = 0; i < 7 && current; i += 1) {
            candidates.push(...current.querySelectorAll?.('img') || []);
            current = current.parentElement;
          }

          const src = candidates
            .map(img => img.currentSrc || img.src || img.getAttribute('data-src') || img.getAttribute('srcset')?.split(' ')?.[0])
            .find(value => value && !value.includes('logo') && !value.includes('avatar') && !value.includes('sprite'));

          if (!src) return null;

          try {
            const parsed = new URL(src, window.location.origin);
            const nested = parsed.searchParams.get('url');
            return nested ? decodeURIComponent(nested) : parsed.href;
          } catch {
            return src;
          }
        };

        const cards = [];
        const anchors = [...document.querySelectorAll('a[href]')];

        for (const anchor of anchors) {
          let node = anchor;
          let text = '';

          for (let i = 0; i < 5 && node; i += 1) {
            text = String(node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim();
            if (text.includes('R$') && text.length < 3500) break;
            node = node.parentElement;
          }

          const href = anchor.getAttribute('href') || '';
          const haystack = `${href} ${text}`.toLowerCase();
          const normalizedHaystack = normalizeSearchText(`${href} ${text}`);
          const price = moneyToNumber(text);
          const title = titleFrom(anchor.innerText || text);

          if (!price || !haystack.match(/apartamento|casa|comercial|sala|loja|im[oó]vel|venda|comprar|condom/i)) continue;
          if (haystack.includes('publicidade') || haystack.includes('patrocinado')) continue;
          if (neighborhood && !normalizedHaystack.includes(normalizeSearchText(neighborhood))) continue;
          if (!neighborhood && city && !normalizedHaystack.includes(normalizeSearchText(city)) && !normalizedHaystack.includes(normalizeSearchText(state))) continue;
          if (target.name === 'OLX' && !href.includes('/imoveis/')) continue;
          if (target.name === 'Facebook Marketplace' && !href.includes('/marketplace/item/')) continue;

          const compactSpecs = inferCompactSpecs(text);
          cards.push({
            title,
            text,
            source_url: absolutize(href),
            thumbnail: imageFrom(href, node),
            asking_price: price,
            area_m2: compactSpecs.area_m2 || numberFrom(text, /(\d+(?:[,.]\d+)?)\s*m²/i),
            bedrooms: compactSpecs.bedrooms || numberFrom(text, /(\d+)\s*(?:quarto|dormitório|dorm)/i),
            bathrooms: compactSpecs.bathrooms || numberFrom(text, /(\d+)\s*(?:banheiro|banh)/i),
            parking_spaces: compactSpecs.parking_spaces || numberFrom(text, /(\d+)\s*(?:vaga|garagem)/i),
            address: neighborhood || city || 'Região pesquisada',
            city,
            neighborhood,
            state,
            source: target.name,
            source_key: target.source_key,
          });
        }

        return cards;
      }, { target, city, neighborhood, state });

      const unique = dedupe(found).slice(0, Math.max(0, limit - items.length));
      const enriched = enrichDetails ? await enrichListings(browser, unique) : unique;
      diagnostics.push({ source: target.name, status: response?.status() || 0, found: unique.length });
      items.push(...enriched);
    } catch (error) {
      diagnostics.push({ source: target.name, status: 0, message: error.message });
    } finally {
      await page.close().catch(() => {});
    }
  }
} finally {
  await browser.close();
}

console.log(JSON.stringify({ items: dedupe(items).slice(0, limit), diagnostics }));

async function enrichListings(browser, listings) {
  const enriched = [];

  for (const item of listings) {
    if (!item.source_url) {
      enriched.push(item);
      continue;
    }

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0 Safari/537.36');
    await page.setExtraHTTPHeaders({ 'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7' });

    try {
      await page.goto(item.source_url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      await new Promise(resolve => setTimeout(resolve, 1200));

      const detail = await page.evaluate(() => {
        const text = document.body.innerText.replace(/\s+/g, ' ').trim();
        const metaImage = document.querySelector('meta[property="og:image"]')?.getAttribute('content')
          || document.querySelector('meta[name="twitter:image"]')?.getAttribute('content')
          || null;
        const firstImage = [...document.querySelectorAll('img')]
          .map(img => img.currentSrc || img.src || img.getAttribute('data-src'))
          .find(src => src && !src.includes('logo') && !src.includes('sprite')) || null;

        const numberNearLabel = (labels) => {
          for (const label of labels) {
            const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const patterns = [
              new RegExp(`${escaped}\\s*(\\d+(?:[,.]\\d+)?)`, 'i'),
              new RegExp(`(\\d+(?:[,.]\\d+)?)\\s*${escaped}`, 'i'),
            ];

            for (const pattern of patterns) {
              const match = text.match(pattern);
              if (match) return Number(String(match[1]).replace(',', '.'));
            }
          }

          return null;
        };

        return {
          thumbnail: metaImage || firstImage,
          area_m2: numberNearLabel(['Área útil', 'Área total', 'm²', 'metros quadrados']),
          bedrooms: numberNearLabel(['Quartos', 'Dormitórios', 'Dorms']),
          bathrooms: numberNearLabel(['Banheiros', 'Banheiro']),
          parking_spaces: numberNearLabel(['Vagas na garagem', 'Vagas', 'Garagem']),
        };
      });

      enriched.push({
        ...item,
        thumbnail: detail.thumbnail || item.thumbnail,
        area_m2: detail.area_m2 || item.area_m2,
        bedrooms: detail.bedrooms || item.bedrooms,
        bathrooms: detail.bathrooms ?? item.bathrooms,
        parking_spaces: detail.parking_spaces ?? item.parking_spaces,
      });
    } catch {
      enriched.push(item);
    } finally {
      await page.close().catch(() => {});
    }
  }

  return enriched;
}

function dedupe(list) {
  const seen = new Set();
  return list.filter((item) => {
    const key = item.source_url || `${item.title}-${item.asking_price}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let totalHeight = 0;
      const distance = 650;
      const maxHeight = Math.min(document.body.scrollHeight, 6000);
      const timer = setInterval(() => {
        window.scrollBy(0, distance);
        totalHeight += distance;
        if (totalHeight >= maxHeight) {
          clearInterval(timer);
          resolve();
        }
      }, 90);
    });
  });
}

async function speedUpPage(page) {
  await page.setRequestInterception(true);
  page.on('request', (request) => {
    const resourceType = request.resourceType();
    if (['font', 'media', 'stylesheet'].includes(resourceType)) {
      request.abort();
      return;
    }

    request.continue();
  });
}
