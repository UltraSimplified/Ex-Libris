<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { type BreadcrumbItem } from '@/types';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import ListingFormFields from '../../../components/dashboard/ListingFormFields.vue';
import Heading from '../../../components/Heading.vue';
import { Button } from '../../../components/ui/button';
import AppLayout from '../../../layouts/AppLayout.vue';

type Option = { id: number; name: string; town?: string | null };

defineProps<{
    categories: Option[];
    venues: Option[];
    promoters: Option[];
    managesVenue: boolean;
    canAddVenue: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Listings', href: ListingController.index().url },
    { title: 'Add a listing', href: ListingController.create().url },
];
</script>

<template>
    <Head title="Add a listing" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading
                title="Add a listing"
                description="Tell us about your event and it will appear on your dashboard."
            />

            <Form
                v-bind="ListingController.store.form()"
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
                />

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Create listing' }}
                    </Button>
                    <Link
                        :href="ListingController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
