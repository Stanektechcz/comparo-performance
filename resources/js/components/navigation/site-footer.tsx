import { Link } from '@inertiajs/react';
import { primaryNavItems } from '@/components/navigation/primary-nav';

export function SiteFooter() {
    return (
        <footer className="mt-16 border-t border-line bg-surface-sub">
            <div className="mx-auto grid max-w-[1440px] gap-8 px-4 py-10 sm:px-6 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div className="space-y-3">
                    <p className="text-base font-extrabold text-text">
                        Commission, subscriptions and sponsorship never change
                        organic rank.
                    </p>
                    <p className="max-w-2xl text-[13px] leading-relaxed text-text-3">
                        Totals are the product price plus shipping to your
                        delivery country, minus the best coupon we have seen
                        working. Shops set the final price at checkout. Values
                        labelled “Demo data” are fictional and shown only to
                        illustrate the layout; every other figure comes from
                        shop feeds with the timestamp shown next to it.
                    </p>
                    <p className="text-xs text-text-4">
                        Some links are affiliate links. If you buy through them
                        we may earn a commission — your price does not change.
                    </p>
                </div>
                <nav aria-label="Footer">
                    <p className="mb-3 eyebrow">Compare</p>
                    <ul className="grid grid-cols-2 gap-1">
                        {primaryNavItems.map((item) => (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    className="inline-flex min-h-tap items-center text-sm text-text-2 hover:text-acc-text"
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
            </div>
            <div className="border-t border-line-soft">
                <p className="mx-auto max-w-[1440px] px-4 py-4 text-xs text-text-4 sm:px-6">
                    © {new Date().getUTCFullYear()} Comparo Performance. Food
                    supplements do not replace a varied diet.
                </p>
            </div>
        </footer>
    );
}
