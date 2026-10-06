<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import SecurityController from '@/actions/App/Http/Controllers/Platform/SecurityController';
import TenantSubscriptionController from '@/actions/App/Http/Controllers/Platform/TenantSubscriptionController';
import InputError from '@/components/InputError.vue';
import MockTop from '@/components/mock/MockTop.vue';
import PaymentReceivedDialog from '@/components/platform/PaymentReceivedDialog.vue';
import type {
    PaymentOptions,
    PaymentTarget,
} from '@/components/platform/PaymentReceivedDialog.vue';
import TenantFields from '@/components/platform/TenantFields.vue';
import type { TenantOptions } from '@/components/platform/TenantFields.vue';
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
import { index as tenantsIndex, show } from '@/routes/platform/tenants';

type Usage = { used: number; limit: number | null };

type TenantRow = {
    id: string;
    name: string;
    city: string | null;
    plan: { id: string; name: string };
    state: { value: string; label: string; tone: string };
    renewal: string;
    usage: { passengers: Usage; staff: Usage };
    request: {
        plan_id: string;
        plan: string;
        billing_cycle: string | null;
        billing_cycle_label: string | null;
    } | null;
    billing_cycle: string | null;
    suspicious: boolean;
};

type AlertRow = {
    id: number;
    tenant: string | null;
    user: string;
    email: string;
    label: string;
    summary: string;
};

defineProps<{
    tenants: TenantRow[];
    alerts: AlertRow[];
    options: TenantOptions;
    paymentOptions: PaymentOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Acenteler', href: tenantsIndex() }],
    },
});

const pct = (u: Usage) =>
    u.limit ? Math.min(100, Math.round((u.used / u.limit) * 100)) : 0;
const limitText = (u: Usage) =>
    u.limit === null ? '∞' : u.limit.toLocaleString('tr-TR');

// "Ödeme geldi" talep, gecikme ya da salt okunur durumda gösterilir (tasarımdaki gibi).
const needsPayment = (t: TenantRow) =>
    t.request !== null || ['gecikmede', 'salt_okunur'].includes(t.state.value);

const paying = ref<PaymentTarget | null>(null);

function openPayment(t: TenantRow): void {
    paying.value = {
        id: t.id,
        name: t.name,
        planId: t.request?.plan_id ?? t.plan.id,
        cycle: t.request?.billing_cycle ?? t.billing_cycle,
    };
}

function verify(a: AlertRow): void {
    if (
        confirm(
            `${a.user} kullanıcısının bütün oturumları ve cihazları sıfırlansın mı? Şifresiyle yeniden girmesi gerekecek.`,
        )
    ) {
        router.post(
            SecurityController.resolve.url(a.id),
            {},
            { preserveScroll: true },
        );
    }
}

function extend(t: TenantRow): void {
    router.post(
        TenantSubscriptionController.extend.url(t.id),
        {},
        { preserveScroll: true },
    );
}

const createOpen = ref(false);
</script>

