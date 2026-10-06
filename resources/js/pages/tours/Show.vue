<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BedDouble,
    Bus,
    CalendarDays,
    FileDown,
    IdCard,
    MessageCircle,
    Pencil,
    Plus,
    Trash2,
    UserPlus,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import GroupController from '@/actions/App/Http/Controllers/GroupController';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import GroupDialog from '@/components/tours/GroupDialog.vue';
import RegistrationDialog from '@/components/tours/RegistrationDialog.vue';
import BusesCard from '@/components/tours/BusesCard.vue';
import FlightsCard from '@/components/tours/FlightsCard.vue';
import StaysCard from '@/components/tours/StaysCard.vue';
import ExportMenu from '@/components/ExportMenu.vue';
import TourExports from '@/components/tours/TourExports.vue';
import TourJourney from '@/components/tours/TourJourney.vue';
import TourKpis from '@/components/tours/TourKpis.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';
import { show as showPerson } from '@/routes/persons';
import { show as showRegistration } from '@/routes/registrations';
import {
    badges as badgeReport,
    passengers as passengerReport,
    payments as paymentReport,
    program as programReport,
} from '@/routes/reports/tours';
import { badgeCards, destroy, edit, index } from '@/routes/tours';
import type { TourBus, VehicleTypeOption } from '@/types/bus';
import type { TourFlight } from '@/types/flight';
import type { Option } from '@/types/person';
import type { HotelOption, TourStay } from '@/types/hotel';
import { toast } from 'vue-sonner';
import type { ExportItem } from '@/types/export';
import { tourStatusVariant } from '@/types/tour';
import type {
    JourneyStep,
    RegistrationRow,
    TourGroup,
    TourShowOptions,
    TourReadinessSummary,
    TourStats,
    TourSummary,
} from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    stats: TourStats;
    journey: JourneyStep[];
    // Rehberde null (tur geneli değil kendi grubu).
    readiness: TourReadinessSummary | null;
    groups: TourGroup[];
    registrations: RegistrationRow[];
    // Oda planı modülü kapalıysa null.
    stays: TourStay[] | null;
    // Otobüs planı modülü kapalıysa null.
    buses: TourBus[] | null;
    // Uçuş listesi modülü kapalıysa null.
    flights: TourFlight[] | null;
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

const registrationStatusLabels: Record<string, string> = {
    on_kayit: 'Ön kayıt',
    kesin_kayit: 'Kesin kayıt',
    iptal: 'İptal',
};
const roomTypeLabels = computed(() =>
    Object.fromEntries(props.options.roomTypes.map((o) => [o.value, o.label])),
);

const page = usePage();
const paymentsEnabled = computed(() =>
    (page.props.features ?? []).includes('payments'),
);
const reportsEnabled = computed(() =>
    (page.props.features ?? []).includes('basic_reports'),
);
// Yaka kartı (Kurumsal paket): personel basar.
const badgesEnabled = computed(
    () =>
        (page.props.features ?? []).includes('badge_generation') &&
        props.can.update,
);

// Tek yolcunun yaka kartı (satırdaki kart simgesi).
function badgeUrl(registrationId: string): string {
    return badgeReport.url(props.tour.id, {
        query: { registration: registrationId },
    });
}

// "Çıktı al" menüsü: turun en sık kullanılan listeleri (hepsi "Çıktılar" sekmesinde).
const exportItems = computed<ExportItem[]>(() => {
    if (!reportsEnabled.value) {
        return [];
    }

    const items: ExportItem[] = [
        {
            title: 'Yolcu listesi',
            description: props.can.update ? 'Tüm tur' : 'Grubunuz',
            url: passengerReport.url(props.tour.id),
        },
        {
            title: 'Tur programı',
            description: 'Tarihler, oteller, uçuşlar',
            url: programReport.url(props.tour.id),
        },
    ];

    if (paymentsEnabled.value && props.can.viewFinance) {
        items.push({
            title: 'Ödeme durumu',
            description: 'Yolcu bazında ödenen / kalan',
            url: paymentReport.url(props.tour.id),
        });
    }

    if (badgesEnabled.value) {
        items.push({
            title: 'Yaka kartları',
            description: 'Tüm tur, A4 sayfaya dizili',
            url: badgeReport.url(props.tour.id),
            pdfOnly: true,
        });
    }

    return items;
});

// WhatsApp grubu: davet bağlantısını panoya kopyalar (yolcuya iletmek için).
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

