<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BedDouble, Bus, FileText, IdCard, Plane, Users } from '@lucide/vue';
import { computed } from 'vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { passengers as busPassengers, seatChart } from '@/routes/reports/buses';
import { manifest } from '@/routes/reports/flights';
import { roomOccupancy, roomingList } from '@/routes/reports/stays';
import {
    badges,
    passengers as tourPassengers,
    payments as tourPayments,
} from '@/routes/reports/tours';
import type { TourBus } from '@/types/bus';
import type { FeatureKey } from '@/types/tenant';
import type { TourFlight } from '@/types/flight';
import type { TourStay } from '@/types/hotel';
import type { TourGroup, TourSummary } from '@/types/tour';

/**
 * Tur sayfasının "Çıktılar" sekmesi: turun bütün Excel / PDF listeleri tek yerde.
 * Her liste yalnızca modülü açıksa ve kullanıcının yetkisi varsa görünür (sunucu da ayrıca denetler).
 */
const props = defineProps<{
    tour: TourSummary;
    groups: TourGroup[];
    stays: TourStay[] | null;
    buses: TourBus[] | null;
    flights: TourFlight[] | null;
    can: { update: boolean; viewFinance: boolean };
}>();

const page = usePage();
const has = (feature: FeatureKey) =>
    (page.props.features ?? []).includes(feature);

const showBadges = computed(() => has('badge_generation') && props.can.update);
// Otel / otobüs / uçuş listeleri personele (rehber yalnız yolcu listesini alır).
const staffLists = computed(() => props.can.update);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="size-4" /> Yolcu listeleri
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 text-sm">
                <ExportButtons
                    v-if="can.update"
                    :url="tourPassengers.url(tour.id)"
                    label="Tüm tur"
                />
                <ExportButtons
                    v-for="group in groups"
                    :key="group.id"
                    :url="
                        tourPassengers.url(tour.id, {
                            query: { group: group.id },
                        })
                    "
                    :label="group.name"
                />
                <ExportButtons
                    v-if="has('payments') && can.viewFinance"
                    :url="tourPayments.url(tour.id)"
                    label="Ödeme durumu"
                />
            </CardContent>
        </Card>

        <Card v-if="showBadges">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <IdCard class="size-4" /> Yaka kartları (PDF)
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2 text-sm">
                <Button variant="outline" size="sm" as-child>
                    <a :href="badges.url(tour.id)"><FileText /> Tüm tur</a>
                </Button>
                <Button
                    v-for="group in groups"
                    :key="group.id"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <a
                        :href="
                            badges.url(tour.id, { query: { group: group.id } })
                        "
                    >
                        <FileText /> {{ group.name }}
                    </a>
                </Button>
                <p class="w-full text-xs text-muted-foreground">
                    Tek yolcunun kartı: Yolcular sekmesinde satırdaki kart
                    simgesi.
                </p>
            </CardContent>
        </Card>

        <Card v-if="staffLists && stays !== null && stays.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BedDouble class="size-4" /> Oteller
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 text-sm">
                <div v-for="stay in stays" :key="stay.id" class="space-y-1">
                    <p class="font-medium">
                        {{ stay.city_label }} — {{ stay.hotel_name }}
                    </p>
                    <ExportButtons
                        :url="roomingList.url(stay.id)"
                        label="Otel oda listesi"
                    />
                    <ExportButtons
                        :url="roomOccupancy.url(stay.id)"
                        label="Doluluk özeti"
                    />
                </div>
            </CardContent>
        </Card>

        <Card v-if="staffLists && buses !== null && buses.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Bus class="size-4" /> Otobüsler
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 text-sm">
                <div v-for="bus in buses" :key="bus.id" class="space-y-1">
                    <p class="font-medium">{{ bus.name }}</p>
                    <ExportButtons
                        :url="busPassengers.url(bus.id)"
                        label="Yolcu listesi"
                    />
                    <Button variant="outline" size="sm" as-child>
                        <a :href="seatChart.url(bus.id)">
                            <FileText /> Koltuk planı (PDF)
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="staffLists && flights !== null && flights.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Plane class="size-4" /> Uçuşlar
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 text-sm">
                <ExportButtons
                    v-for="flight in flights"
                    :key="flight.id"
                    :url="manifest.url(flight.id)"
                    :label="`${flight.flight_no} · ${flight.departure_airport} → ${flight.arrival_airport}`"
                />
            </CardContent>
        </Card>
    </div>
</template>
