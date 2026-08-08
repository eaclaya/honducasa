<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy as destroyInvitation } from '@/routes/teams/invitations';
import type { Team, TeamInvitation } from '@/types';

type Props = {
    team: Team;
    invitation: TeamInvitation | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

const cancelInvitation = () => {
    if (!props.invitation) {
        return;
    }

    router.visit(destroyInvitation([props.team.slug, props.invitation.code]), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    tr('Cancelar invitación', 'Cancel invitation')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        tr(
                            '¿Estás seguro de que quieres cancelar la invitación para',
                            'Are you sure you want to cancel the invitation for',
                        )
                    }}
                    <strong>{{ props.invitation?.email }}</strong
                    >?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">{{
                        tr('Mantener invitación', 'Keep invitation')
                    }}</Button>
                </DialogClose>

                <Button
                    data-test="cancel-invitation-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="cancelInvitation"
                >
                    {{ tr('Cancelar invitación', 'Cancel invitation') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
