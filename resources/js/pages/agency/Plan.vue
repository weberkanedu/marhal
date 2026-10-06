<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SubscriptionController from '@/actions/App/Http/Controllers/SubscriptionController';
import { formatDate, formatNumber } from '@/lib/format';
import { plan as agencyPlan } from '@/routes/agency';

type PlanCard = {
    id: string;
    name: string;
    tagline: string | null;
    price_monthly: string;
    price_yearly: string;
    user_limit: number | null;
    passenger_limit: number | null;
    is_featured: boolean;
    is_public: boolean;
    features: string[];
};

type Usage = { used: number; limit: number | null };

const props = defineProps<{
    plans: PlanCard[];
    featureOptions: { key: string; label: string }[];
    cycles: { value: string; label: string }[];
    current: {
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
        usage: {
            passengers: Usage & { renews_at: string };
            staff: Usage;
        };
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
    bank: {
        name: string | null;
        iban: string | null;
        holder: string | null;
        reference: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Paketim', href: agencyPlan() }],
    },
});

const sub = computed(() => props.current);
const bill = ref<string>(sub.value.billing_cycle ?? 'yillik');
const tl = (value: string | number) =>
    `₺${formatNumber(Math.round(Number(value)))}`;
const pct = (u: Usage) =>
    u.limit ? Math.min(100, Math.round((u.used / u.limit) * 100)) : 0;
const label = (key: string) =>
    props.featureOptions.find((f) => f.key === key)?.label ?? key;

/** Tasarımdaki gibi: her kart yalnız bir önceki pakete göre eklenenleri sayar. */
const cards = computed(() =>
    props.plans.map((plan, i) => {
        const before = i > 0 ? props.plans[i - 1] : null;

        return {
            plan,
            before,
            own: plan.features.filter((k) => !before?.features.includes(k)),
            price:
                bill.value === 'yillik'
                    ? Number(plan.price_yearly) / 12
                    : Number(plan.price_monthly),
        };
    }),
);

function cta(plan: PlanCard): { text: string; disabled: boolean } {
    const r = sub.value.request;

    if (r && r.plan_id === plan.id && r.billing_cycle === bill.value) {
        return { text: 'Talep gönderildi', disabled: true };
    }

    const current = plan.id === sub.value.plan.id;

    if (
        current &&
        sub.value.state.value === 'aktif' &&
        (sub.value.billing_cycle === null ||
            sub.value.billing_cycle === bill.value)
    ) {
        return { text: 'Mevcut paketiniz', disabled: true };
    }

    return {
        text:
            current && sub.value.state.value !== 'deneme'
                ? 'Paketi yenile'
                : 'Bu paketi seç',
        disabled: false,
    };
}

function choose(plan: PlanCard): void {
    router.post(
        SubscriptionController.request.url(),
        { plan_id: plan.id, billing_cycle: bill.value },
        { preserveScroll: true },
    );
}

