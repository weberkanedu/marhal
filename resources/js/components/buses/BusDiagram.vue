<script setup lang="ts">
import type { BusCell } from '@/types/bus';

/**
 * Otobüs koltuk çizimi. Koltuk içeriği "seat" slotuyla verilir; slot verilmezse sadece numara.
 * Önizleme (araç tipi formu) ve koltuk planı ekranı aynı bileşeni kullanır.
 */
withDefaults(
    defineProps<{
        grid: BusCell[][];
        compact?: boolean;
    }>(),
    { compact: false },
);
</script>

<template>
    <div
        class="inline-flex flex-col gap-1 rounded-2xl border-2 bg-muted/30 p-2 sm:p-3"
    >
        <div
            class="mb-1 rounded-md border bg-background px-2 py-1 text-center text-xs text-muted-foreground"
        >
            Ön — şoför
        </div>
        <div
            v-for="(cells, rowIndex) in grid"
            :key="rowIndex"
            class="flex gap-1"
        >
            <template v-for="(cell, cellIndex) in cells" :key="cellIndex">
                <!-- Arka sıranın (koridorsuz) koltukları diğer sıraların genişliğini paylaşır. -->
                <div
                    v-if="typeof cell === 'number'"
                    :class="
                        !cells.includes('aisle')
                            ? compact
                                ? 'h-7 min-w-0 flex-1 text-[10px]'
                                : 'min-w-0 flex-1'
                            : compact
                              ? 'size-7 shrink-0 text-[10px]'
                              : 'w-[4.2rem] shrink-0 sm:w-28'
                    "
                >
                    <slot name="seat" :seat="cell">
                        <div
                            class="flex size-full items-center justify-center rounded-md border bg-background"
                        >
                            {{ cell }}
                        </div>
                    </slot>
                </div>
                <div
                    v-else-if="cell === 'aisle'"
                    :class="compact ? 'w-3' : 'w-3 sm:w-6'"
                    class="shrink-0"
                ></div>
                <div
                    v-else-if="cell === 'door'"
                    :class="
                        compact
                            ? 'size-7 text-[9px]'
                            : 'w-[4.2rem] text-xs sm:w-28'
                    "
                    class="flex shrink-0 items-center justify-center text-muted-foreground"
                >
                    kapı
                </div>
                <div v-else class="min-w-0 flex-1"></div>
            </template>
        </div>
    </div>
</template>
