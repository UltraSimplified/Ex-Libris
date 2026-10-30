<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AddressController from '@/actions/App/Http/Controllers/Settings/AddressController';
import { edit } from '@/routes/addresses';
import { type BreadcrumbItem } from '@/types';
import Heading from '../../components/Heading.vue';
import InputError from '../../components/InputError.vue';
import AddressFields, {
    type Address,
} from '../../components/settings/AddressFields.vue';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsLayout from '../../layouts/settings/Layout.vue';

const props = withDefaults(
    defineProps<{
        billing?: Address | null;
        delivery?: Address | null;
        phone?: string | null;
        status?: string;
    }>(),
    {
        billing: null,
        delivery: null,
        phone: null,
        status: undefined,
    },
);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Addresses',
        href: edit().url,
    },
];

/**
 * Having no delivery address on file is what "same as billing" means, so
 * the box starts ticked for anyone who has never given a second one.
 */
const sameAsBilling = ref<boolean>(props.delivery === null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Addresses" />

        <h1 class="sr-only">Addresses</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    title="Addresses"
                    description="Where you are billed, and where your post goes"
                />

                <Form
                    v-bind="AddressController.update.form()"
                    class="space-y-8"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <section class="space-y-4">
                        <h2
                            class="text-xl font-semibold tracking-tight uppercase"
                        >
                            Contact
                        </h2>

                        <div class="grid gap-2">
                            <Label for="phone">Contact phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                :default-value="phone ?? ''"
                                autocomplete="tel"
                                placeholder="e.g. 07700 900123"
                            />
                            <p class="text-sm text-muted-foreground">
                                Used on any listing you send in, so the Good on
                                Paper office can check the details with you.
                            </p>
                            <InputError :message="errors.phone" />
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2
                            class="text-xl font-semibold tracking-tight uppercase"
                        >
                            Billing address
                        </h2>
                        <AddressFields
                            name="billing"
                            :address="billing"
                            :errors="errors"
                        />
                    </section>

                    <section class="space-y-4">
                        <h2
                            class="text-xl font-semibold tracking-tight uppercase"
                        >
                            Delivery address
                        </h2>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="sameAsBilling"
                                type="checkbox"
                                name="delivery_same_as_billing"
                                value="1"
                                class="size-4 rounded border-input"
                            />
                            My delivery address is the same as my billing
                            address
                        </label>

                        <!--
                            Left out of the document entirely rather than
                            hidden, so a delivery address the box has since
                            put away is not posted back alongside it.
                        -->
                        <AddressFields
                            v-if="!sameAsBilling"
                            name="delivery"
                            :address="delivery"
                            :errors="errors"
                        />
                    </section>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="save-addresses"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful || status"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
