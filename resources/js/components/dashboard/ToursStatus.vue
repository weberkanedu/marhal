<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { create as createTour, show as showTour } from '@/routes/tours';
import { percent } from '@/types/dashboard';
import type { DashboardTour } from '@/types/dashboard';

/**
 * "Turların durumu" (tasarımdaki gibi): tur, kalan gün, kayıt ve tahsilat çubukları; ödeme modülü
 * kapalıysa tahsilat yerine hazırlık (oda / koltuk / uçuş ortalaması).
 */
defineProps<{ tours: DashboardTour[] }>();

const secondPct = (tour: DashboardTour) => {
    if (tour.collection) {
        return percent(
            Number(tour.collection.paid),
            Number(tour.collection.total),
        );
    }

    const parts = tour.checks.map((c) => percent(c.done, c.total));

    return parts.length
        ? Math.round(parts.reduce((a, b) => a + b, 0) / parts.length)
        : 0;
};

// "23 Eki · 15 gün"
const dateText = (tour: DashboardTour) => {
    const a = new Date(`${tour.start_date}T00:00:00`);
    const b = new Date(`${tour.end_date}T00:00:00`);

    return `${a.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })} · ${Math.round((b.getTime() - a.getTime()) / 86_400_000) + 1} gün`;
};
</script>

<template>
    <div class="card a-tours">
        <h4>Turların durumu <em>Kayıt · Hazırlık · Tahsilat</em></h4>
        <div class="trs">
            <Link
                v-for="tour in tours"
                :key="tour.id"
                class="tr"
                :href="showTour(tour.id)"
            >
                <div>
                    <b>{{ tour.name }}</b
                    ><small>{{ dateText(tour) }}</small>
                </div>
                <div>
                    <b style="font-family: var(--m-num)">{{
                        tour.days_left > 0 ? tour.days_left : '—'
                    }}</b
                    ><small>{{
                        tour.days_left > 0
                            ? 'gün'
                            : tour.days_left === 0
                              ? 'bugün'
                              : 'yolda'
                    }}</small>
                </div>
                <div class="mini">
                    <span
                        >Kayıt
                        <em style="font-style: normal"
                            >{{ tour.registered
                            }}{{ tour.capacity ? `/${tour.capacity}` : '' }}</em
                        ></span
                    >
                    <div class="bar">
                        <i
                            :style="{
                                width: `${percent(tour.registered, tour.capacity ?? tour.registered)}%`,
                            }"
                        />
                    </div>
                </div>
                <div class="mini">
                    <span
                        >{{ tour.collection ? 'Tahsilat' : 'Hazırlık' }}
                        <em style="font-style: normal"
                            >%{{ secondPct(tour) }}</em
                        ></span
                    >
                    <div class="bar">
                        <i
                            :class="{ full: secondPct(tour) >= 100 }"
                            :style="{ width: `${secondPct(tour)}%` }"
                        />
                    </div>
                </div>
            </Link>
            <p v-if="tours.length === 0" class="lbl">
                Aktif tur yok.
                <Link :href="createTour()">Yeni tur oluşturun.</Link>
            </p>
        </div>
    </div>
</template>
