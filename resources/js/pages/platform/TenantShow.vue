<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import TenantSubscriptionController from '@/actions/App/Http/Controllers/Platform/TenantSubscriptionController';
import MockTop from '@/components/mock/MockTop.vue';
import PaymentReceivedDialog from '@/components/platform/PaymentReceivedDialog.vue';
import type {
    PaymentOptions,
    PaymentTarget,
} from '@/components/platform/PaymentReceivedDialog.vue';
import TenantFields from '@/components/platform/TenantFields.vue';
import type {
    TenantOptions,
    TenantValues,
} from '@/components/platform/TenantFields.vue';
import { formatDate, formatMoney } from '@/lib/format';
import { index as tenantsIndex } from '@/routes/platform/tenants';

type FeatureRow = {
    key: string;
    label: string;
    plan: boolean;
    override: boolean | null;
    effective: boolean;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    last_login_at: string | null;
};

const props = defineProps<{
    agency: TenantValues & {
        id: string;
        accessible: boolean;
        active_tours: number;
        passengers_used: number;
        passenger_limit: number | null;
    };
    features: FeatureRow[];
    users: UserRow[];
    options: TenantOptions;
    subscriptionDetail: {
        plan: { id: string; name: string };
        state: { value: string; label: string; tone: string };
        billing_cycle: string | null;
        billing_cycle_label: string | null;
        ends_at: string | null;
        days_left: number | null;
        grace_ends_at: string | null;
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
    paymentOptions: PaymentOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Acenteler', href: tenantsIndex() }],
    },
});

const sub = computed(() => props.subscriptionDetail);
const paying = ref<PaymentTarget | null>(null);

function openPayment(): void {
    paying.value = {
        id: props.agency.id,
        name: props.agency.name ?? '',
        planId: sub.value.request?.plan_id ?? sub.value.plan.id,
        cycle: sub.value.request?.billing_cycle ?? sub.value.billing_cycle,
    };
}

function extend(): void {
    router.post(
        TenantSubscriptionController.extend.url(props.agency.id),
        {},
        { preserveScroll: true },
    );
}

const roleLabels: Record<string, string> = {
    admin: 'Yönetici',
    operasyon: 'Operasyon',
    rehber: 'Rehber',
};

function setFeature(feature: FeatureRow, value: string): void {
    router.put(
        TenantController.updateFeature.url(props.agency.id),
        {
            feature: feature.key,
            enabled: value === 'default' ? null : value === 'on',
        },
        { preserveScroll: true },
    );
}

