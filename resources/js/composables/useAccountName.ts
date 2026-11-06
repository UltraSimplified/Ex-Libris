import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

export type AccountName = 'Account' | 'Backstage';

/**
 * What the dashboard is called for whoever is looking: Backstage for an
 * account that works with the magazine - a publisher, promoter, venue,
 * writer or the office - and Account for a guest who only reads it.
 */
export function useAccountName(): ComputedRef<AccountName> {
    const page = usePage();

    return computed<AccountName>(() =>
        page.props.auth?.backstage ? 'Backstage' : 'Account',
    );
}

/**
 * The first crumb on every dashboard page, named as the dashboard is.
 */
export function useAccountCrumb(): BreadcrumbItem {
    return { title: useAccountName().value, href: dashboard().url };
}
