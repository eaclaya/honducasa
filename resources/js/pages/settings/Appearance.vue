<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: (props: { locale?: string }) => ({
        breadcrumbs: [
            {
                title:
                    props.locale === 'es'
                        ? 'Configuración de apariencia'
                        : 'Appearance settings',
                href: edit(),
            },
        ],
    }),
});

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
</script>

<template>
    <Head :title="tr('Configuración de apariencia', 'Appearance settings')" />

    <h1 class="sr-only">
        {{ tr('Configuración de apariencia', 'Appearance settings') }}
    </h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="tr('Configuración de apariencia', 'Appearance settings')"
            :description="
                tr(
                    'Actualiza la apariencia de tu cuenta',
                    'Update the appearance settings for your account',
                )
            "
        />
        <AppearanceTabs />
    </div>
</template>
