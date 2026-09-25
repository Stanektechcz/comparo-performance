import { CircleAlert, CircleCheck } from 'lucide-react';
import { ComparoDialogContent } from '@/components/comparo/comparo-dialog';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { toneSoft, trustTone } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type { Trust } from '@/types/catalog';

type TrustBadgeProps = {
    trust: Trust;
    shopName: string;
    className?: string;
};

/** Merchant trust score; opens the plain-language signals behind it. */
export function TrustBadge({ trust, shopName, className }: TrustBadgeProps) {
    return (
        <Dialog>
            <DialogTrigger
                className={cn(
                    'inline-flex min-h-7 items-center gap-1.5 rounded-pill border px-2 text-[11px] font-bold whitespace-nowrap',
                    toneSoft[trustTone[trust.band]],
                    className,
                )}
            >
                <span className="num">Trust {trust.score}/100</span>
                <span aria-hidden="true">·</span>
                <span>{trust.label}</span>
                <span className="sr-only">
                    {' '}
                    — explain {shopName} trust score
                </span>
            </DialogTrigger>
            <ComparoDialogContent
                title={`${shopName} trust: ${trust.score}/100 — ${trust.label}`}
                description="What we observed about this shop. Trust reflects shop behaviour only; commission, subscriptions and sponsorship are not inputs."
            >
                {trust.signals.length === 0 ? (
                    <p className="text-[13px] text-text-3">
                        No individual signals are available for this shop yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-line-soft">
                        {trust.signals.map((signal) => (
                            <li
                                key={signal.label}
                                className="flex items-start gap-3 py-2.5 text-[13px]"
                            >
                                {signal.good ? (
                                    <CircleCheck
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0 text-ok"
                                    />
                                ) : (
                                    <CircleAlert
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 shrink-0 text-warn"
                                    />
                                )}
                                <span className="min-w-0 flex-1">
                                    <span className="block text-text">
                                        {signal.label}
                                    </span>
                                    <span className="block text-text-3">
                                        {signal.value}
                                        <span className="sr-only">
                                            {signal.good
                                                ? ' (looks good)'
                                                : ' (needs attention)'}
                                        </span>
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </ComparoDialogContent>
        </Dialog>
    );
}
