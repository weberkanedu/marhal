<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TenantSubscriptionController from '@/actions/App/Http/Controllers/Platform/TenantSubscriptionController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';

/**
 * Platform → "Ödeme geldi" penceresi (acente listesi ve acente sayfası). Form, bekleyen talep varsa
 * ondan, yoksa acentenin mevcut paket ve döneminden dolar; tutar paket fiyatından gelir, değiştirilebilir.
 */
export type PaymentOptions = {
    plans: {
        id: string;
        name: string;
        price_monthly: string;
        price_yearly: string;
    }[];
    cycles: { value: string; label: string }[];
    methods: { value: string; label: string }[];
};

export type PaymentTarget = {
    id: string;
    name: string;
    planId: string;
    cycle: string | null;
};

const props = defineProps<{
    target: PaymentTarget | null;
    options: PaymentOptions;
}>();

const emit = defineEmits<{ close: [] }>();

const planId = ref('');
const cycle = ref('yillik');
const amount = ref('');
const today = new Date().toISOString().slice(0, 10);

watch(
    () => props.target,
    (t) => {
        if (t) {
            planId.value = t.planId;
            cycle.value = t.cycle ?? 'yillik';
        }
    },
    { immediate: true },
);

const price = computed(() => {
    const plan = props.options.plans.find((p) => p.id === planId.value);

    return plan
        ? cycle.value === 'yillik'
            ? plan.price_yearly
            : plan.price_monthly
        : '';
});

watch(price, (v) => (amount.value = String(Math.round(Number(v)))), {
    immediate: true,
});
</script>

<template>
    <Dialog
        :open="target !== null"
        @update:open="(v: boolean) => !v && emit('close')"
    >
        <DialogContent v-if="target" class="sm:max-w-lg">
            <Form
                v-bind="TenantSubscriptionController.payment.form(target.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="emit('close')"
            >
                <DialogHeader>
                    <DialogTitle>Ödeme geldi · {{ target.name }}</DialogTitle>
                    <DialogDescription>
                        Yeni dönem açılır, hesap aktif olur, bekleyen talep
                        kapanır. Aktif ya da gecikmedeki acentede dönem eski
                        dönemin bittiği yerden devam eder.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="pr-plan">Paket</Label>
                        <select
                            id="pr-plan"
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
                        <Label for="pr-cycle">Dönem</Label>
                        <select
                            id="pr-cycle"
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
                        <Label for="pr-amount">Tutar (₺, KDV hariç)</Label>
                        <Input
                            id="pr-amount"
                            v-model="amount"
                            name="amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pr-method">Yöntem</Label>
                        <select
                            id="pr-method"
                            name="method"
                            :class="selectClass"
                        >
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
                        <Label for="pr-date">Ödeme tarihi</Label>
                        <Input
                            id="pr-date"
                            name="paid_at"
                            type="date"
                            :max="today"
                            :default-value="today"
                        />
                        <InputError :message="errors.paid_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pr-note">Not</Label>
                        <Input
                            id="pr-note"
                            name="note"
                            placeholder="İsteğe bağlı"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="emit('close')"
                        >Vazgeç</Button
                    >
                    <Button type="submit" :disabled="processing"
                        >Ödemeyi işle</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
