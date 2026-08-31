<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ImageOff,
    ImagePlus,
    Loader2,
    Save,
    Sparkles,
    X,
} from '@lucide/vue';
import type { FilePondFile } from 'filepond';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginImageTransform from 'filepond-plugin-image-transform';
import { computed, nextTick, reactive } from 'vue';
import { ref, watch } from 'vue';
import vueFilePond from 'vue-filepond';
import ListingPublishModal from '@/components/ListingPublishModal.vue';
import PropertyLocationPicker from '@/components/PropertyLocationPicker.vue';
import { index, store, update } from '@/routes/listings';
import {
    destroy as destroyUpload,
    enhance as enhanceUpload,
    enhancementStatus,
    store as storeUpload,
} from '@/routes/listings/uploads';
import {
    index as personalIndex,
    store as personalStore,
    update as personalUpdate,
} from '@/routes/personal-listings';

const FilePond = vueFilePond(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImageResize,
    FilePondPluginImageTransform,
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
    currencies: string[];
    oldInput: Record<string, FormValue>;
}>();
const page = usePage();
const locale = computed(() => page.props.locale);
const tr = (es: string, en: string): string =>
    locale.value === 'es' ? es : en;
const team = computed(() => page.props.currentTeam);
const action = computed(() => {
    if (props.listing) {
        return team.value
            ? update.form({
                  current_team: team.value.slug,
                  listing: props.listing.id,
              })
            : personalUpdate.form(props.listing.id);
    }

    return team.value ? store.form(team.value.slug) : personalStore.form();
});
const backUrl = computed(() =>
    team.value ? index.url(team.value.slug) : personalIndex().url,
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
    },
}));

const photoById = new Map(
    (props.listing?.photos ?? []).map((photo) => [String(photo.id), photo]),
);

// Drives the "no photos means draft" notice and the publish modal. Advisory
// only — `SaveListingRequest` re-derives it from the submitted media ids.
const photoCount = ref(initialFiles.length);
type PhotoCandidate = { id: number; url: string; name: string; size: number };
type EnhanceablePhoto = PhotoCandidate & { objectUrl: boolean };
type PhotoPondRef = {
    addFile: (source: string, options: object) => Promise<FilePondFile>;
    removeFile: (query: string) => void;
};

const photoPond = ref<PhotoPondRef | null>(null);
const pondFileIdByMediaId = new Map<number, string>();
const enhanceablePhotos = ref<EnhanceablePhoto[]>(
    (props.listing?.photos ?? []).map((photo) => ({
        ...photo,
        objectUrl: false,
    })),
);
const enhancementSource = ref<EnhanceablePhoto | null>(null);
const enhancedCandidate = ref<PhotoCandidate | null>(null);
const enhancingPhotoId = ref<number | null>(null);
const enhancementError = ref('');

const parseJson = async (
    response: Response,
): Promise<Record<string, unknown>> => {
    const contentType = response.headers.get('content-type') ?? '';

    return contentType.includes('application/json')
        ? ((await response.json()) as Record<string, unknown>)
        : {};
};

