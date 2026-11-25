<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';
import MediBuddy from '@/components/MediBuddy.vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage();

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout
        :title="
            page.props.portal === 'platform'
                ? 'MediPath administration'
                : page.props.portal === 'hospital'
                  ? 'Hospital workspace'
                  : title
        "
        :description="
            page.props.portal === 'platform'
                ? 'Manage hospital tenants and platform operations.'
                : page.props.portal === 'hospital'
                  ? 'Sign in to your hospital’s admissions workspace.'
                  : description
        "
    >
        <slot />
    </AuthLayout>
    <MediBuddy />
</template>
