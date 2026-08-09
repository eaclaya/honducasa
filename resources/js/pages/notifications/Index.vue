<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Bell, CheckCheck, MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { show as messages } from '@/routes/messages';
import { read, read_all as readAll } from '@/routes/notifications';

type NotificationItem = {
    id: string;
    conversationId: number | null;
    targetUrl: string | null;
    propertyName: string | null;
    senderLabel: string | null;
    preview: string | null;
    isRead: boolean;
    createdAt: string;
};
defineProps<{ notifications: NotificationItem[] }>();
const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;
const unread = computed(() => page.props.unreadNotifications);
const openNotification = (item: NotificationItem): void => {
    if (item.isRead && item.conversationId) {
        router.visit(messages(item.conversationId).url);

        return;
    }

    if (item.isRead && item.targetUrl) {
        router.visit(item.targetUrl);

        return;
    }

    router.patch(
        read.url(item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                item.conversationId
                    ? router.visit(messages(item.conversationId).url)
                    : item.targetUrl && router.visit(item.targetUrl),
        },
    );
};
</script>

<template>
    <Head :title="tr('Notificaciones', 'Notifications')" />
    <div class="space-y-6 p-4 md:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold">
                    {{ tr('Notificaciones', 'Notifications') }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{
                        tr(
                            'Actualizaciones privadas de tus conversaciones y alertas.',
                            'Private updates from your conversations and alerts.',
                        )
                    }}
                </p>
            </div>
            <button
                v-if="unread"
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold"
                @click="router.post(readAll.url())"
            >
                <CheckCheck class="size-4" />{{
                    tr('Marcar todas como leídas', 'Mark all as read')
                }}
            </button>
        </div>
        <div
            v-if="notifications.length"
            class="overflow-hidden rounded-2xl border bg-card"
        >
            <button
                v-for="item in notifications"
                :key="item.id"
                type="button"
                class="flex w-full gap-4 border-b p-4 text-left last:border-b-0 hover:bg-muted/50"
                :class="!item.isRead ? 'bg-blue-50/70 dark:bg-blue-950/20' : ''"
                @click="openNotification(item)"
            >
                <span
                    class="grid size-11 shrink-0 place-items-center rounded-full bg-blue-100 text-blue-700"
                    ><MessageCircle class="size-5" /></span
                ><span class="min-w-0 flex-1"
                    ><span class="flex flex-wrap justify-between gap-2"
                        ><b>{{ item.senderLabel }}</b
                        ><small class="text-muted-foreground">{{
                            item.createdAt
                        }}</small></span
                    ><span class="mt-1 block text-sm font-medium">{{
                        item.propertyName
                    }}</span
                    ><span
                        class="mt-1 block truncate text-sm text-muted-foreground"
                        >{{ item.preview }}</span
                    ></span
                ><span
                    v-if="!item.isRead"
                    class="mt-2 size-2 shrink-0 rounded-full bg-blue-600"
                />
            </button>
        </div>
        <div
            v-else
            class="grid min-h-80 place-items-center rounded-2xl border border-dashed text-center text-muted-foreground"
        >
            <div>
                <Bell class="mx-auto size-12" />
                <h2 class="mt-4 text-xl font-semibold text-foreground">
                    {{ tr('Todo al día', 'All caught up') }}
                </h2>
                <p class="mt-1">
                    {{
                        tr(
                            'Las nuevas actualizaciones aparecerán aquí.',
                            'New updates will appear here.',
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
