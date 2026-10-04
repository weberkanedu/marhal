<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { agencySettingsTabs } from '@/lib/agencySettings';
import { toUrl } from '@/lib/utils';

/**
 * "Acente ayarları": acente bilgileri, personel, oteller, araç tipleri, erişim kayıtları tek yerde, sekmeli.
 * Sekmeler role ve pakete göre süzülür (operasyon: oteller + araç tipleri).
 */
const page = usePage();
const tabs = computed(() =>
    agencySettingsTabs(page.props.auth.user?.role, page.props.features ?? []),
);
const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex h-full flex-1 flex-col">
        <div class="px-4 pt-4">
            <h1 class="text-xl font-semibold tracking-tight">
                Acente ayarları
            </h1>
            <p class="text-sm text-muted-foreground">
                Sık değişmeyen tanımlar ve yönetim işleri.
            </p>
            <nav
                class="mt-3 -mb-px flex gap-1 overflow-x-auto border-b"
                aria-label="Acente ayarları"
            >
                <Link
                    v-for="tab in tabs"
                    :key="toUrl(tab.href)"
                    :href="tab.href"
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors"
                    :class="
                        isCurrentOrParentUrl(tab.href)
                            ? 'border-primary font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    :aria-current="
                        isCurrentOrParentUrl(tab.href) ? 'page' : undefined
                    "
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.title }}
                </Link>
            </nav>
        </div>
        <slot />
    </div>
</template>
