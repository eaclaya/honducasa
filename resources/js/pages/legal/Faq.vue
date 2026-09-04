<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

const faqs = [
    {
        question: '¿Cómo busco una propiedad en alquiler o venta?',
        answer: 'Escribe una ciudad, colonia o dirección en el buscador, o dibuja un área directamente en el mapa para ver únicamente las propiedades dentro de esa zona. Puedes filtrar por precio, habitaciones, baños, tipo de propiedad, amueblado y más.',
    },
    {
        question: '¿Qué es una búsqueda guardada y cómo recibo alertas?',
        answer: 'Al guardar una búsqueda, Honducasa la revisa periódicamente y te notifica cuando aparecen propiedades nuevas que coinciden con tus filtros. Puedes gestionar o eliminar tus búsquedas guardadas en cualquier momento desde tu cuenta.',
    },
    {
        question: '¿Cómo publico un anuncio?',
        answer: 'Inicia sesión, crea tu anuncio con fotos, ubicación, precio y características de la propiedad, y publícalo. Puedes publicar como persona individual o crear una agencia si administras varias propiedades con tu equipo.',
    },
    {
        question: '¿Hay un período de prueba gratuito?',
        answer: 'Sí. Al crear tu cuenta y comenzar a publicar, obtienes un período de prueba gratuito con acceso al plan de entrada. Al finalizar, deberás elegir un plan de suscripción para seguir publicando y administrando tus anuncios.',
    },
    {
        question: '¿Qué planes de suscripción existen?',
        answer: 'Ofrecemos planes con un límite fijo de anuncios activos por un precio mensual, y planes que cobran por cada anuncio activo sin límite de cantidad. Puedes ver el precio y los beneficios de cada plan desde la sección de facturación de tu cuenta.',
    },
    {
        question: '¿Cómo contacto al propietario o inquilino interesado?',
        answer: 'Cada anuncio tiene un botón para iniciar una conversación directamente dentro de la Plataforma. Todos tus mensajes quedan organizados en la sección de Mensajes de tu cuenta.',
    },
    {
        question: '¿Puedo iniciar sesión con Google?',
        answer: 'Sí. Puedes crear tu cuenta o iniciar sesión con tu cuenta de Google, o registrarte con tu correo electrónico y una contraseña.',
    },
    {
        question: '¿En qué moneda se muestran los precios?',
        answer: 'Puedes alternar entre lempiras (HNL) y dólares (USD) usando el selector de moneda en la parte superior de la página; los precios se convierten automáticamente.',
    },
    {
        question: '¿Qué hago si un anuncio parece falso o sospechoso?',
        answer: 'Puedes reportarlo desde la propia página del anuncio o conversación. Nuestro equipo revisa los reportes y puede retirar anuncios que incumplan nuestros Términos de servicio.',
    },
    {
        question: '¿Cómo elimino mi cuenta?',
        answer: 'Puedes solicitar la eliminación de tu cuenta desde la configuración de tu perfil. Si tienes problemas para hacerlo, escríbenos y te ayudamos.',
    },
];
</script>

<template>
    <Head title="Preguntas frecuentes" />

    <div
        class="public-site min-h-screen bg-[var(--public-surface)] text-[var(--public-text)]"
    >
        <PublicHeader />

        <main class="public-container max-w-3xl py-10">
            <h1 class="text-3xl font-bold">Preguntas frecuentes</h1>
            <p class="mt-3 text-[var(--public-muted)]">
                Respuestas rápidas sobre cómo funciona Honducasa.
            </p>

            <div class="mt-8 space-y-3">
                <details
                    v-for="item in faqs"
                    :key="item.question"
                    class="group rounded-xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] p-5 open:pb-5"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold"
                    >
                        {{ item.question }}
                        <ChevronDown
                            class="size-5 shrink-0 text-[var(--public-muted)] transition group-open:rotate-180"
                        />
                    </summary>
                    <p class="mt-3 text-[var(--public-muted)]">
                        {{ item.answer }}
                    </p>
                </details>
            </div>
        </main>

        <PublicFooter />
    </div>
</template>
