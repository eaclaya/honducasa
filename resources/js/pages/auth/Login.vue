<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: (props: { locale?: string }) => ({
        title:
            props.locale === 'es'
                ? 'Inicia sesión en tu cuenta'
                : 'Log in to your account',
        description:
            props.locale === 'es'
                ? 'Ingresa tu correo electrónico y contraseña para iniciar sesión'
                : 'Enter your email and password below to log in',
    }),
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head :title="tr('Iniciar sesión', 'Log in')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Log in"
    />

    <div class="grid gap-4">
        <GoogleAuthButton :invitation="teamInvitation?.code" />
        <InputError :message="$page.props.errors.google" />
        <div class="relative text-center text-sm">
            <span
                class="relative z-10 bg-background px-2 text-muted-foreground"
            >
                {{ tr('O continúa manualmente', 'Or continue manually') }}
            </span>
            <span class="absolute inset-x-0 top-1/2 border-t" />
        </div>
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{
                    tr('Correo electrónico', 'Email address')
                }}</Label>
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
                    <Label for="password">{{
                        tr('Contraseña', 'Password')
                    }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{
                            tr('¿Olvidaste tu contraseña?', 'Forgot password?')
                        }}
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="tr('Contraseña', 'Password')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ tr('Recuérdame', 'Remember me') }}</span>
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
                {{ tr('Iniciar sesión', 'Log in') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ tr('¿No tienes una cuenta?', "Don't have an account?") }}
            <TextLink
                :href="
                    register({
                        query: {
                            invitation: teamInvitation?.code,
                        },
                    })
                "
                :tabindex="5"
                data-test="register-link"
            >
                {{ tr('Regístrate', 'Sign up') }}
            </TextLink>
        </div>
    </Form>
</template>
