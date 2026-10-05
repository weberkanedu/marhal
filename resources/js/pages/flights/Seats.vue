<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    Eraser,
    GripVertical,
    Plane,
    Search,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FlightSeatPlanController from '@/actions/App/Http/Controllers/FlightSeatPlanController';
import ExportMenu from '@/components/ExportMenu.vue';
import PlaneDiagram from '@/components/flights/PlaneDiagram.vue';
import PersonAvatar from '@/components/persons/PersonAvatar.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { selectClass } from '@/lib/formClasses';
import { index as aircraftTypesIndex } from '@/routes/aircraft-types';
import { show as showFlight } from '@/routes/flights';
import { manifest, seats as seatReport } from '@/routes/reports/flights';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type { ExportItem } from '@/types/export';
import type { Gender } from '@/types/person';

type Passenger = {
    id: string;
    full_name: string;
    gender: Gender;
    age: number | null;
    group_name: string | null;
    seat_no: string | null;
    warnings: string[];
};

const props = defineProps<{
    flight: {
        id: string;
        title: string;
        airline: string;
        departure_at: string;
        tour: { id: string; name: string };
        aircraft_type_id: string | null;
        aircraft: string | null;
    };
    cabin: {
        groups: string[][];
        rows: number[];
        exit_rows: number[];
        label: string;
        blocked: string[];
    } | null;
    passengers: Passenger[];
    units: { label: string | null; ids: string[] }[];
    aircraftTypes: { id: string; name: string; label: string }[];
    presets: { key: string; name: string; cabin: string }[];
    can: { update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const bySeat = computed(
    () =>
        new Map(
            props.passengers
                .filter((p) => p.seat_no)
                .map((p) => [p.seat_no as string, p]),
        ),
);
const byId = computed(() => new Map(props.passengers.map((p) => [p.id, p])));
const occupant = (seat: string) => bySeat.value.get(seat);
const isBlocked = (seat: string) =>
    props.cabin?.blocked.includes(seat) ?? false;
const isExit = (row: number) => props.cabin?.exit_rows.includes(row) ?? false;

const seatedCount = computed(() => bySeat.value.size);
const warnings = computed(() =>
    props.passengers.flatMap((p) =>
        p.warnings.map((w) => ({
            seat: p.seat_no,
            name: p.full_name,
            text: w,
        })),
    ),
);

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

// Uçak tipi: acentenin tipi ("t:<id>") ya da hazır tip ("p:<key>").
const aircraftChoice = ref(
    props.flight.aircraft_type_id ? `t:${props.flight.aircraft_type_id}` : '',
);

function chooseAircraft(): void {
    const [kind, value] = aircraftChoice.value.split(':');

    if (!value) {
        return;
    }

    router.put(
        FlightSeatPlanController.aircraft.url(props.flight.id),
        kind === 't' ? { aircraft_type_id: value } : { preset: value },
        { preserveScroll: true, onError: showError },
    );
}

// Seçim (tıklayarak), sürükleme ve "gri koltuk" modu.
const selected = ref<Passenger | null>(null);
const dragging = ref<Passenger | null>(null);
const overSeat = ref<string | null>(null);
const overPool = ref(false);
const blockMode = ref(false);

function assign(who: Passenger, seat: string): void {
    router.post(
        FlightSeatPlanController.assign.url(props.flight.id),
        { passenger_id: who.id, seat, swap: who.seat_no !== null },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

function unseat(who: Passenger | null): void {
    if (!who?.seat_no) {
        return;
    }

    router.delete(FlightSeatPlanController.unassign.url(who.id), {
        preserveScroll: true,
        onSuccess: () => (selected.value = null),
        onError: showError,
    });
}

function clickSeat(seat: string): void {
    if (!props.can.update) {
        return;
    }

    if (blockMode.value) {
        router.post(
            FlightSeatPlanController.block.url(props.flight.id),
            { seat },
            { preserveScroll: true, onError: showError },
        );

        return;
    }

    if (isBlocked(seat)) {
        return;
    }

    const current = occupant(seat);

    if (current) {
        if (selected.value?.seat_no && selected.value.id !== current.id) {
            assign(selected.value, seat);

            return;
        }

        selected.value = selected.value?.id === current.id ? null : current;

        return;
    }

    if (selected.value) {
        assign(selected.value, seat);
    }
}

function canDropOn(seat: string): boolean {
    const who = dragging.value;

    if (!who || isBlocked(seat) || who.seat_no === seat) {
        return false;
    }

    return !occupant(seat) || who.seat_no !== null;
}

function startDrag(event: DragEvent, who: Passenger): void {
    if (!props.can.update || blockMode.value) {
        return;
    }

    dragging.value = who;
    event.dataTransfer?.setData('text/plain', who.id);
}

function endDrag(): void {
    dragging.value = null;
    overSeat.value = null;
    overPool.value = false;
}

function dropOnSeat(seat: string): void {
    if (dragging.value && canDropOn(seat)) {
        assign(dragging.value, seat);
    }

    endDrag();
}

function dropOnPool(): void {
    unseat(dragging.value);
    endDrag();
}

function clearPlan(): void {
    if (confirm('Bu uçuştaki bütün yolcuların koltuk seçimi kaldırılsın mı?')) {
        router.delete(FlightSeatPlanController.clear.url(props.flight.id), {
            preserveScroll: true,
            onError: showError,
        });
    }
}

// Havuz (koltuğu olmayanlar), aileler bir arada.
const search = ref('');
const normalize = (value: string) => value.toLocaleLowerCase('tr');
const pool = computed(() =>
    props.units
        .map((unit) => ({
            label: unit.label,
            people: unit.ids
                .map((id) => byId.value.get(id))
                .filter((p): p is Passenger => p !== undefined)
                .filter((p) =>
                    normalize(p.full_name).includes(normalize(search.value)),
                ),
        }))
        .filter((unit) => unit.people.length > 0),
);

const exportItems = computed<ExportItem[]>(() =>
    props.can.update
        ? [
              {
                  title: 'Koltuk tercih listesi',
                  description: 'Havayoluna gönderilecek, koltuk sırasıyla',
                  url: seatReport.url(props.flight.id),
              },
              {
                  title: 'Havayolu listesi',
                  description: 'Yolcu, pasaport, PNR',
                  url: manifest.url(props.flight.id),
              },
          ]
        : [],
);

const shortName = (name: string) => {
    const parts = name.split(' ');

    return parts.length > 1 ? `${parts[0]} ${parts.at(-1)?.charAt(0)}.` : name;
};
</script>

<template>
    <Head :title="`Koltuk planı — ${flight.title}`" />

    <div class="flex w-full flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="showFlight(flight.id)"
                    class="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> {{ flight.title }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Uçak koltuk planı
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <Link
                        :href="
                            showTour(flight.tour.id, {
                                query: { tab: 'ulasim' },
                            })
                        "
                        class="hover:text-foreground"
                        >{{ flight.tour.name }}</Link
                    >
                    · {{ flight.airline }}
                    <template v-if="cabin">
                        · {{ flight.aircraft }} · {{ cabin.label }}
                    </template>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Bu plan havayoluna gönderilen koltuk tercih listesidir;
                    kesin koltuğu havayolu verir.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <ExportMenu :items="exportItems" />
                <Button
                    v-if="can.update && cabin"
                    variant="outline"
                    :disabled="seatedCount === 0"
                    @click="clearPlan"
                >
                    <Eraser /> Temizle
                </Button>
            </div>
        </div>

        <!-- Uçak tipi -->
        <Card v-if="can.update" class="py-4">
            <CardContent class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1">
                    <label for="aircraft" class="text-xs text-muted-foreground"
                        >Uçak tipi</label
                    >
                    <select
                        id="aircraft"
                        v-model="aircraftChoice"
                        :class="selectClass"
                        class="w-72"
                        @change="chooseAircraft"
                    >
                        <option value="" disabled>Seçin…</option>
                        <optgroup
                            v-if="aircraftTypes.length"
                            label="Acentenin uçak tipleri"
                        >
                            <option
                                v-for="t in aircraftTypes"
                                :key="t.id"
                                :value="`t:${t.id}`"
                            >
                                {{ t.name }} ({{ t.label }})
                            </option>
                        </optgroup>
                        <optgroup label="Hazır tipler">
                            <option
                                v-for="p in presets.filter(
                                    (p) =>
                                        !aircraftTypes.some(
                                            (t) => t.name === p.name,
                                        ),
                                )"
                                :key="p.key"
                                :value="`p:${p.key}`"
                            >
                                {{ p.name }} ({{ p.cabin }})
                            </option>
                        </optgroup>
                    </select>
                </div>
                <p class="text-xs text-muted-foreground">
                    Sıra aralığı ve acil çıkışlar
                    <Link :href="aircraftTypesIndex()" class="underline"
                        >Acente ayarları → Uçak tipleri</Link
                    >'nden değiştirilebilir.
                </p>
                <Button
                    v-if="cabin"
                    class="ml-auto"
                    :variant="blockMode ? 'default' : 'outline'"
                    @click="blockMode = !blockMode"
                >
                    <Ban />
                    {{
                        blockMode
                            ? 'Bitti'
                            : 'Başkasına ait koltukları işaretle'
                    }}
                </Button>
            </CardContent>
        </Card>

        <div
            v-if="!cabin"
            class="flex flex-col items-center gap-2 rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <Plane class="size-8" />
            Koltuk planı için önce uçak tipini seçin.
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <div class="flex min-w-60 flex-1 items-center gap-3">
                    <ProgressBar
                        class="flex-1"
                        :value="seatedCount"
                        :max="passengers.length"
                    />
                    <span class="text-muted-foreground tabular-nums">
                        {{ seatedCount }} / {{ passengers.length }} koltuk
                        seçildi
                    </span>
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5"
                        ><i class="inline-block size-3 rounded-full bg-women" />
                        Kadın</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><i class="inline-block size-3 rounded-full bg-men" />
                        Erkek</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><i
                            class="inline-block size-3 rounded bg-muted-foreground/40"
                        />
                        Başka yolcu</span
                    >
                    <span class="flex items-center gap-1.5 text-danger"
                        >Acil çıkış: 15 yaş altı ve 65 yaş üstü oturamaz</span
                    >
                </div>
            </div>

            <div
                v-if="selected"
                class="sticky top-2 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/40 bg-popover p-3 text-sm shadow-lg"
            >
                <span>
                    <strong>{{ selected.full_name }}</strong> seçildi — boş bir
                    koltuğa tıklayın.
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="selected.seat_no"
                        size="sm"
                        variant="outline"
                        class="text-destructive"
                        @click="unseat(selected)"
                    >
                        Koltuğu kaldır
                    </Button>
                    <Button size="sm" variant="ghost" @click="selected = null">
                        <X /> Vazgeç
                    </Button>
                </div>
            </div>

            <div class="grid min-w-0 gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                <Card
                    class="h-fit min-w-0 lg:sticky lg:top-2"
                    :class="{ 'ring-2 ring-primary': overPool }"
                    @dragover.prevent="overPool = dragging?.seat_no !== null"
                    @dragleave="overPool = false"
                    @drop.prevent="dropOnPool"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="size-4" /> Koltuğu seçilmeyenler
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >{{ passengers.length - seatedCount }}</span
                            >
                        </CardTitle>
                        <CardDescription v-if="can.update">
                            Yolcuyu koltuğa sürükleyin ya da önce yolcuya, sonra
                            koltuğa tıklayın.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 text-sm">
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
                        <p
                            v-if="passengers.length === 0"
                            class="rounded-lg border border-dashed p-4 text-center text-muted-foreground"
                        >
                            Uçuşta yolcu yok.
                            <Link
                                :href="showFlight(flight.id)"
                                class="underline"
                                >Yolcu ekleyin</Link
                            >.
                        </p>
                        <p
                            v-else-if="pool.length === 0 && !search"
                            class="rounded-lg border border-dashed p-4 text-center text-muted-foreground"
                        >
                            Herkesin koltuğu seçildi ✓
                        </p>
                        <div
                            class="flex max-h-[35vh] flex-col gap-2 overflow-y-auto lg:max-h-[60vh]"
                        >
                            <div
                                v-for="(unit, index) in pool"
                                :key="index"
                                class="flex flex-col gap-1"
                                :class="
                                    unit.label
                                        ? 'rounded-xl border border-dashed border-primary/40 p-1.5'
                                        : ''
                                "
                            >
                                <small
                                    v-if="unit.label"
                                    class="px-1 pt-1 text-[10.5px] tracking-wider text-muted-foreground uppercase"
                                    >{{ unit.label }}</small
                                >
                                <button
                                    v-for="p in unit.people"
                                    :key="p.id"
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded-lg border px-2 py-1.5 text-left hover:border-primary"
                                    :class="{
                                        'border-primary ring-2 ring-primary/30':
                                            selected?.id === p.id,
                                        'cursor-grab': can.update,
                                    }"
                                    :draggable="can.update"
                                    :disabled="!can.update"
                                    @click="
                                        selected =
                                            selected?.id === p.id ? null : p
                                    "
                                    @dragstart="startDrag($event, p)"
                                    @dragend="endDrag"
                                >
                                    <GripVertical
                                        v-if="can.update"
                                        class="size-3.5 shrink-0 text-muted-foreground"
                                    />
                                    <PersonAvatar
                                        :name="p.full_name"
                                        :gender="p.gender"
                                        size="sm"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate font-medium"
                                            >{{ p.full_name }}</span
                                        >
                                        <span
                                            class="block truncate text-xs text-muted-foreground"
                                        >
                                            {{ p.group_name }}
                                            <template v-if="p.age !== null"
                                                >· {{ p.age }} yaş</template
                                            >
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex min-w-0 flex-col gap-3">
                    <div class="overflow-x-auto pb-2">
                        <PlaneDiagram
                            :groups="cabin.groups"
                            :rows="cabin.rows"
                            :exit-rows="cabin.exit_rows"
                        >
                            <template #seat="{ seat, row }">
                                <button
                                    type="button"
                                    class="flex h-9 w-full flex-col items-center justify-center rounded-t-lg rounded-b-sm border text-[10px] leading-tight transition"
                                    :class="{
                                        'cursor-not-allowed border-transparent bg-muted-foreground/25 text-muted-foreground':
                                            isBlocked(seat),
                                        'border-danger/50': isExit(row),
                                        'bg-background/70 hover:bg-muted':
                                            !isBlocked(seat) && !occupant(seat),
                                        'border-women/60 bg-women/15':
                                            occupant(seat)?.gender === 'kadin',
                                        'border-men/60 bg-men/15':
                                            occupant(seat)?.gender === 'erkek',
                                        'ring-2 ring-primary':
                                            overSeat === seat ||
                                            (selected !== null &&
                                                occupant(seat)?.id ===
                                                    selected.id),
                                        'cursor-pointer': blockMode,
                                    }"
                                    :disabled="!can.update"
                                    :draggable="
                                        can.update &&
                                        !blockMode &&
                                        !!occupant(seat)
                                    "
                                    :aria-label="`Koltuk ${seat}${occupant(seat) ? ': ' + occupant(seat)?.full_name : isBlocked(seat) ? ' (başka yolcu)' : ''}${isExit(row) ? ' (acil çıkış)' : ''}`"
                                    :title="
                                        occupant(seat)?.full_name ??
                                        (isBlocked(seat) ? 'Başka yolcu' : seat)
                                    "
                                    @click="clickSeat(seat)"
                                    @dragstart="
                                        occupant(seat) &&
                                        startDrag(
                                            $event,
                                            occupant(seat) as Passenger,
                                        )
                                    "
                                    @dragend="endDrag"
                                    @dragover="
                                        canDropOn(seat) &&
                                        ($event.preventDefault(),
                                        (overSeat = seat))
                                    "
                                    @dragleave="overSeat = null"
                                    @drop.prevent="dropOnSeat(seat)"
                                >
                                    <span class="font-semibold">
                                        {{ seat }}
                                        <AlertTriangle
                                            v-if="
                                                occupant(seat)?.warnings.length
                                            "
                                            class="inline size-2.5 text-warning"
                                        />
                                    </span>
                                    <span
                                        v-if="occupant(seat)"
                                        class="w-full truncate px-0.5 text-center"
                                        >{{
                                            shortName(
                                                occupant(seat)?.full_name ?? '',
                                            )
                                        }}</span
                                    >
                                </button>
                            </template>
                        </PlaneDiagram>
                    </div>

                    <Card v-if="warnings.length">
                        <CardHeader>
                            <CardTitle
                                class="flex items-center gap-2 text-warning"
                            >
                                <AlertTriangle class="size-4" /> Uyarılar
                            </CardTitle>
                            <CardDescription>
                                Engellemez; havayolu acil çıkış kuralına uymayan
                                yolcuyu kabul etmez.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul class="list-disc space-y-1 pl-5 text-sm">
                                <li
                                    v-for="w in warnings"
                                    :key="`${w.seat}${w.text}`"
                                >
                                    {{ w.seat }} — {{ w.name }}: {{ w.text }}
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>
    </div>
</template>
