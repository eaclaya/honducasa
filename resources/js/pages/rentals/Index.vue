<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Bath,
    BedDouble,
    Building2,
    Car,
    ChevronDown,
    Filter,
    House,
    Heart,
    List,
    Map as MapIcon,
    Maximize2,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import LocationTypeahead from '@/components/LocationTypeahead.vue';
import PropertyResultsMap from '@/components/PropertyResultsMap.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { login } from '@/routes';
import { store as favorite, destroy as unfavorite } from '@/routes/favorites';
import { show as propertyShow } from '@/routes/properties';
import { index as rentals } from '@/routes/rentals';
import { store as saveSearchRoute } from '@/routes/saved-searches';

type Rental = {
    id: number;
    slug: string;
    name: string | null;
    type: string;
    listingType: 'rent' | 'buy';
    location: string;
    bedrooms: number;
    bathrooms: string;
    parkingSpaces: number;
    interiorAreaM2: number | null;
    furnishing: string;
    priceAmount: number;
    currency: string;
    depositAmount: number | null;
    utilitiesIncluded: boolean;
    mapLatitude: number;
    mapLongitude: number;
    primaryImage: { url: string; altText: string | null } | null;
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
    | 'currency'
    | 'minPrice'
    | 'maxPrice'
    | 'bedrooms'
    | 'bathrooms'
    | 'parkingSpaces'
    | 'minArea'
    | 'maxArea'
    | 'furnishing'
    | 'utilitiesIncluded';

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
    };
    properties: PaginatedRentals;
}>();

const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const location = ref(props.filters.location);
const nearbyLatitude = ref(props.filters.latitude);
const nearbyLongitude = ref(props.filters.longitude);
const propertyType = ref(props.filters.propertyType);
const listingType = ref(props.filters.listingType);
const currency = ref(props.filters.currency);
const minPrice = ref(props.filters.minPrice);
const maxPrice = ref(props.filters.maxPrice);
const bedrooms = ref(props.filters.bedrooms);
const bathrooms = ref(props.filters.bathrooms);
const parkingSpaces = ref(props.filters.parkingSpaces);
const minArea = ref(props.filters.minArea);
const maxArea = ref(props.filters.maxArea);
const furnishing = ref(props.filters.furnishing);
const utilitiesIncluded = ref(
    props.filters.utilitiesIncluded === null
        ? ''
        : props.filters.utilitiesIncluded
          ? '1'
          : '0',
);
const sort = ref(props.filters.sort);
const showMap = ref(false);
const showFilters = ref(false);
const nearbyActive = computed(
    () => nearbyLatitude.value !== null && nearbyLongitude.value !== null,
);
const clearNearbySearch = (): void => {
    nearbyLatitude.value = null;
    nearbyLongitude.value = null;
};
const saveSearch = (): void => {
    if (!page.props.auth.user) {
        router.visit(login.url());

        return;
    }

    const filters = Object.fromEntries(
        Object.entries(queryParameters()).filter(
            ([, value]) => value !== undefined,
        ),
    );
    router.post(
        saveSearchRoute.url(),
        {
            name: nearbyActive.value
                ? tr('Propiedades cerca de mí', 'Properties near me')
                : location.value
                  ? `${tr('Propiedades en', 'Properties in')} ${location.value}`
                  : tr('Mi búsqueda', 'My search'),
            filters,
            alerts_enabled: true,
        },
        { preserveScroll: true },
    );
};
const toggleFavorite = (property: Rental): void => {
    if (!page.props.auth.user) {
        router.visit(login.url());

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
    currency: currency.value || undefined,
    min_price: minPrice.value || undefined,
    max_price: maxPrice.value || undefined,
    bedrooms: bedrooms.value || undefined,
    bathrooms: bathrooms.value || undefined,
    parking_spaces: parkingSpaces.value || undefined,
    min_area: minArea.value || undefined,
    max_area: maxArea.value || undefined,
    furnishing: furnishing.value || undefined,
    utilities_included:
        utilitiesIncluded.value === '' ? undefined : utilitiesIncluded.value,
    sort: sort.value === 'newest' ? undefined : sort.value,
    latitude: nearbyLatitude.value ?? undefined,
    longitude: nearbyLongitude.value ?? undefined,
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
    clearNearbySearch();
    search();
};

const applyFilters = (): void => {
    showFilters.value = false;
    search();
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
    const money = currency.value || 'HNL';

    if (currency.value) {
        filters.push({ key: 'currency', label: currency.value });
    }

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
            label: `${tr('Área desde', 'Area from')} ${minArea.value} m²`,
        });
    }

    if (maxArea.value) {
        filters.push({
            key: 'maxArea',
            label: `${tr('Área hasta', 'Area up to')} ${maxArea.value} m²`,
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

    const selectedCurrency = currency.value || 'HNL';

    return `${selectedCurrency} ${minPrice.value || '0'}–${maxPrice.value || '∞'}`;
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
                  apartment: 'Apartamento',
                  house: 'Casa',
                  condominium: 'Condominio',
                  townhouse: 'Casa adosada',
                  room: 'Habitación',
                  studio: 'Estudio',
              }[propertyType.value] ?? humanize(propertyType.value),
              {
                  apartment: 'Apartment',
                  house: 'House',
                  condominium: 'Condo',
                  townhouse: 'Townhouse',
                  room: 'Room',
                  studio: 'Studio',
              }[propertyType.value] ?? humanize(propertyType.value),
          )
        : tr('Tipo de propiedad', 'Home type'),
);

