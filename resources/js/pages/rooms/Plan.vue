<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import RoomAssignmentController from '@/actions/App/Http/Controllers/RoomAssignmentController';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
import RoomPlanController from '@/actions/App/Http/Controllers/RoomPlanController';
import MockPool from '@/components/mock/MockPool.vue';
import type { PoolPerson } from '@/components/mock/MockPool.vue';
import MockTop from '@/components/mock/MockTop.vue';
import { useFeatures } from '@/composables/useFeatures';
import { usePointerDrag } from '@/composables/usePointerDrag';
import {
    floorPlan,
    needs as needsReport,
    roomOccupancy,
    roomingList,
} from '@/routes/reports/stays';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type { ExportItem } from '@/types/export';
import type {
    OtherPassenger,
    PlanOptions,
    PlanRoom,
    PlanStats,
    PlanStay,
    PlanStayLink,
    RoomKind,
    RoomOccupant,
    UnassignedPassenger,
} from '@/types/room';

/**
 * Otel planı — tasarım sayfasındaki "Otel planı" ekranının birebir hâli (gerçek veriyle): solda
 * yerleşmemişler (aileler bir arada), üstte turun otelleri, solda otel kulesi (bizim katlar ve
 * doluluk), sağda seçili kattaki odalar. Sürükle-bırak ya da yolcuya sonra boş yatağa tıklama.
 */
