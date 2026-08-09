<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, ImageOff, ImagePlus, Save } from '@lucide/vue';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import { computed, nextTick, reactive } from 'vue';
import { ref, watch } from 'vue';
import vueFilePond from 'vue-filepond';
import ListingPublishModal from '@/components/ListingPublishModal.vue';
import PropertyLocationPicker from '@/components/PropertyLocationPicker.vue';
import { index, store, update } from '@/routes/listings';
import { store as startStore } from '@/routes/listings/start';
import {
    destroy as destroyUpload,
    store as storeUpload,
} from '@/routes/listings/uploads';
import { dashboard as userDashboard } from '@/routes/user';

const FilePond = vueFilePond(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
);

type Location = {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
};
type PolygonGeometry = {
    type: 'Polygon';
    coordinates: [number, number][][];
};
type FormValue = string | number | boolean | PolygonGeometry | null;
interface ListingFormData {
    name: string | number;
    type: string | number;
    listing_type: string | number;
    price_amount: string | number;
    currency: string | number;
    deposit_amount: string | number;
    bedrooms: string | number;
    bathrooms: string | number;
    parking_spaces: string | number;
    interior_area_m2: string | number;
    lot_area_m2: string | number;
    year_built: string | number;
    furnishing: string | number;
    utilities_included: boolean;
    address_line: string | number;
    description: string | number;
    status: string | number;
}
type ListingPhoto = {
    id: number;
    url: string;
    name: string;
    size: number;
};
type SaveStatus = 'draft' | 'published';
type Listing = Record<string, FormValue> & {
    id: number;
    photos?: ListingPhoto[];
};
const props = defineProps<{
    listing: Listing | null;
    locations: Location[];
    oldInput: Record<string, FormValue>;
}>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const team = computed(() => page.props.currentTeam);
const action = computed(() => {
    if (props.listing) {
        return update.form({
            current_team: team.value!.slug,
            listing: props.listing.id,
        });
    }

    return team.value ? store.form(team.value.slug) : startStore.form();
});
const backUrl = computed(() =>
    team.value ? index.url(team.value.slug) : userDashboard().url,
);
const value = (
    key: string,
    fallback: string | number | boolean = '',
): string | number => {
    const currentValue =
        props.oldInput[key] ?? props.listing?.[key] ?? fallback;

    return typeof currentValue === 'boolean'
        ? Number(currentValue)
        : typeof currentValue === 'object'
          ? ''
          : currentValue;
};
const numericFallback = (key: string): string | number => {
    if (key === 'bathrooms') {
        return 1;
    }

    if (key === 'bedrooms' || key === 'parking_spaces') {
        return 0;
    }

    return '';
};
const locationMode = value(
    'location_mode',
    String(props.listing?.public_location_precision ?? 'approximate'),
) as 'exact' | 'approximate';
// New listings open over Tegucigalpa; the city itself is derived from wherever
// the publisher ends up dropping the pin.
const initialLatitude = Number(value('latitude', 14.0723));
const initialLongitude = Number(value('longitude', -87.1921));
const initialApproximateRadiusKm = Number(
    value(
        'approximate_radius_km',
        Number(props.listing?.approximate_radius_meters ?? 500) / 1_000,
    ),
);

// Real-time Precognition validation re-renders this form on every keystroke
// and step change, so editable fields must be reactively bound (v-model).
// A plain `:value="..."` binding gets forcibly reset by Vue on re-render
// because Vue always re-syncs the DOM `value` property to match the bound
// expression, wiping out whatever the user just typed.
const formData = reactive<ListingFormData>({
    name: value('name'),
    type: value('type', 'house'),
    listing_type: value('listing_type', 'rent'),
    price_amount: value('price_amount'),
    currency: value('currency', 'HNL'),
    deposit_amount: value('deposit_amount'),
    bedrooms: value('bedrooms', numericFallback('bedrooms')),
    bathrooms: value('bathrooms', numericFallback('bathrooms')),
    parking_spaces: value('parking_spaces', numericFallback('parking_spaces')),
    interior_area_m2: value(
        'interior_area_m2',
        numericFallback('interior_area_m2'),
    ),
    lot_area_m2: value('lot_area_m2', numericFallback('lot_area_m2')),
    year_built: value('year_built', numericFallback('year_built')),
    furnishing: value('furnishing', 'unfurnished'),
    utilities_included: Boolean(value('utilities_included')),
    address_line: value('address_line'),
    description: value('description'),
    status: value('status', 'draft'),
});