const clearAdvancedFilters = (): void => {
    currency.value = '';
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
        .replace('&laquo; Previous', 'Previous')
        .replace('Next &raquo;', 'Next');

const cardTone = (index: number): string =>
    [
        'from-blue-200 via-sky-100 to-amber-100',
        'from-sky-200 via-cyan-100 to-blue-100',
        'from-orange-200 via-amber-100 to-stone-100',
        'from-violet-200 via-rose-100 to-orange-100',
    ][index % 4];

const formatPrice = (property: Rental): string =>
    new Intl.NumberFormat('es-HN', {
        style: 'currency',
        currency: property.currency,
        maximumFractionDigits: 0,
    }).format(property.priceAmount);
</script>

<template>
    <Head title="Property search" />

    <div class="min-h-screen bg-slate-100 text-[#13233a]">
        <PublicHeader />

        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <div
                class="rounded-3xl border border-stone-200 bg-white p-3 shadow-sm"
            >
                <form
                    class="grid gap-3 md:grid-cols-[1fr_190px_160px_auto]"
                    @submit.prevent="search"
                >
                    <LocationTypeahead
                        v-model="location"
                        :locale="locale"
                        :placeholder="
                            nearbyActive
                                ? tr('Cerca de mí · 2 km', 'Near me · 2 km')
                                : tr('Ciudad o colonia', 'City or neighborhood')
                        "
                        @input="clearNearbySearch"
                        @select="searchSelectedLocation"
                    />
                    <label
                        class="flex items-center gap-3 rounded-2xl bg-stone-50 px-4 py-3"
                    >
                        <Building2 class="size-5 text-blue-700" />
                        <select
                            v-model="propertyType"
                            class="w-full bg-transparent text-sm font-medium outline-none"
                        >
                            <option value="">
                                {{ tr('Cualquier propiedad', 'Any home') }}
                            </option>
                            <option value="apartment">
                                {{ tr('Apartamento', 'Apartment') }}
                            </option>
                            <option value="house">
                                {{ tr('Casa', 'House') }}
                            </option>
                            <option value="condominium">
                                {{ tr('Condominio', 'Condominium') }}
                            </option>
                            <option value="townhouse">
                                {{ tr('Casa adosada', 'Townhouse') }}
                            </option>
                            <option value="studio">
                                {{ tr('Estudio', 'Studio') }}
                            </option>
                            <option value="room">
                                {{ tr('Habitación', 'Room') }}
                            </option>
                        </select>
                    </label>
                    <label
                        class="flex items-center gap-3 rounded-2xl bg-stone-50 px-4 py-3"
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
                        class="flex items-center justify-center gap-2 rounded-2xl bg-[#123b6d] px-6 py-3 font-semibold text-white transition hover:bg-[#185a96]"
                        type="submit"
                    >
                        <Search class="size-5" /> {{ tr('Buscar', 'Search') }}
                    </button>
                </form>

                <div
                    class="mt-3 overflow-x-auto border-t border-stone-200 pt-3"
                >
                    <div class="flex min-w-max items-center gap-2 pb-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-stone-300 bg-white px-5 py-2.5 text-sm font-semibold"
                            @click="saveSearch"
                        >
                            <Heart class="size-4" />{{
                                tr('Guardar búsqueda', 'Save search')
                            }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold transition hover:border-blue-700"
                            :aria-expanded="showFilters"
                            @click="showFilters = true"
                        >
                            <Filter class="size-4" />
                            {{ tr('Filtros', 'Filters') }}
                            <span
                                v-if="activeAdvancedFilters.length"
                                class="grid size-5 place-items-center rounded-full bg-blue-700 text-xs text-white"
                                >{{ activeAdvancedFilters.length }}</span
                            >
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-3 text-sm font-semibold transition hover:border-blue-700"
                            :class="
                                minPrice || maxPrice
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            {{ priceFilterLabel }}
                            <ChevronDown class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-3 text-sm font-semibold transition hover:border-blue-700"
                            :class="
                                bedrooms || bathrooms
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            {{ roomsFilterLabel }}
                            <ChevronDown class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-3 text-sm font-semibold transition hover:border-blue-700"
                            :class="
                                propertyType
                                    ? 'border-blue-700 text-blue-800'
                                    : 'border-stone-300'
                            "
                            @click="showFilters = true"
                        >
                            {{ homeTypeFilterLabel }}
                            <ChevronDown class="size-4" />
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
                        @click="showFilters = false"
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
                        class="fixed inset-y-0 right-0 z-50 flex w-full translate-x-0 flex-col bg-white text-[#13233a] shadow-2xl sm:max-w-xl"
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
                                @click="showFilters = false"
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
                                <div class="mt-4 grid grid-cols-3 gap-2">
                                    <button
                                        v-for="option in ['', 'HNL', 'USD']"
                                        :key="option || 'any'"
                                        type="button"
                                        class="rounded-xl border px-3 py-3 text-sm font-semibold transition"
                                        :class="
                                            currency === option
                                                ? 'border-blue-700 bg-blue-50 text-blue-800'
                                                : 'border-stone-300 hover:border-stone-500'
                                        "
                                        @click="currency = option"
                                    >
                                        {{ option || tr('Cualquiera', 'Any') }}
                                    </button>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <label
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Mínimo', 'Minimum') }}
                                        <input
                                            v-model="minPrice"
                                            type="number"
                                            min="0"
                                            class="rounded-xl border border-stone-300 px-4 py-3 font-normal outline-none focus:border-blue-700"
                                            :placeholder="
                                                tr('Sin mínimo', 'No min')
                                            "
                                        />
                                    </label>
                                    <label
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Máximo', 'Maximum') }}
                                        <input
                                            v-model="maxPrice"
                                            type="number"
                                            min="0"
                                            class="rounded-xl border border-stone-300 px-4 py-3 font-normal outline-none focus:border-blue-700"
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
                                                value: 'condominium',
                                                es: 'Condominio',
                                                en: 'Condo',
                                            },
                                            {
                                                value: 'townhouse',
                                                es: 'Casa adosada',
                                                en: 'Townhouse',
                                            },
                                            {
                                                value: 'room',
                                                es: 'Habitación',
                                                en: 'Room',
                                            },
                                            {
                                                value: 'studio',
                                                es: 'Estudio',
                                                en: 'Studio',
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
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{
                                            tr(
                                                'Estacionamientos',
                                                'Parking spaces',
                                            )
                                        }}
                                        <select
                                            v-model="parkingSpaces"
                                            class="rounded-xl border border-stone-300 bg-white px-4 py-3 font-normal outline-none focus:border-blue-700"
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
                                            class="rounded-xl border border-stone-300 bg-white px-4 py-3 font-normal outline-none focus:border-blue-700"
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
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{
                                            tr(
                                                'Área mínima m²',
                                                'Minimum area m²',
                                            )
                                        }}
                                        <input
                                            v-model="minArea"
                                            type="number"
                                            min="0"
                                            class="rounded-xl border border-stone-300 px-4 py-3 font-normal outline-none focus:border-blue-700"
                                            :placeholder="
                                                tr('Sin mínimo', 'No min')
                                            "
                                        />
                                    </label>
                                    <label
                                        class="grid gap-2 text-sm font-semibold"
                                    >
                                        {{
                                            tr(
                                                'Área máxima m²',
                                                'Maximum area m²',
                                            )
                                        }}
                                        <input
                                            v-model="maxArea"
                                            type="number"
                                            min="0"
                                            class="rounded-xl border border-stone-300 px-4 py-3 font-normal outline-none focus:border-blue-700"
                                            :placeholder="
                                                tr('Sin máximo', 'No max')
                                            "
                                        />
                                    </label>
                                    <label
                                        class="col-span-2 grid gap-2 text-sm font-semibold"
                                    >
                                        {{ tr('Servicios', 'Utilities') }}
                                        <select
                                            v-model="utilitiesIncluded"
                                            class="rounded-xl border border-stone-300 bg-white px-4 py-3 font-normal outline-none focus:border-blue-700"
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
                            class="grid grid-cols-2 gap-3 border-t border-stone-200 bg-white p-5 sm:px-7"
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
                                class="rounded-xl bg-[#123b6d] px-5 py-3 font-semibold text-white transition hover:bg-[#185a96]"
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
                                class="rounded-full border border-stone-300 bg-white px-4 py-2.5 transition outline-none hover:border-blue-700"
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
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-[#123b6d] bg-white px-5 py-2.5 text-sm font-semibold text-[#123b6d] shadow-sm transition hover:bg-[#123b6d] hover:text-white"
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
                        class="grid gap-5 sm:grid-cols-2"
                        :class="
                            showMap
                                ? 'xl:grid-cols-3'
                                : 'lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5'
                        "
                    >
                        <Link
                            v-for="(property, index) in properties.data"
                            :key="property.id"
                            :href="propertyShow.url(property.slug)"
                            class="overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                        >
                            <div
                                class="relative grid aspect-[16/10] place-items-center bg-gradient-to-br"
                                :class="cardTone(index)"
                            >
                                <img
                                    v-if="property.primaryImage"
                                    :src="property.primaryImage.url"
                                    :alt="
                                        property.primaryImage.altText ??
                                        property.name ??
                                        'Rental property'
                                    "
                                    class="absolute inset-0 size-full object-cover"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="grid size-24 place-items-center rounded-[2rem] bg-white/65 text-[#123b6d] shadow-sm backdrop-blur"
                                >
                                    <House
                                        class="size-11"
                                        :stroke-width="1.5"
                                    />
                                </div>
                                <span
                                    class="absolute top-4 left-4 rounded-full bg-white/85 px-3 py-1.5 text-xs font-bold backdrop-blur"
                                    >{{ humanize(property.type) }}</span
                                >
                                <button
                                    type="button"
                                    class="absolute top-4 right-4 grid size-10 place-items-center rounded-full bg-white/90 text-blue-800 shadow"
                                    :aria-label="
                                        tr('Guardar propiedad', 'Save property')
                                    "
                                    @click.prevent.stop="
                                        toggleFavorite(property)
                                    "
                                >
                                    <Heart
                                        class="size-5"
                                        :class="
                                            property.isFavorited
                                                ? 'fill-current'
                                                : ''
                                        "
                                    />
                                </button>
                            </div>
                            <div class="p-5">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <h2
                                            class="line-clamp-1 text-lg font-semibold"
                                        >
                                            {{
                                                property.name ??
                                                'Rental property'
                                            }}
                                        </h2>
                                        <p
                                            class="mt-1 flex items-center gap-1.5 text-sm text-stone-500"
                                        >
                                            <MapPin class="size-4" />
                                            {{ property.location }}, Honduras
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="mt-5 grid grid-cols-4 gap-2 border-t border-stone-100 pt-4 text-center text-xs text-stone-600"
                                >
                                    <span
                                        class="flex flex-col items-center gap-1"
                                        ><BedDouble
                                            class="size-4 text-blue-700"
                                        />{{ property.bedrooms }} beds</span
                                    >
                                    <span
                                        class="flex flex-col items-center gap-1"
                                        ><Bath class="size-4 text-blue-700" />{{
                                            property.bathrooms
                                        }}
                                        baths</span
                                    >
                                    <span
                                        class="flex flex-col items-center gap-1"
                                        ><Car class="size-4 text-blue-700" />{{
                                            property.parkingSpaces
                                        }}
                                        parks</span
                                    >
                                    <span
                                        class="flex flex-col items-center gap-1"
                                        ><Maximize2
                                            class="size-4 text-blue-700"
                                        />{{
                                            property.interiorAreaM2 ?? '—'
                                        }}
                                        m²</span
                                    >
                                </div>
                                <div
                                    class="mt-4 flex items-center justify-between"
                                >
                                    <span
                                        class="text-xs font-medium text-stone-500"
                                        >{{
                                            humanize(property.furnishing)
                                        }}</span
                                    >
                                    <span class="font-semibold text-blue-800"
                                        >{{ formatPrice(property)
                                        }}<span
                                            v-if="
                                                property.listingType === 'rent'
                                            "
                                            class="text-xs font-normal text-stone-500"
                                            >/mo</span
                                        ></span
                                    >
                                </div>
                                <p
                                    v-if="property.utilitiesIncluded"
                                    class="mt-2 text-xs font-medium text-blue-700"
                                >
                                    Utilities included
                                </p>
                            </div>
                        </Link>
                    </section>
                    <aside
                        v-if="showMap"
                        class="order-first h-[70vh] min-h-[520px] lg:sticky lg:top-5 lg:order-none lg:h-[calc(100vh-2.5rem)]"
                    >
                        <PropertyResultsMap
                            :properties="properties.data"
                            :initial-bounds="initialBounds"
                            @search="searchMapBounds"
                        />
                    </aside>
                </div>
            </div>

            <section
                v-else
                class="mt-8 grid min-h-[360px] place-items-center rounded-[2rem] border border-dashed border-stone-300 bg-white p-8 text-center"
            >
                <div class="max-w-md">
                    <span
                        class="mx-auto grid size-16 place-items-center rounded-2xl bg-blue-100 text-blue-800"
                        ><Search class="size-7"
                    /></span>
                    <h2 class="mt-6 text-2xl font-semibold">
                        Try another location
                    </h2>
                    <p class="mt-3 leading-7 text-stone-600">
                        Search Tegucigalpa, San Pedro Sula, La Ceiba, Roatán, or
                        another major Honduran city.
                    </p>
                </div>
            </section>

            <nav
                v-if="properties.last_page > 1"
                class="mt-10 flex flex-wrap justify-center gap-2"
                aria-label="Search result pages"
            >
                <template v-for="link in properties.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="grid min-w-10 place-items-center rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="
                            link.active
                                ? 'border-[#123b6d] bg-[#123b6d] text-white'
                                : 'border-stone-200 bg-white hover:border-blue-700'
                        "
                        >{{ paginationLabel(link.label) }}</Link
                    >
                    <span
                        v-else
                        class="grid min-w-10 place-items-center rounded-xl border border-stone-200 px-3 py-2 text-sm text-stone-300"
                        >{{ paginationLabel(link.label) }}</span
                    >
                </template>
            </nav>
        </main>
    </div>
</template>
