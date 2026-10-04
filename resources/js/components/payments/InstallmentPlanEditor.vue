<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { update } from '@/routes/registrations/installments';
import type { InstallmentRow, RegistrationDetail } from '@/types/payment';

const props = defineProps<{
    registration: RegistrationDetail;
    installments: InstallmentRow[];
}>();

const emit = defineEmits<{ saved: [] }>();

const rows = ref<InstallmentRow[]>([]);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

// "Eşit böl" yardımcısı
const splitCount = ref(3);
const splitStart = ref(new Date().toISOString().slice(0, 10));

function reset(): void {
    rows.value = props.installments.map((row) => ({ ...row }));
    errors.value = {};
}

watch(() => props.installments, reset, { immediate: true });

const total = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
);
const difference = computed(
    () => Number(props.registration.net_price) - total.value,
);

function addRow(): void {
    const last = rows.value.at(-1);
    const date = last ? new Date(last.due_date) : new Date();

    if (last) {
        date.setMonth(date.getMonth() + 1);
    }

    rows.value.push({
        due_date: date.toISOString().slice(0, 10),
        amount: difference.value > 0 ? difference.value.toFixed(2) : '',
        notes: null,
    });
}

/**
 * Plan her zaman net ücretin tamamını kapsar (gecikme = vadesi gelen − ödenen).
 * Şimdiye kadar ödenen tutar bugünkü tarihli "Peşinat" satırı olur; kalan borç
 * eşit taksitlere bölünür, kuruş farkı son taksite eklenir.
 */
function split(): void {
    const paid = Number(props.registration.paid);
    const remaining = Number(props.registration.balance);
    const count = Math.max(1, Math.min(36, splitCount.value));

    if (remaining <= 0) {
        return;
    }

    const base = Math.floor((remaining / count) * 100) / 100;
    const start = new Date(splitStart.value);
    const plan: InstallmentRow[] = [];

    if (paid > 0) {
        plan.push({
            due_date: new Date().toISOString().slice(0, 10),
            amount: paid.toFixed(2),
            notes: 'Peşinat (ödendi)',
        });
    }

    for (let i = 0; i < count; i++) {
        const due = new Date(start);
        due.setMonth(start.getMonth() + i);
        const amount = i === count - 1 ? remaining - base * (count - 1) : base;

        plan.push({
            due_date: due.toISOString().slice(0, 10),
            amount: amount.toFixed(2),
            notes: null,
        });
    }

    rows.value = plan;
}

function save(): void {
    saving.value = true;
    router.put(
        update.url(props.registration.id),
        {
            installments: rows.value.map(({ due_date, amount, notes }) => ({
                due_date,
                amount,
                notes,
            })),
        },
        {
            preserveScroll: true,
            onError: (e) => (errors.value = e),
            onSuccess: () => emit('saved'),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <div class="space-y-4">
        <div
            class="flex flex-wrap items-end gap-2 rounded-md border border-dashed p-3 text-sm"
        >
            <span class="w-full text-muted-foreground">
                Kalan borcu ({{
                    formatMoney(registration.balance, registration.currency)
                }}) eşit taksitlere böl. Plan net ücretin tamamını kapsar;
                ödenmiş tutar "peşinat" satırı olarak eklenir.
            </span>
            <div class="grid gap-1">
                <Label for="split-count" class="text-xs">Taksit sayısı</Label>
                <Input
                    id="split-count"
                    v-model.number="splitCount"
                    type="number"
                    min="1"
                    max="36"
                    class="w-24"
                />
            </div>
            <div class="grid gap-1">
                <Label for="split-start" class="text-xs">İlk vade</Label>
                <Input
                    id="split-start"
                    v-model="splitStart"
                    type="date"
                    class="w-40"
                />
            </div>
            <Button type="button" variant="secondary" size="sm" @click="split">
                Böl
            </Button>
        </div>

        <p v-if="rows.length === 0" class="text-sm text-muted-foreground">
            Taksit planı yok. Plan olmadan "gecikmiş borç" hesaplanmaz; yalnızca
            kalan borç gösterilir.
        </p>

        <div v-for="(row, i) in rows" :key="i" class="flex items-start gap-2">
            <span class="w-6 pt-2 text-right text-sm text-muted-foreground">
                {{ i + 1 }}.
            </span>
            <div class="grid gap-1">
                <Input v-model="row.due_date" type="date" class="w-40" />
                <InputError :message="errors[`installments.${i}.due_date`]" />
            </div>
            <div class="grid gap-1">
                <Input
                    v-model="row.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-32"
                    :placeholder="registration.currency"
                />
                <InputError :message="errors[`installments.${i}.amount`]" />
            </div>
            <Input
                :model-value="row.notes ?? ''"
                class="flex-1"
                @update:model-value="row.notes = String($event) || null"
                placeholder="Not (isteğe bağlı)"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="text-destructive"
                @click="rows.splice(i, 1)"
            >
                <Trash2 />
            </Button>
        </div>

        <InputError :message="errors.installments" />

        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <Button type="button" variant="outline" size="sm" @click="addRow">
                <Plus /> Taksit ekle
            </Button>
            <span
                :class="
                    difference < 0
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                "
            >
                Plan toplamı:
                {{ formatMoney(total, registration.currency) }} / net ücret
                {{ formatMoney(registration.net_price, registration.currency) }}
            </span>
        </div>

        <div class="flex gap-2">
            <Button type="button" :disabled="saving" @click="save">
                Planı kaydet
            </Button>
            <Button type="button" variant="ghost" @click="reset">
                Değişiklikleri geri al
            </Button>
        </div>
    </div>
</template>
