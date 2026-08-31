<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, Check } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/agencies';
import { index as agencies } from '@/routes/teams';

type Plan = {
    id: number;
    key: string;
    name: string;
    activeListingsLimit: number | null;
    seatsLimit: number | null;
    featuredListingSlots: number;
    analyticsTier: string;
    supportTier: string;
    priceAmount: number;
    currency: string;
    isEntryTier: boolean;
};

defineProps<{ plans: Plan[] }>();

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
                title: props.locale === 'es' ? 'Agencias' : 'Agencies',
                href: agencies(),
            },
            {
                title:
                    props.locale === 'es'
                        ? 'Crear una agencia'
                        : 'Create an agency',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <div class="space-y-8">
        <Head :title="tr('Crear una agencia', 'Create an agency')" />
        <Heading
            :title="tr('Crea tu agencia', 'Create your agency')"
            :description="
                tr(
                    'Elige un plan para publicar propiedades e invitar a tus colegas.',
                    'Choose a plan to publish properties and invite your colleagues.',
                )
            "
        />

        <Form
            v-bind="store.form()"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="agency-name">{{
                    tr('Nombre de la agencia', 'Agency name')
                }}</Label>
                <Input
                    id="agency-name"
                    name="name"
                    data-test="agency-name"
                    :placeholder="tr('Mi agencia', 'My agency')"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <fieldset class="space-y-4">
                <legend class="text-base font-semibold">
                    {{ tr('Elige un plan', 'Choose a plan') }}
                </legend>
                <div class="grid gap-4 md:grid-cols-2">
                    <label
                        v-for="plan in plans"
                        :key="plan.id"
                        class="cursor-pointer rounded-2xl border p-5 transition hover:border-primary has-checked:border-primary has-checked:ring-2 has-checked:ring-primary/30"
                    >
                        <input
                            type="radio"
                            name="subscription_plan_id"
                            :value="plan.id"
                            class="sr-only"
                            required
                        />
                        <span class="flex items-start justify-between gap-4">
                            <span>
                                <span
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <Building2 class="size-5" /> {{ plan.name }}
                                </span>
                                <Badge
                                    v-if="plan.isEntryTier"
                                    variant="secondary"
                                    class="mt-2"
                                >
                                    {{ tr('Inicial', 'Starter') }}
                                </Badge>
                            </span>
                            <strong class="text-lg">
                                {{ money(plan.priceAmount, plan.currency) }}
                                <small
                                    class="font-normal text-muted-foreground"
                                >
                                    /{{ tr('mes', 'mo') }}
                                </small>
                            </strong>
                        </span>
                        <ul
                            class="mt-5 space-y-2 text-sm text-muted-foreground"
                        >
                            <li class="flex gap-2">
                                <Check class="size-4 text-primary" />
                                {{
                                    plan.activeListingsLimit === null
                                        ? tr(
                                              'Propiedades ilimitadas',
                                              'Unlimited listings',
                                          )
                                        : tr(
                                              String(plan.activeListingsLimit) +
                                                  ' propiedades activas',
                                              String(plan.activeListingsLimit) +
                                                  ' active listings',
                                          )
                                }}
                            </li>
                            <li class="flex gap-2">
                                <Check class="size-4 text-primary" />
                                {{
                                    plan.seatsLimit === null
                                        ? tr(
                                              'Miembros ilimitados',
                                              'Unlimited members',
                                          )
                                        : tr(
                                              String(plan.seatsLimit) +
                                                  ' miembros',
                                              String(plan.seatsLimit) +
                                                  ' members',
                                          )
                                }}
                            </li>
                            <li
                                v-if="plan.featuredListingSlots"
                                class="flex gap-2"
                            >
                                <Check class="size-4 text-primary" />
                                {{
                                    tr(
                                        String(plan.featuredListingSlots) +
                                            ' propiedades destacadas',
                                        String(plan.featuredListingSlots) +
                                            ' featured listings',
                                    )
                                }}
                            </li>
                        </ul>
                    </label>
                </div>
                <InputError :message="errors.subscription_plan_id" />
            </fieldset>

            <div class="flex flex-wrap justify-end gap-3">
                <Button variant="outline" as-child>
                    <Link :href="agencies()">{{
                        tr('Volver a agencias', 'Back to agencies')
                    }}</Link>
                </Button>
                <Button
                    type="submit"
                    data-test="create-agency-submit"
                    :disabled="processing || plans.length === 0"
                >
                    {{
                        processing
                            ? tr('Creando…', 'Creating…')
                            : tr('Crear agencia', 'Create agency')
                    }}
                </Button>
            </div>
        </Form>
    </div>
</template>
