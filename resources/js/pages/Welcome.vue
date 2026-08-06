<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    CheckCircle2,
    Compass,
    Heart,
    House,
    MapPin,
    Menu,
    Search,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { dashboard, login, register } from '@/routes';
import { index as rentals } from '@/routes/rentals';

const page = usePage();
const location = ref('');
const propertyType = ref('');
const radius = ref('10');
const mobileMenuOpen = ref(false);

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const searchRentals = (): void => {
    router.get(rentals.url(), {
        location: location.value || undefined,
        property_type: propertyType.value || undefined,
        radius: radius.value,
    });
};

const exploreCity = (city: string): void => {
    router.get(rentals.url({ query: { location: city, radius: 10 } }));
};
</script>

<template>
    <Head title="Homes for rent in Honduras" />

    <div
        class="min-h-screen bg-[#f8f7f2] text-[#17231c] selection:bg-emerald-200"
    >
        <header
            class="absolute inset-x-0 top-0 z-30 border-b border-white/15 text-white"
        >
            <div
                class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8"
            >
                <Link
                    :href="rentals.url()"
                    class="flex items-center gap-2.5"
                    aria-label="HonduCasa home"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-[#e7ff62] text-[#163e2c]"
                    >
                        <House class="size-5" :stroke-width="2.4" />
                    </span>
                    <span class="text-xl font-semibold tracking-tight"
                        >HonduCasa</span
                    >
                </Link>

                <nav
                    class="hidden items-center gap-8 text-sm font-medium lg:flex"
                >
                    <Link
                        :href="rentals.url()"
                        class="transition hover:text-[#e7ff62]"
                        >Rentals</Link
                    >
                    <a
                        href="#popular-places"
                        class="transition hover:text-[#e7ff62]"
                        >Popular places</a
                    >
                    <a
                        href="#how-it-works"
                        class="transition hover:text-[#e7ff62]"
                        >How it works</a
                    >
                    <a href="#owners" class="transition hover:text-[#e7ff62]"
                        >List a property</a
                    >
                </nav>

                <div class="hidden items-center gap-3 sm:flex">
                    <Link
                        v-if="page.props.auth.user"
                        :href="dashboardUrl"
                        class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[#153c2b] transition hover:bg-[#e7ff62]"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login.url()"
                            class="px-3 py-2 text-sm font-medium transition hover:text-[#e7ff62]"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register.url()"
                            class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-[#153c2b] transition hover:bg-[#e7ff62]"
                        >
                            Create account
                        </Link>
                    </template>
                </div>

                <button
                    class="grid size-10 place-items-center rounded-xl border border-white/25 sm:hidden"
                    type="button"
                    aria-label="Toggle navigation"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <Menu class="size-5" />
                </button>
            </div>

            <div
                v-if="mobileMenuOpen"
                class="border-t border-white/15 bg-[#153c2b]/95 px-5 py-5 backdrop-blur sm:hidden"
            >
                <div class="flex flex-col gap-4 text-sm font-medium">
                    <Link :href="rentals.url()">Rentals</Link>
                    <a href="#popular-places">Popular places</a>
                    <Link v-if="!page.props.auth.user" :href="login.url()"
                        >Log in</Link
                    >
                    <Link v-if="!page.props.auth.user" :href="register.url()"
                        >Create account</Link
                    >
                </div>
            </div>
        </header>

        <main>
            <section
                class="relative isolate overflow-hidden bg-[#153c2b] pt-20 text-white"
            >
                <div class="absolute inset-0 -z-10 opacity-40">
                    <div
                        class="absolute -top-24 right-[-8rem] size-[34rem] rounded-full border-[100px] border-[#277353]"
                    />
                    <div
                        class="absolute bottom-[-17rem] left-[-9rem] size-[32rem] rounded-full border-[90px] border-[#1e593f]"
                    />
                </div>

                <div
                    class="mx-auto grid min-h-[690px] max-w-7xl items-center gap-14 px-5 py-20 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:py-24"
                >
                    <div class="max-w-3xl">
                        <div
                            class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm backdrop-blur"
                        >
                            <Sparkles class="size-4 text-[#e7ff62]" />
                            A simpler way to rent in Honduras
                        </div>
                        <h1
                            class="max-w-3xl text-5xl leading-[1.02] font-semibold tracking-[-0.045em] sm:text-6xl lg:text-7xl"
                        >
                            Find a place that feels like
                            <span class="text-[#e7ff62]">home.</span>
                        </h1>
                        <p
                            class="mt-6 max-w-xl text-lg leading-8 text-white/72"
                        >
                            Discover apartments, houses, and condos across
                            Honduras with local search, clear details, and
                            people you can trust.
                        </p>

                        <form
                            class="mt-10 rounded-3xl bg-white p-3 text-[#17231c] shadow-2xl shadow-black/20"
                            @submit.prevent="searchRentals"
                        >
                            <div
                                class="grid gap-2 md:grid-cols-[1.45fr_1fr_.75fr_auto]"
                            >
                                <label
                                    class="flex items-center gap-3 rounded-2xl px-4 py-3 focus-within:bg-stone-50"
                                >
                                    <MapPin
                                        class="size-5 shrink-0 text-emerald-700"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block text-xs font-semibold text-stone-500"
                                            >Where</span
                                        >
                                        <input
                                            v-model="location"
                                            class="w-full bg-transparent text-sm font-medium outline-none placeholder:text-stone-400"
                                            placeholder="City or neighborhood"
                                        />
                                    </span>
                                </label>
                                <label
                                    class="rounded-2xl border-t border-stone-100 px-4 py-3 md:border-t-0 md:border-l"
                                >
                                    <span
                                        class="block text-xs font-semibold text-stone-500"
                                        >Property type</span
                                    >
                                    <select
                                        v-model="propertyType"
                                        class="mt-0.5 w-full bg-transparent text-sm font-medium outline-none"
                                    >
                                        <option value="">Any home</option>
                                        <option value="apartment">
                                            Apartment
                                        </option>
                                        <option value="house">House</option>
                                        <option value="condominium">
                                            Condo
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="rounded-2xl border-t border-stone-100 px-4 py-3 md:border-t-0 md:border-l"
                                >
                                    <span
                                        class="block text-xs font-semibold text-stone-500"
                                        >Search radius</span
                                    >
                                    <select
                                        v-model="radius"
                                        class="mt-0.5 w-full bg-transparent text-sm font-medium outline-none"
                                    >
                                        <option value="5">5 km</option>
                                        <option value="10">10 km</option>
                                        <option value="25">25 km</option>
                                        <option value="50">50 km</option>
                                    </select>
                                </label>
                                <button
                                    class="flex items-center justify-center gap-2 rounded-2xl bg-[#e7ff62] px-6 py-4 text-sm font-bold text-[#153c2b] transition hover:bg-[#d8f24f]"
                                    type="submit"
                                >
                                    <Search class="size-5" /> Search
                                </button>
                            </div>
                        </form>

                        <div
                            class="mt-5 flex flex-wrap items-center gap-2 text-sm text-white/65"
                        >
                            <span>Try:</span>
                            <button
                                class="rounded-full border border-white/20 px-3 py-1.5 hover:border-white/50 hover:text-white"
                                @click="exploreCity('Tegucigalpa')"
                            >
                                Tegucigalpa
                            </button>
                            <button
                                class="rounded-full border border-white/20 px-3 py-1.5 hover:border-white/50 hover:text-white"
                                @click="exploreCity('San Pedro Sula')"
                            >
                                San Pedro Sula
                            </button>
                            <button
                                class="rounded-full border border-white/20 px-3 py-1.5 hover:border-white/50 hover:text-white"
                                @click="exploreCity('Roatán')"
                            >
                                Roatán
                            </button>
                        </div>
                    </div>

                    <div
                        class="relative hidden min-h-[500px] lg:block"
                        aria-hidden="true"
                    >
                        <div
                            class="absolute inset-x-10 top-0 rotate-3 rounded-[2.5rem] bg-[#f3efe4] p-4 text-[#17231c] shadow-2xl shadow-black/30"
                        >
                            <div
                                class="aspect-[4/3] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-[#98c5a6] via-[#d8e8c9] to-[#f2c389] p-6"
                            >
                                <div
                                    class="flex h-full items-end justify-center"
                                >
                                    <div
                                        class="relative h-[68%] w-[82%] rounded-t-[2rem] bg-[#fffaf0] shadow-xl"
                                    >
                                        <div
                                            class="absolute inset-x-7 top-7 grid grid-cols-3 gap-4"
                                        >
                                            <span
                                                v-for="index in 6"
                                                :key="index"
                                                class="h-14 rounded-lg bg-[#b8d5da] ring-8 ring-[#f8f3e8]"
                                            />
                                        </div>
                                        <div
                                            class="absolute bottom-0 left-1/2 h-20 w-16 -translate-x-1/2 rounded-t-full bg-[#d77947]"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between px-2 pt-4 pb-2"
                            >
                                <div>
                                    <p class="font-semibold">
                                        Your next home, nearby
                                    </p>
                                    <p class="mt-1 text-sm text-stone-500">
                                        Explore by location and distance
                                    </p>
                                </div>
                                <span
                                    class="grid size-11 place-items-center rounded-full bg-[#153c2b] text-white"
                                    ><Heart class="size-5"
                                /></span>
                            </div>
                        </div>
                        <div
                            class="absolute bottom-2 left-0 flex items-center gap-3 rounded-2xl bg-white p-4 text-[#17231c] shadow-xl"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-800"
                                ><Compass class="size-5"
                            /></span>
                            <div>
                                <p class="text-xs text-stone-500">
                                    Near me search
                                </p>
                                <p class="font-semibold">
                                    Powered by real distance
                                </p>
                            </div>
                        </div>
                    </div>
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
                            class="text-sm font-bold tracking-[0.16em] text-emerald-700 uppercase"
                        >
                            Explore Honduras
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            Start with a place you love
                        </h2>
                    </div>
                    <Link
                        :href="rentals.url()"
                        class="group flex items-center gap-2 font-semibold text-emerald-800"
                    >
                        View all rentals
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
                                note: 'Capital energy & hillside living',
                                tone: 'from-[#2f6c52] to-[#86a27a]',
                            },
                            {
                                name: 'San Pedro Sula',
                                note: 'Urban convenience & opportunity',
                                tone: 'from-[#91684b] to-[#d1aa72]',
                            },
                            {
                                name: 'Roatán',
                                note: 'Island life by the Caribbean',
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
                class="border-y border-stone-200 bg-white"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-14 px-5 py-24 sm:px-8 lg:grid-cols-[.8fr_1.2fr] lg:items-center"
                >
                    <div>
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-emerald-700 uppercase"
                        >
                            Built for local renting
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            Less guessing. More confidence.
                        </h2>
                        <p class="mt-5 leading-7 text-stone-600">
                            HonduCasa brings the details renters care about into
                            one calm, straightforward experience.
                        </p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <article
                            v-for="item in [
                                {
                                    icon: MapPin,
                                    title: 'Search nearby',
                                    text: 'Use your location or choose a radius around any area.',
                                },
                                {
                                    icon: ShieldCheck,
                                    title: 'Trust the details',
                                    text: 'Clear property information and accountable publishers.',
                                },
                                {
                                    icon: CheckCircle2,
                                    title: 'Connect directly',
                                    text: 'Save favorites and contact the right person with context.',
                                },
                            ]"
                            :key="item.title"
                            class="rounded-3xl border border-stone-200 bg-[#faf9f5] p-6"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-xl bg-emerald-100 text-emerald-800"
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
                    class="relative overflow-hidden rounded-[2.5rem] bg-[#e7ff62] px-7 py-12 sm:px-12 lg:flex lg:items-center lg:justify-between lg:px-16"
                >
                    <Building2
                        class="absolute -right-5 -bottom-10 size-52 text-[#153c2b]/10"
                        :stroke-width="1"
                    />
                    <div class="relative max-w-2xl">
                        <p
                            class="text-sm font-bold tracking-[0.16em] text-emerald-900 uppercase"
                        >
                            For owners & agents
                        </p>
                        <h2
                            class="mt-3 text-3xl font-semibold tracking-tight text-[#153c2b] sm:text-4xl"
                        >
                            Have a property to rent?
                        </h2>
                        <p class="mt-4 max-w-xl text-[#365441]">
                            Create your account now. Property publishing and
                            lead management are the next parts of the platform
                            we’re building.
                        </p>
                    </div>
                    <Link
                        :href="register.url()"
                        class="relative mt-8 inline-flex items-center gap-2 rounded-full bg-[#153c2b] px-6 py-3.5 font-semibold text-white transition hover:bg-[#20553d] lg:mt-0"
                    >
                        Get started <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>
        </main>

        <footer class="bg-[#102d21] text-white/70">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8"
            >
                <div class="flex items-center gap-2 font-semibold text-white">
                    <House class="size-4 text-[#e7ff62]" /> HonduCasa
                </div>
                <p>
                    Built for renters, owners, and communities across Honduras.
                </p>
                <p>© {{ new Date().getFullYear() }} HonduCasa</p>
            </div>
        </footer>
    </div>
</template>
