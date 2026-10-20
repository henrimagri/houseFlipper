import { PRODUCT_META, ProductSlug } from '@/lib/products';

export function afterLoginPath(products: ProductSlug[], lastProduct?: ProductSlug | null): string {
  if (products.length === 1) {
    return `/app/onboarding/${products[0]}`;
  }
  if (lastProduct && products.includes(lastProduct)) {
    return '/app/inicio';
  }
  if (products.length > 1) {
    return '/app/inicio';
  }
  return '/app/produtos';
}

export function productDashboard(slug: ProductSlug): string {
  return PRODUCT_META[slug].path;
}
