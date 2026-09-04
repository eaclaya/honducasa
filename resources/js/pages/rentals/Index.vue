<script setup lang="ts">
import { Head, Link, router, usePage, useRemember } from '@inertiajs/vue3';
import {
    BedDouble,
    Building2,
    ChevronDown,
    CircleDollarSign,
    Filter,
    Heart,
    List,
    Map as MapIcon,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, defineAsyncComponent, reactive, ref, toRefs } from 'vue';
import AreaUnitInput from '@/components/AreaUnitInput.vue';
import AuthModal from '@/components/AuthModal.vue';
import LocationTypeahead from '@/components/LocationTypeahead.vue';
import PropertyResultsMap from '@/components/PropertyResultsMap.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import PublicPropertyCard from '@/components/PublicPropertyCard.vue';
import SavedSearchRefinementModal from '@/components/SavedSearchRefinementModal.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePendingAuthAction } from '@/composables/usePendingAuthAction';
import type { PendingAuthAction } from '@/composables/usePendingAuthAction';
import { squareMetersToArea } from '@/lib/areaUnits';
import type { AreaUnit } from '@/lib/areaUnits';
import { encodePolygon } from '@/lib/polygonSearch';
import { store as favorite, destroy as unfavorite } from '@/routes/favorites';
import { index as rentals } from '@/routes/rentals';
import {
    store as saveSearchRoute,
    update as updateSavedSearchRoute,
} from '@/routes/saved-searches';

// Leaflet + leaflet-geoman are a heavy pair (~150KB+77KB gzipped combined)
// nobody needs unless they actually open the drawer, so this loads on demand
// rather than bundling into every results-page visit.
const MapAreaDrawer = defineAsyncComponent(
    () => import('@/components/MapAreaDrawer.vue'),
);

