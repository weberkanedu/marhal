<script setup lang="ts">
import ProgressRing from '@/components/ProgressRing.vue';
import { formatMoney } from '@/lib/format';
import type { ReadinessCheck } from '@/types/dashboard';
import type {
    TourReadinessSummary,
    TourStats,
    TourSummary,
} from '@/types/tour';

/**
 * Tur sayfasının gösterge halkaları: Kayıt, Tahsilat, Oda, Koltuk, Uçuş.
 * Oda / koltuk / uçuş / tahsilat sayıları ana paneldeki "Turların durumu" ile aynı yerden gelir
 * (App\Support\Dashboard\TourReadiness). Halkaya tıklayınca ilgili sekme açılır.
 */
defineProps<{
    tour: TourSummary;
    stats: TourStats;
    readiness: TourReadinessSummary;
}>();

const emit = defineEmits<{ select: [tab: string] }>();

const ringLabel: Record<string, string> = {
    rooms: 'Oda',
    seats: 'Koltuk',
    flights: 'Uçuş koltuğu',
};

const checkText = (check: ReadinessCheck) =>
    check.total - check.done > 0
        ? `${check.total - check.done} kişi bekliyor`
        : 'Herkes yerleşti';
</script>

<template>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(9.5rem,1fr))] gap-3">
        <button
            type="button"
            class="kpi-tile"
            @click="emit('select', 'yolcular')"
        >
            <ProgressRing
                :done="stats.registered"
                :total="tour.capacity ?? stats.registered"
            />
            <div class="min-w-0">
                <small>Kayıt</small>
                <span class="kpi-value num">
                    {{ stats.registered
                    }}<template v-if="tour.capacity">
                        / {{ tour.capacity }}</template
                    >
                </span>
                <small>
                    {{ stats.confirmed }} kesin · {{ stats.pending }} ön kayıt
                </small>
            </div>
        </button>

        <div
            v-if="readiness.collection && stats.total !== null"
            class="kpi-tile static"
        >
            <ProgressRing
                :done="Number(readiness.collection.paid)"
                :total="Number(readiness.collection.total)"
            />
            <div class="min-w-0">
                <small>Tahsilat</small>
                <span class="kpi-value num text-success">
                    {{ formatMoney(stats.paid ?? '0', tour.currency) }}
                </span>
                <small>
                    Kalan
                    <b class="font-semibold text-warning">{{
                        formatMoney(stats.balance ?? '0', tour.currency)
                    }}</b>
                </small>
            </div>
        </div>

        <button
            v-for="check in readiness.checks"
            :key="check.key"
            type="button"
            class="kpi-tile"
            @click="emit('select', check.tab)"
        >
            <ProgressRing :done="check.done" :total="check.total" />
            <div class="min-w-0">
                <small>{{ ringLabel[check.key] ?? check.label }}</small>
                <span class="kpi-value num"
                    >{{ check.done }} / {{ check.total }}</span
                >
                <small>{{ checkText(check) }}</small>
            </div>
        </button>
    </div>
</template>
