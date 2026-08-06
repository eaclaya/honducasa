<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bath,
    BedDouble,
    Building2,
    Car,
    House,
    MapPin,
    Maximize2,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import { ref } from 'vue';
import { home, login, register } from '@/routes';
import { index as rentals } from '@/routes/rentals';

type Rental = {
    id: number;
    slug: string;
    name: string | null;
    type: string;
    location: string;
    bedrooms: number;
    bathrooms: string;
    parkingSpaces: number;
    interiorAreaM2: number | null;
    furnishing: string;
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

const props = defineProps<{
    filters: { location: string; propertyType: string; radius: number };
    properties: PaginatedRentals;
}>();

const page = usePage();
const location = ref(props.filters.location);
const propertyType = ref(props.filters.propertyType);
const radius = ref(String(props.filters.radius));

const search = (): void => {
    router.get(
        rentals.url(),
        {
            location: location.value || undefined,
            property_type: propertyType.value || undefined,
            radius: radius.value,
        },
        { preserveState: true, replace: true },
    );
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
        'from-emerald-200 via-lime-100 to-amber-100',
        'from-sky-200 via-cyan-100 to-emerald-100',
        'from-orange-200 via-amber-100 to-stone-100',
        'from-violet-200 via-rose-100 to-orange-100',
    ][index % 4];
</script>

<template>
    <Head title="Rental search" />

    <div class="min-h-screen bg-[#f8f7f2] text-[#17231c]">
        <header class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8"
            >
                <Link :href="home.url()" class="flex items-center gap-2.5">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-[#153c2b] text-[#e7ff62]"
                        ><House class="size-5"
                    /></span>
                    <span class="text-xl font-semibold tracking-tight"
                        >HonduCasa</span
                    >
                </Link>
                <div
                    v-if="!page.props.auth.user"
                    class="flex items-center gap-2 text-sm font-semibold"
                >
                    <Link :href="login.url()" class="px-3 py-2">Log in</Link>
                    <Link
                        :href="register.url()"
                        class="rounded-full bg-[#153c2b] px-4 py-2.5 text-white"
                        >Create account</Link
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
            <Link
                :href="home.url()"
                class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800"
                ><ArrowLeft class="size-4" /> Back home</Link
            >

            <div class="mt-7">
                <p
                    class="text-sm font-bold tracking-[0.16em] text-emerald-700 uppercase"
                >
                    Rental search
                </p>
                <h1
                    class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    {{
                        filters.location
                            ? `Homes in ${filters.location}`
                            : 'Homes for rent in Honduras'
                    }}
                </h1>
                <p class="mt-3 text-stone-600">
                    <template v-if="properties.total">
                        Showing {{ properties.from }}–{{ properties.to }} of
                        {{ properties.total.toLocaleString() }} properties
                    </template>
                    <template v-else
                        >No properties match this search yet.</template
                    >
                </p>
            </div>

            <form
                class="mt-8 grid gap-3 rounded-3xl border border-stone-200 bg-white p-3 shadow-sm md:grid-cols-[1fr_190px_160px_auto]"
                @submit.prevent="search"
            >
                <label
                    class="flex items-center gap-3 rounded-2xl bg-stone-50 px-4 py-3"
                >
                    <MapPin class="size-5 text-emerald-700" />
                    <input
                        v-model="location"
                        class="w-full bg-transparent text-sm font-medium outline-none"
                        placeholder="City or neighborhood"
                    />
                </label>
                <label
                    class="flex items-center gap-3 rounded-2xl bg-stone-50 px-4 py-3"
                >
                    <Building2 class="size-5 text-emerald-700" />
                    <select
                        v-model="propertyType"
                        class="w-full bg-transparent text-sm font-medium outline-none"
                    >
                        <option value="">Any home</option>
                        <option value="apartment">Apartment</option>
                        <option value="house">House</option>
                        <option value="condominium">Condominium</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="studio">Studio</option>
                        <option value="room">Room</option>
                    </select>
                </label>
                <label
                    class="flex items-center gap-3 rounded-2xl bg-stone-50 px-4 py-3"
                >
                    <SlidersHorizontal class="size-5 text-emerald-700" />
                    <select
                        v-model="radius"
                        class="w-full bg-transparent text-sm font-medium outline-none"
                    >
                        <option value="5">Within 5 km</option>
                        <option value="10">Within 10 km</option>
                        <option value="25">Within 25 km</option>
                        <option value="50">Within 50 km</option>
                    </select>
                </label>
                <button
                    class="flex items-center justify-center gap-2 rounded-2xl bg-[#153c2b] px-6 py-3 font-semibold text-white transition hover:bg-[#20553d]"
                    type="submit"
                >
                    <Search class="size-5" /> Search
                </button>
            </form>

            <section
                v-if="properties.data.length"
                class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="(property, index) in properties.data"
                    :key="property.id"
                    class="overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
                >
                    <div
                        class="relative grid aspect-[16/10] place-items-center bg-gradient-to-br"
                        :class="cardTone(index)"
                    >
                        <div
                            class="grid size-24 place-items-center rounded-[2rem] bg-white/65 text-[#153c2b] shadow-sm backdrop-blur"
                        >
                            <House class="size-11" :stroke-width="1.5" />
                        </div>
                        <span
                            class="absolute top-4 left-4 rounded-full bg-white/85 px-3 py-1.5 text-xs font-bold backdrop-blur"
                            >{{ humanize(property.type) }}</span
                        >
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="line-clamp-1 text-lg font-semibold">
                                    {{ property.name ?? 'Rental property' }}
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
                            <span class="flex flex-col items-center gap-1"
                                ><BedDouble class="size-4 text-emerald-700" />{{
                                    property.bedrooms
                                }}
                                beds</span
                            >
                            <span class="flex flex-col items-center gap-1"
                                ><Bath class="size-4 text-emerald-700" />{{
                                    property.bathrooms
                                }}
                                baths</span
                            >
                            <span class="flex flex-col items-center gap-1"
                                ><Car class="size-4 text-emerald-700" />{{
                                    property.parkingSpaces
                                }}
                                parks</span
                            >
                            <span class="flex flex-col items-center gap-1"
                                ><Maximize2 class="size-4 text-emerald-700" />{{
                                    property.interiorAreaM2 ?? '—'
                                }}
                                m²</span
                            >
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-xs font-medium text-stone-500">{{
                                humanize(property.furnishing)
                            }}</span>
                            <span class="font-semibold text-emerald-800"
                                >Contact for price</span
                            >
                        </div>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="mt-8 grid min-h-[360px] place-items-center rounded-[2rem] border border-dashed border-stone-300 bg-white p-8 text-center"
            >
                <div class="max-w-md">
                    <span
                        class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-100 text-emerald-800"
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
                                ? 'border-[#153c2b] bg-[#153c2b] text-white'
                                : 'border-stone-200 bg-white hover:border-emerald-700'
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
