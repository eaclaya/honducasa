<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    MapPin,
    Search,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocationTypeahead from '@/components/LocationTypeahead.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { register } from '@/routes';
import { create as createListing } from '@/routes/listings';
import { create as createPersonalListing } from '@/routes/personal-listings';
import { index as rentals } from '@/routes/rentals';
import { index as savedSearchesIndex } from '@/routes/saved-searches';

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

const exploreCity = (city: string): void => {
    router.get(
        rentals.url({ query: { location: city, listing_type: 'rent' } }),
    );
};
</script>

<template>
    <Head title="Homes for rent and sale in Honduras" />

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
                                    :locating="locating"
                                    :location-error="locationError"
                                    @nearby="searchNearby"
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
                            <span>{{ tr('Prueba:', 'Try:') }}</span>
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
                                @click="exploreCity('Roatán')"
                            >
                                Roatán
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
                                note: tr(
                                    'Energía capitalina y vida entre colinas',
                                    'Capital energy & hillside living',
                                ),
                                tone: 'from-[#2f6c52] to-[#86a27a]',
                            },
                            {
                                name: 'San Pedro Sula',
                                note: tr(
                                    'Comodidad urbana y oportunidades',
                                    'Urban convenience & opportunity',
                                ),
                                tone: 'from-[#91684b] to-[#d1aa72]',
                            },
                            {
                                name: 'Roatán',
                                note: tr(
                                    'Vida isleña junto al Caribe',
                                    'Island life by the Caribbean',
                                ),
                                tone: 'from-[#227b78] to-[#6fc5b3]',
                            },
                        ]"
                        :key="place.name"
                        class="group relative min-h-72 overflow-hidden rounded-[2rem] bg-gradient-to-br p-7 text-left text-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl"
                        :class="place.tone"
                        @click="exploreCity(place.name)"
                    >
                        <div
                            class="absolute -right-12 -bottom-16 size-52 rounded-full border-[42px] border-white/10 transition group-hover:scale-110"
                        />
                        <MapPin class="size-7" />
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
                class="border-y border-[var(--public-border)] bg-[var(--public-surface-raised)]"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-14 px-5 py-24 sm:px-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center"
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-blue-700 uppercase"
                        >
                            {{
                                tr(
                                    'Creado para el mercado local',
                                    'Built for the local market',
                                )
                            }}
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            {{
                                tr(
                                    'Menos dudas. Más confianza.',
                                    'Less guessing. More confidence.',
                                )
                            }}
                        </h2>
                        <p class="mt-5 leading-7 text-stone-600">
                            {{
                                tr(
                                    'HonduCasa reúne la información que compradores e inquilinos necesitan en una experiencia clara y sencilla.',
                                    'HonduCasa brings the details buyers and renters need into one calm, straightforward experience.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <article
                            v-for="item in [
                                {
                                    icon: MapPin,
                                    title: tr(
                                        'Busca por ubicación',
                                        'Search by location',
                                    ),
                                    text: tr(
                                        'Elige dónde quieres vivir y si deseas alquilar o comprar.',
                                        'Choose where you want to live and whether you want to rent or buy.',
                                    ),
                                },
                                {
                                    icon: ShieldCheck,
                                    title: tr(
                                        'Confía en los detalles',
                                        'Trust the details',
                                    ),
                                    text: tr(
                                        'Información clara y anunciantes responsables.',
                                        'Clear property information and accountable publishers.',
                                    ),
                                },
                                {
                                    icon: CheckCircle2,
                                    title: tr(
                                        'Conecta directamente',
                                        'Connect directly',
                                    ),
                                    text: tr(
                                        'Guarda favoritos y contacta a la persona indicada.',
                                        'Save favorites and contact the right person with context.',
                                    ),
                                },
                            ]"
                            :key="item.title"
                            class="rounded-3xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] p-6"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-xl bg-blue-100 text-blue-800"
                                ><component :is="item.icon" class="size-5"
                            /></span>
                            <h3 class="mt-7 font-semibold">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ item.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="owners" class="mx-auto max-w-7xl px-5 py-24 sm:px-8">
                <div
                    class="relative overflow-hidden rounded-[2.5rem] bg-primary px-7 py-12 text-primary-foreground sm:px-12 lg:flex lg:items-center lg:justify-between lg:px-16"
                >
                    <Building2
                        class="absolute -right-5 -bottom-10 size-52 text-white/10"
                        :stroke-width="1"
                    />
                    <div class="relative max-w-2xl">
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
                            class="mt-3 text-3xl font-semibold tracking-tight text-primary-foreground sm:text-4xl"
                        >
                            {{
                                tr(
                                    '¿Tienes una propiedad para publicar?',
                                    'Have a property to list?',
                                )
                            }}
                        </h2>
                        <p class="mt-4 max-w-xl text-blue-100">
                            {{
                                tr(
                                    'Publica tu propiedad con fotos, precio y ubicación. Puedes guardarla como borrador antes de hacerla visible.',
                                    'Publish your property with photos, pricing, and location. Save it as a draft before making it visible.',
                                )
                            }}
                        </p>
                    </div>
                    <Link
                        :href="listPropertyUrl"
                        class="relative mt-8 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-primary transition hover:bg-slate-100 lg:mt-0"
                    >
                        {{ tr('Publicar propiedad', 'List a property') }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="bg-[#0a2748] text-white/70">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8"
            >
                <div class="flex items-center gap-2 font-semibold text-white">
                    <AppLogoIcon class="size-7" /> HonduCasa
                </div>
                <p>
                    Built for renters, owners, and communities across Honduras.
                </p>
                <p>© {{ new Date().getFullYear() }} HonduCasa</p>
            </div>
        </footer>
    </div>
</template>
