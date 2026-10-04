<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Plus,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FlightPassengerController from '@/actions/App/Http/Controllers/FlightPassengerController';
import ExportButtons from '@/components/ExportButtons.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { manifest } from '@/routes/reports/flights';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import { formatFlightTime } from '@/types/flight';
import type { FlightPassengerRow, FlightSummary } from '@/types/flight';

const props = defineProps<{
    flight: FlightSummary & { tour: { id: string; name: string } };
    passengers: FlightPassengerRow[];
    groups: { id: string; name: string; missing: number }[];
    candidates: { id: string; full_name: string; group_name: string | null }[];
    can: { update: boolean; reports: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const warningCount = computed(
    () => props.passengers.filter((p) => p.warnings.length > 0).length,
);

// PNR / bilet no: alan değiştirilip çıkılınca kaydedilir.
function save(
    row: FlightPassengerRow,
    field: 'pnr' | 'ticket_no',
    value: string,
): void {
    if ((row[field] ?? '') === value.trim()) {
        return;
    }

    router.put(
        FlightPassengerController.update.url(row.id),
        {
            pnr: field === 'pnr' ? value : row.pnr,
            ticket_no: field === 'ticket_no' ? value : row.ticket_no,
        },
        {
            preserveScroll: true,
            onSuccess: () => toast.success(`${row.full_name}: kaydedildi.`),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Kaydedilemedi.'),
        },
    );
}

function remove(row: FlightPassengerRow): void {
    if (confirm(`${row.full_name} bu uçuştan çıkarılsın mı?`)) {
        router.delete(FlightPassengerController.destroy.url(row.id), {
            preserveScroll: true,
        });
    }
}

// Yolcu ekleme
const addOpen = ref(false);
const selectedGroups = ref<string[]>([]);
const selectedPeople = ref<string[]>([]);
const search = ref('');
const filteredCandidates = computed(() =>
    props.candidates.filter((c) =>
        c.full_name
            .toLocaleLowerCase('tr')
            .includes(search.value.toLocaleLowerCase('tr')),
    ),
);

function openAdd(): void {
    selectedGroups.value = props.groups
        .filter((g) => g.missing > 0)
        .map((g) => g.id);
    selectedPeople.value = [];
    search.value = '';
    addOpen.value = true;
}

function add(): void {
    router.post(
        FlightPassengerController.store.url(props.flight.id),
        {
            group_ids: selectedGroups.value,
            registration_ids: selectedPeople.value,
        },
        { preserveScroll: true, onSuccess: () => (addOpen.value = false) },
    );
}
</script>

<template>
    <Head :title="`${flight.flight_no} yolcuları`" />

    <div class="flex w-full flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="showTour(flight.tour.id)"
                    class="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> {{ flight.tour.name }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ flight.flight_no }} · {{ flight.departure_airport }} →
                    {{ flight.arrival_airport }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ flight.direction_label }} · {{ flight.airline }} ·
                    {{ formatFlightTime(flight.departure_at) }} →
                    {{ formatFlightTime(flight.arrival_at) }}
                    <template v-if="flight.pnr">
                        · Grup PNR {{ flight.pnr }}</template
                    >
                    <template v-if="flight.baggage">
                        · Bagaj {{ flight.baggage }}</template
                    >
                </p>
            </div>
            <Button v-if="can.update" @click="openAdd"
                ><Plus /> Yolcu ekle</Button
            >
        </div>

        <div v-if="can.reports" class="flex flex-wrap items-center gap-4">
            <ExportButtons
                :url="manifest.url(flight.id)"
                label="Havayolu yolcu listesi"
            />
        </div>

        <p
            v-if="warningCount > 0"
            class="flex items-center gap-2 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
        >
            <AlertTriangle class="size-4 shrink-0" />
            {{ warningCount }} yolcunun pasaport bilgisinde sorun var (aşağıda
            işaretli). Bilet kesilmeden önce düzeltin.
        </p>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <div class="border-b px-4 py-3 text-sm font-medium">
                    {{ passengers.length }} yolcu
                </div>
                <div
                    v-if="passengers.length === 0"
                    class="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground"
                >
                    <Users class="size-8" />
                    Bu uçuşta henüz yolcu yok.
                    <Button v-if="can.update" size="sm" @click="openAdd">
                        <Plus /> Yolcu ekle
                    </Button>
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Unvan</th>
                            <th class="px-3 py-2 font-medium">Yolcu</th>
                            <th
                                v-if="can.reports"
                                class="px-3 py-2 font-medium"
                            >
                                Pasaport
                            </th>
                            <th class="px-3 py-2 font-medium">PNR</th>
                            <th class="px-3 py-2 font-medium">Bilet no</th>
                            <th class="w-0 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in passengers"
                            :key="row.id"
                            class="border-t align-top"
                        >
                            <td class="px-3 py-2">
                                <Badge variant="outline">{{ row.title }}</Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium">
                                    {{ row.full_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.group_name }}
                                </div>
                                <div
                                    v-for="w in row.warnings"
                                    :key="w"
                                    class="flex items-center gap-1 text-xs text-warning"
                                >
                                    <AlertTriangle class="size-3" /> {{ w }}
                                </div>
                            </td>
                            <td
                                v-if="can.reports"
                                class="px-3 py-2 whitespace-nowrap"
                            >
                                {{ row.masked_passport_no ?? '—' }}
                                <div
                                    v-if="row.passport_expiry"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(row.passport_expiry) }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <Input
                                    v-if="can.update"
                                    :default-value="row.pnr ?? ''"
                                    :placeholder="flight.pnr ?? '—'"
                                    class="h-8 w-28 uppercase"
                                    :aria-label="`${row.full_name} PNR`"
                                    @change="
                                        save(
                                            row,
                                            'pnr',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                                <template v-else>{{
                                    row.pnr ?? flight.pnr ?? '—'
                                }}</template>
                            </td>
                            <td class="px-3 py-2">
                                <Input
                                    v-if="can.update"
                                    :default-value="row.ticket_no ?? ''"
                                    class="h-8 w-36"
                                    :aria-label="`${row.full_name} bilet no`"
                                    @change="
                                        save(
                                            row,
                                            'ticket_no',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                                <template v-else>{{
                                    row.ticket_no ?? '—'
                                }}</template>
                            </td>
                            <td class="px-2 py-2">
                                <Button
                                    v-if="can.update"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive"
                                    title="Uçuştan çıkar"
                                    @click="remove(row)"
                                >
                                    <Trash2 />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
        <p
            v-if="can.update && passengers.length"
            class="text-xs text-muted-foreground"
        >
            Kişisel PNR boş bırakılırsa uçuşun grup PNR'ı kullanılır. Kutudan
            çıkınca kaydedilir.
        </p>
    </div>

    <Dialog v-model:open="addOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Uçuşa yolcu ekle</DialogTitle>
                <DialogDescription>
                    Grupları toplu ekleyin veya yolcuları tek tek seçin. Aynı
                    saatlerde başka uçuşta olanlar eklenmez.
                </DialogDescription>
            </DialogHeader>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Gruplar</legend>
                <label
                    v-for="group in groups"
                    :key="group.id"
                    class="flex items-center gap-2 text-sm"
                    :class="{ 'opacity-50': group.missing === 0 }"
                >
                    <input
                        v-model="selectedGroups"
                        type="checkbox"
                        :value="group.id"
                        :disabled="group.missing === 0"
                    />
                    {{ group.name }}
                    <span class="text-xs text-muted-foreground">
                        {{
                            group.missing === 0
                                ? '(hepsi uçuşta)'
                                : `(${group.missing} yolcu eklenecek)`
                        }}
                    </span>
                </label>
            </fieldset>

            <div v-if="candidates.length" class="grid gap-2">
                <p class="text-sm font-medium">Tek tek yolcu</p>
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="İsimle ara"
                        class="pl-8"
                    />
                </div>
                <ul class="max-h-48 overflow-y-auto rounded-md border text-sm">
                    <li v-for="c in filteredCandidates" :key="c.id">
                        <label
                            class="flex items-center gap-2 px-3 py-1.5 hover:bg-muted"
                        >
                            <input
                                v-model="selectedPeople"
                                type="checkbox"
                                :value="c.id"
                            />
                            {{ c.full_name }}
                            <span class="text-xs text-muted-foreground">{{
                                c.group_name ?? 'Grupsuz'
                            }}</span>
                        </label>
                    </li>
                </ul>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="addOpen = false">Vazgeç</Button>
                <Button
                    :disabled="
                        selectedGroups.length === 0 &&
                        selectedPeople.length === 0
                    "
                    @click="add"
                >
                    Ekle
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
