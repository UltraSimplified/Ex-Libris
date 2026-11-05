import { onBeforeUnmount, onMounted, watch, type Ref } from 'vue';
import FeatureReadController from '@/actions/App/Http/Controllers/FeatureReadController';

/** Reading stops counting after this long without a scroll, key or touch. */
const IDLE_AFTER_MS = 60_000;

/** The longest one read is counted for: the server's cap on a report too. */
const LONGEST_READ_SECONDS = 1800;

const ACTIVITY = [
    'scroll',
    'wheel',
    'keydown',
    'pointerdown',
    'pointermove',
    'touchstart',
] as const;

/**
 * Times how long a feature is actually read, for the Office: only while
 * its tab is showing and the reader has scrolled, typed or touched within
 * the last minute, so a page left open does not count. Also notes whether
 * they reached the element marking the end of the article.
 *
 * Reports go by beacon each time the reader leaves - switching tab, closing
 * it, or moving to another page - carrying only the seconds since the last
 * report. The first report of a read says so, which is how the server
 * counts reads without being told anything about who is reading. Nothing is
 * kept on the reader's device.
 *
 * @param featureId - The feature being read, or null where nothing should
 *                    be counted, as in the Office's preview
 * @param end       - The element at the end of the article
 */
export function useFeatureReading(
    featureId: () => number | null,
    end: Ref<HTMLElement | null>,
) {
    let reading: number | null = null;
    let unsent = 0;
    let total = 0;
    let reported = false;
    let reachedEnd = false;
    let endReported = false;
    let lastActivity = Date.now();
    let ticker: ReturnType<typeof setInterval> | null = null;
    let observer: IntersectionObserver | null = null;

    const isReading = () =>
        document.visibilityState === 'visible' &&
        Date.now() - lastActivity < IDLE_AFTER_MS &&
        total < LONGEST_READ_SECONDS;

    const tick = () => {
        if (isReading()) {
            unsent++;
            total++;
        }
    };

    const noteActivity = () => {
        lastActivity = Date.now();
    };

    /*
     * Reaching the end only counts once a read has begun, so a reader who
     * lands at the foot of the page and leaves at once adds nothing.
     */
    const report = () => {
        const endIsNew =
            reachedEnd && !endReported && (reported || unsent > 0);

        if (reading === null || (unsent === 0 && !endIsNew)) {
            return;
        }

        const payload = {
            seconds: unsent,
            newRead: !reported,
            reachedEnd: endIsNew,
        };

        navigator.sendBeacon(
            FeatureReadController.url(reading),
            new Blob([JSON.stringify(payload)], { type: 'application/json' }),
        );

        reported = true;
        unsent = 0;
        endReported = endReported || endIsNew;
    };

    /** Close off the read under way, if any, and start on another. */
    const begin = (id: number | null) => {
        report();
        reading = id;
        unsent = 0;
        total = 0;
        reported = false;
        reachedEnd = false;
        endReported = false;
        lastActivity = Date.now();
    };

    const onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            report();
        } else {
            noteActivity();
        }
    };

    onMounted(() => {
        begin(featureId());
        ticker = setInterval(tick, 1000);

        ACTIVITY.forEach((type) =>
            document.addEventListener(type, noteActivity, {
                capture: true,
                passive: true,
            }),
        );
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', report);

        if (end.value) {
            observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    reachedEnd = true;
                }
            });
            observer.observe(end.value);
        }
    });

    watch(featureId, begin);

    onBeforeUnmount(() => {
        report();

        if (ticker) {
            clearInterval(ticker);
        }

        ACTIVITY.forEach((type) =>
            document.removeEventListener(type, noteActivity, { capture: true }),
        );
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('pagehide', report);
        observer?.disconnect();
    });
}
