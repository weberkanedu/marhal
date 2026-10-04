<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Users, Wallet } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type UpcomingTour = {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
    capacity: number | null;
    registrations_count: number;
};

defineProps<{
    stats: {
        persons: number;
        activeTours: number;
        activeTourLimit: number | null;
        outstanding: Record<string, string>;
    };
    upcomingTours: UpcomingTour[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ana Panel',
                href: dashboard(),
            },
        ],
    },
});

const statusLabels: Record<string, string> = {
    taslak: 'Taslak',
    satista: 'Satışta',
    kapandi: 'Satış kapandı',
    tamamlandi: 'Tamamlandı',
    iptal: 'İptal',
};

function formatMoney(amount: string, currency: string): string {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency,
    }).format(Number(amount));
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Ana Panel" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Users class="size-4" /> Kayıtlı kişi
                    </CardDescription>
                    <CardTitle class="text-3xl">{{ stats.persons }}</CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <CalendarDays class="size-4" /> Aktif tur
                    </CardDescription>
                    <CardTitle class="text-3xl">
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

            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Wallet class="size-4" /> Kalan alacak
                    </CardDescription>
                    <CardTitle
                        v-if="Object.keys(stats.outstanding).length === 0"
                        class="text-3xl"
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

        <Card>
            <CardHeader>
                <CardTitle>Yaklaşan turlar</CardTitle>
                <CardDescription>
                    Bitmemiş ve iptal edilmemiş turlar
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="upcomingTours.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Henüz tur oluşturulmadı.
                </p>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 font-medium">Tur</th>
                            <th class="py-2 font-medium">Tarih</th>
                            <th class="py-2 font-medium">Durum</th>
                            <th class="py-2 text-right font-medium">Kayıt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tour in upcomingTours"
                            :key="tour.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2 font-medium">{{ tour.name }}</td>
                            <td class="py-2">
                                {{ formatDate(tour.start_date) }} –
                                {{ formatDate(tour.end_date) }}
                            </td>
                            <td class="py-2">
                                <Badge variant="secondary">
                                    {{
                                        statusLabels[tour.status] ?? tour.status
                                    }}
                                </Badge>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ tour.registrations_count }}
                                <span v-if="tour.capacity">
                                    / {{ tour.capacity }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
