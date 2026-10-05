<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronDown,
    HandCoins,
    Plane,
    Plus,
    UserPlus,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import ActivityList from '@/components/dashboard/ActivityList.vue';
import AttentionPanel from '@/components/dashboard/AttentionPanel.vue';
import CollectionChart from '@/components/dashboard/CollectionChart.vue';
import NextTourCard from '@/components/dashboard/NextTourCard.vue';
import ToursStatus from '@/components/dashboard/ToursStatus.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { create as createPerson } from '@/routes/persons';
import { create as createTour } from '@/routes/tours';
import type {
    ActivityItem,
    DashboardPayments,
    DashboardTour,
} from '@/types/dashboard';

/**
 * Ana panel: özet, dikkat edilmesi gerekenler (düğmeli), son hareketler, turların durumu, aylık tahsilat.
 */
const props = defineProps<{
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

const page = usePage();

// "Hayırlı günler, Zeynep": günün saatine göre selam, kullanıcının ilk adı.
const greeting = computed(() => {
    const hour = new Date().getHours();
    const salute =
        hour < 5
            ? 'İyi geceler'
            : hour < 11
              ? 'Günaydın'
              : hour < 18
                ? 'Hayırlı günler'
                : 'İyi akşamlar';
    const name = (page.props.auth.user?.name ?? '').split(' ')[0];

    return name ? `${salute}, ${name}` : salute;
});

const today = new Date().toLocaleDateString('tr-TR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const passengers = computed(() =>
    (page.props.features ?? []).includes('passengers'),
);

// Sıradaki tur: henüz bitmemiş, en erken başlayan (liste başlangıç tarihine göre sıralı gelir).
const nextTour = computed(() => props.tours[0] ?? null);
</script>

<template>
    <Head title="Ana Panel" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ greeting }}
                </h1>
                <p class="text-sm text-muted-foreground first-letter:uppercase">
                    {{ today }}
                </p>
            </div>
            <DropdownMenu v-if="passengers">
                <DropdownMenuTrigger as-child>
                    <Button>
                        <Plus /> Yeni kayıt <ChevronDown class="-mr-1" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuItem as-child>
                        <Link :href="createPerson()"><UserPlus /> Yolcu</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="createTour()"><Plane /> Tur</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="payments" as-child>
                        <Link :href="collectionsIndex()"
                            ><HandCoins /> Ödeme</Link
                        >
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <NextTourCard v-if="nextTour" :tour="nextTour" />

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
