<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import MockTop from '@/components/mock/MockTop.vue';
import PaymentDialog from '@/components/payments/PaymentDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatDate, formatMoney } from '@/lib/format';
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
    next_due: string | null;
    late_days: number;
    gender: string;
    age: number | null;
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

// Tasarımdaki özet kartları: ilk para birimi büyük, diğerleri alt satırda.
const mainCurrency = computed(() => collectedCurrencies.value[0] ?? null);
const monthPct = computed(() => {
    const m = mainCurrency.value
        ? props.summary.collected[mainCurrency.value]
        : null;

    if (!m) {
        return 0;
    }

    const top = Math.max(Number(m.this), Number(m.last));

    return top > 0 ? Math.round((Number(m.this) / top) * 100) : 0;
});
const firstMoney = (amounts: Record<string, string>) => {
    const [currency, amount] = Object.entries(amounts)[0] ?? [];

    return currency ? formatMoney(amount, currency) : '—';
};
const paidPct = (row: RegistrationItem) =>
    Number(row.net_price) > 0
        ? Math.min(
              100,
              Math.round((Number(row.paid) / Number(row.net_price)) * 100),
          )
        : 100;
const shortDate = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: 'short',
    });
const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');

const asRegistrations = (items: unknown) => items as RegistrationItem[];
const asPayments = (items: unknown) => items as PaymentItem[];
</script>