// --- Photo uploads (FilePond + Spatie Media Library) ---------------------

const csrfToken = (): string =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

const initialFiles = (props.listing?.photos ?? []).map((photo) => ({
    source: String(photo.id),
    options: {
        type: 'local' as const,
        file: {
            name: photo.name,
            size: photo.size,
            type: 'image/jpeg',
        },
    },
}));

const photoUrlById = new Map(
    (props.listing?.photos ?? []).map((photo) => [String(photo.id), photo.url]),
);

// Drives the "no photos means draft" notice and the publish modal. Advisory
// only — `SaveListingRequest` re-derives it from the submitted media ids.
const photoCount = ref(initialFiles.length);

const filePondServer = {
    process: (
        _field: string,
        file: Blob,
        _metadata: unknown,
        load: (id: string) => void,
        error: (message: string) => void,
    ) => {
        const body = new FormData();
        body.append('file', file);

        fetch(storeUpload().url, {
            method: 'POST',
            body,
            headers: { 'X-XSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    error(
                        tr(
                            'No se pudo subir la foto.',
                            'The photo failed to upload.',
                        ),
                    );

                    return;
                }

                load(await response.text());
            })
            .catch(() =>
                error(
                    tr(
                        'No se pudo subir la foto.',
                        'The photo failed to upload.',
                    ),
                ),
            );
    },
    revert: (id: string, load: () => void) => {
        fetch(destroyUpload(Number(id)).url, {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        }).finally(load);
    },
    load: (
        source: string,
        load: (file: Blob) => void,
        error: (message: string) => void,
    ) => {
        const url = photoUrlById.get(source);

        if (!url) {
            error(tr('Foto no encontrada.', 'Photo not found.'));

            return;
        }

        fetch(url)
            .then((response) => response.blob())
            .then(load)
            .catch(() => error(tr('Foto no encontrada.', 'Photo not found.')));
    },
};

// --- Multi-step wizard ---------------------------------------------------
//
// Only new listings are published through the wizard: it exists to walk a
// first-time landlord through an empty form. Editing an existing listing
// renders every section on a single page so a one-field fix doesn't require
// clicking through three steps.

const isWizard = computed(() => props.listing === null);

const steps = computed(() => [
    { id: 1, label: tr('Propiedad y ubicación', 'Property and location') },
    { id: 2, label: tr('Precio y detalles', 'Price and details') },
    { id: 3, label: tr('Fotos', 'Photos') },
]);

const stepFields: Record<number, string[]> = {
    1: [
        'name',
        'type',
        'listing_type',
        'location_id',
        'location_mode',
        'latitude',
        'longitude',
        'approximate_shape',
        'approximate_radius_km',
        'approximate_polygon',
        'address_line',
        'description',
    ],
    2: [
        'price_amount',
        'currency',
        'deposit_amount',
        'bedrooms',
        'bathrooms',
        'parking_spaces',
        'interior_area_m2',
        'lot_area_m2',
        'year_built',
        'furnishing',
        'utilities_included',
    ],
    3: ['images', 'status'],
};

const stepForField = (field: string): number => {
    if (field.startsWith('images.')) {
        return 3;
    }

    const step = Object.entries(stepFields).find(([, fields]) =>
        fields.includes(field.split('.')[0]),
    );

    return step ? Number(step[0]) : 1;
};

