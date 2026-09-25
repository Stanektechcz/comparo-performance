import { Form } from '@inertiajs/react';
import { useId } from 'react';
import ListingDecisionController from '@/actions/App/Http/Controllers/Merchant/Matching/ListingDecisionController';
import ListingProposalController from '@/actions/App/Http/Controllers/Merchant/Matching/ListingProposalController';
import { ActionDialog, NoteField } from '@/components/admin/action-dialog';
import { buttonStyles } from '@/components/comparo/button-styles';
import { FieldError } from '@/components/merchant/form-fields';
import { MerchantProductPicker } from '@/components/merchant/product-picker';
import type {
    MatchedProduct,
    MatchingShowProps,
    ProductSearchResult,
} from '@/types/merchant';

type DecisionAction = 'confirm' | 'choose' | 'reject';

function toSearchResult(product: MatchedProduct): ProductSearchResult {
    return {
        id: product.id,
        name: product.name,
        brand: product.brand ?? '',
        pack: product.pack,
        ean: product.ean,
    };
}

export function productName(product: MatchedProduct | null): string {
    return product
        ? `${product.brand ? `${product.brand} ` : ''}${product.name} (${product.pack})`
        : 'the suggested product';
}

function DecisionForm({
    listingId,
    action,
    close,
    submitLabel,
    preselected = null,
    excludeId = null,
}: {
    listingId: number;
    action: DecisionAction;
    close: () => void;
    submitLabel: string;
    preselected?: MatchedProduct | null;
    excludeId?: number | null;
}) {
    const id = useId();

    return (
        <Form
            {...ListingDecisionController.store.form(listingId)}
            options={{ preserveScroll: true }}
            onSuccess={close}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="action" value={action} />
                    {action === 'choose' ? (
                        <MerchantProductPicker
                            initial={
                                preselected ? toSearchResult(preselected) : null
                            }
                            excludeId={excludeId}
                            error={errors.product_id}
                        />
                    ) : null}
                    <NoteField id={`${id}-note`} error={errors.note} />
                    <FieldError
                        id={`${id}-decision-error`}
                        message={errors.decision ?? errors.action}
                    />
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing ? 'Saving…' : submitLabel}
                    </button>
                </>
            )}
        </Form>
    );
}

function ProposeForm({
    listingId,
    close,
}: {
    listingId: number;
    close: () => void;
}) {
    const id = useId();

    return (
        <Form
            {...ListingProposalController.store.form(listingId)}
            options={{ preserveScroll: true }}
            onSuccess={close}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <FieldError
                        id={`${id}-decision-error`}
                        message={errors.decision}
                    />
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing ? 'Sending…' : 'Propose new product'}
                    </button>
                </>
            )}
        </Form>
    );
}

/** "Link to this product" on one compared candidate. */
export function CandidateChoice({
    listingId,
    product,
    linkedProductId,
    canChoose,
}: {
    listingId: number;
    product: MatchedProduct;
    linkedProductId: number | null;
    canChoose: boolean;
}) {
    if (product.id === linkedProductId) {
        return (
            <p className="text-[12.5px] font-bold text-ok">
                Currently linked product
            </p>
        );
    }

    if (!canChoose) {
        return null;
    }

    return (
        <ActionDialog
            triggerLabel="Link to this product"
            title="Link your listing to this product?"
            description={`Your listing will be linked to ${productName(product)}. If the product is not cleared for the listing’s market, the link is held and the offer is not published.`}
        >
            {(close) => (
                <DecisionForm
                    listingId={listingId}
                    action="choose"
                    close={close}
                    preselected={product}
                    submitLabel="Link to this product"
                />
            )}
        </ActionDialog>
    );
}

/** Listing-level actions; disabled ones say why in text. */
export function MerchantDecisionPanel({
    listing,
    currentDecision,
    can,
    actions,
}: Pick<MatchingShowProps, 'listing' | 'currentDecision' | 'can' | 'actions'>) {
    if (!can.decide) {
        return (
            <p className="text-[13px] text-text-3">
                Your role can review matches but not decide them. Ask an owner
                or manager of your team.
            </p>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ActionDialog
                triggerLabel="Confirm suggestion"
                variant="primary"
                disabledReason={
                    actions.confirm
                        ? null
                        : 'Only a pending suggestion can be confirmed.'
                }
                title="Confirm the suggested product?"
                description={`Your listing will be linked to ${productName(currentDecision?.product ?? null)}. If the product is not cleared for the listing’s market, the link is held and the offer is not published.`}
            >
                {(close) => (
                    <DecisionForm
                        listingId={listing.id}
                        action="confirm"
                        close={close}
                        submitLabel="Confirm and link"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Choose another product"
                disabledReason={
                    actions.choose
                        ? null
                        : 'The listing is linked. Contact Comparo support to move it.'
                }
                title="Choose another product"
                description="Search the catalogue and link your listing to an active product."
            >
                {(close) => (
                    <DecisionForm
                        listingId={listing.id}
                        action="choose"
                        close={close}
                        excludeId={listing.linkedProductId}
                        submitLabel="Link to selected product"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Reject match"
                disabledReason={
                    actions.reject
                        ? null
                        : 'Nothing is linked or suggested to reject.'
                }
                title="Reject this match?"
                description="The listing loses its product and goes back to your unmatched queue. Its offer, if any, stops being shown."
            >
                {(close) => (
                    <DecisionForm
                        listingId={listing.id}
                        action="reject"
                        close={close}
                        submitLabel="Reject match"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Propose new product"
                disabledReason={
                    !can.propose
                        ? 'Your role cannot propose products.'
                        : actions.propose
                          ? null
                          : 'Only unmatched or suggested listings with a title can be proposed.'
                }
                title="Propose a new catalogue product?"
                description="If this product is not in the Comparo catalogue yet, the catalogue team reviews your proposal. The listing stays unmatched until then; nothing is published now."
            >
                {(close) => (
                    <ProposeForm listingId={listing.id} close={close} />
                )}
            </ActionDialog>
        </div>
    );
}
