<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import GoogleOneTap from '@/components/GoogleOneTap.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground md:p-10"
    >
        <GoogleOneTap />
        <Button
            as-child
            variant="ghost"
            class="absolute top-4 left-4 text-muted-foreground md:top-6 md:left-6"
        >
            <Link :href="home()" data-test="back-to-home-link">
                <ArrowLeft class="size-4" />
                {{ tr('Volver al inicio', 'Back to home') }}
            </Link>
        </Button>
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-foreground"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
