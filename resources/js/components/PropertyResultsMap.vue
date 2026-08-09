<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    control,
    divIcon,
    latLngBounds,
    layerGroup,
    map as createMap,
    marker,
    tileLayer,
} from 'leaflet';
import type { LayerGroup, Map as LeafletMap } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { show as propertyShow } from '@/routes/properties';

type MapProperty = {
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
    priceAmount: number;
    currency: string;
    mapLatitude: number;
    mapLongitude: number;
    primaryImage: { url: string; altText: string | null } | null;
};
type Bounds = {
    west: number;
    south: number;
    east: number;
    north: number;
};

const props = defineProps<{
    properties: MapProperty[];
    initialBounds: Bounds | null;
}>();
const emit = defineEmits<{ search: [bounds: Bounds] }>();

const mapContainer = ref<HTMLElement | null>(null);
const pendingBounds = ref<Bounds | null>(null);
const showSearchButton = ref(false);
const mapError = ref(false);
const locale = computed(() => document.documentElement.lang || 'es');
const mapboxAccessToken = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN;
const mapboxStyle = import.meta.env.VITE_MAPBOX_STYLE ?? 'mapbox/streets-v12';
let map: LeafletMap | null = null;
let resultLayers: LayerGroup | null = null;
let isReady = false;

const formatPrice = (property: MapProperty): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        currency: property.currency,
        maximumFractionDigits: 0,
        notation: 'compact',
        style: 'currency',
    }).format(property.priceAmount);

const formatPreviewPrice = (property: MapProperty): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        currency: property.currency,
        maximumFractionDigits: 0,
        style: 'currency',
    }).format(property.priceAmount);

const humanize = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const createPropertyPreview = (property: MapProperty): HTMLElement => {
    const preview = document.createElement('article');
    preview.className = 'honducasa-map-preview';

    if (property.primaryImage) {
        const image = document.createElement('img');
        image.className = 'honducasa-map-preview__image';
        image.src = property.primaryImage.url;
        image.alt =
            property.primaryImage.altText ??
            property.name ??
            (locale.value === 'es' ? 'Propiedad' : 'Property');
        preview.append(image);
    }

    const content = document.createElement('div');
    content.className = 'honducasa-map-preview__content';

    const eyebrow = document.createElement('p');
    eyebrow.className = 'honducasa-map-preview__eyebrow';
    eyebrow.textContent = `${humanize(property.type)} · ${
        property.listingType === 'rent'
            ? locale.value === 'es'
                ? 'Alquiler'
                : 'For rent'
            : locale.value === 'es'
              ? 'Venta'
              : 'For sale'
    }`;

    const title = document.createElement('h3');
    title.className = 'honducasa-map-preview__title';
    title.textContent =
        property.name ??
        (locale.value === 'es'
            ? 'Propiedad en Honduras'
            : 'Property in Honduras');

    const location = document.createElement('p');
    location.className = 'honducasa-map-preview__location';
    location.textContent = `${property.location}, Honduras`;

    const features = document.createElement('p');
    features.className = 'honducasa-map-preview__features';
    features.textContent = [
        `${property.bedrooms} ${locale.value === 'es' ? 'hab.' : 'beds'}`,
        `${property.bathrooms} ${locale.value === 'es' ? 'baños' : 'baths'}`,
        property.interiorAreaM2 ? `${property.interiorAreaM2} m²` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    const footer = document.createElement('div');
    footer.className = 'honducasa-map-preview__footer';

    const price = document.createElement('strong');
    price.className = 'honducasa-map-preview__price';
    price.textContent = `${formatPreviewPrice(property)}${
        property.listingType === 'rent'
            ? locale.value === 'es'
                ? '/mes'
                : '/mo'
            : ''
    }`;

    const viewButton = document.createElement('button');
    viewButton.className = 'honducasa-map-preview__button';
    viewButton.type = 'button';
    viewButton.ariaLabel =
        locale.value === 'es' ? 'Ver propiedad' : 'View property';
    viewButton.title = viewButton.ariaLabel;

    const searchIcon = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'svg',
    );
    searchIcon.setAttribute('aria-hidden', 'true');
    searchIcon.setAttribute('fill', 'none');
    searchIcon.setAttribute('stroke', 'currentColor');
    searchIcon.setAttribute('stroke-linecap', 'round');
    searchIcon.setAttribute('stroke-linejoin', 'round');
    searchIcon.setAttribute('stroke-width', '2');
    searchIcon.setAttribute('viewBox', '0 0 24 24');

    const searchCircle = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'circle',
    );
    searchCircle.setAttribute('cx', '11');
    searchCircle.setAttribute('cy', '11');
    searchCircle.setAttribute('r', '8');

    const searchHandle = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'path',
    );
    searchHandle.setAttribute('d', 'm21 21-4.3-4.3');
    searchIcon.append(searchCircle, searchHandle);
    viewButton.append(searchIcon);
    viewButton.addEventListener('click', () =>
        router.visit(propertyShow.url(property.slug)),
    );

    footer.append(price, viewButton);
    content.append(eyebrow, title, location, features, footer);
    preview.append(content);

    return preview;
};