const initialErrorKeys = Object.keys(page.props.errors ?? {});
const currentStep = ref(
    initialErrorKeys.length
        ? Math.min(...initialErrorKeys.map(stepForField))
        : 1,
);

watch(
    () => page.props.errors,
    (errors) => {
        const keys = Object.keys(errors ?? {});

        if (keys.length && isWizard.value) {
            currentStep.value = Math.min(...keys.map(stepForField));
        }
    },
);

/** Every section is visible at once outside the wizard. */
const showsStep = (step: number): boolean =>
    !isWizard.value || currentStep.value === step;

const stepHasError = (step: number, errors: Record<string, string>): boolean =>
    Object.keys(errors).some((field) => stepForField(field) === step);

const goToStep = (step: number): void => {
    if (step <= currentStep.value) {
        currentStep.value = step;
    }
};

const nextStep = (): void => {
    currentStep.value = Math.min(currentStep.value + 1, steps.value.length);
};

const previousStep = (): void => {
    currentStep.value = Math.max(currentStep.value - 1, 1);
};

// Tracks only the Next button's own validation call, independent of the
// Form's shared `validating` flag (which also flips true/false for every
// per-field @change validation elsewhere on the page). Disabling Next off
// that shared flag meant a click landing while some unrelated field's
// validation was in flight got silently dropped by the disabled button.
const advancingStep = ref(false);

type ValidateFn = (options: {
    only: string[];
    onSuccess: () => void;
    onFinish?: () => void;
}) => void;

const advanceStep = (validate: ValidateFn): void => {
    advancingStep.value = true;
    validate({
        only: stepFields[currentStep.value],
        onSuccess: nextStep,
        onFinish: () => {
            advancingStep.value = false;
        },
    });
};

// Saving always goes through the draft-or-publish modal, so Enter must never
// submit the form straight from a field.
const handleEnterKey = (event: KeyboardEvent): void => {
    if ((event.target as HTMLElement).tagName === 'INPUT') {
        event.preventDefault();
    }
};

// --- Draft or publish ----------------------------------------------------

const publishModalOpen = ref(false);

const confirmSave = (status: SaveStatus, submit: () => void): void => {
    // A listing with no photos can only be a draft; the server enforces the
    // same rule, this just keeps the submitted value honest.
    formData.status = photoCount.value === 0 ? 'draft' : status;
    publishModalOpen.value = false;
    nextTick(submit);
};
</script>

