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
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
    >
        <path
            d="M24 44S8 31.5 8 19.5a16 16 0 1 1 32 0C40 31.5 24 44 24 44Z"
            fill="currentColor"
            opacity="0.12"
        />
        <path
            d="M24 44S8 31.5 8 19.5a16 16 0 1 1 32 0C40 31.5 24 44 24 44Z"
            stroke="currentColor"
            stroke-width="2.6"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="M24 11.5v14M17 18.5h14"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
        />
        <path
            d="M14.5 31c3.2-3.1 6.6-3.5 9.8-1.2 3 2.1 6 1.5 9.2-2"
            stroke="currentColor"
            stroke-width="2.4"
            stroke-linecap="round"
        />
        <circle cx="14.5" cy="31" r="2.2" fill="currentColor" />
        <circle cx="33.5" cy="27.8" r="2.2" fill="currentColor" />
    </svg>
</template>
