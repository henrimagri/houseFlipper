export type ProductSlug = 'corretor' | 'imobiliaria' | 'house_flipper';

export const PRODUCT_STORAGE_KEY = 'active_product';

export const PRODUCT_META: Record<ProductSlug, { label: string; path: string; role: string; accent: string }> = {
  corretor: { label: 'Corretor', path: '/app/corretor/dashboard', role: 'Corretor', accent: 'text-sky-700' },
  imobiliaria: { label: 'Imobiliária', path: '/app/imobiliaria/dashboard', role: 'Gestão', accent: 'text-indigo-700' },
  house_flipper: { label: 'House Flipper', path: '/app/house-flipper/dashboard', role: 'Investidor', accent: 'text-violet-700' },
};

export function productFromPath(pathname: string): ProductSlug | null {
  if (pathname.startsWith('/app/corretor')) return 'corretor';
  if (pathname.startsWith('/app/imobiliaria')) return 'imobiliaria';
  if (pathname.startsWith('/app/house-flipper')) return 'house_flipper';
  return null;
}

export function readStoredProduct(): ProductSlug | null {
  const value = localStorage.getItem(PRODUCT_STORAGE_KEY);
  return value === 'corretor' || value === 'imobiliaria' || value === 'house_flipper' ? value : null;
}

export function walletPath(pathname: string, id?: number | string) {
  const base = pathname.startsWith('/app/imobiliaria') ? '/app/imobiliaria/imoveis' : '/app/corretor/carteira';
  return id ? `${base}/${id}` : base;
}

export function contactsPath(pathname: string, id?: number | string) {
  const base = pathname.startsWith('/app/imobiliaria') ? '/app/imobiliaria/clientes' : '/app/corretor/leads';
  return id ? `${base}/${id}` : base;
}
