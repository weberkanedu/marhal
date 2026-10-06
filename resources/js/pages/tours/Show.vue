<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { IdCard, Pencil, Trash2, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import GroupController from '@/actions/App/Http/Controllers/GroupController';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import MockIcon from '@/components/mock/MockIcon.vue';
import MockRing from '@/components/mock/MockRing.vue';
import MockTop from '@/components/mock/MockTop.vue';
import BusesCard from '@/components/tours/BusesCard.vue';
import FlightsCard from '@/components/tours/FlightsCard.vue';
import GroupDialog from '@/components/tours/GroupDialog.vue';
import RegistrationDialog from '@/components/tours/RegistrationDialog.vue';
import ReadinessTab from '@/components/tours/ReadinessTab.vue';
import StaysCard from '@/components/tours/StaysCard.vue';
import { index as collectionsIndex } from '@/routes/collections';
import { show as showPerson } from '@/routes/persons';
import { show as showRegistration } from '@/routes/registrations';
import {
    badges as badgeReport,
    passengers as passengerReport,
    payments as paymentReport,
    program as programReport,
    readiness as readinessReport,
} from '@/routes/reports/tours';
import { badgeCards, edit, index } from '@/routes/tours';
import type { TourBus, VehicleTypeOption } from '@/types/bus';
import type { ExportItem } from '@/types/export';
import type { TourFlight } from '@/types/flight';
import type { HotelOption, TourStay } from '@/types/hotel';
import type { Option } from '@/types/person';
import type {
    JourneyStep,
    ReadinessBoardData,
    RegistrationRow,
    TourGroup,
    TourReadinessSummary,
    TourShowOptions,
    TourStats,
    TourSummary,
} from '@/types/tour';

/**
 * Tur detayı — tasarım sayfasındaki "Tur" ekranının birebir hâli (gerçek veriyle): tarih ve durum,
 * yolculuk çizelgesi, hazırlık halkaları; Yolcular / Konaklama / Ulaşım sekmeleri.
 */
const props = defineProps<{
    tour: TourSummary;
    stats: TourStats;
    journey: JourneyStep[];
    // Rehberde null (tur geneli değil kendi grubu).
    readiness: TourReadinessSummary | null;
    groups: TourGroup[];
    registrations: RegistrationRow[];
    // Modül kapalıysa null.
    stays: TourStay[] | null;
    buses: TourBus[] | null;
    flights: TourFlight[] | null;
    // Hazırlık modülü kapalıysa null; açıksa sekme açılınca (ertelenmiş) gelir.
    readinessBoard?: ReadinessBoardData | null;
    options: TourShowOptions & {
        flightDirections: Option[];
        hotels: HotelOption[];
        vehicleTypes: VehicleTypeOption[];
    };
    can: {
        update: boolean;
        delete: boolean;
        viewFinance: boolean;
        viewPersons: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: index() }],
    },
});

const page = usePage();
const features = computed(() => page.props.features ?? []);
const paymentsEnabled = computed(() => features.value.includes('payments'));
const reportsEnabled = computed(() => features.value.includes('basic_reports'));
const badgesEnabled = computed(
    () => features.value.includes('badge_generation') && props.can.update,
);

// Başlık: "23 Ekim – 6 Kasım 2026 · 15 gün"
const dateRange = computed(() => {
    const a = new Date(`${props.tour.start_date}T00:00:00`);
    const b = new Date(`${props.tour.end_date}T00:00:00`);
    const sameYear = a.getFullYear() === b.getFullYear();
    const left = a.toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
        ...(sameYear ? {} : { year: 'numeric' }),
    });
    const right = b.toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const days = Math.round((b.getTime() - a.getTime()) / 86_400_000) + 1;

    return `${left} – ${right} · ${days} gün`;
});
const statusChip = computed(
    () =>
        ({ satista: 'ok', iptal: 'danger', taslak: 'warning' })[
            props.tour.status as string
        ] ?? '',
);

