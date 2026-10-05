<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import { Armchair, Pencil, Plane, Plus, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import FlightController from '@/actions/App/Http/Controllers/FlightController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { seatPlan, show as showFlight } from '@/routes/flights';
import { commonAirports, formatFlightTime } from '@/types/flight';
import type { TourFlight } from '@/types/flight';
import type { Option } from '@/types/person';
import type { TourSummary } from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    flights: TourFlight[];
    directions: Option[];
    canUpdate: boolean;
}>();

const open = ref(false);
const editing = ref<TourFlight | null>(null);

const form = computed(() =>
    editing.value
        ? FlightController.update.form(editing.value.id)
        : FlightController.store.form(props.tour.id),
);

// Yeni uçuşta öneri: ilk uçuş gidiş (tur başı), sonraki dönüş (tur sonu).
const isReturn = computed(() => props.flights.length > 0);
const defaults = computed(() =>
    isReturn.value
        ? {
              direction: 'donus',
              from: 'JED',
              to: 'IST',
              at: `${props.tour.end_date}T12:00`,
              arrive: `${props.tour.end_date}T16:00`,
          }
        : {
              direction: 'gidis',
              from: 'IST',
              to: 'JED',
              at: `${props.tour.start_date}T10:00`,
              arrive: `${props.tour.start_date}T14:00`,
          },
);

function openDialog(flight: TourFlight | null): void {
    editing.value = flight;
    open.value = true;
}

function remove(flight: TourFlight): void {
    if (
        confirm(
            `${flight.flight_no} uçuşu silinsin mi? ${flight.passengers_count} yolcunun bu uçuştaki kaydı (PNR, bilet no) da silinir.`,
        )
    ) {
        router.delete(FlightController.destroy.url(flight.id), {
            preserveScroll: true,
        });
    }
}

const directionVariant = (d: string) =>
    d === 'gidis' ? 'default' : d === 'donus' ? 'secondary' : 'outline';
</script>

<template>
    <Card class="min-w-0">
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="flex items-center gap-2">
                <Plane class="size-4" /> Uçuşlar
            </CardTitle>
            <Button
                v-if="canUpdate"
                variant="ghost"
                size="sm"
                @click="openDialog(null)"
            >
                <Plus /> Uçuş ekle
            </Button>
        </CardHeader>
        <CardContent class="text-sm">
            <p v-if="flights.length === 0" class="text-muted-foreground">
                Henüz uçuş eklenmedi.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="flight in flights"
                    :key="flight.id"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2"
                >
                    <Badge
                        :variant="directionVariant(flight.direction)"
                        class="w-20 justify-center"
                    >
                        {{ flight.direction_label }}
                    </Badge>
                    <div class="min-w-48 flex-1">
                        <div class="font-medium">
                            {{ flight.flight_no }} ·
                            {{ flight.departure_airport }} →
                            {{ flight.arrival_airport }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ flight.airline }}
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ formatFlightTime(flight.departure_at) }} →
                            {{ formatFlightTime(flight.arrival_at) }}
                            <template v-if="flight.pnr">
                                · PNR {{ flight.pnr }}
                            </template>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="showFlight(flight.id)">
                            <Users />
                            Yolcular
                            <span class="text-xs text-muted-foreground">
                                {{ flight.passengers_count }}
                            </span>
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="seatPlan(flight.id)">
                            <Armchair /> Koltuk planı
                        </Link>
                    </Button>
                    <div v-if="canUpdate" class="flex">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Düzenle"
                            @click="openDialog(flight)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive"
                            title="Sil"
                            @click="remove(flight)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>

    <datalist id="airport-codes">
        <option
            v-for="airport in commonAirports"
            :key="airport.code"
            :value="airport.code"
        >
            {{ airport.name }}
        </option>
    </datalist>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto">
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="form"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? editing.flight_no : 'Uçuş ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Saatler havalimanının yerel saatiyle yazılır. Yolcuları
                        kaydettikten sonra uçuş sayfasından eklersiniz.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="fl-direction">Yön *</Label>
                        <select
                            id="fl-direction"
                            name="direction"
                            :class="selectClass"
                        >
                            <option
                                v-for="d in directions"
                                :key="d.value"
                                :value="d.value"
                                :selected="
                                    (editing?.direction ??
                                        defaults.direction) === d.value
                                "
                            >
                                {{ d.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-no">Uçuş no *</Label>
                        <Input
                            id="fl-no"
                            name="flight_no"
                            :default-value="editing?.flight_no"
                            placeholder="TK 92"
                            required
                        />
                        <InputError :message="errors.flight_no" />
                    </div>
                    <div class="col-span-2 grid gap-2">
                        <Label for="fl-airline">Havayolu *</Label>
                        <Input
                            id="fl-airline"
                            name="airline"
                            :default-value="
                                editing?.airline ?? 'Türk Hava Yolları'
                            "
                            required
                        />
                        <InputError :message="errors.airline" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-from">Kalkış *</Label>
                        <Input
                            id="fl-from"
                            name="departure_airport"
                            list="airport-codes"
                            maxlength="3"
                            class="uppercase"
                            :default-value="
                                editing?.departure_airport ?? defaults.from
                            "
                            required
                        />
                        <InputError :message="errors.departure_airport" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-to">Varış *</Label>
                        <Input
                            id="fl-to"
                            name="arrival_airport"
                            list="airport-codes"
                            maxlength="3"
                            class="uppercase"
                            :default-value="
                                editing?.arrival_airport ?? defaults.to
                            "
                            required
                        />
                        <InputError :message="errors.arrival_airport" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-dep">Kalkış saati *</Label>
                        <Input
                            id="fl-dep"
                            name="departure_at"
                            type="datetime-local"
                            :default-value="
                                editing?.departure_at ?? defaults.at
                            "
                            required
                        />
                        <InputError :message="errors.departure_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-arr">Varış saati *</Label>
                        <Input
                            id="fl-arr"
                            name="arrival_at"
                            type="datetime-local"
                            :default-value="
                                editing?.arrival_at ?? defaults.arrive
                            "
                            required
                        />
                        <InputError :message="errors.arrival_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-pnr">Grup PNR</Label>
                        <Input
                            id="fl-pnr"
                            name="pnr"
                            class="uppercase"
                            :default-value="editing?.pnr ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fl-baggage">Bagaj</Label>
                        <Input
                            id="fl-baggage"
                            name="baggage"
                            :default-value="editing?.baggage ?? undefined"
                            placeholder="Örn. 30 kg + 8 kg kabin"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">Kaydet</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
