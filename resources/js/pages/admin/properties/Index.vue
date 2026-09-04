<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    Home,
    ImageOff,
    MoreVertical,
    Search,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as propertiesIndex } from '@/routes/admin/properties';
import { update as updateStatus } from '@/routes/admin/properties/status';
import { show as showProperty } from '@/routes/properties';

type PropertyRow = {
    id: number;
    slug: string;
    name: string | null;
    status: 'draft' | 'published' | 'paused' | 'archived';
    type: string;
    listingType: 'rent' | 'buy';
    priceAmount: number;
    currency: string;
    photosCount: number;
    teamName: string;
    teamSlug: string;
    locationName: string;
    publishedAt: string | null;
    createdAt: string;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};
type TeamOption = { id: number; name: string };
type Filters = {
    search: string | null;
    status: string | null;
    teamId: number | null;
    listingType: string | null;
    type: string | null;
    noPhotos: boolean;
};

const props = defineProps<{
    properties: Paginated<PropertyRow>;
    teams: TeamOption[];
    facetCounts: {
        all: number;
        published: number;
        draft: number;
        paused: number;
        archived: number;
    };
    filters: Filters;
}>();

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

const statusLabels: Record<string, [string, string]> = {
    published: ['Publicada', 'Published'],
    draft: ['Borrador', 'Draft'],
    paused: ['Pausada', 'Paused'],
    archived: ['Archivada', 'Archived'],
};
const statusClasses: Record<string, string> = {
    published:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400',
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400',
    paused: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    archived: 'bg-muted text-muted-foreground',
};
const statusLabel = (status: string): string => {
    const pair = statusLabels[status];

    return pair ? tr(pair[0], pair[1]) : status;
};

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    teamId: props.filters.teamId ?? '',
    listingType: props.filters.listingType ?? '',
    type: props.filters.type ?? '',
    noPhotos: props.filters.noPhotos,
});

