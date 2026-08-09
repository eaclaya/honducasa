<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    FilePenLine,
    MessageCircle,
    Radio,
    MailOpen,
} from '@lucide/vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { dashboard } from '@/routes';
import { show as messages } from '@/routes/messages';
import type { DashboardInvitation, Team } from '@/types';

type Metrics = {
    totalListings: number;
    publishedListings: number;
    draftListings: number;
    activeConversations: number;
    unreadMessages: number;
};
type RecentConversation = {
    id: number;
    propertyName: string | null;
    propertySlug: string;
    renterName: string;
    status: string;
    lastMessageAt: string | null;
};
defineProps<{
    pendingInvitations?: DashboardInvitation[];
    metrics: Metrics;
    recentConversations: RecentConversation[];
}>();
const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Dashboard" />
    <PendingInvitationsModal
        v-if="pendingInvitations?.length"
        :invitations="pendingInvitations"
    />
    <main class="space-y-7 p-4 md:p-7">
        <div>
            <h1 class="text-3xl font-semibold">
                {{ tr('Resumen de tu equipo', 'Your team overview') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Inventario y conversaciones que requieren atención.',
                        'Inventory and conversations requiring attention.',
                    )
                }}
            </p>
        </div>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="item in [
                    {
                        label: tr('Propiedades', 'Listings'),
                        value: metrics.totalListings,
                        icon: Building2,
                    },
                    {
                        label: tr('Publicadas', 'Published'),
                        value: metrics.publishedListings,
                        icon: Radio,
                    },
                    {
                        label: tr('Borradores', 'Drafts'),
                        value: metrics.draftListings,
                        icon: FilePenLine,
                    },
                    {
                        label: tr(
                            'Conversaciones activas',
                            'Active conversations',
                        ),
                        value: metrics.activeConversations,
                        icon: MessageCircle,
                    },
                    {
                        label: tr('Mensajes sin leer', 'Unread messages'),
                        value: metrics.unreadMessages,
                        icon: MailOpen,
                    },
                ]"
                :key="item.label"
                class="rounded-2xl border bg-card p-5 shadow-sm"
            >
                <component
                    :is="item.icon"
                    class="size-5 text-blue-700"
                /><strong class="mt-5 block text-3xl">{{ item.value }}</strong
                ><span class="text-sm text-muted-foreground">{{
                    item.label
                }}</span>
            </article>
        </section>
        <section class="overflow-hidden rounded-2xl border bg-card">
            <div class="flex items-center justify-between border-b p-5">
                <h2 class="text-xl font-semibold">
                    {{ tr('Conversaciones recientes', 'Recent conversations') }}
                </h2>
                <Link
                    :href="messages().url"
                    class="text-sm font-semibold text-blue-700"
                    >{{ tr('Ver todas', 'View all') }}</Link
                >
            </div>
            <div v-if="recentConversations.length">
                <Link
                    v-for="conversation in recentConversations"
                    :key="conversation.id"
                    :href="messages(conversation.id).url"
                    class="flex flex-wrap items-center gap-4 border-b p-5 last:border-b-0 hover:bg-muted/50"
                    ><span
                        class="grid size-11 place-items-center rounded-full bg-blue-100 text-blue-700"
                        ><MessageCircle class="size-5" /></span
                    ><span class="min-w-0 flex-1"
                        ><b class="block truncate">{{
                            conversation.propertyName
                        }}</b
                        ><small class="text-muted-foreground">{{
                            conversation.renterName
                        }}</small></span
                    ><span class="text-sm text-muted-foreground">{{
                        conversation.lastMessageAt
                    }}</span
                    ><span
                        class="rounded-full bg-muted px-3 py-1 text-xs font-semibold"
                        >{{ conversation.status }}</span
                    ></Link
                >
            </div>
            <p v-else class="p-8 text-center text-muted-foreground">
                {{ tr('Aún no hay conversaciones.', 'No conversations yet.') }}
            </p>
        </section>
    </main>
</template>
