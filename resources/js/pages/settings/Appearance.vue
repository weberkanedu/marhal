<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { applyTheme } from '@/composables/useTheme';
import { edit } from '@/routes/appearance';
import { update } from '@/routes/theme';
import type { ColorTheme } from '@/types';

type ThemeOption = { value: ColorTheme; label: string };

const props = defineProps<{
    theme: ColorTheme;
    themes: ThemeOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Görünüm', href: edit() }],
    },
});

// Önizleme kartları: temanın kendi renkleriyle küçük bir ekran taslağı.
const previews: Record<
    ColorTheme,
    {
        bg: string;
        glow: string[];
        sidebar: string;
        card: string;
        line: string;
        ink: string;
        button: string;
        bar: string;
        font: string;
        description: string;
    }
> = {
    zumrut: {
        bg: '#0b1b16',
        glow: ['#15795a', '#9a7816'],
        sidebar: 'rgba(10,26,20,.6)',
        card: 'rgba(22,48,38,.85)',
        line: 'rgba(217,180,74,.25)',
        ink: '#ddb94f',
        button: 'linear-gradient(135deg,#f3d77e,#c99f30)',
        bar: 'linear-gradient(90deg,#a37f20,#f1d27a)',
        font: "'Fraunces', serif",
        description:
            'Koyu gece yeşili ve altın; cam kartlar, parlayan kenarlar',
    },
    safak: {
        bg: '#eef3f1',
        glow: ['#9be7c4', '#ffd98a', '#ffc1d6'],
        sidebar: 'rgba(255,255,255,.55)',
        card: 'rgba(255,255,255,.7)',
        line: 'rgba(19,38,31,.12)',
        ink: '#13261f',
        button: '#13261f',
        bar: 'linear-gradient(90deg,#3cc58f,#0f6b4e)',
        font: "'Plus Jakarta Sans', sans-serif",
        description: 'Açık ve ferah; buzlu cam, renkli aurora ışıkları',
    },
};

const selected = ref<ColorTheme>(props.theme);

function choose(value: ColorTheme): void {
    selected.value = value;
    // Anında uygula, sonra kaydet (her cihazda aynı tema).
    applyTheme(value);
    router.patch(update.url(), { theme: value }, { preserveScroll: true });
}

function background(value: ColorTheme): string {
    const p = previews[value];
    const spots = p.glow
        .map(
            (color, i) =>
                `radial-gradient(circle at ${[15, 85, 45][i]}% ${[10, 30, 100][i]}%, ${color}${value === 'zumrut' ? '99' : ''} 0, transparent 55%)`,
        )
        .join(', ');

    return `${spots}, ${p.bg}`;
}
</script>

<template>
    <Head title="Görünüm" />

    <h1 class="sr-only">Görünüm ayarları</h1>

    <div class="space-y-4">
        <Heading
            variant="small"
            title="Tema"
            description="Seçiminiz hesabınıza kaydedilir; her cihazda aynı görünür. Yazdırılan listeler ve yaka kartları temadan etkilenmez."
        />
        <div class="grid gap-4 sm:grid-cols-2">
            <button
                v-for="option in themes"
                :key="option.value"
                type="button"
                class="group overflow-hidden rounded-2xl border-2 text-left transition-colors"
                :class="
                    selected === option.value
                        ? 'border-primary'
                        : 'border-border hover:border-muted-foreground/40'
                "
                :aria-pressed="selected === option.value"
                @click="choose(option.value)"
            >
                <div
                    class="flex h-32 gap-2 p-2.5"
                    :style="{ background: background(option.value) }"
                >
                    <div
                        class="w-1/4 rounded-lg border"
                        :style="{
                            background: previews[option.value].sidebar,
                            borderColor: previews[option.value].line,
                        }"
                    />
                    <div
                        class="flex flex-1 flex-col gap-2 rounded-lg border p-2.5"
                        :style="{
                            background: previews[option.value].card,
                            borderColor: previews[option.value].line,
                        }"
                    >
                        <div
                            class="h-2.5 w-2/3 rounded-full"
                            :style="{ background: previews[option.value].ink }"
                        />
                        <div
                            class="h-1.5 w-3/4 overflow-hidden rounded-full"
                            :style="{ background: previews[option.value].line }"
                        >
                            <div
                                class="h-full w-2/3 rounded-full"
                                :style="{
                                    background: previews[option.value].bar,
                                }"
                            />
                        </div>
                        <div class="mt-auto flex gap-1.5">
                            <div
                                class="h-4 w-12 rounded-full"
                                :style="{
                                    background: previews[option.value].button,
                                }"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex items-start justify-between gap-2 p-3">
                    <div>
                        <div
                            class="text-sm font-semibold"
                            :style="{
                                fontFamily: previews[option.value].font,
                            }"
                        >
                            {{ option.label }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ previews[option.value].description }}
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
</template>