// "Çıktı al" menüsü
const exportItems = computed<ExportItem[]>(() => {
    if (!reportsEnabled.value) {
        return [];
    }

    const items: ExportItem[] = [
        {
            title: 'Yolcu listesi',
            description: props.can.update
                ? 'Oda ve koltuk sütunlarıyla'
                : 'Grubunuz',
            url: passengerReport.url(props.tour.id),
        },
        ...(props.can.update && props.groups.length > 1
            ? props.groups.map((g) => ({
                  title: `Yolcu listesi · ${g.name}`,
                  description: 'Yalnız bu grup',
                  url: passengerReport.url(props.tour.id, {
                      query: { group: g.id },
                  }),
              }))
            : []),
    ];

    if (badgesEnabled.value) {
        items.push({
            title: 'Yaka kartları',
            description: 'Fotoğraflı, toplu ya da tek tek',
            url: badgeReport.url(props.tour.id),
            pdfOnly: true,
        });
    }

    if (props.readinessBoard !== null) {
        items.push({
            title: 'Hazırlık listesi',
            description: 'Yolcu × madde, kim hazır',
            url: readinessReport.url(props.tour.id),
        });
    }

    items.push({
        title: 'Tur programı',
        description: 'Çizelge, uçuş ve oteller',
        url: programReport.url(props.tour.id),
    });

    if (paymentsEnabled.value && props.can.viewFinance) {
        items.push({
            title: 'Ödeme durumu',
            description: 'Yolcu bazında ödenen / kalan',
            url: paymentReport.url(props.tour.id),
        });
    }

    return items;
});

// Yolculuk çizelgesi (tasarımdaki simgeler).
const journeyIcon = (step: JourneyStep) =>
    step.kind === 'prep'
        ? 'home'
        : step.kind === 'stay'
          ? /medine/i.test(step.title)
              ? 'mosque'
              : 'kaaba'
          : 'plane';
const progress = computed(() => {
    const last = props.journey.length - 1;
    const now = props.journey.findIndex((s) => s.state === 'now');
    const reached =
        now === -1
            ? props.journey.every((s) => s.state === 'done')
                ? last
                : 0
            : now;

    return last > 0 ? reached / last : 0;
});
const shortDate = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
function stepText(step: JourneyStep): string {
    if (step.kind === 'prep') {
        const next = props.journey[1];
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const left = next
            ? Math.round(
                  (new Date(`${next.start}T00:00:00`).getTime() -
                      today.getTime()) /
                      86_400_000,
              )
            : 0;

        return step.state === 'done'
            ? 'Tamamlandı'
            : left > 0
              ? `${left} gün kaldı`
              : 'Bugün yola çıkılıyor';
    }

    // Tasarımdaki gibi: otelde "Otel · 7 gece", uçuşta "25 Eki · TK 0098".
    if (step.kind === 'stay') {
        const nights = Math.round(
            (new Date(`${step.end}T00:00:00`).getTime() -
                new Date(`${step.start}T00:00:00`).getTime()) /
                86_400_000,
        );

        return [step.detail, nights > 0 ? `${nights} gece` : null]
            .filter(Boolean)
            .join(' · ');
    }

    const rest = (step.detail ?? '')
        .split(' · ')
        .filter((part) => part && !part.includes('→'));

    return [shortDate(step.start), ...rest].join(' · ');
}

// Uçuş adımının başlığı güzergâh ("IST → JED"), yoksa "Gidiş / Dönüş".
const stepTitle = (step: JourneyStep) =>
    step.kind === 'outbound' || step.kind === 'return'
        ? ((step.detail ?? '').split(' · ').find((p) => p.includes('→')) ??
          step.title)
        : step.title;

// Hazırlık halkaları
const compactMoney = (amount: string | number) =>
    new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: props.tour.currency,
        notation: 'compact',
        maximumFractionDigits: 2,
    }).format(Number(amount));

// WhatsApp grubu: davet bağlantısını panoya kopyalar.
async function copyWhatsappLink(): Promise<void> {
    if (!props.tour.whatsapp_link) {
        toast.info(
            'Bu turun WhatsApp grup bağlantısı yok. "Düzenle"den ekleyebilirsiniz.',
        );

        return;
    }

    try {
        await navigator.clipboard.writeText(props.tour.whatsapp_link);
        toast.success('WhatsApp grubunun davet bağlantısı kopyalandı.');
    } catch {
        window.prompt('Bağlantıyı kopyalayın:', props.tour.whatsapp_link);
    }
}

