<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import FestivalController from '@/actions/App/Http/Controllers/Dashboard/FestivalController';
import FestivalFields, {
    type FestivalDetails,
} from '@/components/dashboard/FestivalFields.vue';
import FormErrors from '@/components/dashboard/FormErrors.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useAccountCrumb } from '@/composables/useAccountName';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Managed } from '@/types';

type Festival = FestivalDetails & {
    id: number;
    promoter: Managed;
};

const props = defineProps<{ festival: Festival }>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Festivals', href: FestivalController.index().url },
    {
        title: props.festival.name,
        href: FestivalController.edit(props.festival.id).url,
    },
];

const confirmDelete = (): boolean =>
    confirm(
        `Delete “${props.festival.name}”? Its listings stay up, no longer in a festival, but its gig widgets are deleted and stop working on other websites. This cannot be undone.`,
    );
</script>

<template>
    <Head :title="`Edit ${festival.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading title="Edit festival" :subject="festival.name" />

            <Form
                v-bind="FestivalController.update.form(festival.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" subject="festival" />

                <p class="text-sm text-muted-foreground">
                    Put on by
                    <span class="font-medium text-foreground">{{
                        festival.promoter.name
                    }}</span
                    >. A festival stays with the promoter it was made for.
                </p>

                <FestivalFields :festival="festival" :errors="errors" />

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Link
                        :href="FestivalController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>

            <div class="space-y-4 rounded-xl border border-red-600/30 p-4">
                <Heading
                    title="Delete this festival"
                    description="Its listings stay up, no longer in a festival. Its gig widgets are deleted and stop working on other websites."
                />

                <Form
                    v-bind="FestivalController.destroy.form(festival.id)"
                    :on-before="confirmDelete"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        {{ processing ? 'Deleting…' : 'Delete festival' }}
                    </Button>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