const props = defineProps<{
    stay: PlanStay;
    rooms: PlanRoom[];
    unassigned: UnassignedPassenger[];
    units: { label: string | null; ids: string[] }[];
    others: OtherPassenger[];
    stays: PlanStayLink[];
    stats: PlanStats;
    options: PlanOptions;
    can: { update: boolean; reports: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const root = ref<HTMLElement | null>(null);
const selected = ref<string | null>(null);
const editing = ref(false);
const showOthers = ref(false);

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
const surname = (name: string) => name.trim().split(/\s+/).slice(-1)[0];
const g = (gender: string | null): 'E' | 'K' =>
    gender === 'kadin' ? 'K' : 'E';

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

// Katlar: binanın kat sayısı ve bize verilen katlar; tanımlı değilse odaların katlarından.
const floorOf = (room: PlanRoom): number | null =>
    room.floor !== null && /^\d+$/.test(room.floor.trim())
        ? Number(room.floor)
        : null;
const roomFloors = computed(() => [
    ...new Set(props.rooms.map(floorOf).filter((f): f is number => f !== null)),
]);
const ours = computed(() =>
    [...new Set([...props.stay.used_floors, ...roomFloors.value])].sort(
        (a, b) => a - b,
    ),
);
const total = computed(() =>
    Math.max(props.stay.floors_count ?? 0, ...ours.value, 0),
);
const floorsDesc = computed(() =>
    Array.from({ length: total.value }, (_, i) => total.value - i),
);
// Katı yazılmamış odalar (eski kayıtlar) ayrı bir "kat" gibi gösterilir.
const NO_FLOOR = -1;
const hasNoFloor = computed(() => props.rooms.some((r) => floorOf(r) === null));

const floorSel = ref<number>(
    ours.value.find((f) => roomFloors.value.includes(f)) ??
        ours.value[0] ??
        NO_FLOOR,
);
watch(ours, (list) => {
    if (
        !list.includes(floorSel.value) &&
        !(floorSel.value === NO_FLOOR && hasNoFloor.value)
    ) {
        floorSel.value = list[0] ?? NO_FLOOR;
    }
});

const roomsOn = (floor: number) =>
    props.rooms.filter((r) =>
        floor === NO_FLOOR ? floorOf(r) === null : floorOf(r) === floor,
    );
const floorRooms = computed(() => roomsOn(floorSel.value));
const pct = (n: number, d: number) => (d ? Math.round((n / d) * 100) : 0);
const occFloor = (floor: number) => {
    const rs = roomsOn(floor);

    return pct(
        rs.reduce((s, r) => s + r.occupants.length, 0),
        rs.reduce((s, r) => s + r.capacity, 0),
    );
};
const capFloor = computed(() =>
    floorRooms.value.reduce((s, r) => s + r.capacity, 0),
);

// Oda kartı: etiket ve uyarılar (tasarımdaki gibi kısa).
const kindLabel = (kind: RoomKind) =>
    props.options.kinds.find((k) => k.value === kind)?.label ?? kind;
function roomLabel(room: PlanRoom): string {
    const people = room.occupants.filter((o) => o.registration_id !== null);

    if (!room.occupants.length) {
        return `Boş · ${kindLabel(room.kind)} odası`;
    }

    if (!people.length) {
        return 'Başka grup';
    }

    const names = [...new Set(people.map((o) => surname(o.full_name)))];

    if (room.kind === 'aile' || names.length === 1) {
        return `${names.join(' / ')} ailesi`;
    }

    return people.every((o) => o.gender === 'kadin')
        ? 'Kadın odası'
        : people.every((o) => o.gender === 'erkek')
          ? 'Erkek odası'
          : 'Karışık';
}
const shortWarning = (w: string) =>
    /asansör/i.test(w)
        ? 'Asansöre uzak'
        : /Ödediği oda/.test(w)
          ? 'Oda tipi farkı'
          : /Grubu bu otelde/.test(w)
            ? 'Grup dışı'
            : w;
const roomIssues = (room: PlanRoom) => [
    ...new Set(room.occupants.flatMap((o) => o.warnings.map(shortWarning))),
];
const issuesAll = computed(
    () => props.rooms.filter((r) => roomIssues(r).length).length,
);
const slots = (room: PlanRoom) =>
    Array.from(
        { length: Math.max(room.capacity, room.occupants.length) },
        (_, i) => room.occupants[i] ?? null,
    );

// Havuz: yerleşmemişler aile kümeleriyle (+ istenirse grubu bu otelde olmayanlar).
const people = computed(
    () =>
        new Map(
            [...props.unassigned, ...props.others].map((p) => [
                p.registration_id,
                p,
            ]),
        ),
);
const toPool = (p: UnassignedPassenger | OtherPassenger): PoolPerson => ({
    id: p.registration_id,
    name: p.full_name,
    g: g(p.gender),
    age: p.age ?? null,
    tag: p.mobility ? 'Asansör' : null,
    group: p.group_name,
});
const units = computed(() => [
    ...props.units
        .map((u) => ({
            label: u.label,
            people: u.ids
                .map((id) => people.value.get(id))
                .filter((p) => !!p)
                .map(toPool),
        }))
        .filter((u) => u.people.length),
    ...(showOthers.value
        ? [
              {
                  label: 'Grubu bu otelde olmayanlar',
                  people: props.others
                      .filter((p) => p.elsewhere === null)
                      .map(toPool),
              },
          ].filter((u) => u.people.length)
        : []),
]);
const placeableOthers = computed(
    () => props.others.filter((p) => p.elsewhere === null).length,
);

// Yerleşmiş yolcular: kayıt → oda sakini (sürüklenince ve havuza bırakılınca).
const occupants = computed(
    () =>
        new Map(
            props.rooms.flatMap((r) =>
                r.occupants
                    .filter((o) => o.registration_id !== null)
                    .map((o) => [o.registration_id as string, o] as const),
            ),
        ),
);
const nameOf = (id: string) =>
    people.value.get(id)?.full_name ?? occupants.value.get(id)?.full_name;

function place(pid: string, from: string | null, to: string): void {
    if (!props.can.update) {
        return;
    }

    if (to === 'list') {
        const occupant: RoomOccupant | undefined = occupants.value.get(pid);

        if (occupant) {
            router.delete(
                RoomAssignmentController.destroy.url(occupant.assignment_id),
                {
                    preserveScroll: true,
                    onSuccess: () =>
                        toast(`${occupant.full_name} listeye döndü`),
                    onError: showError,
                },
            );
        }

        return;
    }

    const room = props.rooms.find((r) => r.id === to);

    if (!room) {
        return;
    }

    router.post(
        RoomAssignmentController.store.url(room.id),
        { registration_id: pid },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = null;
                const p =
                    people.value.get(pid) ?? occupants.value.get(pid) ?? null;
                toast(
                    `${nameOf(pid)} → oda ${room.room_no}${room.near_elevator && p?.mobility ? ' · asansöre yakın ✓' : ''}`,
                );
            },
            onError: showError,
        },
    );
}

usePointerDrag(root, {
    label: (pid) => {
        const name = nameOf(pid);

        return name ? { initials: ini(name), name } : null;
    },
    onDrop: place,
    onClick: (pid, from) => {
        if (from === null && props.can.update) {
            selected.value = selected.value === pid ? null : pid;

            if (selected.value) {
                toast(`${nameOf(pid)} seçildi. Şimdi boş bir yatağa tıkla`);
            }
        }
    },
    // Sürüklerken bir katın üstünde durunca o kata geçilir.
    onHover: (el) => {
        const floor = Number(el.dataset.floor);

        if (!Number.isNaN(floor) && floor !== floorSel.value) {
            floorSel.value = floor;
        }
    },
});

