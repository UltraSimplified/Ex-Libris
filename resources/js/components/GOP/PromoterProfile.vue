<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Promoter } from '@/types/global';

/**
 * The top of a promoter's page of listings, under its title, set as a
 * venue's is: its banner, what it says about itself, then how to reach
 * it. A promoter has no one place, so there is no address or map. Each
 * part is left out when the promoter has nothing for it.
 *
 * A festival's page is headed the same way, with its own banner and
 * details and a line naming the promoter putting it on. A promoter's own
 * page lists its festivals still to come.
 */
interface Props {
    promoter: Promoter;
    /** The promoter putting a festival on, on the festival's page. */
    by?: { id: number; name: string } | null;
    /** A promoter's festivals still to come, on its own page. */
    festivals?: { id: number; name: string }[];
}

const props = defineProps<Props>();
const page = usePage();

const bannerUrl = computed<string | null>(() =>
    props.promoter.banner
        ? `${page.props.cdnUrl}/${props.promoter.banner.replace(/^\//, '')}`
        : null,
);

const about = computed<string>(() => (props.promoter.about ?? '').trim());
const phone = computed<string>(() => (props.promoter.phone ?? '').trim());
const email = computed<string>(() => (props.promoter.email ?? '').trim());
/** Where the banner and the Website line go: tagged as Good on Paper's referral. */
const websiteHref = computed<string | null>(
    () => props.promoter.website_link ?? props.promoter.website ?? null,
);
/** The address as the promoter gave it, which is what the reader is shown. */
const websiteText = computed<string>(() =>
    (props.promoter.website ?? '').trim(),
);
const hasContact = computed<boolean>(() =>
    Boolean(email.value || websiteText.value || phone.value),
);
const festivals = computed(() => props.festivals ?? []);
/** Whether there is anything to say under the banner. */
const hasText = computed<boolean>(() =>
    Boolean(
        about.value ||
        hasContact.value ||
        props.by ||
        festivals.value.length,
    ),
);

const slugify = (text: string): string =>
    text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
</script>

<template>
    <section :aria-label="props.promoter.name">
        <!--
            Inset and rounded, filling the column up to 666px and no wider,
            and a way to the promoter's website where it has one.
        -->
        <div
            v-if="bannerUrl"
            :class="[
                'border-silver px-5 pt-5 md:border-l',
                hasText ? '' : 'border-b pb-5',
            ]"
        >
            <component
                :is="websiteHref ? 'a' : 'div'"
                :href="websiteHref ?? undefined"
                :target="websiteHref ? '_blank' : undefined"
                :rel="websiteHref ? 'noreferrer nofollow' : undefined"
                class="block w-full max-w-[666px]"
            >
                <img
                    :src="bannerUrl"
                    :alt="props.promoter.name"
                    class="block h-auto w-full rounded-md"
                />
            </component>
        </div>

        <!--
            What the promoter says about itself, kept to its own line breaks,
            then how to reach it as a compact block of labelled lines.
        -->
        <div v-if="hasText" class="border-b border-silver px-5 py-8 md:border-l">
            <div class="max-w-[666px] text-lg md:text-base">
                <p
                    v-if="props.by"
                    data-test="festival-by"
                    :class="about || hasContact ? 'mb-4' : ''"
                >
                    A festival by
                    <Link
                        :href="`/listings/promoter/${props.by.id}/${slugify(props.by.name)}`"
                        class="underline underline-offset-2"
                        >{{ props.by.name }}</Link
                    >.
                </p>

                <p v-if="about" class="whitespace-pre-line">{{ about }}</p>

                <address
                    v-if="hasContact"
                    :class="['not-italic', about ? 'mt-4' : '']"
                >
                    <span v-if="email" class="block break-all">
                        Email:
                        <a
                            :href="`mailto:${email}`"
                            class="underline underline-offset-2"
                            >{{ email }}</a
                        >
                    </span>
                    <span v-if="websiteText" class="block break-all">
                        Website:
                        <a
                            :href="websiteHref ?? websiteText"
                            target="_blank"
                            rel="noreferrer nofollow"
                            class="underline underline-offset-2"
                            >{{ websiteText }}</a
                        >
                    </span>
                    <span v-if="phone" class="block">
                        Telephone:
                        <a
                            :href="`tel:${phone.replace(/[^\d+]/g, '')}`"
                            class="underline underline-offset-2"
                            >{{ phone }}</a
                        >
                    </span>
                </address>

                <p
                    v-if="festivals.length"
                    data-test="promoter-festivals"
                    :class="about || hasContact ? 'mt-4' : ''"
                >
                    Festivals:
                    <template
                        v-for="(festival, index) in festivals"
                        :key="festival.id"
                    >
                        <Link
                            :href="`/listings/festival/${festival.id}/${slugify(festival.name)}`"
                            class="underline underline-offset-2"
                            >{{ festival.name }}</Link
                        >{{ index < festivals.length - 1 ? ', ' : '' }}
                    </template>
                </p>
            </div>
        </div>
    </section>
</template>
