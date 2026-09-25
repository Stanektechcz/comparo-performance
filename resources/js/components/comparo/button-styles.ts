/** Comparo button hierarchy (DESIGN-SYSTEM.md "Button hierarchy"). */
export const buttonStyles = {
    primary:
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-btn bg-acc px-4 text-[13px] font-extrabold text-acc-ink transition-[filter] hover:brightness-95',
    secondary:
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-btn border border-line-2 bg-transparent px-4 text-[13px] font-bold text-text transition-colors hover:bg-surface-3',
    tertiary:
        'inline-flex min-h-tap items-center gap-1 text-xs font-bold text-acc-text underline-offset-4 hover:underline',
    disabled:
        'inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-btn border border-line px-4 text-[13px] font-bold text-text-4',
    icon: 'inline-flex size-10 items-center justify-center rounded-field border border-line-2 bg-field text-text-2 transition-colors hover:text-text',
} as const;

export const selectStyles =
    'min-h-10 rounded-field border border-line-2 bg-field px-3 text-[13px] font-semibold text-text';
