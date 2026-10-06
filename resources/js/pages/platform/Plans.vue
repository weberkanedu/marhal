<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { toast } from 'vue-sonner';
import PlanController from '@/actions/App/Http/Controllers/Platform/PlanController';
import MockTop from '@/components/mock/MockTop.vue';
import { formatNumber } from '@/lib/format';
import { index } from '@/routes/platform/plans';

type PlanRow = {
    id: string;
    name: string;
    price_monthly: string;
    price_yearly: string;
    user_limit: number | null;
    passenger_limit: number | null;
    is_public: boolean;
    tenants_count: number;
    features: string[];
};

type Draft = {
    price_monthly: string;
    user_limit: string;
    passenger_limit: string;
    is_public: boolean;
    features: string[];
};

const props = defineProps<{
    plans: PlanRow[];
    featureOptions: { key: string; label: string }[];
    yearlyMonths: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Paketler', href: index() }],
    },
});

/**
 * Tasarımdaki paket düzenleyici (paketler tasarım sayfası → Platform paneli → Paketler): her değişiklik
 * hemen kaydedilir. Sınır kutusu boşsa sınırsız.
 */
const drafts = reactive<Record<string, Draft>>({});

function reset(): void {
    for (const plan of props.plans) {
        drafts[plan.id] = {
            price_monthly: String(Math.round(Number(plan.price_monthly))),
            user_limit: plan.user_limit === null ? '' : String(plan.user_limit),
            passenger_limit:
                plan.passenger_limit === null
                    ? ''
                    : String(plan.passenger_limit),
            is_public: plan.is_public,
            features: [...plan.features],
        };
    }
}

watch(() => props.plans, reset, { immediate: true });

const toLimit = (value: string) => (value.trim() === '' ? null : Number(value));

const yearly = (id: string) =>
    formatNumber((Number(drafts[id].price_monthly) || 0) * props.yearlyMonths);

function save(plan: PlanRow): void {
    const d = drafts[plan.id];

    router.put(
        PlanController.update.url(plan.id),
        {
            price_monthly: Number(d.price_monthly) || 0,
            user_limit: toLimit(d.user_limit),
            passenger_limit: toLimit(d.passenger_limit),
            is_public: d.is_public,
            features: d.features,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                toast.error(Object.values(errors)[0] ?? 'Kaydedilemedi.');
                reset();
            },
        },
    );
}

function toggleFeature(plan: PlanRow, key: string): void {
    const list = drafts[plan.id].features;
    drafts[plan.id].features = list.includes(key)
        ? list.filter((k) => k !== key)
        : [...list, key];
    save(plan);
}

function toggleLive(plan: PlanRow): void {
    drafts[plan.id].is_public = !drafts[plan.id].is_public;
    save(plan);
}
</script>

<template>
    <Head title="Paketler" />

    <div class="mx">
        <div class="main">
            <MockTop :crumbs="[{ label: 'Platform' }]" title="Paketler" />

            <p class="lbl">
                Fiyatlar KDV hariç. Değişiklik yeni müşterilere hemen uygulanır;
                mevcut aboneler yenileme tarihine kadar eski fiyattan devam
                eder.
            </p>

            <div class="pe">
                <div v-for="plan in plans" :key="plan.id" class="card">
                    <h3>
                        {{ plan.name }}
                        <span
                            class="sw"
                            :class="{ on: drafts[plan.id].is_public }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="drafts[plan.id].is_public"
                            :aria-label="`${plan.name} satışta`"
                            :title="
                                drafts[plan.id].is_public
                                    ? 'Satışta'
                                    : 'Satışta değil · mevcut acenteler etkilenmez'
                            "
                            @click="toggleLive(plan)"
                            @keydown.enter.prevent="toggleLive(plan)"
                            @keydown.space.prevent="toggleLive(plan)"
                        />
                    </h3>
                    <div class="row2">
                        <label class="fi"
                            >Aylık (₺)<input
                                v-model="drafts[plan.id].price_monthly"
                                type="number"
                                min="0"
                                step="10"
                                @change="save(plan)"
                        /></label>
                        <label class="fi"
                            >Yıllık (₺)<input
                                :value="yearly(plan.id)"
                                disabled
                                :title="`Aylık × ${yearlyMonths}`"
                        /></label>
                    </div>
                    <div class="row2">
                        <label class="fi"
                            >Personel<input
                                v-model="drafts[plan.id].user_limit"
                                type="number"
                                min="1"
                                placeholder="Sınırsız"
                                @change="save(plan)"
                        /></label>
                        <label class="fi"
                            >Yolcu / yıl<input
                                v-model="drafts[plan.id].passenger_limit"
                                type="number"
                                min="0"
                                step="50"
                                placeholder="Sınırsız"
                                @change="save(plan)"
                        /></label>
                    </div>
                    <div>
                        <div
                            v-for="feature in featureOptions"
                            :key="feature.key"
                            class="feat"
                        >
                            <span>{{ feature.label }}</span>
                            <span
                                class="sw"
                                :class="{
                                    on: drafts[plan.id].features.includes(
                                        feature.key,
                                    ),
                                }"
                                role="switch"
                                tabindex="0"
                                :aria-checked="
                                    drafts[plan.id].features.includes(
                                        feature.key,
                                    )
                                "
                                :aria-label="`${plan.name}: ${feature.label}`"
                                @click="toggleFeature(plan, feature.key)"
                                @keydown.enter.prevent="
                                    toggleFeature(plan, feature.key)
                                "
                                @keydown.space.prevent="
                                    toggleFeature(plan, feature.key)
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
