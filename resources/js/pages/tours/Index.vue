<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, Plus, Users } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/tours';
import { tourStatusVariant } from '@/types/tour';
import type { TourListItem } from '@/types/tour';

defineProps<{
    tours: TourListItem[];
    filter: 'active' | 'past' | 'all';
    limits: { active: number; max: number | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: index() }],
    },
});

const filters = [
    { value: 'active', label: 'Aktif' },
    { value: 'past', label: 'Geçmiş / iptal' },
    { value: 'all', label: 'Tümü' },
] as const;

function occupancy(tour: TourListItem): number | null {
    return tour.capacity
        ? Math.min(
              100,
              Math.round((tour.registrations_count / tour.capacity) * 100),
          )
        : null;
}
</script>

<template>
    <Head title="Turlar" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Turlar</h1>
                <p class="text-sm text-muted-foreground">
                    Aktif tur: {{ limits.active }}
                    <template v-if="limits.max !== null">
                        / {{ limits.max }} (paket sınırı)
                    </template>
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> Yeni tur</Link>
            </Button>
        </div>

        <div class="flex gap-1">
            <Button
                v-for="item in filters"
                :key="item.value"
                size="sm"
                :variant="filter === item.value ? 'secondary' : 'ghost'"
                @click="
                    router.get(
                        index.url(),
                        { filter: item.value },
                        {
                            preserveScroll: true,
                        },
                    )
                "
            >
                {{ item.label }}
            </Button>
        </div>

        <p
            v-if="tours.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Bu listede tur yok.
        </p>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="tour in tours"
                :key="tour.id"
                :href="show(tour.id)"
                class="group"
            >
                <Card
                    class="h-full transition-colors group-hover:border-primary/40"
                >
                    <CardContent class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-semibold">{{ tour.name }}</h2>
                            <Badge :variant="tourStatusVariant[tour.status]">
                                {{ tour.status_label }}
                            </Badge>
                        </div>
                        <p
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <CalendarDays class="size-4" />
                            {{ formatDate(tour.start_date) }} –
                            {{ formatDate(tour.end_date) }}
                        </p>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2">
                                <Users class="size-4 text-muted-foreground" />
                                {{ tour.registrations_count }}
                                <template v-if="tour.capacity">
                                    / {{ tour.capacity }}
                                </template>
                                yolcu
                            </span>
                            <span class="text-muted-foreground">
                                {{ tour.groups_count }} grup
                            </span>
                        </div>
                        <div
                            v-if="occupancy(tour) !== null"
                            class="h-1.5 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${occupancy(tour)}%` }"
                            />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
