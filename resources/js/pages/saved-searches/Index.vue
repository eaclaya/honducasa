<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellOff, Search, Trash2 } from '@lucide/vue';
import { index as rentals } from '@/routes/rentals';
import { destroy, update } from '@/routes/saved-searches';

type SavedSearch = {
    id: number;
    name: string;
    filters: Record<string, string | number | boolean | null>;
    alertsEnabled: boolean;
    createdAt: string;
};
defineProps<{ savedSearches: SavedSearch[] }>();
const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head :title="tr('Búsquedas guardadas', 'Saved searches')" />
    <main class="mx-auto max-w-5xl space-y-6 p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-semibold">
                {{ tr('Búsquedas guardadas', 'Saved searches') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Administra tus criterios y alertas privadas.',
                        'Manage your criteria and private alerts.',
                    )
                }}
            </p>
        </div>
        <div v-if="savedSearches.length" class="space-y-3">
            <article
                v-for="item in savedSearches"
                :key="item.id"
                class="flex flex-wrap items-center gap-4 rounded-2xl border bg-card p-5"
            >
                <span
                    class="grid size-11 place-items-center rounded-full bg-blue-100 text-blue-700"
                    ><Search class="size-5"
                /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">{{ item.name }}</h2>
                    <p class="truncate text-sm text-muted-foreground">
                        {{
                            Object.values(item.filters)
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                </div>
                <Link
                    :href="rentals.url({ query: item.filters })"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold"
                    >{{ tr('Ver resultados', 'View results') }}</Link
                ><button
                    type="button"
                    class="rounded-xl border p-2"
                    @click="
                        router.patch(
                            update.url(item.id),
                            { alerts_enabled: !item.alertsEnabled },
                            { preserveScroll: true },
                        )
                    "
                >
                    <Bell
                        v-if="item.alertsEnabled"
                        class="size-5 text-blue-700"
                    /><BellOff v-else class="size-5" /></button
                ><button
                    type="button"
                    class="rounded-xl border p-2 text-red-600"
                    @click="
                        router.delete(destroy.url(item.id), {
                            preserveScroll: true,
                        })
                    "
                >
                    <Trash2 class="size-5" />
                </button>
            </article>
        </div>
        <div
            v-else
            class="grid min-h-72 place-items-center rounded-2xl border border-dashed text-center"
        >
            <div>
                <Search class="mx-auto size-10 text-muted-foreground" />
                <h2 class="mt-3 text-xl font-semibold">
                    {{ tr('No hay búsquedas guardadas', 'No saved searches') }}
                </h2>
            </div>
        </div>
    </main>
</template>