<template>
    <Head title="Acenteler" />

    <div class="mx">
        <div class="main">
            <MockTop :crumbs="[{ label: 'Platform' }]" title="Acenteler">
                <button class="btn" type="button" @click="createOpen = true">
                    <Building2 /> Yeni acente
                </button>
            </MockTop>

            <div v-for="a in alerts" :key="a.id" class="alert" role="status">
                <span aria-hidden="true">⚠</span>
                <div>
                    <b
                        >{{ a.tenant ?? 'Acente' }}:
                        {{ a.label.toLocaleLowerCase('tr') }}</b
                    >
                    "{{ a.email }}" ({{ a.user }}) {{ a.summary }} Acente
                    yöneticisi Personel sayfasında görüyor.
                </div>
                <button class="btn ghost sm" type="button" @click="verify(a)">
                    Kullanıcıyı doğrula
                </button>
            </div>

            <div class="card">
                <p v-if="tenants.length === 0" class="lbl">Henüz acente yok.</p>
                <div v-else class="tbl">
                    <table>
                        <thead>
                            <tr>
                                <th>Acente</th>
                                <th>Paket</th>
                                <th>Durum</th>
                                <th>Yenileme</th>
                                <th>Kullanım</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in tenants" :key="t.id">
                                <td>
                                    <Link :href="show(t.id)"
                                        ><b>{{ t.name }}</b></Link
                                    ><br /><span class="lbl">{{
                                        t.city ?? '—'
                                    }}</span>
                                    <span
                                        v-if="t.suspicious"
                                        class="chip warning"
                                        >Şüpheli giriş</span
                                    >
                                    <span v-if="t.request" class="chip acc"
                                        >Talep: {{ t.request.plan }} ·
                                        {{
                                            t.request.billing_cycle_label
                                        }}</span
                                    >
                                </td>
                                <td>{{ t.plan.name }}</td>
                                <td>
                                    <span class="chip" :class="t.state.tone">{{
                                        t.state.label
                                    }}</span>
                                </td>
                                <td class="lbl">{{ t.renewal }}</td>
                                <td>
                                    <div class="usage">
                                        Yolcu
                                        {{
                                            t.usage.passengers.used.toLocaleString(
                                                'tr-TR',
                                            )
                                        }}
                                        / {{ limitText(t.usage.passengers) }}
                                        <div class="bar">
                                            <i
                                                :class="{
                                                    hi:
                                                        pct(
                                                            t.usage.passengers,
                                                        ) > 85,
                                                }"
                                                :style="{
                                                    width: `${pct(t.usage.passengers)}%`,
                                                }"
                                            />
                                        </div>
                                        Personel {{ t.usage.staff.used }} /
                                        {{ limitText(t.usage.staff) }}
                                        <div class="bar">
                                            <i
                                                :class="{
                                                    hi: pct(t.usage.staff) > 85,
                                                }"
                                                :style="{
                                                    width: `${pct(t.usage.staff)}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="row-acts">
                                        <button
                                            v-if="needsPayment(t)"
                                            class="btn ghost sm"
                                            type="button"
                                            @click="openPayment(t)"
                                        >
                                            Ödeme geldi
                                        </button>
                                        <button
                                            v-if="t.state.value !== 'askida'"
                                            class="btn ghost sm"
                                            type="button"
                                            @click="extend(t)"
                                        >
                                            +7 gün
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <PaymentReceivedDialog
        :target="paying"
        :options="paymentOptions"
        @close="paying = null"
    />

    <Dialog v-model:open="createOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <Form
                v-bind="TenantController.store.form()"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="createOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>Yeni acente</DialogTitle>
                    <DialogDescription>
                        Acente ve ilk yönetici hesabı birlikte oluşturulur.
                        Yöneticinin geçici şifresi bir sonraki ekranda
                        gösterilir.
                    </DialogDescription>
                </DialogHeader>

                <TenantFields
                    :options="options"
                    :values="{
                        status: 'trial',
                        default_currency: 'USD',
                        trial_ends_at: new Date(Date.now() + 14 * 864e5)
                            .toISOString()
                            .slice(0, 10),
                    }"
                    :errors="errors"
                />

                <div class="grid gap-4 border-t pt-4 sm:grid-cols-2">
                    <p class="text-sm font-medium sm:col-span-2">
                        İlk yönetici hesabı
                    </p>
                    <div class="grid gap-2">
                        <Label for="admin_name">Ad Soyad *</Label>
                        <Input id="admin_name" name="admin_name" required />
                        <InputError :message="errors.admin_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="admin_email">E-posta *</Label>
                        <Input
                            id="admin_email"
                            name="admin_email"
                            type="email"
                            required
                        />
                        <InputError :message="errors.admin_email" />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="createOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">
                        Acenteyi oluştur
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