function clickBed(room: PlanRoom): void {
    if (selected.value) {
        place(selected.value, null, room.id);
    }
}

// Düğmeler
function auto(): void {
    router.post(
        RoomPlanController.apply.url(props.stay.id),
        {},
        { preserveScroll: true, onError: showError },
    );
}

function clear(): void {
    if (
        confirm('Bu oteldeki bütün yerleşimler kaldırılsın mı? Odalar kalır.')
    ) {
        router.delete(RoomPlanController.clear.url(props.stay.id), {
            preserveScroll: true,
            onError: showError,
        });
    }
}

// "Medine'ye kopyala" / "Mekke'den kopyala": sıradaki otele (yoksa öncekinden bu otele).
const VOWELS = 'aeıioöuü';
const lastVowel = (w: string) =>
    [...w.toLocaleLowerCase('tr')].reverse().find((c) => VOWELS.includes(c)) ??
    'e';
const front = (w: string) => 'eiöü'.includes(lastVowel(w));
const dative = (w: string) =>
    `${w}'${VOWELS.includes(w.slice(-1).toLocaleLowerCase('tr')) ? 'y' : ''}${front(w) ? 'e' : 'a'}`;
const ablative = (w: string) =>
    `${w}'${'fstkçşhp'.includes(w.slice(-1).toLocaleLowerCase('tr')) ? 't' : 'd'}${front(w) ? 'en' : 'an'}`;

// Sonraki konaklama: bu otelden çıkış günü ya da sonra giriş (aynı anda başka grubun oteli değil).
const nextStay = computed(() =>
    props.stays.find(
        (s) => s.id !== props.stay.id && s.check_in >= props.stay.check_out,
    ),
);
const prevStay = computed(() =>
    [...props.stays]
        .reverse()
        .find(
            (s) => s.id !== props.stay.id && s.check_out <= props.stay.check_in,
        ),
);
// Aynı şehirdeyse otelin adı, değilse şehir ("Medine'ye kopyala").
const placeName = (s: PlanStayLink) =>
    s.city === props.stay.city_label ? s.hotel_name : s.city;
const copyPlan = computed(() => {
    if (nextStay.value) {
        return {
            from: props.stay.id,
            target: nextStay.value,
            go: true,
            label: `${dative(placeName(nextStay.value))} kopyala`,
        };
    }

    if (prevStay.value) {
        return {
            from: prevStay.value.id,
            target: props.stays.find((s) => s.id === props.stay.id)!,
            go: false,
            label: `${ablative(placeName(prevStay.value))} kopyala`,
        };
    }

    return null;
});

function copy(): void {
    const plan = copyPlan.value;

    if (!plan) {
        return;
    }

    const target = plan.target;

    if (!target.has_rooms) {
        toast.error(
            `Önce ${target.city} · ${target.hotel_name} için "Oteli tanımla"dan oda ekleyin.`,
        );

        return;
    }

    router.post(
        RoomPlanController.copy.url(target.id),
        { from: plan.from },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (plan.go) {
                    router.visit(RoomPlanController.show.url(target.id));
                }
            },
            onError: showError,
        },
    );
}

// "Oteli tanımla": kat sayısı, bizim katlar, kattaki odalar (kişi, tür, asansöre yakın).
const floorsInput = ref(String(total.value || 10));
watch(total, (t) => (floorsInput.value = String(t || 10)));

function saveFloors(count: number, used: number[]): void {
    router.put(
        RoomPlanController.floors.url(props.stay.id),
        { floors_count: count, used_floors: used },
        { preserveScroll: true, preserveState: true, onError: showError },
    );
}

function setTotal(): void {
    const count = Math.min(150, Math.max(1, Number(floorsInput.value) || 1));
    saveFloors(
        count,
        ours.value.filter((f) => f <= count),
    );
}

function toggleFloor(floor: number): void {
    const count = Math.max(1, Number(floorsInput.value) || total.value || 1);

    if (ours.value.includes(floor)) {
        saveFloors(
            count,
            ours.value.filter((f) => f !== floor),
        );
    } else {
        saveFloors(count, [...ours.value, floor]);
        floorSel.value = floor;
    }
}

