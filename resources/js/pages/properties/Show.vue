<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bath,
    BedDouble,
    Calendar,
    Car,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Heart,
    Images,
    MapPin,
    Maximize2,
    MessageCircle,
    Ruler,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PropertyDetailMap from '@/components/PropertyDetailMap.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { login } from '@/routes';
import { store as startConversation } from '@/routes/conversations';
import { store as favorite, destroy as unfavorite } from '@/routes/favorites';
import { show as messages } from '@/routes/messages';
import { show as propertyShow } from '@/routes/properties';
import { index as rentals } from '@/routes/rentals';

type PropertyDetails = {
    slug: string;
    name: string | null;
    type: string;
    listingType: 'rent' | 'buy';
    location: string;
    bedrooms: number;
    bathrooms: string;
    parkingSpaces: number;
    interiorAreaM2: number | null;
    lotAreaM2: number | null;
    yearBuilt: number | null;
    furnishing: string;
    description: string | null;
    priceAmount: number;
    currency: string;
    depositAmount: number | null;
    utilitiesIncluded: boolean;
    isFavorited: boolean;
    publisher: { teamName: string; agentName: string };
    messaging: {
        canMessage: boolean;
        existingConversationId: number | null;
    };
    images: Array<{ url: string; altText: string | null }>;
    map: {
        latitude: number;
        longitude: number;
        precision: 'exact' | 'approximate';
        shape: 'radius' | 'polygon' | null;
        radiusMeters: number | null;
        polygon: {
            type: 'Polygon';
            coordinates: Array<Array<[number, number]>>;
        } | null;
    };
};

type RelatedProperty = {
    slug: string;
    name: string | null;
    location: string;
    priceAmount: number;
    currency: string;
    listingType: 'rent' | 'buy';
    image: string | null;
    bedrooms: number;
    bathrooms: string;
    interiorAreaM2: number | null;
};

const props = defineProps<{
    property: PropertyDetails;
    related: RelatedProperty[];
}>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const humanize = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/^./, (character) => character.toUpperCase());
const money = (amount: number, currency: string): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
const galleryOpen = ref(false);
const selectedImageIndex = ref(0);
const selectedImage = computed(
    () => props.property.images[selectedImageIndex.value],
);
const openGallery = (index = 0): void => {
    selectedImageIndex.value = index;
    galleryOpen.value = true;
};
const previousImage = (): void => {
    selectedImageIndex.value =
        (selectedImageIndex.value - 1 + props.property.images.length) %
        props.property.images.length;
};
const nextImage = (): void => {
    selectedImageIndex.value =
        (selectedImageIndex.value + 1) % props.property.images.length;
};
const messageForm = useForm({ body: '' });
const sendInitialMessage = (): void => {
    messageForm.post(startConversation.url(props.property.slug));
};
const toggleFavorite = (): void => {
    if (!page.props.auth.user) {
        window.location.href = login.url();

        return;
    }

    if (props.property.isFavorited) {
        router.delete(unfavorite.url(props.property.slug), {
            preserveScroll: true,
        });
    } else {
        router.post(
            favorite.url(props.property.slug),
            {},
            { preserveScroll: true },
        );
    }
};
</script>

