<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle, MapPin, Search, ShieldCheck } from '@lucide/vue';
import {
    circle as createCircle,
    control,
    divIcon,
    map as createMap,
    marker as createMarker,
    tileLayer,
} from 'leaflet';
import type { Circle, Map as LeafletMap, Marker } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { search as placeSearch } from '@/routes/places';

type Coordinate = [number, number];
type Location = {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
};
type SearchResult = {
    id: string;
    geometry: { coordinates: Coordinate };
    properties: {
        full_address?: string;
        name?: string;
        place_formatted?: string;
    };
};

const props = withDefaults(
    defineProps<{
        /**
         * Cities a listing can be filed under, with their centers. The city is
         * no longer picked by hand — it is derived from the pin — so this is
         * only used to tell the publisher which one their pin resolves to.
         */
        locations: Location[];
        initialMode: 'exact' | 'approximate';
        initialLatitude: number;
        initialLongitude: number;
        initialApproximateRadiusKm: number;
        /**
         * Whether the map is currently shown. Pass `false` when it lives inside a
         * `v-show`-hidden container: Leaflet measures its container at creation
         * time, so a map mounted while hidden ends up with a stale zero size and
         * only partially renders once shown again. Toggling this back to `true`
         * triggers `invalidateSize()` so it re-measures against its real size.
         */
        visible?: boolean;
    }>(),
    {
        visible: true,
    },
);
const mode = ref<'exact' | 'approximate'>(props.initialMode);
const approximateRadiusMeters = ref(
    Math.round(props.initialApproximateRadiusKm * 1000),
);
const latitude = ref(props.initialLatitude);
const longitude = ref(props.initialLongitude);
const mapContainer = ref<HTMLElement | null>(null);
const mapError = ref(false);
const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const placeSearchHttp = useHttp<{ q: string }, { features: SearchResult[] }>({
    q: '',
});
let searchTimer: number | undefined;
let map: LeafletMap | null = null;
let marker: Marker | null = null;
let approximateArea: Circle | null = null;

/**
 * Mirrors `App\Support\NearestCity` so the publisher sees the same city the
 * server will file the listing under. Advisory only — the server re-derives it.
 */
const distanceInKilometers = (
    from: [number, number],
    to: [number, number],
): number => {
    const toRadians = (degrees: number): number => (degrees * Math.PI) / 180;
    const latitudeDelta = toRadians(to[0] - from[0]);
    const longitudeDelta = toRadians(to[1] - from[1]);
    const chord =
        Math.sin(latitudeDelta / 2) ** 2 +
        Math.cos(toRadians(from[0])) *
            Math.cos(toRadians(to[0])) *
            Math.sin(longitudeDelta / 2) ** 2;

    return 2 * 6371.0088 * Math.asin(Math.min(1, Math.sqrt(chord)));
};

const detectedCity = computed(() =>
    props.locations
        .filter((location) => location.latitude !== null)
        .map((location) => ({
            location,
            distance: distanceInKilometers(
                [latitude.value, longitude.value],
                [location.latitude!, location.longitude!],
            ),
        }))
        .sort((a, b) => a.distance - b.distance)
        .at(0),
);

const mapboxAccessToken = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN;
const mapboxStyle = import.meta.env.VITE_MAPBOX_STYLE ?? 'mapbox/streets-v12';
const markerIcon = divIcon({
    className: 'honducasa-map-marker',
    html: '<span aria-hidden="true"></span>',
    iconAnchor: [16, 38],
    iconSize: [32, 38],
});

const removeLocationLayers = (): void => {
    if (!map) {
        return;
    }

    marker?.removeFrom(map);
    approximateArea?.removeFrom(map);
    marker = null;
    approximateArea = null;
};

const setMarker = (coordinates: Coordinate): void => {
    marker?.removeFrom(map!);
    const markerInstance = createMarker([coordinates[1], coordinates[0]], {
        draggable: true,
        icon: markerIcon,
    }).addTo(map!);
    marker = markerInstance;
    markerInstance.on('dragend', () => {
        const position = markerInstance.getLatLng();
        latitude.value = Number(position.lat.toFixed(6));
        longitude.value = Number(position.lng.toFixed(6));
    });
};

const showRadius = (coordinates: Coordinate): void => {
    removeLocationLayers();
    approximateArea = createCircle([coordinates[1], coordinates[0]], {
        color: '#1D4ED8',
        fillColor: '#2563EB',
        fillOpacity: 0.2,
        radius: approximateRadiusMeters.value,
        weight: 2,
    }).addTo(map!);
};

const renderSelection = (): void => {
    if (!map) {
        return;
    }

    removeLocationLayers();

    if (mode.value === 'exact') {
        setMarker([longitude.value, latitude.value]);
        map.flyTo([latitude.value, longitude.value], 15);

        return;
    }

    showRadius([longitude.value, latitude.value]);
    map.flyTo([latitude.value, longitude.value], 11);
};