// Sekmeler (adres ?tab= ile açılır; plan ekranlarından geri gelince doğru sekme).
type TabKey = 'yolcular' | 'konaklama' | 'ulasim' | 'hazirlik';
const tabs = computed(() => [
    { key: 'yolcular' as TabKey, label: 'Yolcular' },
    ...(props.stays !== null
        ? [{ key: 'konaklama' as TabKey, label: 'Konaklama' }]
        : []),
    ...(props.flights !== null || props.buses !== null
        ? [{ key: 'ulasim' as TabKey, label: 'Ulaşım' }]
        : []),
    ...(props.readinessBoard !== null
        ? [{ key: 'hazirlik' as TabKey, label: 'Hazırlık' }]
        : []),
]);
const query = new URL(page.url, 'http://x').searchParams;
const tab = ref<TabKey>(
    tabs.value.some((t) => t.key === query.get('tab'))
        ? (query.get('tab') as TabKey)
        : 'yolcular',
);
function selectTab(key: string): void {
    if (!tabs.value.some((t) => t.key === key)) {
        return;
    }

    tab.value = key as TabKey;
    const url = new URL(window.location.href);

    if (key === 'yolcular') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', key);
    }

    window.history.replaceState(window.history.state, '', url);
}

// Yolcular: arama + grup süzgeci (?grup=yok → grupsuzlar, ?grup=<id>).
const search = ref('');
const initialGroup = query.get('grup');
const groupFilter = ref<string>(
    initialGroup === 'yok'
        ? 'none'
        : initialGroup && props.groups.some((g) => g.id === initialGroup)
          ? initialGroup
          : 'all',
);
const showCancelled = ref(false);
const norm = (s: string) => s.toLocaleLowerCase('tr');
const visible = computed(() =>
    props.registrations.filter((r) => {
        if (!showCancelled.value && r.status === 'iptal') {
            return false;
        }

        if (
            groupFilter.value !== 'all' &&
            (groupFilter.value === 'none'
                ? r.group_id !== null
                : r.group_id !== groupFilter.value)
        ) {
            return false;
        }

        return norm(r.person.full_name).includes(norm(search.value));
    }),
);
const selectedGroup = computed(
    () => props.groups.find((g) => g.id === groupFilter.value) ?? null,
);

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
const paidPct = (r: RegistrationRow) =>
    Number(r.net_price) > 0
        ? Math.min(
              100,
              Math.round((Number(r.paid) / Number(r.net_price)) * 100),
          )
        : 100;
const seatText = (r: RegistrationRow) => {
    if (!r.seat) {
        return null;
    }

    const no = r.seat.label.match(/^(\d+)/)?.[1];

    return `${no ? `O${no}` : r.seat.label} · ${r.seat.value}`;
};
const statusChips: Record<string, [string, string]> = {
    kesin_kayit: ['ok', 'Kesin kayıt'],
    on_kayit: ['', 'Ön kayıt'],
    iptal: ['danger', 'İptal'],
};

// Diyaloglar
const groupDialogOpen = ref(false);
const editingGroup = ref<TourGroup | null>(null);
const registrationDialogOpen = ref(false);
const editingRegistration = ref<RegistrationRow | null>(null);

function openGroup(group: TourGroup | null): void {
    editingGroup.value = group;
    groupDialogOpen.value = true;
}

function openRegistration(registration: RegistrationRow | null): void {
    editingRegistration.value = registration;
    registrationDialogOpen.value = true;
}

function deleteGroup(group: TourGroup): void {
    if (
        confirm(
            `${group.name} silinsin mi? Gruptaki ${group.registrations_count} yolcu turda kalır, grupsuz olur.`,
        )
    ) {
        router.delete(GroupController.destroy.url(group.id), {
            preserveScroll: true,
            onSuccess: () => (groupFilter.value = 'all'),
        });
    }
}

function removeRegistration(registration: RegistrationRow): void {
    if (
        confirm(
            `${registration.person.full_name} bu turdan çıkarılsın mı? (Ödemesi varsa çıkarılamaz, iptal edilmelidir.)`,
        )
    ) {
        router.delete(RegistrationController.destroy.url(registration.id), {
            preserveScroll: true,
        });
    }
}

const badgeUrl = (id: string) =>
    badgeReport.url(props.tour.id, { query: { registration: id } });
