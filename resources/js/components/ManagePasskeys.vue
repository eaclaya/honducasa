<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <Heading
            variant="small"
            :title="tr('Llaves de acceso', 'Passkeys')"
            :description="
                tr(
                    'Administra tus llaves de acceso para iniciar sesión sin contraseña',
                    'Manage your passkeys for passwordless sign-in',
                )
            "
        />

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <div v-else class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <KeyRound class="h-7 w-7 text-muted-foreground" />
                </div>
                <p class="font-medium">
                    {{
                        tr('Aún no tienes llaves de acceso', 'No passkeys yet')
                    }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        tr(
                            'Agrega una llave de acceso para iniciar sesión sin contraseña',
                            'Add a passkey to sign in without a password',
                        )
                    }}
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