const searchPlaces = (): void => {
    window.clearTimeout(searchTimer);
    const query = searchQuery.value.trim();

    if (query.length < 3) {
        placeSearchHttp.cancel();
        searchResults.value = [];

        return;
    }

    searchTimer = window.setTimeout(() => {
        placeSearchHttp.cancel();
        placeSearchHttp.q = query;
        placeSearchHttp
            .get(placeSearch.url(), {
                onSuccess: (response) => {
                    searchResults.value = response.features ?? [];
                },
            })
            .catch(() => {
                searchResults.value = [];
            });
    }, 350);
};

const selectSearchResult = (result: SearchResult): void => {
    const [lng, lat] = result.geometry.coordinates;
    longitude.value = Number(lng.toFixed(6));
    latitude.value = Number(lat.toFixed(6));
    searchQuery.value =
        result.properties.full_address ??
        [result.properties.name, result.properties.place_formatted]
            .filter(Boolean)
            .join(', ');
    searchResults.value = [];
    renderSelection();
};

watch(mode, () => nextTick(renderSelection));
watch(approximateRadiusMeters, () => {
    approximateArea?.setRadius(approximateRadiusMeters.value);
});
watch(
    () => props.visible,
    (isVisible) => {
        if (isVisible) {
            nextTick(() => map?.invalidateSize());
        }
    },
);

onMounted(() => {
    if (!mapboxAccessToken) {
        mapError.value = true;

        return;
    }

    const mapInstance = createMap(mapContainer.value!, {
        center: [latitude.value, longitude.value],
        maxBounds: [
            [12.9, -89.4],
            [16.6, -83.1],
        ],
        zoom: mode.value === 'exact' ? 15 : 11,
        zoomControl: false,
    });
    map = mapInstance;
    control.zoom({ position: 'topright' }).addTo(mapInstance);
    tileLayer(
        `https://api.mapbox.com/styles/v1/${mapboxStyle}/tiles/256/{z}/{x}/{y}@2x?access_token=${mapboxAccessToken}`,
        {
            attribution:
                '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 20,
        },
    )
        .on('load', () => {
            mapError.value = false;
        })
        .on('tileerror', () => {
            mapError.value = true;
        })
        .addTo(mapInstance);
    renderSelection();
    mapInstance.on('click', (event) => {
        const coordinates: Coordinate = [
            Number(event.latlng.lng.toFixed(6)),
            Number(event.latlng.lat.toFixed(6)),
        ];

        if (mode.value === 'exact') {
            longitude.value = coordinates[0];
            latitude.value = coordinates[1];
            setMarker(coordinates);

            return;
        }

        longitude.value = coordinates[0];
        latitude.value = coordinates[1];
        showRadius(coordinates);
    });
});

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer);
    placeSearchHttp.cancel();
    map?.remove();
});
</script>

