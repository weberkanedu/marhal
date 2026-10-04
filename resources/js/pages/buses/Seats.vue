<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    FileText,
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
import ExportButtons from '@/components/ExportButtons.vue';
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
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type {
    BusCell,
    OtherSeatPassenger,
    SeatAutoPreview,
    SeatOccupant,
    SeatPlanBus,
    SeatPlanStats,
    UnseatedPassenger,
} from '@/types/bus';

const props = defineProps<{
    bus: SeatPlanBus;
    grid: BusCell[][];
    seats: Record<string, SeatOccupant>;
    unassigned: UnseatedPassenger[];
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

// Seçim: koltuğu olmayan yolcu veya taşınacak oturan yolcu.
const selected = ref<{
    registration_id: string;
    full_name: string;
    seat_id: string | null;
} | null>(null);

function selectPassenger(p: {
    registration_id: string;
    full_name: string;
}): void {
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
              };
}

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

function clickSeat(seat: number): void {
    if (!props.can.update || isReserved(seat)) {
        return;
    }

    const current = occupant(seat);

    // Dolu koltuk: yolcuyu seç (taşımak veya kaldırmak için).
    if (current) {
        if (current.registration_id === null) {
            return;
        }

        selected.value =
            selected.value?.registration_id === current.registration_id
                ? null
                : {
                      registration_id: current.registration_id,
                      full_name: current.full_name,
                      seat_id: current.seat_id,
                  };

        return;
    }

    if (!selected.value) {
        return;
    }

    router.post(
        SeatAssignmentController.store.url(props.bus.id),
        { registration_id: selected.value.registration_id, seat_no: seat },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

function unseat(): void {
    if (!selected.value?.seat_id) {
        return;
    }

    router.delete(
        SeatAssignmentController.destroy.url(selected.value.seat_id),
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

// Arama
const search = ref('');
const normalize = (value: string) => value.toLocaleLowerCase('tr');
const filtered = computed(() =>
    props.unassigned.filter((p) =>
        normalize(p.full_name).includes(normalize(search.value)),
    ),
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

// Otomatik dağıt
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
            {
                headers: { Accept: 'application/json' },
            },
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
                    :href="showTour(bus.tour.id)"
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
            <Button
                v-if="can.update"
                :disabled="stats.unassigned === 0"
                @click="openAuto"
            >
                <Wand2 /> Otomatik dağıt
            </Button>
        </div>

        <div
            v-if="can.reports"
            class="flex flex-wrap items-center gap-x-6 gap-y-2"
        >
            <ExportButtons
                :url="passengerReport.url(bus.id)"
                label="Otobüs yolcu listesi"
            />
            <Button variant="outline" size="sm" as-child>
                <a :href="seatChart.url(bus.id)">
                    <FileText /> Koltuk planı (PDF, otobüse asmak için)
                </a>
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardDescription>Yolcu koltuğu</CardDescription>
                    <CardTitle class="text-2xl">{{ stats.seats }}</CardTitle>
                    <p
                        v-if="stats.reserved"
                        class="text-xs text-muted-foreground"
                    >
                        + {{ stats.reserved }} rehber / görevli
                    </p>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Dolu</CardDescription>
                    <CardTitle class="text-2xl">{{ stats.occupied }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Boş</CardDescription>
                    <CardTitle class="text-2xl text-success">
                        {{ stats.seats - stats.occupied }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Koltuğu olmayan</CardDescription>
                    <CardTitle
                        class="text-2xl"
                        :class="{ 'text-warning': stats.unassigned > 0 }"
                    >
                        {{ stats.unassigned }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <div
            v-if="selected"
            class="sticky top-2 z-10 flex flex-wrap items-center justify-between gap-3 rounded-md border border-primary/40 bg-background p-3 text-sm shadow-sm"
        >
            <span>
                <strong>{{ selected.full_name }}</strong> seçildi — boş bir
                koltuğa tıklayın.
            </span>
            <div class="flex gap-2">
                <Button
                    v-if="selected.seat_id"
                    size="sm"
                    variant="outline"
                    class="text-destructive"
                    @click="unseat"
                >
                    Koltuktan kaldır
                </Button>
                <Button size="sm" variant="ghost" @click="selected = null">
                    <X /> Vazgeç
                </Button>
            </div>
        </div>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <Card class="h-fit min-w-0 lg:sticky lg:top-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="size-4" /> Koltuğu olmayanlar
                    </CardTitle>
                    <CardDescription v-if="can.update">
                        Yolcuya, sonra boş koltuğa tıklayın.
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
                        class="py-2 text-muted-foreground"
                    >
                        Herkesin koltuğu var.
                    </p>
                    <ul
                        class="flex max-h-[60vh] flex-col gap-0.5 overflow-y-auto"
                    >
                        <li v-for="p in filtered" :key="p.registration_id">
                            <button
                                type="button"
                                class="w-full rounded-md px-2 py-1.5 text-left hover:bg-muted"
                                :class="{
                                    'bg-primary/10 ring-1 ring-primary':
                                        selected?.registration_id ===
                                        p.registration_id,
                                }"
                                :disabled="!can.update"
                                @click="selectPassenger(p)"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="truncate font-medium">{{
                                        p.full_name
                                    }}</span>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{
                                            p.gender === 'erkek'
                                                ? 'Erkek'
                                                : 'Kadın'
                                        }}
                                        <template v-if="p.age"
                                            >· {{ p.age }}</template
                                        >
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ p.group_name }}
                                </div>
                                <div
                                    v-for="f in p.family"
                                    :key="f.name"
                                    class="text-xs text-primary"
                                >
                                    {{ f.relation }}: {{ f.name }}
                                    <template v-if="f.seat_no">
                                        (koltuk {{ f.seat_no }})
                                    </template>
                                </div>
                            </button>
                        </li>
                    </ul>

                    <template v-if="can.update && others.length > 0">
                        <button
                            type="button"
                            class="mt-2 text-left text-xs text-muted-foreground underline"
                            @click="showOthers = !showOthers"
                        >
                            {{ showOthers ? 'Gizle' : 'Göster' }}: grubu bu
                            otobüste olmayan yolcular ({{ others.length }})
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
                                    @click="selectPassenger(p)"
                                >
                                    <div class="truncate">
                                        {{ p.full_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ p.group_name ?? 'Grupsuz' }}
                                        <template v-if="p.elsewhere">
                                            · şu an
                                            {{ p.elsewhere }} (seçerseniz buraya
                                            taşınır)
                                        </template>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </template>
                </CardContent>
            </Card>

            <div class="flex min-w-0 flex-col gap-3">
                <div class="overflow-x-auto pb-2">
                    <BusDiagram :grid="grid">
                        <template #seat="{ seat }">
                            <button
                                type="button"
                                class="flex h-14 w-full flex-col items-start rounded-md border px-1.5 py-1 text-left text-xs transition"
                                :class="{
                                    'border-dashed bg-muted text-muted-foreground':
                                        isReserved(seat),
                                    'bg-background hover:bg-muted':
                                        !isReserved(seat) && !occupant(seat),
                                    'bg-primary/5': occupant(seat),
                                    'border-primary ring-2 ring-primary/30':
                                        selected &&
                                        !occupant(seat) &&
                                        !isReserved(seat),
                                    'ring-2 ring-primary':
                                        occupant(seat)?.registration_id ===
                                            selected?.registration_id &&
                                        selected !== null,
                                }"
                                :disabled="!can.update || isReserved(seat)"
                                :aria-label="`Koltuk ${seat}${occupant(seat) ? ': ' + occupant(seat)?.full_name : ''}`"
                                @click="clickSeat(seat)"
                            >
                                <span
                                    class="flex w-full items-center justify-between font-semibold"
                                >
                                    {{ seat }}
                                    <AlertTriangle
                                        v-if="occupant(seat)?.warnings.length"
                                        class="size-3 text-warning"
                                    />
                                    <span
                                        v-else-if="occupant(seat)?.gender"
                                        class="text-[10px] font-normal text-muted-foreground"
                                    >
                                        {{
                                            occupant(seat)?.gender === 'erkek'
                                                ? 'E'
                                                : 'K'
                                        }}
                                    </span>
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
                            Engellemez; isterseniz yolcuları yer değiştirin.
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
                <DialogTitle>Otomatik dağıt</DialogTitle>
                <DialogDescription>
                    65 yaş ve üstü yolcular öne, aileler yan yana, tek yolcular
                    karşı cinsten yabancının yanına düşmeyecek şekilde
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
                    <p class="mt-1">Başka bir otobüs ekleyebilirsiniz.</p>
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