const waitForEnhancement = async (
    requestId: string,
): Promise<PhotoCandidate> => {
    for (let attempt = 0; attempt < 120; attempt++) {
        await new Promise((resolve) => window.setTimeout(resolve, 2000));

        const response = await fetch(enhancementStatus(requestId).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const body = await parseJson(response);

        if (!response.ok) {
            throw new Error(
                tr(
                    'No se pudo consultar el estado de la mejora.',
                    'The enhancement status could not be checked.',
                ),
            );
        }

        if (body.status === 'completed' && body.candidate) {
            return body.candidate as PhotoCandidate;
        }

        if (body.status === 'failed') {
            throw new Error(
                typeof body.message === 'string'
                    ? body.message
                    : tr(
                          'No se pudo mejorar la foto.',
                          'The photo could not be enhanced.',
                      ),
            );
        }
    }

    throw new Error(
        tr(
            'La mejora está tardando demasiado. Inténtalo de nuevo.',
            'The enhancement is taking too long. Please try again.',
        ),
    );
};

const enhancePhoto = async (photo: EnhanceablePhoto): Promise<void> => {
    enhancementSource.value = photo;
    enhancedCandidate.value = null;
    enhancementError.value = '';
    enhancingPhotoId.value = photo.id;

    try {
        const response = await fetch(enhanceUpload(photo.id).url, {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        const body = await parseJson(response);

        if (!response.ok) {
            throw new Error(
                tr(
                    'No se pudo iniciar la mejora de la foto.',
                    'The photo enhancement could not be started.',
                ),
            );
        }

        if (typeof body.request_id !== 'string') {
            throw new Error(
                tr(
                    'La respuesta de mejora no es válida.',
                    'The enhancement response is invalid.',
                ),
            );
        }

        enhancedCandidate.value = await waitForEnhancement(body.request_id);
    } catch (error) {
        enhancementError.value =
            error instanceof Error
                ? error.message
                : tr(
                      'No se pudo mejorar la foto.',
                      'The photo could not be enhanced.',
                  );
    } finally {
        enhancingPhotoId.value = null;
    }
};

const discardCandidate = async (): Promise<void> => {
    if (enhancingPhotoId.value !== null) {
        return;
    }

    if (enhancedCandidate.value) {
        await fetch(destroyUpload(enhancedCandidate.value.id).url, {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        });
    }

    enhancementSource.value = null;
    enhancedCandidate.value = null;
    enhancementError.value = '';
};

const useEnhancedPhoto = async (): Promise<void> => {
    const source = enhancementSource.value;
    const candidate = enhancedCandidate.value;

    if (!source || !candidate || !photoPond.value) {
        return;
    }

    photoById.set(String(candidate.id), candidate);
    const candidateFile = await photoPond.value.addFile(String(candidate.id), {
        type: 'local',
    });
    pondFileIdByMediaId.set(candidate.id, candidateFile.id);
    photoPond.value.removeFile(
        pondFileIdByMediaId.get(source.id) ?? String(source.id),
    );
    enhanceablePhotos.value = enhanceablePhotos.value.filter(
        (photo) => photo.id !== source.id,
    );
    enhancementSource.value = null;
    enhancedCandidate.value = null;
};

const onPhotoProcessed = (error: unknown, file: FilePondFile): void => {
    if (!error && file.serverId) {
        pondFileIdByMediaId.set(Number(file.serverId), file.id);
    }
};

const onPhotoRemoved = (_error: unknown, file: FilePondFile): void => {
    const mediaId = Number(file.serverId || file.source);
    const photo = enhanceablePhotos.value.find((item) => item.id === mediaId);

    if (photo?.objectUrl) {
        URL.revokeObjectURL(photo.url);
    }

    enhanceablePhotos.value = enhanceablePhotos.value.filter(
        (item) => item.id !== mediaId,
    );
    pondFileIdByMediaId.delete(mediaId);
};

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

                const id = await response.text();
                const url = URL.createObjectURL(file);
                enhanceablePhotos.value.push({
                    id: Number(id),
                    url,
                    name: file instanceof File ? file.name : 'photo.webp',
                    size: file.size,
                    objectUrl: true,
                });
                load(id);
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
        const photo = photoById.get(source);

        if (!photo) {
            error(tr('Foto no encontrada.', 'Photo not found.'));

            return;
        }

        fetch(photo.url)
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Photo failed to load.');
                }

                const blob = await response.blob();
                load(
                    new File([blob], photo.name, {
                        type: blob.type || 'image/jpeg',
                    }),
                );
            })
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
                        :placeholder="
                            isWizard
                                ? tr(
                                      'Ej. Apartamento moderno con vista a la ciudad',
                                      'E.g. Modern apartment with city views',
                                  )
                                : ''
                        "
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
                        :placeholder="
                            isWizard
                                ? tr(
                                      'Describe los espacios, las comodidades, el estado de la propiedad y lo que hace especial este lugar.',
                                      'Describe the spaces, amenities, condition, and what makes this property special.',
                                  )
                                : ''
                        "
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
                        <option
                            v-for="currency in currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option></select
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
                    ref="photoPond"
                    name="images[]"
                    class="listing-photo-pond mt-5"
                    :allow-multiple="true"
                    :allow-image-preview="true"
                    :allow-reorder="true"
                    :max-files="10"
                    :accepted-file-types="[
                        'image/png',
                        'image/jpeg',
                        'image/webp',
                    ]"
                    max-file-size="10MB"
                    :allow-image-resize="true"
                    image-resize-mode="contain"
                    :image-resize-target-width="2560"
                    :image-resize-target-height="2560"
                    :image-resize-upscale="false"
                    :allow-image-transform="true"
                    image-transform-output-mime-type="image/webp"
                    :image-transform-output-quality="82"
                    image-transform-output-quality-mode="always"
                    :image-preview-height="180"
                    style-button-remove-item-position="top right"
                    style-load-indicator-position="center bottom"
                    style-progress-indicator-position="center bottom"
                    :credits="false"
                    :label-idle="
                        tr(
                            'Arrastra tus fotos aquí o <span class=\'filepond--label-action\'>selecciónalas</span>',
                            'Drag photos here or <span class=\'filepond--label-action\'>browse</span>',
                        )
                    "
                    :server="filePondServer"
                    :files="initialFiles"
                    @processfile="onPhotoProcessed"
                    @removefile="onPhotoRemoved"
                    @updatefiles="photoCount = $event.length"
                />
                <div v-if="enhanceablePhotos.length" class="mt-5">
                    <div
                        class="mb-3 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30"
                    >
                        <Sparkles class="mt-0.5 size-5 shrink-0 text-primary" />
                        <div>
                            <p class="font-semibold">
                                {{
                                    tr(
                                        'Mejora tus fotos con IA',
                                        'Improve your photos with AI',
                                    )
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    tr(
                                        'La IA crea una versión mejorada. Siempre podrás comparar y elegir antes de reemplazar la original.',
                                        'AI creates an enhanced version. You will always compare and choose before the original is replaced.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="photo in enhanceablePhotos"
                            :key="photo.id"
                            class="overflow-hidden rounded-xl border bg-card"
                        >
                            <img
                                :src="photo.url"
                                :alt="photo.name"
                                class="aspect-[4/3] w-full object-cover"
                            />
                            <button
                                type="button"
                                :disabled="enhancingPhotoId !== null"
                                class="flex w-full items-center justify-center gap-2 border-t px-3 py-2 text-sm font-semibold text-primary hover:bg-muted disabled:opacity-50"
                                @click="enhancePhoto(photo)"
                            >
                                <Loader2
                                    v-if="enhancingPhotoId === photo.id"
                                    class="size-4 animate-spin"
                                />
                                <Sparkles v-else class="size-4" />
                                {{
                                    enhancingPhotoId === photo.id
                                        ? tr('Mejorando…', 'Enhancing…')
                                        : tr('Mejorar foto', 'Improve photo')
                                }}
                            </button>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        tr(
                            'Hasta 10 MB por foto. Las imágenes se comprimen automáticamente antes de subirlas.',
                            'Up to 10 MB per photo. Images are compressed automatically before upload.',
                        )
                    }}
                </p>
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

            <Teleport to="body">
                <div
                    v-if="enhancementSource"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
                    role="dialog"
                    aria-modal="true"
                >
                    <div
                        class="max-h-[95vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-background p-5 shadow-2xl sm:p-7"
                    >
                        <div
                            class="mb-5 flex items-start justify-between gap-4"
                        >
                            <div>
                                <h2 class="text-xl font-bold">
                                    {{
                                        tr(
                                            'Compara antes de elegir',
                                            'Compare before choosing',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        tr(
                                            'Tu foto original no cambiará hasta que elijas la versión mejorada.',
                                            'Your original will not change until you choose the enhanced version.',
                                        )
                                    }}
                                </p>
                            </div>
                            <button
                                type="button"
                                :disabled="enhancingPhotoId !== null"
                                class="rounded-full p-2 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :aria-label="tr('Cerrar', 'Close')"
                                @click="discardCandidate"
                            >
                                <X class="size-5" />
                            </button>
                        </div>
                        <div
                            v-if="enhancingPhotoId"
                            class="flex min-h-72 flex-col items-center justify-center gap-3 rounded-xl border bg-muted/30 text-center"
                        >
                            <Loader2 class="size-9 animate-spin text-primary" />
                            <p class="font-semibold">
                                {{
                                    tr(
                                        'Creando una mejora profesional…',
                                        'Creating a professional enhancement…',
                                    )
                                }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    tr(
                                        'Esto puede tardar hasta un par de minutos.',
                                        'This can take up to a couple of minutes.',
                                    )
                                }}
                            </p>
                        </div>
                        <p
                            v-else-if="enhancementError"
                            class="rounded-xl bg-destructive/10 p-4 text-destructive"
                        >
                            {{ enhancementError }}
                        </p>
                        <template v-else-if="enhancedCandidate">
                            <div class="grid gap-4 md:grid-cols-2">
                                <figure
                                    class="overflow-hidden rounded-xl border"
                                >
                                    <figcaption
                                        class="border-b px-4 py-2 font-semibold"
                                    >
                                        {{ tr('Original', 'Original') }}
                                    </figcaption>
                                    <img
                                        :src="enhancementSource.url"
                                        class="w-full object-contain"
                                        :alt="
                                            tr(
                                                'Foto original',
                                                'Original photo',
                                            )
                                        "
                                    />
                                </figure>
                                <figure
                                    class="overflow-hidden rounded-xl border border-primary"
                                >
                                    <figcaption
                                        class="border-b bg-primary/5 px-4 py-2 font-semibold text-primary"
                                    >
                                        {{
                                            tr('Mejorada con IA', 'AI enhanced')
                                        }}
                                    </figcaption>
                                    <img
                                        :src="enhancedCandidate.url"
                                        class="w-full object-contain"
                                        :alt="
                                            tr(
                                                'Foto mejorada',
                                                'Enhanced photo',
                                            )
                                        "
                                    />
                                </figure>
                            </div>
                            <div
                                class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                            >
                                <button
                                    type="button"
                                    class="rounded-xl border px-5 py-3 font-semibold"
                                    @click="discardCandidate"
                                >
                                    {{
                                        tr(
                                            'Conservar original',
                                            'Keep original',
                                        )
                                    }}</button
                                ><button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 font-semibold text-primary-foreground"
                                    @click="useEnhancedPhoto"
                                >
                                    <Sparkles class="size-4" />{{
                                        tr(
                                            'Usar foto mejorada',
                                            'Use enhanced photo',
                                        )
                                    }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </Teleport>

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

<style scoped>
:deep(.listing-photo-pond .filepond--item) {
    width: calc(50% - 0.5em);
}

@media (min-width: 640px) {
    :deep(.listing-photo-pond .filepond--item) {
        width: calc(33.333% - 0.5em);
    }
}

@media (min-width: 1024px) {
    :deep(.listing-photo-pond .filepond--item) {
        width: calc(25% - 0.5em);
    }
}
</style>
