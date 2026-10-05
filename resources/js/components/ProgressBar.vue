<script setup lang="ts">
import { computed } from 'vue';

/**
 * Temaya uyan ilerleme çubuğu: dolarken altın / yeşil geçişli, tamamlanınca yeşil, aşınca kırmızı.
 */
const props = defineProps<{
    value: number;
    max?: number;
}>();

const percent = computed(() => {
    const max = props.max ?? 100;

    return max > 0 ? (props.value / max) * 100 : 0;
});
</script>

<template>
    <div
        class="m-bar"
        role="progressbar"
        :aria-valuenow="Math.round(percent)"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <i
            :class="{ full: percent === 100, over: percent > 100 }"
            :style="{ width: `${Math.min(100, Math.max(0, percent))}%` }"
        />
    </div>
</template>
