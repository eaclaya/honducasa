<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { faq, home, privacy, terms } from '@/routes';
</script>

<template>
    <footer class="bg-[var(--public-surface)] text-[var(--public-muted)]">
        <div class="public-container flex flex-col gap-5 py-8 text-sm">
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold text-[var(--public-text)]"
                >
                    <AppLogoIcon class="size-7" /> Honducasa
                </Link>

                <nav class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <Link
                        :href="terms()"
                        class="hover:text-[var(--public-text)]"
                        >Términos de servicio</Link
                    >
                    <Link
                        :href="privacy()"
                        class="hover:text-[var(--public-text)]"
                        >Política de privacidad</Link
                    >
                    <Link :href="faq()" class="hover:text-[var(--public-text)]"
                        >Preguntas frecuentes</Link
                    >
                </nav>
            </div>

            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    Hecho para inquilinos, propietarios y comunidades de todo
                    Honduras.
                </p>
                <p>© {{ new Date().getFullYear() }} Honducasa</p>
            </div>
        </div>
    </footer>
</template>