type Rental = {
    id: number;
    slug: string;
    name: string | null;
    type: string;
    listingType: 'rent' | 'buy';
    location: string;
    bedrooms: number | null;
    bathrooms: string | null;
    parkingSpaces: number | null;
    interiorAreaM2: number | null;
    furnishing: string | null;
    priceAmount: number;
    currency: string;
    priceIsConverted: boolean;
    depositAmount: number | null;
    utilitiesIncluded: boolean | null;
    mapLatitude: number;
    mapLongitude: number;
    primaryImage: { url: string; altText: string | null } | null;
    images: Array<{ url: string; altText: string | null }>;
    isFavorited: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRentals = {
    data: Rental[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

type AdvancedFilterKey =
    | 'minPrice'
    | 'maxPrice'
    | 'bedrooms'
    | 'bathrooms'
    | 'parkingSpaces'
    | 'minArea'
    | 'maxArea'
    | 'areaUnit'
    | 'furnishing'
    | 'utilitiesIncluded';

type RentalResultsContext = {
    location: string;
    nearbyLatitude: number | null;
    nearbyLongitude: number | null;
    polygonArea: Array<[number, number]> | null;
    propertyType: string;
    listingType: string;
    minPrice: string;
    maxPrice: string;
    bedrooms: string;
    bathrooms: string;
    parkingSpaces: string;
    minArea: string;
    maxArea: string;
    areaUnit: AreaUnit;
    furnishing: string;
    utilitiesIncluded: string;
    sort: string;
    showMap: boolean;
};

const props = defineProps<{
    filters: {
        location: string;
        propertyType: string;
        listingType: string;
        currency: string;
        minPrice: string;
        maxPrice: string;
        bedrooms: string;
        bathrooms: string;
        parkingSpaces: string;
        minArea: string;
        maxArea: string;
        areaUnit: AreaUnit;
        furnishing: string;
        utilitiesIncluded: boolean | null;
        sort: string;
        west: number | null;
        south: number | null;
        east: number | null;
        north: number | null;
        latitude: number | null;
        longitude: number | null;
        radiusMeters: number | null;
        polygon: Array<[number, number]> | null;
    };
    properties: PaginatedRentals;
    baseCurrency: string;
    isSearchSaved: boolean;
    savedSearch: {
        id: number;
        name: string;
        hasChanges: boolean;
    } | null;
}>();

const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const pageUrl = new URL(page.url, 'http://localhost');
/**
 * The advanced filters as the server last applied them. `props.filters` only
 * changes once a search actually runs, so this is the state the drawer reverts
 * to when it is dismissed without applying.
 */
const appliedAdvancedFilters = (): Pick<
    RentalResultsContext,
    AdvancedFilterKey
> => ({
    minPrice: props.filters.minPrice,
    maxPrice: props.filters.maxPrice,
    bedrooms: props.filters.bedrooms,
    bathrooms: props.filters.bathrooms,
    parkingSpaces: props.filters.parkingSpaces,
    minArea: props.filters.minArea,
    maxArea: props.filters.maxArea,
    areaUnit: props.filters.areaUnit,
    furnishing: props.filters.furnishing,
    utilitiesIncluded:
        props.filters.utilitiesIncluded === null
            ? ''
            : props.filters.utilitiesIncluded
              ? '1'
              : '0',
});
const rememberedContext = useRemember(
    reactive<RentalResultsContext>({
        location: props.filters.location,
        nearbyLatitude: props.filters.latitude,
        nearbyLongitude: props.filters.longitude,
        polygonArea: props.filters.polygon,
        propertyType: props.filters.propertyType,
        listingType: props.filters.listingType,
        ...appliedAdvancedFilters(),
        sort: props.filters.sort,
        showMap: pageUrl.searchParams.get('view') === 'map',
    }),
    'rentals.results',
) as RentalResultsContext;
/**
 * Prices render in whatever currency the server resolved for this response —
 * the visitor's header preference, or an explicit `?currency=` override that
 * keeps shared links and saved searches faithful to what they captured.
 */
const currency = computed(() => props.filters.currency);
const {
    location,
    nearbyLatitude,
    nearbyLongitude,
    polygonArea,
    propertyType,
    listingType,
    minPrice,
    maxPrice,
    bedrooms,
    bathrooms,
    parkingSpaces,
    minArea,
    maxArea,
    areaUnit,
    furnishing,
    utilitiesIncluded,
    sort,
    showMap,
} = toRefs(rememberedContext);
const resultsContextUrl = computed(() => {
    const url = new URL(page.url, 'http://localhost');

    if (showMap.value) {
        url.searchParams.set('view', 'map');
    } else {
        url.searchParams.delete('view');
    }

    return `${url.pathname}${url.search}${url.hash}`;
});
const showFilters = ref(false);
const savingSearch = ref(false);
const refinementModalOpen = ref(false);
const nearbyActive = computed(
    () => nearbyLatitude.value !== null && nearbyLongitude.value !== null,
);
const polygonAreaActive = computed(() => polygonArea.value !== null);
const clearAreaSearch = (): void => {
    nearbyLatitude.value = null;
    nearbyLongitude.value = null;
    polygonArea.value = null;
};
const drawingArea = ref(false);
const authModalOpen = ref(false);
const authModalDescription = ref<string | undefined>(undefined);
const { remember: rememberPendingAuthAction } = usePendingAuthAction();
const requireAuth = async (
    description: string,
    action?: PendingAuthAction,
): Promise<void> => {
    authModalDescription.value = description;

    if (!action) {
        authModalOpen.value = true;

        return;
    }

    if (await rememberPendingAuthAction(action, page.url)) {
        authModalOpen.value = true;
    }
};
const saveSearch = (): void => {
    if (props.isSearchSaved || savingSearch.value) {
        return;
    }

    if (props.savedSearch?.hasChanges) {
        refinementModalOpen.value = true;

        return;
    }

    createSavedSearch();
};
const currentSavedSearch = () => {
    const filters = Object.fromEntries(
        Object.entries(queryParameters()).filter(
            ([key, value]) => key !== 'saved_search' && value !== undefined,
        ),
    );

    return {
        name: nearbyActive.value
            ? tr('Propiedades cerca de mí', 'Properties near me')
            : location.value
              ? `${tr('Propiedades en', 'Properties in')} ${location.value}`
              : tr('Mi búsqueda', 'My search'),
        filters,
        alerts_enabled: true,
    };
};
const createSavedSearch = (): void => {
    const savedSearch = currentSavedSearch();

    if (!page.props.auth.user) {
        requireAuth(
            tr(
                'Necesitas una cuenta para guardar esta búsqueda.',
                'You need an account to save this search.',
            ),
            {
                type: 'save_search',
                payload: { saved_search: savedSearch },
            },
        );

        return;
    }

    router.post(saveSearchRoute.url(), savedSearch, {
        preserveScroll: true,
        onStart: () => {
            savingSearch.value = true;
        },
        onFinish: () => {
            savingSearch.value = false;
        },
    });
};
const updateSavedSearch = (): void => {
    if (!props.savedSearch) {
        return;
    }

    router.patch(
        updateSavedSearchRoute.url(props.savedSearch.id),
        { filters: currentSavedSearch().filters },
        {
            preserveScroll: true,
            onStart: () => {
                savingSearch.value = true;
            },
            onSuccess: () => {
                refinementModalOpen.value = false;
            },
            onFinish: () => {
                savingSearch.value = false;
            },
        },
    );
};
const saveRefinedSearchAsNew = (): void => {
    createSavedSearch();
    refinementModalOpen.value = false;
};
const toggleFavorite = (property: Rental): void => {
    if (!page.props.auth.user) {
        requireAuth(
            tr(
                'Necesitas una cuenta para guardar esta propiedad.',
                'You need an account to save this property.',
            ),
            {
                type: 'favorite_property',
                payload: { property_slug: property.slug },
            },
        );

        return;
    }

    const options = { preserveScroll: true, preserveState: true };

    if (property.isFavorited) {
        router.delete(unfavorite.url(property.slug), options);
    } else {
        router.post(favorite.url(property.slug), {}, options);
    }
};
const initialBounds = computed(() =>
    props.filters.west !== null &&
    props.filters.south !== null &&
    props.filters.east !== null &&
    props.filters.north !== null
        ? {
              west: props.filters.west,
              south: props.filters.south,
              east: props.filters.east,
              north: props.filters.north,
          }
        : null,
);

const queryParameters = (
    additional: Record<string, number> = {},
): Record<string, string | number | undefined> => ({
    location: location.value || undefined,
    property_type: propertyType.value || undefined,
    listing_type: listingType.value || undefined,
    currency:
        currency.value && currency.value !== props.baseCurrency
            ? currency.value
            : undefined,
    min_price: minPrice.value || undefined,
    max_price: maxPrice.value || undefined,
    bedrooms: bedrooms.value || undefined,
    bathrooms: bathrooms.value || undefined,
    parking_spaces: parkingSpaces.value || undefined,
    min_area: minArea.value || undefined,
    max_area: maxArea.value || undefined,
    area_unit: areaUnit.value === 'm2' ? undefined : areaUnit.value,
    furnishing: furnishing.value || undefined,
    utilities_included:
        utilitiesIncluded.value === '' ? undefined : utilitiesIncluded.value,
    sort: sort.value === 'newest' ? undefined : sort.value,
    latitude: nearbyLatitude.value ?? undefined,
    longitude: nearbyLongitude.value ?? undefined,
    polygon: polygonArea.value ? encodePolygon(polygonArea.value) : undefined,
    saved_search: props.savedSearch?.id,
    ...additional,
});

const search = (): void => {
    router.get(rentals.url(), queryParameters(), {
        preserveState: true,
        replace: true,
    });
};

const searchSelectedLocation = (selectedLocation: string): void => {
    location.value = selectedLocation;
    clearAreaSearch();
    search();
};

const searchDrawnArea = (ring: Array<[number, number]>): void => {
    drawingArea.value = false;
    location.value = '';
    nearbyLatitude.value = null;
    nearbyLongitude.value = null;
    polygonArea.value = ring;
    search();
};

const applyFilters = (): void => {
    showFilters.value = false;
    search();
};

/**
 * Dismissing the drawer (X or backdrop) must not leave edits behind: the chips
 * read this same state, so uncommitted edits would otherwise label the results
 * with filters that were never sent to the server.
 */
const discardFilters = (): void => {
    Object.assign(rememberedContext, appliedAdvancedFilters());
    showFilters.value = false;
};

const searchMapBounds = (bounds: {
    west: number;
    south: number;
    east: number;
    north: number;
}): void => {
    router.get(rentals.url(), queryParameters(bounds), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const activeAdvancedFilters = computed(() => {
    const filters: Array<{ key: AdvancedFilterKey; label: string }> = [];
    const money = currency.value;
    const areaSuffix = areaUnit.value === 'vara2' ? 'varas²' : 'm²';
    const displayArea = (value: string): number =>
        squareMetersToArea(Number(value), areaUnit.value);

    if (minPrice.value) {
        filters.push({
            key: 'minPrice',
            label: `${tr('Precio desde', 'Price from')} ${money} ${minPrice.value}`,
        });
    }

    if (maxPrice.value) {
        filters.push({
            key: 'maxPrice',
            label: `${tr('Precio hasta', 'Price up to')} ${money} ${maxPrice.value}`,
        });
    }

    if (bedrooms.value) {
        filters.push({
            key: 'bedrooms',
            label: `${bedrooms.value}+ ${tr('habitaciones', 'bedrooms')}`,
        });
    }

    if (bathrooms.value) {
        filters.push({
            key: 'bathrooms',
            label: `${bathrooms.value}+ ${tr('baños', 'bathrooms')}`,
        });
    }

    if (parkingSpaces.value) {
        filters.push({
            key: 'parkingSpaces',
            label: `${parkingSpaces.value}+ ${tr('estacionamientos', 'parking spaces')}`,
        });
    }

    if (minArea.value) {
        filters.push({
            key: 'minArea',
            label: `${tr('Área desde', 'Area from')} ${displayArea(minArea.value)} ${areaSuffix}`,
        });
    }

    if (maxArea.value) {
        filters.push({
            key: 'maxArea',
            label: `${tr('Área hasta', 'Area up to')} ${displayArea(maxArea.value)} ${areaSuffix}`,
        });
    }

    if (furnishing.value) {
        filters.push({
            key: 'furnishing',
            label: humanize(furnishing.value),
        });
    }

    if (utilitiesIncluded.value) {
        filters.push({
            key: 'utilitiesIncluded',
            label:
                utilitiesIncluded.value === '1'
                    ? tr('Servicios incluidos', 'Utilities included')
                    : tr('Servicios no incluidos', 'Utilities not included'),
        });
    }

    return filters;
});

const priceFilterLabel = computed(() => {
    if (!minPrice.value && !maxPrice.value) {
        return tr('Precio', 'Price');
    }

    return `${currency.value} ${minPrice.value || '0'}–${maxPrice.value || '∞'}`;
});

const roomsFilterLabel = computed(() =>
    bedrooms.value || bathrooms.value
        ? `${bedrooms.value || '0'}+ ${tr('hab.', 'beds')} · ${bathrooms.value || '0'}+ ${tr('baños', 'baths')}`
        : tr('Habitaciones', 'Rooms'),
);

const homeTypeFilterLabel = computed(() =>
    propertyType.value
        ? tr(
              {
                  house: 'Casa',
                  apartment: 'Apartamento',
                  commercial_space: 'Local Comercial',
                  land: 'Terreno',
                  office_space: 'Local Para Oficina',
                  warehouse: 'Bodega',
                  building: 'Edificio',
              }[propertyType.value] ?? humanize(propertyType.value),
              {
                  house: 'House',
                  apartment: 'Apartment',
                  commercial_space: 'Commercial Space',
                  land: 'Land',
                  office_space: 'Office Space',
                  warehouse: 'Warehouse',
                  building: 'Building',
              }[propertyType.value] ?? humanize(propertyType.value),
          )
        : tr('Tipo de propiedad', 'Home type'),
);

const clearAdvancedFilters = (): void => {
    minPrice.value = '';
    maxPrice.value = '';
    bedrooms.value = '';
    bathrooms.value = '';
    parkingSpaces.value = '';
    minArea.value = '';
    maxArea.value = '';
    furnishing.value = '';
    utilitiesIncluded.value = '';
    applyFilters();
};

const humanize = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());

const paginationLabel = (label: string): string =>
    label
        .replace('&laquo; Previous', tr('Anterior', 'Previous'))
        .replace('Next &raquo;', tr('Siguiente', 'Next'));
const previousPageLink = computed(() => props.properties.links[0]);
const nextPageLink = computed(
    () => props.properties.links[props.properties.links.length - 1],
);

const cardTone = (index: number): string =>
    [
        'from-blue-200 via-sky-100 to-amber-100',
        'from-sky-200 via-cyan-100 to-blue-100',
        'from-orange-200 via-amber-100 to-stone-100',
        'from-violet-200 via-rose-100 to-orange-100',
    ][index % 4];

const seoTitle = 'Propiedades en alquiler y venta en Honduras';
const seoDescription =
    'Busca casas, apartamentos y propiedades disponibles en Honduras por ubicación, precio y características.';
const canonicalUrl = computed(() =>
    typeof window === 'undefined'
        ? page.url
        : new URL(page.url, window.location.origin).href,
);
</script>

<template>
    <Head :title="seoTitle">
        <meta name="description" :content="seoDescription" />
        <link rel="canonical" :href="canonicalUrl" />
        <meta property="og:locale" content="es_HN" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="Honducasa" />
        <meta property="og:title" :content="seoTitle" />
        <meta property="og:description" :content="seoDescription" />
        <meta property="og:url" :content="canonicalUrl" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="seoTitle" />
        <meta name="twitter:description" :content="seoDescription" />
    </Head>

    <div
        class="public-site min-h-screen bg-[var(--public-surface)] text-[var(--public-text)]"
    >
        <PublicHeader />

        <main class="public-container py-7">
            <div class="public-search-shell p-2">
                <form
                    class="grid gap-1 md:grid-cols-[1fr_190px_auto]"
                    @submit.prevent="search"
                >
                    <LocationTypeahead
                        v-model="location"
                        :locale="locale"
                        :placeholder="
                            nearbyActive
                                ? tr('Cerca de mí · 5 km', 'Near me · 5 km')
                                : polygonAreaActive
                                  ? tr('Área dibujada', 'Custom drawn area')
                                  : tr(
                                        'Ciudad o colonia',
                                        'City or neighborhood',
                                    )
                        "
                        show-draw-area
                        @input="clearAreaSearch"
                        @draw="drawingArea = true"
                        @select="searchSelectedLocation"
                    />
                    <label
                        class="flex items-center gap-3 border-l border-[var(--public-border)] px-5 py-3 transition hover:bg-[var(--public-surface-hover)]"
                    >
                        <SlidersHorizontal class="size-5 text-blue-700" />
                        <select
                            v-model="listingType"
                            class="w-full bg-transparent text-sm font-medium outline-none"
                        >
                            <option value="">
                                {{ tr('Comprar o alquilar', 'Buy or rent') }}
                            </option>
                            <option value="rent">
                                {{ tr('En alquiler', 'For rent') }}
                            </option>
                            <option value="buy">
                                {{ tr('En venta', 'For sale') }}
                            </option>
                        </select>
                    </label>
                    <button
                        class="flex items-center justify-center gap-2 rounded-[10px] bg-primary px-7 py-3 font-semibold text-primary-foreground transition hover:bg-primary-hover"
                        type="submit"
                    >
                        <Search class="size-5" /> {{ tr('Buscar', 'Search') }}
                    </button>
                </form>

                <div
                    class="mt-3 overflow-x-auto border-t border-[var(--public-border)] pt-3"
                >
                    <div class="flex min-w-max items-center gap-2 pb-1">
                        <button
                            type="button"
                            class="public-chip"
                            :class="
                                isSearchSaved
                                    ? 'border-primary text-[var(--public-brand-ink)]'
                                    : savedSearch?.hasChanges
                                      ? 'border-amber-400 text-amber-800'
                                      : ''
                            "
                            :disabled="isSearchSaved || savingSearch"
                            :aria-pressed="isSearchSaved"
                            @click="saveSearch"
                        >
                            <Heart
                                class="size-4 text-[var(--public-brand-ink)]"
                                :class="isSearchSaved ? 'fill-current' : ''"
                            />{{
                                isSearchSaved
                                    ? tr('Búsqueda guardada', 'Search saved')
                                    : savedSearch?.hasChanges
                                      ? tr(
                                            'Cambios sin guardar',
                                            'Unsaved changes',
                                        )
                                      : savingSearch
                                        ? tr('Guardando…', 'Saving…')
                                        : tr('Guardar búsqueda', 'Save search')
                            }}
                        </button>
                        <button
                            type="button"
                            class="public-chip"
                            :aria-expanded="showFilters"
                            @click="showFilters = true"
                        >
                            <Filter
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                            {{ tr('Filtros', 'Filters') }}
                            <span
                                v-if="activeAdvancedFilters.length"
                                class="grid size-5 place-items-center rounded-full bg-primary text-xs text-primary-foreground"
                                >{{ activeAdvancedFilters.length }}</span
                            >
                        </button>
                        <button
                            type="button"
                            class="public-chip"
                            :class="
                                minPrice || maxPrice
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            <CircleDollarSign
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                            {{ priceFilterLabel }}
                            <ChevronDown
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                        </button>
                        <button
                            type="button"
                            class="public-chip"
                            :class="
                                bedrooms || bathrooms
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            <BedDouble
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                            {{ roomsFilterLabel }}
                            <ChevronDown
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                        </button>
                        <button
                            type="button"
                            class="public-chip"
                            :class="
                                propertyType
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            <Building2
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                            {{ homeTypeFilterLabel }}
                            <ChevronDown
                                class="size-4 text-[var(--public-brand-ink)]"
                            />
                        </button>
                        <button
                            v-if="activeAdvancedFilters.length"
                            type="button"
                            class="px-3 py-3 text-sm font-semibold text-blue-800 underline-offset-4 hover:underline"
                            @click="clearAdvancedFilters"
                        >
                            {{ tr('Limpiar', 'Clear') }}
                        </button>
                    </div>
                </div>
            </div>

            <Teleport to="body">
                <Transition
                    enter-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-200"
                    leave-to-class="opacity-0"
                >
                    <button
                        v-if="showFilters"
                        type="button"
                        class="fixed inset-0 z-40 bg-black/55"
                        :aria-label="tr('Cerrar filtros', 'Close filters')"
                        @click="discardFilters"
                    />
                </Transition>
                <Transition
                    enter-active-class="transition-transform duration-300 ease-out"
                    enter-from-class="translate-x-full"
                    leave-active-class="transition-transform duration-200 ease-in"
                    leave-to-class="translate-x-full"
                >
                    <aside
                        v-if="showFilters"
                        class="public-site fixed inset-y-0 right-0 z-50 flex w-full translate-x-0 flex-col bg-[var(--public-surface-raised)] text-[var(--public-text)] shadow-2xl sm:max-w-xl"
                        :aria-label="
                            tr('Filtros de propiedades', 'Property filters')
                        "
                    >
                        <header
                            class="flex items-center justify-between border-b border-stone-200 px-5 py-5 sm:px-7"
                        >
                            <h2 class="text-2xl font-semibold">
                                {{ tr('Filtros', 'Filters') }}
                            </h2>
                            <button
                                type="button"
                                class="grid size-10 place-items-center rounded-full transition hover:bg-stone-100"
                                :aria-label="
                                    tr('Cerrar filtros', 'Close filters')
                                "
                                @click="discardFilters"
                            >
                                <X class="size-6" />
                            </button>
                        </header>

                        <div class="flex-1 overflow-y-auto">
                            <section
                                class="border-b border-stone-200 px-5 py-6 sm:px-7"
                            >
                                <h3 class="text-lg font-semibold">
                                    {{ tr('Precio', 'Price') }}
                                </h3>
                                <p class="mt-1 text-sm text-stone-500">
                                    {{
                                        tr(
                                            `Montos en ${currency}. Cambia la moneda en la barra superior.`,
                                            `Amounts in ${currency}. Change the currency in the top bar.`,
                                        )
                                    }}
                                </p>
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <label
                                        class="grid min-w-0 gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Mínimo', 'Minimum') }}
                                        <input
                                            v-model="minPrice"
                                            type="number"
                                            min="0"
                                            class="w-full min-w-0 appearance-none rounded-xl border border-stone-300 bg-transparent px-4 py-3 font-normal outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            :placeholder="
                                                tr('Sin mínimo', 'No min')
                                            "
                                        />
                                    </label>
                                    <label
                                        class="grid min-w-0 gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Máximo', 'Maximum') }}
                                        <input
                                            v-model="maxPrice"
                                            type="number"
                                            min="0"
                                            class="w-full min-w-0 appearance-none rounded-xl border border-stone-300 bg-transparent px-4 py-3 font-normal outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-700/20 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            :placeholder="
                                                tr('Sin máximo', 'No max')
                                            "
                                        />
                                    </label>
                                </div>
                            </section>

                            <section
                                class="border-b border-stone-200 px-5 py-6 sm:px-7"
                            >
                                <h3 class="text-lg font-semibold">
                                    {{ tr('Habitaciones', 'Rooms') }}
                                </h3>
                                <p class="mt-4 text-sm font-medium">
                                    {{ tr('Dormitorios', 'Bedrooms') }}
                                </p>
                                <div
                                    class="mt-2 grid grid-cols-6 overflow-hidden rounded-xl border border-stone-300"
                                >
                                    <button
                                        v-for="option in [
                                            '',
                                            '1',
                                            '2',
                                            '3',
                                            '4',
                                            '5',
                                        ]"
                                        :key="`bed-${option || 'any'}`"
                                        type="button"
                                        class="border-r border-stone-300 px-2 py-3 text-sm last:border-r-0"
                                        :class="
                                            bedrooms === option
                                                ? 'bg-blue-50 font-semibold text-blue-800'
                                                : 'hover:bg-stone-50'
                                        "
                                        @click="bedrooms = option"
                                    >
                                        {{
                                            option
                                                ? `${option}+`
                                                : tr('Todas', 'Any')
                                        }}
                                    </button>
                                </div>
                                <p class="mt-5 text-sm font-medium">
                                    {{ tr('Baños', 'Bathrooms') }}
                                </p>
                                <div
                                    class="mt-2 grid grid-cols-6 overflow-hidden rounded-xl border border-stone-300"
                                >
                                    <button
                                        v-for="option in [
                                            '',
                                            '1',
                                            '1.5',
                                            '2',
                                            '3',
                                            '4',
                                        ]"
                                        :key="`bath-${option || 'any'}`"
                                        type="button"
                                        class="border-r border-stone-300 px-2 py-3 text-sm last:border-r-0"
                                        :class="
                                            bathrooms === option
                                                ? 'bg-blue-50 font-semibold text-blue-800'
                                                : 'hover:bg-stone-50'
                                        "
                                        @click="bathrooms = option"
                                    >
                                        {{
                                            option
                                                ? `${option}+`
                                                : tr('Todos', 'Any')
                                        }}
                                    </button>
                                </div>
                            </section>

                            <section
                                class="border-b border-stone-200 px-5 py-6 sm:px-7"
                            >
                                <h3 class="text-lg font-semibold">
                                    {{ tr('Tipo de propiedad', 'Home type') }}
                                </h3>
                                <div
                                    class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"
                                >
                                    <button
                                        v-for="option in [
                                            {
                                                value: '',
                                                es: 'Cualquiera',
                                                en: 'Any',
                                            },
                                            {
                                                value: 'house',
                                                es: 'Casa',
                                                en: 'House',
                                            },
                                            {
                                                value: 'apartment',
                                                es: 'Apartamento',
                                                en: 'Apartment',
                                            },
                                            {
                                                value: 'commercial_space',
                                                es: 'Local Comercial',
                                                en: 'Commercial Space',
                                            },
                                            {
                                                value: 'land',
                                                es: 'Terreno',
                                                en: 'Land',
                                            },
                                            {
                                                value: 'office_space',
                                                es: 'Local Para Oficina',
                                                en: 'Office Space',
                                            },
                                            {
                                                value: 'warehouse',
                                                es: 'Bodega',
                                                en: 'Warehouse',
                                            },
                                            {
                                                value: 'building',
                                                es: 'Edificio',
                                                en: 'Building',
                                            },
                                        ]"
                                        :key="option.value || 'any'"
                                        type="button"
                                        class="grid min-h-24 place-items-center rounded-xl border p-3 text-sm font-semibold transition"
                                        :class="
                                            propertyType === option.value
                                                ? 'border-blue-700 bg-blue-50 text-blue-800'
                                                : 'border-stone-300 hover:border-stone-500'
                                        "
                                        @click="propertyType = option.value"
                                    >
                                        <Building2 class="mb-2 size-6" />
                                        {{ tr(option.es, option.en) }}
                                    </button>
                                </div>
                            </section>

                            <section
                                class="border-b border-stone-200 px-5 py-6 sm:px-7"
                            >
                                <h3 class="text-lg font-semibold">
                                    {{ tr('Detalles', 'Details') }}
                                </h3>
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <label
                                        class="grid min-w-0 gap-2 text-sm font-semibold"
                                    >
                                        {{
                                            tr(
                                                'Estacionamientos',
                                                'Parking spaces',
                                            )
                                        }}
                                        <select
                                            v-model="parkingSpaces"
                                            class="rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] px-4 py-3 font-normal text-[var(--public-text)] outline-none focus:border-primary"
                                        >
                                            <option value="">
                                                {{ tr('Cualquiera', 'Any') }}
                                            </option>
                                            <option
                                                v-for="number in 4"
                                                :key="number"
                                                :value="String(number)"
                                            >
                                                {{ number }}+
                                            </option>
                                        </select>
                                    </label>
                                    <label
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Amueblado', 'Furnishing') }}
                                        <select
                                            v-model="furnishing"
                                            class="rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] px-4 py-3 font-normal text-[var(--public-text)] outline-none focus:border-primary"
                                        >
                                            <option value="">
                                                {{ tr('Cualquiera', 'Any') }}
                                            </option>
                                            <option value="furnished">
                                                {{
                                                    tr('Amueblado', 'Furnished')
                                                }}
                                            </option>
                                            <option value="semi_furnished">
                                                {{
                                                    tr(
                                                        'Semi amueblado',
                                                        'Semi furnished',
                                                    )
                                                }}
                                            </option>
                                            <option value="unfurnished">
                                                {{
                                                    tr(
                                                        'No amueblado',
                                                        'Unfurnished',
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </label>
                                    <label
                                        class="grid min-w-0 gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Área mínima', 'Minimum area') }}
                                        <AreaUnitInput
                                            v-model="minArea"
                                            v-model:unit="areaUnit"
                                            :placeholder="
                                                tr('Sin mínimo', 'No min')
                                            "
                                            :aria-label="
                                                tr(
                                                    'Unidad del área mínima',
                                                    'Minimum area unit',
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="grid min-w-0 gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Área máxima', 'Maximum area') }}
                                        <AreaUnitInput
                                            v-model="maxArea"
                                            v-model:unit="areaUnit"
                                            :placeholder="
                                                tr('Sin máximo', 'No max')
                                            "
                                            :aria-label="
                                                tr(
                                                    'Unidad del área máxima',
                                                    'Maximum area unit',
                                                )
                                            "
                                        />
                                    </label>
                                    <label
                                        class="col-span-2 grid gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Servicios', 'Utilities') }}
                                        <select
                                            v-model="utilitiesIncluded"
                                            class="rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] px-4 py-3 font-normal text-[var(--public-text)] outline-none focus:border-primary"
                                        >
                                            <option value="">
                                                {{ tr('Cualquiera', 'Any') }}
                                            </option>
                                            <option value="1">
                                                {{
                                                    tr('Incluidos', 'Included')
                                                }}
                                            </option>
                                            <option value="0">
                                                {{
                                                    tr(
                                                        'No incluidos',
                                                        'Not included',
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </label>
                                </div>
                            </section>
                        </div>

                        <footer
                            class="grid grid-cols-2 gap-3 border-t border-[var(--public-border)] bg-[var(--public-surface-raised)] p-5 sm:px-7"
                        >
                            <button
                                type="button"
                                class="rounded-xl border border-stone-300 px-5 py-3 font-semibold transition hover:bg-stone-50"
                                @click="clearAdvancedFilters"
                            >
                                {{ tr('Limpiar', 'Clear') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground transition hover:bg-primary-hover"
                                @click="applyFilters"
                            >
                                {{ tr('Ver resultados', 'View results') }}
                            </button>
                        </footer>
                    </aside>
                </Transition>
            </Teleport>

            <div
                v-if="properties.data.length"
                class="relative left-1/2 mt-6 w-[calc(100vw-2rem)] -translate-x-1/2 transition-[width] sm:w-[calc(100vw-3rem)]"
            >
                <div
                    class="mb-5 flex flex-wrap items-center justify-between gap-4"
                >
                    <p class="text-sm font-medium text-stone-500">
                        {{ properties.total.toLocaleString() }}
                        {{ tr('resultados', 'results') }}
                    </p>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <label
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <span class="sr-only">{{
                                tr('Ordenar', 'Sort')
                            }}</span>
                            <select
                                v-model="sort"
                                class="public-chip appearance-auto pr-9 outline-none"
                                :aria-label="
                                    tr('Ordenar resultados', 'Sort results')
                                "
                                @change="search"
                            >
                                <option value="newest">
                                    {{ tr('Más recientes', 'Newest') }}
                                </option>
                                <option value="price_asc">
                                    {{ tr('Menor precio', 'Lowest price') }}
                                </option>
                                <option value="price_desc">
                                    {{ tr('Mayor precio', 'Highest price') }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="public-chip border-primary text-[var(--public-brand-ink)] hover:bg-primary hover:text-primary-foreground"
                            :aria-pressed="showMap"
                            @click="showMap = !showMap"
                        >
                            <List v-if="showMap" class="size-4" />
                            <MapIcon v-else class="size-4" />
                            {{
                                showMap
                                    ? tr('Ocultar mapa', 'Hide map')
                                    : tr('Mostrar mapa', 'Show map')
                            }}
                        </button>
                    </div>
                </div>

                <div
                    class="grid items-start gap-6"
                    :class="
                        showMap
                            ? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(420px,1fr)]'
                            : ''
                    "
                >
                    <section
                        class="grid gap-x-6 gap-y-10 sm:grid-cols-2"
                        :class="
                            showMap
                                ? 'xl:grid-cols-2 2xl:grid-cols-3'
                                : 'lg:grid-cols-3 xl:grid-cols-4'
                        "
                    >
                        <PublicPropertyCard
                            v-for="(property, index) in properties.data"
                            :key="property.id"
                            :property="property"
                            :return-to="resultsContextUrl"
                            :tone="cardTone(index)"
                            @favorite="toggleFavorite(property)"
                        />
                    </section>
                    <aside
                        v-if="showMap"
                        class="order-first h-[70vh] min-h-[520px] lg:sticky lg:top-5 lg:order-none lg:h-[calc(100vh-2.5rem)]"
                    >
                        <PropertyResultsMap
                            :properties="properties.data"
                            :initial-bounds="initialBounds"
                            :return-to="resultsContextUrl"
                            @favorite="toggleFavorite"
                            @search="searchMapBounds"
                        />
                    </aside>
                </div>
            </div>

            <section
                v-else
                class="mt-8 grid min-h-[360px] place-items-center rounded-[2rem] border border-dashed border-[var(--public-border)] bg-[var(--public-surface-raised)] p-8 text-center"
            >
                <div class="max-w-md">
                    <span
                        class="mx-auto grid size-16 place-items-center rounded-2xl bg-blue-100 text-blue-800"
                        ><Search class="size-7"
                    /></span>
                    <h2 class="mt-6 text-2xl font-semibold">
                        {{
                            tr(
                                'Prueba con otra ubicación',
                                'Try another location',
                            )
                        }}
                    </h2>
                    <p class="mt-3 leading-7 text-stone-600">
                        {{
                            tr(
                                'Busca Tegucigalpa, San Pedro Sula, La Ceiba, Roatán u otra ciudad importante.',
                                'Search Tegucigalpa, San Pedro Sula, La Ceiba, Roatán, or another major city.',
                            )
                        }}
                    </p>
                </div>
            </section>

            <nav
                v-if="properties.last_page > 1"
                class="mt-10"
                :aria-label="
                    tr(
                        'Páginas de resultados de búsqueda',
                        'Search result pages',
                    )
                "
            >
                <div class="flex items-center justify-between gap-3 sm:hidden">
                    <Link
                        v-if="previousPageLink?.url"
                        :href="previousPageLink.url"
                        preserve-scroll
                        class="rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] px-4 py-2.5 text-sm font-semibold transition hover:border-primary"
                    >
                        {{ tr('Anterior', 'Previous') }}
                    </Link>
                    <span
                        v-else
                        aria-disabled="true"
                        class="rounded-xl border border-[var(--public-border)] px-4 py-2.5 text-sm text-[var(--public-text-muted)] opacity-50"
                    >
                        {{ tr('Anterior', 'Previous') }}
                    </span>

                    <span
                        class="text-center text-sm font-medium text-[var(--public-text-muted)]"
                    >
                        {{ tr('Página', 'Page') }}
                        <strong class="text-[var(--public-text)]">
                            {{ properties.current_page }}
                        </strong>
                        {{ tr('de', 'of') }} {{ properties.last_page }}
                    </span>

                    <Link
                        v-if="nextPageLink?.url"
                        :href="nextPageLink.url"
                        preserve-scroll
                        class="rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] px-4 py-2.5 text-sm font-semibold transition hover:border-primary"
                    >
                        {{ tr('Siguiente', 'Next') }}
                    </Link>
                    <span
                        v-else
                        aria-disabled="true"
                        class="rounded-xl border border-[var(--public-border)] px-4 py-2.5 text-sm text-[var(--public-text-muted)] opacity-50"
                    >
                        {{ tr('Siguiente', 'Next') }}
                    </span>
                </div>

                <div class="hidden flex-wrap justify-center gap-2 sm:flex">
                    <template
                        v-for="link in properties.links"
                        :key="link.label"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="grid min-w-10 place-items-center rounded-xl border px-3 py-2 text-sm font-semibold transition"
                            :class="
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-[var(--public-border)] bg-[var(--public-surface-raised)] hover:border-primary'
                            "
                            :aria-current="link.active ? 'page' : undefined"
                            >{{ paginationLabel(link.label) }}</Link
                        >
                        <span
                            v-else
                            class="grid min-w-10 place-items-center rounded-xl border border-[var(--public-border)] px-3 py-2 text-sm text-[var(--public-text-muted)] opacity-50"
                            >{{ paginationLabel(link.label) }}</span
                        >
                    </template>
                </div>
            </nav>
        </main>

        <AuthModal
            v-model:open="authModalOpen"
            :description="authModalDescription"
        />
        <SavedSearchRefinementModal
            v-if="savedSearch"
            v-model:open="refinementModalOpen"
            :processing="savingSearch"
            :search-name="savedSearch.name"
            @update="updateSavedSearch"
            @duplicate="saveRefinedSearchAsNew"
        />
        <MapAreaDrawer
            v-if="drawingArea"
            :locale="locale"
            @close="drawingArea = false"
            @search="searchDrawnArea"
        />
        <Toaster />
    </div>
</template>
