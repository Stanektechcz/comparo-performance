import { Form } from '@inertiajs/react';
import { useId, useState } from 'react';
import CandidateResolutionController from '@/actions/App/Http/Controllers/Admin/Catalogue/CandidateResolutionController';
import ConflictResolutionController from '@/actions/App/Http/Controllers/Admin/Catalogue/ConflictResolutionController';
import {
    ActionDialog,
    FieldError,
    fieldLabel,
    NoteField,
    textFieldStyles,
} from '@/components/admin/action-dialog';
import { ProductPicker } from '@/components/admin/product-picker';
import { buttonStyles } from '@/components/comparo/button-styles';
import { cn } from '@/lib/utils';
import type { CandidateRow, ConflictRow } from '@/types/admin';

const radioRow =
    'flex min-h-11 cursor-pointer items-start gap-3 rounded-field border border-line-2 px-3 py-2.5 text-[13px] text-text has-checked:border-acc-line has-checked:bg-acc-tint';

export function ConflictResolveDialog({ conflict }: { conflict: ConflictRow }) {
    const id = useId();
    const product = conflict.product
        ? `${conflict.product.brand ?? ''} ${conflict.product.name}`.trim()
        : `conflict #${conflict.id}`;

    return (
        <ActionDialog
            triggerLabel="Close conflict"
            disabledReason={
                conflict.canResolve
                    ? null
                    : 'Needs the compliance.manage permission.'
            }
            title="Close this conflict"
            description={`${conflict.kind.label} on ${product}. Closing it is audited with your note.${conflict.kind.value === 'compliance_hold' ? ' Held listings stay held until a feed run clears them or staff relink them.' : ''}`}
        >
            {(close) => (
                <Form
                    {...ConflictResolutionController.store.form(conflict.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={close}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset className="flex flex-col gap-2">
                                <legend className={cn(fieldLabel, 'mb-1')}>
                                    Outcome
                                </legend>
                                <label className={radioRow}>
                                    <input
                                        type="radio"
                                        name="resolution"
                                        value="resolved"
                                        defaultChecked
                                        className="mt-1"
                                    />
                                    <span>
                                        <strong>Resolved</strong> — the correct
                                        value is known.
                                    </span>
                                </label>
                                <label className={radioRow}>
                                    <input
                                        type="radio"
                                        name="resolution"
                                        value="dismissed"
                                        className="mt-1"
                                    />
                                    <span>
                                        <strong>Dismissed</strong> — not a real
                                        conflict.
                                    </span>
                                </label>
                                <FieldError
                                    id={`${id}-resolution-error`}
                                    message={errors.resolution}
                                />
                            </fieldset>
                            {conflict.field ? (
                                <div className="flex flex-col gap-1.5">
                                    <label
                                        htmlFor={`${id}-value`}
                                        className={fieldLabel}
                                    >
                                        Correct {conflict.field} (optional)
                                    </label>
                                    <input
                                        id={`${id}-value`}
                                        name="resolved_value"
                                        maxLength={255}
                                        className={textFieldStyles}
                                    />
                                    <FieldError
                                        id={`${id}-value-error`}
                                        message={errors.resolved_value}
                                    />
                                </div>
                            ) : null}
                            <NoteField
                                id={`${id}-note`}
                                required
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
                                {processing ? 'Saving…' : 'Close conflict'}
                            </button>
                        </>
                    )}
                </Form>
            )}
        </ActionDialog>
    );
}

export function CandidateResolveDialog({
    candidate,
}: {
    candidate: CandidateRow;
}) {
    const id = useId();
    const [action, setAction] = useState<'link_existing' | 'reject'>(
        'link_existing',
    );

    return (
        <ActionDialog
            triggerLabel="Resolve"
            title="Resolve this proposal"
            description={`“${candidate.proposedName}” from ${candidate.sourceCount} ${candidate.sourceCount === 1 ? 'listing' : 'listings'}. Creating a new catalogue product from a proposal is not available yet (Phase 8).`}
        >
            {(close) => (
                <Form
                    {...CandidateResolutionController.store.form(candidate.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={close}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset className="flex flex-col gap-2">
                                <legend className={cn(fieldLabel, 'mb-1')}>
                                    Outcome
                                </legend>
                                <label className={radioRow}>
                                    <input
                                        type="radio"
                                        name="action"
                                        value="link_existing"
                                        checked={action === 'link_existing'}
                                        onChange={() =>
                                            setAction('link_existing')
                                        }
                                        className="mt-1"
                                    />
                                    <span>
                                        <strong>Existing product</strong> — link
                                        its unlinked listings to it.
                                    </span>
                                </label>
                                <label className={radioRow}>
                                    <input
                                        type="radio"
                                        name="action"
                                        value="reject"
                                        checked={action === 'reject'}
                                        onChange={() => setAction('reject')}
                                        className="mt-1"
                                    />
                                    <span>
                                        <strong>Reject</strong> — listings stay
                                        as they are.
                                    </span>
                                </label>
                            </fieldset>
                            {action === 'link_existing' ? (
                                <ProductPicker error={errors.product_id} />
                            ) : null}
                            <NoteField id={`${id}-note`} error={errors.note} />
                            <FieldError
                                id={`${id}-decision-error`}
                                message={errors.decision}
                            />
                            <button
                                type="submit"
                                disabled={processing}
                                className={buttonStyles.primary}
                            >
                                {processing
                                    ? 'Saving…'
                                    : action === 'reject'
                                      ? 'Reject proposal'
                                      : 'Link to product'}
                            </button>
                        </>
                    )}
                </Form>
            )}
        </ActionDialog>
    );
}
