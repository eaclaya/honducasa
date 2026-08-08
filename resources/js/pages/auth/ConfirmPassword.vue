<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: (props: { locale?: string }) => ({
        title:
            props.locale === 'es'
                ? 'Confirma tu contraseña'
                : 'Confirm password',
        description:
            props.locale === 'es'
                ? 'Esta es un área segura de la aplicación. Por favor, confirma tu contraseña antes de continuar.'
                : 'This is a secure area of the application. Please confirm your password before continuing.',
    }),
});

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head :title="tr('Confirmar contraseña', 'Confirm password')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="tr('Confirma con tu llave de acceso', 'Confirm with passkey')"
        :loading-label="tr('Confirmando...', 'Confirming...')"
        :separator="
            tr('O confirma con tu contraseña', 'Or confirm with password')
        "
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{
                    tr('Contraseña', 'Password')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ tr('Confirmar contraseña', 'Confirm password') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
