<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
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
</script>

<template>
    <div
        class="flex items-center gap-1 rounded-full border border-current/20 p-1 text-xs font-bold"
        aria-label="Language"
    >
        <Languages class="ml-1 size-3.5 opacity-70" />
        <button
            type="button"
            class="rounded-full px-2 py-1"
            :class="locale === 'es' ? 'bg-current/15' : 'opacity-60'"
            @click="switchLocale('es')"
        >
            ES
        </button>
        <button
            type="button"
            class="rounded-full px-2 py-1"
            :class="locale === 'en' ? 'bg-current/15' : 'opacity-60'"
            @click="switchLocale('en')"
        >
            EN
        </button>
    </div>
</template>
