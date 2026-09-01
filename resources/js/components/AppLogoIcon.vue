<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 240 240"
        :class="className"
        v-bind="$attrs"
    >
        <rect
            x="20"
            y="20"
            width="200"
            height="200"
            rx="48"
            ry="48"
            fill="#58C8FF"
        />
        <path
            d="M68 144 120 92l16 16V92h12v28l24 24"
            fill="none"
            stroke="#0F2C59"
            stroke-width="12"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