const createMultiPropertyPreview = (properties: MapProperty[]): HTMLElement => {
    const wrapper = document.createElement('div');
    wrapper.className = 'honducasa-map-preview-list';

    const header = document.createElement('p');
    header.className = 'honducasa-map-preview-list__header';
    header.textContent =
        locale.value === 'es'
            ? `${properties.length} propiedades en esta ubicación`
            : `${properties.length} properties at this location`;
    wrapper.append(header);

    const items = document.createElement('div');
    items.className = 'honducasa-map-preview-list__items';

    for (const property of properties) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'honducasa-map-preview-list__item';
        item.addEventListener('click', () =>
            router.visit(propertyShow.url(property.slug)),
        );

        if (property.primaryImage) {
            const image = document.createElement('img');
            image.className = 'honducasa-map-preview-list__thumb';
            image.src = property.primaryImage.url;
            image.alt = '';
            item.append(image);
        }

        const info = document.createElement('div');
        info.className = 'honducasa-map-preview-list__info';

        const title = document.createElement('strong');
        title.className = 'honducasa-map-preview-list__title';
        title.textContent =
            property.name ??
            (locale.value === 'es'
                ? 'Propiedad en Honduras'
                : 'Property in Honduras');

        const meta = document.createElement('span');
        meta.className = 'honducasa-map-preview-list__meta';
        meta.textContent = [
            humanize(property.type),
            `${property.bedrooms} ${locale.value === 'es' ? 'hab.' : 'beds'}`,
            `${property.bathrooms} ${locale.value === 'es' ? 'baños' : 'baths'}`,
        ].join(' · ');

        const price = document.createElement('span');
        price.className = 'honducasa-map-preview-list__price';
        price.textContent = `${formatPreviewPrice(property)}${
            property.listingType === 'rent'
                ? locale.value === 'es'
                    ? '/mes'
                    : '/mo'
                : ''
        }`;

        info.append(title, meta, price);
        item.append(info);
        items.append(item);
    }

    wrapper.append(items);

    return wrapper;
};

const clusterSize = (zoom: number): number => {
    if (zoom <= 9) {
        return 0.5;
    }

    if (zoom <= 11) {
        return 0.15;
    }

    if (zoom <= 13) {
        return 0.04;
    }

    return 0;
};

