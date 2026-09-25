import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ComparoDialogContentProps = {
    title: ReactNode;
    description: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * Comparo-styled Radix dialog body: focus trap, Escape to close, labelled
 * title and description. Pair it with a `DialogTrigger` so focus returns to
 * the trigger when the dialog closes.
 */
export function ComparoDialogContent({
    title,
    description,
    children,
    className,
}: ComparoDialogContentProps) {
    return (
        <DialogPortal>
            <DialogOverlay className="bg-overlay backdrop-blur-sm" />
            <DialogPrimitive.Content
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-[520px] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-modal border border-line-2 bg-surface p-6 text-text shadow-modal data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0',
                    className,
                )}
            >
                <DialogPrimitive.Title className="text-xl leading-[1.1] font-black tracking-[-0.02em]">
                    {title}
                </DialogPrimitive.Title>
                <DialogPrimitive.Description className="mt-2 text-[13px] text-text-3">
                    {description}
                </DialogPrimitive.Description>
                <div className="mt-5">{children}</div>
                <DialogPrimitive.Close className="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-btn border border-line-2 text-[13px] font-bold text-text hover:bg-surface-3">
                    Close
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPortal>
    );
}
