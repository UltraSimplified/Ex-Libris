<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { useInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';
import DeleteUser from '../../components/DeleteUser.vue';
import Heading from '../../components/Heading.vue';
import InputError from '../../components/InputError.vue';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsLayout from '../../layouts/settings/Layout.vue';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

const { getInitials } = useInitials();

/**
 * Whether this account writes for the magazine. Only a writer has a
 * byline, so only a writer is asked for one.
 */
const writer = computed<boolean>(() => page.props.auth.roles.is_writer);

/**
 * Avatars are held as a path on the Spaces disk - "avatars/xyz.webp" - so
 * they are resolved against the CDN, as UserInfo does. Anything already
 * absolute belongs elsewhere and is left alone.
 */
const existingAvatar = computed<string | null>(() => {
    const avatar = user.avatar;

    if (!avatar) {
        return null;
    }

    return /^(https?:)?\/\//.test(avatar)
        ? avatar
        : `${String(page.props.cdnUrl).replace(/\/$/, '')}/${avatar.replace(/^\//, '')}`;
});

const preview = ref<string | null>(null);
const removeAvatar = ref(false);

const releasePreview = (): void => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
};

const onAvatarChange = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    releasePreview();

    if (file) {
        preview.value = URL.createObjectURL(file);
        removeAvatar.value = false;
    }
};

onBeforeUnmount(releasePreview);

const shown = computed<string | null>(
    () => preview.value ?? (removeAvatar.value ? null : existingAvatar.value),
);

const pickerButtonClass =
    'inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-within:ring-[3px] focus-within:ring-ring/50';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    title="Profile information"
                    description="Update your picture, name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="avatar">Profile picture</Label>

                        <div class="mt-1 flex items-start gap-4">
                            <img
                                v-if="shown"
                                :src="shown"
                                alt=""
                                class="size-20 shrink-0 rounded-full border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                            />
                            <div
                                v-else
                                class="flex size-20 shrink-0 items-center justify-center rounded-full border border-dashed border-sidebar-border/70 text-lg font-semibold text-muted-foreground dark:border-sidebar-border"
                            >
                                {{ getInitials(user.name) }}
                            </div>

                            <div class="grid flex-1 gap-2">
                                <div>
                                    <label
                                        for="avatar"
                                        :class="pickerButtonClass"
                                    >
                                        Choose picture
                                    </label>
                                    <input
                                        id="avatar"
                                        name="avatar"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="sr-only"
                                        @change="onAvatarChange"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    JPEG, PNG or WebP, up to 8MB. It is squared
                                    off to 250&times;250 at most, so a picture
                                    that is already square keeps all of itself.
                                </p>

                                <label
                                    v-if="existingAvatar"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="removeAvatar"
                                        type="checkbox"
                                        name="remove_avatar"
                                        value="1"
                                        class="size-4 rounded border-input"
                                    />
                                    Remove the current picture
                                </label>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="errors.avatar" />
                    </div>

                    <!--
                        The byline: the same picture and the same name as
                        the profile, with a note under it. Offered only to
                        the writers, who are the only people it is ever
                        printed for.
                    -->
                    <template v-if="writer">
                        <div v-if="shown" class="grid gap-2">
                            <Label for="avatar_alt_text"
                                >Picture description</Label
                            >
                            <Input
                                id="avatar_alt_text"
                                class="mt-1 block w-full"
                                name="avatar_alt_text"
                                :default-value="user.avatar_alt_text ?? ''"
                                placeholder="Sam outside the Prince Albert"
                            />
                            <p class="text-xs text-muted-foreground">
                                Read aloud in place of your picture, on your
                                articles and anywhere else it appears.
                            </p>
                            <InputError
                                class="mt-2"
                                :message="errors.avatar_alt_text"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="bio">Byline</Label>
                            <textarea
                                id="bio"
                                name="bio"
                                rows="4"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                placeholder="A sentence or two about you, printed under everything you write."
                                :value="user.bio ?? ''"
                            ></textarea>
                            <p class="text-xs text-muted-foreground">
                                Runs under your name at the end of your
                                articles.
                            </p>
                            <InputError class="mt-2" :message="errors.bio" />
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
