<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, LayoutGrid, Plane, Users, Wallet } from '@lucide/vue';
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
import { index as collectionsIndex } from '@/routes/collections';
import { index as personsIndex } from '@/routes/persons';
import { index as tenantsIndex } from '@/routes/platform/tenants';
import { index as toursIndex } from '@/routes/tours';
import type { NavItem } from '@/types';

const page = usePage();

const isSuperAdmin = computed(
    () => page.props.auth.user?.role === 'super_admin',
);

const homeLink = computed(() =>
    isSuperAdmin.value ? tenantsIndex() : dashboard(),
);

const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) {
        return [{ title: 'Acenteler', href: tenantsIndex(), icon: Building2 }];
    }

    const role = page.props.auth.user?.role;
    const features = page.props.features ?? [];
    const items: NavItem[] = [
        { title: 'Ana Panel', href: dashboard(), icon: LayoutGrid },
    ];

    if (
        features.includes('passengers') &&
        (role === 'admin' || role === 'operasyon')
    ) {
        items.push(
            { title: 'Turlar', href: toursIndex(), icon: Plane },
            { title: 'Yolcular', href: personsIndex(), icon: Users },
        );
    }

    if (
        features.includes('payments') &&
        (role === 'admin' || role === 'operasyon')
    ) {
        items.push({
            title: 'Tahsilat',
            href: collectionsIndex(),
            icon: Wallet,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeLink">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