<template>
    <Head
        :title="
            listing
                ? tr('Editar propiedad', 'Edit listing')
                : tr('Nueva propiedad', 'New listing')
        "
    />
    <div class="mx-auto max-w-5xl p-4 md:p-8">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700"
            ><ArrowLeft class="size-4" />{{
                team
                    ? tr('Mis propiedades', 'My listings')
                    : tr('Volver al panel', 'Back to dashboard')
            }}</Link
        >
        <div class="mt-6">
            <h1 class="text-3xl font-semibold">
                {{
                    listing
                        ? tr('Editar propiedad', 'Edit listing')
                        : tr('Publicar propiedad', 'List a property')
                }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{
                    listing
                        ? tr(
                              'Actualiza cualquier sección y guarda los cambios.',
                              'Update any section and save your changes.',
                          )
                        : tr(
                              'Completa la información y guarda como borrador o publica.',
                              'Complete the details and save as draft or publish.',
                          )
                }}
            </p>
        </div>

        <Form
            v-bind="action"
            v-slot="{ errors, processing, submit, validate }"
            enctype="multipart/form-data"
            class="mt-8 space-y-8"
            :validation-timeout="150"
            @keydown.enter="handleEnterKey"
        >
            <nav
                v-if="isWizard"
                class="flex items-center"
                :aria-label="
                    tr('Progreso de la publicación', 'Listing progress')
                "
            >
                <template v-for="(step, index) in steps" :key="step.id">
                    <button
                        type="button"
                        class="flex items-center gap-2.5 text-left"
                        :class="step.id > currentStep && 'cursor-default'"
                        :disabled="step.id > currentStep"
                        @click="goToStep(step.id)"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="[
                                step.id === currentStep
                                    ? 'bg-primary text-primary-foreground'
                                    : step.id < currentStep
                                      ? 'bg-primary/15 text-primary'
                                      : 'bg-muted text-muted-foreground',
                                stepHasError(step.id, errors) &&
                                    step.id !== currentStep &&
                                    '!bg-destructive/15 !text-destructive',
                            ]"
                        >
                            <Check
                                v-if="step.id < currentStep"
                                class="size-4"
                            />
                            <template v-else>{{ step.id }}</template>
                        </span>
                        <span
                            class="hidden text-sm font-semibold sm:inline"
                            :class="
                                step.id === currentStep
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                            >{{ step.label }}</span
                        >
                    </button>
                    <span
                        v-if="index < steps.length - 1"
                        class="mx-3 h-px flex-1"
                        :class="
                            step.id < currentStep
                                ? 'bg-primary/40'
                                : 'bg-border'
                        "
                    />
                </template>
            </nav>

            <div
                v-if="Object.keys(errors).length"
                role="alert"
                class="rounded-2xl border border-destructive/30 bg-destructive/10 p-5 text-destructive"
            >
                <p class="font-semibold">
                    {{
                        tr(
                            'Revisa los siguientes campos:',
                            'Please review the following fields:',
                        )
                    }}
                </p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="(message, field) in errors" :key="field">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <section
                v-show="showsStep(1)"
                class="grid gap-5 rounded-2xl border bg-card p-6 md:grid-cols-2"
            >
                <h2 class="text-xl font-semibold md:col-span-2">
                    {{ tr('Información principal', 'Main information') }}
                </h2>
                <label class="text-sm font-medium md:col-span-2"
                    >{{ tr('Título', 'Title')
                    }}<input
                        name="name"
                        v-model="formData.name"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('name')"
                    /><small class="text-destructive">{{
                        errors.name
                    }}</small></label
                ><label class="text-sm font-medium"
                    >{{ tr('Tipo', 'Type')
                    }}<select
                        name="type"
                        v-model="formData.type"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('type')"
                    >
                        <option value="house">Casa</option>
                        <option value="apartment">Apartamento</option>
                        <option value="condominium">Condominio</option>
                        <option value="townhouse">Townhouse</option>
                        <option value="studio">Estudio</option>
                        <option value="room">Habitación</option></select
                    ><small class="text-destructive">{{
                        errors.type
                    }}</small></label
                ><label class="text-sm font-medium"
                    >{{ tr('Operación', 'Listing type')
                    }}<select
                        name="listing_type"
                        v-model="formData.listing_type"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('listing_type')"
                    >
                        <option value="rent">
                            {{ tr('Alquiler', 'Rent') }}
                        </option>
                        <option value="buy">
                            {{ tr('Venta', 'Sale') }}
                        </option></select
                    ><small class="text-destructive">{{
                        errors.listing_type
                    }}</small></label
                >
            </section>

            <section
                v-show="showsStep(1)"
                class="grid gap-5 rounded-2xl border bg-card p-6 md:grid-cols-2"
            >
                <h2 class="text-xl font-semibold md:col-span-2">
                    {{
                        tr(
                            'Ubicación y descripción',
                            'Location and description',
                        )
                    }}
                </h2>
                <div class="md:col-span-2">
                    <PropertyLocationPicker
                        :locations="locations"
                        :initial-mode="locationMode"
                        :initial-latitude="initialLatitude"
                        :initial-longitude="initialLongitude"
                        :initial-approximate-radius-km="
                            initialApproximateRadiusKm
                        "
                        :visible="showsStep(1)"
                    />
                    <small class="text-destructive">{{
                        errors.location_id
                    }}</small>
                </div>
                <label class="text-sm font-medium md:col-span-2"
                    >{{
                        tr(
                            'Dirección exacta (privada)',
                            'Exact address (private)',
                        )
                    }}<input
                        name="address_line"
                        v-model="formData.address_line"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('address_line')"
                    /><small class="text-destructive">{{
                        errors.address_line
                    }}</small></label
                ><label class="text-sm font-medium md:col-span-2"
                    >{{ tr('Descripción', 'Description') }}
                    <span class="font-normal text-muted-foreground"
                        >({{ tr('opcional', 'optional') }})</span
                    ><textarea
                        name="description"
                        rows="6"
                        v-model="formData.description"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('description')"
                    /><small class="text-destructive">{{
                        errors.description
                    }}</small></label
                >
            </section>

            <div
                v-if="isWizard"
                v-show="currentStep === 1"
                class="flex justify-end"
            >
                <button
                    type="button"
                    :disabled="advancingStep"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                    @click="advanceStep(validate)"
                >
                    {{ tr('Siguiente', 'Next') }}
                </button>
            </div>

            <section
                v-show="showsStep(2)"
                class="grid gap-5 rounded-2xl border bg-card p-6 md:grid-cols-3"
            >
                <h2 class="text-xl font-semibold md:col-span-3">
                    {{ tr('Precio y características', 'Price and features') }}
                </h2>
                <label class="text-sm font-medium"
                    >{{ tr('Precio', 'Price')
                    }}<input
                        name="price_amount"
                        type="number"
                        min="1"
                        v-model="formData.price_amount"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('price_amount')"
                    /><small class="text-destructive">{{
                        errors.price_amount
                    }}</small></label
                ><label class="text-sm font-medium"
                    >Moneda<select
                        name="currency"
                        v-model="formData.currency"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('currency')"
                    >
                        <option>HNL</option>
                        <option>USD</option></select
                    ><small class="text-destructive">{{
                        errors.currency
                    }}</small></label
                ><label class="text-sm font-medium"
                    >{{ tr('Depósito', 'Deposit')
                    }}<input
                        name="deposit_amount"
                        type="number"
                        v-model="formData.deposit_amount"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('deposit_amount')"
                    /><small class="text-destructive">{{
                        errors.deposit_amount
                    }}</small></label
                ><label
                    v-for="field in [
                        {
                            n: 'bedrooms',
                            l: tr('Habitaciones', 'Bedrooms'),
                        },
                        { n: 'bathrooms', l: tr('Baños', 'Bathrooms') },
                        {
                            n: 'parking_spaces',
                            l: tr('Estacionamientos', 'Parking'),
                        },
                        { n: 'interior_area_m2', l: 'Área interior m²' },
                        { n: 'lot_area_m2', l: 'Terreno m²' },
                        {
                            n: 'year_built',
                            l: tr('Año de construcción', 'Year built'),
                        },
                    ] as const"
                    :key="field.n"
                    class="text-sm font-medium"
                    >{{ field.l
                    }}<input
                        :name="field.n"
                        type="number"
                        :step="field.n === 'bathrooms' ? 0.5 : 1"
                        v-model="formData[field.n]"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate(field.n)"
                    /><small class="text-destructive">{{
                        errors[field.n]
                    }}</small></label
                ><label class="text-sm font-medium"
                    >{{ tr('Amueblado', 'Furnishing')
                    }}<select
                        name="furnishing"
                        v-model="formData.furnishing"
                        class="mt-2 w-full rounded-xl border bg-background px-4 py-3"
                        @change="validate('furnishing')"
                    >
                        <option value="unfurnished">No amueblado</option>
                        <option value="semi_furnished">Semi amueblado</option>
                        <option value="furnished">Amueblado</option></select
                    ><small class="text-destructive">{{
                        errors.furnishing
                    }}</small></label
                ><label class="flex items-center gap-2 self-end pb-3 text-sm"
                    ><input
                        type="hidden"
                        name="utilities_included"
                        value="0"
                    /><input
                        type="checkbox"
                        name="utilities_included"
                        value="1"
                        v-model="formData.utilities_included"
                        @change="validate('utilities_included')"
                    />{{
                        tr('Servicios incluidos', 'Utilities included')
                    }}</label
                >
            </section>

            <div
                v-if="isWizard"
                v-show="currentStep === 2"
                class="flex justify-between"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border px-6 py-3 font-semibold"
                    @click="previousStep"
                >
                    {{ tr('Atrás', 'Back') }}
                </button>
                <button
                    type="button"
                    :disabled="advancingStep"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                    @click="advanceStep(validate)"
                >
                    {{ tr('Siguiente', 'Next') }}
                </button>
            </div>

            <section
                v-show="showsStep(3)"
                class="rounded-2xl border bg-card p-6"
            >
                <h2 class="flex items-center gap-2 text-xl font-semibold">
                    <ImagePlus class="size-5 text-blue-600" />{{
                        tr('Fotografías', 'Photos')
                    }}
                </h2>
                <FilePond
                    name="images[]"
                    class="mt-5"
                    :allow-multiple="true"
                    :max-files="10"
                    :accepted-file-types="[
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                    ]"
                    max-file-size="5MB"
                    :credits="false"
                    :label-idle="
                        tr(
                            'Arrastra tus fotos aquí o <span class=\'filepond--label-action\'>selecciónalas</span>',
                            'Drag photos here or <span class=\'filepond--label-action\'>browse</span>',
                        )
                    "
                    :server="filePondServer"
                    :files="initialFiles"
                    @updatefiles="photoCount = $event.length"
                />
                <small class="text-destructive">{{ errors.images }}</small>
                <p
                    class="mt-5 flex items-start gap-2.5 rounded-xl p-3 text-sm"
                    :class="
                        photoCount === 0
                            ? 'bg-amber-50 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200'
                            : 'bg-muted/40 text-muted-foreground'
                    "
                >
                    <ImageOff
                        v-if="photoCount === 0"
                        class="mt-0.5 size-4 shrink-0"
                    />
                    <Check v-else class="mt-0.5 size-4 shrink-0" />
                    <span>{{
                        photoCount === 0
                            ? tr(
                                  'No puedes publicar una propiedad sin fotos. Si la guardas así, quedará como borrador hasta que agregues al menos una.',
                                  'You cannot publish a listing without photos. Saving it now keeps it as a draft until you add at least one.',
                              )
                            : tr(
                                  'Ya puedes publicar esta propiedad.',
                                  'This listing is ready to publish.',
                              )
                    }}</span>
                </p>
            </section>

            <input type="hidden" name="status" :value="formData.status" />

            <div
                v-if="isWizard"
                v-show="currentStep === 3"
                class="flex justify-between"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border px-6 py-3 font-semibold"
                    @click="previousStep"
                >
                    {{ tr('Atrás', 'Back') }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                    @click="publishModalOpen = true"
                >
                    <Save class="size-5" />{{
                        processing
                            ? tr('Guardando…', 'Saving…')
                            : tr('Guardar propiedad', 'Save listing')
                    }}
                </button>
            </div>

            <div
                v-else
                class="sticky bottom-0 flex justify-end gap-3 border-t bg-background/90 py-4 backdrop-blur"
            >
                <Link
                    :href="backUrl"
                    class="inline-flex items-center gap-2 rounded-xl border px-6 py-3 font-semibold"
                    >{{ tr('Cancelar', 'Cancel') }}</Link
                >
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                    @click="publishModalOpen = true"
                >
                    <Save class="size-5" />{{
                        processing
                            ? tr('Guardando…', 'Saving…')
                            : tr('Guardar cambios', 'Save changes')
                    }}
                </button>
            </div>

            <ListingPublishModal
                v-model:open="publishModalOpen"
                :has-photos="photoCount > 0"
                :processing="processing"
                @confirm="confirmSave($event, submit)"
            />
        </Form>
    </div>
</template>
