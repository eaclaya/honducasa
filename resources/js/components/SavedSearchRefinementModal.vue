<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CopyPlus, RefreshCw } from '@lucide/vue';
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

defineProps<{
    open: boolean;
    processing: boolean;
    searchName: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    update: [];
    duplicate: [];
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="gap-6 rounded-2xl border-border bg-card p-6 text-card-foreground shadow-2xl sm:max-w-lg"
        >
            <DialogHeader>
                <DialogTitle>{{
                    tr(
                        '¿Cómo quieres guardar los cambios?',
                        'How do you want to save these changes?',
                    )
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        tr(
                            `Cambiaste los filtros de “${searchName}”. Puedes actualizarla y cambiar sus alertas, o conservarla y crear una búsqueda nueva.`,
                            `You changed the filters for “${searchName}”. Update it and change its alerts, or keep it and create a new search.`,
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2 sm:flex-col">
                <Button
                    type="button"
                    class="w-full"
                    :disabled="processing"
                    @click="emit('update')"
                >
                    <RefreshCw class="size-4" />
                    {{ tr('Actualizar búsqueda', 'Update search') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    :disabled="processing"
                    @click="emit('duplicate')"
                >
                    <CopyPlus class="size-4" />
                    {{ tr('Guardar como nueva', 'Save as new') }}
                </Button>
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        class="w-full"
                        :disabled="processing"
                    >
                        {{ tr('Cancelar', 'Cancel') }}
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
