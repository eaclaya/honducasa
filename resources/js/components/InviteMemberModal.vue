<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/teams/invitations';
import type { RoleOption, Team } from '@/types';

type Props = {
    team: Team;
    availableRoles: RoleOption[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const inviteRole = ref('member');
const formKey = ref(0);

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        inviteRole.value = 'member';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        tr(
                            'Invita a un miembro al equipo',
                            'Invite a team member',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Envía una invitación para unirse a este equipo.',
                                'Send an invitation to join this team.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="email">{{
                            tr('Correo electrónico', 'Email address')
                        }}</Label>
                        <Input
                            id="email"
                            name="email"
                            data-test="invite-email"
                            type="email"
                            placeholder="colleague@example.com"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">{{ tr('Rol', 'Role') }}</Label>
                        <Select
                            v-model="inviteRole"
                            name="role"
                            data-test="invite-role"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    :placeholder="
                                        tr('Selecciona un rol', 'Select a role')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in props.availableRoles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{
                            tr('Cancelar', 'Cancel')
                        }}</Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="invite-submit"
                        :disabled="processing"
                    >
                        {{ tr('Enviar invitación', 'Send invitation') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
