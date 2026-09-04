<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Edit3,
    ExternalLink,
    Eye,
    MessageCircle,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { create, destroy, edit } from '@/routes/listings';
import { show as messagesShow } from '@/routes/messages';
import {
    create as createPersonal,
    destroy as destroyPersonal,
    edit as editPersonal,
} from '@/routes/personal-listings';
import {
    preview as previewProperty,
    show as showProperty,
} from '@/routes/properties';

type ListingStatus = 'draft' | 'published' | 'paused' | 'archived';
type Listing = {
    id: number;
    slug: string;
    name: string | null;
    status: ListingStatus;
    listingType: string;
    priceAmount: number;
    currency: string;
    image: string | null;
    location: string;
    conversationsCount: number;
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type PaginatedListings = {
    data: Listing[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

defineProps<{ listings: PaginatedListings }>();

const page = usePage();
const locale = computed(() => page.props.locale);
const team = computed(() => page.props.currentTeam);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const money = (item: Listing): string =>
    new Intl.NumberFormat(locale.value === 'es' ? 'es-HN' : 'en-US', {
        style: 'currency',
        currency: item.currency,
        maximumFractionDigits: 0,
    }).format(item.priceAmount);
const statusLabel = (status: ListingStatus): string =>
    ({
        draft: tr('Borrador', 'Draft'),
        published: tr('Publicada', 'Published'),
        paused: tr('Pausada', 'Paused'),
        archived: tr('Archivada', 'Archived'),
    })[status];
const statusClasses = (status: ListingStatus): string =>
    ({
        draft: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        published:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        paused: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
        archived: 'bg-muted text-muted-foreground',
    })[status];
const paginationLabel = (label: string): string =>
    label
        .replace('&laquo; Previous', tr('Anterior', 'Previous'))
        .replace('Next &raquo;', tr('Siguiente', 'Next'));
const editUrl = (item: Listing): string =>
    team.value
        ? edit.url({ current_team: team.value.slug, listing: item.id })
        : editPersonal.url(item.id);
const messagesUrl = (item: Listing): string =>
    messagesShow.url(undefined, { query: { listing: item.id } });
const viewUrl = (item: Listing): string =>
    item.status === 'published'
        ? showProperty.url(item.slug)
        : previewProperty.url(item.slug);
const remove = (item: Listing): void => {
    if (confirm(tr('¿Eliminar esta propiedad?', 'Delete this listing?'))) {
        router.delete(
            team.value
                ? destroy.url({
                      current_team: team.value.slug,
                      listing: item.id,
                  })
                : destroyPersonal.url(item.id),
        );
    }
};
</script>

<template>
    <Head :title="tr('Mis propiedades', 'My listings')" />
    <div class="space-y-6 p-4 md:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-3xl font-semibold">
                    {{ tr('Mis propiedades', 'My listings') }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    {{
                        tr(
                            'Administra borradores y publicaciones activas.',
                            'Manage drafts and active listings.',
                        )
                    }}
                </p>
            </div>
            <Link
                :href="team ? create.url(team.slug) : createPersonal().url"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground"
            >
                <Plus class="size-5" />{{
                    tr('Nueva propiedad', 'New listing')
                }}
            </Link>
        </div>

        <div
            v-if="listings.data.length"
            class="divide-y overflow-hidden rounded-2xl border bg-card"
        >
            <article
                v-for="item in listings.data"
                :key="item.id"
                class="grid gap-4 p-4 sm:grid-cols-[11rem_minmax(0,1fr)] xl:grid-cols-[12rem_minmax(0,1fr)_auto] xl:items-center"
            >
                <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.name ?? ''"
                    class="h-40 w-full rounded-xl object-cover sm:h-32"
                />
                <div
                    v-else
                    class="grid h-40 w-full place-items-center rounded-xl bg-blue-50 text-blue-700 sm:h-32 dark:bg-blue-950 dark:text-blue-200"
                >
                    <Building2 class="size-10" />
                </div>

                <div class="min-w-0 self-center">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="statusClasses(item.status)"
                        >
                            {{ statusLabel(item.status) }}
                        </span>
                        <span class="text-sm text-muted-foreground">
                            {{ item.location }}
                        </span>
                    </div>
                    <h2 class="mt-2 truncate text-lg font-semibold">
                        {{ item.name }}
                    </h2>
                    <p class="mt-1 font-semibold">
                        {{ money(item)
                        }}<small v-if="item.listingType === 'rent'">
                            /{{ tr('mes', 'month') }}</small
                        >
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{
                            tr(
                                `${item.conversationsCount} conversaciones`,
                                `${item.conversationsCount} conversations`,
                            )
                        }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2 sm:col-start-2 xl:col-start-auto xl:max-w-md xl:justify-end"
                >
                    <Link
                        :href="messagesUrl(item)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-muted"
                    >
                        <MessageCircle class="size-4" />
                        {{ tr('Mensajes', 'Messages') }}
                        <span
                            v-if="item.conversationsCount"
                            class="rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
                        >
                            {{ item.conversationsCount }}
                        </span>
                    </Link>
                    <Link
                        :href="editUrl(item)"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-muted"
                    >
                        <Edit3 class="size-4" />{{ tr('Editar', 'Edit') }}
                    </Link>
                    <a
                        :href="viewUrl(item)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold hover:bg-muted"
                    >
                        <ExternalLink
                            v-if="item.status === 'published'"
                            class="size-4"
                        />
                        <Eye v-else class="size-4" />
                        {{
                            item.status === 'published'
                                ? tr('Ver publicada', 'View live')
                                : tr('Vista previa', 'Preview')
                        }}
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-destructive hover:bg-destructive/10"
                        :aria-label="tr('Eliminar propiedad', 'Delete listing')"
                        @click="remove(item)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </article>
        </div>

        <div
            v-if="listings.data.length && listings.last_page > 1"
            class="flex flex-col items-center gap-4"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    tr(
                        `Mostrando ${listings.from}–${listings.to} de ${listings.total}`,
                        `Showing ${listings.from}–${listings.to} of ${listings.total}`,
                    )
                }}
            </p>
            <nav
                class="flex flex-wrap justify-center gap-2"
                :aria-label="tr('Páginas de propiedades', 'Listing pages')"
            >
                <template v-for="link in listings.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="grid min-w-10 place-items-center rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:border-primary'
                        "
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ paginationLabel(link.label) }}
                    </Link>
                    <span
                        v-else
                        class="grid min-w-10 place-items-center rounded-xl border px-3 py-2 text-sm text-muted-foreground"
                    >
                        {{ paginationLabel(link.label) }}
                    </span>
                </template>
            </nav>
        </div>

        <div
            v-if="!listings.data.length"
            class="grid min-h-80 place-items-center rounded-2xl border border-dashed text-center"
        >
            <div>
                <Building2 class="mx-auto size-12 text-blue-600" />
                <h2 class="mt-4 text-xl font-semibold">
                    {{ tr('Aún no tienes propiedades', 'No listings yet') }}
                </h2>
            </div>
        </div>
    </div>
</template>
