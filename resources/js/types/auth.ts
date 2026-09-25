export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    /** Shared by the backend on every page; staff can reach admin tools. */
    is_staff: boolean;
    /**
     * Shared on every page (cheap `exists()` query): true when the user
     * belongs to at least one merchant. Drives merchant nav visibility
     * outside `/merchant/*`; the full switcher data stays in
     * `merchantContext`, which is only bound inside the merchant route
     * group.
     */
    has_merchant_access: boolean;
    /**
     * Minimal staff capability flags (never the full permission list),
     * computed via Gate/can. Present for every authenticated user; only
     * meaningful for staff.
     */
    staff_can: {
        review_matching: boolean;
    };
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** `user` is null for guests (public catalogue pages). */
export type Auth = {
    user: User | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
