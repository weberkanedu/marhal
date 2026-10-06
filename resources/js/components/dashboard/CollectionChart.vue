<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * Aylık tahsilat (son 6 ay) — tasarımdaki çubuk grafik: bin cinsinden değerler, beş ızgara çizgisi,
 * bu ayın çubuğu koyu. Para birimleri toplanmaz; birden fazlaysa başlıktaki haplarla seçilir.
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

const symbol = computed(
    () =>
        new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: currency.value || 'TRY',
        })
            .formatToParts(0)
            .find((p) => p.type === 'currency')?.value ?? currency.value,
);

// Bin cinsinden (tasarımdaki gibi).
const data = computed(() =>
    props.months.map((m, i) => ({
        label: m.label,
        value: Math.round(
            Number(props.series[currency.value]?.[i] ?? 0) / 1000,
        ),
    })),
);

// Ölçek: en büyük değeri kapsayan "yuvarlak" üst sınır, dört eşit aralık.
const top = computed(() => {
    const max = Math.max(...data.value.map((d) => d.value), 1);
    const raw = max / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step =
        [1, 2, 2.5, 5, 10].map((f) => f * mag).find((s) => s >= raw) ?? raw;

    return step * 4;
});

const W = 560;
const H = 200;
const L = 40;
const R = 8;
const T = 18;
const B = 26;
const ph = H - T - B;
const slot = computed(() => (W - L - R) / Math.max(data.value.length, 1));
const bw = computed(() => slot.value * 0.52);
const y = (v: number) => T + ph - (Math.min(v, top.value) / top.value) * ph;
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (top.value / 4) * i));
</script>

<template>
    <div class="card a-chart">
        <h4>
            Aylık tahsilat
            <em
                >Bin {{ symbol
                }}<template v-if="currencies.length > 1">
                    ·
                    <a
                        v-for="c in currencies"
                        :key="c"
                        role="button"
                        tabindex="0"
                        :style="{
                            fontWeight: c === currency ? 700 : 500,
                            cursor: 'pointer',
                            marginLeft: '4px',
                        }"
                        @click="currency = c"
                        >{{ c }}</a
                    ></template
                ></em
            >
        </h4>
        <svg
            class="chartsvg"
            :viewBox="`0 0 ${W} ${H}`"
            role="img"
            :aria-label="`Aylık tahsilat, bin ${symbol}`"
        >
            <template v-for="t in ticks" :key="`t${t}`">
                <line class="gl" :x1="L" :x2="W - R" :y1="y(t)" :y2="y(t)" />
                <text class="ax" :x="L - 8" :y="y(t) + 3.5" text-anchor="end">
                    {{ Math.round(t) }}
                </text>
            </template>
            <template v-for="(d, i) in data" :key="d.label">
                <rect
                    class="bb"
                    :class="{ cur: i === data.length - 1 }"
                    :x="L + i * slot + (slot - bw) / 2"
                    :y="y(d.value)"
                    :width="bw"
                    :height="y(0) - y(d.value)"
                    rx="5"
                />
                <text
                    class="vl"
                    :x="L + i * slot + slot / 2"
                    :y="y(d.value) - 6"
                    text-anchor="middle"
                >
                    {{ d.value }}
                </text>
                <text
                    class="ax"
                    :x="L + i * slot + slot / 2"
                    :y="H - 8"
                    text-anchor="middle"
                >
                    {{ d.label }}
                </text>
            </template>
        </svg>
    </div>
</template>
