import { Link } from '@inertiajs/react';

export type CrumbItem = { label: string; href?: string };

/** Breadcrumb trail; the last item is the current page. */
export function CatalogBreadcrumbs({ items }: { items: CrumbItem[] }) {
    return (
        <nav aria-label="Breadcrumb" className="mb-4">
            <ol className="flex flex-wrap items-center gap-1.5 text-xs text-text-4">
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li
                            key={`${item.label}-${index}`}
                            className="flex items-center gap-1.5"
                        >
                            {item.href && !isLast ? (
                                <Link
                                    href={item.href}
                                    className="hover:text-acc-text"
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <span
                                    aria-current={isLast ? 'page' : undefined}
                                    className="text-text-2"
                                >
                                    {item.label}
                                </span>
                            )}
                            {isLast ? null : <span aria-hidden="true">/</span>}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
