<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle, LocateFixed, MapPin } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { search as searchLocations } from '@/routes/locations';

type LocationSuggestion = {
    id: number;
    name: string;
    type: string;
    context: string;
    listingCount: number;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        locale: string;
        variant?: 'hero' | 'results';
        placeholder?: string;
        showNearMe?: boolean;
        locating?: boolean;
        locationError?: string;
    }>(),
    {
        variant: 'results',
        placeholder: '',
        showNearMe: false,
        locating: false,
        locationError: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [value: string];
    nearby: [];
    input: [];
}>();

const http = useHttp<{ q: string }, { data: LocationSuggestion[] }>({ q: '' });
const suggestions = ref<LocationSuggestion[]>([]);
const open = ref(false);
const activeIndex = ref(-1);
const searched = ref(false);
let debounceTimer: number | undefined;

const tr = (es: string, en: string): string =>
    props.locale === 'es' ? es : en;

const typeLabels: Record<string, [string, string]> = {
    department: ['Departamento', 'Department'],
    municipality: ['Municipio', 'Municipality'],
    city: ['Ciudad', 'City'],
    neighborhood: ['Barrio o colonia', 'Neighborhood'],
    development: ['Residencial', 'Development'],
};

const typeLabel = (type: string): string => {
    const labels = typeLabels[type] ?? ['Ubicación', 'Location'];

    return tr(labels[0], labels[1]);
};

const controlClasses = computed(() =>
    props.variant === 'hero'
        ? 'flex h-full items-center gap-3 rounded-[10px] px-6 py-3'
        : 'flex h-full items-center gap-3 rounded-[10px] px-5 py-3',
);

const popupPositionClasses = computed(() =>
    props.variant === 'hero'
        ? '-left-2 w-[calc(100%+0.5rem)]'
        : 'left-0 w-full',
);

const popupContentPaddingClasses = computed(() =>
    props.variant === 'hero' ? 'px-8' : 'px-5',
);

const updateSuggestions = (value: string = props.modelValue): void => {
    window.clearTimeout(debounceTimer);
    const query = value.trim();

    if (query.length < 2) {
        http.cancel();
        suggestions.value = [];
        activeIndex.value = -1;
        searched.value = false;

        return;
    }

    debounceTimer = window.setTimeout(() => {
        http.cancel();
        http.q = query;
        http.get(searchLocations.url(), {
            onSuccess: (response) => {
                suggestions.value = response.data;
                activeIndex.value = -1;
                searched.value = true;
                open.value = true;
            },
        }).catch(() => undefined);
    }, 250);
};

const onInput = (event: Event): void => {
    const value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', value);
    emit('input');
    open.value = true;
    updateSuggestions(value);
};

const selectSuggestion = (suggestion: LocationSuggestion): void => {
    emit('update:modelValue', suggestion.name);
    open.value = false;
    suggestions.value = [];
    emit('select', suggestion.name);
};

const selectActive = (event: KeyboardEvent): void => {
    const suggestion = suggestions.value[activeIndex.value];

    if (suggestion !== undefined) {
        event.preventDefault();
        selectSuggestion(suggestion);
    }
};

const moveActive = (amount: number): void => {
    if (suggestions.value.length === 0) {
        return;
    }

    activeIndex.value =
        (activeIndex.value + amount + suggestions.value.length) %
        suggestions.value.length;
};

const close = (): void => {
    window.setTimeout(() => {
        open.value = false;
    }, 150);
};

onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);
    http.cancel();
});
</script>

<template>
    <div class="relative min-w-0">
        <label :class="controlClasses">
            <MapPin class="size-5 shrink-0 text-blue-700" />
            <span class="min-w-0 flex-1">
                <span
                    v-if="variant === 'hero'"
                    class="block text-xs font-bold text-stone-600"
                    >{{ tr('Dónde', 'Where') }}</span
                >
                <input
                    :value="modelValue"
                    class="w-full bg-transparent text-sm font-semibold text-[var(--public-text)] outline-none placeholder:text-[var(--public-muted)]"
                    :placeholder="placeholder"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="open"
                    @input="onInput"
                    @focus="
                        open = true;
                        updateSuggestions();
                    "
                    @blur="close"
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter="selectActive"
                    @keydown.esc="open = false"
                />
            </span>
            <LoaderCircle
                v-if="http.processing"
                class="size-4 shrink-0 animate-spin text-blue-700"
            />
        </label>

        <div
            v-if="
                open &&
                (showNearMe ||
                    suggestions.length > 0 ||
                    http.processing ||
                    searched)
            "
            class="absolute top-[calc(100%+0.5rem)] z-50 min-w-72 overflow-hidden rounded-2xl border border-[var(--public-border)] bg-[var(--public-surface-raised)] text-[var(--public-text)] shadow-xl"
            :class="popupPositionClasses"
            role="listbox"
        >
            <button
                v-if="showNearMe"
                type="button"
                class="flex w-full items-center gap-3 border-b border-stone-200 py-3 text-left transition hover:bg-blue-50 disabled:cursor-wait disabled:opacity-70"
                :class="popupContentPaddingClasses"
                :disabled="locating"
                @mousedown.prevent
                @click="emit('nearby')"
            >
                <LoaderCircle
                    v-if="locating"
                    class="size-5 animate-spin text-blue-700"
                />
                <LocateFixed v-else class="size-5 text-blue-700" />
                <span>
                    <strong class="block text-sm">{{
                        locating
                            ? tr(
                                  'Obteniendo ubicación…',
                                  'Getting your location…',
                              )
                            : tr('Buscar cerca de mí', 'Search near me')
                    }}</strong>
                    <small class="text-stone-500">{{
                        tr(
                            'Propiedades dentro de 2 km',
                            'Properties within 2 km',
                        )
                    }}</small>
                </span>
            </button>

            <button
                v-for="(suggestion, index) in suggestions"
                :key="suggestion.id"
                type="button"
                class="flex w-full items-center justify-between gap-4 border-b border-stone-100 py-3 text-left last:border-b-0 hover:bg-blue-50"
                :class="[
                    popupContentPaddingClasses,
                    { 'bg-blue-50': activeIndex === index },
                ]"
                role="option"
                :aria-selected="activeIndex === index"
                @mousedown.prevent
                @mouseenter="activeIndex = index"
                @click="selectSuggestion(suggestion)"
            >
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold">
                        {{ suggestion.name
                        }}<template v-if="suggestion.context">, </template>
                        <span v-if="suggestion.context" class="font-normal">{{
                            suggestion.context
                        }}</span>
                    </span>
                    <span
                        class="mt-0.5 flex items-center gap-1 text-xs text-stone-500"
                    >
                        <MapPin class="size-3.5" />
                        {{ typeLabel(suggestion.type) }}
                    </span>
                </span>
                <span
                    v-if="suggestion.listingCount > 0"
                    class="shrink-0 text-sm text-stone-500"
                >
                    {{ suggestion.listingCount }}
                </span>
            </button>

            <p
                v-if="searched && !http.processing && suggestions.length === 0"
                class="py-4 text-sm text-stone-500"
                :class="popupContentPaddingClasses"
            >
                {{ tr('No encontramos ubicaciones.', 'No locations found.') }}
            </p>

            <p
                v-if="locationError"
                class="border-t border-stone-200 py-3 text-xs text-red-600"
                :class="popupContentPaddingClasses"
                role="alert"
            >
                {{ locationError }}
            </p>
        </div>
    </div>
</template>