</script>

<template>
    <Head :title="tour.name" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: 'Turlar', href: index.url() }]"
                :title="tour.name"
                :exports="exportItems"
            >
                <Link
                    v-if="badgesEnabled"
                    class="btn ghost"
                    :href="badgeCards(tour.id)"
                    >Yaka kartları</Link
                >
                <button
                    v-if="tour.whatsapp_link || can.update"
                    class="btn ghost"
                    type="button"
                    @click="copyWhatsappLink"
                >
                    <MockIcon name="chat" />WhatsApp grubu
                </button>
                <Link
                    v-if="can.update"
                    class="btn ghost"
                    :href="edit(tour.id)"
                    title="Tur bilgilerini düzenle"
                    ><MockIcon name="edit" />Düzenle</Link
                >
                <button
                    v-if="can.update"
                    class="btn"
                    type="button"
                    @click="openRegistration(null)"
                >
                    <MockIcon name="plus" />Yolcu kaydet
                </button>
            </MockTop>

            <div class="card glow">
                <h4>
                    {{ dateRange }}
                    <em
                        ><span class="chip" :class="statusChip">{{
                            tour.status_label
                        }}</span></em
                    >
                </h4>
                <div
                    class="journey"
                    :style="{ '--prog': progress, '--steps': journey.length }"
                >
                    <div
                        v-for="step in journey"
                        :key="step.key"
                        class="st"
                        :class="{ now: step.state === 'now' }"
                    >
                        <span class="dot"
                            ><MockIcon :name="journeyIcon(step)"
                        /></span>
                        <div>
                            <b>{{ stepTitle(step) }}</b
                            ><br /><small>{{ stepText(step) }}</small>
                        </div>
                    </div>
                </div>
                <div v-if="readiness" class="kpis">
                    <div
                        class="kpi"
                        role="button"
                        tabindex="0"
                        @click="selectTab('yolcular')"
                    >
                        <MockRing
                            :done="stats.registered"
                            :total="tour.capacity ?? stats.registered"
                        />
                        <div>
                            <small>Kayıt</small
                            ><span
                                >{{ stats.registered
                                }}{{
                                    tour.capacity ? ` / ${tour.capacity}` : ''
                                }}</span
                            >
                        </div>
                    </div>
                    <Link
                        v-if="readiness.collection && stats.total !== null"
                        class="kpi"
                        :href="collectionsIndex()"
                    >
                        <MockRing
                            :done="Number(readiness.collection.paid)"
                            :total="Number(readiness.collection.total)"
                        />
                        <div>
                            <small>Tahsilat</small
                            ><span
                                >{{ compactMoney(readiness.collection.paid) }} /
                                {{
                                    compactMoney(readiness.collection.total)
                                }}</span
                            >
                        </div>
                    </Link>
                    <div
                        v-for="check in readiness.checks"
                        :key="check.key"
                        class="kpi"
                        role="button"
                        tabindex="0"
                        @click="selectTab(check.tab)"
                    >
                        <MockRing :done="check.done" :total="check.total" />
                        <div>
                            <small>{{ check.label }}</small
                            ><span>{{ check.done }} / {{ check.total }}</span>
                        </div>
                    </div>
                </div>
                <span v-else class="lbl">{{ stats.registered }} yolcu</span>
            </div>

            <div class="card">
                <div class="tabs" role="tablist">
                    <a
                        v-for="t in tabs"
                        :key="t.key"
                        role="tab"
                        :class="{ on: tab === t.key }"
                        :aria-selected="tab === t.key"
                        @click="selectTab(t.key)"
                        >{{ t.label }}</a
                    >
                </div>

                <template v-if="tab === 'yolcular'">
                    <div class="row">
                        <div class="search">
                            <MockIcon name="search" /><input
                                v-model="search"
                                placeholder="Yolcu ara: ad soyad"
                                aria-label="Yolcu ara"
                            />
                        </div>
                        <div class="pills">
                            <span
                                class="pill"
                                :class="{ on: groupFilter === 'all' }"
                                role="button"
                                tabindex="0"
                                @click="groupFilter = 'all'"
                                >Tümü · {{ stats.registered }}</span
                            >
                            <span
                                v-for="g in groups"
                                :key="g.id"
                                class="pill"
                                :class="{ on: groupFilter === g.id }"
                                role="button"
                                tabindex="0"
                                :title="
                                    g.guide_name
                                        ? `Rehber: ${g.guide_name}`
                                        : undefined
                                "
                                @click="groupFilter = g.id"
                                >{{ g.name }} ·
                                {{ g.registrations_count }}</span
                            >
                            <span
                                v-if="stats.unassigned > 0"
                                class="pill"
                                :class="{ on: groupFilter === 'none' }"
                                role="button"
                                tabindex="0"
                                style="color: var(--m-warn)"
                                @click="groupFilter = 'none'"
                                >Grupsuz · {{ stats.unassigned }}</span
                            >
                            <span
                                v-if="stats.cancelled > 0"
                                class="pill"
                                :class="{ on: showCancelled }"
                                role="button"
                                tabindex="0"
                                @click="showCancelled = !showCancelled"
                                >İptaller · {{ stats.cancelled }}</span
                            >
                        </div>
                        <template v-if="can.update">
                            <button
                                v-if="selectedGroup"
                                class="btn ghost sm"
                                type="button"
                                @click="openGroup(selectedGroup)"
                            >
                                <MockIcon name="edit" />Grubu düzenle
                            </button>
                            <button
                                v-if="selectedGroup"
                                class="btn ghost sm"
                                type="button"
                                @click="deleteGroup(selectedGroup)"
                            >
                                Sil
                            </button>
                            <button
                                class="btn ghost sm"
                                type="button"
                                @click="openGroup(null)"
                            >
                                <MockIcon name="plus" />Grup ekle
                            </button>
                        </template>
                    </div>
                    <div class="tbl">
                        <table>
                            <thead>
                                <tr>
                                    <th>Yolcu</th>
                                    <th>Grup</th>
                                    <th class="num">Oda</th>
                                    <th class="num">Koltuk</th>
                                    <th v-if="can.viewFinance">Ödeme</th>
                                    <th v-else>Telefon</th>
                                    <th>Durum</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in visible"
                                    :key="r.id"
                                    :style="
                                        r.status === 'iptal'
                                            ? 'opacity: 0.55'
                                            : undefined
                                    "
                                >
                                    <td>
                                        <div class="person">
                                            <span
                                                class="av"
                                                :class="
                                                    r.person.gender === 'kadin'
                                                        ? 'k'
                                                        : 'e'
                                                "
                                                >{{
                                                    ini(r.person.full_name)
                                                }}</span
                                            >
                                            <div>
                                                <Link
                                                    v-if="can.viewPersons"
                                                    :href="
                                                        showPerson(r.person.id)
                                                    "
                                                    >{{
                                                        r.person.full_name
                                                    }}</Link
                                                ><template v-else>{{
                                                    r.person.full_name
                                                }}</template>
                                                <small
                                                    >{{
                                                        r.person.gender ===
                                                        'kadin'
                                                            ? 'Kadın'
                                                            : 'Erkek'
                                                    }}<template
                                                        v-if="
                                                            r.person.age !==
                                                            null
                                                        "
                                                    >
                                                        ·
                                                        {{ r.person.age }}
                                                        yaş</template
                                                    ><template
                                                        v-if="
                                                            r.person
                                                                .passport_missing ||
                                                            r.person
                                                                .passport_expiring
                                                        "
                                                    >
                                                        ·
                                                        <span
                                                            style="
                                                                color: var(
                                                                    --m-warn
                                                                );
                                                            "
                                                            >{{
                                                                r.person
                                                                    .passport_missing
                                                                    ? 'Pasaport yok'
                                                                    : 'Pasaport kısa'
                                                            }}</span
                                                        ></template
                                                    ></small
                                                >
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            v-if="r.group_name"
                                            class="chip acc"
                                            >{{ r.group_name }}</span
                                        >
                                        <span v-else class="chip warning"
                                            >Grupsuz</span
                                        >
                                        <span
                                            v-for="need in r.needs"
                                            :key="need"
                                            class="tag"
                                            style="margin-left: 4px"
                                            >{{ need }}</span
                                        >
                                    </td>
                                    <td
                                        class="num"
                                        :title="
                                            r.rooms
                                                .map(
                                                    (x) =>
                                                        `${x.label} ${x.value}`,
                                                )
                                                .join(' · ')
                                        "
                                    >
                                        <template v-if="r.rooms.length">{{
                                            r.rooms
                                                .map((x) => x.value)
                                                .join(' · ')
                                        }}</template>
                                        <span v-else class="chip warning"
                                            >Yok</span
                                        >
                                    </td>
                                    <td class="num">
                                        <template v-if="r.seat">{{
                                            seatText(r)
                                        }}</template>
                                        <span v-else class="chip warning"
                                            >Yok</span
                                        >
                                    </td>
                                    <td v-if="can.viewFinance">
                                        <div
                                            style="
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                            "
                                        >
                                            <div
                                                class="bar"
                                                style="width: 80px"
                                            >
                                                <i
                                                    :class="{
                                                        full:
                                                            paidPct(r) === 100,
                                                    }"
                                                    :style="{
                                                        width: `${paidPct(r)}%`,
                                                    }"
                                                />
                                            </div>
                                            <small style="color: var(--m-muted)"
                                                >%{{ paidPct(r) }}</small
                                            >
                                        </div>
                                    </td>
                                    <td v-else class="num">
                                        <a
                                            v-if="r.person.phone"
                                            :href="`tel:${r.person.phone}`"
                                            >{{ r.person.phone }}</a
                                        >
                                        <template v-else>—</template>
                                    </td>
                                    <td>
                                        <span
                                            class="chip"
                                            :class="statusChips[r.status]?.[0]"
                                            >{{
                                                statusChips[r.status]?.[1]
                                            }}</span
                                        >
                                    </td>
                                    <td class="acts-cell">
                                        <span class="acts">
                                            <a
                                                v-if="
                                                    badgesEnabled &&
                                                    r.status !== 'iptal'
                                                "
                                                :href="badgeUrl(r.id)"
                                                title="Yaka kartı (PDF)"
                                                ><IdCard class="size-3.5"
                                            /></a>
                                            <Link
                                                v-if="
                                                    paymentsEnabled &&
                                                    can.viewFinance
                                                "
                                                :href="showRegistration(r.id)"
                                                title="Ödemeler"
                                                ><Wallet class="size-3.5"
                                            /></Link>
                                            <template v-if="can.update">
                                                <button
                                                    type="button"
                                                    title="Düzenle"
                                                    @click="openRegistration(r)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Turdan çıkar"
                                                    @click="
                                                        removeRegistration(r)
                                                    "
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </template>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!visible.length" class="empty-row">
                                    <td colspan="7">
                                        {{
                                            search
                                                ? 'Aramaya uyan yolcu yok'
                                                : 'Bu listede yolcu yok'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-if="tour.notes"
                        class="lbl"
                        style="white-space: pre-line; margin: 0"
                    >
                        <b>Notlar:</b> {{ tour.notes }}
                    </p>
                </template>

                <ReadinessTab
                    v-if="tab === 'hazirlik' && readinessBoard !== null"
                    :tour-id="tour.id"
                    :board="readinessBoard"
                    :groups="groups"
                    :can-update="can.update"
                />

                <StaysCard
                    v-if="tab === 'konaklama' && stays !== null"
                    :tour="tour"
                    :stays="stays"
                    :groups="groups"
                    :hotels="options.hotels"
                    :can-update="can.update"
                />

                <div v-if="tab === 'ulasim'" class="g3">
                    <BusesCard
                        v-if="buses !== null"
                        :tour="tour"
                        :buses="buses"
                        :groups="groups"
                        :vehicle-types="options.vehicleTypes"
                        :can-update="can.update"
                    />
                    <FlightsCard
                        v-if="flights !== null"
                        :tour="tour"
                        :flights="flights"
                        :directions="options.flightDirections"
                        :can-update="can.update"
                    />
                </div>
            </div>
        </div>
    </div>

    <GroupDialog
        v-model:open="groupDialogOpen"
        :tour-id="tour.id"
        :group="editingGroup"
        :guides="options.guides"
    />
    <RegistrationDialog
        v-model:open="registrationDialogOpen"
        :tour="tour"
        :groups="groups"
        :options="options"
        :registration="editingRegistration"
    />
</template>
