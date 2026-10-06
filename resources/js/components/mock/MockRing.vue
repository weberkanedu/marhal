<script setup lang="ts">
import { computed } from 'vue';

/**
 * Tasarımdaki yüzde halkası (.ringwrap): ortada yüzde, tamamlanınca yeşil.
 */
const props = defineProps<{ done: number; total: number }>();

const pct = computed(() =>
    props.total > 0
        ? Math.min(100, Math.round((props.done / props.total) * 100))
        : 0,
);
</script>

<template>
    <div class="ringwrap">
        <div
            class="ring"
            :class="{ full: pct >= 100 }"
            :style="{ '--p': pct }"
        />
        <b>{{ pct }}%</b>
    </div>
</template>
