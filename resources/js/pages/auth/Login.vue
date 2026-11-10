<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import InputError from '../../components/InputError.vue';
import TextLink from '../../components/TextLink.vue';
import { Button } from '../../components/ui/button';
import { Checkbox } from '../../components/ui/checkbox';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Spinner } from '../../components/ui/spinner';
import AuthBase from '../../layouts/AuthLayout.vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
              <h2 style="font-size: 24px; font-weight: 700; margin-top:40px; color: #212121">Reader Account</h2>
              <p style="margin-bottom: 40px; color: #212121;"><TextLink :href="register()" :tabindex="5">Click here</TextLink> to sign up for your own free <strong>Good On Paper</strong> account enabling you to bookmark events and receive notifications of forthcoming events!</p>
              <h2 style="font-size: 24px; font-weight: 700; color: #212121">Venue / Promoter Account</h2>
              <p style="color: #212121;">Don't have an account? Email <a href="mailto:info@goodonpaper.info" style="text-decoration: underline;" tabindex="6">info@goodonpaper.info</a> and we will set you up with one so you can add your listing to <strong>Good On Paper</strong></p>

            </div>
        </Form>
    </AuthBase>
</template>
