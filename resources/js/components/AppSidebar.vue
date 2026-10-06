<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    Building2,
    Inbox,
    LayoutGrid,
    Package,
    Plane,
    Settings,
    Users,
    Wallet,
} from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';
import CommandPalette from '@/components/CommandPalette.vue';
import FeedbackPanel from '@/components/FeedbackPanel.vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { agencySettingsPages, agencySettingsTabs } from '@/lib/agencySettings';
import { dashboard } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { show as importPage } from '@/routes/person-import';
import {
    create as createPerson,
    index as personsIndex,
} from '@/routes/persons';
import { index as feedbackIndex } from '@/routes/platform/feedback';
import { index as plansIndex } from '@/routes/platform/plans';
import { index as tenantsIndex } from '@/routes/platform/tenants';
import { create as createTour, index as toursIndex } from '@/routes/tours';
import type { NavItem } from '@/types';

const page = usePage();

const isSuperAdmin = computed(
    () => page.props.auth.user?.role === 'super_admin',
);

const homeLink = computed(() =>
    isSuperAdmin.value ? tenantsIndex() : dashboard(),
);

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile } = useSidebar();

// Kullanıcı (alt köşe): baş harfler ve rolün adı.
const user = computed(() => page.props.auth.user);
const initials = computed(() =>
    (user.value?.name ?? '')
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr'),
);
const roleLabel = computed(
    () =>
        ({
            admin: 'Yönetici',
            operasyon: 'Operasyon',
            rehber: 'Rehber',
            super_admin: 'Platform yöneticisi',
        })[user.value?.role ?? ''] ?? '',
);

// Menü simgeleri tasarımdakiyle aynı çizgiler.
const iconFor = (title: string) =>
    ({
        'Ana Panel': 'grid',
        Turlar: 'plane',
        Gruplarım: 'plane',
        Yolcular: 'users',
        Tahsilat: 'wallet',
        'Acente ayarları': 'settings',
        Acenteler: 'grid',
        Paketler: 'settings',
        'Geri bildirimler': 'chat',
    })[title] ?? 'grid';

const hrefOf = (href: NonNullable<InertiaLinkProps['href']>) =>
    typeof href === 'string' ? href : href.url;

// Ctrl K araması
const paletteOpen = ref(false);
useEventListener(window, 'keydown', (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        paletteOpen.value = !paletteOpen.value;
    }
});

const actions = computed(() => {
    const role = user.value?.role;
    const features = page.props.features ?? [];
    const staff = role === 'admin' || role === 'operasyon';
    const list: { title: string; href: string }[] = [];

    if (staff && features.includes('passengers')) {
        list.push(
            { title: 'Yeni yolcu ekle', href: createPerson.url() },
            { title: "Excel'den yolcu aktar", href: importPage.url() },
            { title: 'Yeni tur oluştur', href: createTour.url() },
        );
    }

    if (staff && features.includes('payments')) {
        list.push({ title: 'Ödeme al', href: collectionsIndex.url() });
    }

    return list;
});

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
    <Sidebar collapsible="offcanvas" variant="inset">
        <!-- Tasarımdaki yan menü: logo, Ara (Ctrl K), menü, altta kullanıcı. -->
        <div class="mx mx-side">
            <Link class="brand" :href="homeLink">
                <span class="logo">M</span>
                <div>
                    <b>Marhal</b
                    ><span>{{
                        page.props.tenant?.name ??
                        (isSuperAdmin ? 'Platform' : '')
                    }}</span>
                </div>
            </Link>
            <button
                v-if="!isSuperAdmin"
                class="kbtn"
                type="button"
                @click="paletteOpen = true"
            >
                <MockIcon name="search" /><span>Ara</span><kbd>Ctrl K</kbd>
            </button>
            <nav class="nav">
                <Link
                    v-for="item in mainNavItems"
                    :key="item.title"
                    :href="item.href"
                    :class="{ on: item.isActive ?? isCurrentUrl(item.href) }"
                    @click="isMobile && setOpenMobile(false)"
                >
                    <MockIcon :name="iconFor(item.title)" />{{ item.title }}
                </Link>
            </nav>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        class="me"
                        type="button"
                        data-test="sidebar-menu-button"
                    >
                        <span class="av">{{ initials }}</span>
                        <div>
                            {{ user?.name }}<small>{{ roleLabel }}</small>
                        </div>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="min-w-56 rounded-lg"
                    side="top"
                    align="start"
                    :side-offset="4"
                >
                    <UserMenuContent v-if="user" :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </Sidebar>
    <CommandPalette
        v-if="!isSuperAdmin"
        v-model:open="paletteOpen"
        :screens="
            mainNavItems.map((i) => ({ title: i.title, href: hrefOf(i.href) }))
        "
        :actions="actions"
    />
    <FeedbackPanel v-if="!isSuperAdmin" />
    <slot />
</template>
