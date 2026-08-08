<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/teams';
import type { Team } from '@/types';

type Props = {
    team: Team;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const confirmationName = ref('');
const formKey = ref(0);

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

const canDeleteTeam = computed(() => {
    return confirmationName.value === props.team.name;
});

const handleOpenChange = (nextOpen: boolean) => {
    emit('update:open', nextOpen);

    if (!nextOpen) {
        confirmationName.value = '';
        formKey.value++;
    }
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="destroy.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        tr('¿Estás seguro?', 'Are you sure?')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Esta acción no se puede deshacer. Se eliminará permanentemente el equipo',
                                'This action cannot be undone. This will permanently delete the team',
                            )
                        }}
                        <strong>"{{ props.team.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <Label for="confirmation-name">
                            {{ tr('Escribe', 'Type') }}
                            <strong>"{{ props.team.name }}"</strong>
                            {{ tr('para confirmar', 'to confirm') }}
                        </Label>
                        <Input
                            id="confirmation-name"
                            name="name"
                            data-test="delete-team-name"
                            v-model="confirmationName"
                            :placeholder="
                                tr(
                                    'Ingresa el nombre del equipo',
                                    'Enter team name',
                                )
                            "
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{
                            tr('Cancelar', 'Cancel')
                        }}</Button>
                    </DialogClose>

                    <Button
                        data-test="delete-team-confirm"
                        variant="destructive"
                        type="submit"
                        :disabled="!canDeleteTeam || processing"
                    >
                        {{ tr('Eliminar equipo', 'Delete team') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
