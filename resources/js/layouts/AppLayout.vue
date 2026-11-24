<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import MediBuddy from '@/components/MediBuddy.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <MediBuddy />
</template>
