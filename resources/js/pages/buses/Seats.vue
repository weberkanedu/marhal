<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SeatAssignmentController from '@/actions/App/Http/Controllers/SeatAssignmentController';
import SeatPlanController from '@/actions/App/Http/Controllers/SeatPlanController';
import MockPool from '@/components/mock/MockPool.vue';
import type { PoolPerson } from '@/components/mock/MockPool.vue';
import MockTop from '@/components/mock/MockTop.vue';
import { usePointerDrag } from '@/composables/usePointerDrag';
import {
    passengers as passengerReport,
    seatChart,
} from '@/routes/reports/buses';
import { drivers as driverReport } from '@/routes/reports/tours';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type {
    BusCell,
    OtherSeatPassenger,
    SeatOccupant,
    SeatPlanBus,
    SeatPlanBusLink,
    SeatPlanStats,
    SeatPoolUnit,
    UnseatedPassenger,
} from '@/types/bus';
import type { ExportItem } from '@/types/export';

/**
 * Araç koltuk planı — tasarım sayfasındaki "Araç planı" ekranının birebir hâli (gerçek veriyle):
 * araç gövdesi (otobüs / midibüs / sprinter / minivan), şoför, kapılar, rehber koltuğu, ön bölge.
 */
const props = defineProps<{
    bus: SeatPlanBus;
    grid: BusCell[][];
    seats: Record<string, SeatOccupant>;
    unassigned: UnseatedPassenger[];
    units: SeatPoolUnit[];
    others: OtherSeatPassenger[];
    buses: SeatPlanBusLink[];
    stats: SeatPlanStats;
    can: { update: boolean; reports: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const root = ref<HTMLElement | null>(null);
const selected = ref<string | null>(null);
const showOthers = ref(false);
const showWarnings = ref(false);

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
const g = (gender: string | null): 'E' | 'K' =>
    gender === 'kadin' ? 'K' : 'E';

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

const occupant = (seat: number): SeatOccupant | undefined =>
    props.seats[String(seat)];
const isReserved = (seat: number) => props.bus.reserved.includes(seat);
const inFront = (seat: number) => props.bus.front_zone.includes(seat);

// Gövde: sunucudaki gövde adları tasarımdaki çizim sınıflarına.
const body = computed(
    () =>
        ({ otobus: 'bus', midibus: 'midi', minibus: 'van', van: 'mini' })[
            props.bus.body
        ],
);
const columns = computed(
    () =>
        `repeat(${props.bus.layout.left}, var(--s)) ${body.value === 'mini' ? '10px' : '18px'} repeat(${props.bus.layout.right}, var(--s))`,
);

// Satırlar tasarımdaki hücrelere: koltuk, koridor, şoför, kapı boşluğu (birleşik), arka sıra.
type Item =
    | { kind: 'seat'; seat: number }
    | { kind: 'empty' }
    | { kind: 'driver' }
    | { kind: 'door'; span: number };
const rows = computed(() =>
    props.grid.map((cells) => {
        if (!cells.includes('aisle')) {
            return {
                back: true,
                seats: cells.filter((c): c is number => typeof c === 'number'),
                items: [] as Item[],
            };
        }

        const items: Item[] = [];

        for (const cell of cells) {
            const last = items.at(-1);

            if (cell === 'door') {
                if (last?.kind === 'door') {
                    last.span++;
                } else {
                    items.push({ kind: 'door', span: 1 });
                }
            } else if (typeof cell === 'number') {
                items.push({ kind: 'seat', seat: cell });
            } else if (cell === 'driver') {
                items.push({ kind: 'driver' });
            } else {
                items.push({ kind: 'empty' });
            }
        }

        return { back: false, seats: [] as number[], items };
    }),
);

// Kapılar (tasarımdaki hesapla aynı ölçüler).
const S = 34;
const GAP = 7;
const hasDriver = computed(() => props.grid[0]?.includes('driver') ?? false);
const pt = computed(() => (hasDriver.value ? 22 : 60));
const doorRow = computed(() => props.grid.findIndex((r) => r.includes('door')));

// Havuz: koltuğu olmayanlar aile kümeleriyle (+ istenirse grubu bu araçta olmayanlar).
const people = computed(
    () =>
        new Map(
            [...props.unassigned, ...props.others].map((p) => [
                p.registration_id,
                p,
            ]),
        ),
);
const toPool = (p: UnseatedPassenger | OtherSeatPassenger): PoolPerson => ({
    id: p.registration_id,
    name: p.full_name,
    g: g(p.gender),
    age: p.age,
    tag: p.mobility ? 'Ön' : null,
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
    ...(showOthers.value && props.others.length
        ? [
              {
                  label: 'Grubu bu araçta olmayanlar',
                  people: props.others.map(toPool),
              },
          ]
        : []),
]);

const seated = computed(
    () =>
        new Map(
            Object.entries(props.seats)
                .filter(([, o]) => o.registration_id !== null)
                .map(([seat, o]) => [
                    o.registration_id as string,
                    { ...o, seat },
                ]),
        ),
);
const nameOf = (id: string) =>
    people.value.get(id)?.full_name ?? seated.value.get(id)?.full_name;

const warnings = computed(() =>
    Object.entries(props.seats).flatMap(([seat, o]) =>
        o.warnings.map((w) => ({ seat, name: o.full_name, text: w })),
    ),
);

function place(pid: string, from: string | null, to: string): void {
    if (!props.can.update) {
        return;
    }

    if (to === 'list') {
        const current = seated.value.get(pid);

        if (current) {
            router.delete(
                SeatAssignmentController.destroy.url(current.seat_id),
                {
                    preserveScroll: true,
                    onSuccess: () =>
                        toast(`${current.full_name} listeye döndü`),
                    onError: showError,
                },
            );
        }

        return;
    }

    const seat = Number(to);

    if (isReserved(seat) || (occupant(seat) && from === null)) {
        return;
    }

    router.post(
        SeatAssignmentController.store.url(props.bus.id),
        { registration_id: pid, seat_no: seat, swap: from !== null },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = null;
                const p = people.value.get(pid);

                if (p?.mobility && !inFront(seat)) {
                    toast(
                        `Uyarı: ${p.full_name} yürüme güçlüğü var, ön sıralara alın`,
                    );
                }
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
                toast(`${nameOf(pid)} seçildi. Şimdi boş bir koltuğa tıkla`);
            }
        }
    },
});

