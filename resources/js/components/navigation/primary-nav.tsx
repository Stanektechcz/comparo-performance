import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { catalogUrls } from '@/lib/catalog-urls';
import { cn } from '@/lib/utils';

export const primaryNavItems = [
    { label: 'Products', href: catalogUrls.products() },
    { label: 'Categories', href: catalogUrls.categories() },
    { label: 'Brands', href: catalogUrls.brands() },
    { label: 'Shops', href: catalogUrls.shops() },
] as const;

type PrimaryNavProps = {
    orientation?: 'horizontal' | 'vertical';
    className?: string;
    onNavigate?: () => void;
};

export function PrimaryNav({
    orientation = 'horizontal',
    className,
    onNavigate,
}: PrimaryNavProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const vertical = orientation === 'vertical';

    return (
        <nav aria-label="Primary" className={className}>
            <ul className={cn('flex', vertical ? 'flex-col gap-1' : 'gap-1')}>
                {primaryNavItems.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);

                    return (
                        <li key={item.href}>
                            <Link
                                href={item.href}
                                onClick={onNavigate}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-11 items-center px-3 text-[15px] font-bold transition-colors hover:text-text',
                                    vertical
                                        ? 'rounded-btn hover:bg-surface-3'
                                        : 'shadow-[inset_0_-3px_0_0_transparent]',
                                    active
                                        ? vertical
                                            ? 'bg-acc-tint text-text'
                                            : 'text-text shadow-[inset_0_-3px_0_0_var(--acc)]'
                                        : 'text-text-3',
                                )}
                            >
                                {item.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