<template>
    <Head
        :title="
            property.name ?? tr('Propiedad en Honduras', 'Property in Honduras')
        "
    >
        <meta
            name="description"
            :content="`${property.name ?? 'HonduCasa'} · ${property.location}, Honduras`"
        />
    </Head>

    <div class="min-h-screen bg-[#f8f7f2] text-[#13233a]">
        <PublicHeader />

        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8">
            <Link
                :href="
                    rentals.url({
                        query: {
                            location: property.location,
                            listing_type: property.listingType,
                        },
                    })
                "
                class="inline-flex items-center gap-2 text-sm font-semibold text-blue-800"
                ><ArrowLeft class="size-4" />
                {{ tr('Volver a resultados', 'Back to results') }}</Link
            >

            <section
                class="relative mt-7 grid gap-3 overflow-hidden rounded-[2rem] lg:grid-cols-[1.6fr_1fr]"
            >
                <button
                    type="button"
                    class="min-h-72 cursor-zoom-in overflow-hidden bg-stone-200 text-left lg:min-h-[520px]"
                    @click="openGallery(0)"
                >
                    <img
                        v-if="property.images[0]"
                        :src="property.images[0].url"
                        :alt="property.images[0].altText ?? property.name ?? ''"
                        class="size-full object-cover transition duration-300 hover:scale-[1.02]"
                    />
                </button>
                <div class="hidden grid-rows-2 gap-3 lg:grid">
                    <button
                        v-for="(image, index) in property.images.slice(1, 3)"
                        :key="index"
                        type="button"
                        class="min-h-0 cursor-zoom-in overflow-hidden"
                        @click="openGallery(index + 1)"
                    >
                        <img
                            :src="image.url"
                            :alt="image.altText ?? ''"
                            class="size-full object-cover transition duration-300 hover:scale-[1.02]"
                        />
                    </button>
                    <div
                        v-if="property.images.length < 2"
                        class="bg-gradient-to-br from-blue-100 to-amber-100"
                    />
                </div>
                <button
                    v-if="property.images.length"
                    type="button"
                    class="absolute right-4 bottom-4 flex items-center gap-2 rounded-full bg-white px-4 py-2.5 text-sm font-semibold shadow-lg transition hover:bg-stone-50"
                    @click="openGallery(0)"
                >
                    <Images class="size-4" />
                    {{ tr('Ver todas las fotos', 'View all photos') }}
                    ({{ property.images.length }})
                </button>
            </section>

            <div class="mt-10 grid gap-10 lg:grid-cols-[1fr_360px]">
                <div>
                    <div
                        class="flex flex-wrap items-center gap-2 text-sm font-bold"
                    >
                        <span
                            class="rounded-full bg-blue-100 px-3 py-1.5 text-blue-800"
                            >{{
                                property.listingType === 'rent'
                                    ? tr('En alquiler', 'For rent')
                                    : tr('En venta', 'For sale')
                            }}</span
                        ><span class="rounded-full bg-stone-200 px-3 py-1.5">{{
                            humanize(property.type)
                        }}</span>
                    </div>
                    <h1
                        class="mt-5 text-3xl font-semibold tracking-tight sm:text-5xl"
                    >
                        {{
                            property.name ??
                            tr('Propiedad en Honduras', 'Property in Honduras')
                        }}
                    </h1>
                    <p class="mt-3 flex items-center gap-2 text-stone-600">
                        <MapPin class="size-5 text-blue-700" />
                        {{ property.location }}, Honduras
                    </p>
                    <button
                        type="button"
                        class="mt-5 inline-flex items-center gap-2 rounded-full border border-blue-800 px-4 py-2 font-semibold text-blue-800"
                        @click="toggleFavorite"
                    >
                        <Heart
                            class="size-5"
                            :class="property.isFavorited ? 'fill-current' : ''"
                        />{{
                            property.isFavorited
                                ? tr('Guardada', 'Saved')
                                : tr('Guardar', 'Save')
                        }}
                    </button>

                    <div
                        class="mt-8 grid grid-cols-2 gap-3 border-y border-stone-200 py-6 sm:grid-cols-4"
                    >
                        <div class="flex items-center gap-3">
                            <BedDouble class="size-5 text-blue-700" /><span
                                ><b>{{ property.bedrooms }}</b
                                ><small class="block text-stone-500">{{
                                    tr('habitaciones', 'bedrooms')
                                }}</small></span
                            >
                        </div>
                        <div class="flex items-center gap-3">
                            <Bath class="size-5 text-blue-700" /><span
                                ><b>{{ property.bathrooms }}</b
                                ><small class="block text-stone-500">{{
                                    tr('baños', 'bathrooms')
                                }}</small></span
                            >
                        </div>
                        <div class="flex items-center gap-3">
                            <Car class="size-5 text-blue-700" /><span
                                ><b>{{ property.parkingSpaces }}</b
                                ><small class="block text-stone-500">{{
                                    tr('estacionamientos', 'parking')
                                }}</small></span
                            >
                        </div>
                        <div class="flex items-center gap-3">
                            <Maximize2 class="size-5 text-blue-700" /><span
                                ><b>{{ property.interiorAreaM2 ?? '—' }} m²</b
                                ><small class="block text-stone-500">{{
                                    tr('interior', 'interior')
                                }}</small></span
                            >
                        </div>
                    </div>

                    <section v-if="property.description" class="py-8">
                        <h2 class="text-2xl font-semibold">
                            {{
                                tr(
                                    'Acerca de esta propiedad',
                                    'About this property',
                                )
                            }}
                        </h2>
                        <p
                            class="mt-4 leading-8 whitespace-pre-line text-stone-600"
                        >
                            {{ property.description }}
                        </p>
                    </section>

                    <section class="border-t border-stone-200 py-8">
                        <h2 class="text-2xl font-semibold">
                            {{ tr('Detalles', 'Details') }}
                        </h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <p class="flex items-center gap-3">
                                <CheckCircle2 class="size-5 text-blue-700" />
                                {{ humanize(property.furnishing) }}
                            </p>
                            <p
                                v-if="property.lotAreaM2"
                                class="flex items-center gap-3"
                            >
                                <Ruler class="size-5 text-blue-700" />
                                {{ property.lotAreaM2 }} m²
                                {{ tr('de terreno', 'lot') }}
                            </p>
                            <p
                                v-if="property.yearBuilt"
                                class="flex items-center gap-3"
                            >
                                <Calendar class="size-5 text-blue-700" />
                                {{ tr('Construida en', 'Built in') }}
                                {{ property.yearBuilt }}
                            </p>
                            <p
                                v-if="property.utilitiesIncluded"
                                class="flex items-center gap-3"
                            >
                                <CheckCircle2 class="size-5 text-blue-700" />
                                {{
                                    tr(
                                        'Servicios incluidos',
                                        'Utilities included',
                                    )
                                }}
                            </p>
                        </div>
                    </section>

                    <section class="border-t border-stone-200 py-8">
                        <h2 class="text-2xl font-semibold">
                            {{
                                property.map.precision === 'exact'
                                    ? tr('Ubicación', 'Location')
                                    : tr(
                                          'Ubicación aproximada',
                                          'Approximate location',
                                      )
                            }}
                        </h2>
                        <p class="mt-2 text-sm text-stone-500">
                            {{
                                tr(
                                    'Protegemos la dirección exacta hasta que contactes al anunciante.',
                                    'We protect the exact address until you contact the publisher.',
                                )
                            }}
                        </p>
                        <PropertyDetailMap
                            class="mt-5"
                            :latitude="property.map.latitude"
                            :longitude="property.map.longitude"
                            :precision="property.map.precision"
                            :shape="property.map.shape"
                            :radius-meters="property.map.radiusMeters"
                            :polygon="property.map.polygon"
                        />
                    </section>
                </div>

                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <div
                        class="rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm"
                    >
                        <p class="text-3xl font-semibold text-blue-800">
                            {{ money(property.priceAmount, property.currency)
                            }}<span
                                v-if="property.listingType === 'rent'"
                                class="text-sm font-normal text-stone-500"
                                >/{{ tr('mes', 'mo') }}</span
                            >
                        </p>
                        <p
                            v-if="property.depositAmount"
                            class="mt-2 text-sm text-stone-500"
                        >
                            {{ tr('Depósito', 'Deposit') }}:
                            {{
                                money(property.depositAmount, property.currency)
                            }}
                        </p>
                        <div class="mt-6 border-t border-stone-100 pt-6">
                            <p
                                class="text-xs font-bold tracking-wider text-stone-500 uppercase"
                            >
                                {{ tr('Publicado por', 'Listed by') }}
                            </p>
                            <p class="mt-2 text-lg font-semibold">
                                {{ property.publisher.teamName }}
                            </p>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ property.publisher.agentName }}
                            </p>
                            <p
                                class="mt-1 flex items-center gap-1.5 text-sm text-blue-700"
                            >
                                <ShieldCheck class="size-4" />
                                {{
                                    tr(
                                        'Anunciante de HonduCasa',
                                        'HonduCasa publisher',
                                    )
                                }}
                            </p>
                        </div>
                        <Link
                            v-if="property.messaging.existingConversationId"
                            :href="
                                messages(
                                    property.messaging.existingConversationId,
                                ).url
                            "
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#123b6d] px-5 py-4 font-semibold text-white"
                            ><MessageCircle class="size-5" />{{
                                tr('Abrir conversación', 'Open conversation')
                            }}</Link
                        >
                        <form
                            v-else-if="property.messaging.canMessage"
                            class="mt-6"
                            @submit.prevent="sendInitialMessage"
                        >
                            <label
                                for="initial-message"
                                class="text-sm font-semibold"
                                >{{
                                    tr(
                                        'Mensaje al anunciante',
                                        'Message the publisher',
                                    )
                                }}</label
                            >
                            <textarea
                                id="initial-message"
                                v-model="messageForm.body"
                                rows="5"
                                class="mt-2 w-full resize-none rounded-2xl border border-stone-300 bg-stone-50 p-4 text-sm outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100"
                                :placeholder="
                                    tr(
                                        'Menciona qué te interesa y cualquier pregunta sobre la propiedad…',
                                        'Mention what interests you and any questions about the property…',
                                    )
                                "
                            />
                            <p
                                v-if="messageForm.errors.body"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ messageForm.errors.body }}
                            </p>
                            <p class="mt-2 text-xs text-stone-500">
                                {{
                                    tr(
                                        'Por seguridad, no compartas teléfonos, correos ni enlaces.',
                                        'For safety, do not share phone numbers, email addresses, or links.',
                                    )
                                }}
                            </p>
                            <button
                                type="submit"
                                :disabled="messageForm.processing"
                                class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#123b6d] px-5 py-4 font-semibold text-white disabled:opacity-60"
                            >
                                <MessageCircle class="size-5" />{{
                                    tr('Enviar mensaje', 'Send message')
                                }}
                            </button>
                        </form>
                        <Link
                            v-else-if="!page.props.auth.user"
                            :href="login.url()"
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#123b6d] px-5 py-4 font-semibold text-white"
                            ><MessageCircle class="size-5" />{{
                                tr(
                                    'Inicia sesión para escribir',
                                    'Log in to message',
                                )
                            }}</Link
                        >
                    </div>
                </aside>
            </div>

            <section
                v-if="related.length"
                class="border-t border-stone-200 py-14"
            >
                <h2 class="text-2xl font-semibold">
                    {{ tr('Propiedades similares', 'Similar properties') }}
                </h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in related"
                        :key="item.slug"
                        :href="propertyShow.url(item.slug)"
                        class="overflow-hidden rounded-3xl border border-stone-200 bg-white"
                        ><img
                            v-if="item.image"
                            :src="item.image"
                            :alt="item.name ?? ''"
                            class="aspect-[16/10] w-full object-cover"
                        />
                        <div class="p-5">
                            <h3 class="font-semibold">{{ item.name }}</h3>
                            <p class="mt-1 text-sm text-stone-500">
                                {{ item.location }}
                            </p>
                            <p
                                class="mt-3 flex flex-wrap gap-3 text-xs text-stone-500"
                            >
                                <span
                                    >{{ item.bedrooms }}
                                    {{ tr('hab.', 'beds') }}</span
                                >
                                <span
                                    >{{ item.bathrooms }}
                                    {{ tr('baños', 'baths') }}</span
                                >
                                <span v-if="item.interiorAreaM2"
                                    >{{ item.interiorAreaM2 }} m²</span
                                >
                            </p>
                            <p class="mt-3 font-semibold text-blue-800">
                                {{ money(item.priceAmount, item.currency)
                                }}<span
                                    v-if="item.listingType === 'rent'"
                                    class="text-xs font-normal"
                                    >/{{ tr('mes', 'mo') }}</span
                                >
                            </p>
                        </div></Link
                    >
                </div>
            </section>
        </main>

        <Teleport to="body">
            <div
                v-if="galleryOpen && selectedImage"
                class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/95 p-4 sm:p-10"
                role="dialog"
                aria-modal="true"
                :aria-label="tr('Galería de fotos', 'Photo gallery')"
            >
                <button
                    type="button"
                    class="absolute top-5 right-5 grid size-11 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20"
                    :aria-label="tr('Cerrar galería', 'Close gallery')"
                    @click="galleryOpen = false"
                >
                    <X class="size-6" />
                </button>
                <button
                    v-if="property.images.length > 1"
                    type="button"
                    class="absolute left-4 grid size-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:left-8"
                    :aria-label="tr('Foto anterior', 'Previous photo')"
                    @click="previousImage"
                >
                    <ChevronLeft class="size-7" />
                </button>
                <img
                    :src="selectedImage.url"
                    :alt="selectedImage.altText ?? property.name ?? ''"
                    class="max-h-[82vh] max-w-[88vw] object-contain"
                />
                <button
                    v-if="property.images.length > 1"
                    type="button"
                    class="absolute right-4 grid size-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:right-8"
                    :aria-label="tr('Foto siguiente', 'Next photo')"
                    @click="nextImage"
                >
                    <ChevronRight class="size-7" />
                </button>
                <p class="absolute bottom-5 text-sm font-semibold text-white">
                    {{ selectedImageIndex + 1 }} / {{ property.images.length }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
