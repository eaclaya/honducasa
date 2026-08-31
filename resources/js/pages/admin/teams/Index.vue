<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Building2, MoreVertical, RotateCcw, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { reactive, ref } from 'vue';
import CompSubscriptionModal from '@/components/admin/CompSubscriptionModal.vue';
import ExtendTrialModal from '@/components/admin/ExtendTrialModal.vue';
import Pagination from '@/components/admin/Pagination.vue';
import SuspensionModal from '@/components/admin/SuspensionModal.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { index as propertiesIndex } from '@/routes/admin/properties';
import {
    index as teamsIndex,
    restore as restoreTeam,
} from '@/routes/admin/teams';
import {
    cancel as cancelSubscriptionRoute,
    comp as compSubscriptionRoute,
} from '@/routes/admin/teams/subscription';
import { update as updateSuspension } from '@/routes/admin/teams/suspension';
import { update as updateTrial } from '@/routes/admin/teams/trial';

type PlanOption = {
    id: number;
    key: string;
    ladder: 'individual' | 'agency';
    name: string;
};
type SubscriptionSummary = {
    state:
        | 'active'
        | 'past_due'
        | 'incomplete'
        | 'trial'
        | 'trial_expired'
        | 'legacy';
    planName?: string;
    trialEndsAt?: string;
};
type TeamRow = {
    id: number;
    slug: string;
    name: string;
    isPersonal: boolean;
    owner: string | null;
    membersCount: number;
    propertiesCount: number;
    publishedPropertiesCount: number;
    conversationsCount: number;
    isSuspended: boolean;
    suspensionReason: string | null;
    deletedAt: string | null;
    createdAt: string;
    subscription: SubscriptionSummary;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};
type Filters = {
    search: string | null;
    type: string | null;
    properties: string | null;
    sort: string | null;
    showDeleted: boolean;
};

const props = defineProps<{
    teams: Paginated<TeamRow>;
    subscriptionPlans: PlanOption[];
    filters: Filters;
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const { getInitials } = useInitials();

const filters = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    properties: props.filters.properties ?? '',
    sort: props.filters.sort ?? '',
    showDeleted: props.filters.showDeleted,
});

