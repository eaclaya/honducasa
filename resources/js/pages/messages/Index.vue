<script setup lang="ts">
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    CircleCheck,
    Flag,
    MessageCircle,
    Send,
    ShieldCheck,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { show as messagesShow, store as sendMessage } from '@/routes/messages';
import { store as reportConversation } from '@/routes/messages/reports';
import { update as updateStatus } from '@/routes/messages/status';
import { show as propertyShow } from '@/routes/properties';

type ConversationSummary = {
    id: number;
    propertyName: string | null;
    propertyImage: string | null;
    counterpart: string;
    unreadCount: number;
    lastMessageAt: string | null;
    status: string;
    canUnblock: boolean;
};
type SelectedConversation = ConversationSummary & {
    isRenter: boolean;
    propertySlug: string;
    propertyPrice: number;
    propertyCurrency: string;
    listingType: 'rent' | 'buy';
    messages: Array<{
        id: number;
        body: string;
        isMine: boolean;
        sentAt: string;
    }>;
};

const props = defineProps<{
    conversations: ConversationSummary[];
    selected: SelectedConversation | null;
}>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const form = useForm({ body: '' });
const statusForm = useForm({ status: 'active' });
const reportForm = useForm({ reason: 'spam', details: '' });
const reportOpen = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const money = (amount: number, currency: string): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
const scrollToLatest = (): void => {
    nextTick(() =>
        messagesContainer.value?.scrollTo({
            top: messagesContainer.value.scrollHeight,
        }),
    );
};
const submit = (): void => {
    if (!props.selected) {
        return;
    }

    form.post(sendMessage.url(props.selected.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            scrollToLatest();
        },
    });
};
const setStatus = (status: 'active' | 'closed' | 'blocked'): void => {
    if (!props.selected) {
        return;
    }

    statusForm.status = status;
    statusForm.patch(updateStatus.url(props.selected.id), {
        preserveScroll: true,
    });
};
const report = (): void => {
    if (!props.selected) {
        return;
    }

    reportForm.post(reportConversation.url(props.selected.id), {
        preserveScroll: true,
        onSuccess: () => {
            reportOpen.value = false;
            reportForm.reset();
        },
    });
};

usePoll(5_000, { only: ['conversations', 'selected'] });
onMounted(scrollToLatest);
watch(() => props.selected?.messages.length, scrollToLatest);
</script>

