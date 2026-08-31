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
import { createSwipeGesture } from '@/lib/swipeGesture';
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
    furnishing: string;
    priceAmount: number;
    currency: string;
    priceIsConverted: boolean;
    depositAmount: number | null;
    utilitiesIncluded: boolean;
    mapLatitude: number;
    mapLongitude: number;
    primaryImage: { url: string; altText: string | null } | null;
    images: Array<{ url: string; altText: string | null }>;
    isFavorited: boolean;
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
    returnTo?: string;
}>();
const emit = defineEmits<{
    favorite: [property: MapProperty];
    search: [bounds: Bounds];
}>();

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
let isFittingResults = false;

const resultViewportKey = computed(() =>
    JSON.stringify({
        bounds: props.initialBounds,
        points: props.properties.map((property) => [
            property.id,
            property.mapLatitude,
            property.mapLongitude,
        ]),
    }),
);

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

const propertyDetailsUrl = (property: MapProperty): string =>
    propertyShow.url(property.slug, {
        query: { return_to: props.returnTo },
    });

const createIcon = (paths: string[], viewBox = '0 0 24 24'): SVGSVGElement => {
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    icon.setAttribute('stroke-width', '2');
    icon.setAttribute('viewBox', viewBox);

    for (const pathData of paths) {
        const path = document.createElementNS(
            'http://www.w3.org/2000/svg',
            'path',
        );
        path.setAttribute('d', pathData);
        icon.append(path);
    }

    return icon;
};