function updateRoom(room: PlanRoom, changes: Partial<PlanRoom>): void {
    router.put(
        RoomController.update.url(room.id),
        {
            room_no: room.room_no,
            floor: room.floor,
            capacity: room.capacity,
            kind: room.kind,
            notes: room.notes,
            near_elevator: room.near_elevator,
            ...changes,
        },
        { preserveScroll: true, preserveState: true, onError: showError },
    );
}

function deleteRoom(room: PlanRoom): void {
    if (
        room.occupants.length &&
        !confirm(
            `${room.room_no} numaralı odada ${room.occupants.length} yolcu var. Oda silinsin, yolcular listeye dönsün mü?`,
        )
    ) {
        return;
    }

    router.delete(RoomController.destroy.url(room.id), {
        preserveScroll: true,
        preserveState: true,
        onError: showError,
    });
}

function addRoom(): void {
    const floor = floorSel.value === NO_FLOOR ? null : floorSel.value;
    const taken = new Set(props.rooms.map((r) => r.room_no));
    let no = floor !== null ? floor * 100 + floorRooms.value.length + 1 : 1;

    while (taken.has(String(no))) {
        no++;
    }

    router.post(
        RoomController.store.url(props.stay.id),
        {
            start_no: String(no),
            count: 1,
            floor: floor === null ? null : String(floor),
            capacity: 4,
            kind: 'aile',
            near_elevator: 0,
        },
        { preserveScroll: true, preserveState: true, onError: showError },
    );
}

const features = useFeatures();

const exportItems = computed<ExportItem[]>(() =>
    props.can.reports
        ? [
              {
                  title: 'Oda listesi',
                  description: 'Otel resepsiyonu için, kat sırasıyla',
                  url: roomingList.url(props.stay.id),
              },
              {
                  title: 'Kat planı',
                  description: 'Her katın çizimi, isimlerle',
                  url: floorPlan.url(props.stay.id),
              },
              ...(features.has('need_rules')
                  ? [
                        {
                            title: 'İhtiyaç listesi',
                            description: 'Asansör ve yardım gereken yolcular',
                            url: needsReport.url(props.stay.id),
                        },
                    ]
                  : []),
              {
                  title: 'Doluluk özeti',
                  description: 'Oda türü ve boş yatak',
                  url: roomOccupancy.url(props.stay.id),
              },
          ]
        : [],
);

const crumbs = computed(() => [
    { label: 'Turlar', href: toursIndex.url() },
    { label: props.stay.tour.name, href: showTour.url(props.stay.tour.id) },
    {
        label: 'Konaklama',
        href: showTour.url(props.stay.tour.id, {
            query: { tab: 'konaklama' },
        }),
    },
]);

const goal = computed(() =>
    Math.min(props.stats.beds, props.stats.occupied + props.stats.unassigned),
);
const progress = computed(() => pct(props.stats.occupied, goal.value));
</script>

