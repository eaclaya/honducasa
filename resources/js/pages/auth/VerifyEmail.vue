<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: (props: { locale?: string }) => ({
        title:
            props.locale === 'es'
                ? 'Verificación de correo electrónico'
                : 'Email verification',
        description:
            props.locale === 'es'
                ? 'Verifica tu correo electrónico haciendo clic en el enlace que te acabamos de enviar.'
                : 'Please verify your email address by clicking on the link we just emailed to you.',
    }),
});

defineProps<{
    status?: string;
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head
        :title="tr('Verificación de correo electrónico', 'Email verification')"
    />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-success"
    >
        {{
            tr(
                'Se ha enviado un nuevo enlace de verificación al correo electrónico que proporcionaste durante el registro.',
                'A new verification link has been sent to the email address you provided during registration.',
            )
        }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{
                tr(
                    'Reenviar correo de verificación',
                    'Resend verification email',
                )
            }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ tr('Cerrar sesión', 'Log out') }}
        </TextLink>
    </Form>
</template>
