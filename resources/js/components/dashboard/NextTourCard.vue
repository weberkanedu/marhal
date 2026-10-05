<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import ProgressBar from '@/components/ProgressBar.vue';
import ProgressRing from '@/components/ProgressRing.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';
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

const tabUrl = (tab: string) => showTour.url(props.tour.id, { query: { tab } });
</script>

<template>
    <Card class="card-glow">
        <CardContent class="grid gap-5 md:grid-cols-[auto_minmax(0,1fr)]">
            <div class="flex items-center gap-5">
                <div>
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Sıradaki tur
                    </p>
                    <p
                        class="num mt-1 text-5xl leading-none font-bold tracking-tight"
                    >
                        {{ countdown.value }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ countdown.unit }}
                    </p>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <ProgressRing :done="readiness" :total="100" :size="76" />
                    <span class="text-xs text-muted-foreground">Hazırlık</span>
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <Link
                    :href="showTour(tour.id)"
                    class="group flex flex-wrap items-baseline justify-between gap-2"
                >
                    <span class="font-heading text-xl font-semibold">
                        {{ tour.name }}
                    </span>
                    <span
                        class="flex items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <CalendarDays class="size-4" />
                        {{ formatDate(tour.start_date) }} –
                        {{ formatDate(tour.end_date) }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </span>
                </Link>

                <div class="grid gap-2.5 sm:grid-cols-2">
                    <Link
                        v-for="check in tour.checks"
                        :key="check.key"
                        :href="tabUrl(check.tab)"
                        class="rounded-lg p-1 hover:bg-muted"
                    >
                        <span
                            class="mb-1 flex justify-between text-xs text-muted-foreground"
                        >
                            <span>{{ check.label }}</span>
                            <span class="tabular-nums">
                                {{ check.done }} / {{ check.total }}
                            </span>
                        </span>
                        <ProgressBar :value="check.done" :max="check.total" />
                    </Link>
                    <Link
                        v-if="tour.collection"
                        :href="tabUrl('yolcular')"
                        class="rounded-lg p-1 hover:bg-muted"
                    >
                        <span
                            class="mb-1 flex justify-between text-xs text-muted-foreground"
                        >
                            <span>Tahsilat</span>
                            <span class="tabular-nums">
                                {{
                                    formatMoney(
                                        tour.collection.paid,
                                        tour.collection.currency,
                                    )
                                }}
                            </span>
                        </span>
                        <ProgressBar
                            :value="Number(tour.collection.paid)"
                            :max="Number(tour.collection.total)"
                        />
                    </Link>
                </div>

                <p
                    v-if="tour.checks.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Otel, otobüs ve uçuş eklendikçe yerleşim durumu burada
                    görünür.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
