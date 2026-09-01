<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

type PageLink = { url: string | null; label: string; active: boolean };

defineProps<{ links: PageLink[] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <template v-for="(link, i) in links" :key="i">
            <span
                v-if="!link.url"
                class="grid size-9 place-items-center rounded-lg border text-sm text-muted-foreground opacity-40"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                preserve-state
                class="grid size-9 place-items-center rounded-lg border text-sm font-medium hover:bg-muted"
                :class="
                    link.active
                        ? 'border-primary bg-primary text-primary-foreground hover:bg-primary'
                        : ''
                "
            >
                <span v-html="link.label" />
            </Link>
        </template>
    </div>
</template>