// Grup filtresi: 'all' | 'none' | grup id. Adresten açılabilir: ?grup=yok (grupsuzlar) veya ?grup=<id>
// (ör. ana paneldeki "Gruba ata" düğmesi).
const initialGroup = new URL(page.url, 'http://x').searchParams.get('grup');
const groupFilter = ref<string>(
    initialGroup === 'yok'
        ? 'none'
        : initialGroup && props.groups.some((g) => g.id === initialGroup)
          ? initialGroup
          : 'all',
);

// Sekmeler: hangi sekmede olunduğu adreste (?tab=) tutulur; oda / koltuk / uçuş sayfasından
// "geri" gelince doğru sekme açılır.
type TabKey = 'yolcular' | 'konaklama' | 'ulasim' | 'ciktilar';
const tabs = computed(() => {
    const list: {
        key: TabKey;
        label: string;
        icon: unknown;
        count: number | null;
    }[] = [
        {
            key: 'yolcular',
            label: 'Yolcular',
            icon: Users,
            count: props.stats.registered,
        },
    ];

    if (props.stays !== null) {
        list.push({
            key: 'konaklama',
            label: 'Konaklama',
            icon: BedDouble,
            count: props.stays.length,
        });
    }

    if (props.flights !== null || props.buses !== null) {
        list.push({
            key: 'ulasim',
            label: 'Ulaşım',
            icon: Bus,
            count: (props.flights?.length ?? 0) + (props.buses?.length ?? 0),
        });
    }

    if (reportsEnabled.value) {
        list.push({
            key: 'ciktilar',
            label: 'Çıktılar',
            icon: FileDown,
            count: null,
        });
    }

    return list;
});

const initialTab = new URL(page.url, 'http://x').searchParams.get(
    'tab',
) as TabKey | null;
const tab = ref<TabKey>(
    tabs.value.some((t) => t.key === initialTab)
        ? (initialTab as TabKey)
        : 'yolcular',
);

