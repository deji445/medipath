<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    MessagesSquare,
    Settings2,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const isSuperAdmin = computed(
    () => page.props.auth.user.role === 'SUPER_ADMIN',
);
const isHospitalAdmin = computed(
    () => page.props.auth.user.role === 'HOSPITAL_ADMIN',
);
const mainNavItems = computed<NavItem[]>(() =>
    isSuperAdmin.value
        ? [
              { title: 'Hospital network', href: '/platform', icon: Building2 },
              {
                  title: 'Consultant requests',
                  href: '/platform/consultant-requests',
                  icon: MessagesSquare,
              },
          ]
        : [
              { title: 'Admissions', href: dashboard(), icon: ClipboardList },
              ...(isHospitalAdmin.value
                  ? [
                        {
                            title: 'Hospital settings',
                            href: '/hospital/settings',
                            icon: Settings2,
                        },
                        {
                            title: 'Staff access',
                            href: '/hospital/staff',
                            icon: Users,
                        },
                    ]
                  : []),
          ],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="isSuperAdmin ? '/platform' : dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                :items="mainNavItems"
                :label="
                    isSuperAdmin ? 'Platform workspace' : 'Hospital workspace'
                "
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
