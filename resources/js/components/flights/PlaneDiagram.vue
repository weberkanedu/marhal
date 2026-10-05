<script setup lang="ts">
import { computed } from 'vue';

/**
 * Uçak kabini çizimi: sıra numaraları, koridorla ayrılmış harf grupları, acil çıkış sıraları.
 * Koltuk içeriği "seat" slotuyla verilir (koltuk adı, ör. "14C").
 */
const props = defineProps<{
    groups: string[][];
    rows: number[];
    exitRows: number[];
}>();

const wide = computed(() => props.groups.length > 2);
</script>

<template>
    <div class="plane inline-flex flex-col gap-1 border-2 px-3 pt-10 pb-6">
        <!-- Harf başlığı -->
        <div
            class="flex items-center gap-1 pl-8 text-[10px] text-muted-foreground"
        >
            <template v-for="(group, g) in groups" :key="g">
                <div v-if="g > 0" :class="wide ? 'w-4' : 'w-6'" />
                <div
                    v-for="letter in group"
                    :key="letter"
                    class="plane-seat-w text-center font-semibold"
                >
                    {{ letter }}
                </div>
            </template>
        </div>

        <template v-for="row in rows" :key="row">
            <div
                v-if="exitRows.includes(row)"
                class="flex justify-between px-1 text-[9.5px] font-bold tracking-wider text-danger uppercase"
            >
                <span>◀ Acil çıkış</span><span>Acil çıkış ▶</span>
            </div>
            <div class="flex items-center gap-1">
                <span
                    class="w-7 shrink-0 text-right text-[10px] text-muted-foreground tabular-nums"
                >
                    {{ row }}
                </span>
                <template v-for="(group, g) in groups" :key="g">
                    <div
                        v-if="g > 0"
                        :class="wide ? 'w-4' : 'w-6'"
                        class="shrink-0"
                    />
                    <div
                        v-for="letter in group"
                        :key="letter"
                        class="plane-seat-w shrink-0"
                    >
                        <slot name="seat" :seat="`${row}${letter}`" :row="row">
                            <div
                                class="grid h-9 place-items-center rounded-md border text-[10px]"
                            >
                                {{ row }}{{ letter }}
                            </div>
                        </slot>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>
