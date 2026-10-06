<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FlightSeatPlanController from '@/actions/App/Http/Controllers/FlightSeatPlanController';
import MockPool from '@/components/mock/MockPool.vue';
import type { PoolPerson } from '@/components/mock/MockPool.vue';
import MockTop from '@/components/mock/MockTop.vue';
import { usePointerDrag } from '@/composables/usePointerDrag';
import {
    assistance as assistanceReport,
    manifest,
    seats as seatReport,
} from '@/routes/reports/flights';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type { ExportItem } from '@/types/export';
import type { Gender } from '@/types/person';

/**
 * Uçak koltuk planı — tasarım sayfasındaki "Uçak planı" ekranının birebir hâli (gerçek veriyle).
 * Havayoluna gönderilen koltuk tercih listesidir; kesin koltuğu havayolu check-in'de verir.
 */
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
        flight_no: string;
        departure_airport: string;
        arrival_airport: string;
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

const root = ref<HTMLElement | null>(null);
const selected = ref<string | null>(null);
const blockMode = ref(false);
const showWarnings = ref(false);

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
const g = (p: Passenger): 'E' | 'K' => (p.gender === 'kadin' ? 'K' : 'E');

const byId = computed(() => new Map(props.passengers.map((p) => [p.id, p])));
const bySeat = computed(
    () =>
        new Map(
            props.passengers
                .filter((p) => p.seat_no)
                .map((p) => [p.seat_no as string, p]),
        ),
);
const seated = computed(() => bySeat.value.size);
const warnCount = computed(
    () => props.passengers.filter((p) => p.warnings.length).length,
);

// Uçak tipleri: acentenin tipleri + henüz eklenmemiş hazır tipler (tasarımdaki gibi hap düğmeler).
const short = (name: string) =>
    name.replace(/^Airbus\s+/i, '').replace(/^Boeing\s+/i, 'B');
const typePills = computed(() => [
    ...props.aircraftTypes.map((t) => ({
        key: `t:${t.id}`,
        label: short(t.name),
        on: t.id === props.flight.aircraft_type_id,
    })),
    ...props.presets
        .filter((p) => !props.aircraftTypes.some((t) => t.name === p.name))
        .map((p) => ({ key: `p:${p.key}`, label: short(p.name), on: false })),
]);

function chooseAircraft(key: string): void {
    const [kind, value] = key.split(':');
    router.put(
        FlightSeatPlanController.aircraft.url(props.flight.id),
        kind === 't' ? { aircraft_type_id: value } : { preset: value },
        { preserveScroll: true, onError: showError },
    );
}

// Kabin çizimi (tasarımdaki hesapla aynı: sütunlar, kanat ve motor konumu).
const wide = computed(() => (props.cabin?.groups.length ?? 0) > 2);
const columns = computed(() =>
    [
        '20px',
        ...(props.cabin?.groups ?? []).flatMap((gr) => [
            ...gr.map(() => 'var(--s)'),
            '20px',
        ]),
    ].join(' '),
);
const wingTop = computed(() => {
    if (!props.cabin) {
        return 0;
    }

    const size = wide.value ? 26 : 30;
    const anchor =
        props.cabin.exit_rows[0] ??
        props.cabin.rows[Math.floor(props.cabin.rows.length / 3)];
    const index = Math.max(0, props.cabin.rows.indexOf(anchor));

    return (wide.value ? 120 : 100) + 20 + index * (size + 6) - 30;
});
const wingStyle = computed(() => ({
    top: `${wingTop.value}px`,
    ...(wide.value ? { width: '160px', height: '150px' } : {}),
}));
const exitStarts = (row: number) =>
    !!props.cabin?.exit_rows.includes(row) &&
    !props.cabin.exit_rows.includes(row - 1);
const isBlocked = (seat: string) =>
    props.cabin?.blocked.includes(seat) ?? false;

const units = computed(() =>
    props.units
        .map((u) => ({
            label: u.label,
            people: u.ids
                .map((id) => byId.value.get(id))
                .filter((p): p is Passenger => !!p)
                .map((p): PoolPerson => ({
                    id: p.id,
                    name: p.full_name,
                    g: g(p),
                    age: p.age,
                    group: p.group_name,
                })),
        }))
        .filter((u) => u.people.length),
);

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

function place(pid: string, from: string | null, to: string): void {
    const who = byId.value.get(pid);

    if (!who || !props.can.update) {
        return;
    }

    if (to === 'list') {
        router.delete(FlightSeatPlanController.unassign.url(pid), {
            preserveScroll: true,
            onSuccess: () => toast(`${who.full_name} listeye döndü`),
            onError: showError,
        });

        return;
    }

    if (isBlocked(to)) {
        return;
    }

    router.post(
        FlightSeatPlanController.assign.url(props.flight.id),
        { passenger_id: pid, seat: to, swap: from !== null },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = null),
            onError: showError,
        },
    );
}

