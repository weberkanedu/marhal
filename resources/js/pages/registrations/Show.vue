<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    CalendarClock,
    Trash2,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InstallmentPlanEditor from '@/components/payments/InstallmentPlanEditor.vue';
import PaymentDialog from '@/components/payments/PaymentDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';
import { destroy as destroyPayment } from '@/routes/payments';
import { show as showPerson } from '@/routes/persons';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type {
    InstallmentRow,
    PaymentOptions,
    PaymentRow,
    RegistrationDetail,
} from '@/types/payment';

const props = defineProps<{
    registration: RegistrationDetail;
    tour: { id: string; name: string; start_date: string; end_date: string };
    person: { id: string; full_name: string; phone: string | null };
    payments: PaymentRow[];
    installments: InstallmentRow[];
    options: PaymentOptions;
    can: { pay: boolean; deletePayment: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

const dialogOpen = ref(false);
const dialogType = ref<'tahsilat' | 'iade'>('tahsilat');
const editingPlan = ref(false);

function openPayment(type: 'tahsilat' | 'iade'): void {
    dialogType.value = type;
    dialogOpen.value = true;
}

function removePayment(payment: PaymentRow): void {
    if (
        confirm(
            `${formatDate(payment.paid_at)} tarihli ${formatMoney(payment.amount, payment.currency)} silinsin mi? (Hatalı giriş düzeltmek için)`,
        )
    ) {
        router.delete(destroyPayment.url(payment.id), { preserveScroll: true });
    }
}

const today = new Date().toISOString().slice(0, 10);
const isPaidOff = computed(() => Number(props.registration.balance) <= 0);

// Taksit satırlarının durumu: ödenen toplam vadeye göre sırayla taksitleri kapatır.
const installmentStatus = computed(() => {
    let remainingPaid = Number(props.registration.paid);

    return props.installments.map((row) => {
        const amount = Number(row.amount);
        const covered = Math.min(amount, Math.max(0, remainingPaid));
        remainingPaid -= amount;

        if (covered >= amount) {
            return 'paid';
        }

        return row.due_date < today
            ? 'overdue'
            : covered > 0
              ? 'partial'
              : 'open';
    });
});
</script>

<template>
    <Head :title="`${person.full_name} — Ödemeler`" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
        <!-- Başlık -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    <Link :href="showPerson(person.id)" class="hover:underline">
                        {{ person.full_name }}
                    </Link>
                </h1>
                <p class="text-sm text-muted-foreground">
                    <Link :href="showTour(tour.id)" class="hover:underline">
                        {{ tour.name }}
                    </Link>
                    <template v-if="registration.group">
                        · {{ registration.group }}
                    </template>
                    <template v-if="registration.room_type">
                        · {{ registration.room_type }}
                    </template>
                    ·
                    <Badge variant="secondary">{{
                        registration.status_label
                    }}</Badge>
                </p>
            </div>
            <div v-if="can.pay" class="flex gap-2">
                <Button @click="openPayment('tahsilat')">
                    <ArrowDownLeft /> Ödeme al
                </Button>
                <Button
                    v-if="Number(registration.paid) > 0"
                    variant="outline"
                    @click="openPayment('iade')"
                >
                    <ArrowUpRight /> İade
                </Button>
            </div>
        </div>

        <!-- Özet -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardDescription>Net ücret</CardDescription>
                    <CardTitle class="text-2xl">
                        {{
                            formatMoney(
                                registration.net_price,
                                registration.currency,
                            )
                        }}
                    </CardTitle>
                    <p
                        v-if="Number(registration.discount) > 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            formatMoney(
                                registration.price,
                                registration.currency,
                            )
                        }}
                        −
                        {{
                            formatMoney(
                                registration.discount,
                                registration.currency,
                            )
                        }}
                        indirim
                    </p>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Ödenen</CardDescription>
                    <CardTitle class="text-2xl text-success">
                        {{
                            formatMoney(
                                registration.paid,
                                registration.currency,
                            )
                        }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Kalan borç</CardDescription>
                    <CardTitle
                        class="text-2xl"
                        :class="isPaidOff ? 'text-success' : 'text-warning'"
                    >
                        {{
                            isPaidOff
                                ? 'Tamamlandı'
                                : formatMoney(
                                      registration.balance,
                                      registration.currency,
                                  )
                        }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Gecikmiş</CardDescription>
                    <CardTitle
                        class="text-2xl"
                        :class="
                            Number(registration.overdue) > 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            installments.length === 0
                                ? '—'
                                : formatMoney(
                                      registration.overdue,
                                      registration.currency,
                                  )
                        }}
                    </CardTitle>
                    <p
                        v-if="installments.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        Taksit planı yok
                    </p>
                </CardHeader>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Ödeme geçmişi -->
            <Card class="min-w-0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wallet class="size-4" /> Ödeme geçmişi
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="payments.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Henüz ödeme yok.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="payment in payments"
                            :key="payment.id"
                            class="flex items-start justify-between gap-3 py-2"
                        >
                            <div>
                                <div class="font-medium">
                                    <span
                                        :class="
                                            payment.type === 'iade'
                                                ? 'text-destructive'
                                                : ''
                                        "
                                    >
                                        {{
                                            payment.type === 'iade' ? '− ' : ''
                                        }}
                                        {{
                                            formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            payment.currency !==
                                            registration.currency
                                        "
                                        class="font-normal text-muted-foreground"
                                    >
                                        (≈
                                        {{
                                            formatMoney(
                                                payment.amount_in_registration_currency,
                                                registration.currency,
                                            )
                                        }}, kur
                                        {{ Number(payment.exchange_rate) }})
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ formatDate(payment.paid_at) }} ·
                                    {{ payment.method_label }}
                                    <template v-if="payment.reference">
                                        · No: {{ payment.reference }}
                                    </template>
                                    <template v-if="payment.received_by">
                                        · {{ payment.received_by }}
                                    </template>
                                </div>
                                <div
                                    v-if="payment.notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ payment.notes }}
                                </div>
                            </div>
                            <Button
                                v-if="can.deletePayment"
                                variant="ghost"
                                size="icon-sm"
                                class="text-destructive"
                                title="Hatalı girişi sil"
                                @click="removePayment(payment)"
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Taksit planı -->
            <Card class="min-w-0">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <CalendarClock class="size-4" /> Taksit planı
                    </CardTitle>
                    <Button
                        v-if="can.pay && !editingPlan"
                        variant="ghost"
                        size="sm"
                        @click="editingPlan = true"
                    >
                        {{ installments.length ? 'Düzenle' : 'Plan oluştur' }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <InstallmentPlanEditor
                        v-if="editingPlan"
                        :registration="registration"
                        :installments="installments"
                        @saved="editingPlan = false"
                    />
                    <template v-else>
                        <p
                            v-if="installments.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Taksit planı yok.
                        </p>
                        <ul v-else class="divide-y text-sm">
                            <li
                                v-for="(row, i) in installments"
                                :key="row.id ?? i"
                                class="flex items-center justify-between py-2"
                            >
                                <span>
                                    {{ formatDate(row.due_date) }}
                                    <span
                                        v-if="row.notes"
                                        class="text-xs text-muted-foreground"
                                    >
                                        · {{ row.notes }}
                                    </span>
                                </span>
                                <span class="flex items-center gap-2">
                                    {{
                                        formatMoney(
                                            row.amount,
                                            registration.currency,
                                        )
                                    }}
                                    <Badge
                                        v-if="installmentStatus[i] === 'paid'"
                                        variant="success"
                                    >
                                        Ödendi
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            installmentStatus[i] === 'overdue'
                                        "
                                        variant="danger"
                                    >
                                        <AlertTriangle /> Gecikti
                                    </Badge>
                                    <Badge
                                        v-else-if="
                                            installmentStatus[i] === 'partial'
                                        "
                                        variant="outline"
                                    >
                                        Kısmen
                                    </Badge>
                                    <Badge v-else variant="outline"
                                        >Bekliyor</Badge
                                    >
                                </span>
                            </li>
                        </ul>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>

    <PaymentDialog
        v-model:open="dialogOpen"
        :registration="registration"
        :options="options"
        :type="dialogType"
    />
</template>
