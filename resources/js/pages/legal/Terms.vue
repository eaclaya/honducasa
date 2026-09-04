<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

const lastUpdated = '4 de septiembre de 2026';
</script>

<template>
    <Head title="Términos de servicio" />

    <div
        class="public-site min-h-screen bg-[var(--public-surface)] text-[var(--public-text)]"
    >
        <PublicHeader />

        <main class="public-container max-w-3xl py-10">
            <h1 class="text-3xl font-bold">Términos de servicio</h1>
            <p class="mt-2 text-sm text-[var(--public-muted)]">
                Última actualización: {{ lastUpdated }}
            </p>

            <div
                class="mt-8 space-y-8 text-[15px] leading-relaxed text-[var(--public-text)]"
            >
                <section>
                    <h2 class="text-xl font-semibold">
                        1. Aceptación de los términos
                    </h2>
                    <p class="mt-2">
                        Al crear una cuenta, publicar un anuncio o utilizar de
                        cualquier forma Honducasa ("la Plataforma"), aceptas
                        estos Términos de servicio. Si no estás de acuerdo, no
                        debes utilizar la Plataforma.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        2. Descripción del servicio
                    </h2>
                    <p class="mt-2">
                        Honducasa es un mercado en línea que conecta a
                        propietarios, agencias y personas interesadas en
                        alquilar o comprar propiedades en Honduras. Honducasa no
                        es propietaria, arrendadora, corredora ni parte de
                        ningún contrato de arrendamiento o compraventa entre
                        usuarios: únicamente facilita la publicación de anuncios
                        y la comunicación entre las partes.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        3. Registro y cuentas de usuario
                    </h2>
                    <p class="mt-2">
                        Puedes crear una cuenta con tu correo electrónico y una
                        contraseña, o mediante tu cuenta de Google. Eres
                        responsable de mantener la confidencialidad de tus
                        credenciales y de toda actividad que ocurra bajo tu
                        cuenta. Debes proporcionar información veraz y
                        mantenerla actualizada.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        4. Publicación de anuncios
                    </h2>
                    <p class="mt-2">
                        Al publicar una propiedad, declaras que tienes derecho a
                        ofrecerla en alquiler o venta y que la información,
                        fotografías y precio son exactos. Honducasa puede
                        revisar, moderar, marcar como borrador o retirar
                        cualquier anuncio que incumpla estos Términos, contenga
                        información falsa o engañosa, o sea reportado por otros
                        usuarios.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        5. Uso aceptable de la plataforma
                    </h2>
                    <p class="mt-2">Al usar Honducasa, te comprometes a no:</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-6">
                        <li>
                            Publicar contenido falso, fraudulento, difamatorio u
                            ofensivo, incluyendo lenguaje soez u obsceno en
                            anuncios o mensajes.
                        </li>
                        <li>
                            Acosar, amenazar o discriminar a otros usuarios.
                        </li>
                        <li>
                            Extraer datos de la Plataforma de forma automatizada
                            (scraping) sin autorización previa.
                        </li>
                        <li>
                            Suplantar la identidad de otra persona o entidad.
                        </li>
                        <li>
                            Utilizar la Plataforma para fines distintos a la
                            búsqueda, publicación o gestión de propiedades en
                            alquiler o venta.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        6. Mensajería y comunicación entre usuarios
                    </h2>
                    <p class="mt-2">
                        La mensajería dentro de la Plataforma existe para
                        coordinar visitas y consultas sobre propiedades.
                        Honducasa puede moderar automáticamente los mensajes
                        (por ejemplo, filtrando lenguaje inapropiado) y
                        conservar su contenido para fines de seguridad, soporte
                        y cumplimiento de estos Términos.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        7. Planes y suscripciones
                    </h2>
                    <p class="mt-2">
                        Algunas funciones, como publicar más de un número
                        limitado de anuncios, requieren un plan de suscripción
                        de pago una vez finalizado el período de prueba
                        gratuito. Los precios, límites y condiciones de cada
                        plan se muestran en la Plataforma antes de confirmar la
                        suscripción. Nos reservamos el derecho de modificar los
                        planes disponibles; los cambios de precio no afectan
                        retroactivamente a suscripciones ya activas bajo el plan
                        anterior.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        8. Propiedad intelectual
                    </h2>
                    <p class="mt-2">
                        El nombre, logotipo, diseño y software de Honducasa son
                        propiedad de sus titulares y están protegidos por las
                        leyes de propiedad intelectual aplicables. Al publicar
                        fotografías o contenido, otorgas a Honducasa una
                        licencia no exclusiva para mostrarlo dentro de la
                        Plataforma con el fin de operar el servicio.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        9. Exclusión de garantías y limitación de
                        responsabilidad
                    </h2>
                    <p class="mt-2">
                        Honducasa no garantiza la exactitud, disponibilidad ni
                        legalidad de los anuncios publicados por terceros, ni
                        actúa como intermediario en las transacciones entre
                        usuarios. El uso de la Plataforma es bajo tu propio
                        riesgo; te recomendamos verificar directamente cualquier
                        propiedad e identidad antes de acordar un arrendamiento
                        o compra.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        10. Suspensión y terminación
                    </h2>
                    <p class="mt-2">
                        Podemos suspender o cancelar tu cuenta si incumples
                        estos Términos, incluyendo la publicación de contenido
                        prohibido o el uso indebido de la Plataforma. Puedes
                        solicitar la eliminación de tu cuenta en cualquier
                        momento.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        11. Modificaciones a los términos
                    </h2>
                    <p class="mt-2">
                        Podemos actualizar estos Términos periódicamente. Si los
                        cambios son significativos, lo indicaremos en la
                        Plataforma. El uso continuado de Honducasa después de
                        una actualización implica la aceptación de los nuevos
                        Términos.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">12. Ley aplicable</h2>
                    <p class="mt-2">
                        Estos Términos se rigen por las leyes de la República de
                        Honduras.
                    </p>
                </section>
            </div>
        </main>

        <PublicFooter />
    </div>
</template>
