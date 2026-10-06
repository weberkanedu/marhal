<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MockRing from '@/components/mock/MockRing.vue';
import { show as showTour } from '@/routes/tours';
import { percent } from '@/types/dashboard';
import type { DashboardTour } from '@/types/dashboard';

/**
 * Ana paneldeki "Sıradaki tur": geri sayım, genel hazırlık halkası ve oda / koltuk / uçuş çubukları.
 * Sayılar "Turların durumu" tablosuyla aynı kaynaktan (TourReadiness); çubuklar ilgili sekmeye gider.
 */
const props = defineProps<{ tour: DashboardTour }>();

// Genel hazırlık: oda / koltuk / uçuş yerleşimi ve geçerli pasaport oranlarının ortalaması.
const readiness = computed(() => {
    const parts = props.tour.checks.map((c) => percent(c.done, c.total));

    if (props.tour.registered > 0) {
        parts.push(
            percent(
                props.tour.registered - props.tour.passport_issues,
                props.tour.registered,
            ),
        );
    }

    return parts.length
        ? Math.round(parts.reduce((a, b) => a + b, 0) / parts.length)
        : 0;
});

const countdown = computed(() =>
    props.tour.days_left > 0
        ? { value: props.tour.days_left, unit: 'gün kaldı' }
        : props.tour.days_left === 0
          ? { value: 'Bugün', unit: 'yola çıkılıyor' }
          : { value: 'Yolda', unit: 'tur devam ediyor' },
);

const labels: Record<string, string> = {
    rooms: 'Oda',
    seats: 'Koltuk',
    flights: 'Uçuş',
};

// "23 Eki – 6 Kas"
const short = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
const range = computed(
    () => `${short(props.tour.start_date)} – ${short(props.tour.end_date)}`,
);

const tabUrl = (tab: string) => showTour.url(props.tour.id, { query: { tab } });
</script>

<template>
    <div class="card glow a-hero">
        <h4>
            Sıradaki tur <em>{{ range }}</em>
        </h4>
        <Link
            :href="showTour(tour.id)"
            style="font: 600 17px var(--m-display)"
            >{{ tour.name }}</Link
        >
        <div class="row" style="justify-content: space-between">
            <div class="count">
                <b>{{ countdown.value }}</b
                ><span class="lbl">{{ countdown.unit }}</span>
            </div>
            <MockRing :done="readiness" :total="100" />
        </div>
        <Link
            v-for="check in tour.checks"
            :key="check.key"
            class="chk"
            :href="tabUrl(check.tab)"
        >
            <span>{{ labels[check.key] ?? check.label }}</span>
            <div class="bar">
                <i
                    :class="{ full: percent(check.done, check.total) >= 100 }"
                    :style="{ width: `${percent(check.done, check.total)}%` }"
                />
            </div>
            <span>{{ check.done }}/{{ check.total }}</span>
        </Link>
    </div>
</template>
