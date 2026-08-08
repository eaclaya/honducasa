<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

const props = defineProps<Props>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

const invitationMessage = (): string =>
    tr(
        `${props.action === 'Log in' ? 'Inicia sesión' : 'Regístrate'} para unirte al equipo "${props.invitation.teamName}".`,
        `${props.action} to join the "${props.invitation.teamName}" team.`,
    );
</script>

<template>
    <div data-test="team-invitation-alert">
        <Alert
            class="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <Info class="size-4" />
            <AlertDescription class="text-blue-900 dark:text-blue-100">
                {{ invitationMessage() }}
            </AlertDescription>
        </Alert>
    </div>
</template>