<template>
    <Head :title="`Otel planı — ${stay.hotel_name}`" />

    <div ref="root" class="mx">
        <div class="main">
            <MockTop
                :crumbs="crumbs"
                :back="{
                    label: `${stay.tour.name} · Konaklama`,
                    href: crumbs[2].href,
                }"
                title="Otel planı"
                :exports="exportItems"
            >
                <template v-if="can.update">
                    <button
                        v-if="copyPlan"
                        class="btn ghost"
                        type="button"
                        @click="copy"
                    >
                        <Copy />{{ copyPlan.label }}
                    </button>
                    <button class="btn ghost" type="button" @click="clear">
                        Temizle
                    </button>
                    <button class="btn" type="button" @click="auto">
                        Otomatik dağıt
                    </button>
                </template>
            </MockTop>

            <div class="planner">
                <MockPool
                    :units="units"
                    :selected="selected"
                    :locked="!can.update"
                    hint="Aileler aynı odaya yerleşir. Yürüme güçlüğü olanlar asansöre yakın odalara gider."
                >
                    <span
                        v-if="can.update && placeableOthers"
                        class="lbl"
                        role="button"
                        tabindex="0"
                        style="cursor: pointer; text-decoration: underline"
                        @click="showOthers = !showOthers"
                        >{{ showOthers ? 'Gizle' : 'Göster' }}: grubu bu otelde
                        olmayan yolcular ({{ placeableOthers }})</span
                    >
                </MockPool>

                <div class="card">
                    <div class="stagebar">
                        <div class="pills">
                            <span
                                v-for="s in stays"
                                :key="s.id"
                                class="pill"
                                :class="{ on: s.id === stay.id }"
                                role="button"
                                tabindex="0"
                                @click="
                                    s.id !== stay.id &&
                                    router.visit(
                                        RoomPlanController.show.url(s.id),
                                    )
                                "
                                >{{ s.city }} · {{ s.hotel_name }}</span
                            >
                        </div>
                        <div class="prog">
                            <span>{{ stats.occupied }} / {{ goal }}</span>
                            <div class="bar">
                                <i
                                    :class="{ full: progress >= 100 }"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="editing && can.update" class="editor">
                        <h5>
                            {{ stay.city_label }} · {{ stay.hotel_name }} tanımı
                        </h5>
                        <div class="row">
                            <label class="fi" for="hName"
                                >Otel adı<input
                                    id="hName"
                                    :value="stay.hotel_name"
                                    readonly
                                    title="Otel adı Oteller sayfasından değişir"
                            /></label>
                            <label class="fi" for="hTotal"
                                >Kat sayısı<input
                                    id="hTotal"
                                    v-model="floorsInput"
                                    type="number"
                                    min="1"
                                    max="150"
                                    style="width: 90px"
                                    @change="setTotal"
                            /></label>
                        </div>
                        <div class="fi">
                            Bizim kullandığımız katlar
                            <div class="chips">
                                <span
                                    v-for="f in Number(floorsInput) || 0"
                                    :key="f"
                                    :class="{ on: ours.includes(f) }"
                                    role="button"
                                    tabindex="0"
                                    @click="toggleFloor(f)"
                                    >{{ f }}</span
                                >
                            </div>
                        </div>
                        <div class="fi">
                            {{
                                floorSel === NO_FLOOR
                                    ? 'Katı yazılmamış odalar'
                                    : `${floorSel}. kattaki odalar`
                            }}
                            <div class="tbl">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Oda</th>
                                            <th>Kişi</th>
                                            <th>Tür</th>
                                            <th>Asansöre yakın</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="r in floorRooms" :key="r.id">
                                            <td>
                                                <b>{{ r.room_no }}</b>
                                            </td>
                                            <td>
                                                <select
                                                    class="mini-in"
                                                    aria-label="Kişi sayısı"
                                                    :value="r.capacity"
                                                    @change="
                                                        updateRoom(r, {
                                                            capacity: Number(
                                                                (
                                                                    $event.target as HTMLSelectElement
                                                                ).value,
                                                            ),
                                                        })
                                                    "
                                                >
                                                    <option
                                                        v-for="n in Math.max(
                                                            6,
                                                            r.capacity,
                                                        )"
                                                        :key="n"
                                                        :value="n"
                                                    >
                                                        {{ n }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <select
                                                    class="mini-in"
                                                    aria-label="Oda türü"
                                                    :value="r.kind"
                                                    @change="
                                                        updateRoom(r, {
                                                            kind: (
                                                                $event.target as HTMLSelectElement
                                                            ).value as RoomKind,
                                                        })
                                                    "
                                                >
                                                    <option
                                                        v-for="k in options.kinds"
                                                        :key="k.value"
                                                        :value="k.value"
                                                    >
                                                        {{ k.label }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <span
                                                    class="cb"
                                                    :class="{
                                                        on: r.near_elevator,
                                                    }"
                                                    role="checkbox"
                                                    :aria-checked="
                                                        r.near_elevator
                                                    "
                                                    tabindex="0"
                                                    @click="
                                                        updateRoom(r, {
                                                            near_elevator:
                                                                !r.near_elevator,
                                                        })
                                                    "
                                                    >{{
                                                        r.near_elevator
                                                            ? '✓'
                                                            : ''
                                                    }}</span
                                                >
                                            </td>
                                            <td>
                                                <button
                                                    class="xbtn"
                                                    type="button"
                                                    aria-label="Odayı sil"
                                                    @click="deleteRoom(r)"
                                                >
                                                    ×
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <button
                                class="btn ghost sm"
                                type="button"
                                :disabled="
                                    floorSel === NO_FLOOR && ours.length > 0
                                "
                                @click="addRoom"
                            >
                                <Plus />Oda ekle
                            </button>
                            <button
                                class="btn sm"
                                type="button"
                                @click="editing = false"
                            >
                                Tamam
                            </button>
                        </div>
                    </div>

                    <div class="hotel">
                        <div v-if="total > 0 || hasNoFloor" class="tower">
                            <div class="roof" />
                            <div class="body">
                                <template v-for="f in floorsDesc" :key="f">
                                    <div
                                        v-if="ours.includes(f)"
                                        class="fl ours"
                                        :class="{ on: floorSel === f }"
                                        :data-floor="f"
                                        data-hover-drop
                                        :style="{ '--o': occFloor(f) }"
                                        :title="`${f}. kat · %${occFloor(f)} dolu`"
                                        @click="floorSel = f"
                                    >
                                        <span>{{ f }}</span
                                        ><i />
                                    </div>
                                    <div v-else class="fl" :title="`${f}. kat`">
                                        <span>{{ f }}</span
                                        ><i />
                                    </div>
                                </template>
                                <div
                                    v-if="hasNoFloor"
                                    class="fl ours"
                                    :class="{ on: floorSel === NO_FLOOR }"
                                    :data-floor="NO_FLOOR"
                                    data-hover-drop
                                    :style="{ '--o': occFloor(NO_FLOOR) }"
                                    title="Katı yazılmamış odalar"
                                    @click="floorSel = NO_FLOOR"
                                >
                                    <span>–</span><i />
                                </div>
                            </div>
                            <div class="base" />
                            <small>Renkli katlar bizim.<br />Kata tıkla.</small>
                        </div>
                        <div v-else />

                        <div
                            style="
                                display: flex;
                                flex-direction: column;
                                gap: 10px;
                                min-width: 0;
                            "
                        >
                            <div class="floorhd">
                                <div>
                                    <b>{{
                                        floorSel === NO_FLOOR
                                            ? rooms.length
                                                ? 'Katsız odalar'
                                                : 'Oda yok'
                                            : `${floorSel}. kat`
                                    }}</b>
                                    <span class="lbl">
                                        · {{ floorRooms.length }} oda ·
                                        {{ capFloor }} yatak · %{{
                                            occFloor(floorSel)
                                        }}
                                        dolu</span
                                    >
                                </div>
                                <button
                                    v-if="can.update && !editing"
                                    class="btn ghost sm"
                                    type="button"
                                    @click="editing = true"
                                >
                                    <Pencil />Oteli tanımla
                                </button>
                            </div>
                            <div class="legend">
                                <span
                                    ><span class="tag">Asansör</span> asansöre
                                    yakın oda</span
                                >
                                <span
                                    ><i style="background: var(--m-warn)" />{{
                                        issuesAll
                                            ? `${issuesAll} odada uyarı`
                                            : 'Uyarı yok'
                                    }}</span
                                >
                            </div>
                            <div class="rooms">
                                <div
                                    v-for="r in floorRooms"
                                    :key="r.id"
                                    class="rm"
                                    :class="{ warnr: roomIssues(r).length }"
                                >
                                    <header>
                                        <b>{{ r.room_no }}</b>
                                        <span
                                            v-if="r.near_elevator"
                                            class="tag"
                                            title="Asansöre yakın"
                                            >Asansör</span
                                        >
                                        <span v-else class="tag soft"
                                            >{{ r.capacity }} kişilik</span
                                        >
                                    </header>
                                    <span class="occ" :title="roomLabel(r)">{{
                                        roomLabel(r)
                                    }}</span>
                                    <span
                                        v-if="roomIssues(r).length"
                                        class="issue"
                                        >{{ roomIssues(r).join(' · ') }}</span
                                    >
                                    <div class="beds">
                                        <template
                                            v-for="(o, i) in slots(r)"
                                            :key="i"
                                        >
                                            <div
                                                v-if="
                                                    o &&
                                                    o.registration_id === null
                                                "
                                                class="slot lock"
                                                title="Başka grup"
                                            />
                                            <div
                                                v-else-if="o"
                                                class="slot full"
                                                :class="[
                                                    g(o.gender).toLowerCase(),
                                                    { warn: o.warnings.length },
                                                ]"
                                                data-drop="slot"
                                                :data-key="r.id"
                                                :data-pid="o.registration_id"
                                                data-from="slot"
                                                :data-locked="
                                                    can.update ? undefined : ''
                                                "
                                                :title="
                                                    [
                                                        o.full_name,
                                                        ...(o.needs ?? []),
                                                        ...o.warnings,
                                                    ].join(' · ')
                                                "
                                            >
                                                {{ ini(o.full_name) }}
                                            </div>
                                            <div
                                                v-else
                                                class="slot free"
                                                data-drop="slot"
                                                :data-key="r.id"
                                                title="Boş yatak"
                                                @click="clickBed(r)"
                                            />
                                        </template>
                                    </div>
                                </div>
                                <div
                                    v-if="!floorRooms.length"
                                    class="empty-pool"
                                >
                                    Bu katta oda yok. "Oteli tanımla"dan oda
                                    ekle.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
