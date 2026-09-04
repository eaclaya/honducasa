<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    Heart,
    MapPin,
    MessageCircle,
    Search,
    ShieldCheck,
} from '@lucide/vue';
import { computed, defineAsyncComponent, ref } from 'vue';
import LocationTypeahead from '@/components/LocationTypeahead.vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { encodePolygon } from '@/lib/polygonSearch';
import { register } from '@/routes';
import { create as createListing } from '@/routes/listings';
import { create as createPersonalListing } from '@/routes/personal-listings';
import { index as rentals } from '@/routes/rentals';
import { index as savedSearchesIndex } from '@/routes/saved-searches';

// Leaflet + leaflet-geoman are a heavy pair (~150KB+77KB gzipped combined)
// nobody needs unless they actually open the drawer, so this loads on demand
// rather than bundling into every homepage visit.
const MapAreaDrawer = defineAsyncComponent(
    () => import('@/components/MapAreaDrawer.vue'),
);

type SavedSearch = {
    id: number;
    name: string;
    filters: Record<string, string | number | boolean | null>;
};

defineProps<{ savedSearches: SavedSearch[] }>();

const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const location = ref('');
const listingType = ref('rent');
const locating = ref(false);
const locationError = ref('');
const drawingArea = ref(false);
const audience = ref<'renters' | 'owners'>('renters');
const seoTitle = 'Casas y apartamentos en alquiler y venta en Honduras';
const seoDescription =
    'Encuentra casas, apartamentos y propiedades en alquiler o venta en Honduras. Explora opciones en Tegucigalpa, San Pedro Sula, Comayagua y más.';
const canonicalUrl = computed(() =>
    typeof window === 'undefined'
        ? page.url
        : new URL(page.url, window.location.origin).href,
);
const seoImageUrl = computed(() =>
    typeof window === 'undefined'
        ? '/images/honducasa-hero.jpg'
        : new URL('/images/honducasa-hero.jpg', window.location.origin).href,
);

const listPropertyUrl = computed(() => {
    if (page.props.currentTeam) {
        return createListing.url(page.props.currentTeam.slug);
    }

    return page.props.auth.user ? createPersonalListing().url : register.url();
});

const searchRentals = (): void => {
    router.get(rentals.url(), {
        location: location.value || undefined,
        listing_type: listingType.value,
    });
};

const searchSelectedLocation = (selectedLocation: string): void => {
    location.value = selectedLocation;
    searchRentals();
};

const searchNearby = (): void => {
    if (!navigator.geolocation) {
        locationError.value = tr(
            'Tu navegador no permite obtener tu ubicación.',
            'Your browser does not support location access.',
        );

        return;
    }

    locating.value = true;
    locationError.value = '';
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            locating.value = false;
            location.value = '';
            router.get(rentals.url(), {
                latitude: coords.latitude,
                longitude: coords.longitude,
                listing_type: listingType.value,
            });
        },
        () => {
            locating.value = false;
            locationError.value = tr(
                'No pudimos obtener tu ubicación. Revisa el permiso del navegador.',
                'We could not get your location. Check your browser permission.',
            );
        },
        { enableHighAccuracy: true, timeout: 10_000, maximumAge: 300_000 },
    );
};

const searchDrawnArea = (ring: Array<[number, number]>): void => {
    drawingArea.value = false;
    location.value = '';
    router.get(rentals.url(), {
        polygon: encodePolygon(ring),
        listing_type: listingType.value,
    });
};

