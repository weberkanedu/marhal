<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/lib/format';
import { index as collectionsIndex } from '@/routes/collections';
import { show as showTour } from '@/routes/tours';
import type { DashboardPayments, DashboardTour } from '@/types/dashboard';

/**
 * "Dikkat edilmesi gerekenler": her maddede aciliyet etiketi ve doğrudan işe götüren düğme.
 * Tur hazırlık uyarıları yalnızca başlamasına 45 günden az kalan turlar için gösterilir (gürültü olmasın).
 */
const props = defineProps<{
    payments: DashboardPayments | null;
    tours: DashboardTour[];
}>();

type Item = {
    key: string;
    tone: 'danger' | 'warning';
    chip: string;
    text: string;
    action: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

const SOON_DAYS = 45;
const URGENT_DAYS = 14;

const items = computed<Item[]>(() => {
    const list: Item[] = [];

    if (props.payments && props.payments.overdue_count > 0) {
        const amounts = Object.entries(props.payments.overdue)
            .map(([currency, amount]) => formatMoney(amount, currency))
            .join(' + ');
        list.push({
            key: 'overdue',
            tone: 'danger',
            chip: 'Gecikmiş',
            text: `${props.payments.overdue_count} yolcunun vadesi geçmiş taksit borcu var (${amounts})`,
            action: 'Borçluları gör',
            href: collectionsIndex({ query: { tab: 'borclu' } }),
        });
    }

    if (props.payments && props.payments.due_soon_count > 0) {
        list.push({
            key: 'due-soon',
            tone: 'warning',
            chip: 'Bu hafta',
            text: `${props.payments.due_soon_count} yolcunun taksiti önümüzdeki 7 gün içinde`,
            action: 'Listeyi aç',
            href: collectionsIndex({ query: { tab: 'borclu' } }),
        });
    }

    for (const tour of props.tours) {
        const urgent = tour.days_left <= URGENT_DAYS;
        const chip = urgent ? 'Acil' : 'Yaklaşıyor';
        const tone = urgent ? 'danger' : 'warning';

        if (tour.passport_issues > 0) {
            list.push({
                key: `passport-${tour.id}`,
                tone: tour.days_left <= 30 ? 'danger' : 'warning',
                chip: tour.days_left <= 30 ? 'Acil' : 'Takip',
                text: `${tour.name}: ${tour.passport_issues} yolcunun pasaportu eksik veya 6 aydan kısa geçerli`,
                action: 'Yolculara git',
                href: showTour(tour.id),
            });
        }

        if (tour.ungrouped > 0) {
            list.push({
                key: `ungrouped-${tour.id}`,
                tone: 'warning',
                chip: 'Eksik',
                text: `${tour.name}: ${tour.ungrouped} yolcu henüz bir gruba atanmadı`,
                action: 'Gruba ata',
                href: showTour(tour.id, { query: { grup: 'yok' } }),
            });
        }

        if (tour.days_left > SOON_DAYS) {
            continue;
        }

        if (tour.pending > 0) {
            list.push({
                key: `pending-${tour.id}`,
                tone,
                chip,
                text: `${tour.name}: ${tour.pending} ön kayıt henüz kesinleşmedi`,
                action: 'Yolculara git',
                href: showTour(tour.id),
            });
        }

        const actions: Record<string, [string, string]> = {
            rooms: ['yolcunun odası yok', 'Oda planına git'],
            seats: ['yolcunun otobüs koltuğu yok', 'Otobüslere git'],
            flights: ['yolcunun uçuş kaydı yok', 'Uçuşlara git'],
        };

        for (const check of tour.checks) {
            const missing = check.total - check.done;

            if (missing > 0 && actions[check.key]) {
                list.push({
                    key: `${check.key}-${tour.id}`,
                    tone,
                    chip,
                    text: `${tour.name}: ${missing} ${actions[check.key][0]}`,
                    action: actions[check.key][1],
                    href: showTour(tour.id, { query: { tab: check.tab } }),
                });
            }
        }
    }

    // Kırmızılar önce
    return list.sort((a, b) =>
        a.tone === b.tone ? 0 : a.tone === 'danger' ? -1 : 1,
    );
});
</script>

<template>
    <div class="card a-att">
        <h4>
            Dikkat edilmesi gerekenler <em>{{ items.length }} madde</em>
        </h4>
        <div class="alist">
            <div v-for="item in items" :key="item.key" class="aitem">
                <span class="chip" :class="item.tone">{{ item.chip }}</span>
                <p>{{ item.text }}</p>
                <Link :href="item.href">{{ item.action }} →</Link>
            </div>
            <div v-if="items.length === 0" class="aitem">
                <span class="chip ok">Tamam</span>
                <p>Şu an bekleyen bir sorun yok.</p>
                <span />
            </div>
        </div>
    </div>
</template>
