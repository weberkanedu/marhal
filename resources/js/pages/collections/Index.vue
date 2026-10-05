<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    HandCoins,
    Search,
    TrendingDown,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ExportMenu from '@/components/ExportMenu.vue';
import PaymentDialog from '@/components/payments/PaymentDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney } from '@/lib/format';
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/collections';
import { show as showRegistration } from '@/routes/registrations';
import { collections as collectionsReport } from '@/routes/reports';
import type { ExportItem } from '@/types/export';
import type { Option, Paginated } from '@/types/person';

type Tab = 'borclu' | 'tamamlanan' | 'tahsilatlar';

type RegistrationItem = {
    id: string;
    person: { id: string; full_name: string; phone: string | null };
    tour: { id: string; name: string };
    group: string | null;
    currency: string;
    net_price: string;
    paid: string;
    balance: string;
    overdue: string;
};

type PaymentItem = {
    id: string;
    registration_id: string;
    person: string;
    tour: string;
    type: 'tahsilat' | 'iade';
    method: string;
    amount: string;
    currency: string;
    paid_at: string;
    reference: string | null;
    received_by: string | null;
};

type Debtor = {
    id: string;
    label: string;
    tour: string;
    currency: string;
    balance: string;
};

type PayTarget = {
    id: string;
    currency: string;
    balance: string;
    person: string;
};

const props = defineProps<{
    tab: Tab;
    filters: { tour: string | null; from: string; to: string };
    tours: { id: string; name: string }[];
    rows: {
        items: Paginated<RegistrationItem | PaymentItem>;
        totals: Record<string, Record<string, string>>;
    };
    // Ana paneldeki hesapla aynı (App\Support\Collections\CollectionSummary).
    summary: {
        outstanding: Record<string, string>;
        overdue: { count: number; amounts: Record<string, string> };
        due_this_month: Record<string, string>;
        collected: Record<string, { this: string; last: string }>;
    };
    // Ertelenmiş: sayfa açıldıktan sonra gelir.
    debtors?: Debtor[];
    paymentOptions: { methods: Option[]; currencies: string[] };
    canPay: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tahsilat', href: index() }],
    },
});

const tabs: { value: Tab; label: string }[] = [
    { value: 'borclu', label: 'Borçlu yolcular' },
    { value: 'tamamlanan', label: 'Ödemesi tamamlananlar' },
    { value: 'tahsilatlar', label: 'Tahsilatlar' },
];

const tour = ref(props.filters.tour ?? '');
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function go(tab: Tab = props.tab): void {
    router.get(
        index.url(),
        {
            tab,
            tour: tour.value || undefined,
            ...(tab === 'tahsilatlar'
                ? { from: from.value, to: to.value }
                : {}),
        },
        { preserveScroll: true, preserveState: true },
    );
}

const page = usePage();
const reportsEnabled = computed(() =>
    (page.props.features ?? []).includes('basic_reports'),
);

// "Çıktı al": üç liste, ekrandaki tur / tarih süzgeciyle aynı veri.
const exportItems = computed<ExportItem[]>(() => {
    if (!reportsEnabled.value) {
        return [];
    }

    const tourQuery = props.filters.tour ? { tour: props.filters.tour } : {};
    const scope =
        props.tours.find((t) => t.id === props.filters.tour)?.name ??
        'Tüm turlar';

    return [
        {
            title: 'Borçlu yolcular',
            description: `${scope} · kalan ve gecikmiş`,
            url: collectionsReport.url({
                query: { tab: 'borclu', ...tourQuery },
            }),
        },
        {
            title: 'Tahsilat raporu',
            description: `${formatDate(props.filters.from)} – ${formatDate(props.filters.to)} · makbuz no`,
            url: collectionsReport.url({
                query: {
                    tab: 'tahsilatlar',
                    ...tourQuery,
                    from: props.filters.from,
                    to: props.filters.to,
                },
            }),
        },
        {
            title: 'Ödemesi tamamlananlar',
            description: scope,
            url: collectionsReport.url({
                query: { tab: 'tamamlanan', ...tourQuery },
            }),
        },
    ];
});

// Bu ay / geçen ay karşılaştırması (para birimi başına).
const collectedCurrencies = computed(() =>
    Object.keys(props.summary.collected),
);

function change(currency: string): number | null {
    const month = props.summary.collected[currency];

    if (!month || Number(month.last) <= 0) {
        return null;
    }

    return Math.round(
        ((Number(month.this) - Number(month.last)) / Number(month.last)) * 100,
    );
}

