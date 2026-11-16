<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import FestivalController from '@/actions/App/Http/Controllers/Dashboard/FestivalController';
import FestivalFields, {
    type FestivalDetails,
} from '@/components/dashboard/FestivalFields.vue';
import FormErrors from '@/components/dashboard/FormErrors.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAccountCrumb } from '@/composables/useAccountName';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Managed } from '@/types';

const props = defineProps<{
    /** The promoters this account runs, any of which may put it on. */
    promoters: Managed[];
    /** The one it came from, or the first. */
    promoterId: number;
    /** The festivals this account runs, newest first, to start from. */
    earlier: Managed[];
    /** The one chosen to start from, whose details fill the form. */
    startingFrom: (FestivalDetails & { id: number }) | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Festivals', href: FestivalController.index().url },
    { title: 'New festival', href: FestivalController.create().url },
];

const blank: FestivalDetails = {
    name: '',
    website: null,
    email: null,
    phone: null,
    about: null,
    logo: null,
    banner: null,
    brand_colour: null,
    accent_colour1: null,
    accent_colour2: null,
    brand_background_colour: null,
};

/**
 * What the form opens with: the earlier festival's details when it starts
 * from one, under a name of its own for the account to give.
 */
const details = computed<FestivalDetails>(() =>
    props.startingFrom ? { ...props.startingFrom, name: '' } : blank,
);

/** Reload the form filled from the festival chosen, or blank. */
const startFrom = (event: Event): void => {
    const from = (event.target as HTMLSelectElement).value;

    router.visit(
        FestivalController.create.url(from ? { query: { from } } : undefined),
    );
};

const onlyPromoter = props.promoters.length === 1 ? props.promoters[0] : null;

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <Head title="New festival" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading
                title="New festival"
                description="A set of your events, across one or more venues, under a name and look of its own. It goes up as soon as you save it."
            />

            <div v-if="earlier.length > 0" class="grid gap-2">
                <Label for="start_from">Start from</Label>
                <select
                    id="start_from"
                    :class="fieldClass"
                    :value="startingFrom?.id ?? ''"
                    @change="startFrom"
                >
                    <option value="">A blank form</option>
                    <option
                        v-for="festival in earlier"
                        :key="festival.id"
                        :value="festival.id"
                    >
                        {{ festival.name }}
                    </option>
                </select>
                <p class="text-sm text-muted-foreground">
                    One of your festivals, such as last year's. Its details,
                    colours and pictures are filled in for you to change, and
                    it stays as it is.
                </p>
            </div>

            <!--
                Keyed by the festival it starts from, so its fields, which
                keep what is typed into them, refill when that changes.
            -->
            <Form
                :key="startingFrom?.id ?? 'blank'"
                v-bind="FestivalController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" subject="festival" />

                <input
                    v-if="startingFrom"
                    type="hidden"
                    name="from"
                    :value="startingFrom.id"
                />

                <!--
                    The promoter is chosen once, here, and stays with the
                    festival. One promoter needs no choosing.
                -->
                <input
                    v-if="onlyPromoter"
                    type="hidden"
                    name="promoter_id"
                    :value="onlyPromoter.id"
                />
                <div v-else class="grid gap-2">
                    <Label for="promoter_id">Promoter</Label>
                    <select
                        id="promoter_id"
                        name="promoter_id"
                        :class="fieldClass"
                        required
                    >
                        <option
                            v-for="promoter in promoters"
                            :key="promoter.id"
                            :value="promoter.id"
                            :selected="promoter.id === promoterId"
                        >
                            {{ promoter.name }}
                        </option>
                    </select>
                    <p class="text-sm text-muted-foreground">
                        This can't be changed once the festival is made.
                    </p>
                    <InputError :message="errors.promoter_id" />
                </div>

                <FestivalFields :festival="details" :errors="errors" />

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Create festival' }}
                    </Button>
                    <Link
                        :href="FestivalController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
