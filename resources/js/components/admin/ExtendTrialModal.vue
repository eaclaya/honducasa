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

type Props = {
    open: boolean;
    name: string;
    url: string;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

const formKey = ref(0);

const handleOpenChange = (nextOpen: boolean): void => {
    emit('update:open', nextOpen);

    if (!nextOpen) {
        formKey.value++;
    }
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                :action="props.url"
                method="patch"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        tr('Extender prueba', 'Extend trial')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Días adicionales de prueba para',
                                'Additional trial days for',
                            )
                        }}
                        <strong>"{{ props.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="days">{{ tr('Días', 'Days') }}</Label>
                    <Input
                        id="days"
                        name="days"
                        type="number"
                        min="1"
                        max="90"
                        default-value="14"
                    />
                    <InputError :message="errors.days" />
                </div>

                <div class="grid gap-2">
                    <Label for="reason">{{
                        tr('Motivo (opcional)', 'Reason (optional)')
                    }}</Label>
                    <Input id="reason" name="reason" autocomplete="off" />
                    <InputError :message="errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">{{
                            tr('Cancelar', 'Cancel')
                        }}</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ tr('Extender', 'Extend') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