// Ödeme al: satırdaki düğmeden doğrudan, üstteki düğmeden önce yolcu seçilerek.
const payOpen = ref(false);
const pickOpen = ref(false);
const pickSearch = ref('');
const payTarget = ref<PayTarget | null>(null);

function pay(target: PayTarget): void {
    payTarget.value = target;
    pickOpen.value = false;
    payOpen.value = true;
}

const pickList = computed(() => {
    const term = pickSearch.value.trim().toLocaleLowerCase('tr');

    return (props.debtors ?? [])
        .filter(
            (d) =>
                term === '' ||
                `${d.label} ${d.tour}`.toLocaleLowerCase('tr').includes(term),
        )
        .slice(0, 50);
});

const asRegistrations = (items: unknown) => items as RegistrationItem[];
const asPayments = (items: unknown) => items as PaymentItem[];
</script>

<template>
    <Head title="Tahsilat" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Tahsilat</h1>
                <p class="text-sm text-muted-foreground">
                    Borçlar, gecikmeler ve alınan ödemeler. Para birimleri ayrı
                    tutulur.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <ExportMenu :items="exportItems" />
                <Button v-if="canPay" @click="pickOpen = true">
                    <HandCoins /> Ödeme al
                </Button>
            </div>
        </div>

        <!-- Özet: bu ay tahsilat, bu ay vadesi gelen, gecikmiş, kalan -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="kpi-tile items-start">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-success-soft text-success"
                >
                    <Wallet class="size-5" />
                </span>
                <div class="min-w-0">
                    <small>Bu ay tahsil edilen</small>
                    <span
                        v-if="collectedCurrencies.length === 0"
                        class="kpi-value num"
                        >—</span
                    >
                    <span
                        v-for="currency in collectedCurrencies"
                        :key="currency"
                        class="kpi-value num"
                    >
                        {{
                            formatMoney(
                                summary.collected[currency]?.this ?? '0',
                                currency,
                            )
                        }}
                        <span
                            v-if="change(currency) !== null"
                            class="ml-1 inline-flex items-center gap-0.5 text-xs font-semibold"
                            :class="
                                (change(currency) ?? 0) >= 0
                                    ? 'text-success'
                                    : 'text-danger'
                            "
                            :title="`Geçen ay: ${formatMoney(summary.collected[currency]?.last ?? '0', currency)}`"
                        >
                            <component
                                :is="
                                    (change(currency) ?? 0) >= 0
                                        ? TrendingUp
                                        : TrendingDown
                                "
                                class="size-3"
                            />
                            %{{ Math.abs(change(currency) ?? 0) }}
                        </span>
                    </span>
                </div>
            </div>
            <div class="kpi-tile items-start">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground"
                >
                    <CalendarClock class="size-5" />
                </span>
                <div class="min-w-0">
                    <small>Bu ay vadesi gelen taksit</small>
                    <span
                        v-if="Object.keys(summary.due_this_month).length === 0"
                        class="kpi-value num"
                        >—</span
                    >
                    <span
                        v-for="(amount, currency) in summary.due_this_month"
                        :key="currency"
                        class="kpi-value num"
                    >
                        {{ formatMoney(amount, String(currency)) }}
                    </span>
                </div>
            </div>
            <button
                type="button"
                class="kpi-tile items-start"
                @click="go('borclu')"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-danger-soft text-danger"
                >
                    <AlertTriangle class="size-5" />
                </span>
                <div class="min-w-0">
                    <small>Gecikmiş · {{ summary.overdue.count }} yolcu</small>
                    <span
                        v-if="summary.overdue.count === 0"
                        class="kpi-value num text-success"
                        >Gecikme yok</span
                    >
                    <span
                        v-for="(amount, currency) in summary.overdue.amounts"
                        :key="currency"
                        class="kpi-value num text-danger"
                    >
                        {{ formatMoney(amount, String(currency)) }}
                    </span>
                </div>
            </button>
            <div class="kpi-tile items-start">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-warning-soft text-warning"
                >
                    <HandCoins class="size-5" />
                </span>
                <div class="min-w-0">
                    <small>Toplam kalan alacak</small>
                    <span
                        v-if="Object.keys(summary.outstanding).length === 0"
                        class="kpi-value num"
                        >—</span
                    >
                    <span
                        v-for="(amount, currency) in summary.outstanding"
                        :key="currency"
                        class="kpi-value num text-warning"
                    >
                        {{ formatMoney(amount, String(currency)) }}
                    </span>
                </div>
            </div>
        </div>

        <nav
            class="flex gap-1 overflow-x-auto border-b"
            role="tablist"
            aria-label="Tahsilat listeleri"
        >
            <button
                v-for="item in tabs"
                :key="item.value"
                type="button"
                role="tab"
                :aria-selected="tab === item.value"
                class="shrink-0 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors"
                :class="
                    tab === item.value
                        ? 'border-primary font-medium text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="go(item.value)"
            >
                {{ item.label }}
            </button>
        </nav>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <Label for="tour" class="text-xs">Tur</Label>
                <select
                    id="tour"
                    v-model="tour"
                    :class="selectClass"
                    class="w-64"
                    @change="go()"
                >
                    <option value="">Tüm turlar</option>
                    <option v-for="t in tours" :key="t.id" :value="t.id">
                        {{ t.name }}
                    </option>
                </select>
            </div>
            <template v-if="tab === 'tahsilatlar'">
                <div class="grid gap-1">
                    <Label for="from" class="text-xs">Başlangıç</Label>
                    <Input id="from" v-model="from" type="date" class="w-40" />
                </div>
                <div class="grid gap-1">
                    <Label for="to" class="text-xs">Bitiş</Label>
                    <Input id="to" v-model="to" type="date" class="w-40" />
                </div>
                <Button size="sm" variant="outline" @click="go()"
                    >Uygula</Button
                >
            </template>
        </div>

        <!-- Bu listenin toplamları (tur seçilmemiş borçlu listesi üstteki özetle aynı olduğundan gösterilmez) -->
        <div
            v-if="
                Object.keys(rows.totals).length > 0 &&
                (tab !== 'borclu' || filters.tour)
            "
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Card v-for="(total, currency) in rows.totals" :key="currency">
                <CardHeader>
                    <template v-if="tab === 'tahsilatlar'">
                        <p class="text-sm text-muted-foreground">
                            Net tahsilat ({{ total.count }} işlem)
                        </p>
                        <CardTitle class="num text-2xl text-success">
                            {{ formatMoney(total.net, String(currency)) }}
                        </CardTitle>
                    </template>
                    <template v-else-if="tab === 'borclu'">
                        <p class="text-sm text-muted-foreground">
                            Kalan alacak ({{ currency }})
                        </p>
                        <CardTitle class="num text-2xl text-warning">
                            {{ formatMoney(total.balance, String(currency)) }}
                        </CardTitle>
                        <p
                            v-if="Number(total.overdue) > 0"
                            class="text-xs text-destructive"
                        >
                            Gecikmiş:
                            {{ formatMoney(total.overdue, String(currency)) }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            Tahsil edilen ({{ currency }})
                        </p>
                        <CardTitle class="num text-2xl text-success">
                            {{ formatMoney(total.paid, String(currency)) }}
                        </CardTitle>
                    </template>
                </CardHeader>
            </Card>
        </div>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <p
                    v-if="rows.items.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Bu listede kayıt yok.
                </p>

                <!-- Borçlu / tamamlanan -->
                <table
                    v-else-if="tab !== 'tahsilatlar'"
                    class="w-full text-sm whitespace-nowrap"
                >
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Yolcu</th>
                            <th class="px-4 py-2.5 font-medium">Tur / grup</th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Net
                            </th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Ödenen
                            </th>
                            <th
                                v-if="tab === 'borclu'"
                                class="px-4 py-2.5 text-right font-medium"
                            >
                                Kalan
                            </th>
                            <th
                                v-if="tab === 'borclu'"
                                class="px-4 py-2.5 text-right font-medium"
                            >
                                Gecikmiş
                            </th>
                            <th
                                v-if="tab === 'borclu' && canPay"
                                class="px-4 py-2.5"
                            >
                                <span class="sr-only">İşlem</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in asRegistrations(rows.items.data)"
                            :key="row.id"
                            class="cursor-pointer border-t hover:bg-muted"
                            @click="router.visit(showRegistration(row.id))"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="showRegistration(row.id)"
                                    class="font-medium"
                                    @click.stop
                                >
                                    {{ row.person.full_name }}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.person.phone }}
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                {{ row.tour.name }}
                                <span
                                    v-if="row.group"
                                    class="text-muted-foreground"
                                >
                                    · {{ row.group }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ formatMoney(row.net_price, row.currency) }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ formatMoney(row.paid, row.currency) }}
                            </td>
                            <td
                                v-if="tab === 'borclu'"
                                class="px-4 py-2 text-right font-medium text-warning tabular-nums"
                            >
                                {{ formatMoney(row.balance, row.currency) }}
                            </td>
                            <td
                                v-if="tab === 'borclu'"
                                class="px-4 py-2 text-right tabular-nums"
                                :class="
                                    Number(row.overdue) > 0
                                        ? 'font-medium text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    Number(row.overdue) > 0
                                        ? formatMoney(row.overdue, row.currency)
                                        : '—'
                                }}
                            </td>
                            <td
                                v-if="tab === 'borclu' && canPay"
                                class="px-4 py-2 text-right"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click.stop="
                                        pay({
                                            id: row.id,
                                            currency: row.currency,
                                            balance: row.balance,
                                            person: row.person.full_name,
                                        })
                                    "
                                >
                                    Ödeme al
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Tahsilatlar -->
                <table v-else class="w-full text-sm whitespace-nowrap">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Tarih</th>
                            <th class="px-4 py-2.5 font-medium">Yolcu</th>
                            <th class="px-4 py-2.5 font-medium">Tur</th>
                            <th class="px-4 py-2.5 font-medium">Yöntem</th>
                            <th class="px-4 py-2.5 font-medium">Makbuz no</th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Tutar
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in asPayments(rows.items.data)"
                            :key="row.id"
                            class="cursor-pointer border-t hover:bg-muted"
                            @click="
                                router.visit(
                                    showRegistration(row.registration_id),
                                )
                            "
                        >
                            <td class="px-4 py-2">
                                {{ formatDate(row.paid_at) }}
                            </td>
                            <td class="px-4 py-2 font-medium">
                                {{ row.person }}
                            </td>
                            <td class="px-4 py-2">{{ row.tour }}</td>
                            <td class="px-4 py-2">
                                {{ row.method }}
                                <span
                                    v-if="row.received_by"
                                    class="text-xs text-muted-foreground"
                                >
                                    · {{ row.received_by }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ row.reference ?? '—' }}
                            </td>
                            <td
                                class="px-4 py-2 text-right font-medium tabular-nums"
                                :class="
                                    row.type === 'iade'
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                {{ row.type === 'iade' ? '− ' : '' }}
                                {{ formatMoney(row.amount, row.currency) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div
            v-if="rows.items.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <span class="text-muted-foreground">
                {{ rows.items.from }}–{{ rows.items.to }} /
                {{ rows.items.total }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!rows.items.prev_page_url"
                    @click="
                        rows.items.prev_page_url &&
                        router.visit(rows.items.prev_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Önceki
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!rows.items.next_page_url"
                    @click="
                        rows.items.next_page_url &&
                        router.visit(rows.items.next_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Sonraki
                </Button>
            </div>
        </div>
    </div>

    <!-- Ödeme al: önce yolcu seç -->
    <Dialog v-model:open="pickOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Ödeme al</DialogTitle>
                <DialogDescription>
                    Borcu olan yolcuyu seçin; tutar, yöntem ve tarihi sonra
                    girersiniz.
                </DialogDescription>
            </DialogHeader>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="pickSearch"
                    class="pl-9"
                    placeholder="Yolcu veya tur ara"
                />
            </div>
            <ul class="-mx-2 max-h-80 overflow-y-auto">
                <li
                    v-if="debtors === undefined"
                    class="p-4 text-center text-sm text-muted-foreground"
                >
                    Yükleniyor…
                </li>
                <li
                    v-else-if="pickList.length === 0"
                    class="p-4 text-center text-sm text-muted-foreground"
                >
                    Borcu olan yolcu bulunamadı.
                </li>
                <li v-for="debtor in pickList" :key="debtor.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-muted"
                        @click="
                            pay({
                                id: debtor.id,
                                currency: debtor.currency,
                                balance: debtor.balance,
                                person: debtor.label,
                            })
                        "
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">
                                {{ debtor.label }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                {{ debtor.tour }}
                            </span>
                        </span>
                        <span
                            class="shrink-0 font-semibold text-warning tabular-nums"
                        >
                            {{ formatMoney(debtor.balance, debtor.currency) }}
                        </span>
                    </button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>

    <PaymentDialog
        v-if="payTarget"
        v-model:open="payOpen"
        :registration="payTarget"
        :person="payTarget.person"
        :options="paymentOptions"
        type="tahsilat"
    />
</template>