function selectTab(key: TabKey | string): void {
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
const showCancelled = ref(false);

const visibleRegistrations = computed(() =>
    props.registrations.filter((r) => {
        if (!showCancelled.value && r.status === 'iptal') {
            return false;
        }

        if (groupFilter.value === 'all') {
            return true;
        }

        return groupFilter.value === 'none'
            ? r.group_id === null
            : r.group_id === groupFilter.value;
    }),
);

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

function deleteTour(): void {
    if (confirm(`${props.tour.name} silinsin mi?`)) {
        router.delete(destroy.url(props.tour.id));
    }
}

const occupancyText = computed(() =>
    props.tour.capacity
        ? `${props.stats.registered} / ${props.tour.capacity}`
        : String(props.stats.registered),
);
</script>

<template>
    <Head :title="tour.name" />

    <div class="flex w-full flex-col gap-4 p-4">
        <!-- Başlık -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ tour.name }}
                    </h1>
                    <Badge :variant="tourStatusVariant[tour.status]">
                        {{ tour.status_label }}
                    </Badge>
                </div>
                <p
                    class="mt-1 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CalendarDays class="size-4" />
                    {{ formatDate(tour.start_date) }} –
                    {{ formatDate(tour.end_date) }}
                    <template v-if="tour.default_price">
                        · Kişi başı
                        {{ formatMoney(tour.default_price, tour.currency) }}
                    </template>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="badgesEnabled" variant="outline" as-child>
                    <Link :href="badgeCards(tour.id)"
                        ><IdCard /> Yaka kartları</Link
                    >
                </Button>
                <Button
                    v-if="tour.whatsapp_link || can.update"
                    variant="outline"
                    @click="copyWhatsappLink"
                >
                    <MessageCircle /> WhatsApp grubu
                </Button>
                <ExportMenu :items="exportItems" />
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(tour.id)"><Pencil /> Düzenle</Link>
                </Button>
                <Button v-if="can.update" @click="openRegistration(null)">
                    <UserPlus /> Yolcu kaydet
                </Button>
                <Button
                    v-if="can.delete"
                    variant="ghost"
                    class="text-destructive"
                    @click="deleteTour"
                >
                    <Trash2 />
                </Button>
            </div>
        </div>

        <!-- Yolculuk çizelgesi -->
        <Card class="py-5">
            <CardContent>
                <TourJourney :steps="journey" />
            </CardContent>
        </Card>

        <!-- Gösterge halkaları (personel); rehber yalnız kendi grubunun sayısını görür -->
        <TourKpis
            v-if="readiness"
            :tour="tour"
            :stats="stats"
            :readiness="readiness"
            @select="selectTab"
        />
        <p v-else class="text-sm text-muted-foreground">
            {{ occupancyText }} yolcu
        </p>

        <!-- Sekmeler -->
        <nav
            class="-mb-1 flex gap-1 overflow-x-auto border-b"
            role="tablist"
            aria-label="Tur bölümleri"
        >
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="tab === item.key"
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-2 py-2 text-sm whitespace-nowrap transition-colors sm:px-3"
                :class="
                    tab === item.key
                        ? 'border-primary font-medium text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="selectTab(item.key)"
            >
                <component :is="item.icon" class="hidden size-4 sm:block" />
                {{ item.label }}
                <span
                    v-if="item.count !== null"
                    class="hidden rounded-full bg-muted px-1.5 text-xs text-muted-foreground sm:inline"
                >
                    {{ item.count }}
                </span>
            </button>
        </nav>

        <template v-if="tab === 'konaklama' && stays !== null">
            <StaysCard
                :tour="tour"
                :stays="stays"
                :groups="groups"
                :hotels="options.hotels"
                :can-update="can.update"
            />
        </template>

        <template v-if="tab === 'ulasim'">
            <FlightsCard
                v-if="flights !== null"
                :tour="tour"
                :flights="flights"
                :directions="options.flightDirections"
                :can-update="can.update"
            />
            <BusesCard
                v-if="buses !== null"
                :tour="tour"
                :buses="buses"
                :groups="groups"
                :vehicle-types="options.vehicleTypes"
                :can-update="can.update"
            />
        </template>

        <TourExports
            v-if="tab === 'ciktilar'"
            :tour="tour"
            :groups="groups"
            :stays="stays"
            :buses="buses"
            :flights="flights"
            :can="can"
        />

        <template v-if="tab === 'yolcular'">
            <div class="grid min-w-0 gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <!-- Gruplar -->
                <Card class="h-fit min-w-0">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Gruplar</CardTitle>
                        <Button
                            v-if="can.update"
                            variant="ghost"
                            size="sm"
                            @click="openGroup(null)"
                        >
                            <Plus /> Ekle
                        </Button>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-1 text-sm">
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 text-left hover:bg-muted"
                            :class="{
                                'bg-muted font-medium': groupFilter === 'all',
                            }"
                            @click="groupFilter = 'all'"
                        >
                            Tüm yolcular
                            <span class="text-muted-foreground">
                                {{ stats.registered }}
                            </span>
                        </button>
                        <div
                            v-for="group in groups"
                            :key="group.id"
                            class="group/item flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-muted"
                            :class="{ 'bg-muted': groupFilter === group.id }"
                        >
                            <button
                                type="button"
                                class="flex-1 text-left"
                                :class="{
                                    'font-medium': groupFilter === group.id,
                                }"
                                @click="groupFilter = group.id"
                            >
                                <div>{{ group.name }}</div>
                                <div
                                    v-if="group.guide_name"
                                    class="text-xs text-muted-foreground"
                                >
                                    Rehber: {{ group.guide_name }}
                                </div>
                            </button>
                            <span class="text-muted-foreground">
                                {{ group.registrations_count }}
                            </span>
                            <span
                                v-if="can.update"
                                class="ml-1 hidden gap-0.5 group-hover/item:flex"
                            >
                                <button
                                    type="button"
                                    class="rounded p-1 hover:bg-background"
                                    title="Düzenle"
                                    @click="openGroup(group)"
                                >
                                    <Pencil class="size-3" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1 text-destructive hover:bg-background"
                                    title="Sil"
                                    @click="deleteGroup(group)"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </span>
                        </div>
                        <button
                            v-if="stats.unassigned > 0"
                            type="button"
                            class="flex items-center justify-between rounded-md px-2 py-1.5 text-left text-warning hover:bg-muted"
                            :class="{
                                'bg-muted font-medium': groupFilter === 'none',
                            }"
                            @click="groupFilter = 'none'"
                        >
                            Grupsuz yolcular
                            <span>{{ stats.unassigned }}</span>
                        </button>
                    </CardContent>
                </Card>

                <!-- Kayıtlar -->
                <Card class="min-w-0 py-0">
                    <CardContent class="overflow-x-auto p-0">
                        <div
                            class="flex items-center justify-between border-b px-4 py-3"
                        >
                            <span class="text-sm font-medium">
                                {{ visibleRegistrations.length }} yolcu
                            </span>
                            <label
                                v-if="stats.cancelled > 0"
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <input
                                    v-model="showCancelled"
                                    type="checkbox"
                                />
                                İptalleri göster
                            </label>
                        </div>

                        <div
                            v-if="visibleRegistrations.length === 0"
                            class="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground"
                        >
                            <Users class="size-8" />
                            Bu listede yolcu yok.
                            <Button
                                v-if="can.update"
                                size="sm"
                                @click="openRegistration(null)"
                            >
                                <UserPlus /> Yolcu ekle
                            </Button>
                        </div>

                        <table v-else class="hidden w-full text-sm sm:table">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-2 font-medium">Yolcu</th>
                                    <th class="px-4 py-2 font-medium">Grup</th>
                                    <th class="px-4 py-2 font-medium">Oda</th>
                                    <th class="px-4 py-2 font-medium">Durum</th>
                                    <th
                                        v-if="!can.viewFinance"
                                        class="px-4 py-2 font-medium"
                                    >
                                        Telefon
                                    </th>
                                    <th
                                        v-if="!can.viewFinance"
                                        class="px-4 py-2 font-medium"
                                    >
                                        Acil durum
                                    </th>
                                    <th
                                        v-if="can.viewFinance"
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Ücret
                                    </th>
                                    <th
                                        v-if="can.viewFinance"
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Kalan
                                    </th>
                                    <th class="w-0 px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="registration in visibleRegistrations"
                                    :key="registration.id"
                                    class="border-t hover:bg-muted/40"
                                    :class="{
                                        'opacity-50':
                                            registration.status === 'iptal',
                                    }"
                                >
                                    <td class="px-4 py-2">
                                        <Link
                                            v-if="can.viewPersons"
                                            :href="
                                                showPerson(
                                                    registration.person.id,
                                                )
                                            "
                                            class="font-medium hover:underline"
                                        >
                                            {{ registration.person.full_name }}
                                        </Link>
                                        <span v-else class="font-medium">
                                            {{ registration.person.full_name }}
                                        </span>
                                        <div
                                            v-if="
                                                registration.person
                                                    .passport_missing ||
                                                registration.person
                                                    .passport_expiring
                                            "
                                            class="flex items-center gap-1 text-xs text-warning"
                                        >
                                            <AlertTriangle class="size-3" />
                                            {{
                                                registration.person
                                                    .passport_missing
                                                    ? 'Pasaport bilgisi yok'
                                                    : 'Pasaport süresi yetersiz'
                                            }}
                                        </div>
                                        <div
                                            v-if="registration.needs.length"
                                            class="mt-0.5 flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="need in registration.needs"
                                                :key="need"
                                                class="rounded-full bg-accent px-1.5 text-[11px] font-semibold text-accent-foreground"
                                            >
                                                {{ need }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span v-if="registration.group_name">
                                            {{ registration.group_name }}
                                        </span>
                                        <span v-else class="text-warning">
                                            Grupsuz
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        {{
                                            registration.room_type
                                                ? roomTypeLabels[
                                                      registration.room_type
                                                  ]
                                                : '—'
                                        }}
                                        <div
                                            v-if="
                                                registration.placements.length
                                            "
                                            class="text-xs whitespace-nowrap text-muted-foreground"
                                        >
                                            <span
                                                v-for="(
                                                    place, i
                                                ) in registration.placements"
                                                :key="place.label"
                                            >
                                                <template v-if="i > 0">
                                                    ·
                                                </template>
                                                {{ place.label }}
                                                <strong
                                                    class="text-foreground"
                                                    >{{ place.value }}</strong
                                                >
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Badge
                                            :variant="
                                                registration.status ===
                                                'kesin_kayit'
                                                    ? 'success'
                                                    : registration.status ===
                                                        'iptal'
                                                      ? 'danger'
                                                      : 'warning'
                                            "
                                        >
                                            {{
                                                registrationStatusLabels[
                                                    registration.status
                                                ]
                                            }}
                                        </Badge>
                                    </td>
                                    <td
                                        v-if="!can.viewFinance"
                                        class="px-4 py-2 tabular-nums"
                                    >
                                        <a
                                            v-if="registration.person.phone"
                                            :href="`tel:${registration.person.phone}`"
                                            class="hover:underline"
                                        >
                                            {{ registration.person.phone }}
                                        </a>
                                        <template v-else>—</template>
                                    </td>
                                    <td
                                        v-if="!can.viewFinance"
                                        class="px-4 py-2"
                                    >
                                        {{
                                            registration.person
                                                .emergency_contact ?? '—'
                                        }}
                                    </td>
                                    <td
                                        v-if="can.viewFinance"
                                        class="px-4 py-2 text-right tabular-nums"
                                    >
                                        {{
                                            formatMoney(
                                                registration.net_price,
                                                registration.currency,
                                            )
                                        }}
                                    </td>
                                    <td
                                        v-if="can.viewFinance"
                                        class="px-4 py-2 text-right font-medium tabular-nums"
                                        :class="
                                            Number(registration.balance) > 0
                                                ? 'text-warning'
                                                : 'text-success'
                                        "
                                    >
                                        {{
                                            formatMoney(
                                                registration.balance,
                                                registration.currency,
                                            )
                                        }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap">
                                        <Button
                                            v-if="
                                                badgesEnabled &&
                                                registration.status !== 'iptal'
                                            "
                                            variant="ghost"
                                            size="icon-sm"
                                            title="Yaka kartı (PDF)"
                                            as-child
                                        >
                                            <a
                                                :href="
                                                    badgeUrl(registration.id)
                                                "
                                            >
                                                <IdCard />
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="
                                                paymentsEnabled &&
                                                can.viewFinance
                                            "
                                            variant="ghost"
                                            size="icon-sm"
                                            title="Ödemeler"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    showRegistration(
                                                        registration.id,
                                                    )
                                                "
                                            >
                                                <Wallet />
                                            </Link>
                                        </Button>
                                        <template v-if="can.update">
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                title="Düzenle"
                                                @click="
                                                    openRegistration(
                                                        registration,
                                                    )
                                                "
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                class="text-destructive"
                                                title="Turdan çıkar"
                                                @click="
                                                    removeRegistration(
                                                        registration,
                                                    )
                                                "
                                            >
                                                <Trash2 />
                                            </Button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Telefon: tablo yerine kartlar (rehberin sahada kullanımı) -->
                        <ul
                            v-if="visibleRegistrations.length > 0"
                            class="divide-y sm:hidden"
                        >
                            <li
                                v-for="registration in visibleRegistrations"
                                :key="registration.id"
                                class="space-y-1.5 px-4 py-3"
                                :class="{
                                    'opacity-50':
                                        registration.status === 'iptal',
                                }"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span class="font-medium">
                                        {{ registration.person.full_name }}
                                    </span>
                                    <Badge
                                        :variant="
                                            registration.status ===
                                            'kesin_kayit'
                                                ? 'success'
                                                : registration.status ===
                                                    'iptal'
                                                  ? 'danger'
                                                  : 'warning'
                                        "
                                    >
                                        {{
                                            registrationStatusLabels[
                                                registration.status
                                            ]
                                        }}
                                    </Badge>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ registration.group_name ?? 'Grupsuz' }}
                                    <template v-if="registration.room_type">
                                        ·
                                        {{
                                            roomTypeLabels[
                                                registration.room_type
                                            ]
                                        }}
                                    </template>
                                </div>
                                <div
                                    v-if="registration.placements.length"
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="place in registration.placements"
                                        :key="place.label"
                                        variant="secondary"
                                    >
                                        {{ place.label }} {{ place.value }}
                                    </Badge>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                                >
                                    <a
                                        v-if="registration.person.phone"
                                        :href="`tel:${registration.person.phone}`"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ registration.person.phone }}
                                    </a>
                                    <span
                                        v-if="
                                            !can.viewFinance &&
                                            registration.person
                                                .emergency_contact
                                        "
                                        class="text-muted-foreground"
                                    >
                                        Acil:
                                        {{
                                            registration.person
                                                .emergency_contact
                                        }}
                                    </span>
                                    <span
                                        v-if="can.viewFinance"
                                        :class="
                                            Number(registration.balance) > 0
                                                ? 'text-warning'
                                                : 'text-success'
                                        "
                                    >
                                        Kalan:
                                        {{
                                            formatMoney(
                                                registration.balance,
                                                registration.currency,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        can.update ||
                                        (paymentsEnabled && can.viewFinance)
                                    "
                                    class="flex gap-2 pt-1"
                                >
                                    <Button
                                        v-if="
                                            paymentsEnabled && can.viewFinance
                                        "
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                showRegistration(
                                                    registration.id,
                                                )
                                            "
                                        >
                                            <Wallet /> Ödemeler
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="can.update"
                                        variant="outline"
                                        size="sm"
                                        @click="openRegistration(registration)"
                                    >
                                        <Pencil /> Düzenle
                                    </Button>
                                    <Button
                                        v-if="
                                            badgesEnabled &&
                                            registration.status !== 'iptal'
                                        "
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <a :href="badgeUrl(registration.id)">
                                            <IdCard /> Yaka kartı
                                        </a>
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="tour.notes">
                <CardHeader>
                    <CardTitle>Notlar</CardTitle>
                </CardHeader>
                <CardContent class="text-sm whitespace-pre-line">
                    {{ tour.notes }}
                </CardContent>
            </Card>
        </template>
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
