<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { FormComponentProps } from '@inertiajs/core';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass, textareaClass } from '@/lib/formClasses';
import type { TourFormOptions, TourSummary } from '@/types/tour';

defineProps<{
    form: Pick<FormComponentProps, 'action' | 'method'>;
    options: TourFormOptions;
    tour?: TourSummary;
    defaultCurrency?: string;
    submitLabel: string;
}>();
</script>

<template>
    <Form
        v-bind="form"
        class="space-y-6"
        v-slot="{ errors, processing }"
        :options="{ preserveScroll: true }"
    >
        <Card>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="name">Tur adı *</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="tour?.name"
                        placeholder="Örn. Ekim 2026 Umre Turu"
                        required
                        autofocus
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="type">Tür</Label>
                    <select id="type" name="type" :class="selectClass">
                        <option
                            v-for="option in options.types"
                            :key="option.value"
                            :value="option.value"
                            :selected="(tour?.type ?? 'umre') === option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                </div>
                <div class="grid gap-2">
                    <Label for="status">Durum</Label>
                    <select id="status" name="status" :class="selectClass">
                        <option
                            v-for="option in options.statuses"
                            :key="option.value"
                            :value="option.value"
                            :selected="
                                (tour?.status ?? 'satista') === option.value
                            "
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="start_date">Başlangıç tarihi *</Label>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        :default-value="tour?.start_date"
                        required
                    />
                    <InputError :message="errors.start_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="end_date">Bitiş tarihi *</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        :default-value="tour?.end_date"
                        required
                    />
                    <InputError :message="errors.end_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="capacity">Kapasite (kişi)</Label>
                    <Input
                        id="capacity"
                        name="capacity"
                        type="number"
                        min="1"
                        :default-value="tour?.capacity ?? undefined"
                        placeholder="Sınırsız"
                    />
                    <InputError :message="errors.capacity" />
                </div>
                <div class="grid grid-cols-[1fr_7rem] gap-2">
                    <div class="grid gap-2">
                        <Label for="default_price">Kişi başı fiyat</Label>
                        <Input
                            id="default_price"
                            name="default_price"
                            type="number"
                            step="0.01"
                            min="0"
                            :default-value="tour?.default_price ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="currency">Para birimi</Label>
                        <select
                            id="currency"
                            name="currency"
                            :class="selectClass"
                        >
                            <option
                                v-for="currency in options.currencies"
                                :key="currency"
                                :value="currency"
                                :selected="
                                    (tour?.currency ?? defaultCurrency) ===
                                    currency
                                "
                            >
                                {{ currency }}
                            </option>
                        </select>
                    </div>
                    <InputError
                        class="col-span-2"
                        :message="errors.default_price ?? errors.currency"
                    />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="whatsapp_link">WhatsApp grup bağlantısı</Label>
                    <Input
                        id="whatsapp_link"
                        name="whatsapp_link"
                        type="url"
                        inputmode="url"
                        :default-value="tour?.whatsapp_link ?? undefined"
                        placeholder="https://chat.whatsapp.com/..."
                    />
                    <p class="text-xs text-muted-foreground">
                        WhatsApp'ta grup → Davet bağlantısı. Tur sayfasındaki
                        "WhatsApp grubu" düğmesi bunu kopyalar.
                    </p>
                    <InputError :message="errors.whatsapp_link" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="notes">Notlar</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        :class="textareaClass"
                        >{{ tour?.notes ?? '' }}</textarea>
                    <InputError :message="errors.notes" />
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                {{ submitLabel }}
            </Button>
            <slot name="actions" />
        </div>
    </Form>
</template>
