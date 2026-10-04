<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';
import { update } from '@/routes/theme';

type ThemeOption = { value: string; label: string };

const props = defineProps<{
    theme: string;
    themes: ThemeOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Görünüm', href: edit() }],
    },
});

// Önizleme kartları: temanın kendi renkleriyle küçük bir ekran taslağı.
const swatches: Record<
    string,
    {
        sidebar: string;
        bg: string;
        primary: string;
        accent: string;
        font: string;
        description: string;
    }
> = {
    haremeyn: {
        sidebar: '#0e3b2e',
        bg: '#fbf9f4',
        primary: '#0e3b2e',
        accent: '#d4af37',
        font: "'Fraunces', serif",
        description: 'Zümrüt yeşili ve altın; zarif başlıklar',
    },
    kurumsal: {
        sidebar: '#ffffff',
        bg: '#f6f8fb',
        primary: '#1e3a5f',
        accent: '#7fa7d9',
        font: "'Inter', sans-serif",
        description: 'Lacivert ve beyaz; sade iş yazılımı',
    },
    kum: {
        sidebar: '#efe5d6',
        bg: '#f7f1e8',
        primary: '#b5651d',
        accent: '#e08a4a',
        font: "'Plus Jakarta Sans', sans-serif",
        description: 'Sıcak bej ve bakır; yumuşak köşeler',
    },
};

const selected = ref(props.theme);

function choose(value: string): void {
    selected.value = value;
    // Anında uygula, sonra kaydet (her cihazda aynı tema).
    document.documentElement.dataset.theme = value;
    router.patch(update.url(), { theme: value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Görünüm" />

    <h1 class="sr-only">Görünüm ayarları</h1>

    <div class="space-y-8">
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Tema"
                description="Renk ve yazı stili. Seçiminiz hesabınıza kaydedilir; her cihazda aynı görünür."
            />
            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    v-for="option in themes"
                    :key="option.value"
                    type="button"
                    class="group overflow-hidden rounded-xl border-2 text-left transition-colors"
                    :class="
                        selected === option.value
                            ? 'border-primary'
                            : 'border-border hover:border-muted-foreground/40'
                    "
                    @click="choose(option.value)"
                >
                    <div
                        class="flex h-20"
                        :style="{ background: swatches[option.value]?.bg }"
                    >
                        <div
                            class="w-1/4 border-r"
                            :style="{
                                background: swatches[option.value]?.sidebar,
                            }"
                        />
                        <div class="flex flex-1 flex-col gap-1.5 p-2.5">
                            <div
                                class="h-2 w-2/3 rounded-full"
                                :style="{
                                    background: swatches[option.value]?.primary,
                                }"
                            />
                            <div class="h-1.5 w-1/2 rounded-full bg-black/10" />
                            <div class="mt-auto flex gap-1">
                                <div
                                    class="h-3 w-8 rounded"
                                    :style="{
                                        background:
                                            swatches[option.value]?.primary,
                                    }"
                                />
                                <div
                                    class="h-3 w-5 rounded"
                                    :style="{
                                        background:
                                            swatches[option.value]?.accent,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start justify-between gap-2 p-3">
                        <div>
                            <div
                                class="text-sm font-medium"
                                :style="{
                                    fontFamily: swatches[option.value]?.font,
                                }"
                            >
                                {{ option.label }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ swatches[option.value]?.description }}
                            </div>
                        </div>
                        <Check
                            v-if="selected === option.value"
                            class="size-4 shrink-0 text-primary"
                        />
                    </div>
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <Heading
                variant="small"
                title="Açık / koyu mod"
                description="'Sistem' seçilirse cihazınızın ayarını izler."
            />
            <AppearanceTabs />
        </div>
    </div>
</template>
