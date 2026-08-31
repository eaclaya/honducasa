<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { RotateCcw, Search, ShieldBan, TriangleAlert } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
import { index, destroy as unblock } from '@/routes/admin/blacklist';

type Strike = {
    id: number;
    source: string;
    reason: string;
    metadata: Record<string, unknown> | null;
    createdAt: string;
};
type BlacklistedUser = {
    id: number;
    name: string;
    email: string;
    blockedAt: string | null;
    blockedReason: string | null;
    activeStrikesCount: number;
    totalStrikesCount: number;
    strikes: Strike[];
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    users: Paginated<BlacklistedUser>;
    filters: { search: string | null };
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const search = ref(props.filters.search ?? '');
const selectedUser = ref<BlacklistedUser | null>(null);
const unblockForm = useForm({ reason: '' });

const applySearch = useDebounceFn(() => {
    router.get(
        index.url(),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 350);

const openUnblock = (user: BlacklistedUser): void => {
    selectedUser.value = user;
    unblockForm.reset();
    unblockForm.clearErrors();
};

const closeUnblock = (): void => {
    if (!unblockForm.processing) {
        selectedUser.value = null;
    }
};

const submitUnblock = (): void => {
    if (!selectedUser.value) {
        return;
    }

    unblockForm.delete(unblock.url(selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => closeUnblock(),
    });
};

const sourceLabel = (source: string): string =>
    source === 'listing_image'
        ? tr('Imagen de propiedad', 'Listing image')
        : tr('Texto de propiedad', 'Listing text');
</script>

<template>
    <Head :title="tr('Lista negra', 'Blacklist')" />
    <div class="space-y-6 p-4 md:p-8">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold">
                <ShieldBan class="size-8 text-destructive" />
                {{ tr('Lista negra', 'Blacklist') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Cuentas bloqueadas automáticamente después de tres infracciones de moderación.',
                        'Accounts automatically blocked after three moderation violations.',
                    )
                }}
            </p>
        </div>

        <label class="block max-w-xl text-xs font-semibold text-muted-foreground">
            {{ tr('Buscar', 'Search') }}
            <div class="relative mt-1.5">
                <Search
                    class="pointer-events-none absolute top-2.5 left-3 size-4"
                />
                <input
                    v-model="search"
                    class="w-full rounded-xl border bg-background py-2 pr-3 pl-9 text-sm text-foreground"
                    :placeholder="tr('Nombre o correo', 'Name or email')"
                    @input="applySearch"
                />
            </div>
        </label>

        <div v-if="users.data.length" class="space-y-4">
            <article
                v-for="user in users.data"
                :key="user.id"
                class="rounded-2xl border bg-card p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold">{{ user.name }}</h2>
                            <span
                                class="rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-bold text-destructive"
                            >
                                {{ user.activeStrikesCount }}/3
                                {{ tr('infracciones activas', 'active strikes') }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ user.email }} ·
                            {{ tr('Bloqueado', 'Blocked') }} {{ user.blockedAt }}
                        </p>
                        <p class="mt-3 font-medium text-destructive">
                            {{ user.blockedReason }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-muted"
                        @click="openUnblock(user)"
                    >
                        <RotateCcw class="size-4" />
                        {{ tr('Desbloquear', 'Unblock') }}
                    </button>
                </div>

                <div class="mt-4 grid gap-2 border-t pt-4 md:grid-cols-3">
                    <div
                        v-for="strike in user.strikes"
                        :key="strike.id"
                        class="rounded-xl bg-muted p-3 text-sm"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <TriangleAlert class="size-4 text-amber-600" />
                            {{ sourceLabel(strike.source) }}
                        </div>
                        <p class="mt-2 text-muted-foreground">
                            {{ strike.reason }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ strike.createdAt }}
                        </p>
                    </div>
                </div>
            </article>

            <Pagination :links="users.links" />
        </div>

        <div
            v-else
            class="grid min-h-80 place-items-center rounded-2xl border border-dashed text-center text-muted-foreground"
        >
            <div>
                <ShieldBan class="mx-auto size-12 text-emerald-600" />
                <h2 class="mt-4 text-xl font-semibold text-foreground">
                    {{ tr('La lista negra está vacía', 'The blacklist is empty') }}
                </h2>
            </div>
        </div>
    </div>

    <div
        v-if="selectedUser"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="closeUnblock"
    >
        <form
            class="w-full max-w-lg rounded-2xl border bg-card p-6 shadow-xl"
            @submit.prevent="submitUnblock"
        >
            <h2 class="text-xl font-semibold">
                {{ tr('Desbloquear cuenta', 'Unblock account') }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    tr(
                        `Se limpiarán las infracciones activas de ${selectedUser.name}, pero el historial se conservará.`,
                        `${selectedUser.name}'s active strikes will be cleared, but their history will be retained.`,
                    )
                }}
            </p>
            <label class="mt-5 block text-sm font-semibold">
                {{ tr('Motivo del desbloqueo', 'Reason for unblocking') }}
                <textarea
                    v-model="unblockForm.reason"
                    rows="4"
                    class="mt-2 w-full rounded-xl border bg-background p-3 text-foreground"
                    required
                />
            </label>
            <p v-if="unblockForm.errors.reason" class="mt-1 text-sm text-destructive">
                {{ unblockForm.errors.reason }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold"
                    :disabled="unblockForm.processing"
                    @click="closeUnblock"
                >
                    {{ tr('Cancelar', 'Cancel') }}
                </button>
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                    :disabled="unblockForm.processing"
                >
                    {{
                        unblockForm.processing
                            ? tr('Desbloqueando…', 'Unblocking…')
                            : tr('Confirmar desbloqueo', 'Confirm unblock')
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
