<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { FileText, ImageOff, Send } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    /** A listing with no photos can only be saved as a draft. */
    hasPhotos: boolean;
    processing: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [status: 'draft' | 'published'];
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    tr(
                        '¿Cómo quieres guardarla?',
                        'How do you want to save it?',
                    )
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        tr(
                            'Un borrador queda visible solo para ti. Al publicar, la propiedad aparece en las búsquedas.',
                            'A draft stays visible only to you. Publishing makes the listing appear in search.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="!props.hasPhotos"
                role="alert"
                class="flex items-start gap-2.5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
            >
                <ImageOff class="mt-0.5 size-4 shrink-0" />
                <span>{{
                    tr(
                        'Esta propiedad no tiene fotos, así que se guardará como borrador. Agrega al menos una foto para poder publicarla.',
                        'This listing has no photos, so it will be saved as a draft. Add at least one photo to publish it.',
                    )
                }}</span>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    variant="secondary"
                    type="button"
                    :disabled="props.processing"
                    @click="emit('confirm', 'draft')"
                >
                    <FileText class="size-4" />{{
                        tr('Guardar como borrador', 'Save as draft')
                    }}
                </Button>
                <Button
                    type="button"
                    :disabled="props.processing || !props.hasPhotos"
                    @click="emit('confirm', 'published')"
                >
                    <Send class="size-4" />{{ tr('Publicar', 'Publish') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
