import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import api from '@/lib/api';
import { getAuthToken } from '@/lib/authToken';
import { ProductSlug, PRODUCT_STORAGE_KEY, productFromPath, readStoredProduct } from '@/lib/products';

export type CatalogItem = {
  slug: ProductSlug;
  code: string;
  label: string;
  description: string;
  path: string;
  entitled: boolean;
  subscription: { status: string; active: boolean; trial_ends_at?: string | null } | null;
  plans: { slug: string; name: string; billing_interval: string; price: number; currency: string; checkout: string }[];
};

type Session = {
  loading: boolean;
  error: string;
  name: string;
  email: string;
  avatarUrl: string;
  locationLabel: string;
  products: ProductSlug[];
  features: string[];
  catalog: CatalogItem[];
  navigation: Record<string, { label: string; path: string; feature?: string | null; group?: string }[]>;
  lastProduct: ProductSlug | null;
  isPlatformAdmin: boolean;
  requiresEmailVerification: boolean;
  refresh: () => Promise<void>;
  selectProduct: (slug: ProductSlug) => Promise<void>;
};

const SessionContext = createContext<Session | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [avatarUrl, setAvatarUrl] = useState('');
  const [locationLabel, setLocationLabel] = useState('');
  const [products, setProducts] = useState<ProductSlug[]>([]);
  const [features, setFeatures] = useState<string[]>([]);
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  const [navigation, setNavigation] = useState<Session['navigation']>({});
  const [lastProduct, setLastProduct] = useState<ProductSlug | null>(null);
  const [isPlatformAdmin, setIsPlatformAdmin] = useState(false);
  const [requiresEmailVerification, setRequiresEmailVerification] = useState(false);

  async function refresh() {
    setError('');
    if (!getAuthToken()) {
      setProducts([]);
      return;
    }
    const { data } = await api.get('/auth/me');
    setName(data.name ?? '');
    setEmail(data.email ?? '');
    setAvatarUrl(data.avatar_url ?? '');
    const address = data.registration_profile?.address;
    const city = address?.city || data.registration_profile?.city || data.registration_profile?.region_preference || '';
    const state = address?.state || '';
    setLocationLabel([city, state].filter(Boolean).join(' · '));
    setProducts(data.products ?? []);
    setFeatures(data.features ?? []);
    setCatalog(data.catalog ?? []);
    setNavigation(data.navigation ?? {});
    setLastProduct(data.last_product_slug ?? null);
    setIsPlatformAdmin(Boolean(data.is_platform_admin));
    setRequiresEmailVerification(Boolean(data.requires_email_verification));
    const fromPath = productFromPath(window.location.pathname);
    const stored = readStoredProduct();
    const next = fromPath && data.products?.includes(fromPath)
      ? fromPath
      : (stored && data.products?.includes(stored) ? stored : data.last_product_slug);
    if (next) localStorage.setItem(PRODUCT_STORAGE_KEY, next);
  }

  useEffect(() => {
    refresh().catch(() => setError('Não foi possível carregar a sessão.')).finally(() => setLoading(false));
  }, []);

  async function selectProduct(slug: ProductSlug) {
    localStorage.setItem(PRODUCT_STORAGE_KEY, slug);
    await api.post('/auth/product', { product: slug });
    setLastProduct(slug);
  }

  const value = useMemo<Session>(() => ({
    loading, error, name, email, avatarUrl, locationLabel, products, features, catalog, navigation, lastProduct, isPlatformAdmin, requiresEmailVerification, refresh, selectProduct,
  }), [loading, error, name, email, avatarUrl, locationLabel, products, features, catalog, navigation, lastProduct, isPlatformAdmin, requiresEmailVerification]);

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  const ctx = useContext(SessionContext);
  if (!ctx) throw new Error('SessionProvider ausente');
  return ctx;
}
