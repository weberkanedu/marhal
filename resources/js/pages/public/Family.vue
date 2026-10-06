<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Aile ekranı — tasarımdaki telefon ekranının gerçek hâli: "Ayşe Yılmaz'ın yolculuğu", şu an nerede,
 * gün gün program, rehber ve acentenin acil telefonu. Giriş gerektirmez; kimlik / sağlık / ödeme yok.
 */
type Event = {
    time: string | null;
    title: string;
    place: string | null;
    past: boolean;
};

const props = defineProps<{
    family: {
        name: string;
        tour: { name: string; start: string; end: string };
        phase: 'before' | 'during' | 'after';
        days_left: number | null;
        now: {
            city: string;
            hotel: string;
            room: string | null;
            with: string[];
        } | null;
        next: { title: string; detail: string | null; start: string } | null;
        group: string | null;
        guide: string | null;
        agency: { name: string; phone: string | null };
        days: { date: string; label: string; events: Event[] }[];
        today: string;
    };
}>();

// "Ayşe Yılmaz" → "Ayşe Yılmaz'ın" (Türkçe ilgi eki).
function genitive(name: string): string {
    const lower = name.toLocaleLowerCase('tr');
    const vowels = 'aeıioöuü';
    const last = [...lower].reverse().find((c) => vowels.includes(c)) ?? 'e';
    const suffix =
        {
            a: 'ın',
            ı: 'ın',
            e: 'in',
            i: 'in',
            o: 'un',
            u: 'un',
            ö: 'ün',
            ü: 'ün',
        }[last] ?? 'in';

    return `${name}'${vowels.includes(lower.slice(-1)) ? 'n' : ''}${suffix}`;
}

const selected = ref(
    props.family.days.find((d) => d.date === props.family.today)?.date ??
        props.family.days[0]?.date ??
        '',
);
const day = computed(() =>
    props.family.days.find((d) => d.date === selected.value),
);

const shortDate = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
</script>

<template>
    <Head :title="`${genitive(family.name)} yolculuğu`" />

    <div class="mx fam-page">
        <div class="screen">
            <h5>{{ genitive(family.name) }} yolculuğu</h5>
            <small class="lbl"
                >{{ family.tour.name }} · {{ shortDate(family.tour.start) }} –
                {{ shortDate(family.tour.end) }}</small
            >

            <div class="box">
                <small>Şu an</small>
                <template v-if="family.phase === 'before'">
                    <b>Yolculuğa {{ family.days_left }} gün var</b>
                    <small v-if="family.next"
                        >İlk adım:
                        {{ family.next.detail ?? family.next.title }} ·
                        {{ shortDate(family.next.start) }}</small
                    >
                </template>
                <template v-else-if="family.phase === 'after'">
                    <b>Yolculuk tamamlandı</b>
                    <small>Hayırlı ve kabul olsun.</small>
                </template>
                <template v-else-if="family.now">
                    <b
                        >{{ family.now.city }} · {{ family.now.hotel
                        }}<template v-if="family.now.room"
                            >, oda {{ family.now.room }}</template
                        ></b
                    >
                    <small>{{
                        [
                            family.now.with.length
                                ? `${family.now.with.join(', ')} ile birlikte`
                                : null,
                            family.group,
                        ]
                            .filter(Boolean)
                            .join(' · ')
                    }}</small>
                </template>
                <template v-else>
                    <b>Yolda</b>
                    <small>{{ family.group }}</small>
                </template>
            </div>

            <div class="dayp" role="tablist" aria-label="Günler">
                <span
                    v-for="d in family.days"
                    :key="d.date"
                    :class="{ on: d.date === selected }"
                    role="tab"
                    :aria-selected="d.date === selected"
                    tabindex="0"
                    @click="selected = d.date"
                    @keydown.enter="selected = d.date"
                    >{{ d.label }}</span
                >
            </div>
            <div class="sched">
                <small class="lbl">{{ day ? shortDate(day.date) : '' }}</small>
                <div
                    v-for="(e, i) in day?.events ?? []"
                    :key="i"
                    class="ev"
                    :class="{ past: e.past }"
                >
                    <b>{{ e.time ?? '—' }}</b
                    ><span
                        >{{ e.title
                        }}<template v-if="e.place"> · {{ e.place }}</template
                        ><template v-if="e.past"> ✓</template></span
                    >
                </div>
                <small v-if="!day?.events.length" class="lbl"
                    >Bu gün için program girilmedi.</small
                >
            </div>

            <div class="box">
                <small>Rehber</small>
                <b>{{ family.guide ?? family.agency.name }}</b>
                <small v-if="family.agency.phone"
                    >Acil durumda acente:
                    <a :href="`tel:${family.agency.phone}`">{{
                        family.agency.phone
                    }}</a></small
                >
            </div>
            <small class="lbl" style="text-align: center"
                >{{ family.agency.name }} · Marhal</small
            >
        </div>
    </div>
</template>
