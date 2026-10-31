<script setup lang="ts">
import { EnvelopeIcon } from '@heroicons/vue/24/outline';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { logout } from '@/routes';

interface Props {
    /**
     * On the feature page, line up with its rule, 2.5rem further left.
     */
    featureLayout?: boolean;
}

const props = defineProps<Props>();
const page = usePage();

/**
 * Somebody signed in who manages no venue, promotes nothing and writes
 * nothing has no dashboard yet, so Account becomes the way to sign out.
 * Staff hold every role, so they keep it.
 */
const reader = computed(
    () =>
        Boolean(page.props.auth?.user) &&
        !Object.values(page.props.auth.roles ?? {}).some(Boolean),
);

/** Prefetched pages were drawn for the account, so they go with it. */
const signOut = () => router.flushAll();

/**
 * Brings back the cookie banner from the page shell (app.blade.php), so a
 * visitor can change their mind. It is left alone if it is already showing.
 */
const openCookieConsent = () => {
    const banner = document.getElementById('scify-cookies-consent');
    const toggle = (window as Window & { toggleCookieBanner?: () => void })
        .toggleCookieBanner;

    if (banner && banner.style.display !== 'block' && toggle) {
        toggle();
    }
};

const currentYear = computed(() => new Date().getFullYear());

const word = computed(() => {
    const words = ['PRINTED', 'SPOKEN', 'DIGITAL', 'WRITTEN'];
    return words[Math.floor(Math.random() * words.length)];
});
</script>
<template>
    <div
        class="md:w-full"
        :class="props.featureLayout ? 'md:pl-38' : 'md:pl-48'"
    >
        <!-- FOOTER -->
        <div
            class="site-footer size-full border-silver px-5 py-5 pt-10 md:border-l"
        >
            <div class="md:grid md:grid-cols-2 md:gap-8">
                <!-- Contact column -->
                <div>
                    <h2 class="text-xl">Contact.</h2>
                    <p class="text-md mt-10">
                        General enquiries<br />
                        <a
                            href="mailto:info@goodonpaper.info"
                            class="hover: inline-flex items-center gap-1.5 text-gray-600 underline dark:text-white dark:hover:text-gray-400"
                            ><EnvelopeIcon
                                class="left size-4 text-black dark:text-white"
                            />info@goodonpaper.info</a
                        >
                    </p>
                    <p class="text-md mt-10">
                        Advertising<br />
                        <a
                            href="mailto:alex@goodonpaper.info"
                            class="hover: inline-flex items-center gap-1.5 text-gray-600 underline dark:text-white dark:hover:text-gray-400"
                            ><EnvelopeIcon
                                class="left size-4 text-black dark:text-white"
                            />alex@goodonpaper.info</a
                        >
                    </p>
                </div>

                <!-- Useful Stuff column -->
                <div class="mt-10 md:mt-0">
                    <h2 class="text-xl">Useful Stuff.</h2>
                    <nav class="mt-8 md:mt-10">
                        <div class="flex gap-20 md:gap-8">
                            <ul class="flex flex-col space-y-4 md:space-y-6">
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/who-we-are/">Who we are.</Link>
                                </li>
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/our-writers/"
                                        >Our Writers.</Link
                                    >
                                </li>
                                <!--li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/directory/">Directory</Link>
                                </li -->
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/privacy/">Privacy.</Link>
                                </li>
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/contact/">Contact.</Link>
                                </li>
                            </ul>
                            <ul class="flex flex-col space-y-4 md:space-y-6">
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link
                                        v-if="reader"
                                        :href="logout()"
                                        as="button"
                                        class="cursor-pointer text-left"
                                        @click="signOut"
                                        >Log out
                                        {{ page.props.auth.user.name }}</Link
                                    >
                                    <Link v-else href="/dashboard/"
                                        >Account.</Link
                                    >
                                </li>
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/faqs/">FAQs.</Link>
                                </li>
                                <!--li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/sponsors/">Sponsors</Link>
                                </li -->
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <Link href="/terms/">Terms.</Link>
                                </li>
                                <li
                                    class="text-black hover:text-gray-600 dark:text-white dark:hover:text-gray-400"
                                >
                                    <button
                                        type="button"
                                        class="cursor-pointer text-left"
                                        @click="openCookieConsent"
                                    >
                                        Cookie consent.
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>
            </div>

            <footer class="mt-10">
                <h2
                    class="logo font-haas text-[20px]/[18px] font-bold tracking-tight text-black dark:text-white"
                >
                    LONG<br />LIVE THE<br />{{ word }}<br />WORD
                </h2>
                <p class="mt-5">
                    &copy; {{ currentYear }} Good on Paper. Designed by Good on
                    Paper, developed by
                    <a
                        href="https://ultrasimplified.com/"
                        target="_blank"
                        class="underline"
                        rel="noreferrer nofollow"
                        >UltraSimplified</a
                    >.
                </p>
            </footer>
        </div>
    </div>
</template>
<style scoped></style>
