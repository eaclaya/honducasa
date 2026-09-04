<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/teams';
import {
    edit as editBilling,
    update as updateBilling,
} from '@/routes/teams/billing';
import type { Team } from '@/types';

type Plan = {
    id: number;
    key: string;
    name: string;
    activeListingsLimit: number | null;
    pricingModel: string;
    seatsLimit: number | null;
    featuredListingSlots: number;
    analyticsTier: string;
    supportTier: string;
    priceAmount: number;
    currency: string;
    isEntryTier: boolean;
};

type Props = {
    team: Team;
    currentPlanKey: string | null;
    subscriptionStatus: string | null;
    isOnTrial: boolean;
    trialEndsAt: string | null;
    plans: Plan[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team; locale?: string }) => ({
        breadcrumbs: [
            {
                title: props.locale === 'es' ? 'Equipos' : 'Teams',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
            {
                title: props.locale === 'es' ? 'Facturación' : 'Billing',
                href: editBilling(props.team.slug),
            },
        ],
    }),
});

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const money = (amount: number, currency: string): string =>
    new Intl.NumberFormat(page.props.locale === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
const countLabel = (
    count: number,
    esSingular: string,
    esPlural: string,
    enSingular: string,
    enPlural: string,
): string =>
    `${count} ${tr(count === 1 ? esSingular : esPlural, count === 1 ? enSingular : enPlural)}`;

const statusLabel = computed(() => {
    if (props.subscriptionStatus === 'past_due') {
        return tr(
            'Pago pendiente. Actualiza tu método de pago.',
            'Payment past due. Update your payment method.',
        );
    }

    if (props.subscriptionStatus === 'incomplete') {
        return tr('Suscripción incompleta.', 'Subscription incomplete.');
    }

    if (props.currentPlanKey === null) {
        return tr(
            'Sin plan activo. Elige uno para seguir publicando.',
            'No active plan. Choose one to keep publishing.',
        );
    }

    if (props.isOnTrial) {
        return tr(
            `En periodo de prueba hasta el ${props.trialEndsAt}.`,
            `On your free trial until ${props.trialEndsAt}.`,
        );
    }

    return tr('Suscripción activa.', 'Active subscription.');
});
</script>

<template>
    <Head :title="tr('Facturación', 'Billing')" />

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="tr('Plan y facturación', 'Plan & billing')"
            :description="statusLabel"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="flex flex-col gap-4 rounded-2xl border p-5"
                :class="
                    plan.key === currentPlanKey
                        ? 'border-primary ring-1 ring-primary'
                        : ''
                "
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold">{{ plan.name }}</h3>
                        <Badge v-if="plan.isEntryTier" variant="secondary">{{
                            tr('Prueba', 'Trial')
                        }}</Badge>
                        <Badge
                            v-if="plan.key === currentPlanKey"
                            variant="default"
                        >
                            {{ tr('Plan actual', 'Current plan') }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ money(plan.priceAmount, plan.currency) }}
                        <span
                            class="text-sm font-normal text-muted-foreground"
                            >{{
                                plan.pricingModel === 'per_listing'
                                    ? tr('/ anuncio / mes', '/ listing / mo')
                                    : tr('/ mes', '/ mo')
                            }}</span
                        >
                    </p>
                </div>

                <ul class="flex-1 space-y-1.5 text-sm text-muted-foreground">
                    <li class="flex items-center gap-2">
                        <Check class="size-4 shrink-0 text-primary" />
                        {{
                            plan.activeListingsLimit === null
                                ? tr(
                                      'Anuncios ilimitados',
                                      'Unlimited listings',
                                  )
                                : countLabel(
                                      plan.activeListingsLimit,
                                      'anuncio',
                                      'anuncios',
                                      'listing',
                                      'listings',
                                  )
                        }}
                    </li>
                    <li class="flex items-center gap-2">
                        <Check class="size-4 shrink-0 text-primary" />
                        {{
                            plan.seatsLimit === null
                                ? tr('Asientos ilimitados', 'Unlimited seats')
                                : countLabel(
                                      plan.seatsLimit,
                                      'asiento',
                                      'asientos',
                                      'seat',
                                      'seats',
                                  )
                        }}
                    </li>
                    <li
                        v-if="plan.featuredListingSlots > 0"
                        class="flex items-center gap-2"
                    >
                        <Check class="size-4 shrink-0 text-primary" />
                        {{
                            countLabel(
                                plan.featuredListingSlots,
                                'anuncio destacado',
                                'anuncios destacados',
                                'featured listing',
                                'featured listings',
                            )
                        }}
                    </li>
                    <li class="flex items-center gap-2">
                        <Check class="size-4 shrink-0 text-primary" />
                        {{
                            tr(
                                `Analítica ${plan.analyticsTier === 'full' ? 'completa' : 'básica'}`,
                                `${plan.analyticsTier === 'full' ? 'Full' : 'Basic'} analytics`,
                            )
                        }}
                    </li>
                    <li class="flex items-center gap-2">
                        <Check class="size-4 shrink-0 text-primary" />
                        {{
                            tr(
                                `Soporte ${plan.supportTier === 'standard' ? 'estándar' : plan.supportTier === 'priority' ? 'prioritario' : 'dedicado'}`,
                                `${plan.supportTier === 'standard' ? 'Standard' : plan.supportTier === 'priority' ? 'Priority' : 'Dedicated'} support`,
                            )
                        }}
                    </li>
                </ul>

                <Form
                    v-bind="updateBilling.form(team.slug)"
                    v-slot="{ processing }"
                >
                    <input
                        type="hidden"
                        name="subscription_plan_id"
                        :value="plan.id"
                    />
                    <Button
                        type="submit"
                        class="w-full"
                        :variant="
                            plan.key === currentPlanKey ? 'outline' : 'default'
                        "
                        :disabled="plan.key === currentPlanKey || processing"
                    >
                        {{
                            plan.key === currentPlanKey
                                ? tr('Plan actual', 'Current plan')
                                : tr('Seleccionar', 'Select')
                        }}
                    </Button>
                </Form>
            </div>
        </div>

        <div
            v-if="plans.length === 0"
            class="rounded-2xl border p-6 text-center text-muted-foreground"
        >
            {{
                tr(
                    'No hay planes disponibles en este momento.',
                    'No plans are available right now.',
                )
            }}
        </div>
    </div>
</template>