const createPropertyPreview = (property: MapProperty): HTMLElement => {
    const preview = document.createElement('article');
    preview.className = 'honducasa-map-preview';

    const gallery = document.createElement('div');
    gallery.className = 'honducasa-map-preview__gallery';
    gallery.tabIndex = 0;
    gallery.setAttribute('role', 'link');
    gallery.setAttribute(
        'aria-label',
        locale.value === 'es' ? 'Ver propiedad' : 'View property',
    );
    gallery.addEventListener('click', () => {
        if (!gallerySwipe.shouldSuppressClick()) {
            router.visit(propertyDetailsUrl(property));
        }
    });
    gallery.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            router.visit(propertyDetailsUrl(property));
        }
    });

    const images = property.images.length
        ? property.images
        : property.primaryImage
          ? [property.primaryImage]
          : [];
    let activeImageIndex = 0;
    const image = document.createElement('img');
    image.className = 'honducasa-map-preview__image';

    const indicators = document.createElement('div');
    indicators.className = 'honducasa-map-preview__indicators';

    const renderGalleryImage = (): void => {
        const activeImage = images[activeImageIndex];

        if (activeImage) {
            image.src = activeImage.url;
            image.alt =
                activeImage.altText ??
                property.name ??
                (locale.value === 'es' ? 'Propiedad' : 'Property');
        }

        [...indicators.children].forEach((indicator, index) => {
            indicator.classList.toggle(
                'honducasa-map-preview__indicator--active',
                index === activeImageIndex,
            );
        });
    };

    const gallerySwipe = createSwipeGesture({
        onSwipeLeft: () => {
            activeImageIndex = (activeImageIndex + 1) % images.length;
            renderGalleryImage();
        },
        onSwipeRight: () => {
            activeImageIndex =
                (activeImageIndex - 1 + images.length) % images.length;
            renderGalleryImage();
        },
    });

    if (images.length) {
        gallery.append(image);
        image.draggable = false;
    } else {
        const placeholder = document.createElement('div');
        placeholder.className = 'honducasa-map-preview__placeholder';
        placeholder.textContent =
            locale.value === 'es' ? 'Sin foto' : 'No photo';
        gallery.append(placeholder);
    }

    if (images.length > 1) {
        gallery.addEventListener('pointerdown', gallerySwipe.onPointerDown);
        gallery.addEventListener('pointerup', gallerySwipe.onPointerUp);
        gallery.addEventListener('pointercancel', gallerySwipe.onPointerCancel);

        const previousButton = document.createElement('button');
        previousButton.type = 'button';
        previousButton.className =
            'honducasa-map-preview__gallery-button honducasa-map-preview__gallery-button--previous';
        previousButton.ariaLabel =
            locale.value === 'es' ? 'Foto anterior' : 'Previous photo';
        previousButton.append(createIcon(['m15 18-6-6 6-6']));
        previousButton.addEventListener('click', (event) => {
            event.stopPropagation();
            activeImageIndex =
                (activeImageIndex - 1 + images.length) % images.length;
            renderGalleryImage();
        });

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className =
            'honducasa-map-preview__gallery-button honducasa-map-preview__gallery-button--next';
        nextButton.ariaLabel =
            locale.value === 'es' ? 'Foto siguiente' : 'Next photo';
        nextButton.append(createIcon(['m9 18 6-6-6-6']));
        nextButton.addEventListener('click', (event) => {
            event.stopPropagation();
            activeImageIndex = (activeImageIndex + 1) % images.length;
            renderGalleryImage();
        });

        images.forEach(() => {
            const indicator = document.createElement('span');
            indicator.className = 'honducasa-map-preview__indicator';
            indicators.append(indicator);
        });
        gallery.append(previousButton, nextButton, indicators);
    }

    const favoriteButton = document.createElement('button');
    favoriteButton.type = 'button';
    favoriteButton.className = 'honducasa-map-preview__favorite';
    favoriteButton.classList.toggle(
        'honducasa-map-preview__favorite--active',
        property.isFavorited,
    );
    favoriteButton.ariaLabel = property.isFavorited
        ? locale.value === 'es'
            ? 'Quitar de favoritos'
            : 'Remove from favorites'
        : locale.value === 'es'
          ? 'Guardar propiedad'
          : 'Save property';
    favoriteButton.append(
        createIcon([
            'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78Z',
        ]),
    );
    favoriteButton.addEventListener('click', (event) => {
        event.stopPropagation();
        emit('favorite', property);
    });

    gallery.append(favoriteButton);
    renderGalleryImage();
    preview.append(gallery);

    const content = document.createElement('div');
    content.className = 'honducasa-map-preview__content';
    content.tabIndex = 0;
    content.setAttribute('role', 'link');

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

    const price = document.createElement('strong');
    price.className = 'honducasa-map-preview__price';
    price.textContent = `${formatPreviewPrice(property)}${
        property.listingType === 'rent'
            ? locale.value === 'es'
                ? '/mes'
                : '/mo'
            : ''
    }`;

    content.addEventListener('click', () =>
        router.visit(propertyDetailsUrl(property)),
    );
    content.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            router.visit(propertyDetailsUrl(property));
        }
    });
    content.append(price, features, title, location);
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
            router.visit(propertyDetailsUrl(property)),
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
                className:
                    'honducasa-property-popup honducasa-property-card-popup',
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
    if (!map || !isReady || isFittingResults) {
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

const fitResultBounds = (): void => {
    if (!map) {
        return;
    }

    isFittingResults = true;
    pendingBounds.value = null;
    showSearchButton.value = false;
    map.once('moveend', () => (isFittingResults = false));

    if (props.initialBounds) {
        map.fitBounds([
            [props.initialBounds.south, props.initialBounds.west],
            [props.initialBounds.north, props.initialBounds.east],
        ]);

        return;
    }

    if (props.properties.length) {
        map.fitBounds(
            latLngBounds(
                props.properties.map((property) => [
                    property.mapLatitude,
                    property.mapLongitude,
                ]),
            ),
            { maxZoom: 13, padding: [40, 40] },
        );

        return;
    }

    isFittingResults = false;
};

watch(
    () => props.properties,
    () => renderMarkers(),
    { deep: true },
);

watch(resultViewportKey, () => fitResultBounds());

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

    fitResultBounds();

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
        class="relative h-full min-h-[520px] overflow-hidden rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-hover)]"
    >
        <div
            ref="mapContainer"
            class="absolute inset-0"
            aria-label="Property results map"
        />
        <button
            v-if="showSearchButton"
            type="button"
            class="absolute top-4 left-1/2 z-[500] -translate-x-1/2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-primary-foreground shadow-xl hover:bg-primary-hover"
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
    border: 1px solid #dddddd;
    border-radius: 9999px;
    background: white;
    color: #2563eb;
    box-shadow: 0 2px 8px rgb(0 0 0 / 0.18);
    font-size: 0.75rem;
    font-weight: 600;
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
    color: #2563eb;
    font-size: 0.8125rem;
    font-weight: 700;
}

:deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 0;
    border-radius: 0.75rem;
    box-shadow: 0 2px 10px rgb(0 0 0 / 0.16);
}

:deep(.honducasa-property-popup .leaflet-popup-content-wrapper) {
    overflow: hidden;
    border: 0;
    border-radius: 0.625rem;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.24);
}

:deep(.honducasa-property-popup .leaflet-popup-content) {
    width: auto !important;
    margin: 0;
}

:deep(.honducasa-property-card-popup .leaflet-popup-close-button) {
    display: none;
}

:deep(.honducasa-map-preview) {
    width: 18rem;
    overflow: hidden;
    background: white;
    color: #111827;
}

:deep(.honducasa-map-preview__gallery) {
    position: relative;
    height: 10.5rem;
    overflow: hidden;
    background: #e2e8f0;
    cursor: pointer;
    touch-action: pan-y;
    user-select: none;
}

:deep(.honducasa-map-preview__gallery:active) {
    cursor: grabbing;
}

:deep(.honducasa-map-preview__image),
:deep(.honducasa-map-preview__placeholder) {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

:deep(.honducasa-map-preview__placeholder) {
    display: grid;
    place-items: center;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
}

:deep(.honducasa-map-preview__favorite),
:deep(.honducasa-map-preview__gallery-button) {
    position: absolute;
    z-index: 2;
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    border: 0;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.94);
    color: #111827;
    box-shadow: 0 2px 8px rgb(15 23 42 / 0.2);
    cursor: pointer;
}

:deep(.honducasa-map-preview__favorite) {
    top: 0.625rem;
    right: 0.625rem;
}

:deep(.honducasa-map-preview__favorite--active) {
    color: #2563eb;
}

:deep(.honducasa-map-preview__favorite--active svg) {
    fill: currentColor;
}

:deep(.honducasa-map-preview__favorite svg) {
    width: 1.25rem;
    height: 1.25rem;
}

:deep(.honducasa-map-preview__gallery-button) {
    top: 50%;
    width: 1.875rem;
    height: 1.875rem;
    transform: translateY(-50%);
}

:deep(.honducasa-map-preview__gallery-button--previous) {
    left: 0.5rem;
}

:deep(.honducasa-map-preview__gallery-button--next) {
    right: 0.5rem;
}

:deep(.honducasa-map-preview__gallery-button svg) {
    width: 1rem;
    height: 1rem;
}

:deep(.honducasa-map-preview__indicators) {
    position: absolute;
    bottom: 0.5rem;
    left: 50%;
    z-index: 2;
    display: flex;
    gap: 0.3rem;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: rgb(15 23 42 / 0.58);
    padding: 0.3rem 0.4rem;
}

:deep(.honducasa-map-preview__indicator) {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.56);
}

:deep(.honducasa-map-preview__indicator--active) {
    background: white;
}

:deep(.honducasa-map-preview__content) {
    padding: 0.875rem 1rem 1rem;
    cursor: pointer;
}

:deep(.honducasa-map-preview__title) {
    margin: 0.375rem 0 0;
    overflow: hidden;
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.honducasa-map-preview__location),
:deep(.honducasa-map-preview__features) {
    margin: 0.375rem 0 0;
    color: #64748b;
    font-size: 0.8125rem;
    line-height: 1.3;
}

:deep(.honducasa-map-preview__price) {
    color: #111827;
    font-size: 1.25rem;
    line-height: 1.1;
    white-space: nowrap;
}
</style>
