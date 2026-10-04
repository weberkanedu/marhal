<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';

export type TenantOptions = {
    plans: {
        id: string;
        name: string;
        user_limit: number | null;
        active_tour_limit: number | null;
    }[];
    statuses: { value: string; label: string }[];
    currencies: string[];
};

export type TenantValues = {
    name?: string;
    plan_id?: string;
    status?: string;
    trial_ends_at?: string | null;
    subscription_ends_at?: string | null;
    default_currency?: string;
    phone?: string | null;
    email?: string | null;
    tursab_no?: string | null;
};

defineProps<{
    options: TenantOptions;
    values: TenantValues;
    errors: Record<string, string>;
}>();

const limitText = (n: number | null) => (n === null ? 'sınırsız' : String(n));
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid gap-2 sm:col-span-2">
            <Label for="t-name">Acente adı *</Label>
            <Input
                id="t-name"
                name="name"
                :default-value="values.name"
                required
            />
            <InputError :message="errors.name" />
        </div>
        <div class="grid gap-2">
            <Label for="t-plan">Paket *</Label>
            <select id="t-plan" name="plan_id" :class="selectClass" required>
                <option
                    v-for="plan in options.plans"
                    :key="plan.id"
                    :value="plan.id"
                    :selected="values.plan_id === plan.id"
                >
                    {{ plan.name }} —
                    {{ limitText(plan.user_limit) }} kullanıcı,
                    {{ limitText(plan.active_tour_limit) }} aktif tur
                </option>
            </select>
            <InputError :message="errors.plan_id" />
        </div>
        <div class="grid gap-2">
            <Label for="t-status">Durum *</Label>
            <select id="t-status" name="status" :class="selectClass">
                <option
                    v-for="status in options.statuses"
                    :key="status.value"
                    :value="status.value"
                    :selected="(values.status ?? 'trial') === status.value"
                >
                    {{ status.label }}
                </option>
            </select>
            <InputError :message="errors.status" />
        </div>
        <div class="grid gap-2">
            <Label for="t-trial">Deneme bitişi</Label>
            <Input
                id="t-trial"
                name="trial_ends_at"
                type="date"
                :default-value="values.trial_ends_at ?? undefined"
            />
            <InputError :message="errors.trial_ends_at" />
        </div>
        <div class="grid gap-2">
            <Label for="t-sub">Abonelik bitişi</Label>
            <Input
                id="t-sub"
                name="subscription_ends_at"
                type="date"
                :default-value="values.subscription_ends_at ?? undefined"
            />
            <InputError :message="errors.subscription_ends_at" />
        </div>
        <div class="grid gap-2">
            <Label for="t-currency">Varsayılan para birimi</Label>
            <select
                id="t-currency"
                name="default_currency"
                :class="selectClass"
            >
                <option
                    v-for="currency in options.currencies"
                    :key="currency"
                    :value="currency"
                    :selected="(values.default_currency ?? 'USD') === currency"
                >
                    {{ currency }}
                </option>
            </select>
        </div>
        <div class="grid gap-2">
            <Label for="t-tursab">TÜRSAB no</Label>
            <Input
                id="t-tursab"
                name="tursab_no"
                :default-value="values.tursab_no ?? undefined"
            />
        </div>
        <div class="grid gap-2">
            <Label for="t-phone">Telefon</Label>
            <Input
                id="t-phone"
                name="phone"
                :default-value="values.phone ?? undefined"
            />
        </div>
        <div class="grid gap-2">
            <Label for="t-email">E-posta</Label>
            <Input
                id="t-email"
                name="email"
                type="email"
                :default-value="values.email ?? undefined"
            />
            <InputError :message="errors.email" />
        </div>
    </div>
</template>
