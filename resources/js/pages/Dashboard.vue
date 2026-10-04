<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Users, Wallet } from '@lucide/vue';
import ActivityList from '@/components/dashboard/ActivityList.vue';
import AttentionPanel from '@/components/dashboard/AttentionPanel.vue';
import CollectionChart from '@/components/dashboard/CollectionChart.vue';
import ToursStatus from '@/components/dashboard/ToursStatus.vue';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import type {
    ActivityItem,
    DashboardPayments,
    DashboardTour,
} from '@/types/dashboard';

/**
 * Ana panel: özet, dikkat edilmesi gerekenler (düğmeli), son hareketler, turların durumu, aylık tahsilat.
 */
defineProps<{
    stats: {
        persons: number;
        activeTours: number;
        activeTourLimit: number | null;
        outstanding: Record<string, string>;
    };
    // Ödeme modülü kapalıysa null.
    payments: DashboardPayments | null;
    activity: ActivityItem[];
    trend: {
        months: { key: string; label: string }[];
        series: Record<string, string[]>;
    } | null;
    tours: DashboardTour[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ana Panel', href: dashboard() }],
    },
});
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

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <AttentionPanel :payments="payments" :tours="tours" />
            </div>
            <ActivityList :items="activity" />
        </div>

        <ToursStatus :tours="tours" />

        <CollectionChart
            v-if="trend"
            :months="trend.months"
            :series="trend.series"
        />
    </div>
</template>
