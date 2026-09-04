<script setup lang="ts">
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import type {} from '@geoman-io/leaflet-geoman-free';
import { RotateCcw, X } from '@lucide/vue';
import * as L from 'leaflet';
import type { LatLng, Map as LeafletMap, Polygon } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Dialog, DialogContent } from '@/components/ui/dialog';

/**
 * leaflet-geoman is a legacy global-script bundle: it expects `window.L` to
 * already exist and mutates it directly (`L.PM = ...`, `L.Map.addInitHook`),
 * rather than importing Leaflet itself. It must load after this assignment,
 * so it's a dynamic import inside onMounted rather than a static one here.
 */
(window as unknown as { L: typeof L }).L = L;

const props = defineProps<{ locale: string }>();
const emit = defineEmits<{
    close: [];
    search: [ring: Array<[number, number]>];
}>();

const tr = (es: string, en: string): string =>
    props.locale === 'es' ? es : en;

const mapContainer = ref<HTMLElement | null>(null);
const mapError = ref(false);
const hasShape = ref(false);
const mapboxAccessToken = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN;
const mapboxStyle = import.meta.env.VITE_MAPBOX_STYLE ?? 'mapbox/streets-v12';
let map: LeafletMap | null = null;
let drawnLayer: Polygon | null = null;

const startDrawing = (): void => {
    map?.pm.enableDraw('Polygon', {
        tooltips: false,
        templineStyle: { color: '#2563EB' },
        hintlineStyle: { color: '#2563EB', dashArray: [5, 5] },
        pathOptions: {
            color: '#1D4ED8',
            fillColor: '#2563EB',
            fillOpacity: 0.2,
            weight: 2,
        },
    });
};

const restart = (): void => {
    drawnLayer?.remove();
    drawnLayer = null;
    hasShape.value = false;
    startDrawing();
};

const confirmSearch = (): void => {
    if (!drawnLayer) {
        return;
    }

    const ring = (drawnLayer.getLatLngs()[0] as LatLng[]).map(
        ({ lat, lng }): [number, number] => [
            Number(lng.toFixed(6)),
            Number(lat.toFixed(6)),
        ],
    );
    ring.push(ring[0]);

    emit('search', ring);
};

onMounted(async () => {
    if (!mapboxAccessToken) {
        mapError.value = true;

        return;
    }

    // The dialog's content is teleported and transitions open, so the map
    // container may not be attached to the document yet on this exact tick.
    await nextTick();

    // Loaded dynamically, after `window.L` is assigned above (the plugin
    // mutates that global directly rather than importing Leaflet itself),
    // and before any map is constructed: it attaches `.pm` to maps via
    // `L.Map.addInitHook`, which only affects instances created afterward.
    await import('@geoman-io/leaflet-geoman-free');

    const mapInstance = L.map(mapContainer.value!, {
        center: [14.75, -86.6],
        maxBounds: [
            [12.9, -89.4],
            [16.6, -83.1],
        ],
        zoom: 7,
        zoomControl: false,
    });
    map = mapInstance;
    L.control.zoom({ position: 'topright' }).addTo(mapInstance);
    L.tileLayer(
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

    mapInstance.on('pm:create', ({ layer }) => {
        drawnLayer = layer as Polygon;
        mapInstance.pm.disableDraw('Polygon');
        hasShape.value = true;
    });

    startDrawing();
});

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <Dialog :open="true" @update:open="emit('close')">
        <DialogContent
            class="fixed inset-0 top-0 left-0 h-[100dvh] w-screen max-w-none translate-x-0 translate-y-0 gap-0 rounded-none border-0 p-0 sm:max-w-none"
            :show-close-button="false"
        >
            <div class="relative h-full w-full">
                <div
                    ref="mapContainer"
                    class="absolute inset-0"
                    aria-label="Draw search area map"
                />

                <div
                    class="absolute top-0 right-0 left-0 z-[1000] flex items-center justify-between gap-4 bg-white/95 px-5 py-4 shadow-sm"
                >
                    <h2 class="text-base font-semibold text-stone-900">
                        {{
                            tr(
                                'Dibuja el área de búsqueda',
                                'Draw your search area',
                            )
                        }}
                    </h2>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-full text-stone-500 transition hover:bg-stone-100 hover:text-stone-900"
                        :aria-label="tr('Cerrar', 'Close')"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div
                    v-if="!hasShape && !mapError"
                    class="absolute top-20 left-1/2 z-[1000] w-[90%] max-w-md -translate-x-1/2 rounded-full bg-stone-900/85 px-5 py-2.5 text-center text-sm text-white shadow-lg"
                >
                    {{
                        tr(
                            'Haz clic en el mapa para marcar los límites del área. Haz clic en el primer punto para cerrarla.',
                            'Click the map to mark the edges of the area. Click the first point again to close it.',
                        )
                    }}
                </div>

                <div
                    v-if="hasShape"
                    class="absolute bottom-6 left-1/2 z-[1000] flex -translate-x-1/2 items-center gap-3"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-stone-700 shadow-xl hover:bg-stone-50"
                        @click="restart"
                    >
                        <RotateCcw class="size-4" />
                        {{ tr('Reiniciar', 'Restart') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-primary-foreground shadow-xl hover:bg-primary-hover"
                        @click="confirmSearch"
                    >
                        {{ tr('Buscar en esta área', 'Search this area') }}
                    </button>
                </div>

                <div
                    v-if="mapError"
                    class="absolute inset-0 z-[1000] grid place-items-center bg-stone-100/95 p-8 text-center text-sm text-stone-600"
                >
                    {{
                        tr(
                            'No pudimos cargar el mapa.',
                            'We could not load the map.',
                        )
                    }}
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
