<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { ref } from 'vue';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { type BreadcrumbItem } from '@/types';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import ListingFormFields from '../../../components/dashboard/ListingFormFields.vue';
import ListingPreview from '../../../components/dashboard/ListingPreview.vue';
import Heading from '../../../components/Heading.vue';
import { Button } from '../../../components/ui/button';
import AppLayout from '../../../layouts/AppLayout.vue';

type Option = { id: number; name: string; town?: string | null };

type GalleryImage = {
    key: string;
    attributes: { image: string; caption: string | null };
};

const props = defineProps<{
    listing: Record<string, never> & { id: number; title: string };
    gallery: GalleryImage[];
    lineUp: { name: string; playlist: string | null; video: string | null }[];
    /** The days the event is on, YYYY-MM-DD, when not every day. */
    specificDays: string[];
    categories: Option[];
    venues: Option[];
    promoters: Option[];
    managesVenue: boolean;
    canAddVenue: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Listings', href: ListingController.index().url },
    {
        title: props.listing.title,
        href: ListingController.edit(props.listing.id).url,
    },
];

/** Shows the listing as last saved, the way the public will see it. */
const previewing = ref(false);

const confirmDelete = (): boolean =>
    confirm(`Delete “${props.listing.title}”? This cannot be undone.`);
</script>

<template>
    <Head :title="`Edit ${listing.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading title="Edit listing" :subject="listing.title">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    @click="previewing = true"
                >
                    <Eye class="size-3.5" />
                    Preview listing, as last saved
                </button>
            </Heading>

            <Form
                v-bind="ListingController.update.form(listing.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" />

                <ListingFormFields
                    :categories="categories"
                    :venues="venues"
                    :promoters="promoters"
                    :manages-venue="managesVenue"
                    :can-add-venue="canAddVenue"
                    :errors="errors"
                    :listing="listing"
                    :gallery="gallery"
                    :line-up="lineUp"
                    :specific-days="specificDays"
                />

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Link
                        :href="ListingController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>

            <div class="space-y-4 rounded-xl border border-red-600/30 p-4">
                <Heading
                    title="Delete this listing"
                    description="Once deleted, the listing and its image are gone for good."
                />

                <Form
                    v-bind="ListingController.destroy.form(listing.id)"
                    :on-before="confirmDelete"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        {{ processing ? 'Deleting…' : 'Delete listing' }}
                    </Button>
                </Form>
            </div>
        </div>

        <ListingPreview
            v-if="previewing"
            :id="listing.id"
            :title="listing.title"
            @close="previewing = false"
        />
    </AppLayout>
</template>