function clickSeat(seat: number): void {
    if (selected.value && !occupant(seat) && !isReserved(seat)) {
        place(selected.value, null, String(seat));
    }
}

function auto(): void {
    router.post(
        SeatPlanController.apply.url(props.bus.id),
        {},
        { preserveScroll: true, onError: showError },
    );
}

function clear(): void {
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

const crumbs = computed(() => [
    { label: 'Turlar', href: toursIndex.url() },
    { label: props.bus.tour.name, href: showTour.url(props.bus.tour.id) },
    {
        label: 'Ulaşım',
        href: showTour.url(props.bus.tour.id, { query: { tab: 'ulasim' } }),
    },
]);

const goal = computed(() =>
    Math.min(props.stats.seats, props.stats.occupied + props.stats.unassigned),
);
const pct = computed(() =>
    goal.value ? Math.round((props.stats.occupied / goal.value) * 100) : 0,
);
const seatTitle = (seat: number) => {
    const o = occupant(seat);

    return o
        ? [o.full_name, ...o.warnings].join(' · ')
        : `${seat} · boş${inFront(seat) ? ' · ön bölge' : ''}`;
};
</script>

<template>
    <Head :title="`Araç koltuk planı — ${bus.name}`" />

    <div ref="root" class="mx">
        <div class="main">
            <MockTop
                :crumbs="crumbs"
                :back="{
                    label: `${bus.tour.name} · Ulaşım`,
                    href: crumbs[2].href,
                }"
                title="Araç koltuk planı"
                :exports="exportItems"
            >
                <template v-if="can.update">
                    <button class="btn ghost" type="button" @click="clear">
                        Temizle
                    </button>
                    <button class="btn" type="button" @click="auto">
                        Otomatik yerleştir
                    </button>
                </template>
            </MockTop>

            <div class="planner">
                <MockPool
                    :units="units"
                    :selected="selected"
                    :locked="!can.update"
                    hint="Aileler yan yana oturtulur. Yürüme güçlüğü olanlar ilk üç sıraya yerleşir."
                >
                    <span
                        v-if="can.update && others.length"
                        class="lbl"
                        role="button"
                        tabindex="0"
                        style="cursor: pointer; text-decoration: underline"
                        @click="showOthers = !showOthers"
                        >{{ showOthers ? 'Gizle' : 'Göster' }}: grubu bu araçta
                        olmayan yolcular ({{ others.length }})</span
                    >
                </MockPool>

                <div class="card">
                    <div class="stagebar">
                        <div class="pills">
                            <span
                                v-for="b in buses"
                                :key="b.id"
                                class="pill"
                                :class="{ on: b.id === bus.id }"
                                role="button"
                                tabindex="0"
                                @click="
                                    b.id !== bus.id &&
                                    router.visit(
                                        SeatPlanController.show.url(b.id),
                                    )
                                "
                                >{{ b.name }} · {{ b.label }}</span
                            >
                        </div>
                        <div class="prog">
                            <span>{{ stats.occupied }} / {{ goal }}</span>
                            <div class="bar">
                                <i
                                    :class="{ full: pct >= 100 }"
                                    :style="{ width: `${pct}%` }"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="spec">
                        <span
                            >Araç tipi
                            <b>{{ bus.vehicle_type ?? bus.label }}</b></span
                        >
                        <span
                            >Düzen
                            <b
                                >{{ bus.layout.left }}+{{ bus.layout.right }}</b
                            ></span
                        >
                        <span
                            >Sıra
                            <b
                                >{{ bus.layout.rows
                                }}{{
                                    bus.layout.back
                                        ? ` + arka ${bus.layout.back}`
                                        : ''
                                }}</b
                            ></span
                        >
                        <span
                            >Koltuk
                            <b
                                >{{ stats.seats
                                }}{{
                                    stats.reserved
                                        ? `+${stats.reserved} rehber`
                                        : ''
                                }}</b
                            ></span
                        >
                        <span v-if="bus.plate"
                            >Plaka <b>{{ bus.plate }}</b></span
                        >
                        <span v-if="bus.driver_name"
                            >Şoför
                            <b
                                >{{ bus.driver_name }}
                                {{ bus.driver_phone ?? '' }}</b
                            ></span
                        >
                    </div>
                    <div class="legend">
                        <span><i style="background: var(--m-ok)" />Rehber</span>
                        <span
                            ><i
                                style="
                                    background: linear-gradient(
                                        160deg,
                                        var(--m-accent),
                                        var(--m-k)
                                    );
                                "
                            />Kadın</span
                        >
                        <span
                            ><i
                                style="
                                    background: linear-gradient(
                                        160deg,
                                        var(--m-accent),
                                        var(--m-e)
                                    );
                                "
                            />Erkek</span
                        >
                        <span
                            ><i style="border: 1.5px dashed var(--m-e)" />Ön
                            sıra</span
                        >
                        <span
                            role="button"
                            tabindex="0"
                            @click="showWarnings = !showWarnings"
                            ><i style="background: var(--m-warn)" />{{
                                warnings.length ? 'Uyarı var' : 'Uyarı yok'
                            }}</span
                        >
                    </div>
                    <ul
                        v-if="showWarnings && warnings.length"
                        class="lbl"
                        style="margin: 0; padding-left: 18px"
                    >
                        <li v-for="w in warnings" :key="w.seat + w.text">
                            Koltuk {{ w.seat }} · {{ w.name }}: {{ w.text }}
                        </li>
                    </ul>

                    <div class="vbox">
                        <div
                            class="veh"
                            :class="body"
                            :style="{ '--pt': `${pt}px` }"
                        >
                            <span v-if="!hasDriver" class="wheel" />
                            <span class="mirror" style="left: -8px" />
                            <span class="mirror" style="right: -8px" />
                            <span
                                v-if="!hasDriver"
                                class="door"
                                style="top: 30px; height: 38px"
                            />
                            <span
                                v-else
                                class="door slide"
                                :style="{
                                    top: `${pt + S + GAP - 4}px`,
                                    height: `${S * 2 + GAP + 8}px`,
                                }"
                            />
                            <span
                                v-if="doorRow >= 0"
                                class="door"
                                :style="{
                                    top: `${pt + doorRow * (S + GAP) - 4}px`,
                                    height: `${S + 8}px`,
                                }"
                            />
                            <div
                                class="vgrid"
                                :style="{ gridTemplateColumns: columns }"
                            >
                                <template v-for="(row, ri) in rows" :key="ri">
                                    <div
                                        v-if="row.back"
                                        class="back"
                                        style="grid-column: 1 / -1"
                                    >
                                        <template
                                            v-for="seat in row.seats"
                                            :key="seat"
                                        >
                                            <div
                                                v-if="isReserved(seat)"
                                                class="slot guide"
                                                :title="`${seat} · Rehber`"
                                            >
                                                R
                                            </div>
                                            <div
                                                v-else-if="
                                                    occupant(seat)
                                                        ?.registration_id ===
                                                    null
                                                "
                                                class="slot lock"
                                                title="Başka grup"
                                            />
                                            <div
                                                v-else-if="occupant(seat)"
                                                class="slot full"
                                                :class="[
                                                    g(
                                                        occupant(seat)!.gender,
                                                    ).toLowerCase(),
                                                    {
                                                        warn: occupant(seat)!
                                                            .warnings.length,
                                                    },
                                                ]"
                                                data-drop="slot"
                                                :data-key="seat"
                                                :data-pid="
                                                    occupant(seat)!
                                                        .registration_id
                                                "
                                                data-from="slot"
                                                :data-locked="
                                                    can.update ? undefined : ''
                                                "
                                                :title="seatTitle(seat)"
                                            >
                                                {{
                                                    ini(
                                                        occupant(seat)!
                                                            .full_name,
                                                    )
                                                }}
                                            </div>
                                            <div
                                                v-else
                                                class="slot free"
                                                :class="{
                                                    front: inFront(seat),
                                                }"
                                                data-drop="slot"
                                                :data-key="seat"
                                                :title="seatTitle(seat)"
                                                @click="clickSeat(seat)"
                                            >
                                                {{ seat }}
                                            </div>
                                        </template>
                                    </div>
                                    <template
                                        v-for="(item, ii) in row.items"
                                        :key="`${ri}-${ii}`"
                                    >
                                        <div
                                            v-if="item.kind === 'driver'"
                                            class="drv"
                                        >
                                            <span title="Şoför" />
                                        </div>
                                        <div
                                            v-else-if="item.kind === 'door'"
                                            class="doorgap"
                                            :style="{
                                                gridColumn: `span ${item.span}`,
                                            }"
                                        >
                                            Kapı
                                        </div>
                                        <div
                                            v-else-if="item.kind === 'empty'"
                                        />
                                        <div
                                            v-else-if="isReserved(item.seat)"
                                            class="slot guide"
                                            :title="`${item.seat} · Rehber`"
                                        >
                                            R
                                        </div>
                                        <div
                                            v-else-if="
                                                occupant(item.seat)
                                                    ?.registration_id === null
                                            "
                                            class="slot lock"
                                            title="Başka grup"
                                        />
                                        <div
                                            v-else-if="occupant(item.seat)"
                                            class="slot full"
                                            :class="[
                                                g(
                                                    occupant(item.seat)!.gender,
                                                ).toLowerCase(),
                                                {
                                                    warn: occupant(item.seat)!
                                                        .warnings.length,
                                                    front: inFront(item.seat),
                                                },
                                            ]"
                                            data-drop="slot"
                                            :data-key="item.seat"
                                            :data-pid="
                                                occupant(item.seat)!
                                                    .registration_id
                                            "
                                            data-from="slot"
                                            :data-locked="
                                                can.update ? undefined : ''
                                            "
                                            :title="seatTitle(item.seat)"
                                        >
                                            {{
                                                ini(
                                                    occupant(item.seat)!
                                                        .full_name,
                                                )
                                            }}
                                        </div>
                                        <div
                                            v-else
                                            class="slot free"
                                            :class="{
                                                front: inFront(item.seat),
                                            }"
                                            data-drop="slot"
                                            :data-key="item.seat"
                                            :title="seatTitle(item.seat)"
                                            @click="clickSeat(item.seat)"
                                        >
                                            {{ item.seat }}
                                        </div>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
