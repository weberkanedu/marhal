<script setup lang="ts">
import ProgressBar from '@/components/ProgressBar.vue';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Plane } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { create as createTour, show as showTour } from '@/routes/tours';
import { percent } from '@/types/dashboard';
import type { DashboardTour, ReadinessCheck } from '@/types/dashboard';

/**
 * "Turların durumu": bütün aktif turlar tek tabloda — kayıt, tahsilat, oda, koltuk, uçuş, pasaport.
 * Tamamlanan yeşil, eksik turuncu, başlanmamış "—". Telefonda kart görünümü.
 */
const props = defineProps<{ tours: DashboardTour[] }>();

const columns = [
    { key: 'rooms', label: 'Oda' },
    { key: 'seats', label: 'Koltuk' },
    { key: 'flights', label: 'Uçuş' },
];
const showPayments = computed(() =>
    props.tours.some((t) => t.collection !== null),
);

const check = (tour: DashboardTour, key: string): ReadinessCheck | undefined =>
    tour.checks.find((c) => c.key === key);

const collectionPct = (tour: DashboardTour) =>
    tour.collection
        ? percent(Number(tour.collection.paid), Number(tour.collection.total))
        : null;

// Genel durum: bütün kontroller tamam, pasaport ve grup sorunu yok, ön kayıt kalmamış.
const ready = (tour: DashboardTour) =>
    tour.checks.every((c) => c.done >= c.total) &&
    tour.passport_issues === 0 &&
    tour.ungrouped === 0 &&
    tour.pending === 0;

const daysText = (days: number) =>
    days > 0 ? `${days} gün` : days === 0 ? 'Bugün' : 'Devam ediyor';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Plane class="size-4" /> Turların durumu
            </CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <p
                v-if="tours.length === 0"
                class="px-6 pb-6 text-sm text-muted-foreground"
            >
                Aktif tur yok.
                <Link :href="createTour()" class="underline"
                    >Yeni tur oluşturun.</Link
                >
            </p>

            <!-- Bilgisayar: tablo -->
            <div v-else class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Tur</th>
                            <th class="px-3 py-2 font-medium">Kalan</th>
                            <th class="px-3 py-2 font-medium">Kayıt</th>
                            <th
                                v-if="showPayments"
                                class="px-3 py-2 font-medium"
                            >
                                Tahsilat
                            </th>
                            <th
                                v-for="c in columns"
                                :key="c.key"
                                class="px-3 py-2 font-medium"
                            >
                                {{ c.label }}
                            </th>
                            <th class="px-3 py-2 font-medium">Pasaport</th>
                            <th class="px-4 py-2 font-medium">Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tour in tours"
                            :key="tour.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="showTour(tour.id)"
                                    class="font-medium hover:underline"
                                    >{{ tour.name }}</Link
                                >
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(tour.start_date) }}
                                </div>
                            </td>
                            <td
                                class="px-3 py-2.5 whitespace-nowrap"
                                :class="{
                                    'text-danger':
                                        tour.days_left >= 0 &&
                                        tour.days_left <= 14,
                                }"
                            >
                                {{ daysText(tour.days_left) }}
                            </td>
                            <td class="px-3 py-2.5 tabular-nums">
                                {{ tour.registered
                                }}<span
                                    v-if="tour.capacity"
                                    class="text-muted-foreground"
                                    >/{{ tour.capacity }}</span
                                >
                                <div
                                    v-if="tour.pending"
                                    class="text-xs text-warning"
                                >
                                    {{ tour.pending }} ön kayıt
                                </div>
                            </td>
                            <td v-if="showPayments" class="px-3 py-2.5">
                                <template v-if="collectionPct(tour) !== null">
                                    <span class="tabular-nums"
                                        >%{{ collectionPct(tour) }}</span
                                    >
                                    <ProgressBar
                                        class="mt-1 w-16"
                                        :value="collectionPct(tour) ?? 0"
                                    />
                                </template>
                            </td>
                            <td
                                v-for="c in columns"
                                :key="c.key"
                                class="px-3 py-2.5 whitespace-nowrap"
                            >
                                <Link
                                    v-if="check(tour, c.key)"
                                    :href="
                                        showTour(tour.id, {
                                            query: {
                                                tab: check(tour, c.key)!.tab,
                                            },
                                        })
                                    "
                                    class="inline-flex items-center gap-1.5 tabular-nums hover:underline"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :class="
                                            check(tour, c.key)!.done >=
                                            check(tour, c.key)!.total
                                                ? 'bg-success'
                                                : 'bg-warning'
                                        "
                                        aria-hidden="true"
                                    />
                                    {{ check(tour, c.key)!.done }}/{{
                                        check(tour, c.key)!.total
                                    }}
                                </Link>
                                <span
                                    v-else
                                    class="text-muted-foreground"
                                    title="Henüz başlanmadı"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    v-if="tour.passport_issues"
                                    class="inline-flex items-center gap-1 text-warning"
                                >
                                    <AlertTriangle class="size-3.5" />
                                    {{ tour.passport_issues }}
                                </span>
                                <CheckCircle2
                                    v-else
                                    class="size-4 text-success"
                                    aria-label="Tamam"
                                />
                            </td>
                            <td class="px-4 py-2.5">
                                <Badge
                                    :variant="
                                        ready(tour) ? 'success' : 'warning'
                                    "
                                >
                                    {{ ready(tour) ? 'Hazır' : 'Eksik var' }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Telefon: kartlar -->
            <ul v-if="tours.length" class="divide-y md:hidden">
                <li
                    v-for="tour in tours"
                    :key="tour.id"
                    class="space-y-2 px-4 py-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <Link
                                :href="showTour(tour.id)"
                                class="font-medium hover:underline"
                                >{{ tour.name }}</Link
                            >
                            <div class="text-xs text-muted-foreground">
                                {{ formatDate(tour.start_date) }} ·
                                {{ daysText(tour.days_left) }}
                            </div>
                        </div>
                        <Badge :variant="ready(tour) ? 'success' : 'warning'">
                            {{ ready(tour) ? 'Hazır' : 'Eksik var' }}
                        </Badge>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <span
                            >Kayıt {{ tour.registered
                            }}<template v-if="tour.capacity"
                                >/{{ tour.capacity }}</template
                            ></span
                        >
                        <span v-if="collectionPct(tour) !== null"
                            >Tahsilat %{{ collectionPct(tour) }}</span
                        >
                        <template v-for="c in columns" :key="c.key">
                            <span
                                v-if="check(tour, c.key)"
                                :class="
                                    check(tour, c.key)!.done >=
                                    check(tour, c.key)!.total
                                        ? 'text-success'
                                        : 'text-warning'
                                "
                            >
                                {{ c.label }} {{ check(tour, c.key)!.done }}/{{
                                    check(tour, c.key)!.total
                                }}
                            </span>
                        </template>
                        <span v-if="tour.passport_issues" class="text-warning"
                            >Pasaport {{ tour.passport_issues }}</span
                        >
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
