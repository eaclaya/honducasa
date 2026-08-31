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

type Plan = {
    key: string;
    name: string;
    provider: string;
    priceAmount: number;
    currency: string;
    activeListingsLimit: number | null;
    seatsLimit: number | null;
    featuredListingSlots: number;
    analyticsTier: string;
    supportTier: string;
    isEntryTier: boolean;
    sortOrder?: number;
};

type Props = {
    open: boolean;
    plan: Plan;
    url: string;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const money = computed(() =>
    new Intl.NumberFormat(page.props.locale === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency: props.plan.currency,
        maximumFractionDigits: 0,
    }).format(props.plan.priceAmount),
);

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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
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
                        tr('Editar plan', 'Edit plan')
                    }}</DialogTitle>
                    <DialogDescription>
                        <strong>"{{ props.plan.name }}"</strong>
                        · {{ props.plan.key }} · {{ money }} ·
                        {{ props.plan.provider }}.
                        {{
                            tr(
                                'La clave, la escala, el precio y el proveedor no se pueden editar aquí.',
                                'Key, ladder, price and provider can’t be edited here.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 grid gap-2">
                        <Label for="edit_name">{{
                            tr('Nombre', 'Name')
                        }}</Label>
                        <Input
                            id="edit_name"
                            name="name"
                            autocomplete="off"
                            :default-value="props.plan.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_active_listings_limit">{{
                            tr('Límite de anuncios', 'Listings limit')
                        }}</Label>
                        <Input
                            id="edit_active_listings_limit"
                            name="active_listings_limit"
                            type="number"
                            min="0"
                            :placeholder="tr('Sin límite', 'Unlimited')"
                            :default-value="
                                props.plan.activeListingsLimit ?? undefined
                            "
                        />
                        <InputError :message="errors.active_listings_limit" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_seats_limit">{{
                            tr('Límite de asientos', 'Seats limit')
                        }}</Label>
                        <Input
                            id="edit_seats_limit"
                            name="seats_limit"
                            type="number"
                            min="0"
                            :placeholder="tr('Sin límite', 'Unlimited')"
                            :default-value="props.plan.seatsLimit ?? undefined"
                        />
                        <InputError :message="errors.seats_limit" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_featured_listing_slots">{{
                            tr('Cupos destacados', 'Featured slots')
                        }}</Label>
                        <Input
                            id="edit_featured_listing_slots"
                            name="featured_listing_slots"
                            type="number"
                            min="0"
                            :default-value="props.plan.featuredListingSlots"
                        />
                        <InputError :message="errors.featured_listing_slots" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_sort_order">{{
                            tr('Orden', 'Sort order')
                        }}</Label>
                        <Input
                            id="edit_sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="props.plan.sortOrder ?? 0"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_analytics_tier">{{
                            tr('Analítica', 'Analytics')
                        }}</Label>
                        <select
                            id="edit_analytics_tier"
                            name="analytics_tier"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option
                                value="basic"
                                :selected="props.plan.analyticsTier === 'basic'"
                            >
                                {{ tr('Básico', 'Basic') }}
                            </option>
                            <option
                                value="full"
                                :selected="props.plan.analyticsTier === 'full'"
                            >
                                {{ tr('Completo', 'Full') }}
                            </option>
                        </select>
                        <InputError :message="errors.analytics_tier" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit_support_tier">{{
                            tr('Soporte', 'Support')
                        }}</Label>
                        <select
                            id="edit_support_tier"
                            name="support_tier"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option
                                value="standard"
                                :selected="
                                    props.plan.supportTier === 'standard'
                                "
                            >
                                {{ tr('Estándar', 'Standard') }}
                            </option>
                            <option
                                value="priority"
                                :selected="
                                    props.plan.supportTier === 'priority'
                                "
                            >
                                {{ tr('Prioritario', 'Priority') }}
                            </option>
                            <option
                                value="dedicated"
                                :selected="
                                    props.plan.supportTier === 'dedicated'
                                "
                            >
                                {{ tr('Dedicado', 'Dedicated') }}
                            </option>
                        </select>
                        <InputError :message="errors.support_tier" />
                    </div>

                    <label
                        class="col-span-2 flex items-center gap-2 text-sm font-medium"
                    >
                        <input type="hidden" name="is_entry_tier" value="0" />
                        <input
                            id="edit_is_entry_tier"
                            type="checkbox"
                            name="is_entry_tier"
                            value="1"
                            class="accent-primary"
                            :checked="props.plan.isEntryTier"
                        />
                        {{
                            tr(
                                'Plan de entrada (prueba) para esta escala',
                                'Entry tier (trial) for this ladder',
                            )
                        }}
                    </label>
                    <InputError
                        class="col-span-2"
                        :message="errors.is_entry_tier"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">{{
                            tr('Cancelar', 'Cancel')
                        }}</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ tr('Guardar cambios', 'Save changes') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
