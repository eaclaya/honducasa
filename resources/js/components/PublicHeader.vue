<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, LayoutDashboard, LogOut, Menu, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import GoogleOneTap from '@/components/GoogleOneTap.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { dashboard, home, login, logout, register } from '@/routes';
import { create as createAgency } from '@/routes/agencies';
import { create as createListing } from '@/routes/listings';
import { create as createPersonalListing } from '@/routes/personal-listings';
import { dashboard as userDashboard } from '@/routes/user';

const props = withDefaults(
    defineProps<{
        overlay?: boolean;
    }>(),
    {
        overlay: false,
    },
);

const page = usePage();
const mobileMenuOpen = ref(false);
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const { getInitials } = useInitials();

const handleLogout = (): void => {
    router.flushAll();
};

const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : userDashboard().url,
);
const listPropertyUrl = computed(() => {
    if (page.props.currentTeam) {
        return createListing.url(page.props.currentTeam.slug);
    }

    return page.props.auth.user ? createPersonalListing().url : register.url();
});

const headerClasses = computed(() =>
    props.overlay
        ? 'absolute inset-x-0 top-0 z-30 border-b border-white/25 bg-gradient-to-b from-black/40 to-black/5 text-white'
        : 'relative z-30 border-b border-[var(--public-border)] bg-[var(--public-surface-raised)] text-[var(--public-text)]',
);

const hoverClasses = computed(() =>
    props.overlay
        ? 'hover:text-blue-300'
        : 'hover:text-[var(--public-brand-ink)]',
);
</script>

<template>
    <header :class="headerClasses">
        <GoogleOneTap />
        <div
            class="public-container flex h-20 items-center justify-between gap-5"
        >
            <Link
                :href="home.url()"
                class="flex shrink-0 items-center gap-2.5"
                aria-label="HonduCasa home"
            >
                <AppLogoIcon class="size-10" />
                <span class="text-xl font-semibold tracking-[-0.02em]"
                    >honducasa</span
                >
            </Link>

            <div class="hidden items-center gap-3 sm:flex">
                <LanguageSwitcher />
                <Link
                    :href="listPropertyUrl"
                    class="rounded-full px-4 py-3 text-sm font-semibold transition"
                    :class="
                        overlay
                            ? 'bg-primary text-primary-foreground hover:bg-primary-hover'
                            : 'bg-primary text-primary-foreground hover:bg-primary-hover'
                    "
                >
                    {{ tr('Publicar propiedad', 'List a property') }}
                </Link>
                <DropdownMenu v-if="page.props.auth.user">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded-full p-1 transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                            :class="
                                overlay
                                    ? 'bg-white text-primary hover:bg-blue-50'
                                    : 'border border-[var(--public-border)] bg-[var(--public-surface-raised)] text-[var(--public-brand-ink)] shadow-sm hover:shadow-md'
                            "
                            :aria-label="
                                tr('Abrir menú de usuario', 'Open user menu')
                            "
                        >
                            <Avatar class="size-9 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="page.props.auth.user.avatar"
                                    :src="page.props.auth.user.avatar"
                                    :alt="page.props.auth.user.name"
                                />
                                <AvatarFallback
                                    class="bg-transparent font-semibold text-current"
                                >
                                    {{ getInitials(page.props.auth.user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="z-[2000] w-56">
                        <DropdownMenuLabel class="font-normal">
                            <p class="truncate text-sm font-semibold">
                                {{ page.props.auth.user.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ page.props.auth.user.email }}
                            </p>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link :href="dashboardUrl" class="cursor-pointer">
                                <LayoutDashboard class="mr-2 size-4" />
                                Dashboard
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="createAgency()" class="cursor-pointer">
                                <Building2 class="mr-2 size-4" />
                                {{
                                    tr('Crear una agencia', 'Create an agency')
                                }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="logout()"
                                as="button"
                                class="w-full cursor-pointer"
                                data-test="public-header-logout-button"
                                @click="handleLogout"
                            >
                                <LogOut class="mr-2 size-4" />
                                {{ tr('Cerrar sesión', 'Log out') }}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Link
                    v-else
                    :href="login.url()"
                    class="rounded-full px-4 py-3 text-sm font-semibold transition hover:bg-[var(--public-surface-hover)]"
                    :class="hoverClasses"
                >
                    {{ tr('Ingresar', 'Log in') }}
                </Link>
            </div>

            <button
                type="button"
                class="grid size-10 place-items-center rounded-full border sm:hidden"
                :class="overlay ? 'border-white/30' : 'border-primary/25'"
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
                    ? 'border-white/20 bg-primary/95'
                    : 'border-primary-hover bg-primary'
            "
        >
            <nav class="flex flex-col gap-4 text-sm font-semibold">
                <Link :href="listPropertyUrl">{{
                    tr('Publicar propiedad', 'List a property')
                }}</Link>
                <LanguageSwitcher />
                <template v-if="page.props.auth.user">
                    <Link :href="dashboardUrl">Dashboard</Link>
                    <Link :href="createAgency()">{{
                        tr('Crear una agencia', 'Create an agency')
                    }}</Link>
                    <Link
                        :href="logout()"
                        as="button"
                        class="text-left"
                        @click="handleLogout"
                    >
                        {{ tr('Cerrar sesión', 'Log out') }}
                    </Link>
                </template>
                <Link v-else :href="login.url()">{{
                    tr('Ingresar', 'Log in')
                }}</Link>
            </nav>
        </div>
    </header>
</template>
