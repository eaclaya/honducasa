<script setup lang="ts">
import {
    circle,
    control,
    divIcon,
    map as createMap,
    marker,
    polygon as leafletPolygon,
    tileLayer,
} from 'leaflet';
import type { Map as LeafletMap } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Coordinate = [number, number];
type PolygonGeometry = {
    type: 'Polygon';
    coordinates: Coordinate[][];
};

const props = defineProps<{
    latitude: number;
    longitude: number;
    precision: 'exact' | 'approximate';
    shape: 'radius' | 'polygon' | null;
    radiusMeters: number | null;
    polygon: PolygonGeometry | null;
}>();

const mapContainer = ref<HTMLElement | null>(null);
const mapError = ref(false);
const locale = computed(() => document.documentElement.lang || 'es');
const mapboxAccessToken = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN;
const mapboxStyle = import.meta.env.VITE_MAPBOX_STYLE ?? 'mapbox/streets-v12';
let map: LeafletMap | null = null;

const markerIcon = divIcon({
    className: 'honducasa-detail-marker',
    html: '<span aria-hidden="true"></span>',
    iconAnchor: [17, 40],
    iconSize: [34, 40],
});

onMounted(() => {
    if (!mapboxAccessToken) {
        mapError.value = true;

        return;
    }

    const center: Coordinate = [props.latitude, props.longitude];
    const mapInstance = createMap(mapContainer.value!, {
        center,
        dragging: true,
        scrollWheelZoom: false,
        zoom: props.precision === 'exact' ? 14 : 12,
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

    if (
        props.precision === 'approximate' &&
        props.shape === 'polygon' &&
        props.polygon?.coordinates[0]?.length
    ) {
        const area = leafletPolygon(
            props.polygon.coordinates[0].map(([longitude, latitude]) => [
                latitude,
                longitude,
            ]),
            {
                color: '#1D4ED8',
                fillColor: '#2563EB',
                fillOpacity: 0.18,
                weight: 2,
            },
        ).addTo(mapInstance);
        mapInstance.fitBounds(area.getBounds(), { padding: [35, 35] });

        return;
    }

    if (props.precision === 'approximate') {
        const area = circle(center, {
            color: '#1D4ED8',
            fillColor: '#2563EB',
            fillOpacity: 0.18,
            radius: props.radiusMeters ?? 1_000,
            weight: 2,
        }).addTo(mapInstance);
        mapInstance.fitBounds(area.getBounds(), { padding: [35, 35] });

        return;
    }

    marker(center, { icon: markerIcon }).addTo(mapInstance);
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div
        class="relative h-80 overflow-hidden rounded-3xl border border-stone-200 bg-stone-100"
    >
        <div
            ref="mapContainer"
            class="absolute inset-0"
            :aria-label="
                locale === 'es'
                    ? 'Mapa de ubicación de la propiedad'
                    : 'Property location map'
            "
        />
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
:deep(.honducasa-detail-marker) {
    background: transparent;
    border: 0;
}

:deep(.honducasa-detail-marker span) {
    display: block;
    width: 2.125rem;
    height: 2.125rem;
    border: 5px solid white;
    border-radius: 9999px 9999px 9999px 0;
    background: #2563eb;
    box-shadow: 0 8px 22px rgb(15 23 42 / 0.3);
    transform: rotate(-45deg);
}

:deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 0;
    border-radius: 0.875rem;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.15);
}
</style>
