<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit, update } from '@/routes/billing';

type Plan = {
    id: number;
    key: string;
    name: string;
    activeListingsLimit: number | null;
    pricingModel: string;
    priceAmount: number;
    currency: string;
};

defineProps<{
    currentPlanKey: string | null;
    subscriptionStatus: string | null;
    isOnTrial: boolean;
    trialEndsAt: string | null;
    plans: Plan[];
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const money = (amount: number, currency: string): string =>
    new Intl.NumberFormat(page.props.locale === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);

defineOptions({
    layout: (props: { locale?: string }) => ({
        breadcrumbs: [
            {
                title:
                    props.locale === 'es' ? 'Plan personal' : 'Personal plan',
                href: edit(),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="tr('Plan personal', 'Personal plan')" />
    <div class="space-y-8">
        <Heading
            variant="small"
            :title="tr('Plan personal', 'Personal plan')"
            :description="
                isOnTrial
                    ? tr(
                          `Tu prueba termina el ${trialEndsAt}.`,
                          `Your trial ends on ${trialEndsAt}.`,
                      )
                    : tr(
                          'Administra el plan de tus propiedades individuales.',
                          'Manage the plan for your individual listings.',
                      )
            "
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <article
                v-for="plan in plans"
                :key="plan.id"
                class="flex flex-col gap-5 rounded-2xl border p-5"
                :class="
                    plan.key === currentPlanKey
                        ? 'border-primary ring-1 ring-primary'
                        : ''
                "
            >
                <div>
                    <h2 class="font-semibold">{{ plan.name }}</h2>
                    <p class="mt-1 text-2xl font-bold">
                        {{ money(plan.priceAmount, plan.currency) }}
                        <span class="text-sm font-normal text-muted-foreground">
                            {{
                                plan.pricingModel === 'per_listing'
                                    ? tr('/ anuncio / mes', '/ listing / mo')
                                    : tr('/ mes', '/ mo')
                            }}
                        </span>
                    </p>
                </div>
                <p
                    class="flex flex-1 items-center gap-2 text-sm text-muted-foreground"
                >
                    <Check class="size-4 text-primary" />
                    {{
                        plan.activeListingsLimit === null
                            ? tr('Anuncios ilimitados', 'Unlimited listings')
                            : tr(
                                  `${plan.activeListingsLimit} propiedades activas`,
                                  `${plan.activeListingsLimit} active listings`,
                              )
                    }}
                </p>
                <Form v-bind="update.form()" v-slot="{ processing }">
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
            </article>
        </div>
    </div>
</template>
