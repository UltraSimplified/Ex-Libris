// resources/js/composables/useAdImpression.ts
import { router } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, type Ref } from 'vue';

/**
 * Fire a viewable-impression beacon (IAB standard: ≥50% visible
 * for 1 second) for whichever advert variant is currently shown.
 * Dedupes to one impression per page view, and re-arms on Inertia
 * navigation since the banner lives in the persistent layout.
 *
 * @param advertId  - Ref to the live advert's id (null when no advert)
 * @param targets   - Map of variant name to element ref
 * @param placement - Where on the page it sits: the sponsorship panel or a
 *                    slot among the listings, so the report can split by it
 */
export function useAdImpression(
    advertId: Ref<number | null>,
    targets: Record<'mobile' | 'desktop', Ref<HTMLElement | null>>,
    placement: 'panel' | 'list',
) {
    const fired = ref(false);
    let observer: IntersectionObserver | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const send = (variant: string) => {
        if (fired.value || !advertId.value) return;
        fired.value = true;

        const payload = new Blob(
            [
                JSON.stringify({
                    page: window.location.pathname,
                    variant,
                    placement,
                }),
            ],
            { type: 'application/json' },
        );
        navigator.sendBeacon(`/ad/${advertId.value}/impression`, payload);
        observer?.disconnect();
    };

    const observe = () => {
        observer?.disconnect();
        if (!advertId.value) return;

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    const variant =
                        entry.target === targets.desktop.value
                            ? 'desktop'
                            : 'mobile';

                    if (entry.intersectionRatio >= 0.5) {
                        timer = setTimeout(() => send(variant), 1000);
                    } else if (timer) {
                        clearTimeout(timer);
                    }
                }
            },
            { threshold: [0.5] },
        );

        Object.values(targets).forEach(
            (t) => t.value && observer!.observe(t.value),
        );
    };

    onMounted(() => {
        observe();

        // The layout persists across Inertia visits, so onMounted only
        // runs once per full page load. Re-arm per navigation so each
        // page view can log its own impression.
        router.on('navigate', () => {
            fired.value = false;
            observe();
        });
    });

    onBeforeUnmount(() => observer?.disconnect());
}
