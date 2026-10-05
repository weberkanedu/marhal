<script setup lang="ts">
import { computed } from 'vue';

/**
 * Gösterge halkası (tur sayfası, ana panel): dolu kısım temanın vurgu rengi, tamamlanınca yeşil.
 * `total` 0 ise (henüz başlanmamış) gri ve tire gösterilir.
 */
const props = withDefaults(
    defineProps<{
        done: number;
        total: number;
        size?: number;
    }>(),
    { size: 54 },
);

const percent = computed(() =>
    props.total > 0
        ? Math.min(100, Math.round((props.done / props.total) * 100))
        : null,
);
</script>

<template>
    <div
        class="relative shrink-0"
        :style="{ width: `${size}px`, height: `${size}px` }"
        role="img"
        :aria-label="percent === null ? 'Başlanmadı' : `%${percent}`"
    >
        <div
            class="m-ring"
            :class="{ full: percent === 100 }"
            :style="{ '--p': percent ?? 0 }"
        />
        <b
            class="num absolute inset-0 grid place-items-center text-xs font-semibold"
        >
            {{ percent === null ? '—' : `%${percent}` }}
        </b>
    </div>
</template>
