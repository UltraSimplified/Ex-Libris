<script setup lang="ts">
import InputError from '../InputError.vue';
import { Input } from '../ui/input';
import { Label } from '../ui/label';

export type Address = {
    line_one?: string | null;
    line_two?: string | null;
    town?: string | null;
    county?: string | null;
    postcode?: string | null;
    country?: string | null;
};

const props = withDefaults(
    defineProps<{
        /** Posted as billing[line_one] and so on. */
        name: 'billing' | 'delivery';
        address?: Address | null;
        errors: Record<string, string>;
    }>(),
    {
        address: null,
    },
);

/** Somewhere to start for a magazine posted almost entirely within it. */
const DEFAULT_COUNTRY = 'United Kingdom';

const field = (key: keyof Address): string => `${props.name}[${key}]`;

const id = (key: keyof Address): string => `${props.name}-${key}`;

const error = (key: keyof Address): string | undefined =>
    props.errors[`${props.name}.${key}`];
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label :for="id('line_one')">Address</Label>
            <Input
                :id="id('line_one')"
                :name="field('line_one')"
                :default-value="address?.line_one ?? ''"
                autocomplete="address-line1"
                placeholder="House name or number, and street"
            />
            <InputError :message="error('line_one')" />
        </div>

        <div class="grid gap-2">
            <Label :for="id('line_two')">
                Address line 2
                <span class="text-muted-foreground">(optional)</span>
            </Label>
            <Input
                :id="id('line_two')"
                :name="field('line_two')"
                :default-value="address?.line_two ?? ''"
                autocomplete="address-line2"
            />
            <InputError :message="error('line_two')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="id('town')">Town or city</Label>
                <Input
                    :id="id('town')"
                    :name="field('town')"
                    :default-value="address?.town ?? ''"
                    autocomplete="address-level2"
                />
                <InputError :message="error('town')" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('county')">
                    County
                    <span class="text-muted-foreground">(optional)</span>
                </Label>
                <Input
                    :id="id('county')"
                    :name="field('county')"
                    :default-value="address?.county ?? ''"
                    autocomplete="address-level1"
                />
                <InputError :message="error('county')" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('postcode')">Postcode</Label>
                <Input
                    :id="id('postcode')"
                    :name="field('postcode')"
                    :default-value="address?.postcode ?? ''"
                    autocomplete="postal-code"
                />
                <InputError :message="error('postcode')" />
            </div>

            <div class="grid gap-2">
                <Label :for="id('country')">Country</Label>
                <Input
                    :id="id('country')"
                    :name="field('country')"
                    :default-value="address?.country ?? DEFAULT_COUNTRY"
                    autocomplete="country-name"
                />
                <InputError :message="error('country')" />
            </div>
        </div>
    </div>
</template>
