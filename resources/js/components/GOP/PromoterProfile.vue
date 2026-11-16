<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Promoter } from '@/types/global';

/**
 * The top of a promoter's page of listings, under its title, set as a
 * venue's is: its banner, what it says about itself, then how to reach
 * it. A promoter has no one place, so there is no address or map. Each
 * part is left out when the promoter has nothing for it.
 */
interface Props {
    promoter: Promoter;
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
                about || hasContact ? '' : 'border-b pb-5',
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
        <div
            v-if="about || hasContact"
            class="border-b border-silver px-5 py-8 md:border-l"
        >
            <div class="max-w-[666px] text-lg md:text-base">
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
            </div>
        </div>
    </section>
</template>
