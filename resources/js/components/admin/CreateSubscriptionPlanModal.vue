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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <Form
                :key="formKey"
                :action="props.url"
                method="post"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        tr('Nuevo plan de suscripción', 'New subscription plan')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Crea una fila nueva en el catálogo. Una vez creado, el precio y el proveedor ya no se pueden editar.',
                                'Creates a new catalog row. Once created, the price and provider can no longer be edited.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 grid gap-2">
                        <Label for="key">{{ tr('Clave', 'Key') }}</Label>
                        <Input
                            id="key"
                            name="key"
                            autocomplete="off"
                            placeholder="individual-basic"
                        />
                        <InputError :message="errors.key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ladder">{{ tr('Escala', 'Ladder') }}</Label>
                        <select
                            id="ladder"
                            name="ladder"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="individual">
                                {{ tr('Individual', 'Individual') }}
                            </option>
                            <option value="agency">
                                {{ tr('Agencia', 'Agency') }}
                            </option>
                        </select>
                        <InputError :message="errors.ladder" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="sort_order">{{
                            tr('Orden', 'Sort order')
                        }}</Label>
                        <Input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            default-value="0"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>

                    <div class="col-span-2 grid gap-2">
                        <Label for="name">{{ tr('Nombre', 'Name') }}</Label>
                        <Input id="name" name="name" autocomplete="off" />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="active_listings_limit">{{
                            tr('Límite de anuncios', 'Listings limit')
                        }}</Label>
                        <Input
                            id="active_listings_limit"
                            name="active_listings_limit"
                            type="number"
                            min="0"
                            :placeholder="tr('Sin límite', 'Unlimited')"
                        />
                        <InputError :message="errors.active_listings_limit" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="seats_limit">{{
                            tr('Límite de asientos', 'Seats limit')
                        }}</Label>
                        <Input
                            id="seats_limit"
                            name="seats_limit"
                            type="number"
                            min="0"
                            :placeholder="tr('Sin límite', 'Unlimited')"
                        />
                        <InputError :message="errors.seats_limit" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="featured_listing_slots">{{
                            tr('Cupos destacados', 'Featured slots')
                        }}</Label>
                        <Input
                            id="featured_listing_slots"
                            name="featured_listing_slots"
                            type="number"
                            min="0"
                            default-value="0"
                        />
                        <InputError :message="errors.featured_listing_slots" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="analytics_tier">{{
                            tr('Analítica', 'Analytics')
                        }}</Label>
                        <select
                            id="analytics_tier"
                            name="analytics_tier"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="basic">
                                {{ tr('Básico', 'Basic') }}
                            </option>
                            <option value="full">
                                {{ tr('Completo', 'Full') }}
                            </option>
                        </select>
                        <InputError :message="errors.analytics_tier" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="support_tier">{{
                            tr('Soporte', 'Support')
                        }}</Label>
                        <select
                            id="support_tier"
                            name="support_tier"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="standard">
                                {{ tr('Estándar', 'Standard') }}
                            </option>
                            <option value="priority">
                                {{ tr('Prioritario', 'Priority') }}
                            </option>
                            <option value="dedicated">
                                {{ tr('Dedicado', 'Dedicated') }}
                            </option>
                        </select>
                        <InputError :message="errors.support_tier" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="price_amount">{{
                            tr('Precio', 'Price')
                        }}</Label>
                        <Input
                            id="price_amount"
                            name="price_amount"
                            type="number"
                            min="0"
                        />
                        <InputError :message="errors.price_amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">{{
                            tr('Moneda', 'Currency')
                        }}</Label>
                        <select
                            id="currency"
                            name="currency"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="HNL">HNL</option>
                            <option value="USD">USD</option>
                        </select>
                        <InputError :message="errors.currency" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="provider">{{
                            tr('Proveedor', 'Provider')
                        }}</Label>
                        <select
                            id="provider"
                            name="provider"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                        >
                            <option value="manual">
                                {{ tr('Manual', 'Manual') }}
                            </option>
                            <option value="stripe">Stripe</option>
                            <option value="tilopay">Tilopay</option>
                        </select>
                        <InputError :message="errors.provider" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="provider_price_id">{{
                            tr('ID de precio', 'Price ID')
                        }}</Label>
                        <Input
                            id="provider_price_id"
                            name="provider_price_id"
                            autocomplete="off"
                        />
                        <InputError :message="errors.provider_price_id" />
                    </div>

                    <label
                        class="col-span-2 flex items-center gap-2 text-sm font-medium"
                    >
                        <input type="hidden" name="is_entry_tier" value="0" />
                        <input
                            id="is_entry_tier"
                            type="checkbox"
                            name="is_entry_tier"
                            value="1"
                            class="accent-primary"
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
                        {{ tr('Crear plan', 'Create plan') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
