<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/routes/currency';

/** Segment width in rem, wide enough for a three-letter currency code. */
const SEGMENT_WIDTH = 3.25;

const page = usePage();
const locale = computed(() => page.props.locale);
const currency = computed(() => page.props.currency);
const options = computed(() => currency.value.supported);
const activeIndex = computed(() =>
    Math.max(options.value.indexOf(currency.value.display), 0),
);
const trackStyle = computed(() => ({
    width: `calc(${options.value.length} * ${SEGMENT_WIDTH}rem + 0.5rem)`,
    gridTemplateColumns: `repeat(${options.value.length}, minmax(0, 1fr))`,
}));
const thumbStyle = computed(() => ({
    width: `${SEGMENT_WIDTH}rem`,
    transform: `translateX(${activeIndex.value * SEGMENT_WIDTH}rem)`,
}));

const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;

const switchCurrency = (nextCurrency: string): void => {
    if (nextCurrency === currency.value.display) {
        return;
    }

    router.post(update.url(nextCurrency), {}, { preserveScroll: true });
};

const stepCurrency = (offset: number): void => {
    const count = options.value.length;

    switchCurrency(options.value[(activeIndex.value + offset + count) % count]);
};

const toggleCurrency = (): void => {
    stepCurrency(1);
};

const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        stepCurrency(-1);
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        stepCurrency(1);
    }
};
</script>

<template>
    <div
        v-if="options.length > 1"
        class="flex items-center text-xs font-bold"
        :aria-label="
            tr(`Moneda: ${currency.display}`, `Currency: ${currency.display}`)
        "
    >
        <button
            type="button"
            :role="options.length === 2 ? 'switch' : undefined"
            :aria-checked="options.length === 2 ? activeIndex === 1 : undefined"
            :aria-label="
                tr(
                    `Cambiar moneda. Moneda actual: ${currency.display}`,
                    `Change currency. Current currency: ${currency.display}`,
                )
            "
            class="relative grid h-10 items-center rounded-full border border-current/25 p-1 transition focus-visible:ring-2 focus-visible:ring-current/40 focus-visible:ring-offset-2 focus-visible:outline-none"
            :style="trackStyle"
            @click="toggleCurrency"
            @keydown="handleKeydown"
        >
            <span
                class="absolute inset-y-1 left-1 rounded-full bg-primary shadow-sm transition-transform duration-200 ease-out"
                :style="thumbStyle"
                aria-hidden="true"
            />
            <span
                v-for="option in options"
                :key="option"
                class="relative z-10 transition"
                :class="
                    option === currency.display
                        ? 'text-primary-foreground opacity-100'
                        : 'opacity-55'
                "
                >{{ option }}</span
            >
        </button>
    </div>
</template>
