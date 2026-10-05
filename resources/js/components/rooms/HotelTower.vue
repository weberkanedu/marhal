<script setup lang="ts">
import { computed } from 'vue';

/**
 * Otel kulesi: binanın bütün katları (üstten aşağı); bize verilen katlar renkli ve doluluklu.
 * Kata tıklayınca o katın odaları gösterilir (tekrar tıklayınca hepsi).
 */
const props = defineProps<{
    floorsCount: number;
    usedFloors: number[];
    // kat → { dolu, yatak }
    occupancy: Record<string, { occupied: number; beds: number }>;
    selected: number | null;
}>();

const emit = defineEmits<{ select: [floor: number | null] }>();

const floors = computed(() =>
    Array.from({ length: props.floorsCount }, (_, i) => props.floorsCount - i),
);
</script>

<template>
    <div class="tower flex w-36 shrink-0 flex-col items-stretch">
        <div class="tower-roof mx-auto h-3 w-3/4 rounded-t-lg" />
        <div
            class="flex max-h-[26rem] flex-col gap-0.5 overflow-y-auto rounded-t-md border-2 border-b-0 p-1.5"
        >
            <button
                v-for="floor in floors"
                :key="floor"
                type="button"
                class="flex h-7 shrink-0 items-center justify-between rounded px-2 text-[11px] transition"
                :class="[
                    usedFloors.includes(floor)
                        ? 'tower-ours font-semibold'
                        : 'bg-muted/60 text-muted-foreground',
                    selected === floor ? 'ring-2 ring-primary' : '',
                ]"
                :disabled="!usedFloors.includes(floor)"
                :aria-pressed="selected === floor"
                @click="emit('select', selected === floor ? null : floor)"
            >
                <span>{{ floor }}. kat</span>
                <span
                    v-if="usedFloors.includes(floor) && occupancy[floor]"
                    class="tabular-nums"
                >
                    {{ occupancy[floor].occupied }}/{{ occupancy[floor].beds }}
                </span>
            </button>
        </div>
        <div class="h-2 rounded-b-md border-2 border-t-0" />
        <small class="mt-2 text-center text-[10.5px] text-muted-foreground">
            {{ floorsCount }} katlı bina · bize ayrılan
            {{ usedFloors.length }} kat
        </small>
    </div>
</template>
