<script setup lang="ts">
/**
 * What a festival is known by, as its owner fills it in: the same details
 * a promoter has, all of them the festival's own. Shared by the new and
 * edit forms, which differ only in what they say about the promoter.
 */
import BrandFields from '@/components/dashboard/BrandFields.vue';
import ImageField from '@/components/dashboard/ImageField.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type FestivalDetails = {
    name: string;
    website: string | null;
    email: string | null;
    phone: string | null;
    about: string | null;
    logo: string | null;
    banner: string | null;
    brand_colour: string | null;
    accent_colour1: string | null;
    accent_colour2: string | null;
    brand_background_colour: string | null;
};

defineProps<{
    festival: FestivalDetails;
    errors: Record<string, string | undefined>;
}>();

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                name="name"
                :default-value="festival.name"
                placeholder="Riverside Fest 2027"
                required
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="festival.email ?? ''"
                    placeholder="tickets@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input
                    id="phone"
                    name="phone"
                    :default-value="festival.phone ?? ''"
                />
                <InputError :message="errors.phone" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="website">Website</Label>
            <Input
                id="website"
                name="website"
                type="url"
                :default-value="festival.website ?? ''"
                placeholder="https://example.com"
            />
            <InputError :message="errors.website" />
        </div>

        <div class="grid gap-2">
            <Label for="about">About</Label>
            <textarea
                id="about"
                name="about"
                rows="6"
                :class="fieldClass"
                placeholder="A short introduction to the festival: where, when and what to expect."
                :value="festival.about ?? ''"
            ></textarea>
            <InputError :message="errors.about" />
        </div>

        <ImageField
            name="logo"
            label="Logo"
            :existing="festival.logo"
            shape="square"
            hint="JPEG, PNG or WebP, up to 8MB. Cropped square to 512×512, filled from the middle. This is the mark on the festival's event covers."
            :error="errors.logo"
        />

        <ImageField
            name="banner"
            label="Banner"
            shape="cover"
            :existing="festival.banner"
            hint="JPEG, PNG or WebP, up to 8MB. Kept in its own shape, at up to 1280 pixels wide. It heads the festival's page."
            :error="errors.banner"
        />

        <BrandFields
            :colours="festival"
            :errors="errors"
            hint="The festival's gig widgets are painted in these, and its mark sits on the background colour. Leave any unset to use Good on Paper's own, not your promoter's."
        />
    </div>
</template>