function resetPassword(user: UserRow): void {
    if (confirm(`${user.name} için yeni geçici şifre oluşturulsun mu?`)) {
        router.post(
            TenantController.resetUserPassword.url({
                tenant: props.agency.id,
                user: user.id,
            }),
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="agency.name ?? 'Acente'" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: 'Acenteler', href: tenantsIndex().url }]"
                :title="agency.name ?? 'Acente'"
            />

            <div class="kpis">
                <div class="card">
                    <span class="lbl">Paket</span>
                    <div class="big">{{ sub.plan.name }}</div>
                    <span class="lbl"
                        ><span class="chip" :class="sub.state.tone">{{
                            sub.state.label
                        }}</span>
                        {{ sub.billing_cycle_label ?? '' }}</span
                    >
                </div>
                <div class="card">
                    <span class="lbl">{{
                        sub.state.value === 'deneme'
                            ? 'Deneme bitişi'
                            : 'Dönem sonu'
                    }}</span>
                    <div class="big">
                        {{ sub.ends_at ? formatDate(sub.ends_at) : 'Süresiz' }}
                    </div>
                    <span class="lbl">
                        <template v-if="sub.days_left !== null"
                            >{{ sub.days_left }} gün kaldı</template
                        >
                        <template v-else-if="sub.grace_ends_at"
                            >{{ formatDate(sub.grace_ends_at) }} tarihinde salt
                            okunur olur</template
                        >
                    </span>
                </div>
                <div class="card">
                    <span class="lbl">Yolcu (bu abonelik yılı)</span>
                    <div class="big">
                        {{ agency.passengers_used }}
                        <small
                            v-if="agency.passenger_limit !== null"
                            class="lbl"
                            >/ {{ agency.passenger_limit }}</small
                        >
                    </div>
                    <span class="lbl">{{ agency.active_tours }} aktif tur</span>
                </div>
            </div>

            <div v-if="sub.request" class="aitem">
                <span class="chip acc">Talep</span>
                <p>
                    <b>{{ sub.request.plan }}</b> ·
                    {{ sub.request.billing_cycle_label }} ({{
                        formatMoney(sub.request.amount, 'TRY')
                    }}) — {{ formatDate(sub.request.at) }}. Ödeme gelince "Ödeme
                    geldi" deyin.
                </p>
                <a role="button" tabindex="0" @click="openPayment"
                    >Ödeme geldi →</a
                >
            </div>

            <div class="g2">
                <div class="card">
                    <h4>
                        Abonelik
                        <span class="btns">
                            <button
                                class="btn sm"
                                type="button"
                                @click="openPayment"
                            >
                                Ödeme geldi
                            </button>
                            <button
                                v-if="sub.state.value !== 'askida'"
                                class="btn ghost sm"
                                type="button"
                                @click="extend"
                            >
                                +7 gün
                            </button>
                        </span>
                    </h4>
                    <p v-if="!sub.payments.length" class="lbl">
                        Henüz ödeme yok.
                    </p>
                    <div v-else class="tbl">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Paket</th>
                                    <th>Dönem</th>
                                    <th>Yöntem</th>
                                    <th class="num">Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in sub.payments" :key="p.id">
                                    <td>{{ formatDate(p.paid_at) }}</td>
                                    <td>
                                        {{ p.plan }} ·
                                        {{ p.billing_cycle_label }}
                                    </td>
                                    <td>
                                        {{ formatDate(p.period_starts_at) }} –
                                        {{ formatDate(p.period_ends_at) }}
                                    </td>
                                    <td>{{ p.method_label }}</td>
                                    <td class="num">
                                        {{ formatMoney(p.amount, p.currency) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <h4>
                        Kullanıcılar
                        <em>Unutulan şifre için yeni geçici şifre</em>
                    </h4>
                    <div class="tbl">
                        <table>
                            <tbody>
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    :style="{
                                        opacity: user.is_active ? 1 : 0.5,
                                    }"
                                >
                                    <td>
                                        <b>{{ user.name }}</b
                                        ><br /><span class="lbl"
                                            >{{ user.email }} ·
                                            {{
                                                user.last_login_at
                                                    ? `son giriş ${formatDate(user.last_login_at)}`
                                                    : 'hiç giriş yapmadı'
                                            }}</span
                                        >
                                    </td>
                                    <td>
                                        <span class="chip">{{
                                            roleLabels[user.role] ?? user.role
                                        }}</span>
                                    </td>
                                    <td class="num">
                                        <button
                                            v-if="user.is_active"
                                            class="btn ghost sm"
                                            type="button"
                                            title="Yeni geçici şifre"
                                            @click="resetPassword(user)"
                                        >
                                            <KeyRound />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="g2">
                <div class="card">
                    <h4>
                        Acente bilgileri
                        <em>Durum ve tarihleri elle de değiştirebilirsiniz</em>
                    </h4>
                    <Form
                        v-bind="TenantController.update.form(agency.id)"
                        class="space-y-4"
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing }"
                    >
                        <TenantFields
                            :options="options"
                            :values="agency"
                            :errors="errors"
                        />
                        <button
                            class="btn"
                            type="submit"
                            :disabled="processing"
                        >
                            Kaydet
                        </button>
                    </Form>
                </div>

                <div class="card">
                    <h4>
                        Modüller
                        <em
                            >Varsayılanı paket belirler; acenteye özel
                            açılabilir</em
                        >
                    </h4>
                    <div
                        v-for="feature in features"
                        :key="feature.key"
                        class="feat"
                    >
                        <span
                            >{{ feature.label }}
                            <span class="lbl">{{
                                feature.plan ? '· pakette var' : '· pakette yok'
                            }}</span></span
                        >
                        <span class="row-acts">
                            <select
                                class="mini-in"
                                :aria-label="`${feature.label} ayarı`"
                                :value="
                                    feature.override === null
                                        ? 'default'
                                        : feature.override
                                          ? 'on'
                                          : 'off'
                                "
                                @change="
                                    setFeature(
                                        feature,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="default">Paket</option>
                                <option value="on">Açık</option>
                                <option value="off">Kapalı</option>
                            </select>
                            <span
                                class="chip"
                                :class="{ ok: feature.effective }"
                                >{{
                                    feature.effective ? 'Açık' : 'Kapalı'
                                }}</span
                            >
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <PaymentReceivedDialog
        :target="paying"
        :options="paymentOptions"
        @close="paying = null"
    />
</template>
