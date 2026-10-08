<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell
        variant="sidebar"
        class="bg-slate-50 text-slate-950 [--background:#f8fafc] [--foreground:#0f172a] [--sidebar-accent-foreground:#115e59] [--sidebar-accent:#ccfbf1] [--sidebar-background:#ffffff] [--sidebar-border:#e2e8f0] [--sidebar-foreground:#334155] [--sidebar-primary-foreground:#ffffff] [--sidebar-primary:#0f766e]"
    >
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-w-0 overflow-x-clip bg-slate-50 text-slate-950 dark:bg-slate-50 dark:text-slate-950"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