<template>
    <Head title="Tahsilat" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: 'Tahsilat' }]"
                title="Tahsilat"
                :exports="exportItems"
            >
                <button
                    v-if="canPay"
                    class="btn"
                    type="button"
                    @click="pickOpen = true"
                >
                    <MockIcon name="wallet" />Ödeme al
                </button>
            </MockTop>

            <div class="g3">
                <div class="card glow">
                    <span class="lbl">Bu ay tahsil edilen</span>
                    <div class="big">
                        {{
                            mainCurrency
                                ? formatMoney(
                                      summary.collected[mainCurrency]?.this ??
                                          '0',
                                      mainCurrency,
                                  )
                                : '—'
                        }}
                    </div>
                    <div class="bar">
                        <i
                            :class="{ full: monthPct >= 100 }"
                            :style="{ width: `${monthPct}%` }"
                        />
                    </div>
                    <span class="lbl"
                        >Geçen ay
                        {{
                            mainCurrency
                                ? formatMoney(
                                      summary.collected[mainCurrency]?.last ??
                                          '0',
                                      mainCurrency,
                                  )
                                : '—'
                        }}<template
                            v-if="mainCurrency && change(mainCurrency) !== null"
                        >
                            ·
                            {{ (change(mainCurrency) ?? 0) >= 0 ? '▲' : '▼' }}
                            %{{ Math.abs(change(mainCurrency) ?? 0) }}</template
                        ><template
                            v-for="c in collectedCurrencies.slice(1)"
                            :key="c"
                        >
                            ·
                            {{
                                formatMoney(
                                    summary.collected[c]?.this ?? '0',
                                    c,
                                )
                            }}</template
                        ></span
                    >
                </div>
                <div class="card">
                    <span class="lbl">Toplam kalan alacak</span>
                    <div class="big">
                        {{ firstMoney(summary.outstanding) }}
                    </div>
                    <span class="lbl"
                        ><template
                            v-for="(amount, c, i) in summary.outstanding"
                            :key="c"
                            ><template v-if="i > 0"
                                >{{ formatMoney(amount, String(c)) }} ·
                            </template></template
                        >Bu ay vadesi gelen
                        {{ firstMoney(summary.due_this_month) }}</span
                    >
                </div>
                <div
                    class="card"
                    role="button"
                    tabindex="0"
                    style="cursor: pointer"
                    @click="go('borclu')"
                >
                    <span class="lbl">Vadesi geçmiş</span>
                    <div
                        class="big"
                        :style="
                            summary.overdue.count
                                ? 'color: var(--m-danger)'
                                : undefined
                        "
                    >
                        {{ summary.overdue.count }} yolcu
                    </div>
                    <span class="lbl">{{
                        summary.overdue.count
                            ? Object.entries(summary.overdue.amounts)
                                  .map(([c, a]) => formatMoney(a, c))
                                  .join(' · ')
                            : 'Gecikme yok'
                    }}</span>
                </div>
            </div>

            <div class="card">
                <div class="tabs" role="tablist">
                    <a
                        v-for="item in tabs"
                        :key="item.value"
                        role="tab"
                        :class="{ on: tab === item.value }"
                        :aria-selected="tab === item.value"
                        @click="go(item.value)"
                        >{{ item.label }}</a
                    >
                </div>
                <div class="row">
                    <select
                        v-model="tour"
                        class="mini-in"
                        aria-label="Tur"
                        @change="go()"
                    >
                        <option value="">Tüm turlar</option>
                        <option v-for="t in tours" :key="t.id" :value="t.id">
                            {{ t.name }}
                        </option>
                    </select>
                    <template v-if="tab === 'tahsilatlar'">
                        <input
                            v-model="from"
                            class="mini-in"
                            type="date"
                            aria-label="Başlangıç"
                        />
                        <input
                            v-model="to"
                            class="mini-in"
                            type="date"
                            aria-label="Bitiş"
                        />
                        <button
                            class="btn ghost sm"
                            type="button"
                            @click="go()"
                        >
                            Uygula
                        </button>
                    </template>
                    <span
                        v-for="(total, currency) in rows.totals"
                        :key="currency"
                        class="lbl"
                        >{{
                            tab === 'tahsilatlar'
                                ? `Net ${formatMoney(total.net, String(currency))} · ${total.count} işlem`
                                : tab === 'borclu'
                                  ? `Kalan ${formatMoney(total.balance, String(currency))}`
                                  : `Tahsil edilen ${formatMoney(total.paid, String(currency))}`
                        }}</span
                    >
                </div>

                <div class="tbl">
                    <table v-if="tab === 'borclu'">
                        <thead>
                            <tr>
                                <th>Yolcu</th>
                                <th class="hide-sm">Tur / grup</th>
                                <th class="num">Toplam</th>
                                <th class="num">Ödenen</th>
                                <th class="num">Kalan</th>
                                <th>İlerleme</th>
                                <th>Sonraki taksit</th>
                                <th v-if="canPay" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in asRegistrations(rows.items.data)"
                                :key="row.id"
                                style="cursor: pointer"
                                @click="router.visit(showRegistration(row.id))"
                            >
                                <td>
                                    <div class="person">
                                        <span
                                            class="av"
                                            :class="
                                                row.gender === 'kadin'
                                                    ? 'k'
                                                    : 'e'
                                            "
                                            >{{
                                                ini(row.person.full_name)
                                            }}</span
                                        >
                                        <div>
                                            {{ row.person.full_name
                                            }}<small>{{
                                                [
                                                    row.gender === 'kadin'
                                                        ? 'Kadın'
                                                        : 'Erkek',
                                                    row.age !== null
                                                        ? `${row.age} yaş`
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')
                                            }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-sm">
                                    {{ row.tour.name
                                    }}<template v-if="row.group">
                                        · {{ row.group }}</template
                                    >
                                </td>
                                <td class="num">
                                    {{
                                        formatMoney(row.net_price, row.currency)
                                    }}
                                </td>
                                <td class="num">
                                    {{ formatMoney(row.paid, row.currency) }}
                                </td>
                                <td class="num">
                                    <b>{{
                                        formatMoney(row.balance, row.currency)
                                    }}</b>
                                </td>
                                <td>
                                    <div class="bar" style="width: 70px">
                                        <i
                                            :style="{
                                                width: `${paidPct(row)}%`,
                                            }"
                                        />
                                    </div>
                                </td>
                                <td>
                                    <span
                                        v-if="row.next_due"
                                        class="chip"
                                        :class="{ danger: row.late_days > 0 }"
                                        >{{ shortDate(row.next_due)
                                        }}<template v-if="row.late_days > 0">
                                            · {{ row.late_days }} gün
                                            gecikti</template
                                        ></span
                                    >
                                    <span v-else class="lbl">—</span>
                                </td>
                                <td v-if="canPay">
                                    <button
                                        class="btn ghost sm"
                                        type="button"
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
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="!rows.items.data.length"
                                class="empty-row"
                            >
                                <td colspan="8">Borçlu yolcu kalmadı</td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else-if="tab === 'tamamlanan'">
                        <thead>
                            <tr>
                                <th>Yolcu</th>
                                <th class="hide-sm">Tur / grup</th>
                                <th class="num">Toplam</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in asRegistrations(rows.items.data)"
                                :key="row.id"
                                style="cursor: pointer"
                                @click="router.visit(showRegistration(row.id))"
                            >
                                <td>
                                    <div class="person">
                                        <span
                                            class="av"
                                            :class="
                                                row.gender === 'kadin'
                                                    ? 'k'
                                                    : 'e'
                                            "
                                            >{{
                                                ini(row.person.full_name)
                                            }}</span
                                        >
                                        <div>
                                            {{ row.person.full_name
                                            }}<small>{{
                                                row.gender === 'kadin'
                                                    ? 'Kadın'
                                                    : 'Erkek'
                                            }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-sm">
                                    {{ row.tour.name
                                    }}<template v-if="row.group">
                                        · {{ row.group }}</template
                                    >
                                </td>
                                <td class="num">
                                    {{
                                        formatMoney(row.net_price, row.currency)
                                    }}
                                </td>
                                <td><span class="chip ok">Tamamlandı</span></td>
                            </tr>
                            <tr
                                v-if="!rows.items.data.length"
                                class="empty-row"
                            >
                                <td colspan="4">Bu listede kayıt yok</td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Yolcu</th>
                                <th>Yöntem</th>
                                <th>Makbuz</th>
                                <th class="num">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in asPayments(rows.items.data)"
                                :key="row.id"
                                style="cursor: pointer"
                                @click="
                                    router.visit(
                                        showRegistration(row.registration_id),
                                    )
                                "
                            >
                                <td class="num" style="text-align: left">
                                    {{ shortDate(row.paid_at) }}
                                </td>
                                <td>
                                    <div class="person">
                                        <span class="av">{{
                                            ini(row.person)
                                        }}</span>
                                        <div>
                                            {{ row.person
                                            }}<small>{{ row.tour }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ row.method
                                    }}<template v-if="row.received_by">
                                        · {{ row.received_by }}</template
                                    >
                                </td>
                                <td>{{ row.reference ?? '—' }}</td>
                                <td
                                    class="num"
                                    :style="
                                        row.type === 'iade'
                                            ? 'color: var(--m-danger)'
                                            : undefined
                                    "
                                >
                                    <b
                                        >{{ row.type === 'iade' ? '− ' : ''
                                        }}{{
                                            formatMoney(
                                                row.amount,
                                                row.currency,
                                            )
                                        }}</b
                                    >
                                </td>
                            </tr>
                            <tr
                                v-if="!rows.items.data.length"
                                class="empty-row"
                            >
                                <td colspan="5">Bu aralıkta tahsilat yok</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="rows.items.last_page > 1" class="row">
                    <span class="lbl"
                        >{{ rows.items.from }}–{{ rows.items.to }} /
                        {{ rows.items.total }}</span
                    >
                    <span style="flex: 1" />
                    <button
                        class="btn ghost sm"
                        type="button"
                        :disabled="!rows.items.prev_page_url"
                        @click="
                            rows.items.prev_page_url &&
                            router.visit(rows.items.prev_page_url, {
                                preserveScroll: true,
                            })
                        "
                    >
                        Önceki
                    </button>
                    <button
                        class="btn ghost sm"
                        type="button"
                        :disabled="!rows.items.next_page_url"
                        @click="
                            rows.items.next_page_url &&
                            router.visit(rows.items.next_page_url, {
                                preserveScroll: true,
                            })
                        "
                    >
                        Sonraki
                    </button>
                </div>
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
