import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { Advert } from '@/types/global';

/**
 * The live in-list adverts a page can set among its own rows, in the order to
 * show them: starting from the one after listingAdvert, the in-list advert
 * whose turn this page view is, so the slots share out evenly from one view
 * to the next. Sponsorships are never among them; they have the panel. An advert with only one creative still takes its turn:
 * its slot shows at the size it has a creative for and is left out at the
 * other (see ListingAdvert). Only an advert with neither is passed over.
 */
export function useAdvertLineUp(): ComputedRef<Advert[]> {
    const page = usePage();

    return computed<Advert[]>(() => {
        const live = (page.props.liveAdverts ?? []).filter(
            (advert) => advert.desktop_image || advert.mobile_image,
        );
        const shown = live.findIndex(
            (advert) => advert.id === page.props.listingAdvert?.id,
        );

        return [...live.slice(shown + 1), ...live.slice(0, shown + 1)];
    });
}