<template>
    <Head :title="tr('Mensajes', 'Messages')" />
    <div class="flex min-h-0 flex-1 flex-col p-4 md:p-8">
        <div class="mb-5">
            <h1 class="text-3xl font-semibold">
                {{ tr('Mensajes', 'Messages') }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                {{
                    tr(
                        'Conversa dentro de HonduCasa sin compartir datos personales.',
                        'Chat inside HonduCasa without sharing personal contact details.',
                    )
                }}
            </p>
        </div>
        <div
            class="grid min-h-[70vh] overflow-hidden rounded-2xl border bg-card lg:grid-cols-[340px_1fr]"
        >
            <aside class="border-b lg:border-r lg:border-b-0">
                <div
                    v-if="conversations.length"
                    class="max-h-[70vh] overflow-y-auto p-2"
                >
                    <Link
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        :href="messagesShow(conversation.id).url"
                        class="flex gap-3 rounded-xl p-3 transition hover:bg-muted"
                        :class="
                            selected?.id === conversation.id
                                ? 'bg-blue-50 dark:bg-blue-950/30'
                                : ''
                        "
                    >
                        <img
                            v-if="conversation.propertyImage"
                            :src="conversation.propertyImage"
                            :alt="conversation.propertyName ?? ''"
                            class="size-14 rounded-xl object-cover"
                        />
                        <span
                            v-else
                            class="grid size-14 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700"
                            ><Building2 class="size-5"
                        /></span>
                        <span class="min-w-0 flex-1"
                            ><span class="flex justify-between gap-2"
                                ><b class="truncate text-sm">{{
                                    conversation.propertyName
                                }}</b
                                ><small class="text-muted-foreground">{{
                                    conversation.lastMessageAt
                                }}</small></span
                            ><span
                                class="mt-1 flex items-center justify-between gap-2 text-sm text-muted-foreground"
                                ><span class="truncate">{{
                                    conversation.counterpart
                                }}</span
                                ><span
                                    v-if="conversation.unreadCount"
                                    class="grid min-w-5 place-items-center rounded-full bg-blue-700 px-1.5 text-xs text-white"
                                    >{{ conversation.unreadCount }}</span
                                ></span
                            ></span
                        >
                    </Link>
                </div>
                <div
                    v-else
                    class="grid min-h-60 place-items-center p-6 text-center text-muted-foreground"
                >
                    <div>
                        <MessageCircle class="mx-auto size-9" />
                        <p class="mt-3">
                            {{
                                tr(
                                    'Aún no tienes conversaciones.',
                                    'You have no conversations yet.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </aside>
            <section
                v-if="selected"
                class="flex min-h-[560px] min-w-0 flex-col"
            >
                <header
                    class="flex flex-wrap items-center justify-between gap-4 border-b p-4"
                >
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">
                            {{ selected.propertyName }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ selected.counterpart }} ·
                            {{
                                money(
                                    selected.propertyPrice,
                                    selected.propertyCurrency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="propertyShow(selected.propertySlug).url"
                            class="shrink-0 rounded-xl border px-3 py-2 text-sm font-semibold"
                            >{{ tr('Ver propiedad', 'View listing') }}</Link
                        ><button
                            v-if="selected.status === 'active'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold"
                            @click="setStatus('closed')"
                        >
                            <CircleCheck class="size-4" />{{
                                tr('Cerrar', 'Close')
                            }}</button
                        ><button
                            v-if="selected.status === 'active'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold text-destructive"
                            @click="setStatus('blocked')"
                        >
                            <Ban class="size-4" />{{
                                tr('Bloquear', 'Block')
                            }}</button
                        ><button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold text-destructive"
                            @click="reportOpen = !reportOpen"
                        >
                            <Flag class="size-4" />{{
                                tr('Reportar', 'Report')
                            }}
                        </button>
                    </div>
                </header>
                <div
                    class="flex items-center gap-2 bg-blue-50 px-4 py-2 text-xs text-blue-800 dark:bg-blue-950/30 dark:text-blue-200"
                >
                    <ShieldCheck class="size-4" />{{
                        tr(
                            'Los datos de contacto y enlaces externos se bloquean para proteger a ambas partes.',
                            'Contact details and external links are blocked to protect both parties.',
                        )
                    }}
                </div>
                <form
                    v-if="reportOpen"
                    class="grid gap-3 border-b bg-destructive/5 p-4 sm:grid-cols-[180px_1fr_auto]"
                    @submit.prevent="report"
                >
                    <select
                        v-model="reportForm.reason"
                        class="rounded-xl border bg-background px-3 py-2"
                    >
                        <option value="spam">Spam</option>
                        <option value="harassment">
                            {{ tr('Acoso', 'Harassment') }}
                        </option>
                        <option value="fraud">
                            {{ tr('Fraude', 'Fraud') }}
                        </option>
                        <option value="contact_sharing">
                            {{ tr('Comparte contacto', 'Contact sharing') }}
                        </option>
                        <option value="other">
                            {{ tr('Otro', 'Other') }}
                        </option></select
                    ><input
                        v-model="reportForm.details"
                        class="rounded-xl border bg-background px-3 py-2"
                        :placeholder="
                            tr('Detalles opcionales', 'Optional details')
                        "
                    /><button
                        type="submit"
                        :disabled="reportForm.processing"
                        class="rounded-xl bg-destructive px-4 py-2 font-semibold text-white"
                    >
                        {{ tr('Enviar reporte', 'Submit report') }}
                    </button>
                </form>
                <div
                    ref="messagesContainer"
                    class="flex flex-1 flex-col gap-3 overflow-y-auto p-4 md:p-6"
                >
                    <div
                        v-for="message in selected.messages"
                        :key="message.id"
                        class="flex"
                        :class="
                            message.isMine ? 'justify-end' : 'justify-start'
                        "
                    >
                        <div
                            class="max-w-[78%] rounded-2xl px-4 py-3 text-sm"
                            :class="
                                message.isMine
                                    ? 'bg-blue-700 text-white'
                                    : 'bg-muted'
                            "
                        >
                            <p class="whitespace-pre-wrap">
                                {{ message.body }}
                            </p>
                            <p class="mt-1 text-right text-[10px] opacity-70">
                                {{ message.sentAt }}
                            </p>
                        </div>
                    </div>
                </div>
                <form
                    v-if="selected.status === 'active'"
                    class="border-t p-4"
                    @submit.prevent="submit"
                >
                    <div class="flex gap-2">
                        <textarea
                            v-model="form.body"
                            rows="2"
                            class="min-h-12 flex-1 resize-none rounded-xl border bg-background px-4 py-3 outline-none focus:border-blue-600"
                            :placeholder="
                                tr('Escribe un mensaje…', 'Write a message…')
                            "
                        /><button
                            type="submit"
                            :disabled="form.processing || !form.body.trim()"
                            class="grid size-12 shrink-0 place-items-center rounded-xl bg-blue-700 text-white disabled:opacity-50"
                        >
                            <Send class="size-5" />
                        </button>
                    </div>
                    <p
                        v-if="form.errors.body"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ form.errors.body }}
                    </p>
                </form>
                <div
                    v-else
                    class="flex items-center justify-between gap-3 border-t bg-muted/50 p-4"
                >
                    <p class="text-sm font-medium">
                        {{
                            selected.status === 'blocked'
                                ? tr(
                                      'Esta conversación está bloqueada.',
                                      'This conversation is blocked.',
                                  )
                                : tr(
                                      'Esta conversación está cerrada.',
                                      'This conversation is closed.',
                                  )
                        }}
                    </p>
                    <button
                        v-if="
                            selected.status === 'closed' || selected.canUnblock
                        "
                        type="button"
                        class="rounded-xl border bg-background px-4 py-2 text-sm font-semibold"
                        @click="setStatus('active')"
                    >
                        {{
                            selected.status === 'blocked'
                                ? tr('Desbloquear', 'Unblock')
                                : tr('Reabrir', 'Reopen')
                        }}
                    </button>
                </div>
            </section>
            <section
                v-else
                class="hidden place-items-center text-center text-muted-foreground lg:grid"
            >
                <div>
                    <MessageCircle class="mx-auto size-12" />
                    <p class="mt-4">
                        {{
                            tr(
                                'Selecciona una conversación.',
                                'Select a conversation.',
                            )
                        }}
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
