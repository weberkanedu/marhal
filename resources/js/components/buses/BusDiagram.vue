<script setup lang="ts">
import { Armchair, CircleDot } from '@lucide/vue';
import { computed } from 'vue';
import type { BusCell, VehicleBody } from '@/types/bus';

/**
 * Araç koltuk çizimi (gövdeye göre: otobüs uzun ve köşeli burun, van / minibüs kısa ve yuvarlak).
 * Koltuk içeriği "seat" slotuyla verilir; slot verilmezse sadece numara.
 * Önizleme (araç tipi formu) ve koltuk planı ekranı aynı bileşeni kullanır.
 */
const props = withDefaults(
    defineProps<{
        grid: BusCell[][];
        compact?: boolean;
        body?: VehicleBody;
    }>(),
    { compact: false, body: 'otobus' },
);

// Şoför yanı koltuk yoksa şoför ayrı bir şerit olarak gösterilir.
const hasDriverRow = computed(() =>
    props.grid.some((cells) => cells.includes('driver')),
);
</script>

<template>
    <div
        class="veh inline-flex flex-col gap-1 border-2 p-2 sm:p-3"
        :class="[`veh-${body}`, compact ? 'veh-compact' : '']"
    >
        <div
            v-if="!hasDriverRow"
            class="mb-1 flex items-center justify-center gap-1 rounded-md border bg-background/60 px-2 py-1 text-xs text-muted-foreground"
        >
            <CircleDot class="size-3" /> Ön — şoför
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
                <div
                    v-else-if="cell === 'driver'"
                    :class="
                        compact
                            ? 'size-7 text-[9px]'
                            : 'h-14 w-[4.2rem] text-xs sm:w-28'
                    "
                    class="flex shrink-0 flex-col items-center justify-center gap-0.5 rounded-md border border-dashed text-muted-foreground"
                    title="Şoför"
                >
                    <Armchair :class="compact ? 'size-3' : 'size-4'" />
                    <span v-if="!compact">Şoför</span>
                </div>
                <div
                    v-else
                    :class="
                        cells.includes('aisle')
                            ? compact
                                ? 'size-7 shrink-0'
                                : 'w-[4.2rem] shrink-0 sm:w-28'
                            : 'min-w-0 flex-1'
                    "
                ></div>
            </template>
        </div>
    </div>
</template>