const exploreCity = (city: string): void => {
    router.get(
        rentals.url({ query: { location: city, listing_type: 'rent' } }),
    );
};
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
        <meta property="og:image" :content="seoImageUrl" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="seoTitle" />
        <meta name="twitter:description" :content="seoDescription" />
        <meta name="twitter:image" :content="seoImageUrl" />
    </Head>

    <div
        class="public-site min-h-screen bg-[var(--public-surface)] text-[var(--public-text)] selection:bg-blue-200"
    >
        <PublicHeader overlay />

        <main>
            <section
                class="relative isolate min-h-[760px] overflow-hidden bg-primary bg-[url('/images/honducasa-hero.jpg')] bg-cover bg-center text-white lg:min-h-[100svh]"
            >
                <div class="absolute inset-0 -z-10 bg-black/20" />
                <div
                    class="absolute inset-0 -z-10 bg-gradient-to-b from-black/35 via-black/5 to-black/55"
                />

                <div
                    class="mx-auto flex min-h-[760px] max-w-7xl items-center px-5 pt-32 pb-14 sm:px-8 lg:min-h-[100svh] lg:pt-36 lg:pb-16"
                >
                    <div class="w-full text-center">
                        <h1
                            class="mx-auto max-w-5xl text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-white [text-shadow:0_3px_16px_rgb(0_0_0/0.65)] sm:text-6xl lg:text-[5.25rem]"
                        >
                            {{
                                tr(
                                    'Encuentra un lugar que se sienta como',
                                    'Find a place that feels like',
                                )
                            }}
                            <span class="text-blue-300">{{
                                tr(' hogar.', ' home.')
                            }}</span>
                        </h1>

                        <form
                            class="public-search-shell mx-auto mt-12 w-full max-w-6xl p-2 text-left text-[var(--public-text)]"
                            @submit.prevent="searchRentals"
                        >
                            <div
                                class="grid gap-2 md:grid-cols-[1.45fr_.75fr_auto]"
                            >
                                <LocationTypeahead
                                    v-model="location"
                                    :locale="locale"
                                    variant="hero"
                                    :placeholder="
                                        tr(
                                            'Ciudad o colonia',
                                            'City or neighborhood',
                                        )
                                    "
                                    show-near-me
                                    show-draw-area
                                    :locating="locating"
                                    :location-error="locationError"
                                    @nearby="searchNearby"
                                    @draw="drawingArea = true"
                                    @select="searchSelectedLocation"
                                />
                                <label
                                    class="border-t border-[var(--public-border)] px-6 py-3 md:border-t-0 md:border-l"
                                >
                                    <span
                                        class="block text-xs font-bold text-stone-600"
                                        >{{ tr('Quiero', 'I want to') }}</span
                                    >
                                    <select
                                        v-model="listingType"
                                        class="mt-0.5 w-full bg-transparent text-sm font-semibold text-[var(--public-text)] outline-none"
                                    >
                                        <option value="rent">
                                            {{ tr('Alquilar', 'Rent') }}
                                        </option>
                                        <option value="buy">
                                            {{ tr('Comprar', 'Buy') }}
                                        </option>
                                    </select>
                                </label>
                                <button
                                    class="flex items-center justify-center gap-2 rounded-[10px] bg-primary px-7 py-4 text-sm font-bold text-primary-foreground transition hover:bg-primary-hover"
                                    type="submit"
                                >
                                    <Search class="size-5" />
                                    {{ tr('Buscar', 'Search') }}
                                </button>
                            </div>
                        </form>

                        <div
                            class="mt-5 flex flex-wrap items-center justify-center gap-2 text-sm font-medium text-white [text-shadow:0_1px_5px_rgb(0_0_0/0.9)]"
                        >
                            <button
                                class="rounded-full border border-white/45 bg-black/25 px-3 py-1.5 text-white backdrop-blur-sm transition hover:border-white hover:bg-black/40"
                                @click="exploreCity('Tegucigalpa')"
                            >
                                Tegucigalpa
                            </button>
                            <button
                                class="rounded-full border border-white/45 bg-black/25 px-3 py-1.5 text-white backdrop-blur-sm transition hover:border-white hover:bg-black/40"
                                @click="exploreCity('San Pedro Sula')"
                            >
                                San Pedro Sula
                            </button>
                            <button
                                class="rounded-full border border-white/45 bg-black/25 px-3 py-1.5 text-white backdrop-blur-sm transition hover:border-white hover:bg-black/40"
                                @click="exploreCity('Comayagua')"
                            >
                                Comayagua
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="page.props.auth.user && savedSearches.length"
                class="mx-auto max-w-7xl px-5 pt-20 sm:px-8"
            >
                <div
                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-blue-700 uppercase"
                        >
                            {{ tr('Continúa buscando', 'Continue searching') }}
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            {{
                                tr(
                                    'Tus búsquedas guardadas',
                                    'Your saved searches',
                                )
                            }}
                        </h2>
                    </div>
                    <Link
                        :href="savedSearchesIndex.url()"
                        class="group flex items-center gap-2 font-semibold text-blue-800"
                    >
                        {{ tr('Ver todas', 'View all') }}
                        <ArrowRight
                            class="size-4 transition group-hover:translate-x-1"
                        />
                    </Link>
                </div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="savedSearch in savedSearches"
                        :key="savedSearch.id"
                        :href="
                            rentals.url({
                                query: {
                                    ...savedSearch.filters,
                                    saved_search: savedSearch.id,
                                },
                            })
                        "
                        class="group flex items-center gap-4 rounded-3xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] p-5 transition hover:border-primary hover:shadow-md"
                    >
                        <span
                            class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground"
                        >
                            <Search class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{
                                savedSearch.name
                            }}</span>
                            <span
                                class="mt-1 block truncate text-sm text-stone-500"
                            >
                                {{
                                    Object.values(savedSearch.filters)
                                        .filter(
                                            (value) =>
                                                value !== null && value !== '',
                                        )
                                        .join(' · ')
                                }}
                            </span>
                        </span>
                        <ArrowRight
                            class="size-5 shrink-0 transition group-hover:translate-x-1"
                        />
                    </Link>
                </div>
            </section>

            <section
                id="popular-places"
                class="mx-auto max-w-7xl px-5 py-24 sm:px-8"
            >
                <div
                    class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-blue-700 uppercase"
                        >
                            {{ tr('Explora Honduras', 'Explore Honduras') }}
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            {{
                                tr(
                                    'Comienza con un lugar que te encante',
                                    'Start with a place you love',
                                )
                            }}
                        </h2>
                    </div>
                    <Link
                        :href="rentals.url()"
                        class="group flex items-center gap-2 font-semibold text-blue-800"
                    >
                        {{
                            tr(
                                'Ver todas las propiedades',
                                'View all properties',
                            )
                        }}
                        <ArrowRight
                            class="size-4 transition group-hover:translate-x-1"
                        />
                    </Link>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <button
                        v-for="place in [
                            {
                                name: 'Tegucigalpa',
                                image: '/images/tegucigalpa-1.jpg',
                                note: tr(
                                    'Energía capitalina y vida entre colinas',
                                    'Capital energy & hillside living',
                                ),
                            },
                            {
                                name: 'San Pedro Sula',
                                image: '/images/sps-1.jpg',
                                note: tr(
                                    'Comodidad urbana y oportunidades',
                                    'Urban convenience & opportunity',
                                ),
                            },
                            {
                                name: 'Comayagua',
                                image: '/images/comayagua-1.jpg',
                                note: tr(
                                    'Historia colonial en el corazón de Honduras',
                                    'Colonial history in the heart of Honduras',
                                ),
                            },
                        ]"
                        :key="place.name"
                        class="group relative min-h-80 overflow-hidden rounded-[2rem] border border-white/10 bg-stone-900 text-left text-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl md:aspect-[1.05] md:min-h-0"
                        @click="exploreCity(place.name)"
                    >
                        <img
                            :src="place.image"
                            :alt="place.name"
                            class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-black/10"
                        />
                        <span
                            class="absolute top-7 left-7 grid size-14 place-items-center rounded-full border border-white/25 bg-black/20 shadow-lg backdrop-blur-md"
                        >
                            <MapPin class="size-6" />
                        </span>
                        <div class="absolute right-7 bottom-7 left-7">
                            <h3 class="text-2xl font-semibold">
                                {{ place.name }}
                            </h3>
                            <p class="mt-2 text-sm text-white/75">
                                {{ place.note }}
                            </p>
                        </div>
                    </button>
                </div>
            </section>

            <section
                id="how-it-works"
                class="mx-auto max-w-[1440px] px-5 pt-10 sm:px-8"
            >
                <div
                    class="rounded-[2rem] bg-[var(--public-surface)] px-5 py-12 sm:px-10 lg:px-14"
                >
                    <div class="mx-auto max-w-3xl text-center">
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-blue-700 uppercase"
                        >
                            {{
                                tr(
                                    'Cómo funciona Honducasa',
                                    'How Honducasa works',
                                )
                            }}
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl"
                        >
                            {{
                                tr(
                                    'Más valor para inquilinos y propietarios',
                                    'More value for renters and owners',
                                )
                            }}
                        </h2>
                        <p
                            class="mx-auto mt-4 max-w-2xl leading-7 text-stone-600"
                        >
                            {{
                                tr(
                                    'Una plataforma diseñada para hacer que alquilar, comprar y publicar propiedades sea simple, seguro y eficiente.',
                                    'A platform designed to make renting, buying, and listing properties simple, safe, and efficient.',
                                )
                            }}
                        </p>

                        <div
                            class="mx-auto mt-7 grid max-w-md grid-cols-2 rounded-full border border-[var(--public-border)] bg-[var(--public-surface-raised)] p-1"
                        >
                            <button
                                type="button"
                                class="rounded-full px-5 py-3 text-sm font-semibold transition"
                                :class="
                                    audience === 'renters'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-[var(--public-muted)] hover:text-[var(--public-text)]'
                                "
                                @click="audience = 'renters'"
                            >
                                {{ tr('Para inquilinos', 'For renters') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-5 py-3 text-sm font-semibold transition"
                                :class="
                                    audience === 'owners'
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-[var(--public-muted)] hover:text-[var(--public-text)]'
                                "
                                @click="audience = 'owners'"
                            >
                                {{ tr('Para propietarios', 'For owners') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-9 grid gap-5 lg:grid-cols-3">
                        <article
                            v-for="(item, index) in audience === 'renters'
                                ? [
                                      {
                                          icon: Search,
                                          title: tr(
                                              'Encuentra tu lugar ideal',
                                              'Find your ideal place',
                                          ),
                                          text: tr(
                                              'Explora propiedades filtrando por ubicación, precio, tipo y más.',
                                              'Explore properties by location, price, type, and more.',
                                          ),
                                      },
                                      {
                                          icon: Heart,
                                          title: tr(
                                              'Compara y guarda',
                                              'Compare and save',
                                          ),
                                          text: tr(
                                              'Guarda tus favoritas, compara detalles y toma decisiones con confianza.',
                                              'Save favorites, compare details, and decide with confidence.',
                                          ),
                                      },
                                      {
                                          icon: MessageCircle,
                                          title: tr(
                                              'Contacta directamente',
                                              'Contact directly',
                                          ),
                                          text: tr(
                                              'Habla directo con propietarios o agentes verificados, sin intermediarios.',
                                              'Talk directly with verified owners or agents, without intermediaries.',
                                          ),
                                      },
                                  ]
                                : [
                                      {
                                          icon: Building2,
                                          title: tr(
                                              'Publica fácilmente',
                                              'List with ease',
                                          ),
                                          text: tr(
                                              'Crea un anuncio completo con fotos, precio y ubicación.',
                                              'Create a complete listing with photos, price, and location.',
                                          ),
                                      },
                                      {
                                          icon: CheckCircle2,
                                          title: tr(
                                              'Administra tus anuncios',
                                              'Manage your listings',
                                          ),
                                          text: tr(
                                              'Guarda borradores y controla cuándo aparece cada propiedad.',
                                              'Save drafts and control when each property appears.',
                                          ),
                                      },
                                      {
                                          icon: MessageCircle,
                                          title: tr(
                                              'Conecta con interesados',
                                              'Connect with prospects',
                                          ),
                                          text: tr(
                                              'Recibe consultas y conversa con posibles inquilinos o compradores.',
                                              'Receive inquiries and chat with potential renters or buyers.',
                                          ),
                                      },
                                  ]"
                            :key="item.title"
                            class="relative rounded-3xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] p-6 shadow-sm sm:p-7"
                        >
                            <span
                                class="grid size-9 place-items-center rounded-xl bg-primary text-sm font-bold text-primary-foreground"
                                >0{{ index + 1 }}</span
                            >
                            <div class="mt-5 flex items-start gap-5">
                                <span
                                    class="grid size-16 shrink-0 place-items-center rounded-2xl bg-blue-100 text-blue-800"
                                    ><component :is="item.icon" class="size-8"
                                /></span>
                                <div>
                                    <h3 class="font-semibold">
                                        {{ item.title }}
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        {{ item.text }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        class="mt-6 flex items-start gap-4 rounded-2xl border border-[var(--public-border)] bg-[var(--public-surface-hover)] p-5"
                    >
                        <span
                            class="grid size-12 shrink-0 place-items-center rounded-xl bg-blue-100 text-blue-800"
                        >
                            <ShieldCheck class="size-6" />
                        </span>
                        <div>
                            <h3 class="font-semibold">
                                {{
                                    tr(
                                        'Confianza en cada paso',
                                        'Confidence at every step',
                                    )
                                }}
                            </h3>
                            <p class="mt-1 text-sm text-stone-600">
                                {{
                                    tr(
                                        'Perfiles verificados, información clara y contacto directo para que tomes decisiones seguras.',
                                        'Verified profiles, clear information, and direct contact so you can make confident decisions.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="owners"
                class="mx-auto max-w-[1360px] px-5 pt-6 pb-24 sm:px-8"
            >
                <div
                    class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-blue-700 via-blue-600 to-blue-700 px-7 py-10 text-white shadow-lg sm:px-12 lg:grid lg:min-h-[360px] lg:grid-cols-[.9fr_1.1fr] lg:items-center lg:px-14"
                >
                    <Building2
                        class="absolute -right-6 -bottom-12 size-52 text-white/10"
                        :stroke-width="1"
                    />
                    <div
                        class="absolute top-8 right-8 h-28 w-36 [background-image:radial-gradient(circle,rgba(255,255,255,.35)_1.5px,transparent_1.5px)] [background-size:16px_16px] opacity-50"
                    />
                    <div class="relative z-10 max-w-xl">
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-blue-100 uppercase"
                        >
                            {{
                                tr(
                                    'Para propietarios y agentes',
                                    'For owners & agents',
                                )
                            }}
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-4xl"
                        >
                            {{
                                tr(
                                    'Publica tu propiedad y llega a más personas',
                                    'List your property and reach more people',
                                )
                            }}
                        </h2>
                        <p class="mt-4 max-w-xl text-blue-100">
                            {{
                                tr(
                                    'Publica gratis, administra tus anuncios y conecta con inquilinos o compradores interesados.',
                                    'List for free, manage your properties, and connect with interested renters or buyers.',
                                )
                            }}
                        </p>
                        <Link
                            :href="listPropertyUrl"
                            class="mt-7 inline-flex items-center gap-3 rounded-xl bg-white px-6 py-3.5 font-semibold text-blue-700 transition hover:bg-blue-50"
                        >
                            {{
                                tr('Publicar mi propiedad', 'List my property')
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                        <p class="mt-5 flex items-center gap-2 text-sm">
                            <CheckCircle2 class="size-4" />
                            {{
                                tr(
                                    'Publicación fácil, rápida y gratuita',
                                    'Easy, fast, and free listing',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="relative mt-10 hidden min-h-64 lg:mt-0 lg:block"
                    >
                        <img
                            src="/images/sps-1.jpg"
                            :alt="
                                tr(
                                    'Vista de San Pedro Sula',
                                    'San Pedro Sula view',
                                )
                            "
                            class="absolute top-0 right-8 h-64 w-[75%] rounded-2xl border border-white/20 object-cover shadow-2xl"
                        />
                        <img
                            src="/images/tegucigalpa-1.jpg"
                            :alt="
                                tr('Vista de Tegucigalpa', 'Tegucigalpa view')
                            "
                            class="absolute -bottom-4 left-4 h-28 w-48 rounded-xl border-2 border-white object-cover shadow-xl"
                        />
                        <img
                            src="/images/comayagua-1.jpg"
                            :alt="tr('Vista de Comayagua', 'Comayagua view')"
                            class="absolute top-10 right-0 h-36 w-52 rounded-xl border-2 border-white object-cover shadow-xl"
                        />
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter />

        <MapAreaDrawer
            v-if="drawingArea"
            :locale="locale"
            @close="drawingArea = false"
            @search="searchDrawnArea"
        />
    </div>
</template>
