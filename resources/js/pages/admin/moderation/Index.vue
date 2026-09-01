<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ShieldAlert, XCircle } from '@lucide/vue';
import { update } from '@/routes/admin/moderation';
import { show as messages } from '@/routes/messages';

type Report = {
    id: number;
    reason: string;
    details: string | null;
    status: string;
    createdAt: string;
    reporter: string;
    conversationId: number;
    conversationStatus: string;
    propertyName: string | null;
    teamName: string;
    renterName: string;
};
type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};
defineProps<{ reports: Paginated<Report> }>();
const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const resolve = (
    report: Report,
    status: 'actioned' | 'dismissed',
    conversationStatus: 'active' | 'blocked',
): void =>
    router.patch(
        update.url(report.id),
        { status, conversation_status: conversationStatus },
        { preserveScroll: true },
    );
</script>

<template>
    <Head :title="tr('Moderación', 'Moderation')" />
    <div class="space-y-6 p-4 md:p-8">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold">
                <ShieldAlert class="size-8 text-amber-600" />{{
                    tr('Moderación', 'Moderation')
                }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Revisa reportes sin exponer información de contacto.',
                        'Review reports without exposing contact information.',
                    )
                }}
            </p>
        </div>
        <div v-if="reports.data.length" class="space-y-4">
            <article
                v-for="report in reports.data"
                :key="report.id"
                class="rounded-2xl border bg-card p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-bold uppercase"
                                :class="
                                    report.status === 'pending'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{ report.status }}</span
                            ><b>{{ report.reason.replaceAll('_', ' ') }}</b
                            ><small class="text-muted-foreground">{{
                                report.createdAt
                            }}</small>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold">
                            {{ report.propertyName }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ report.renterName }} ↔ {{ report.teamName }} ·
                            {{ tr('Reportado por', 'Reported by') }}
                            {{ report.reporter }}
                        </p>
                        <p
                            v-if="report.details"
                            class="mt-3 rounded-xl bg-muted p-3 text-sm"
                        >
                            {{ report.details }}
                        </p>
                    </div>
                    <Link
                        :href="messages(report.conversationId).url"
                        class="rounded-xl border px-3 py-2 text-sm font-semibold"
                        >{{ tr('Ver conversación', 'View conversation') }}</Link
                    >
                </div>
                <div
                    v-if="report.status === 'pending'"
                    class="mt-4 flex flex-wrap gap-2 border-t pt-4"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-destructive px-4 py-2 text-sm font-semibold text-white"
                        @click="resolve(report, 'actioned', 'blocked')"
                    >
                        <XCircle class="size-4" />{{
                            tr('Confirmar y bloquear', 'Confirm and block')
                        }}</button
                    ><button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold"
                        @click="resolve(report, 'dismissed', 'active')"
                    >
                        <CheckCircle2 class="size-4" />{{
                            tr('Descartar y reabrir', 'Dismiss and reopen')
                        }}
                    </button>
                </div>
            </article>
        </div>
        <div
            v-else
            class="grid min-h-80 place-items-center rounded-2xl border border-dashed text-center text-muted-foreground"
        >
            <div>
                <CheckCircle2 class="mx-auto size-12 text-emerald-600" />
                <h2 class="mt-4 text-xl font-semibold text-foreground">
                    {{ tr('No hay reportes', 'No reports') }}
                </h2>
            </div>
        </div>
    </div>
</template>
