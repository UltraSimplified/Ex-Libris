import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

/**
 * A band of the sidebar. A band with a heading has its items indented
 * under it; a band without one leads with the items themselves.
 */
export type NavGroup = {
    label?: string;
    /** Names what the band is for, over its entries without indenting them. */
    heading?: string;
    items: NavItem[];
};

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    /**
     * Whether this is the page being looked at. Worked out from the
     * address when nothing says otherwise, which is enough for entries
     * that differ by path - and not enough for the listings entries,
     * which differ only by what they are narrowed to.
     */
    isActive?: boolean;
    /**
     * What belongs under this entry, drawn indented beneath it: a
     * venue's own listings under the venue.
     */
    items?: NavItem[];
    /**
     * Run before following the link. Log out uses it to empty Inertia's
     * cache, so the next person to sign in on this browser is not handed
     * the last one's pages.
     */
    onSelect?: () => void;
    /**
     * A page this app does not render - Nova, say. Followed with an
     * ordinary browser navigation, because Inertia would choke on the
     * response.
     */
    external?: boolean;
};
