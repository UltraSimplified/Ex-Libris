export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    /** The note that runs under a byline. Writers only. */
    bio?: string | null;
    /** What the profile picture should be read as, on a byline. */
    avatar_alt_text?: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * What the office has granted an account. Staff hold all of them without
 * being marked with any, so these are already resolved when they arrive.
 */
export type Roles = {
    is_venue_mgr: boolean;
    is_promoter: boolean;
    is_writer: boolean;
};

/** Something this account runs, which the sidebar heads a section with. */
export type Managed = {
    id: number;
    name: string;
};

export type Auth = {
    user: User;
    /** The promoters this account runs, by name. */
    promoters: Managed[];
    /** The venues this account runs, by name. */
    venues: Managed[];
    /** Whether this account has anything to look at under Orders. */
    hasOrders: boolean;
    roles: Roles;
    /** Where the office is, for staff; null for everybody else. */
    office: string | null;
    /** Who switched to this account from the Office, while they are in it. */
    switchedFrom: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
