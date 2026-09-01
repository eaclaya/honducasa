<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/routes/locale';

const page = usePage();
const locale = computed(() => page.props.locale);

const switchLocale = (nextLocale: 'es' | 'en'): void => {
    if (nextLocale === locale.value) {
        return;
    }

    router.post(update.url(nextLocale), {}, { preserveScroll: true });
};

const toggleLocale = (): void => {
    switchLocale(locale.value === 'es' ? 'en' : 'es');
};

const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        switchLocale('es');
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        switchLocale('en');
    }
};
</script>

<template>
    <div
        class="flex items-center text-xs font-bold"
        :aria-label="locale === 'es' ? 'Idioma: español' : 'Language: English'"
    >
        <button
            type="button"
            role="switch"
            :aria-checked="locale === 'en'"
            :aria-label="
                locale === 'es'
                    ? 'Cambiar idioma a inglés'
                    : 'Switch language to Spanish'
            "
            class="relative grid h-10 w-[5.5rem] grid-cols-2 items-center rounded-full border border-current/25 p-1 transition focus-visible:ring-2 focus-visible:ring-current/40 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="toggleLocale"
            @keydown="handleKeydown"
        >
            <span
                class="absolute inset-y-1 left-1 w-9 rounded-full bg-primary shadow-sm transition-transform duration-200 ease-out"
                :class="locale === 'en' ? 'translate-x-10' : 'translate-x-0'"
                aria-hidden="true"
            />
            <span
                class="relative z-10 transition-opacity"
                :class="locale === 'es' ? 'opacity-100' : 'opacity-55'"
                >ES</span
            >
            <span
                class="relative z-10 transition-opacity"
                :class="locale === 'en' ? 'opacity-100' : 'opacity-55'"
                >EN</span
            >
        </button>
    </div>
</template>
