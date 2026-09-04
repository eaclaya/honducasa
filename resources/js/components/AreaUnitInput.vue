<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { areaToSquareMeters, squareMetersToArea } from '@/lib/areaUnits';
import type { AreaUnit } from '@/lib/areaUnits';

const props = withDefaults(
    defineProps<{
        modelValue: string | number;
        unit: AreaUnit;
        name?: string;
        min?: number;
        placeholder?: string;
    }>(),
    {
        name: undefined,
        min: 0,
        placeholder: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
    'update:unit': [value: AreaUnit];
    change: [];
}>();

const displayValue = ref<string | number>('');
let isUpdatingCanonicalValue = false;

const displayFromCanonical = (): string | number => {
    if (props.modelValue === '') {
        return '';
    }

    const squareMeters = Number(props.modelValue);

    return Number.isFinite(squareMeters)
        ? squareMetersToArea(squareMeters, props.unit)
        : '';
};

watch(
    () => [props.modelValue, props.unit] as const,
    () => {
        if (!isUpdatingCanonicalValue) {
            displayValue.value = displayFromCanonical();
        }
    },
    { immediate: true },
);

const updateValue = (): void => {
    if (displayValue.value === '') {
        emit('update:modelValue', '');

        return;
    }

    const value = Number(displayValue.value);

    if (!Number.isFinite(value)) {
        return;
    }

    isUpdatingCanonicalValue = true;
    emit('update:modelValue', areaToSquareMeters(value, props.unit));
    void nextTick(() => {
        isUpdatingCanonicalValue = false;
    });
};

const updateUnit = (event: Event): void => {
    const unit = (event.target as HTMLSelectElement).value as AreaUnit;
    emit('update:unit', unit);
};
</script>

<template>
    <div
        class="flex min-w-0 overflow-hidden rounded-xl border border-input bg-background text-base focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
    >
        <input v-if="name" type="hidden" :name="name" :value="modelValue" />
        <input
            v-model="displayValue"
            type="number"
            :min="min"
            step="any"
            :placeholder="placeholder"
            class="min-w-0 flex-1 appearance-none bg-transparent px-4 py-3 font-normal outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @input="updateValue"
            @change="emit('change')"
        />
        <select
            :value="unit"
            class="shrink-0 bg-transparent px-3 py-3 font-medium text-muted-foreground outline-none sm:px-4"
            :aria-label="$attrs['aria-label'] as string"
            @change="updateUnit"
        >
            <option value="m2">m²</option>
            <option value="vara2">varas²</option>
        </select>
    </div>
</template>