<template>
    <div class="space-y-4">
        <input type="hidden" name="location_mode" :value="mode" />
        <input type="hidden" name="latitude" :value="latitude" />
        <input type="hidden" name="longitude" :value="longitude" />
        <input
            type="hidden"
            name="approximate_shape"
            :value="mode === 'approximate' ? 'radius' : ''"
        />
        <input
            type="hidden"
            name="approximate_radius_km"
            :value="
                mode === 'approximate' ? approximateRadiusMeters / 1000 : ''
            "
        />

        <div class="grid gap-3 sm:grid-cols-2">
            <label
                class="cursor-pointer rounded-xl border p-4 transition"
                :class="
                    mode === 'exact'
                        ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/30'
                        : ''
                "
            >
                <input v-model="mode" type="radio" value="exact" class="mr-2" />
                <span class="font-semibold">{{
                    $page.props.locale === 'es'
                        ? 'Ubicación exacta'
                        : 'Exact location'
                }}</span>
                <span class="mt-1 block text-sm text-muted-foreground">{{
                    $page.props.locale === 'es'
                        ? 'Haz clic o arrastra el pin en el mapa.'
                        : 'Click the map or drag the pin.'
                }}</span>
            </label>
            <label
                class="cursor-pointer rounded-xl border p-4 transition"
                :class="
                    mode === 'approximate'
                        ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/30'
                        : ''
                "
            >
                <input
                    v-model="mode"
                    type="radio"
                    value="approximate"
                    class="mr-2"
                />
                <span class="font-semibold">{{
                    $page.props.locale === 'es'
                        ? 'Región aproximada'
                        : 'Approximate region'
                }}</span>
                <span class="mt-1 block text-sm text-muted-foreground">{{
                    $page.props.locale === 'es'
                        ? 'Define un radio alrededor de la propiedad.'
                        : 'Set a radius around the property.'
                }}</span>
            </label>
        </div>

        <div
            class="flex items-start gap-2.5 rounded-xl bg-blue-50 p-3 text-xs text-blue-900 dark:bg-blue-950/30 dark:text-blue-200"
        >
            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
            <span>
                {{
                    $page.props.locale === 'es'
                        ? 'La ubicación exacta muestra el pin donde lo colocas. Elige "Región aproximada" si prefieres ocultar el punto exacto.'
                        : 'Exact location shows the pin where you place it. Choose "Approximate region" if you prefer to hide the exact point.'
                }}
            </span>
        </div>

        <div class="relative z-20">
            <Search
                class="pointer-events-none absolute top-3.5 left-4 size-5 text-muted-foreground"
            />
            <input
                v-model="searchQuery"
                type="search"
                :placeholder="
                    $page.props.locale === 'es'
                        ? 'Buscar dirección, colonia o lugar'
                        : 'Search an address, neighborhood or place'
                "
                autocomplete="off"
                class="w-full rounded-xl border bg-background py-3 pr-11 pl-12 text-sm shadow-sm"
                @input="searchPlaces"
            />
            <LoaderCircle
                v-if="placeSearchHttp.processing"
                class="absolute top-3.5 right-4 size-5 animate-spin text-blue-600"
            />
            <div
                v-if="searchResults.length"
                class="absolute top-full right-0 left-0 mt-2 overflow-hidden rounded-xl border bg-popover shadow-xl"
            >
                <button
                    v-for="result in searchResults"
                    :key="result.id"
                    type="button"
                    class="flex w-full items-start gap-3 border-b p-3 text-left text-sm last:border-0 hover:bg-muted"
                    @click="selectSearchResult(result)"
                >
                    <MapPin class="mt-0.5 size-4 shrink-0 text-blue-600" />
                    <span>
                        <strong class="block font-medium">{{
                            result.properties.name
                        }}</strong>
                        <span class="text-muted-foreground">{{
                            result.properties.full_address ??
                            result.properties.place_formatted
                        }}</span>
                    </span>
                </button>
            </div>
        </div>

        <div
            v-if="mode === 'approximate'"
            class="rounded-xl border bg-muted/30 p-4"
        >
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="font-medium">{{
                    $page.props.locale === 'es'
                        ? 'Tamaño del área'
                        : 'Area size'
                }}</span>
                <strong class="text-blue-700">{{
                    `${approximateRadiusMeters} m`
                }}</strong>
            </div>
            <input
                v-model.number="approximateRadiusMeters"
                type="range"
                min="100"
                max="500"
                step="100"
                class="mt-3 w-full accent-blue-600"
            />
            <div class="mt-2 grid grid-cols-5 gap-1">
                <button
                    v-for="meters in [100, 200, 300, 400, 500]"
                    :key="meters"
                    type="button"
                    class="rounded-md px-1 py-1.5 text-xs font-medium transition"
                    :class="
                        approximateRadiusMeters === meters
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="approximateRadiusMeters = meters"
                >
                    {{ `${meters} m` }}
                </button>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                {{
                    $page.props.locale === 'es'
                        ? 'Haz clic en el mapa para mover el centro del radio.'
                        : 'Click the map to move the center of the radius.'
                }}
            </p>
        </div>

        <div class="relative isolate h-96 overflow-hidden rounded-2xl border">
            <div ref="mapContainer" class="absolute inset-0" />
            <div
                v-if="mapError"
                class="absolute inset-0 z-10 flex items-center justify-center bg-slate-50/95 p-6 text-center text-sm text-slate-600 dark:bg-slate-950/95 dark:text-slate-300"
                role="alert"
            >
                {{
                    $page.props.locale === 'es'
                        ? 'No pudimos cargar el mapa. Verifica la configuración de Mapbox e inténtalo de nuevo.'
                        : 'We could not load the map. Check the Mapbox configuration and try again.'
                }}
            </div>
        </div>
        <div
            class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 p-3 text-sm"
        >
            <MapPin class="size-4 shrink-0 text-blue-600" />
            <span class="text-muted-foreground">{{
                $page.props.locale === 'es'
                    ? 'Ciudad detectada:'
                    : 'Detected city:'
            }}</span>
            <strong v-if="detectedCity">{{
                detectedCity.location.name
            }}</strong>
            <span v-else class="text-destructive">{{
                $page.props.locale === 'es'
                    ? 'Ninguna ciudad cercana disponible'
                    : 'No supported city nearby'
            }}</span>
        </div>

        <p class="text-sm text-muted-foreground">
            {{
                mode === 'exact'
                    ? $page.props.locale === 'es'
                        ? 'El pin seleccionado se usará para la búsqueda por cercanía.'
                        : 'The selected pin will be used for nearby search.'
                    : $page.props.locale === 'es'
                      ? 'La región pública mostrará el área seleccionada sin revelar una dirección exacta.'
                      : 'The public region will show the selected area without revealing an exact address.'
            }}
        </p>
    </div>
</template>

<style scoped>
:deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 0.875rem;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.12);
}

:deep(.leaflet-control-zoom a) {
    width: 2.5rem;
    height: 2.5rem;
    border: 0;
    line-height: 2.5rem;
}

:deep(.leaflet-control-attribution) {
    border-radius: 0.5rem 0 0 0;
    font-size: 0.6875rem;
}

:deep(.honducasa-map-marker) {
    background: transparent;
}

:deep(.honducasa-map-marker span) {
    display: block;
    width: 2rem;
    height: 2rem;
    border: 4px solid white;
    border-radius: 50% 50% 50% 0;
    background: #2563eb;
    box-shadow: 0 8px 18px rgb(15 23 42 / 0.3);
    transform: rotate(-45deg);
}
</style>
