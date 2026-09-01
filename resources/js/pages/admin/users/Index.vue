<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, MoreVertical, Search, Users } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { reactive, ref } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
import SuspensionModal from '@/components/admin/SuspensionModal.vue';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { index as teamsIndex } from '@/routes/admin/teams';
import { index as usersIndex } from '@/routes/admin/users';
import { update as updateAdminStatus } from '@/routes/admin/users/admin-status';
import { update as updateSuspension } from '@/routes/admin/users/suspension';

type UserRow = {
    id: number;
    name: string;
    email: string;
    emailVerified: boolean;
    isAdmin: boolean;
    isLandlord: boolean;
    isRenter: boolean;
    isSuspended: boolean;
    suspensionReason: string | null;
    teamsCount: number;
    conversationsCount: number;
    favoritesCount: number;
    createdAt: string;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};
type Filters = {
    search: string | null;
    role: string | null;
    verification: string | null;
    registered: string | null;
    sort: string | null;
};

const props = defineProps<{
    users: Paginated<UserRow>;
    facetCounts: {
        all: number;
        landlord: number;
        renter: number;
        admin: number;
    };
    filters: Filters;
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const currentUserId = page.props.auth.user.id;
const { getInitials } = useInitials();

const filters = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    verification: props.filters.verification ?? '',
    registered: props.filters.registered ?? '',
    sort: props.filters.sort ?? '',
});

