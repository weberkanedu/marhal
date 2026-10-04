<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import { formatMoney } from '@/lib/format';
import { selectClass, textareaClass } from '@/lib/formClasses';
import { store } from '@/routes/registrations/payments';
import type { PaymentOptions, RegistrationDetail } from '@/types/payment';

const props = defineProps<{
    registration: RegistrationDetail;
    options: PaymentOptions;
    type: 'tahsilat' | 'iade';
}>();

const open = defineModel<boolean>('open', { required: true });

const currency = ref(props.registration.currency);
const amount = ref('');
const rate = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        currency.value = props.registration.currency;
        amount.value =
            props.type === 'tahsilat' && Number(props.registration.balance) > 0
                ? props.registration.balance
                : '';
        rate.value = '';
    }
});

const needsRate = computed(
    () => currency.value !== props.registration.currency,
);

// Kayıt para birimindeki karşılık (bilgi amaçlı; asıl hesap sunucuda).
const converted = computed(() => {
    const value =
        Number(amount.value) * (needsRate.value ? Number(rate.value) : 1);

    return Number.isFinite(value) && value > 0 ? value.toFixed(2) : null;
});

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <Form
                v-bind="store.form(registration.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <input type="hidden" name="type" :value="type" />

                <DialogHeader>
                    <DialogTitle>
                        {{ type === 'iade' ? 'İade yap' : 'Ödeme al' }}
                    </DialogTitle>
                    <DialogDescription>
                        Kalan borç:
                        {{
                            formatMoney(
                                registration.balance,
                                registration.currency,
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-[1fr_7rem] gap-2">
                    <div class="grid gap-2">
                        <Label for="amount">Tutar *</Label>
                        <Input
                            id="amount"
                            v-model="amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            autofocus
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="currency">Para birimi</Label>
                        <select
                            id="currency"
                            v-model="currency"
                            name="currency"
                            :class="selectClass"
                        >
                            <option
                                v-for="item in options.currencies"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select>
                    </div>
                    <InputError class="col-span-2" :message="errors.amount" />
                </div>

                <div v-if="needsRate" class="grid gap-2">
                    <Label for="exchange_rate">
                        Kur: 1 {{ currency }} = ? {{ registration.currency }} *
                    </Label>
                    <Input
                        id="exchange_rate"
                        v-model="rate"
                        name="exchange_rate"
                        type="number"
                        step="0.000001"
                        min="0"
                        required
                    />
                    <p v-if="converted" class="text-xs text-muted-foreground">
                        Kayda işlenecek tutar:
                        {{ formatMoney(converted, registration.currency) }}
                    </p>
                    <InputError :message="errors.exchange_rate" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="method">Yöntem</Label>
                        <select id="method" name="method" :class="selectClass">
                            <option
                                v-for="option in options.methods"
                                :key="option.value"
                                :value="option.value"
                                :selected="option.value === 'havale'"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.method" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="paid_at">Tarih</Label>
                        <Input
                            id="paid_at"
                            name="paid_at"
                            type="date"
                            :default-value="today"
                            :max="today"
                            required
                        />
                        <InputError :message="errors.paid_at" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="reference">Makbuz / dekont no</Label>
                        <Input id="reference" name="reference" />
                        <InputError :message="errors.reference" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="pay-notes">Not</Label>
                        <textarea
                            id="pay-notes"
                            name="notes"
                            rows="2"
                            :class="textareaClass"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button
                        type="submit"
                        :variant="type === 'iade' ? 'destructive' : 'default'"
                        :disabled="processing"
                    >
                        {{
                            type === 'iade' ? 'İadeyi kaydet' : 'Ödemeyi kaydet'
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