usePointerDrag(root, {
    label: (pid) => {
        const p = byId.value.get(pid);

        return p ? { initials: ini(p.full_name), name: p.full_name } : null;
    },
    onDrop: place,
    onClick: (pid, from) => {
        if (from === null && props.can.update) {
            selected.value = selected.value === pid ? null : pid;

            if (selected.value) {
                toast(
                    `${byId.value.get(pid)?.full_name} seçildi. Şimdi boş bir yere tıkla`,
                );
            }
        }
    },
});

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

    if (selected.value && !bySeat.value.has(seat) && !isBlocked(seat)) {
        place(selected.value, null, seat);
    }
}

function auto(): void {
    router.post(
        FlightSeatPlanController.auto.url(props.flight.id),
        {},
        { preserveScroll: true, onError: showError },
    );
}

function clear(): void {
    if (confirm('Bu uçuştaki bütün koltuk seçimleri kaldırılsın mı?')) {
        router.delete(FlightSeatPlanController.clear.url(props.flight.id), {
            preserveScroll: true,
            onError: showError,
        });
    }
}

const exportItems = computed<ExportItem[]>(() =>
    props.can.update
        ? [
              {
                  title: 'Havayolu listesi',
                  description: 'Pasaporttaki gibi BÜYÜK harf',
                  url: manifest.url(props.flight.id),
              },
              {
                  title: 'Koltuk tercih listesi',
                  description: 'Havayoluna gönderilecek',
                  url: seatReport.url(props.flight.id),
              },
              {
                  title: 'Özel yardım listesi',
                  description: 'Tekerlekli sandalye talepleri',
                  url: assistanceReport.url(props.flight.id),
              },
          ]
        : [],
);

const crumbs = computed(() => [
    { label: 'Turlar', href: toursIndex.url() },
    { label: props.flight.tour.name, href: showTour.url(props.flight.tour.id) },
    {
        label: 'Ulaşım',
        href: showTour.url(props.flight.tour.id, { query: { tab: 'ulasim' } }),
    },
]);

const seatPassenger = (row: number, letter: string) =>
    bySeat.value.get(`${row}${letter}`);
const pct = computed(() =>
    props.passengers.length
        ? Math.round((seated.value / props.passengers.length) * 100)
        : 0,
);
</script>