const applyFilters = (): void => {
    router.get(teamsIndex.url(), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const debouncedApply = useDebounceFn(applyFilters, 350);

const clearFilters = (): void => {
    filters.search = '';
    filters.type = '';
    filters.properties = '';
    filters.sort = '';
    filters.showDeleted = false;
    applyFilters();
};

const reinstate = (team: TeamRow): void => {
    router.patch(
        updateSuspension.url(team.slug),
        { suspended: false },
        { preserveScroll: true },
    );
};

const restore = (team: TeamRow): void => {
    router.patch(restoreTeam.url(team.slug), {}, { preserveScroll: true });
};

const cancelSubscription = (team: TeamRow): void => {
    router.delete(cancelSubscriptionRoute.url(team.slug), {
        preserveScroll: true,
    });
};

const subscriptionLabels: Record<
    SubscriptionSummary['state'],
    [string, string]
> = {
    active: ['Activa', 'Active'],
    past_due: ['Pago pendiente', 'Past due'],
    incomplete: ['Incompleta', 'Incomplete'],
    trial: ['Prueba', 'Trial'],
    trial_expired: ['Prueba vencida', 'Trial expired'],
    legacy: ['Sin seguimiento', 'Not tracked'],
};
const subscriptionClasses: Record<SubscriptionSummary['state'], string> = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400',
    past_due:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400',
    incomplete: 'bg-muted text-muted-foreground',
    trial: 'bg-accent text-accent-foreground',
    trial_expired: 'bg-destructive/15 text-destructive',
    legacy: 'bg-muted text-muted-foreground',
};
const subscriptionLabel = (subscription: SubscriptionSummary): string =>
    tr(...subscriptionLabels[subscription.state]);
const hasLiveSubscription = (team: TeamRow): boolean =>
    team.subscription.state === 'active' ||
    team.subscription.state === 'past_due';

const suspendTarget = ref<TeamRow | null>(null);
const extendTrialTarget = ref<TeamRow | null>(null);
const compSubscriptionTarget = ref<TeamRow | null>(null);
</script>

<template>
    <Head :title="tr('Equipos', 'Teams')" />
    <div class="space-y-6 p-4 md:p-8">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold">
                <Building2 class="size-8 text-primary" />{{
                    tr('Equipos', 'Teams')
                }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Cuentas de arrendadores: inmobiliarias, agentes y propietarios individuales.',
                        'Landlord accounts: agencies, agents and individual owners.',
                    )
                }}
            </p>
        </div>

        <div
            class="flex flex-wrap items-end gap-3 rounded-2xl border bg-card p-4"
        >
            <label
                class="min-w-64 flex-1 text-xs font-semibold text-muted-foreground"
            >
                {{ tr('Buscar', 'Search') }}
                <div class="relative mt-1.5">
                    <Search
                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <input
                        v-model="filters.search"
                        class="w-full rounded-xl border bg-background py-2 pr-3 pl-9 text-sm text-foreground"
                        :placeholder="
                            tr(
                                'Nombre, slug o correo del propietario',
                                'Name, slug or owner email',
                            )
                        "
                        @input="debouncedApply"
                    />
                </div>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Tipo', 'Type') }}
                <select
                    v-model="filters.type"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Todos', 'All') }}</option>
                    <option value="organization">
                        {{ tr('Organización', 'Organization') }}
                    </option>
                    <option value="personal">
                        {{ tr('Personal', 'Personal') }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Propiedades', 'Properties') }}
                <select
                    v-model="filters.properties"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Cualquiera', 'Any') }}</option>
                    <option value="with">
                        {{ tr('Con propiedades', 'With properties') }}
                    </option>
                    <option value="without">
                        {{ tr('Sin propiedades', 'Without properties') }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Ordenar por', 'Sort by') }}
                <select
                    v-model="filters.sort"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">
                        {{ tr('Más propiedades', 'Most properties') }}
                    </option>
                    <option value="recent">
                        {{ tr('Más recientes', 'Most recent') }}
                    </option>
                    <option value="name">
                        {{ tr('Nombre A–Z', 'Name A–Z') }}
                    </option>
                </select>
            </label>
            <label
                class="flex items-center gap-2 rounded-xl border bg-background px-3 py-2 text-sm font-medium"
            >
                <input
                    v-model="filters.showDeleted"
                    type="checkbox"
                    class="accent-primary"
                    @change="applyFilters"
                />
                {{ tr('Mostrar eliminados', 'Show deleted') }}
            </label>
            <button
                type="button"
                class="rounded-xl border px-4 py-2 text-sm font-semibold"
                @click="clearFilters"
            >
                {{ tr('Limpiar', 'Clear') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ tr('Equipo', 'Team') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Propietario', 'Owner') }}
                            </th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Miembros', 'Members') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Propiedades', 'Properties') }}
                            </th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Conv.', 'Conv.') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Suscripción', 'Subscription') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Creado', 'Created') }}
                            </th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="team in teams.data"
                            :key="team.id"
                            class="hover:bg-muted/40"
                            :class="
                                team.deletedAt ? 'bg-muted/20 opacity-70' : ''
                            "
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="grid size-9 shrink-0 place-items-center rounded-xl bg-accent text-xs font-bold text-accent-foreground"
                                    >
                                        {{ getInitials(team.name) }}
                                    </div>
                                    <div>
                                        <p
                                            class="font-semibold"
                                            :class="
                                                team.deletedAt
                                                    ? 'line-through decoration-1'
                                                    : ''
                                            "
                                        >
                                            {{ team.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            /{{ team.slug }} ·
                                            <span class="font-medium">{{
                                                team.isPersonal
                                                    ? tr('Personal', 'Personal')
                                                    : tr(
                                                          'Organización',
                                                          'Organization',
                                                      )
                                            }}</span>
                                            <span
                                                v-if="team.deletedAt"
                                                class="ml-1 rounded bg-destructive/15 px-1.5 text-[10px] font-bold text-destructive uppercase"
                                                >{{
                                                    tr('Eliminado', 'Deleted')
                                                }}
                                                {{ team.deletedAt }}</span
                                            >
                                        </p>
                                        <p
                                            v-if="team.isSuspended"
                                            class="mt-0.5 text-xs font-semibold text-destructive"
                                        >
                                            {{ tr('Suspendido', 'Suspended')
                                            }}<span v-if="team.suspensionReason"
                                                >:
                                                {{
                                                    team.suspensionReason
                                                }}</span
                                            >
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-muted-foreground">
                                {{ team.owner }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">
                                {{ team.membersCount }}
                            </td>
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="
                                        propertiesIndex.url({
                                            query: { team_id: team.id },
                                        })
                                    "
                                    class="font-semibold text-primary underline-offset-2 hover:underline"
                                    >{{ team.propertiesCount }}</Link
                                >
                                <span class="text-xs text-muted-foreground"
                                    >· {{ team.publishedPropertiesCount }}
                                    {{ tr('pub', 'pub') }}</span
                                >
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">
                                {{ team.conversationsCount }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase"
                                    :class="
                                        subscriptionClasses[
                                            team.subscription.state
                                        ]
                                    "
                                    >{{
                                        subscriptionLabel(team.subscription)
                                    }}</span
                                >
                                <p
                                    v-if="team.subscription.planName"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ team.subscription.planName }}
                                </p>
                                <p
                                    v-else-if="team.subscription.trialEndsAt"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ tr('vence', 'ends') }}
                                    {{ team.subscription.trialEndsAt }}
                                </p>
                            </td>
                            <td
                                class="px-5 py-3.5 whitespace-nowrap text-muted-foreground"
                            >
                                {{ team.createdAt }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button
                                    v-if="team.deletedAt"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold hover:bg-muted"
                                    @click="restore(team)"
                                >
                                    <RotateCcw class="size-3.5" />{{
                                        tr('Restaurar', 'Restore')
                                    }}
                                </button>
                                <DropdownMenu v-else>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 hover:bg-muted"
                                        >
                                            <MoreVertical
                                                class="size-4 text-muted-foreground"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-56"
                                    >
                                        <DropdownMenuItem
                                            v-if="team.isSuspended"
                                            @click="reinstate(team)"
                                        >
                                            {{
                                                tr(
                                                    'Reactivar equipo',
                                                    'Reinstate team',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-else
                                            variant="destructive"
                                            @click="suspendTarget = team"
                                        >
                                            {{
                                                tr(
                                                    'Suspender equipo',
                                                    'Suspend team',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            @click="extendTrialTarget = team"
                                        >
                                            {{
                                                tr(
                                                    'Extender prueba',
                                                    'Extend trial',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="!hasLiveSubscription(team)"
                                            @click="
                                                compSubscriptionTarget = team
                                            "
                                        >
                                            {{
                                                tr(
                                                    'Otorgar plan gratuito',
                                                    'Comp a plan',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-else
                                            variant="destructive"
                                            @click="cancelSubscription(team)"
                                        >
                                            {{
                                                tr(
                                                    'Cancelar suscripción',
                                                    'Cancel subscription',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="teams.data.length === 0"
                class="grid min-h-40 place-items-center text-center text-muted-foreground"
            >
                {{ tr('No se encontraron equipos.', 'No teams found.') }}
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm"
            >
                <p class="text-muted-foreground">
                    {{ tr('Mostrando', 'Showing') }}
                    <b class="text-foreground"
                        >{{ teams.from ?? 0 }}–{{ teams.to ?? 0 }}</b
                    >
                    {{ tr('de', 'of') }}
                    <b class="text-foreground">{{ teams.total }}</b>
                </p>
                <Pagination :links="teams.links" />
            </div>
        </div>

        <SuspensionModal
            v-if="suspendTarget"
            :open="suspendTarget !== null"
            :name="suspendTarget.name"
            :url="updateSuspension.url(suspendTarget.slug)"
            @update:open="
                (open) => {
                    if (!open) suspendTarget = null;
                }
            "
        />

        <ExtendTrialModal
            v-if="extendTrialTarget"
            :open="extendTrialTarget !== null"
            :name="extendTrialTarget.name"
            :url="updateTrial.url(extendTrialTarget.slug)"
            @update:open="
                (open) => {
                    if (!open) extendTrialTarget = null;
                }
            "
        />

        <CompSubscriptionModal
            v-if="compSubscriptionTarget"
            :open="compSubscriptionTarget !== null"
            :name="compSubscriptionTarget.name"
            :is-personal="compSubscriptionTarget.isPersonal"
            :plans="subscriptionPlans"
            :url="compSubscriptionRoute.url(compSubscriptionTarget.slug)"
            @update:open="
                (open) => {
                    if (!open) compSubscriptionTarget = null;
                }
            "
        />
    </div>
</template>
