<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { dashboard, home, login, register } from '@/routes';
import {
    create as createListing,
    start as startListing,
} from '@/routes/listings';
import { index as rentals } from '@/routes/rentals';
import { dashboard as userDashboard } from '@/routes/user';

const props = withDefaults(
    defineProps<{
        overlay?: boolean;
        showExploreLinks?: boolean;
    }>(),
    {
        overlay: false,
        showExploreLinks: false,
    },
);

const page = usePage();
const mobileMenuOpen = ref(false);
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;

const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : userDashboard().url,
);
const listPropertyUrl = computed(() => {
    if (page.props.currentTeam) {
        return createListing.url(page.props.currentTeam.slug);
    }

    return page.props.auth.user ? startListing().url : register.url();
});

const headerClasses = computed(() =>
    props.overlay
        ? 'absolute inset-x-0 top-0 z-30 border-b border-white/25 bg-gradient-to-b from-black/40 to-black/5 text-white'
        : 'relative z-30 border-b border-slate-200 bg-white text-[#13233a]',
);

const hoverClasses = computed(() =>
    props.overlay ? 'hover:text-[#67d7ff]' : 'hover:text-blue-700',
);
</script>

<template>
    <header :class="headerClasses">
        <div
            class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-5 px-5 sm:px-8"
        >
            <Link
                :href="home.url()"
                class="flex shrink-0 items-center gap-2.5"
                aria-label="HonduCasa home"
            >
                <AppLogoIcon class="size-10" />
                <span class="text-xl font-semibold tracking-tight"
                    >HonduCasa</span
                >
            </Link>

            <nav
                v-if="showExploreLinks"
                class="hidden items-center gap-7 text-sm font-semibold lg:flex"
            >
                <Link :href="rentals.url()" :class="hoverClasses">{{
                    tr('Propiedades', 'Properties')
                }}</Link>
                <a href="#popular-places" :class="hoverClasses">{{
                    tr('Lugares populares', 'Popular places')
                }}</a>
                <a href="#how-it-works" :class="hoverClasses">{{
                    tr('Cómo funciona', 'How it works')
                }}</a>
            </nav>

            <div class="hidden items-center gap-3 sm:flex">
                <Link
                    :href="listPropertyUrl"
                    class="px-3 py-2 text-sm font-semibold transition"
                    :class="hoverClasses"
                >
                    {{ tr('Publicar propiedad', 'List a property') }}
                </Link>
                <LanguageSwitcher />
                <Link
                    v-if="page.props.auth.user"
                    :href="dashboardUrl"
                    class="rounded-full px-5 py-2.5 text-sm font-semibold transition"
                    :class="
                        overlay
                            ? 'bg-white text-[#123b6d] hover:bg-[#67d7ff]'
                            : 'bg-[#123b6d] text-white hover:bg-blue-900'
                    "
                >
                    Dashboard
                </Link>
                <Link
                    v-else
                    :href="login.url()"
                    class="rounded-full px-5 py-2.5 text-sm font-semibold transition"
                    :class="
                        overlay
                            ? 'bg-white text-[#123b6d] hover:bg-[#67d7ff]'
                            : 'bg-[#123b6d] text-white hover:bg-blue-900'
                    "
                >
                    {{ tr('Ingresar', 'Log in') }}
                </Link>
            </div>

            <button
                type="button"
                class="grid size-10 place-items-center rounded-xl border sm:hidden"
                :class="overlay ? 'border-white/30' : 'border-slate-300'"
                :aria-label="tr('Abrir navegación', 'Open navigation')"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <X v-if="mobileMenuOpen" class="size-5" />
                <Menu v-else class="size-5" />
            </button>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="border-t px-5 py-5 sm:hidden"
            :class="
                overlay
                    ? 'border-white/20 bg-[#123b6d]/95'
                    : 'border-slate-200 bg-white'
            "
        >
            <nav class="flex flex-col gap-4 text-sm font-semibold">
                <Link v-if="showExploreLinks" :href="rentals.url()">{{
                    tr('Propiedades', 'Properties')
                }}</Link>
                <Link :href="listPropertyUrl">{{
                    tr('Publicar propiedad', 'List a property')
                }}</Link>
                <LanguageSwitcher />
                <Link v-if="page.props.auth.user" :href="dashboardUrl"
                    >Dashboard</Link
                >
                <Link v-else :href="login.url()">{{
                    tr('Ingresar', 'Log in')
                }}</Link>
            </nav>
        </div>
    </header>
</template>
