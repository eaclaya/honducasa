<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Heart, MapPin } from '@lucide/vue';
import { destroy } from '@/routes/favorites';
import { show as propertyShow } from '@/routes/properties';

type Favorite = {
    slug: string;
    name: string | null;
    location: string;
    priceAmount: number;
    currency: string;
    listingType: string;
    image: string | null;
};
defineProps<{ favorites: { data: Favorite[] } }>();
const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const money = (item: Favorite): string =>
    new Intl.NumberFormat(page.props.locale === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency: item.currency,
        maximumFractionDigits: 0,
    }).format(item.priceAmount);
</script>

<template>
    <Head :title="tr('Favoritos', 'Favorites')" />
    <main class="mx-auto max-w-7xl space-y-6 p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-semibold">
                {{ tr('Propiedades favoritas', 'Favorite properties') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Tus propiedades guardadas en un solo lugar.',
                        'Your saved properties in one place.',
                    )
                }}
            </p>
        </div>
        <div
            v-if="favorites.data.length"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <article
                v-for="item in favorites.data"
                :key="item.slug"
                class="overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <Link :href="propertyShow.url(item.slug)"
                    ><img
                        v-if="item.image"
                        :src="item.image"
                        :alt="item.name ?? ''"
                        class="aspect-[16/10] w-full object-cover" />
                    <div v-else class="aspect-[16/10] bg-muted"
                /></Link>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <Link
                            :href="propertyShow.url(item.slug)"
                            class="font-semibold hover:underline"
                            >{{ item.name }}</Link
                        ><button
                            type="button"
                            :aria-label="
                                tr('Quitar favorito', 'Remove favorite')
                            "
                            class="text-red-500"
                            @click="
                                router.delete(destroy.url(item.slug), {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <Heart class="size-5 fill-current" />
                        </button>
                    </div>
                    <p
                        class="mt-2 flex items-center gap-1 text-sm text-muted-foreground"
                    >
                        <MapPin class="size-4" />{{ item.location }}
                    </p>
                    <p class="mt-4 font-bold text-blue-800">
                        {{ money(item) }}
                    </p>
                </div>
            </article>
        </div>
        <div
            v-else
            class="grid min-h-72 place-items-center rounded-2xl border border-dashed text-center"
        >
            <div>
                <Heart class="mx-auto size-10 text-muted-foreground" />
                <h2 class="mt-3 text-xl font-semibold">
                    {{ tr('Aún no tienes favoritos', 'No favorites yet') }}
                </h2>
            </div>
        </div>
    </main>
</template>
