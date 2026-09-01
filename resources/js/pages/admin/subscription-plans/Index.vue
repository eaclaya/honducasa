<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CreditCard, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CreateSubscriptionPlanModal from '@/components/admin/CreateSubscriptionPlanModal.vue';
import EditSubscriptionPlanModal from '@/components/admin/EditSubscriptionPlanModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { store, update as updatePlan } from '@/routes/admin/subscription-plans';
import { update as updateActive } from '@/routes/admin/subscription-plans/active';

type Plan = {
    id: number;
    key: string;
    ladder: 'individual' | 'agency';
    name: string;
    activeListingsLimit: number | null;
    seatsLimit: number | null;
    featuredListingSlots: number;
    analyticsTier: string;
    supportTier: string;
    priceAmount: number;
    currency: string;
    provider: string;
    isEntryTier: boolean;
    isActive: boolean;
    sortOrder: number;
    subscribersCount: number;
};

const props = defineProps<{ plans: Plan[] }>();

const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const money = (amount: number, currency: string): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);

const ladders = computed(() => [
    {
        key: 'individual' as const,
        label: tr('Individual', 'Individual'),
        plans: props.plans.filter((plan) => plan.ladder === 'individual'),
    },
    {
        key: 'agency' as const,
        label: tr('Agencia', 'Agency'),
        plans: props.plans.filter((plan) => plan.ladder === 'agency'),
    },
]);

const toggleActive = (plan: Plan): void => {
    router.patch(
        updateActive.url(plan.key),
        { is_active: !plan.isActive },
        { preserveScroll: true },
    );
};

const createOpen = ref(false);
const editTarget = ref<Plan | null>(null);
</script>

<template>
    <Head :title="tr('Planes de suscripción', 'Subscription plans')" />
    <div class="space-y-6 p-4 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-3 text-3xl font-semibold">
                    <CreditCard class="size-8 text-primary" />{{
                        tr('Planes de suscripción', 'Subscription plans')
                    }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{
                        tr(
                            'Catálogo de planes. El precio y el proveedor son de solo referencia — un cambio de precio crea un plan nuevo en lugar de editar este.',
                            'The plan catalog. Price and provider are reference-only — a price change creates a new plan rather than editing this one.',
                        )
                    }}
                </p>
            </div>
            <Button type="button" @click="createOpen = true">
                <Plus class="size-4" />{{ tr('Nuevo plan', 'New plan') }}
            </Button>
        </div>

        <div v-for="ladder in ladders" :key="ladder.key" class="space-y-3">
            <h2 class="text-lg font-semibold">{{ ladder.label }}</h2>
            <div class="overflow-hidden rounded-2xl border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-5 py-3">
                                    {{ tr('Plan', 'Plan') }}
                                </th>
                                <th class="px-5 py-3 text-center">
                                    {{ tr('Anuncios', 'Listings') }}
                                </th>
                                <th class="px-5 py-3 text-center">
                                    {{ tr('Asientos', 'Seats') }}
                                </th>
                                <th class="px-5 py-3 text-center">
                                    {{ tr('Destacados', 'Featured') }}
                                </th>
                                <th class="px-5 py-3">
                                    {{ tr('Analítica', 'Analytics') }}
                                </th>
                                <th class="px-5 py-3">
                                    {{ tr('Soporte', 'Support') }}
                                </th>
                                <th class="px-5 py-3 text-right">
                                    {{ tr('Precio', 'Price') }}
                                </th>
                                <th class="px-5 py-3 text-center">
                                    {{ tr('Suscriptores', 'Subscribers') }}
                                </th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="plan in ladder.plans"
                                :key="plan.id"
                                class="hover:bg-muted/40"
                                :class="!plan.isActive ? 'opacity-60' : ''"
                            >
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold">
                                            {{ plan.name }}
                                        </p>
                                        <Badge
                                            v-if="plan.isEntryTier"
                                            variant="secondary"
                                            >{{ tr('Prueba', 'Trial') }}</Badge
                                        >
                                        <Badge
                                            v-if="!plan.isActive"
                                            variant="outline"
                                            >{{
                                                tr('Retirado', 'Retired')
                                            }}</Badge
                                        >
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ plan.key }} · {{ plan.provider }}
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 text-center font-medium">
                                    {{ plan.activeListingsLimit ?? '∞' }}
                                </td>
                                <td class="px-5 py-3.5 text-center font-medium">
                                    {{ plan.seatsLimit ?? '∞' }}
                                </td>
                                <td class="px-5 py-3.5 text-center font-medium">
                                    {{ plan.featuredListingSlots }}
                                </td>
                                <td class="px-5 py-3.5 capitalize">
                                    {{ plan.analyticsTier }}
                                </td>
                                <td class="px-5 py-3.5 capitalize">
                                    {{ plan.supportTier }}
                                </td>
                                <td
                                    class="px-5 py-3.5 text-right font-semibold whitespace-nowrap"
                                >
                                    {{ money(plan.priceAmount, plan.currency) }}
                                </td>
                                <td class="px-5 py-3.5 text-center font-medium">
                                    {{ plan.subscribersCount }}
                                </td>
                                <td
                                    class="px-5 py-3.5 text-right whitespace-nowrap"
                                >
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-muted"
                                        @click="editTarget = plan"
                                    >
                                        {{ tr('Editar', 'Edit') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="ml-2 rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-muted"
                                        @click="toggleActive(plan)"
                                    >
                                        {{
                                            plan.isActive
                                                ? tr('Retirar', 'Retire')
                                                : tr('Reactivar', 'Reactivate')
                                        }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="ladder.plans.length === 0"
                    class="grid min-h-24 place-items-center text-center text-muted-foreground"
                >
                    {{
                        tr(
                            'Sin planes en este catálogo.',
                            'No plans in this catalog.',
                        )
                    }}
                </div>
            </div>
        </div>

        <CreateSubscriptionPlanModal
            :open="createOpen"
            :url="store.url()"
            @update:open="(open) => (createOpen = open)"
        />

        <EditSubscriptionPlanModal
            v-if="editTarget"
            :open="editTarget !== null"
            :plan="editTarget"
            :url="updatePlan.url(editTarget.key)"
            @update:open="
                (open) => {
                    if (!open) editTarget = null;
                }
            "
        />
    </div>
</template>
