<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Inbox,
    LayoutGrid,
    MessageSquareHeart,
    Package,
    Plane,
    Settings,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import FeedbackDialog from '@/components/FeedbackDialog.vue';
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
import { agencySettingsPages, agencySettingsTabs } from '@/lib/agencySettings';
import { dashboard } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { index as personsIndex } from '@/routes/persons';
import { index as feedbackIndex } from '@/routes/platform/feedback';
import { index as plansIndex } from '@/routes/platform/plans';
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

const feedbackOpen = ref(false);

const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) {
        return [
            { title: 'Acenteler', href: tenantsIndex(), icon: Building2 },
            { title: 'Paketler', href: plansIndex(), icon: Package },
            { title: 'Geri bildirimler', href: feedbackIndex(), icon: Inbox },
        ];
    }

    const role = page.props.auth.user?.role;
    const features = page.props.features ?? [];

    // Rehber: sadece kendi grupları (para / kimlik bilgisi yok).
    if (role === 'rehber') {
        return features.includes('passengers')
            ? [{ title: 'Gruplarım', href: toursIndex(), icon: Plane }]
            : [];
    }

    // Sayfa hangi bölüme ait? (alt sayfalarda da menü maddesi seçili görünsün)
    const component = page.component;
    const under = (...prefixes: string[]) =>
        prefixes.some((prefix) => component.startsWith(prefix));
    const staff = role === 'admin' || role === 'operasyon';

    // Menüde sadece günlük işler; tanımlar ve yönetim "Acente ayarları"nda.
    const items: NavItem[] = [
        { title: 'Ana Panel', href: dashboard(), icon: LayoutGrid },
    ];

    if (features.includes('passengers') && staff) {
        items.push(
            {
                title: 'Turlar',
                href: toursIndex(),
                icon: Plane,
                isActive: under(
                    'tours/',
                    'rooms/',
                    'buses/',
                    'flights/',
                    'registrations/',
                ),
            },
            {
                title: 'Yolcular',
                href: personsIndex(),
                icon: Users,
                isActive: under('persons/'),
            },
        );
    }

    if (features.includes('payments') && staff) {
        items.push({
            title: 'Tahsilat',
            href: collectionsIndex(),
            icon: Wallet,
        });
    }

    const settingsTabs = agencySettingsTabs(role, features);
    if (settingsTabs.length > 0) {
        items.push({
            title: 'Acente ayarları',
            href: settingsTabs[0].href,
            icon: Settings,
            isActive: under(...agencySettingsPages),
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
            <SidebarMenu v-if="!isSuperAdmin">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        tooltip="Görüşünü paylaş"
                        @click="feedbackOpen = true"
                    >
                        <MessageSquareHeart />
                        <span>Görüşünü paylaş</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
        <FeedbackDialog v-if="!isSuperAdmin" v-model:open="feedbackOpen" />
    </Sidebar>
    <slot />
</template>
