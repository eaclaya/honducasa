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

type PlanOption = {
    id: number;
    key: string;
    ladder: 'individual' | 'agency';
    name: string;
};

type Props = {
    open: boolean;
    name: string;
    isPersonal: boolean;
    plans: PlanOption[];
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

const availablePlans = computed(() =>
    props.plans.filter(
        (plan) => plan.ladder === (props.isPersonal ? 'individual' : 'agency'),
    ),
);

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
                method="post"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="handleOpenChange(false)"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        tr('Otorgar plan gratuito', 'Comp a plan')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            tr(
                                'Da acceso gratuito a un plan a',
                                'Grant free access to a plan for',
                            )
                        }}
                        <strong>"{{ props.name }}"</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="subscription_plan_id">{{
                        tr('Plan', 'Plan')
                    }}</Label>
                    <select
                        id="subscription_plan_id"
                        name="subscription_plan_id"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                    >
                        <option
                            v-for="plan in availablePlans"
                            :key="plan.id"
                            :value="plan.id"
                        >
                            {{ plan.name }}
                        </option>
                    </select>
                    <InputError :message="errors.subscription_plan_id" />
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
                    <Button
                        type="submit"
                        :disabled="processing || availablePlans.length === 0"
                    >
                        {{ tr('Otorgar', 'Grant') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
