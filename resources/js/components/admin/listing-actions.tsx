import { Form } from '@inertiajs/react';
import { useId } from 'react';
import ListingDecisionController from '@/actions/App/Http/Controllers/Admin/Catalogue/ListingDecisionController';
import ListingRematchController from '@/actions/App/Http/Controllers/Admin/Catalogue/ListingRematchController';
import {
    ActionDialog,
    FieldError,
    NoteField,
} from '@/components/admin/action-dialog';
import { ProductPicker } from '@/components/admin/product-picker';
import { buttonStyles } from '@/components/comparo/button-styles';
import type {
    ListingActions,
    MatchedProduct,
    MatchingShowProps,
    ProductSearchResult,
} from '@/types/admin';

type DecisionAction = 'confirm' | 'choose' | 'reject';

export function toSearchResult(product: MatchedProduct): ProductSearchResult {
    return {
        id: product.id,
        name: product.name,
        brand: product.brand ?? '',
        pack: product.pack,
        ean: product.ean,
    };
}

function productName(product: MatchedProduct | null): string {
    return product
        ? `${product.brand ? `${product.brand} ` : ''}${product.name} (${product.pack})`
        : 'the suggested product';
}

type DecisionFormProps = {
    listingId: number;
    action: DecisionAction;
    close: () => void;
    submitLabel: string;
    preselected?: MatchedProduct | null;
    excludeId?: number | null;
};

function DecisionForm({
    listingId,
    action,
    close,
    submitLabel,
    preselected = null,
    excludeId = null,
}: DecisionFormProps) {
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
                        <ProductPicker
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

type RematchFormProps = {
    listingId: number;
    close: () => void;
    currentProductId: number | null;
    preselected?: MatchedProduct | null;
};

function RematchForm({
    listingId,
    close,
    currentProductId,
    preselected = null,
}: RematchFormProps) {
    const id = useId();

    return (
        <Form
            {...ListingRematchController.store.form(listingId)}
            options={{ preserveScroll: true }}
            onSuccess={close}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <ProductPicker
                        initial={
                            preselected ? toSearchResult(preselected) : null
                        }
                        excludeId={currentProductId}
                        error={errors.product_id}
                    />
                    <NoteField
                        id={`${id}-note`}
                        required
                        label="Why is it relinked? (required)"
                        error={errors.note}
                    />
                    <FieldError
                        id={`${id}-decision-error`}
                        message={errors.decision}
                    />
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing ? 'Saving…' : 'Relink listing'}
                    </button>
                </>
            )}
        </Form>
    );
}

type ChooseDialogProps = {
    listingId: number;
    product: MatchedProduct;
    actions: ListingActions;
    canRematch: boolean;
    linkedProductId: number | null;
};

/** "Use this product" on one compared candidate: a choice or a rematch. */
export function CandidateChoiceDialog({
    listingId,
    product,
    actions,
    canRematch,
    linkedProductId,
}: ChooseDialogProps) {
    if (product.id === linkedProductId) {
        return (
            <p className="text-[12.5px] font-bold text-ok">
                Currently linked product
            </p>
        );
    }

    if (actions.choose) {
        return (
            <ActionDialog
                triggerLabel="Link to this product"
                title="Link the listing to this product?"
                description={`The listing will be linked to ${productName(product)} as a manual decision. If the product is not cleared for the listing’s market, the link is held and nothing is published.`}
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

    if (actions.rematch) {
        return (
            <ActionDialog
                triggerLabel="Relink to this product"
                disabledReason={
                    canRematch ? null : 'Relinking needs offers.manage.'
                }
                title="Relink the listing to this product?"
                description={`The published offer moves to ${productName(product)}. Earlier price history stays with the previous product.`}
            >
                {(close) => (
                    <RematchForm
                        listingId={listingId}
                        close={close}
                        currentProductId={linkedProductId}
                        preselected={product}
                    />
                )}
            </ActionDialog>
        );
    }

    return null;
}

type DecisionPanelProps = Pick<
    MatchingShowProps,
    'actions' | 'can' | 'currentDecision'
> & {
    listingId: number;
    linkedProductId: number | null;
};

/** The listing-level actions; disabled ones say why. */
export function DecisionPanel({
    listingId,
    linkedProductId,
    actions,
    can,
    currentDecision,
}: DecisionPanelProps) {
    const noPermission = can.decide
        ? null
        : 'Needs the matching.review permission.';
    const suggested = currentDecision?.product ?? null;

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ActionDialog
                triggerLabel="Confirm suggestion"
                variant="primary"
                disabledReason={
                    noPermission ??
                    (actions.confirm
                        ? null
                        : 'Only a pending suggestion can be confirmed.')
                }
                title="Confirm the suggested product?"
                description={`The listing will be linked to ${productName(suggested)}. If the product is not cleared for the listing’s market, the link is held and nothing is published.`}
            >
                {(close) => (
                    <DecisionForm
                        listingId={listingId}
                        action="confirm"
                        close={close}
                        submitLabel="Confirm and link"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Choose another product"
                disabledReason={
                    noPermission ??
                    (actions.choose
                        ? null
                        : 'A linked listing is corrected with a rematch.')
                }
                title="Choose another product"
                description="Search the catalogue and link the listing to an active product."
            >
                {(close) => (
                    <DecisionForm
                        listingId={listingId}
                        action="choose"
                        close={close}
                        excludeId={linkedProductId}
                        submitLabel="Link to selected product"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Reject match"
                disabledReason={
                    noPermission ??
                    (actions.reject
                        ? null
                        : 'Nothing is linked or suggested to reject.')
                }
                title="Reject this match?"
                description="The listing loses its product and returns to the review queue. Its offer, if any, is deactivated."
            >
                {(close) => (
                    <DecisionForm
                        listingId={listingId}
                        action="reject"
                        close={close}
                        submitLabel="Reject match"
                    />
                )}
            </ActionDialog>
            <ActionDialog
                triggerLabel="Relink (rematch)"
                disabledReason={
                    !actions.rematch
                        ? 'Only a linked listing can be relinked.'
                        : can.rematch
                          ? null
                          : 'Relinking needs offers.manage.'
                }
                title="Relink this listing"
                description="Move the listing and its offer to another active product. Earlier price history stays with the previous product."
            >
                {(close) => (
                    <RematchForm
                        listingId={listingId}
                        close={close}
                        currentProductId={linkedProductId}
                    />
                )}
            </ActionDialog>
        </div>
    );
}