const renderMarkers = (): void => {
    if (!map) {
        return;
    }

    resultLayers?.removeFrom(map);
    resultLayers = layerGroup().addTo(map);
    const size = clusterSize(map.getZoom());

    // Group properties sharing the exact same public coordinate first — no
    // amount of zooming can ever visually separate these (e.g. two
    // "exact"-precision listings whose privacy-rounded coordinates collide),
    // so they must never be split apart by the zoom-based clustering below.
    const points = new globalThis.Map<string, MapProperty[]>();

    for (const property of props.properties) {
        const key = `${property.mapLatitude}:${property.mapLongitude}`;
        points.set(key, [...(points.get(key) ?? []), property]);
    }

    const cells = new globalThis.Map<string, MapProperty[][]>();

    for (const [key, pointProperties] of points) {
        const [latitude, longitude] = key.split(':').map(Number);
        const cellKey =
            size === 0
                ? key
                : `${Math.round(latitude / size)}:${Math.round(longitude / size)}`;
        cells.set(cellKey, [...(cells.get(cellKey) ?? []), pointProperties]);
    }

    for (const cellPoints of cells.values()) {
        const properties = cellPoints.flat();
        const latitude =
            properties.reduce(
                (total, property) => total + property.mapLatitude,
                0,
            ) / properties.length;
        const longitude =
            properties.reduce(
                (total, property) => total + property.mapLongitude,
                0,
            ) / properties.length;

        if (properties.length === 1) {
            const property = properties[0];
            const propertyMarker = marker([latitude, longitude], {
                icon: divIcon({
                    className: 'honducasa-price-marker',
                    html: `<span>${formatPrice(property)}</span>`,
                    iconAnchor: [38, 16],
                    iconSize: [76, 32],
                }),
                keyboard: true,
                title: formatPrice(property),
            });
            propertyMarker.bindPopup(createPropertyPreview(property), {
                className: 'honducasa-property-popup',
                maxWidth: 320,
                minWidth: 280,
                offset: [0, -10],
            });
            propertyMarker.addTo(resultLayers);

            continue;
        }

        const clusterMarker = marker([latitude, longitude], {
            icon: divIcon({
                className: 'honducasa-cluster-marker',
                html: `<span>${properties.length}</span>`,
                iconAnchor: [22, 22],
                iconSize: [44, 44],
            }),
        });

        if (cellPoints.length === 1) {
            // Every property here shares one identical coordinate — zooming
            // in further would recreate the exact same unsplittable cluster,
            // so offer a list to choose from instead of flying closer.
            clusterMarker.bindPopup(createMultiPropertyPreview(properties), {
                className: 'honducasa-property-popup',
                maxWidth: 320,
                minWidth: 280,
                offset: [0, -10],
            });
        } else {
            clusterMarker.on('click', () =>
                map?.flyTo([latitude, longitude], map.getZoom() + 2),
            );
        }

        clusterMarker.addTo(resultLayers);
    }
};

const captureBounds = (): void => {
    if (!map || !isReady) {
        return;
    }

    const bounds = map.getBounds();
    pendingBounds.value = {
        west: Number(bounds.getWest().toFixed(5)),
        south: Number(bounds.getSouth().toFixed(5)),
        east: Number(bounds.getEast().toFixed(5)),
        north: Number(bounds.getNorth().toFixed(5)),
    };
    showSearchButton.value = true;
};

const searchBounds = (): void => {
    if (!pendingBounds.value) {
        return;
    }

    emit('search', pendingBounds.value);
    showSearchButton.value = false;
};

watch(
    () => props.properties,
    () => renderMarkers(),
    { deep: true },
);