function cancelRequest(): void {
    if (confirm('Paket talebi geri alınsın mı?')) {
        router.delete(SubscriptionController.cancel.url(), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Paketim" />

    <div class="mx">
        <div class="main">
            <div class="kpis">
                <div class="card">
                    <span class="lbl">Paketiniz</span>
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
                        <template v-else-if="sub.state.value === 'salt_okunur'"
                            >Görüntüleme açık, değişiklik kapalı</template
                        >
                    </span>
                </div>
                <div class="card">
                    <span class="lbl">Yolcu kotası</span>
                    <div class="big">
                        {{ formatNumber(sub.usage.passengers.used) }}
                        <small
                            v-if="sub.usage.passengers.limit !== null"
                            class="lbl"
                            >/
                            {{ formatNumber(sub.usage.passengers.limit) }}
                            yıllık kota</small
                        >
                    </div>
                    <div class="bar">
                        <i
                            :style="{ width: `${pct(sub.usage.passengers)}%` }"
                        />
                    </div>
                    <span class="lbl"
                        >{{
                            formatDate(sub.usage.passengers.renews_at)
                        }}
                        tarihinde yenilenir</span
                    >
                </div>
                <div class="card">
                    <span class="lbl">Personel</span>
                    <div class="big">
                        {{ sub.usage.staff.used }}
                        <small v-if="sub.usage.staff.limit !== null" class="lbl"
                            >/ {{ sub.usage.staff.limit }}</small
                        >
                    </div>
                    <div class="bar">
                        <i :style="{ width: `${pct(sub.usage.staff)}%` }" />
                    </div>
                    <span class="lbl">Rehber hesapları sınırsız</span>
                </div>
            </div>

            <div v-if="sub.request" class="aitem">
                <span class="chip acc">Talep</span>
                <p>
                    <b>{{ sub.request.plan }}</b> ·
                    {{ sub.request.billing_cycle_label }} ({{
                        tl(sub.request.amount)
                    }}) talebiniz {{ formatDate(sub.request.at) }} tarihinde
                    alındı. Ödemeniz ulaşınca paketiniz açılır.
                </p>
                <a role="button" tabindex="0" @click="cancelRequest"
                    >Talebi geri al</a
                >
            </div>

            <div class="seg" role="group" aria-label="Ödeme dönemi">
                <button
                    type="button"
                    :aria-pressed="bill === 'aylik'"
                    @click="bill = 'aylik'"
                >
                    Aylık
                </button>
                <button
                    type="button"
                    :aria-pressed="bill === 'yillik'"
                    @click="bill = 'yillik'"
                >
                    Yıllık<em>2 ay bizden</em>
                </button>
            </div>

            <div class="plans">
                <article
                    v-for="c in cards"
                    :key="c.plan.id"
                    class="plan"
                    :class="{ hot: c.plan.is_featured }"
                >
                    <span v-if="c.plan.is_featured" class="tag"
                        >EN ÇOK TERCİH EDİLEN</span
                    >
                    <h3>{{ c.plan.name }}</h3>
                    <p class="who">{{ c.plan.tagline }}</p>
                    <div class="price">
                        <b>{{ tl(c.price) }}</b
                        ><span
                            >/ ay<template v-if="bill === 'yillik'">
                                · yıllık
                                {{ tl(c.plan.price_yearly) }}</template
                            ></span
                        >
                    </div>
                    <div class="limits">
                        <div>
                            <b>{{
                                c.plan.passenger_limit === null
                                    ? 'Sınırsız'
                                    : formatNumber(c.plan.passenger_limit)
                            }}</b
                            >yolcu / yıl
                        </div>
                        <div>
                            <b>{{ c.plan.user_limit ?? 'Sınırsız' }}</b
                            >personel
                        </div>
                    </div>
                    <ul>
                        <li v-if="c.before" class="inh">
                            {{ c.before.name }} paketindeki her şey
                        </li>
                        <li v-for="k in c.own" :key="k">{{ label(k) }}</li>
                        <li>Sınırsız rehber hesabı</li>
                    </ul>
                    <button
                        class="btn cta"
                        type="button"
                        :disabled="cta(c.plan).disabled"
                        @click="choose(c.plan)"
                    >
                        {{ cta(c.plan).text }}
                    </button>
                </article>
            </div>
            <p class="lbl">
                Fiyatlar KDV hariçtir. Paket seçtiğinizde talebiniz bize ulaşır;
                ödemeniz gelince paketiniz ve yeni döneminiz açılır.
            </p>

            <div class="g2">
                <div class="card">
                    <h4>Havale / EFT ile ödeme</h4>
                    <template v-if="bank.iban">
                        <div class="fld">
                            <span>Banka</span><b>{{ bank.name ?? '—' }}</b>
                        </div>
                        <div class="fld">
                            <span>Alıcı</span><b>{{ bank.holder ?? '—' }}</b>
                        </div>
                        <div class="fld">
                            <span>IBAN</span><b>{{ bank.iban }}</b>
                        </div>
                        <div class="fld">
                            <span>Açıklama</span><b>{{ bank.reference }}</b>
                        </div>
                        <p class="lbl">
                            Açıklamaya acente kodunuzu yazın; ödemeniz bu kodla
                            eşleştirilir.
                        </p>
                    </template>
                    <p v-else class="lbl">
                        Havale bilgileri için bizimle iletişime geçin. Acente
                        kodunuz: <b>{{ bank.reference }}</b>
                    </p>
                </div>
                <div class="card">
                    <h4>
                        Ödeme geçmişi <em>{{ sub.payments.length }} ödeme</em>
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
                                    <td class="num">{{ tl(p.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
