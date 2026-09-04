<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicFooter from '@/components/PublicFooter.vue';
import PublicHeader from '@/components/PublicHeader.vue';

const lastUpdated = '4 de septiembre de 2026';
</script>

<template>
    <Head title="Política de privacidad" />

    <div
        class="public-site min-h-screen bg-[var(--public-surface)] text-[var(--public-text)]"
    >
        <PublicHeader />

        <main class="public-container max-w-3xl py-10">
            <h1 class="text-3xl font-bold">Política de privacidad</h1>
            <p class="mt-2 text-sm text-[var(--public-muted)]">
                Última actualización: {{ lastUpdated }}
            </p>

            <div
                class="mt-8 space-y-8 text-[15px] leading-relaxed text-[var(--public-text)]"
            >
                <section>
                    <h2 class="text-xl font-semibold">
                        1. Responsable del tratamiento
                    </h2>
                    <p class="mt-2">
                        Honducasa es responsable de tratar los datos personales
                        que recopila a través de la Plataforma, conforme a esta
                        Política de privacidad.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        2. Información que recopilamos
                    </h2>
                    <ul class="mt-2 list-disc space-y-1.5 pl-6">
                        <li>
                            <strong>Datos de cuenta:</strong> nombre, correo
                            electrónico y, si inicias sesión con Google, tu
                            nombre y foto de perfil asociados a esa cuenta.
                        </li>
                        <li>
                            <strong>Datos de anuncios:</strong> ubicación,
                            fotografías, precio y características de las
                            propiedades que publicas.
                        </li>
                        <li>
                            <strong>Mensajes:</strong> el contenido de las
                            conversaciones entre inquilinos y propietarios
                            dentro de la Plataforma.
                        </li>
                        <li>
                            <strong>Preferencias:</strong> búsquedas guardadas,
                            propiedades favoritas, idioma y moneda
                            seleccionados.
                        </li>
                        <li>
                            <strong>Datos técnicos:</strong> dirección IP, tipo
                            de dispositivo y navegador, y datos de uso de la
                            Plataforma.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        3. Cómo utilizamos tu información
                    </h2>
                    <p class="mt-2">Utilizamos tu información para:</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-6">
                        <li>Crear y gestionar tu cuenta.</li>
                        <li>
                            Mostrar tus anuncios y permitir que otros usuarios
                            los encuentren y te contacten.
                        </li>
                        <li>
                            Enviarte notificaciones sobre mensajes, alertas de
                            búsquedas guardadas y cambios en tu plan de
                            suscripción.
                        </li>
                        <li>
                            Moderar contenido y prevenir fraude, abuso o
                            incumplimientos de nuestros Términos de servicio.
                        </li>
                        <li>
                            Mejorar la Plataforma y solucionar problemas
                            técnicos.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        4. Con quién compartimos tu información
                    </h2>
                    <p class="mt-2">
                        No vendemos tus datos personales. Los compartimos
                        únicamente con:
                    </p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-6">
                        <li>
                            Otros usuarios, en la medida necesaria para
                            coordinar el alquiler o compra de una propiedad (por
                            ejemplo, tu nombre y mensajes en una conversación).
                        </li>
                        <li>
                            Proveedores de infraestructura y servicios que
                            operan en nuestro nombre, como alojamiento,
                            almacenamiento de imágenes, mapas y geolocalización,
                            envío de correo electrónico, y autenticación con
                            Google.
                        </li>
                        <li>
                            Autoridades competentes, cuando la ley lo requiera.
                        </li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        5. Cookies y almacenamiento local
                    </h2>
                    <p class="mt-2">
                        Utilizamos cookies de sesión y almacenamiento del
                        navegador para mantener tu sesión iniciada y recordar
                        tus preferencias de idioma y moneda. No utilizamos
                        cookies de publicidad de terceros.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        6. Conservación de datos
                    </h2>
                    <p class="mt-2">
                        Conservamos tu información mientras tu cuenta esté
                        activa y durante el tiempo adicional necesario para
                        cumplir obligaciones legales, resolver disputas o hacer
                        valer nuestros acuerdos. Puedes solicitar la eliminación
                        de tu cuenta en cualquier momento.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">7. Tus derechos</h2>
                    <p class="mt-2">
                        Puedes solicitarnos acceder, corregir o eliminar tus
                        datos personales, así como retirar tu consentimiento
                        cuando el tratamiento se base en él. Para ejercer estos
                        derechos, contáctanos por los medios indicados más
                        abajo.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        8. Seguridad de la información
                    </h2>
                    <p class="mt-2">
                        Aplicamos medidas técnicas y organizativas razonables
                        para proteger tu información contra acceso no
                        autorizado, pérdida o alteración. Ningún sistema es
                        completamente infalible, por lo que no podemos
                        garantizar seguridad absoluta.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">9. Menores de edad</h2>
                    <p class="mt-2">
                        Honducasa no está dirigida a menores de 18 años y no
                        recopilamos intencionalmente información de menores.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold">
                        10. Cambios a esta política
                    </h2>
                    <p class="mt-2">
                        Podemos actualizar esta Política de privacidad
                        periódicamente. Si los cambios son significativos, lo
                        indicaremos en la Plataforma.
                    </p>
                </section>
            </div>
        </main>

        <PublicFooter />
    </div>
</template>
