/**
 * Public catalogue URLs. Deliberately plain strings instead of Wayfinder
 * imports: the catalogue routes are owned by the PHP side and may not be
 * generated yet.
 */
const segment = (slug: string): string => encodeURIComponent(slug);

export const catalogUrls = {
    home: (): string => '/',
    products: (): string => '/products',
    product: (slug: string): string => `/products/${segment(slug)}`,
    brands: (): string => '/brands',
    brand: (slug: string): string => `/brands/${segment(slug)}`,
    categories: (): string => '/categories',
    category: (slug: string): string => `/categories/${segment(slug)}`,
    shops: (): string => '/shops',
    shop: (slug: string): string => `/shops/${segment(slug)}`,
} as const;

export const accountUrls = {
    login: (): string => '/login',
    register: (): string => '/register',
    dashboard: (): string => '/dashboard',
} as const;

export const MARKET_SWITCH_URL = '/market';
