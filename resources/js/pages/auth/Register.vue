<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

defineProps<{
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
}>();

defineOptions({
    layout: (props: { locale?: string }) => ({
        title: props.locale === 'es' ? 'Crea una cuenta' : 'Create an account',
        description:
            props.locale === 'es'
                ? 'Ingresa tus datos para crear tu cuenta'
                : 'Enter your details below to create your account',
    }),
});

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head :title="tr('Registrarse', 'Register')" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Register"
    />

    <div class="grid gap-4">
        <GoogleAuthButton :invitation="teamInvitation?.code" />
        <InputError :message="$page.props.errors.google" />
        <div class="relative text-center text-sm">
            <span
                class="relative z-10 bg-background px-2 text-muted-foreground"
            >
                {{
                    tr(
                        'O crea una cuenta manualmente',
                        'Or create an account manually',
                    )
                }}
            </span>
            <span class="absolute inset-x-0 top-1/2 border-t" />
        </div>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{{ tr('Nombre', 'Name') }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    :placeholder="tr('Nombre completo', 'Full name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{
                    tr('Correo electrónico', 'Email address')
                }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ tr('Contraseña', 'Password') }}</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="tr('Contraseña', 'Password')"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    tr('Confirmar contraseña', 'Confirm password')
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="
                        tr('Confirmar contraseña', 'Confirm password')
                    "
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ tr('Crear cuenta', 'Create account') }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ tr('¿Ya tienes una cuenta?', 'Already have an account?') }}
            <TextLink
                :href="
                    teamInvitation
                        ? login.url({
                              query: {
                                  invitation: teamInvitation.code,
                              },
                          })
                        : login()
                "
                class="underline underline-offset-4"
                :tabindex="6"
                data-test="team-invitation-login-link"
            >
                {{ tr('Inicia sesión', 'Log in') }}
            </TextLink>
        </div>
    </Form>
</template>
