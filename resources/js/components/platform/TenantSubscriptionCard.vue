<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TenantSubscriptionController from '@/actions/App/Http/Controllers/Platform/TenantSubscriptionController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney } from '@/lib/format';
import { selectClass } from '@/lib/formClasses';

/**
 * Platform → acente: abonelik durumu, bekleyen paket talebi, "Ödeme geldi" (dönem açar), "+7 gün", ödeme geçmişi.
 * Tasarıma uydurulması 10c'de (Platform → Acenteler).
 */
export type TenantSubscription = {
    plan: { id: string; name: string };
    state: { value: string; label: string; tone: string };
    billing_cycle: string | null;
    billing_cycle_label: string | null;
    ends_at: string | null;
    days_left: number | null;
    grace_ends_at: string | null;
    started_at: string | null;
    request: {
        plan_id: string;
        plan: string;
        billing_cycle: string;
        billing_cycle_label: string;
        amount: string;
        at: string;
    } | null;
    payments: {
        id: string;
        paid_at: string;
        plan: string;
        billing_cycle_label: string;
        amount: string;
        currency: string;
        method_label: string;
        period_starts_at: string;
        period_ends_at: string;
    }[];
};

const props = defineProps<{
    tenantId: string;
    subscription: TenantSubscription;
    options: {
        plans: {
            id: string;
            name: string;
            price_monthly: string;
            price_yearly: string;
        }[];
        cycles: { value: string; label: string }[];
        methods: { value: string; label: string }[];
    };
}>();

const variant: Record<string, 'secondary' | 'destructive' | 'outline'> = {
    ok: 'secondary',
    acc: 'outline',
    warning: 'outline',
    danger: 'destructive',
};

// Form varsayılanı: bekleyen talep varsa o, yoksa mevcut paket ve dönem.
const planId = ref('');
const cycle = ref('');
const amount = ref('');

function reset(): void {
    const r = props.subscription.request;
    planId.value = r?.plan_id ?? props.subscription.plan.id;
    cycle.value =
        r?.billing_cycle ?? props.subscription.billing_cycle ?? 'yillik';
}

watch(() => props.subscription, reset, { immediate: true });

const price = computed(() => {
    const plan = props.options.plans.find((p) => p.id === planId.value);

    return plan
        ? cycle.value === 'yillik'
            ? plan.price_yearly
            : plan.price_monthly
        : '';
});

watch(price, (value) => (amount.value = String(Math.round(Number(value)))), {
    immediate: true,
});

const today = new Date().toISOString().slice(0, 10);

function extend(): void {
    router.post(
        TenantSubscriptionController.extend.url(props.tenantId),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                Abonelik
                <Badge :variant="variant[subscription.state.tone]">{{
                    subscription.state.label
                }}</Badge>
            </CardTitle>
            <CardDescription>
                {{ subscription.plan.name }}
                <template v-if="subscription.billing_cycle_label">
                    · {{ subscription.billing_cycle_label }}</template
                >
                ·
                {{
                    subscription.ends_at
                        ? `${subscription.state.value === 'deneme' ? 'deneme bitişi' : 'dönem sonu'} ${formatDate(subscription.ends_at)}`
                        : 'süresiz'
                }}
                <template v-if="subscription.grace_ends_at">
                    · {{ formatDate(subscription.grace_ends_at) }} tarihinde
                    salt okunur olur</template
                >
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <p
                v-if="subscription.request"
                class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm"
            >
                <b>Paket talebi:</b> {{ subscription.request.plan }} ·
                {{ subscription.request.billing_cycle_label }} ({{
                    formatMoney(subscription.request.amount, 'TRY')
                }}) — {{ formatDate(subscription.request.at) }}. Ödeme gelince
                aşağıdan "Ödeme geldi" deyin.
            </p>

            <Form
                v-bind="TenantSubscriptionController.payment.form(tenantId)"
                class="grid gap-3 sm:grid-cols-3"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-1.5">
                    <Label for="sp-plan">Paket</Label>
                    <select
                        id="sp-plan"
                        v-model="planId"
                        name="plan_id"
                        :class="selectClass"
                    >
                        <option
                            v-for="p in options.plans"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                    <InputError :message="errors.plan_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-cycle">Dönem</Label>
                    <select
                        id="sp-cycle"
                        v-model="cycle"
                        name="billing_cycle"
                        :class="selectClass"
                    >
                        <option
                            v-for="c in options.cycles"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-amount">Tutar (₺, KDV hariç)</Label>
                    <Input
                        id="sp-amount"
                        v-model="amount"
                        name="amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-method">Yöntem</Label>
                    <select id="sp-method" name="method" :class="selectClass">
                        <option
                            v-for="m in options.methods"
                            :key="m.value"
                            :value="m.value"
                        >
                            {{ m.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-date">Ödeme tarihi</Label>
                    <Input
                        id="sp-date"
                        name="paid_at"
                        type="date"
                        :max="today"
                        :default-value="today"
                    />
                    <InputError :message="errors.paid_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-note">Not</Label>
                    <Input
                        id="sp-note"
                        name="note"
                        placeholder="İsteğe bağlı"
                    />
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-3">
                    <Button type="submit" :disabled="processing"
                        >Ödeme geldi</Button
                    >
                    <Button type="button" variant="outline" @click="extend"
                        >+7 gün</Button
                    >
                </div>
            </Form>

            <div v-if="subscription.payments.length" class="overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-1 pr-3 font-medium">Tarih</th>
                            <th class="py-1 pr-3 font-medium">Paket</th>
                            <th class="py-1 pr-3 font-medium">Dönem</th>
                            <th class="py-1 pr-3 font-medium">Yöntem</th>
                            <th class="py-1 text-right font-medium">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in subscription.payments" :key="p.id">
                            <td class="py-1.5 pr-3">
                                {{ formatDate(p.paid_at) }}
                            </td>
                            <td class="py-1.5 pr-3">
                                {{ p.plan }} · {{ p.billing_cycle_label }}
                            </td>
                            <td class="py-1.5 pr-3">
                                {{ formatDate(p.period_starts_at) }} –
                                {{ formatDate(p.period_ends_at) }}
                            </td>
                            <td class="py-1.5 pr-3">{{ p.method_label }}</td>
                            <td class="py-1.5 text-right">
                                {{ formatMoney(p.amount, p.currency) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