const applyFilters = (): void => {
    router.get(propertiesIndex.url(), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const debouncedApply = useDebounceFn(applyFilters, 350);

const clearFilters = (): void => {
    filters.search = '';
    filters.status = '';
    filters.teamId = '';
    filters.listingType = '';
    filters.type = '';
    filters.noPhotos = false;
    applyFilters();
};

const setStatusFacet = (status: string): void => {
    filters.status = status;
    applyFilters();
};

const setStatus = (property: PropertyRow, status: string): void => {
    router.patch(
        updateStatus.url(property.id),
        { status },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="tr('Propiedades', 'Properties')" />
    <div class="space-y-6 p-4 md:p-8">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold">
                <Home class="size-8 text-primary" />{{
                    tr('Propiedades', 'Properties')
                }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Inventario completo de todos los equipos, incluyendo borradores y pausadas.',
                        'Full inventory across every team, including drafts and paused listings.',
                    )
                }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 border-b pb-3">
            <button
                v-for="facet in [
                    {
                        key: '',
                        label: tr('Todas', 'All'),
                        count: facetCounts.all,
                    },
                    {
                        key: 'published',
                        label: tr('Publicadas', 'Published'),
                        count: facetCounts.published,
                    },
                    {
                        key: 'draft',
                        label: tr('Borradores', 'Drafts'),
                        count: facetCounts.draft,
                    },
                    {
                        key: 'paused',
                        label: tr('Pausadas', 'Paused'),
                        count: facetCounts.paused,
                    },
                    {
                        key: 'archived',
                        label: tr('Archivadas', 'Archived'),
                        count: facetCounts.archived,
                    },
                ]"
                :key="facet.key"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold"
                :class="
                    filters.status === facet.key
                        ? 'bg-primary text-primary-foreground'
                        : 'font-medium text-muted-foreground hover:bg-muted'
                "
                @click="setStatusFacet(facet.key)"
            >
                {{ facet.label }}
                <span class="opacity-70">{{ facet.count }}</span>
            </button>
        </div>

        <div
            class="flex flex-wrap items-end gap-3 rounded-2xl border bg-card p-4"
        >
            <label
                class="min-w-56 flex-1 text-xs font-semibold text-muted-foreground"
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
                                'Título, slug o ubicación',
                                'Title, slug or location',
                            )
                        "
                        @input="debouncedApply"
                    />
                </div>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Equipo', 'Team') }}
                <select
                    v-model="filters.teamId"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Todos', 'All') }}</option>
                    <option
                        v-for="team in teams"
                        :key="team.id"
                        :value="team.id"
                    >
                        {{ team.name }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Operación', 'Listing') }}
                <select
                    v-model="filters.listingType"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Todas', 'All') }}</option>
                    <option value="rent">{{ tr('Alquiler', 'Rent') }}</option>
                    <option value="buy">{{ tr('Venta', 'Sale') }}</option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Tipo', 'Type') }}
                <select
                    v-model="filters.type"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Todos', 'All') }}</option>
                    <option value="house">{{ tr('Casa', 'House') }}</option>
                    <option value="apartment">
                        {{ tr('Apartamento', 'Apartment') }}
                    </option>
                    <option value="commercial_space">
                        {{ tr('Local Comercial', 'Commercial Space') }}
                    </option>
                    <option value="land">{{ tr('Terreno', 'Land') }}</option>
                    <option value="office_space">
                        {{ tr('Local Para Oficina', 'Office Space') }}
                    </option>
                    <option value="warehouse">
                        {{ tr('Bodega', 'Warehouse') }}
                    </option>
                    <option value="building">
                        {{ tr('Edificio', 'Building') }}
                    </option>
                </select>
            </label>
            <label
                class="flex items-center gap-2 rounded-xl border bg-background px-3 py-2 text-sm font-medium"
            >
                <input
                    v-model="filters.noPhotos"
                    type="checkbox"
                    class="accent-primary"
                    @change="applyFilters"
                />
                {{ tr('Sin fotos', 'No photos') }}
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
                                {{ tr('Propiedad', 'Property') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Equipo', 'Team') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Ubicación', 'Location') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Estado', 'Status') }}
                            </th>
                            <th class="px-5 py-3 text-right">
                                {{ tr('Precio', 'Price') }}
                            </th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Fotos', 'Photos') }}
                            </th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="property in properties.data"
                            :key="property.id"
                            class="hover:bg-muted/40"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="grid size-12 shrink-0 place-items-center rounded-lg"
                                        :class="
                                            property.photosCount === 0
                                                ? 'border border-dashed bg-muted'
                                                : 'bg-gradient-to-br from-sky-200 to-sky-400'
                                        "
                                    >
                                        <ImageOff
                                            v-if="property.photosCount === 0"
                                            class="size-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">
                                            {{
                                                property.name ??
                                                tr('(sin título)', '(untitled)')
                                            }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ property.type }} ·
                                            {{
                                                property.listingType === 'rent'
                                                    ? tr('Alquiler', 'Rent')
                                                    : tr('Venta', 'Sale')
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-primary">{{
                                    property.teamName
                                }}</span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                {{ property.locationName }}
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase"
                                    :class="statusClasses[property.status]"
                                    >{{ statusLabel(property.status) }}</span
                                >
                            </td>
                            <td
                                class="px-5 py-3 text-right font-semibold whitespace-nowrap"
                            >
                                {{
                                    money(
                                        property.priceAmount,
                                        property.currency,
                                    )
                                }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span
                                    class="font-medium"
                                    :class="
                                        property.photosCount === 0
                                            ? 'rounded bg-destructive/15 px-1.5 py-0.5 text-xs font-bold text-destructive'
                                            : ''
                                    "
                                    >{{ property.photosCount }}</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <a
                                        v-if="
                                            property.status === 'published' ||
                                            property.status === 'paused'
                                        "
                                        :href="showProperty.url(property.slug)"
                                        target="_blank"
                                        rel="noopener"
                                        :title="
                                            tr(
                                                'Ver página pública',
                                                'View public page',
                                            )
                                        "
                                        class="rounded-lg p-1.5 hover:bg-muted"
                                    >
                                        <ExternalLink
                                            class="size-4 text-muted-foreground"
                                        />
                                    </a>
                                    <DropdownMenu>
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
                                            <DropdownMenuLabel>{{
                                                tr(
                                                    'Cambiar estado',
                                                    'Change status',
                                                )
                                            }}</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <template
                                                v-if="
                                                    property.status !==
                                                    'published'
                                                "
                                            >
                                                <DropdownMenuItem
                                                    v-if="
                                                        property.photosCount > 0
                                                    "
                                                    @click="
                                                        setStatus(
                                                            property,
                                                            'published',
                                                        )
                                                    "
                                                    >{{
                                                        tr(
                                                            'Publicar',
                                                            'Publish',
                                                        )
                                                    }}</DropdownMenuItem
                                                >
                                                <div
                                                    v-else
                                                    class="px-2 py-1.5 text-sm text-muted-foreground"
                                                >
                                                    {{
                                                        tr(
                                                            'Publicar',
                                                            'Publish',
                                                        )
                                                    }}
                                                    <small
                                                        class="mt-0.5 block text-[11px] leading-tight text-destructive"
                                                    >
                                                        {{
                                                            tr(
                                                                'Sin fotos no se puede publicar.',
                                                                'Cannot publish without photos.',
                                                            )
                                                        }}
                                                    </small>
                                                </div>
                                            </template>
                                            <DropdownMenuItem
                                                v-if="
                                                    property.status !== 'paused'
                                                "
                                                @click="
                                                    setStatus(
                                                        property,
                                                        'paused',
                                                    )
                                                "
                                                >{{
                                                    tr('Pausar', 'Pause')
                                                }}</DropdownMenuItem
                                            >
                                            <DropdownMenuItem
                                                v-if="
                                                    property.status !==
                                                    'archived'
                                                "
                                                variant="destructive"
                                                @click="
                                                    setStatus(
                                                        property,
                                                        'archived',
                                                    )
                                                "
                                                >{{
                                                    tr('Archivar', 'Archive')
                                                }}</DropdownMenuItem
                                            >
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="properties.data.length === 0"
                class="grid min-h-40 place-items-center text-center text-muted-foreground"
            >
                {{
                    tr('No se encontraron propiedades.', 'No properties found.')
                }}
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm"
            >
                <p class="text-muted-foreground">
                    {{ tr('Mostrando', 'Showing') }}
                    <b class="text-foreground"
                        >{{ properties.from ?? 0 }}–{{ properties.to ?? 0 }}</b
                    >
                    {{ tr('de', 'of') }}
                    <b class="text-foreground">{{ properties.total }}</b>
                </p>
                <Pagination :links="properties.links" />
            </div>
        </div>
    </div>
</template>