onMounted(() => {
    if (!mapboxAccessToken) {
        mapError.value = true;

        return;
    }

    const mapInstance = createMap(mapContainer.value!, {
        center: [14.75, -86.6],
        maxBounds: [
            [12.9, -89.4],
            [16.6, -83.1],
        ],
        zoom: 7,
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
        .on('load', () => (mapError.value = false))
        .on('tileerror', () => (mapError.value = true))
        .addTo(mapInstance);
    renderMarkers();

    if (props.initialBounds) {
        mapInstance.fitBounds([
            [props.initialBounds.south, props.initialBounds.west],
            [props.initialBounds.north, props.initialBounds.east],
        ]);
    } else if (props.properties.length) {
        mapInstance.fitBounds(
            latLngBounds(
                props.properties.map((property) => [
                    property.mapLatitude,
                    property.mapLongitude,
                ]),
            ),
            { maxZoom: 13, padding: [40, 40] },
        );
    }

    window.setTimeout(() => (isReady = true), 500);
    mapInstance.on('dragend', captureBounds);
    mapInstance.on('zoomend', () => {
        renderMarkers();
        captureBounds();
    });
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div
        class="relative h-full min-h-[520px] overflow-hidden rounded-[1.75rem] border border-stone-200 bg-stone-100"
    >
        <div
            ref="mapContainer"
            class="absolute inset-0"
            aria-label="Property results map"
        />
        <button
            v-if="showSearchButton"
            type="button"
            class="absolute top-4 left-1/2 z-[500] -translate-x-1/2 rounded-full bg-[#123b6d] px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-white shadow-xl hover:bg-[#185a96]"
            @click="searchBounds"
        >
            {{ locale === 'es' ? 'Buscar en esta zona' : 'Search this area' }}
        </button>
        <div
            v-if="mapError"
            class="absolute inset-0 z-[600] grid place-items-center bg-stone-100/95 p-8 text-center text-sm text-stone-600"
        >
            {{
                locale === 'es'
                    ? 'No pudimos cargar el mapa.'
                    : 'We could not load the map.'
            }}
        </div>
    </div>
</template>

<style scoped>
:deep(.honducasa-price-marker),
:deep(.honducasa-cluster-marker) {
    background: transparent;
    border: 0;
}

:deep(.honducasa-price-marker span) {
    display: grid;
    min-width: 4.75rem;
    height: 2rem;
    place-items: center;
    border: 2px solid white;
    border-radius: 9999px;
    background: #123b6d;
    box-shadow: 0 5px 16px rgb(15 23 42 / 0.25);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
}

:deep(.honducasa-cluster-marker span) {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border: 3px solid white;
    border-radius: 9999px;
    background: #2563eb;
    box-shadow: 0 5px 16px rgb(15 23 42 / 0.25);
    color: white;
    font-weight: 800;
}

:deep(.honducasa-map-preview-list) {
    padding: 0.875rem;
}

:deep(.honducasa-map-preview-list__header) {
    margin: 0 0 0.625rem;
    color: #2563eb;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

:deep(.honducasa-map-preview-list__items) {
    display: flex;
    max-height: 18rem;
    flex-direction: column;
    gap: 0.25rem;
    overflow-y: auto;
}

:deep(.honducasa-map-preview-list__item) {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    border: 0;
    border-radius: 0.75rem;
    background: transparent;
    padding: 0.375rem;
    text-align: left;
    cursor: pointer;
}

:deep(.honducasa-map-preview-list__item:hover) {
    background: #f1f5f9;
}

:deep(.honducasa-map-preview-list__thumb) {
    width: 3.5rem;
    height: 3.5rem;
    flex: 0 0 3.5rem;
    border-radius: 0.625rem;
    object-fit: cover;
}

:deep(.honducasa-map-preview-list__info) {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.125rem;
}

:deep(.honducasa-map-preview-list__title) {
    overflow: hidden;
    color: #13233a;
    font-size: 0.875rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.honducasa-map-preview-list__meta) {
    overflow: hidden;
    color: #64748b;
    font-size: 0.6875rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.honducasa-map-preview-list__price) {
    color: #123b6d;
    font-size: 0.8125rem;
    font-weight: 700;
}

:deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 0;
    border-radius: 0.875rem;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.15);
}

:deep(.honducasa-property-popup .leaflet-popup-content-wrapper) {
    overflow: hidden;
    border: 0;
    border-radius: 1.25rem;
    box-shadow: 0 18px 50px rgb(15 23 42 / 0.28);
}

:deep(.honducasa-property-popup .leaflet-popup-content) {
    width: auto !important;
    margin: 0;
}

:deep(.honducasa-property-popup .leaflet-popup-close-button) {
    top: 0.625rem;
    right: 0.625rem;
    z-index: 1;
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.92);
    color: #123b6d;
    font-size: 1.25rem;
    box-shadow: 0 4px 12px rgb(15 23 42 / 0.18);
}

:deep(.honducasa-map-preview) {
    overflow: hidden;
    background: white;
    color: #13233a;
}

:deep(.honducasa-map-preview__image) {
    display: block;
    width: 100%;
    height: 9.5rem;
    object-fit: cover;
}

:deep(.honducasa-map-preview__content) {
    padding: 1rem;
}

:deep(.honducasa-map-preview__eyebrow) {
    margin: 0 0 0.25rem;
    color: #2563eb;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

:deep(.honducasa-map-preview__title) {
    margin: 0;
    overflow: hidden;
    font-size: 1rem;
    font-weight: 750;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.honducasa-map-preview__location),
:deep(.honducasa-map-preview__features) {
    margin: 0.35rem 0 0;
    color: #64748b;
    font-size: 0.75rem;
    line-height: 1.35;
}

:deep(.honducasa-map-preview__footer) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.875rem;
}

:deep(.honducasa-map-preview__price) {
    color: #123b6d;
    font-size: 1rem;
    white-space: nowrap;
}

:deep(.honducasa-map-preview__button) {
    display: grid;
    width: 3rem;
    height: 3rem;
    flex: 0 0 3rem;
    place-items: center;
    border: 0;
    border-radius: 9999px;
    background: #123b6d;
    padding: 0;
    color: white;
    cursor: pointer;
}

:deep(.honducasa-map-preview__button svg) {
    width: 1.25rem;
    height: 1.25rem;
}

:deep(.honducasa-map-preview__button:hover) {
    background: #185a96;
}
</style>
