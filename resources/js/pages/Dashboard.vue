<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    Plane,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { create as createTour, show as showTour } from '@/routes/tours';

type Check = {
    key: string;
    label: string;
    done: number;
    total: number;
    tab: string;
};

type TourReadiness = {
    id: string;
    name: string;
    status_label: string;
    start_date: string;
    end_date: string;
    days_left: number;
    capacity: number | null;
    registered: number;
    pending: number;
    ungrouped: number;
    passport_issues: number;
    checks: Check[];
};

const props = defineProps<{
    stats: {
        persons: number;
        activeTours: number;
        activeTourLimit: number | null;
        outstanding: Record<string, string>;
    };
    // Ödeme modülü kapalıysa null.
    payments: {
        overdue_count: number;
        overdue: Record<string, string>;
        due_soon_count: number;
    } | null;
    tours: TourReadiness[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ana Panel', href: dashboard() }],
    },
});

/**
 * "Dikkat edilmesi gerekenler": her biri tıklanınca ilgili listeye gider.
 */
type Attention = {
    key: string;
    tone: 'danger' | 'warning';
    text: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

const attention = computed<Attention[]>(() => {
    const items: Attention[] = [];

    if (props.payments && props.payments.overdue_count > 0) {
        const amounts = Object.entries(props.payments.overdue)
            .map(([currency, amount]) => formatMoney(amount, currency))
            .join(' + ');
        items.push({
            key: 'overdue',
            tone: 'danger',
            text: `${props.payments.overdue_count} yolcunun vadesi geçmiş taksit borcu var (${amounts})`,
            href: collectionsIndex({ query: { tab: 'borclu' } }),
        });
    }

    if (props.payments && props.payments.due_soon_count > 0) {
        items.push({
            key: 'due-soon',
            tone: 'warning',
            text: `${props.payments.due_soon_count} yolcunun taksiti önümüzdeki 7 gün içinde`,
            href: collectionsIndex({ query: { tab: 'borclu' } }),
        });
    }

    for (const tour of props.tours) {
        if (tour.passport_issues > 0) {
            items.push({
                key: `passport-${tour.id}`,
                tone: tour.days_left <= 30 ? 'danger' : 'warning',
                text: `${tour.name}: ${tour.passport_issues} yolcunun pasaportu eksik veya 6 aydan kısa geçerli`,
                href: showTour(tour.id),
            });
        }

        if (tour.ungrouped > 0) {
            items.push({
                key: `ungrouped-${tour.id}`,
                tone: 'warning',
                text: `${tour.name}: ${tour.ungrouped} yolcu henüz bir gruba atanmadı`,
                href: showTour(tour.id),
            });
        }
    }

    return items;
});

const progress = (done: number, total: number) =>
    total === 0 ? 100 : Math.round((done / total) * 100);

const daysText = (days: number) =>
    days > 0
        ? `${days} gün kaldı`
        : days === 0
          ? 'Bugün başlıyor'
          : 'Devam ediyor';
</script>

<template>
    <Head title="Ana Panel" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Özet -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Users class="size-4" /> Kayıtlı kişi
                    </CardDescription>
                    <CardTitle class="text-2xl sm:text-3xl">{{
                        stats.persons
                    }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <CalendarDays class="size-4" /> Aktif tur
                    </CardDescription>
                    <CardTitle class="text-2xl sm:text-3xl">
                        {{ stats.activeTours }}
                        <span
                            v-if="stats.activeTourLimit !== null"
                            class="text-base font-normal text-muted-foreground"
                        >
                            / {{ stats.activeTourLimit }}
                        </span>
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card v-if="payments" class="col-span-2 lg:col-span-1">
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Wallet class="size-4" /> Kalan alacak
                    </CardDescription>
                    <CardTitle
                        v-if="Object.keys(stats.outstanding).length === 0"
                        class="text-2xl sm:text-3xl"
                    >
                        —
                    </CardTitle>
                    <CardTitle
                        v-for="(amount, currency) in stats.outstanding"
                        :key="currency"
                        class="text-2xl"
                    >
                        {{ formatMoney(amount, String(currency)) }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Dikkat edilmesi gerekenler -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <AlertTriangle class="size-4" /> Dikkat edilmesi gerekenler
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="attention.length === 0"
                    class="flex items-center gap-2 text-sm text-success"
                >
                    <CheckCircle2 class="size-4" /> Şu an bekleyen bir sorun
                    yok.
                </p>
                <ul v-else class="divide-y">
                    <li v-for="item in attention" :key="item.key">
                        <Link
                            :href="item.href"
                            class="flex items-start gap-2 py-2 text-sm hover:underline"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0"
                                :class="
                                    item.tone === 'danger'
                                        ? 'text-danger'
                                        : 'text-warning'
                                "
                            />
                            {{ item.text }}
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Yaklaşan turlar: hazırlık durumu -->
        <div>
            <h2 class="mb-2 flex items-center gap-2 text-lg font-semibold">
                <Plane class="size-4" /> Yaklaşan turlar
            </h2>
            <Card v-if="tours.length === 0">
                <CardContent class="py-6 text-sm text-muted-foreground">
                    Aktif tur yok.
                    <Link :href="createTour()" class="underline"
                        >Yeni tur oluşturun.</Link
                    >
                </CardContent>
            </Card>
            <div v-else class="grid gap-4 lg:grid-cols-2">
                <Card v-for="tour in tours" :key="tour.id">
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle>
                                    <Link
                                        :href="showTour(tour.id)"
                                        class="hover:underline"
                                    >
                                        {{ tour.name }}
                                    </Link>
                                </CardTitle>
                                <CardDescription
                                    class="mt-1 flex items-center gap-1"
                                >
                                    <CalendarClock class="size-3.5" />
                                    {{ formatDate(tour.start_date) }} –
                                    {{ formatDate(tour.end_date) }}
                                    · {{ daysText(tour.days_left) }}
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">{{
                                tour.status_label
                            }}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <!-- Doluluk -->
                        <div>
                            <div class="flex justify-between">
                                <span>Kayıt</span>
                                <span class="tabular-nums">
                                    {{ tour.registered
                                    }}<template v-if="tour.capacity">
                                        / {{ tour.capacity }}</template
                                    >
                                    <span
                                        v-if="tour.pending"
                                        class="text-xs text-warning"
                                    >
                                        ({{ tour.pending }} ön kayıt)
                                    </span>
                                </span>
                            </div>
                            <div
                                v-if="tour.capacity"
                                class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                :aria-valuenow="tour.registered"
                                :aria-valuemax="tour.capacity"
                                aria-label="Doluluk"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.min(100, progress(tour.registered, tour.capacity))}%`,
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Hazırlık -->
                        <Link
                            v-for="check in tour.checks"
                            :key="check.key"
                            :href="
                                showTour(tour.id, { query: { tab: check.tab } })
                            "
                            class="block rounded-md hover:bg-muted/50"
                        >
                            <div class="flex justify-between">
                                <span>{{ check.label }}</span>
                                <span
                                    class="tabular-nums"
                                    :class="
                                        check.done >= check.total
                                            ? 'text-success'
                                            : 'text-warning'
                                    "
                                >
                                    {{ check.done }} / {{ check.total }}
                                </span>
                            </div>
                            <div
                                class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                :aria-valuenow="check.done"
                                :aria-valuemax="check.total"
                                :aria-label="check.label"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="
                                        check.done >= check.total
                                            ? 'bg-success'
                                            : 'bg-warning'
                                    "
                                    :style="{
                                        width: `${progress(check.done, check.total)}%`,
                                    }"
                                />
                            </div>
                        </Link>

                        <p
                            class="flex items-center gap-1.5"
                            :class="
                                tour.passport_issues > 0
                                    ? 'text-warning'
                                    : 'text-success'
                            "
                        >
                            <AlertTriangle
                                v-if="tour.passport_issues > 0"
                                class="size-4"
                            />
                            <CheckCircle2 v-else class="size-4" />
                            {{
                                tour.passport_issues > 0
                                    ? `${tour.passport_issues} pasaport sorunu`
                                    : 'Pasaportlar tamam'
                            }}
                        </p>
                        <p
                            v-if="tour.checks.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            Otel, otobüs veya uçuş eklendikçe hazırlık durumu
                            burada görünür.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
