<script setup lang="ts">
import { BarChart3 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';

/**
 * Aylık tahsilat (son 6 ay) — tek seri çubuk grafik. Para birimleri toplanmaz; birden fazlaysa
 * üstteki düğmelerle seçilir. Tema rengi (primary) kullanılır; üzerine gelince değer görünür;
 * ekran okuyucular için aynı veri gizli tabloda.
 */
const props = defineProps<{
    months: { key: string; label: string }[];
    series: Record<string, string[]>;
}>();

const currencies = computed(() => Object.keys(props.series));
const currency = ref(currencies.value[0] ?? '');
watch(currencies, (list) => {
    if (!list.includes(currency.value)) {
        currency.value = list[0] ?? '';
    }
});

const values = computed(() =>
    (props.series[currency.value] ?? []).map((v) => Number(v)),
);
const max = computed(() => Math.max(...values.value, 0));

// Grafik geometrisi (viewBox birimleri)
const width = 600;
const height = 180;
const pad = { top: 16, right: 0, bottom: 0, left: 0 };
const plotH = height - pad.top - pad.bottom;
const slot = computed(
    () => (width - pad.left - pad.right) / Math.max(props.months.length, 1),
);
const barW = computed(() => Math.min(56, slot.value * 0.55));

function barHeight(value: number): number {
    return max.value <= 0 ? 0 : (Math.max(value, 0) / max.value) * plotH;
}

// Üst köşeleri 4px yuvarlak, tabanı düz çubuk (tabana oturur).
function barPath(index: number): string {
    const h = barHeight(values.value[index]);
    const x = pad.left + slot.value * index + (slot.value - barW.value) / 2;
    const y = pad.top + plotH - h;
    const r = Math.min(4, h, barW.value / 2);

    if (h <= 0) {
        return '';
    }

    return `M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + barW.value - r} Q${x + barW.value},${y} ${x + barW.value},${y + r} V${y + h} Z`;
}

const gridLines = computed(() =>
    [0.5, 1].map((f) => pad.top + plotH - plotH * f),
);

const hovered = ref<number | null>(null);
const total = computed(() => values.value.reduce((a, b) => a + b, 0));
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row flex-wrap items-center justify-between gap-2"
        >
            <CardTitle class="flex items-center gap-2">
                <BarChart3 class="size-4" /> Aylık tahsilat
            </CardTitle>
            <div v-if="currencies.length > 1" class="flex gap-1">
                <Button
                    v-for="c in currencies"
                    :key="c"
                    size="sm"
                    :variant="c === currency ? 'default' : 'outline'"
                    @click="currency = c"
                >
                    {{ c }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <p
                v-if="currencies.length === 0"
                class="text-sm text-muted-foreground"
            >
                Son 6 ayda tahsilat yok.
            </p>
            <template v-else>
                <p class="mb-2 text-sm text-muted-foreground">
                    Son 6 ay toplamı:
                    <span class="font-medium text-foreground">{{
                        formatMoney(String(total), currency)
                    }}</span>
                </p>
                <div class="relative">
                    <svg
                        :viewBox="`0 0 ${width} ${height}`"
                        preserveAspectRatio="none"
                        class="h-44 w-full"
                        role="img"
                        :aria-label="`Aylık tahsilat, ${currency}`"
                    >
                        <line
                            v-for="(y, i) in gridLines"
                            :key="i"
                            :x1="pad.left"
                            :x2="width - pad.right"
                            :y1="y"
                            :y2="y"
                            class="stroke-border"
                            stroke-dasharray="3 4"
                            vector-effect="non-scaling-stroke"
                        />
                        <line
                            :x1="pad.left"
                            :x2="width - pad.right"
                            :y1="pad.top + plotH"
                            :y2="pad.top + plotH"
                            class="stroke-border"
                            vector-effect="non-scaling-stroke"
                        />
                        <g v-for="(month, i) in months" :key="month.key">
                            <path
                                :d="barPath(i)"
                                class="fill-primary transition-opacity"
                                :class="{
                                    'opacity-60':
                                        hovered !== null && hovered !== i,
                                }"
                            />
                            <!-- Görünmez geniş alan: üzerine gelince değer -->
                            <rect
                                :x="pad.left + slot * i"
                                :y="pad.top"
                                :width="slot"
                                :height="plotH"
                                fill="transparent"
                                @mouseenter="hovered = i"
                                @mouseleave="hovered = null"
                            />
                        </g>
                    </svg>
                    <!-- Ay adları SVG dışında: grafik genişlese de yazı boyutu sabit kalır -->
                    <div
                        class="mt-1 grid text-center text-xs text-muted-foreground"
                        :style="{
                            gridTemplateColumns: `repeat(${months.length}, minmax(0, 1fr))`,
                        }"
                    >
                        <span v-for="month in months" :key="month.key">{{
                            month.label
                        }}</span>
                    </div>
                    <div
                        v-if="hovered !== null"
                        class="pointer-events-none absolute top-0 rounded-md border bg-popover px-2 py-1 text-xs shadow-sm"
                        :style="{
                            left: `${((pad.left + slot * hovered + slot / 2) / width) * 100}%`,
                            transform: 'translateX(-50%)',
                        }"
                    >
                        <div class="text-muted-foreground">
                            {{ months[hovered].label }}
                        </div>
                        <div class="font-medium">
                            {{ formatMoney(String(values[hovered]), currency) }}
                        </div>
                    </div>
                </div>
                <table class="sr-only">
                    <caption>
                        Aylık tahsilat ({{
                            currency
                        }})
                    </caption>
                    <tr v-for="(month, i) in months" :key="month.key">
                        <th scope="row">{{ month.label }}</th>
                        <td>{{ formatMoney(String(values[i]), currency) }}</td>
                    </tr>
                </table>
            </template>
        </CardContent>
    </Card>
</template>
