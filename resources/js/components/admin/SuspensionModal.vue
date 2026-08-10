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
                <input type="hidden" name="suspended" value="true" />
                <DialogHeader>
                    <DialogTitle>{{
                        tr('Suspender cuenta', 'Suspend account')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Se bloqueará el acceso pero se conservarán todos sus datos. Indica el motivo para',
                                'Access will be blocked but all data is kept. Give a reason for suspending',
                            )
                        }}
                        <strong>"{{ props.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="reason">{{ tr('Motivo', 'Reason') }}</Label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        required
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20"
                    />
                    <InputError :message="errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">{{
                            tr('Cancelar', 'Cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        {{ tr('Suspender', 'Suspend') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
