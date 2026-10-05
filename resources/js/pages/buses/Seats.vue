<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Eraser,
    GripVertical,
    Search,
    Users,
    Wand2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SeatAssignmentController from '@/actions/App/Http/Controllers/SeatAssignmentController';
import SeatPlanController from '@/actions/App/Http/Controllers/SeatPlanController';
import BusDiagram from '@/components/buses/BusDiagram.vue';
import ExportMenu from '@/components/ExportMenu.vue';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    passengers as passengerReport,
    seatChart,
} from '@/routes/reports/buses';
import { drivers as driverReport } from '@/routes/reports/tours';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type {
    BusCell,
    OtherSeatPassenger,
    SeatAutoPreview,
    SeatOccupant,
    SeatPlanBus,
    SeatPlanStats,
    SeatPoolUnit,
    UnseatedPassenger,
} from '@/types/bus';
import type { ExportItem } from '@/types/export';

const props = defineProps<{
    bus: SeatPlanBus;
    grid: BusCell[][];
    seats: Record<string, SeatOccupant>;
    unassigned: UnseatedPassenger[];
    units: SeatPoolUnit[];
    others: OtherSeatPassenger[];
    stats: SeatPlanStats;
    can: { update: boolean; reports: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const occupant = (seat: number): SeatOccupant | undefined =>
    props.seats[String(seat)];
const isReserved = (seat: number) => props.bus.reserved.includes(seat);
const inFront = (seat: number) => props.bus.front_zone.includes(seat);

type Picked = {
    registration_id: string;
    full_name: string;
    seat_id: string | null;
    seat_no: number | null;
};

// Seçim (tıklayarak yerleştirme; telefon ve klavye için) ve sürüklenen yolcu.
const selected = ref<Picked | null>(null);
const dragging = ref<Picked | null>(null);
const overSeat = ref<number | null>(null);
const overPool = ref(false);

function pick(p: { registration_id: string; full_name: string }): void {
    if (!props.can.update) {
        return;
    }

    selected.value =
        selected.value?.registration_id === p.registration_id
            ? null
            : {
                  registration_id: p.registration_id,
                  full_name: p.full_name,
                  seat_id: null,
                  seat_no: null,
              };
}

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

function assign(who: Picked, seat: number): void {
    router.post(
        SeatAssignmentController.store.url(props.bus.id),
        {
            registration_id: who.registration_id,
            seat_no: seat,
            // Oturan yolcu dolu koltuğa bırakılırsa yer değiştirir.
            swap: who.seat_id !== null,
        },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

function unseat(who: Picked | null): void {
    if (!who?.seat_id) {
        return;
    }

    router.delete(SeatAssignmentController.destroy.url(who.seat_id), {
        preserveScroll: true,
        onSuccess: () => (selected.value = null),
        onError: showError,
    });
}

function clickSeat(seat: number): void {
    if (!props.can.update || isReserved(seat)) {
        return;
    }

    const current = occupant(seat);

    if (current && current.registration_id !== null) {
        // Seçili oturan yolcu başka bir dolu koltuğa tıklarsa yer değiştirir.
        if (
            selected.value?.seat_id &&
            selected.value.registration_id !== current.registration_id
        ) {
            assign(selected.value, seat);

            return;
        }

        selected.value =
            selected.value?.registration_id === current.registration_id
                ? null
                : {
                      registration_id: current.registration_id,
                      full_name: current.full_name,
                      seat_id: current.seat_id,
                      seat_no: seat,
                  };

        return;
    }

    if (!current && selected.value) {
        assign(selected.value, seat);
    }
}

// Sürükle-bırak (fare ile)
function startDrag(event: DragEvent, who: Picked): void {
    if (!props.can.update) {
        return;
    }

    dragging.value = who;
    event.dataTransfer?.setData('text/plain', who.registration_id);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function endDrag(): void {
    dragging.value = null;
    overSeat.value = null;
    overPool.value = false;
}

function canDropOn(seat: number): boolean {
    const who = dragging.value;

    if (!who || isReserved(seat) || who.seat_no === seat) {
        return false;
    }

    const current = occupant(seat);

    // Boş koltuk ya da (oturan yolcu için) yer değiştirme.
    return (
        !current || (who.seat_id !== null && current.registration_id !== null)
    );
}

function dropOnSeat(seat: number): void {
    if (dragging.value && canDropOn(seat)) {
        assign(dragging.value, seat);
    }

    endDrag();
}

function dropOnPool(): void {
    unseat(dragging.value);
    endDrag();
}

// Havuz: aile kümeleri bir arada; arama süzer.
const search = ref('');
const normalize = (value: string) => value.toLocaleLowerCase('tr');
const byId = computed(
    () => new Map(props.unassigned.map((p) => [p.registration_id, p])),
);
const pool = computed(() =>
    props.units
        .map((unit) => ({
            label: unit.label,
            people: unit.ids
                .map((id) => byId.value.get(id))
                .filter((p): p is UnseatedPassenger => p !== undefined)
                .filter((p) =>
                    normalize(p.full_name).includes(normalize(search.value)),
                ),
        }))
        .filter((unit) => unit.people.length > 0),
);
const filteredOthers = computed(() =>
    props.others.filter((p) =>
        normalize(p.full_name).includes(normalize(search.value)),
    ),
);
const showOthers = ref(false);

const warnings = computed(() =>
    Object.entries(props.seats).flatMap(([seat, o]) =>
        o.warnings.map((w) => ({ seat, name: o.full_name, text: w })),
    ),
);

// Kısa isim (koltuk kutusuna sığsın): "Ayşe Y."
const shortName = (name: string) => {
    const parts = name.split(' ');

    return parts.length > 1
        ? `${parts.slice(0, -1).join(' ')} ${parts.at(-1)?.charAt(0)}.`
        : name;
};

const exportItems = computed<ExportItem[]>(() =>
    props.can.reports
        ? [
              {
                  title: 'Koltuk planı',
                  description: 'Araca asmak için, A4',
                  url: seatChart.url(props.bus.id),
                  pdfOnly: true,
              },
              {
                  title: 'Araç yolcu listesi',
                  description: `${props.bus.name} · koltuk sırasıyla`,
                  url: passengerReport.url(props.bus.id),
              },
              {
                  title: 'Şoför listesi',
                  description: 'Turun bütün araçları, plaka ve telefon',
                  url: driverReport.url(props.bus.tour.id),
              },
          ]
        : [],
);

function clearPlan(): void {
    if (
        confirm(
            `${props.bus.name} için bütün yolcular koltuktan kaldırılsın mı? Rehber koltukları ayrılmış kalır.`,
        )
    ) {
        router.delete(SeatAssignmentController.clear.url(props.bus.id), {
            preserveScroll: true,
            onError: showError,
        });
    }
}

// Otomatik yerleştir
const autoOpen = ref(false);
const preview = ref<SeatAutoPreview | null>(null);
const loading = ref(false);
const applying = ref(false);

async function openAuto(): Promise<void> {
    autoOpen.value = true;
    preview.value = null;
    loading.value = true;

    try {
        const response = await fetch(
            SeatPlanController.preview.url(props.bus.id),
            { headers: { Accept: 'application/json' } },
        );
        preview.value = response.ok ? await response.json() : null;
    } finally {
        loading.value = false;
    }
}

function applyAuto(): void {
    applying.value = true;
    router.post(
        SeatPlanController.apply.url(props.bus.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (autoOpen.value = false),
            onError: showError,
            onFinish: () => (applying.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Koltuk planı — ${bus.name}`" />

    <div class="flex w-full flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="showTour(bus.tour.id, { query: { tab: 'ulasim' } })"
                    class="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> {{ bus.tour.name }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ bus.name }}
                    <span v-if="bus.plate" class="text-muted-foreground">
                        · {{ bus.plate }}
                    </span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ bus.vehicle_type ?? bus.label }} ·
                    {{ stats.seats + stats.reserved }} koltuk
                    <template v-if="bus.groups.length">
                        · {{ bus.groups.join(', ') }}
                    </template>
                    <template v-if="bus.driver_name">
                        · Şoför: {{ bus.driver_name }}
                        {{ bus.driver_phone ?? '' }}
                    </template>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <ExportMenu :items="exportItems" />
                <Button
                    v-if="can.update"
                    variant="outline"
                    :disabled="stats.occupied === 0"
                    @click="clearPlan"
                >
                    <Eraser /> Temizle
                </Button>
                <Button
                    v-if="can.update"
                    :disabled="stats.unassigned === 0"
                    @click="openAuto"
                >
                    <Wand2 /> Otomatik yerleştir
                </Button>
            </div>
        </div>

        <!-- Doluluk -->
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <div class="flex min-w-60 flex-1 items-center gap-3">
                <ProgressBar
                    class="flex-1"
                    :value="stats.occupied"
                    :max="stats.occupied + stats.unassigned || stats.seats"
                />
                <span class="text-muted-foreground tabular-nums">
                    {{ stats.occupied }} /
                    {{ stats.occupied + stats.unassigned }}
                    yerleşti
                </span>
            </div>
            <span class="text-muted-foreground">
                Boş koltuk:
                <b class="text-success">{{ stats.seats - stats.occupied }}</b>
                <template v-if="stats.reserved">
                    · Rehber / görevli: {{ stats.reserved }}
                </template>
            </span>
        </div>

        <div
            v-if="selected"
            class="sticky top-2 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/40 bg-popover p-3 text-sm shadow-lg"
        >
            <span>
                <strong>{{ selected.full_name }}</strong> seçildi —
                {{
                    selected.seat_id
                        ? 'boş koltuğa tıklayın ya da yer değiştireceği yolcuya tıklayın.'
                        : 'boş bir koltuğa tıklayın.'
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="selected.seat_id"
                    size="sm"
                    variant="outline"
                    class="text-destructive"
                    @click="unseat(selected)"
                >
                    Koltuktan kaldır
                </Button>
                <Button size="sm" variant="ghost" @click="selected = null">
                    <X /> Vazgeç
                </Button>
            </div>
        </div>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <!-- Yolcu havuzu: oturan yolcu buraya bırakılırsa koltuktan kalkar -->
            <Card
                class="h-fit min-w-0 transition-shadow lg:sticky lg:top-2"
                :class="{
                    'ring-2 ring-primary': overPool && dragging?.seat_id,
                }"
                @dragover.prevent="overPool = dragging?.seat_id !== null"
                @dragleave="overPool = false"
                @drop.prevent="dropOnPool"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="size-4" /> Koltuğu olmayanlar
                        <span class="text-sm font-normal text-muted-foreground">
                            {{ unassigned.length }}
                        </span>
                    </CardTitle>
                    <CardDescription v-if="can.update">
                        Yolcuyu koltuğa sürükleyin ya da önce yolcuya, sonra
                        koltuğa tıklayın. Aileler bir arada.
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
                        v-if="unassigned.length === 0"
                        class="rounded-lg border border-dashed p-4 text-center text-muted-foreground"
                    >
                        Herkes yerleşti ✓
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
                            >
                                {{ unit.label }}
                            </small>
                            <button
                                v-for="p in unit.people"
                                :key="p.registration_id"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg border px-2 py-1.5 text-left transition-colors hover:border-primary"
                                :class="{
                                    'border-primary ring-2 ring-primary/30':
                                        selected?.registration_id ===
                                        p.registration_id,
                                    'cursor-grab': can.update,
                                    'opacity-40':
                                        dragging?.registration_id ===
                                        p.registration_id,
                                }"
                                :draggable="can.update"
                                :disabled="!can.update"
                                @click="pick(p)"
                                @dragstart="
                                    startDrag($event, {
                                        registration_id: p.registration_id,
                                        full_name: p.full_name,
                                        seat_id: null,
                                        seat_no: null,
                                    })
                                "
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
                                    <span class="block truncate font-medium">
                                        {{ p.full_name }}
                                    </span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                    >
                                        {{ p.group_name }}
                                        <template v-if="p.age">
                                            · {{ p.age }} yaş
                                        </template>
                                        <template
                                            v-for="f in p.family.filter(
                                                (f) => f.seat_no,
                                            )"
                                            :key="f.name"
                                        >
                                            · {{ f.relation }} koltuk
                                            {{ f.seat_no }}
                                        </template>
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <template v-if="can.update && others.length > 0">
                        <button
                            type="button"
                            class="mt-2 text-left text-xs text-muted-foreground underline"
                            @click="showOthers = !showOthers"
                        >
                            {{ showOthers ? 'Gizle' : 'Göster' }}: grubu bu
                            araçta olmayan yolcular ({{ others.length }})
                        </button>
                        <ul v-if="showOthers" class="flex flex-col gap-0.5">
                            <li
                                v-for="p in filteredOthers"
                                :key="p.registration_id"
                            >
                                <button
                                    type="button"
                                    class="w-full rounded-md px-2 py-1.5 text-left hover:bg-muted"
                                    :class="{
                                        'bg-primary/10 ring-1 ring-primary':
                                            selected?.registration_id ===
                                            p.registration_id,
                                    }"
                                    draggable="true"
                                    @click="pick(p)"
                                    @dragstart="
                                        startDrag($event, {
                                            registration_id: p.registration_id,
                                            full_name: p.full_name,
                                            seat_id: null,
                                            seat_no: null,
                                        })
                                    "
                                    @dragend="endDrag"
                                >
                                    <div class="truncate">
                                        {{ p.full_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ p.group_name ?? 'Grupsuz' }}
                                        <template v-if="p.elsewhere">
                                            · şu an {{ p.elsewhere }}
                                            (seçerseniz buraya taşınır)
                                        </template>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </template>
                </CardContent>
            </Card>

            <div class="flex min-w-0 flex-col gap-3">
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <i
                            class="seat-front inline-block size-3 rounded border"
                        />
                        Ön bölge (yaşlı / hareket güçlüğü olanlar için)
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="inline-block size-3 rounded-full bg-women" />
                        Kadın
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i class="inline-block size-3 rounded-full bg-men" />
                        Erkek
                    </span>
                    <span class="flex items-center gap-1.5">
                        <i
                            class="inline-block size-3 rounded border border-dashed"
                        />
                        Rehber / görevli
                    </span>
                </div>

                <div class="overflow-x-auto pb-2">
                    <BusDiagram :grid="grid" :body="bus.body">
                        <template #seat="{ seat }">
                            <button
                                type="button"
                                class="flex h-14 w-full flex-col items-start rounded-lg border px-1.5 py-1 text-left text-xs transition"
                                :class="{
                                    'seat-front': inFront(seat),
                                    'border-dashed bg-muted text-muted-foreground':
                                        isReserved(seat),
                                    'bg-background/70 hover:bg-muted':
                                        !isReserved(seat) && !occupant(seat),
                                    'border-women/60 bg-women/10':
                                        occupant(seat)?.gender === 'kadin',
                                    'border-men/60 bg-men/10':
                                        occupant(seat)?.gender === 'erkek',
                                    'border-primary ring-2 ring-primary/30':
                                        (selected &&
                                            !occupant(seat) &&
                                            !isReserved(seat)) ||
                                        overSeat === seat,
                                    'ring-2 ring-primary':
                                        occupant(seat)?.registration_id ===
                                            selected?.registration_id &&
                                        selected !== null,
                                    'cursor-grab':
                                        can.update &&
                                        occupant(seat)?.registration_id,
                                    'opacity-40': dragging?.seat_no === seat,
                                }"
                                :disabled="!can.update || isReserved(seat)"
                                :draggable="
                                    can.update &&
                                    !!occupant(seat)?.registration_id
                                "
                                :aria-label="`Koltuk ${seat}${occupant(seat) ? ': ' + occupant(seat)?.full_name : ''}${inFront(seat) ? ' (ön bölge)' : ''}`"
                                @click="clickSeat(seat)"
                                @dragstart="
                                    startDrag($event, {
                                        registration_id:
                                            occupant(seat)?.registration_id ??
                                            '',
                                        full_name:
                                            occupant(seat)?.full_name ?? '',
                                        seat_id:
                                            occupant(seat)?.seat_id ?? null,
                                        seat_no: seat,
                                    })
                                "
                                @dragend="endDrag"
                                @dragover="
                                    canDropOn(seat) &&
                                    ($event.preventDefault(), (overSeat = seat))
                                "
                                @dragleave="overSeat = null"
                                @drop.prevent="dropOnSeat(seat)"
                            >
                                <span
                                    class="flex w-full items-center justify-between font-semibold"
                                >
                                    {{ seat }}
                                    <AlertTriangle
                                        v-if="occupant(seat)?.warnings.length"
                                        class="size-3 text-warning"
                                    />
                                </span>
                                <span v-if="isReserved(seat)" class="truncate"
                                    >Rehber</span
                                >
                                <span
                                    v-else-if="occupant(seat)"
                                    class="line-clamp-2 leading-tight"
                                >
                                    {{
                                        shortName(
                                            occupant(seat)?.full_name ?? '',
                                        )
                                    }}
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >Boş</span
                                >
                            </button>
                        </template>
                    </BusDiagram>
                </div>

                <Card v-if="warnings.length">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-warning">
                            <AlertTriangle class="size-4" /> Uyarılar
                        </CardTitle>
                        <CardDescription>
                            Engellemez; isterseniz yolcuları sürükleyip yer
                            değiştirin.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="list-disc space-y-1 pl-5 text-sm">
                            <li v-for="w in warnings" :key="w.seat + w.text">
                                Koltuk {{ w.seat }} — {{ w.name }}: {{ w.text }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <Dialog v-model:open="autoOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Otomatik yerleştir</DialogTitle>
                <DialogDescription>
                    65 yaş ve üstü yolcular ön bölgeye, aileler yan yana, tek
                    yolcular karşı cinsten yabancının yanına düşmeyecek şekilde
                    oturtulur. Elle yaptığınız yerleşimler değişmez.
                </DialogDescription>
            </DialogHeader>

            <p v-if="loading" class="text-sm text-muted-foreground">
                Plan hazırlanıyor…
            </p>
            <template v-else-if="preview">
                <p class="text-sm">
                    <strong>{{ preview.placed }}</strong> yolcu oturtulacak.
                </p>
                <ul
                    class="max-h-72 divide-y overflow-y-auto rounded-md border text-sm"
                >
                    <li
                        v-for="item in preview.placements"
                        :key="item.seat"
                        class="flex gap-3 px-3 py-1.5"
                    >
                        <span class="w-8 font-medium">{{ item.seat }}</span>
                        {{ item.name }}
                    </li>
                </ul>
                <div
                    v-if="preview.unplaced.length"
                    class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
                >
                    <p class="font-medium">
                        {{ preview.unplaced.length }} yolcuya koltuk kalmadı:
                    </p>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="u in preview.unplaced" :key="u.name">
                            {{ u.name }}
                        </li>
                    </ul>
                    <p class="mt-1">Başka bir araç ekleyebilirsiniz.</p>
                </div>
            </template>
            <p v-else class="text-sm text-destructive">
                Plan hazırlanamadı. Sayfayı yenileyip tekrar deneyin.
            </p>

            <DialogFooter>
                <Button variant="ghost" @click="autoOpen = false"
                    >Vazgeç</Button
                >
                <Button
                    :disabled="!preview || preview.placed === 0 || applying"
                    @click="applyAuto"
                >
                    Onayla ve oturt
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
