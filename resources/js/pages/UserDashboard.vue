<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, Heart, MessageCircle, Search } from '@lucide/vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Button } from '@/components/ui/button';
import { index as favorites } from '@/routes/favorites';
import { show as messages } from '@/routes/messages';
import { index as rentals } from '@/routes/rentals';
import { index as savedSearches } from '@/routes/saved-searches';
import type { DashboardInvitation } from '@/types';

type RecentConversation = {
    id: number;
    propertyName: string;
    propertySlug: string;
    teamName: string;
    status: string;
    lastMessageAt: string | null;
};

type Metrics = {
    favorites: number;
    savedSearches: number;
    activeConversations: number;
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
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
        ],
    },
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
                {{ tr('Tu panel', 'Your dashboard') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Tus favoritos, búsquedas y conversaciones en un solo lugar.',
                        'Your favorites, searches, and conversations in one place.',
                    )
                }}
            </p>
        </div>

        <section class="grid gap-4 sm:grid-cols-3">
            <Link
                v-for="item in [
                    {
                        label: tr('Favoritos', 'Favorites'),
                        value: metrics.favorites,
                        icon: Heart,
                        href: favorites().url,
                    },
                    {
                        label: tr('Búsquedas guardadas', 'Saved searches'),
                        value: metrics.savedSearches,
                        icon: Search,
                        href: savedSearches().url,
                    },
                    {
                        label: tr(
                            'Conversaciones activas',
                            'Active conversations',
                        ),
                        value: metrics.activeConversations,
                        icon: MessageCircle,
                        href: messages().url,
                    },
                ]"
                :key="item.label"
                :href="item.href"
                class="rounded-2xl border bg-card p-5 shadow-sm transition hover:bg-muted/50"
            >
                <component :is="item.icon" class="size-5 text-blue-700" />
                <strong class="mt-5 block text-3xl">{{ item.value }}</strong>
                <span class="text-sm text-muted-foreground">{{
                    item.label
                }}</span>
            </Link>
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
                            conversation.teamName
                        }}</small></span
                    ><small class="text-muted-foreground">{{
                        conversation.lastMessageAt
                    }}</small>
                </Link>
            </div>
            <p v-else class="p-10 text-center text-muted-foreground">
                {{
                    tr(
                        'Aún no hay conversaciones. Explora propiedades y escribe a los anunciantes.',
                        'No conversations yet. Explore properties and message the listers.',
                    )
                }}
            </p>
        </section>

        <section
            class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border bg-card p-6 shadow-sm"
        >
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-xl font-semibold">
                    <Building2 class="size-5 text-blue-700" />
                    {{
                        tr(
                            '¿Quieres publicar una propiedad?',
                            'Want to list a property?',
                        )
                    }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        tr(
                            'Crea tu equipo para comenzar a publicar y recibir mensajes de interesados.',
                            'Create your team to start publishing and receiving messages from interested renters.',
                        )
                    }}
                </p>
            </div>
            <div class="flex gap-3">
                <Button variant="outline" as-child>
                    <Link :href="rentals().url">{{
                        tr('Explorar propiedades', 'Explore properties')
                    }}</Link>
                </Button>
                <CreateTeamModal publish>
                    <Button data-test="become-landlord-button">
                        {{ tr('Publicar propiedad', 'List a property') }}
                    </Button>
                </CreateTeamModal>
            </div>
        </section>
    </main>
</template>
