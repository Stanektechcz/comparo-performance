import { Notice } from '@/components/comparo/notice';
import { useMarket } from '@/hooks/use-shared-props';
import { formatDate } from '@/lib/format';
import { complianceTone } from '@/lib/tones';
import type { Compliance } from '@/types/catalog';

type ComplianceBannerProps = {
    compliance: Compliance;
    productName: string;
};

function Provenance({ compliance }: { compliance: Compliance }) {
    const market = useMarket();

    if (!compliance.source && !compliance.reviewedAt) {
        return null;
    }

    return (
        <p className="mt-2 num text-[11px] opacity-90">
            {compliance.source ? `Source: ${compliance.source}` : null}
            {compliance.source && compliance.reviewedAt ? ' · ' : null}
            {compliance.reviewedAt
                ? `Reviewed ${formatDate(compliance.reviewedAt, market.locale)}`
                : null}
        </p>
    );
}

/**
 * Market compliance state for the product (evaluated server-side).
 * Allowed products render nothing.
 */
export function ComplianceBanner({
    compliance,
    productName,
}: ComplianceBannerProps) {
    const market = useMarket();
    const tone = complianceTone[compliance.status];

    switch (compliance.status) {
        case 'allowed':
            return null;
        case 'unknown':
            return (
                <Notice
                    tone={tone}
                    title={compliance.label || 'Compliance review pending'}
                >
                    <p>
                        Market status not verified — prices shown for
                        information, purchase links disabled until our
                        compliance review completes.
                    </p>
                    <Provenance compliance={compliance} />
                </Notice>
            );
        case 'restricted':
            return (
                <Notice
                    tone={tone}
                    title={compliance.label || `Restricted in ${market.name}`}
                >
                    <p>
                        {compliance.reason ??
                            `${productName} is subject to restrictions in ${market.name}. Check the shop's conditions before you buy.`}
                    </p>
                    <Provenance compliance={compliance} />
                </Notice>
            );
        case 'prescription_only':
        case 'not_allowed':
            return (
                <Notice
                    tone={tone}
                    title={
                        compliance.label || `Not available in ${market.name}`
                    }
                >
                    <p>
                        {compliance.reason ??
                            `${productName} cannot be sold for delivery to ${market.name}.`}
                    </p>
                    <p className="mt-1.5">
                        This page is informational only: we list no offers and
                        no purchase links for {market.name}.
                    </p>
                    <Provenance compliance={compliance} />
                </Notice>
            );
    }
}