<template>
    <Head :title="`Uçak koltuk planı — ${flight.title}`" />

    <div ref="root" class="mx">
        <div class="main">
            <MockTop
                :crumbs="crumbs"
                title="Uçak koltuk planı"
                :exports="exportItems"
            >
                <template v-if="can.update && cabin">
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
                    hint="Acil çıkış sırasına 65 yaş üstü ve 15 yaş altı yolcu oturtulursa uyarı çıkar."
                />

                <div class="card">
                    <div class="stagebar">
                        <div class="pills">
                            <span
                                v-for="pill in typePills"
                                :key="pill.key"
                                class="pill"
                                :class="{ on: pill.on }"
                                role="button"
                                tabindex="0"
                                @click="
                                    can.update &&
                                    !pill.on &&
                                    chooseAircraft(pill.key)
                                "
                                >{{ pill.label }}</span
                            >
                        </div>
                        <div v-if="cabin" class="prog">
                            <span>{{ seated }} / {{ passengers.length }}</span>
                            <div class="bar">
                                <i
                                    :class="{ full: pct >= 100 }"
                                    :style="{ width: `${pct}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <template v-if="cabin">
                        <div class="spec">
                            <span
                                >Uçuş
                                <b
                                    >{{ flight.flight_no }} ·
                                    {{ flight.departure_airport }} →
                                    {{ flight.arrival_airport }}</b
                                ></span
                            >
                            <span
                                >Uçak <b>{{ flight.aircraft }}</b></span
                            >
                            <span
                                >Kabin
                                <b>{{
                                    cabin.groups.map((x) => x.length).join('-')
                                }}</b>
                                ·
                                {{
                                    wide
                                        ? 'geniş gövde, iki koridor'
                                        : 'dar gövde, tek koridor'
                                }}</span
                            >
                            <span v-if="cabin.exit_rows.length"
                                >Acil çıkış sırası
                                <b>{{ cabin.exit_rows.join(', ') }}</b></span
                            >
                        </div>
                        <div class="legend">
                            <span
                                ><i style="background: var(--m-line)" />Başka
                                yolcu</span
                            >
                            <span
                                role="button"
                                tabindex="0"
                                @click="showWarnings = !showWarnings"
                                ><i style="background: var(--m-warn)" />{{
                                    warnCount
                                        ? `${warnCount} uyarı`
                                        : 'Uyarı yok'
                                }}</span
                            >
                            <button
                                v-if="can.update"
                                class="btn ghost sm"
                                type="button"
                                :aria-pressed="blockMode"
                                @click="blockMode = !blockMode"
                            >
                                {{
                                    blockMode
                                        ? 'Bitti'
                                        : 'Başka yolcuya ait koltuk işaretle'
                                }}
                            </button>
                        </div>
                        <ul
                            v-if="showWarnings && warnCount"
                            class="lbl"
                            style="margin: 0; padding-left: 18px"
                        >
                            <template v-for="p in passengers" :key="p.id">
                                <li v-for="w in p.warnings" :key="w">
                                    {{ p.seat_no }} · {{ p.full_name }}:
                                    {{ w }}
                                </li>
                            </template>
                        </ul>

                        <div class="pbox">
                            <div class="pwrap">
                                <span class="wing l" :style="wingStyle" />
                                <span class="wing r" :style="wingStyle" />
                                <template v-if="wide">
                                    <span
                                        class="engine"
                                        :style="{
                                            left: '58px',
                                            top: `${wingTop + 56}px`,
                                        }"
                                    />
                                    <span
                                        class="engine"
                                        :style="{
                                            right: '58px',
                                            top: `${wingTop + 56}px`,
                                        }"
                                    />
                                </template>
                                <template v-else>
                                    <span
                                        class="engine"
                                        :style="{
                                            left: '74px',
                                            top: `${wingTop + 48}px`,
                                            height: '38px',
                                        }"
                                    />
                                    <span
                                        class="engine"
                                        :style="{
                                            right: '74px',
                                            top: `${wingTop + 48}px`,
                                            height: '38px',
                                        }"
                                    />
                                </template>
                                <div class="plane" :class="{ wide }">
                                    <div
                                        class="pgrid"
                                        :style="{
                                            gridTemplateColumns: columns,
                                        }"
                                    >
                                        <span />
                                        <template
                                            v-for="(gr, gi) in cabin.groups"
                                            :key="`h${gi}`"
                                        >
                                            <span
                                                v-for="l in gr"
                                                :key="l"
                                                class="hd"
                                                >{{ l }}</span
                                            >
                                            <span />
                                        </template>
                                        <template
                                            v-for="row in cabin.rows"
                                            :key="row"
                                        >
                                            <div
                                                v-if="exitStarts(row)"
                                                class="exit"
                                            >
                                                <span>◂ Acil çıkış</span
                                                ><span>Acil çıkış ▸</span>
                                            </div>
                                            <span class="rn">{{ row }}</span>
                                            <template
                                                v-for="(gr, gi) in cabin.groups"
                                                :key="`${row}-${gi}`"
                                            >
                                                <template
                                                    v-for="l in gr"
                                                    :key="`${row}${l}`"
                                                >
                                                    <div
                                                        v-if="
                                                            isBlocked(
                                                                `${row}${l}`,
                                                            )
                                                        "
                                                        class="slot lock"
                                                        :title="`${row}${l} · başka yolcu`"
                                                        @click="
                                                            blockMode &&
                                                            clickSeat(
                                                                `${row}${l}`,
                                                            )
                                                        "
                                                    />
                                                    <div
                                                        v-else-if="
                                                            seatPassenger(
                                                                row,
                                                                l,
                                                            )
                                                        "
                                                        class="slot full"
                                                        :class="[
                                                            g(
                                                                seatPassenger(
                                                                    row,
                                                                    l,
                                                                )!,
                                                            ).toLowerCase(),
                                                            {
                                                                warn: seatPassenger(
                                                                    row,
                                                                    l,
                                                                )!.warnings
                                                                    .length,
                                                            },
                                                        ]"
                                                        data-drop="slot"
                                                        :data-key="`${row}${l}`"
                                                        :data-pid="
                                                            seatPassenger(
                                                                row,
                                                                l,
                                                            )!.id
                                                        "
                                                        data-from="slot"
                                                        :data-locked="
                                                            can.update
                                                                ? undefined
                                                                : ''
                                                        "
                                                        :title="
                                                            [
                                                                seatPassenger(
                                                                    row,
                                                                    l,
                                                                )!.full_name,
                                                                ...seatPassenger(
                                                                    row,
                                                                    l,
                                                                )!.warnings,
                                                            ].join(' · ')
                                                        "
                                                    >
                                                        {{
                                                            ini(
                                                                seatPassenger(
                                                                    row,
                                                                    l,
                                                                )!.full_name,
                                                            )
                                                        }}
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="slot free"
                                                        data-drop="slot"
                                                        :data-key="`${row}${l}`"
                                                        :title="`${row}${l} · boş`"
                                                        @click="
                                                            clickSeat(
                                                                `${row}${l}`,
                                                            )
                                                        "
                                                    >
                                                        {{ row }}{{ l }}
                                                    </div>
                                                </template>
                                                <span class="rn">{{
                                                    row
                                                }}</span>
                                            </template>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="empty-pool">
                        Koltuk planı için yukarıdan uçak tipini seçin.
                    </div>
                    <span class="lbl"
                        >Kesin koltuğu havayolu check-in'de verir. Bu plan
                        havayoluna gönderilecek koltuk tercih listesidir.</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
