<script setup lang="ts">
import AppBackdrop from '@/components/AppBackdrop.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
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
    <AppBackdrop />
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <!-- Tasarımda üst başlık çubuğu yok (kırıntı yolu ekranın başlığında); telefonda yalnız menü düğmesi. -->
            <div class="flex h-12 items-center px-3 md:hidden">
                <SidebarTrigger />
            </div>
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
