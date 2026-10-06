<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ActivityList from '@/components/dashboard/ActivityList.vue';
import AttentionPanel from '@/components/dashboard/AttentionPanel.vue';
import CollectionChart from '@/components/dashboard/CollectionChart.vue';
import NextTourCard from '@/components/dashboard/NextTourCard.vue';
import ToursStatus from '@/components/dashboard/ToursStatus.vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import MockTop from '@/components/mock/MockTop.vue';
import { formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { create as createPerson } from '@/routes/persons';
import { create as createTour } from '@/routes/tours';
import type {
    ActivityItem,
    DashboardPayments,
    DashboardTour,
} from '@/types/dashboard';

/**
 * Ana Panel — tasarım sayfasındaki "Ana Panel" ekranının birebir hâli (gerçek veriyle): sayı kartları,
 * sıradaki tur, dikkat edilmesi gerekenler, son hareketler, turların durumu, aylık tahsilat.
 */
const props = defineProps<{
    stats: {
        persons: number;
        personsWeek: number;
        activeTours: number;
        activeTourLimit: number | null;
        plan: string | null;
        outstanding: Record<string, string>;
    };
    // Ödeme modülü kapalıysa null.
    payments: DashboardPayments | null;
    activity: ActivityItem[];
    trend: {
        months: { key: string; label: string }[];
        series: Record<string, string[]>;
    } | null;
    tours: DashboardTour[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ana Panel', href: dashboard() }],
    },
});

const page = usePage();

// "Hayırlı günler, Zeynep": günün saatine göre selam, kullanıcının ilk adı.
const greeting = computed(() => {
    const hour = new Date().getHours();
    const salute =
        hour < 5
            ? 'İyi geceler'
            : hour < 11
              ? 'Günaydın'
              : hour < 18
                ? 'Hayırlı günler'
                : 'İyi akşamlar';
    const name = (page.props.auth.user?.name ?? '').split(' ')[0];

    return name ? `${salute}, ${name}` : salute;
});

const today = new Date().toLocaleDateString('tr-TR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const passengers = computed(() =>
    (page.props.features ?? []).includes('passengers'),
);

// Sıradaki tur: henüz bitmemiş, en erken başlayan (liste başlangıç tarihine göre sıralı gelir).
const nextTour = computed(() => props.tours[0] ?? null);

const outstanding = computed(() => Object.entries(props.stats.outstanding));

// "Yeni kayıt" menüsü
const newOpen = ref(false);
const newMenu = ref<HTMLElement | null>(null);

function outside(e: MouseEvent): void {
    if (newOpen.value && !newMenu.value?.contains(e.target as Node)) {
        newOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));
</script>

<template>
    <Head title="Ana Panel" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: `Ana Panel · ${today}` }]"
                :title="greeting"
            >
                <div v-if="passengers" ref="newMenu" class="exp">
                    <button
                        class="btn"
                        type="button"
                        :aria-expanded="newOpen"
                        aria-haspopup="menu"
                        @click="newOpen = !newOpen"
                    >
                        <MockIcon name="plus" />Yeni kayıt
                    </button>
                    <div v-if="newOpen" class="menu" role="menu">
                        <Link class="mi" role="menuitem" :href="createPerson()">
                            <div>
                                <b>Yolcu</b
                                ><small>Kimlik, pasaport, iletişim</small>
                            </div>
                        </Link>
                        <Link class="mi" role="menuitem" :href="createTour()">
                            <div>
                                <b>Tur</b
                                ><small>Tarihler, fiyat, kontenjan</small>
                            </div>
                        </Link>
                        <Link
                            v-if="payments"
                            class="mi"
                            role="menuitem"
                            :href="collectionsIndex()"
                        >
                            <div>
                                <b>Ödeme</b><small>Tahsilat ekranından</small>
                            </div>
                        </Link>
                    </div>
                </div>
            </MockTop>

            <div class="dash">
                <div class="card a-s1">
                    <span class="lbl">Kayıtlı kişi</span>
                    <div class="big">{{ stats.persons }}</div>
                    <span class="lbl"
                        ><span class="chip ok">+{{ stats.personsWeek }}</span>
                        bu hafta</span
                    >
                </div>
                <div class="card a-s2">
                    <span class="lbl">Aktif tur</span>
                    <div class="big">
                        {{ stats.activeTours }}
                        <small v-if="stats.activeTourLimit !== null" class="lbl"
                            >/ {{ stats.activeTourLimit }} paket limiti</small
                        >
                    </div>
                    <span class="lbl">{{
                        stats.plan ? `${stats.plan} paket` : ''
                    }}</span>
                </div>
                <div class="card a-s3">
                    <span class="lbl">Kalan alacak</span>
                    <div class="big">
                        {{
                            outstanding.length
                                ? formatMoney(
                                      outstanding[0][1],
                                      outstanding[0][0],
                                  )
                                : '—'
                        }}
                    </div>
                    <span class="lbl">{{
                        outstanding.length > 1
                            ? outstanding
                                  .slice(1)
                                  .map(([c, a]) => formatMoney(a, c))
                                  .join(' · ')
                            : 'Bütün turların toplamı'
                    }}</span>
                </div>

                <NextTourCard v-if="nextTour" :tour="nextTour" />
                <div v-else class="card glow a-hero">
                    <h4>Sıradaki tur</h4>
                    <span class="lbl">Yaklaşan tur yok.</span>
                    <Link class="btn ghost sm" :href="createTour()"
                        >Yeni tur oluştur</Link
                    >
                </div>

                <AttentionPanel :payments="payments" :tours="tours" />
                <ActivityList :items="activity" />
                <ToursStatus :tours="tours" />
                <CollectionChart
                    v-if="trend"
                    :months="trend.months"
                    :series="trend.series"
                />
            </div>
        </div>
    </div>
</template>
