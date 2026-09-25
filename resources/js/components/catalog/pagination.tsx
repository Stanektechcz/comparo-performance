import { Link } from '@inertiajs/react';
import { buttonStyles } from '@/components/comparo/button-styles';
import type { Paginated } from '@/types/catalog';

type PaginationProps = {
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
};

export function Pagination({ meta, links }: PaginationProps) {
    if (meta.lastPage <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="mt-8 flex flex-wrap items-center justify-between gap-3"
        >
            {links.prev ? (
                <Link
                    href={links.prev}
                    rel="prev"
                    className={buttonStyles.secondary}
                >
                    <span aria-hidden="true">←</span> Previous page
                </Link>
            ) : (
                <span aria-hidden="true" />
            )}
            <p className="num text-[13px] text-text-3">
                Page {meta.currentPage} of {meta.lastPage}
            </p>
            {links.next ? (
                <Link
                    href={links.next}
                    rel="next"
                    className={buttonStyles.secondary}
                >
                    Next page <span aria-hidden="true">→</span>
                </Link>
            ) : (
                <span aria-hidden="true" />
            )}
        </nav>
    );
}
