<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BedDouble,
    Copy,
    Plus,
    Search,
    Users,
    Wand2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import RoomAssignmentController from '@/actions/App/Http/Controllers/RoomAssignmentController';
import RoomPlanController from '@/actions/App/Http/Controllers/RoomPlanController';
import ExportButtons from '@/components/ExportButtons.vue';
import CopyPlanDialog from '@/components/rooms/CopyPlanDialog.vue';
import RoomCard from '@/components/rooms/RoomCard.vue';
import RoomDialogs from '@/components/rooms/RoomDialogs.vue';
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
import { formatDate } from '@/lib/format';
import { roomOccupancy, roomingList } from '@/routes/reports/stays';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type {
    AutoAssignPreview,
    OtherPassenger,
    PlanOptions,
    PlanRoom,
    PlanStats,
    PlanStay,
    RoomOccupant,
    UnassignedPassenger,
} from '@/types/room';

const props = defineProps<{
    stay: PlanStay;
    rooms: PlanRoom[];
    unassigned: UnassignedPassenger[];
    others: OtherPassenger[];
    stats: PlanStats;
    options: PlanOptions;
    // Oda düzeni kopyalanabilecek diğer oteller (ör. Mekke → Medine).
    copySources: { id: string; label: string }[];
    can: { update: boolean; reports: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const kindLabel = (value: string) =>
    props.options.kinds.find((k) => k.value === value)?.label ?? value;

// Seçim: yerleşmemiş bir yolcu veya taşınacak bir oda sakini.
const selected = ref<{ registration_id: string; full_name: string } | null>(
    null,
);

function select(passenger: {
    registration_id: string | null;
    full_name: string;
}): void {
    if (!props.can.update || passenger.registration_id === null) {
        return;
    }

    selected.value =
        selected.value?.registration_id === passenger.registration_id
            ? null
            : {
                  registration_id: passenger.registration_id,
                  full_name: passenger.full_name,
              };
}

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

function place(room: PlanRoom): void {
    if (!selected.value) {
        return;
    }

    router.post(
        RoomAssignmentController.store.url(room.id),
        { registration_id: selected.value.registration_id },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

function remove(occupant: RoomOccupant): void {
    router.delete(
        RoomAssignmentController.destroy.url(occupant.assignment_id),
        {
            preserveScroll: true,
            onError: showError,
        },
    );
}

// Arama
const search = ref('');
const normalize = (value: string) => value.toLocaleLowerCase('tr');
const filteredUnassigned = computed(() =>
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

// Diyaloglar
const addOpen = ref(false);
const editOpen = ref(false);
const editingRoom = ref<PlanRoom | null>(null);

function editRoom(room: PlanRoom): void {
    editingRoom.value = room;
    editOpen.value = true;
}

const copyOpen = ref(false);

// Otomatik dağıt: önce önizleme, onaylanınca kaydet.
const autoOpen = ref(false);
const preview = ref<AutoAssignPreview | null>(null);
const loadingPreview = ref(false);
const applying = ref(false);

async function openAutoAssign(): Promise<void> {
    autoOpen.value = true;
    preview.value = null;
    loadingPreview.value = true;

    try {
        const response = await fetch(
            RoomPlanController.preview.url(props.stay.id),
            { headers: { Accept: 'application/json' } },
        );
        preview.value = response.ok ? await response.json() : null;
    } finally {
        loadingPreview.value = false;
    }
}

function applyAutoAssign(): void {
    applying.value = true;
    router.post(
        RoomPlanController.apply.url(props.stay.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (autoOpen.value = false),
            onError: showError,
            onFinish: () => (applying.value = false),
        },
    );
}

const free = computed(() => props.stats.beds - props.stats.occupied);
</script>

<template>
    <Head :title="`Oda planı — ${stay.hotel_name}`" />

    <div class="flex w-full flex-col gap-4 p-4">
        <!-- Başlık -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="showTour(stay.tour.id)"
                    class="mb-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> {{ stay.tour.name }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ stay.hotel_name }}
                    <span class="text-muted-foreground">
                        · {{ stay.city_label }}
                    </span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ formatDate(stay.check_in) }} –
                    {{ formatDate(stay.check_out) }} · {{ stay.nights }} gece
                    <template v-if="stay.groups.length">
                        · {{ stay.groups.join(', ') }}
                    </template>
                </p>
            </div>
            <div v-if="can.update" class="flex flex-wrap gap-2">
                <Button variant="outline" @click="addOpen = true">
                    <Plus /> Oda ekle
                </Button>
                <Button
                    v-if="copySources.length"
                    variant="outline"
                    :disabled="rooms.length === 0 || stats.unassigned === 0"
                    @click="copyOpen = true"
                >
                    <Copy /> Başka otelden kopyala
                </Button>
                <Button
                    :disabled="rooms.length === 0 || stats.unassigned === 0"
                    @click="openAutoAssign"
                >
                    <Wand2 /> Otomatik dağıt
                </Button>
            </div>
        </div>

        <div
            v-if="can.reports"
            class="flex flex-wrap items-center gap-x-6 gap-y-2"
        >
            <ExportButtons
                :url="roomingList.url(stay.id)"
                label="Otel oda listesi"
            />
            <ExportButtons
                :url="roomOccupancy.url(stay.id)"
                label="Doluluk özeti"
            />
        </div>

        <!-- Özet -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardDescription>Oda</CardDescription>
                    <CardTitle class="text-2xl">{{ stats.rooms }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Dolu / yatak</CardDescription>
                    <CardTitle class="text-2xl">
                        {{ stats.occupied }} / {{ stats.beds }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Boş yatak</CardDescription>
                    <CardTitle class="text-2xl text-success">
                        {{ free }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Yerleşmemiş yolcu</CardDescription>
                    <CardTitle
                        class="text-2xl"
                        :class="{ 'text-warning': stats.unassigned > 0 }"
                    >
                        {{ stats.unassigned }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Seçim bilgisi -->
        <div
            v-if="selected"
            class="sticky top-2 z-10 flex items-center justify-between gap-3 rounded-md border border-primary/40 bg-background p-3 text-sm shadow-sm"
        >
            <span>
                <strong>{{ selected.full_name }}</strong> seçildi — yerleştirmek
                için çerçevesi vurgulanan bir odaya tıklayın.
            </span>
            <Button size="sm" variant="ghost" @click="selected = null">
                <X /> Vazgeç
            </Button>
        </div>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <!-- Yerleşmemiş yolcular -->
            <Card class="h-fit min-w-0 lg:sticky lg:top-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="size-4" /> Yerleşmemiş yolcular
                    </CardTitle>
                    <CardDescription v-if="can.update">
                        Yolcuya, sonra odaya tıklayın.
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
                        Herkes yerleşti.
                    </p>
                    <ul
                        class="flex max-h-[35vh] flex-col gap-0.5 overflow-y-auto lg:max-h-[60vh]"
                    >
                        <li
                            v-for="p in filteredUnassigned"
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
                                :disabled="!can.update"
                                @click="select(p)"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="truncate font-medium">
                                        {{ p.full_name }}
                                    </span>
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                    >
                                        {{
                                            p.gender === 'erkek'
                                                ? 'Erkek'
                                                : 'Kadın'
                                        }}
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        [p.group_name, p.room_type_label]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </div>
                                <div
                                    v-for="f in p.family"
                                    :key="f.name"
                                    class="text-xs text-primary"
                                >
                                    {{ f.relation }}: {{ f.name }}
                                    <template v-if="f.room_no">
                                        (oda {{ f.room_no }})
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
                            otelde olmayan yolcular ({{ others.length }})
                        </button>
                        <ul v-if="showOthers" class="flex flex-col gap-0.5">
                            <li
                                v-for="p in filteredOthers"
                                :key="p.registration_id"
                            >
                                <button
                                    type="button"
                                    class="w-full rounded-md px-2 py-1.5 text-left hover:bg-muted disabled:opacity-50"
                                    :class="{
                                        'bg-primary/10 ring-1 ring-primary':
                                            selected?.registration_id ===
                                            p.registration_id,
                                    }"
                                    :disabled="p.elsewhere !== null"
                                    @click="select(p)"
                                >
                                    <div class="truncate">
                                        {{ p.full_name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ p.group_name ?? 'Grupsuz' }}
                                        <template v-if="p.elsewhere">
                                            · {{ p.elsewhere }} otelinde
                                        </template>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </template>
                </CardContent>
            </Card>

            <!-- Odalar -->
            <div class="min-w-0">
                <div
                    v-if="rooms.length === 0"
                    class="flex flex-col items-center gap-3 rounded-lg border border-dashed p-10 text-sm text-muted-foreground"
                >
                    <BedDouble class="size-8" />
                    Bu otel için henüz oda eklenmedi.
                    <Button v-if="can.update" size="sm" @click="addOpen = true">
                        <Plus /> Oda ekle
                    </Button>
                </div>
                <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <RoomCard
                        v-for="room in rooms"
                        :key="room.id"
                        :room="room"
                        :kind-label="kindLabel(room.kind)"
                        :can-update="can.update"
                        :selecting="selected !== null"
                        :selected-id="selected?.registration_id ?? null"
                        @place="place"
                        @pick="select"
                        @remove="remove"
                        @edit="editRoom"
                    />
                </div>
            </div>
        </div>
    </div>

    <RoomDialogs
        v-if="can.update"
        v-model:add-open="addOpen"
        v-model:edit-open="editOpen"
        :stay-id="stay.id"
        :kinds="options.kinds"
        :editing="editingRoom"
    />

    <CopyPlanDialog
        v-if="can.update && copySources.length"
        v-model:open="copyOpen"
        :stay-id="stay.id"
        :sources="copySources"
    />

    <Dialog v-model:open="autoOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Otomatik dağıt</DialogTitle>
                <DialogDescription>
                    Aileler birlikte, cinsiyete ve ödenen oda tipine göre
                    yerleştirilir. Elle yaptığınız yerleşimler değişmez; sonra
                    istediğiniz gibi düzeltebilirsiniz.
                </DialogDescription>
            </DialogHeader>

            <p v-if="loadingPreview" class="text-sm text-muted-foreground">
                Plan hazırlanıyor…
            </p>
            <template v-else-if="preview">
                <p class="text-sm">
                    <strong>{{ preview.placed }}</strong> yolcu
                    {{ preview.placements.length }} odaya yerleşecek.
                </p>
                <ul class="divide-y rounded-md border text-sm">
                    <li
                        v-for="item in preview.placements"
                        :key="item.room_no"
                        class="px-3 py-2"
                    >
                        <span class="font-medium">{{ item.room_no }}</span>
                        <span class="text-xs text-muted-foreground">
                            ({{ item.kind }})
                        </span>
                        — {{ item.names.join(', ') }}
                    </li>
                </ul>
                <div
                    v-if="preview.unplaced.length"
                    class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
                >
                    <p class="font-medium">
                        {{ preview.unplaced.length }} yolcuya yer bulunamadı:
                    </p>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="u in preview.unplaced" :key="u.name">
                            {{ u.name }} — {{ u.reason }}
                        </li>
                    </ul>
                    <p class="mt-1">Oda ekleyip tekrar deneyebilirsiniz.</p>
                </div>
            </template>
            <p v-else class="text-sm text-destructive">
                Plan hazırlanamadı. Sayfayı yenileyip tekrar deneyin.
            </p>

            <DialogFooter>
                <Button variant="ghost" @click="autoOpen = false">
                    Vazgeç
                </Button>
                <Button
                    :disabled="!preview || preview.placed === 0 || applying"
                    @click="applyAutoAssign"
                >
                    Onayla ve yerleştir
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
