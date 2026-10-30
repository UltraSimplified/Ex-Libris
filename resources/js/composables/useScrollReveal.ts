import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Composable that observes a container element and animates each child into
 * view as it enters the viewport. Styles are applied inline to avoid Tailwind
 * purging dynamically added class names.
 *
 * Stagger behaviour adapts to the current grid column count — on single-column
 * (mobile) layouts each card slides up individually with no horizontal stagger.
 * On multi-column layouts cards reveal left to right within each row.
 *
 * @param {string} childSelector  - CSS selector for children to observe (default: ':scope > div').
 * @param {number} threshold      - Intersection threshold 0–1 at which to trigger (default: 0.05).
 * @param {number} staggerMs      - Delay increment in ms between each card in a row (default: 100).
 * @returns {{ containerRef: Ref<HTMLElement | null>, revealAll: () => void }}
 */
export function useScrollReveal(
    childSelector = ':scope > div',
    threshold = 0.05,
    staggerMs = 100,
) {
    const containerRef = ref<HTMLElement | null>(null);
    let observer: IntersectionObserver | null = null;

    /**
     * Derives the number of columns from the container's computed grid style.
     *
     * @param {HTMLElement} container
     * @returns {number}
     */
    function getColumnCount(container: HTMLElement): number {
        const columns = getComputedStyle(container).gridTemplateColumns;
        return columns.split(' ').filter(Boolean).length;
    }

    onMounted(() => {
        if (!containerRef.value) return;

        const children =
            containerRef.value.querySelectorAll<HTMLElement>(childSelector);
        const columns = getColumnCount(containerRef.value);

        children.forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';

            if (columns === 1) {
                // Mobile: slide up only, no stagger
                el.style.transform = 'translateY(24px)';
                el.dataset.revealDelay = '0';
            } else {
                // Multi-column: slide up with left-to-right stagger per row
                el.style.transform = 'translateY(32px)';
                el.dataset.revealDelay = String((index % columns) * staggerMs);
            }
        });

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const el = entry.target as HTMLElement;
                        const delay = parseInt(el.dataset.revealDelay ?? '0');
                        setTimeout(() => {
                            el.style.opacity = '1';
                            el.style.transform = 'translateY(0)';
                        }, delay);
                        observer?.unobserve(el);
                    }
                });
            },
            { threshold, rootMargin: '0px 0px -60px 0px' },
        );

        children.forEach((el) => observer?.observe(el));
    });

    onUnmounted(() => observer?.disconnect());

    /**
     * Hand every child back to the stylesheet, cancelling the staggered
     * reveal for the rest of the page's life.
     *
     * Filtering a list moves rows the observer has never reached into view
     * without anybody scrolling, so a row that survives a filter could sit
     * there still holding the opacity it was given at mount. The inline
     * styles are removed rather than set to their finished values: left in
     * place they would outrank any transition a filter wants to run.
     */
    function revealAll(): void {
        if (!containerRef.value) {
            return;
        }

        containerRef.value
            .querySelectorAll<HTMLElement>(childSelector)
            .forEach((el) => {
                el.style.removeProperty('opacity');
                el.style.removeProperty('transform');
                el.style.removeProperty('transition');
                observer?.unobserve(el);
            });
    }

    return { containerRef, revealAll };
}
