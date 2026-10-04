<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney } from '@/lib/format';
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/collections';
import { show as showRegistration } from '@/routes/registrations';
import { collections as collectionsReport } from '@/routes/reports';

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

const props = defineProps<{
    tab: Tab;
    filters: { tour: string | null; from: string; to: string };
    tours: { id: string; name: string }[];
    rows: {
        items: (RegistrationItem | PaymentItem)[];
        totals: Record<string, Record<string, string>>;
    };
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

// Rapor, ekranda uygulanmış filtrelerle aynı veriyi üretir.
const exportUrl = computed(() =>
    collectionsReport.url({
        query: {
            tab: props.tab,
            ...(props.filters.tour ? { tour: props.filters.tour } : {}),
            ...(props.tab === 'tahsilatlar'
                ? { from: props.filters.from, to: props.filters.to }
                : {}),
        },
    }),
);

const asRegistrations = (items: unknown) => items as RegistrationItem[];
const asPayments = (items: unknown) => items as PaymentItem[];
</script>

<template>
    <Head title="Tahsilat" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold tracking-tight">Tahsilat</h1>

        <div class="flex flex-wrap gap-1">
            <Button
                v-for="item in tabs"
                :key="item.value"
                size="sm"
                :variant="tab === item.value ? 'secondary' : 'ghost'"
                @click="go(item.value)"
            >
                {{ item.label }}
            </Button>
        </div>

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

        <ExportButtons
            v-if="reportsEnabled && rows.items.length > 0"
            :url="exportUrl"
            label="Bu listeyi indir"
        />

        <!-- Toplamlar -->
        <div
            v-if="Object.keys(rows.totals).length > 0"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Card v-for="(total, currency) in rows.totals" :key="currency">
                <CardHeader>
                    <template v-if="tab === 'tahsilatlar'">
                        <p class="text-sm text-muted-foreground">
                            Net tahsilat ({{ total.count }} işlem)
                        </p>
                        <CardTitle class="text-2xl text-success">
                            {{ formatMoney(total.net, String(currency)) }}
                        </CardTitle>
                    </template>
                    <template v-else-if="tab === 'borclu'">
                        <p class="text-sm text-muted-foreground">
                            Kalan alacak ({{ currency }})
                        </p>
                        <CardTitle class="text-2xl text-warning">
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
                        <CardTitle class="text-2xl text-success">
                            {{ formatMoney(total.paid, String(currency)) }}
                        </CardTitle>
                    </template>
                </CardHeader>
            </Card>
        </div>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <p
                    v-if="rows.items.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Bu listede kayıt yok.
                </p>

                <!-- Borçlu / tamamlanan -->
                <table
                    v-else-if="tab !== 'tahsilatlar'"
                    class="w-full text-sm whitespace-nowrap"
                >
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Yolcu</th>
                            <th class="px-4 py-2 font-medium">Tur / grup</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Net
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Ödenen
                            </th>
                            <th
                                v-if="tab === 'borclu'"
                                class="px-4 py-2 text-right font-medium"
                            >
                                Kalan
                            </th>
                            <th
                                v-if="tab === 'borclu'"
                                class="px-4 py-2 text-right font-medium"
                            >
                                Gecikmiş
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in asRegistrations(rows.items)"
                            :key="row.id"
                            class="cursor-pointer border-t hover:bg-muted/40"
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
                        </tr>
                    </tbody>
                </table>

                <!-- Tahsilatlar -->
                <table v-else class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Tarih</th>
                            <th class="px-4 py-2 font-medium">Yolcu</th>
                            <th class="px-4 py-2 font-medium">Tur</th>
                            <th class="px-4 py-2 font-medium">Yöntem</th>
                            <th class="px-4 py-2 font-medium">Makbuz</th>
                            <th class="px-4 py-2 text-right font-medium">
                                Tutar
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in asPayments(rows.items)"
                            :key="row.id"
                            class="cursor-pointer border-t hover:bg-muted/40"
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
                            <td class="px-4 py-2">
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
    </div>
</template>
