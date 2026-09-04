<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, House } from '@lucide/vue';
import { computed } from 'vue';
import { show as propertyShow } from '@/routes/properties';

export type PublicPropertyCardData = {
    id: number;
    slug: string;
    name: string | null;
    type: string;
    listingType: 'rent' | 'buy';
    location: string;
    bedrooms: number | null;
    bathrooms: string | null;
    parkingSpaces: number | null;
    interiorAreaM2: number | null;
    furnishing: string | null;
    priceAmount: number;
    currency: string;
    priceIsConverted: boolean;
    utilitiesIncluded: boolean | null;
    primaryImage: { url: string; altText: string | null } | null;
    isFavorited: boolean;
};

const props = defineProps<{
    property: PublicPropertyCardData;
    tone?: string;
    returnTo?: string;
}>();

const emit = defineEmits<{ favorite: [] }>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const humanize = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const price = computed(() =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency: props.property.currency,
        maximumFractionDigits: 0,
    }).format(props.property.priceAmount),
);
</script>

<template>
    <Link
        :href="
            propertyShow.url(property.slug, {
                query: { return_to: returnTo },
            })
        "
        class="group block min-w-0 text-[var(--public-text)]"
    >
        <div
            class="relative grid aspect-[4/3] place-items-center overflow-hidden rounded-xl bg-gradient-to-br"
            :class="tone"
        >
            <img
                v-if="property.primaryImage"
                :src="property.primaryImage.url"
                :alt="
                    property.primaryImage.altText ??
                    property.name ??
                    tr('Propiedad', 'Property')
                "
                class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.015]"
                loading="lazy"
            />
            <div
                v-else
                class="grid size-20 place-items-center rounded-full bg-white/75 text-primary shadow-sm backdrop-blur"
            >
                <House class="size-9" :stroke-width="1.5" />
            </div>
            <span
                class="absolute top-3 left-3 rounded-full bg-[var(--public-surface-raised)] px-3 py-1.5 text-xs font-semibold shadow-sm"
            >
                {{ humanize(property.type) }}
            </span>
            <button
                type="button"
                class="absolute top-2.5 right-2.5 grid size-10 place-items-center rounded-full bg-[var(--public-surface-raised)] text-[var(--public-text)] shadow-sm transition hover:scale-105 hover:bg-[var(--public-surface-hover)]"
                :aria-label="tr('Guardar propiedad', 'Save property')"
                @click.prevent.stop="emit('favorite')"
            >
                <Heart
                    class="size-5"
                    :stroke-width="1"
                    :class="
                        property.isFavorited ? 'fill-primary text-primary' : ''
                    "
                />
            </button>
        </div>

        <div class="pt-3">
            <div class="flex items-start justify-between gap-3">
                <h2 class="line-clamp-1 min-w-0 font-semibold">
                    {{ property.name ?? tr('Propiedad', 'Property') }}
                </h2>
            </div>
            <p class="mt-0.5 truncate text-sm text-[var(--public-muted)]">
                {{ property.location }}, Honduras
            </p>
            <p
                v-if="
                    property.bedrooms !== null ||
                    property.bathrooms !== null ||
                    property.parkingSpaces !== null
                "
                class="mt-0.5 flex flex-wrap gap-x-2 text-sm text-[var(--public-muted)]"
            >
                <span v-if="property.bedrooms !== null"
                    >{{ property.bedrooms }}
                    {{ tr('habitaciones', 'beds') }}</span
                >
                <span v-if="property.bathrooms !== null"
                    >{{ property.bathrooms }} {{ tr('baños', 'baths') }}</span
                >
                <span v-if="property.parkingSpaces !== null"
                    >{{ property.parkingSpaces }}
                    {{ tr('parqueos', 'parking') }}</span
                >
            </p>
            <p class="mt-1.5 text-sm">
                <span class="font-semibold text-[var(--public-brand-ink)]"
                    ><span v-if="property.priceIsConverted">≈ </span
                    >{{ price }}</span
                >
                <span v-if="property.listingType === 'rent'">
                    / {{ tr('mes', 'month') }}</span
                >
            </p>
            <p
                v-if="property.utilitiesIncluded"
                class="mt-1 text-xs font-medium text-[var(--public-brand-ink)]"
            >
                {{ tr('Servicios incluidos', 'Utilities included') }}
            </p>
        </div>
    </Link>
</template>