const applyFilters = (): void => {
    router.get(usersIndex.url(), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
const debouncedApply = useDebounceFn(applyFilters, 350);

const clearFilters = (): void => {
    filters.search = '';
    filters.role = '';
    filters.verification = '';
    filters.registered = '';
    filters.sort = '';
    applyFilters();
};

const setRole = (role: string): void => {
    filters.role = role;
    applyFilters();
};

const reinstate = (user: UserRow): void => {
    router.patch(
        updateSuspension.url(user.id),
        { suspended: false },
        { preserveScroll: true },
    );
};

const setAdminStatus = (user: UserRow, isAdmin: boolean): void => {
    router.patch(
        updateAdminStatus.url(user.id),
        { is_admin: isAdmin },
        { preserveScroll: true },
    );
};

const suspendTarget = ref<UserRow | null>(null);
</script>

<template>
    <Head :title="tr('Usuarios', 'Users')" />
    <div class="space-y-6 p-4 md:p-8">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold">
                <Users class="size-8 text-primary" />{{
                    tr('Usuarios', 'Users')
                }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Todas las cuentas de la plataforma: arrendadores, inquilinos y administradores.',
                        'Every account on the platform: landlords, renters and administrators.',
                    )
                }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 border-b pb-3">
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold"
                :class="
                    !filters.role
                        ? 'bg-primary text-primary-foreground'
                        : 'font-medium text-muted-foreground hover:bg-muted'
                "
                @click="setRole('')"
            >
                {{ tr('Todos', 'All') }}
                <span class="opacity-70">{{ facetCounts.all }}</span>
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold"
                :class="
                    filters.role === 'landlord'
                        ? 'bg-primary text-primary-foreground'
                        : 'font-medium text-muted-foreground hover:bg-muted'
                "
                @click="setRole('landlord')"
            >
                {{ tr('Arrendadores', 'Landlords') }}
                <span class="opacity-70">{{ facetCounts.landlord }}</span>
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold"
                :class="
                    filters.role === 'renter'
                        ? 'bg-primary text-primary-foreground'
                        : 'font-medium text-muted-foreground hover:bg-muted'
                "
                @click="setRole('renter')"
            >
                {{ tr('Inquilinos', 'Renters') }}
                <span class="opacity-70">{{ facetCounts.renter }}</span>
            </button>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold"
                :class="
                    filters.role === 'admin'
                        ? 'bg-primary text-primary-foreground'
                        : 'font-medium text-muted-foreground hover:bg-muted'
                "
                @click="setRole('admin')"
            >
                {{ tr('Administradores', 'Admins') }}
                <span class="opacity-70">{{ facetCounts.admin }}</span>
            </button>
        </div>

        <div
            class="flex flex-wrap items-end gap-3 rounded-2xl border bg-card p-4"
        >
            <label
                class="min-w-64 flex-1 text-xs font-semibold text-muted-foreground"
            >
                {{ tr('Buscar', 'Search') }}
                <div class="relative mt-1.5">
                    <Search
                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <input
                        v-model="filters.search"
                        class="w-full rounded-xl border bg-background py-2 pr-3 pl-9 text-sm text-foreground"
                        :placeholder="tr('Nombre o correo', 'Name or email')"
                        @input="debouncedApply"
                    />
                </div>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Verificación', 'Verification') }}
                <select
                    v-model="filters.verification"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">{{ tr('Todas', 'All') }}</option>
                    <option value="verified">
                        {{ tr('Verificados', 'Verified') }}
                    </option>
                    <option value="unverified">
                        {{ tr('Sin verificar', 'Unverified') }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Registro', 'Registered') }}
                <select
                    v-model="filters.registered"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">
                        {{ tr('Cualquier fecha', 'Any date') }}
                    </option>
                    <option value="7d">
                        {{ tr('Últimos 7 días', 'Last 7 days') }}
                    </option>
                    <option value="30d">
                        {{ tr('Últimos 30 días', 'Last 30 days') }}
                    </option>
                </select>
            </label>
            <label class="text-xs font-semibold text-muted-foreground">
                {{ tr('Ordenar por', 'Sort by') }}
                <select
                    v-model="filters.sort"
                    class="mt-1.5 block rounded-xl border bg-background px-3 py-2 text-sm text-foreground"
                    @change="applyFilters"
                >
                    <option value="">
                        {{ tr('Más recientes', 'Most recent') }}
                    </option>
                    <option value="conversations">
                        {{ tr('Más conversaciones', 'Most conversations') }}
                    </option>
                    <option value="name">
                        {{ tr('Nombre A–Z', 'Name A–Z') }}
                    </option>
                </select>
            </label>
            <button
                type="button"
                class="rounded-xl border px-4 py-2 text-sm font-semibold"
                @click="clearFilters"
            >
                {{ tr('Limpiar', 'Clear') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">
                                {{ tr('Usuario', 'User') }}
                            </th>
                            <th class="px-5 py-3">{{ tr('Rol', 'Role') }}</th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Equipos', 'Teams') }}
                            </th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Conv.', 'Conv.') }}
                            </th>
                            <th class="px-5 py-3 text-center">
                                {{ tr('Favoritos', 'Favorites') }}
                            </th>
                            <th class="px-5 py-3">
                                {{ tr('Registro', 'Registered') }}
                            </th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="grid size-9 shrink-0 place-items-center rounded-full text-sm font-bold"
                                        :class="
                                            user.isAdmin
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-accent text-accent-foreground'
                                        "
                                    >
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold">
                                            {{ user.name }}
                                        </p>
                                        <p
                                            class="flex items-center gap-1.5 truncate text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                            <span
                                                v-if="user.emailVerified"
                                                :title="
                                                    tr(
                                                        'Correo verificado',
                                                        'Verified email',
                                                    )
                                                "
                                                class="grid size-3.5 shrink-0 place-items-center rounded-full bg-emerald-600 text-white"
                                            >
                                                <CheckCircle2
                                                    class="size-2.5"
                                                />
                                            </span>
                                            <span
                                                v-else
                                                class="rounded bg-amber-100 px-1.5 text-[10px] font-bold text-amber-800 uppercase dark:bg-amber-950 dark:text-amber-400"
                                                >{{
                                                    tr(
                                                        'Sin verificar',
                                                        'Unverified',
                                                    )
                                                }}</span
                                            >
                                        </p>
                                        <p
                                            v-if="user.isSuspended"
                                            class="mt-0.5 text-xs font-semibold text-destructive"
                                        >
                                            {{ tr('Suspendido', 'Suspended')
                                            }}<span v-if="user.suspensionReason"
                                                >:
                                                {{
                                                    user.suspensionReason
                                                }}</span
                                            >
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-if="user.isAdmin"
                                        class="uppercase"
                                        >{{ tr('Admin', 'Admin') }}</Badge
                                    >
                                    <Badge
                                        v-if="user.isLandlord"
                                        variant="secondary"
                                        class="uppercase"
                                        >{{
                                            tr('Arrendador', 'Landlord')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="user.isRenter"
                                        variant="outline"
                                        class="uppercase"
                                        >{{ tr('Inquilino', 'Renter') }}</Badge
                                    >
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">
                                {{ user.teamsCount }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">
                                {{ user.conversationsCount }}
                            </td>
                            <td class="px-5 py-3.5 text-center font-medium">
                                {{ user.favoritesCount }}
                            </td>
                            <td
                                class="px-5 py-3.5 whitespace-nowrap text-muted-foreground"
                            >
                                {{ user.createdAt }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 hover:bg-muted"
                                        >
                                            <MoreVertical
                                                class="size-4 text-muted-foreground"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-64"
                                    >
                                        <DropdownMenuItem as-child>
                                            <Link
                                                :href="
                                                    teamsIndex.url({
                                                        query: {
                                                            search: user.email,
                                                        },
                                                    })
                                                "
                                            >
                                                {{
                                                    tr(
                                                        'Ver sus equipos',
                                                        'View their teams',
                                                    )
                                                }}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-if="user.isSuspended"
                                            @click="reinstate(user)"
                                        >
                                            {{
                                                tr(
                                                    'Reactivar cuenta',
                                                    'Reinstate account',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-else
                                            variant="destructive"
                                            @click="suspendTarget = user"
                                        >
                                            {{
                                                tr(
                                                    'Suspender cuenta',
                                                    'Suspend account',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                        <template v-if="user.isAdmin">
                                            <DropdownMenuItem
                                                v-if="user.id !== currentUserId"
                                                variant="destructive"
                                                @click="
                                                    setAdminStatus(user, false)
                                                "
                                            >
                                                {{
                                                    tr(
                                                        'Quitar administrador',
                                                        'Remove admin access',
                                                    )
                                                }}
                                            </DropdownMenuItem>
                                            <div
                                                v-else
                                                class="px-2 py-1.5 text-sm text-muted-foreground"
                                            >
                                                {{
                                                    tr(
                                                        'Quitar administrador',
                                                        'Remove admin access',
                                                    )
                                                }}
                                                <small
                                                    class="mt-0.5 block text-[11px] leading-tight text-destructive"
                                                >
                                                    {{
                                                        tr(
                                                            'No puedes quitarte el acceso a ti mismo.',
                                                            "You can't remove your own access.",
                                                        )
                                                    }}
                                                </small>
                                            </div>
                                        </template>
                                        <DropdownMenuItem
                                            v-else
                                            @click="setAdminStatus(user, true)"
                                        >
                                            {{
                                                tr(
                                                    'Hacer administrador',
                                                    'Grant admin access',
                                                )
                                            }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="users.data.length === 0"
                class="grid min-h-40 place-items-center text-center text-muted-foreground"
            >
                {{ tr('No se encontraron usuarios.', 'No users found.') }}
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm"
            >
                <p class="text-muted-foreground">
                    {{ tr('Mostrando', 'Showing') }}
                    <b class="text-foreground"
                        >{{ users.from ?? 0 }}–{{ users.to ?? 0 }}</b
                    >
                    {{ tr('de', 'of') }}
                    <b class="text-foreground">{{ users.total }}</b>
                </p>
                <Pagination :links="users.links" />
            </div>
        </div>

        <SuspensionModal
            v-if="suspendTarget"
            :open="suspendTarget !== null"
            :name="suspendTarget.name"
            :url="updateSuspension.url(suspendTarget.id)"
            @update:open="
                (open) => {
                    if (!open) suspendTarget = null;
                }
            "
        />
    </div>
</template>
