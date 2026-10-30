<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import BrandFields from '../../../components/dashboard/BrandFields.vue';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import ImageField from '../../../components/dashboard/ImageField.vue';
import Heading from '../../../components/Heading.vue';
import InputError from '../../../components/InputError.vue';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import AppLayout from '../../../layouts/AppLayout.vue';

type Venue = {
    id: number;
    name: string;
    address: string | null;
    town: string | null;
    postcode: string | null;
    url: string | null;
    phone: string | null;
    email: string | null;
    cover_photo: string | null;
    description: string | null;
    brand_colour: string | null;
    accent_colour1: string | null;
    accent_colour2: string | null;
    brand_background_colour: string | null;
};

const props = defineProps<{ venue: Venue; publicUrl: string }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Venues', href: VenueController.index().url },
    {
        title: props.venue.name,
        href: VenueController.edit(props.venue.id).url,
    },
];

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <Head :title="`Edit ${venue.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading title="Edit venue" :subject="venue.name">
                <a
                    :href="publicUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                >
                    View venue listings page
                    <ExternalLink class="size-3.5" />
                </a>
            </Heading>

            <Form
                v-bind="VenueController.update.form(venue.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" subject="venue" />

                <div class="grid gap-6">
                    <ImageField
                        name="cover_photo"
                        label="Cover photo"
                        shape="cover"
                        :existing="venue.cover_photo"
                        hint="JPEG, PNG or WebP, up to 8MB. Kept in its own shape, at up to 1280 pixels wide."
                        :error="errors.cover_photo"
                    />

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            maxlength="2000"
                            :class="fieldClass"
                            placeholder="What the place is like, and what goes on there. Leave a blank line between paragraphs."
                            :value="venue.description ?? ''"
                        ></textarea>
                        <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="venue.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            name="address"
                            :default-value="venue.address ?? ''"
                        />
                        <InputError :message="errors.address" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="town">Town</Label>
                            <Input
                                id="town"
                                name="town"
                                :default-value="venue.town ?? ''"
                            />
                            <InputError :message="errors.town" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="postcode">Postcode</Label>
                            <Input
                                id="postcode"
                                name="postcode"
                                :default-value="venue.postcode ?? ''"
                            />
                            <InputError :message="errors.postcode" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="url">Website</Label>
                        <Input
                            id="url"
                            name="url"
                            type="url"
                            :default-value="venue.url ?? ''"
                            placeholder="https://example.com"
                        />
                        <InputError :message="errors.url" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                autocomplete="tel"
                                :default-value="venue.phone ?? ''"
                            />
                            <InputError :message="errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                :default-value="venue.email ?? ''"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <BrandFields
                        :colours="venue"
                        :errors="errors"
                        hint="Your gig widgets are painted in these. Leave any unset to use Good on Paper's own."
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Link
                        :href="VenueController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
