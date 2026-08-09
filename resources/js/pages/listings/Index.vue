<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Edit3, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { create, destroy, edit } from '@/routes/listings';

type Listing = {
    id: number;
    slug: string;
    name: string | null;
    status: string;
    listingType: string;
    priceAmount: number;
    currency: string;
    image: string | null;
};
defineProps<{ listings: Listing[] }>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const team = computed(() => page.props.currentTeam!);
const money = (item: Listing): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency: item.currency,
        maximumFractionDigits: 0,
    }).format(item.priceAmount);
const remove = (item: Listing): void => {
    if (confirm(tr('¿Eliminar esta propiedad?', 'Delete this listing?'))) {
        router.delete(
            destroy.url({ current_team: team.value.slug, listing: item.id }),
        );
    }
};
</script>

<template>
    <Head :title="tr('Mis propiedades', 'My listings')" />
    <div class="space-y-6 p-4 md:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-3xl font-semibold">
                    {{ tr('Mis propiedades', 'My listings') }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{
                        tr(
                            'Administra borradores y publicaciones activas.',
                            'Manage drafts and active listings.',
                        )
                    }}
                </p>
            </div>
            <Link
                :href="create.url(team.slug)"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground"
                ><Plus class="size-5" />{{
                    tr('Nueva propiedad', 'New listing')
                }}</Link
            >
        </div>
        <div
            v-if="listings.length"
            class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="item in listings"
                :key="item.id"
                class="overflow-hidden rounded-2xl border bg-card"
            >
                <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.name ?? ''"
                    class="aspect-video w-full object-cover"
                />
                <div
                    v-else
                    class="grid aspect-video place-items-center bg-blue-50 text-blue-700"
                >
                    <Building2 class="size-12" />
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-800"
                            >{{ item.status }}</span
                        ><b
                            >{{ money(item)
                            }}<small v-if="item.listingType === 'rent'"
                                >/mes</small
                            ></b
                        >
                    </div>
                    <h2 class="mt-4 text-lg font-semibold">{{ item.name }}</h2>
                    <div class="mt-5 flex gap-2">
                        <Link
                            :href="
                                edit.url({
                                    current_team: team.slug,
                                    listing: item.id,
                                })
                            "
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold"
                            ><Edit3 class="size-4" />{{
                                tr('Editar', 'Edit')
                            }}</Link
                        ><button
                            class="rounded-xl border px-3 text-destructive"
                            @click="remove(item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="grid min-h-80 place-items-center rounded-2xl border border-dashed text-center"
        >
            <div>
                <Building2 class="mx-auto size-12 text-blue-600" />
                <h2 class="mt-4 text-xl font-semibold">
                    {{ tr('Aún no tienes propiedades', 'No listings yet') }}
                </h2>
            </div>
        </div>
    </div>
</template>
