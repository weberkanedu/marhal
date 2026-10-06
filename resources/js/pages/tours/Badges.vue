<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BadgeSettingController from '@/actions/App/Http/Controllers/BadgeSettingController';
import TourBadgeController from '@/actions/App/Http/Controllers/TourBadgeController';
import MockTop from '@/components/mock/MockTop.vue';
import { badges as badgeReport } from '@/routes/reports/tours';
import { index as toursIndex, show as showTour } from '@/routes/tours';
import type { ExportItem } from '@/types/export';

/**
 * Tur → Yaka kartları — tasarım sayfasındaki "Yaka kartları" ekranının birebir hâli (gerçek veriyle).
 * Ayarlar acente geneli saklanır; "PDF hazırla" seçilen kapsamı bu ayarla basar.
 */
type Size = 'dikey' | 'yatay' | 'plastik';
type Badge = {
    registration_id: string;
    group_id: string | null;
    first_name: string;
    last_name: string;
    group: string | null;
    color: string;
    guide_name: string | null;
    guide_phone: string | null;
    photo_url: string | null;
    initials: string;
    hotels: {
        city: string;
        hotel: string;
        room: string | null;
        address: string | null;
    }[];
    bus: { label: string; value: string } | null;
    serial: string;
    health: string | null;
};
type Settings = {
    size: Size;
    fields: string[];
    back_languages: string[];
    back_side: boolean;
    health_note: boolean;
};

const props = defineProps<{
    tour: { id: string; name: string; dates: string };
    agency: {
        name: string | null;
        phone: string | null;
        logo_url: string | null;
    };
    settings: Settings;
    badges: Badge[];
    groups: { id: string; name: string; color: string }[];
    palette: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: toursIndex() }],
    },
});

// Tasarımdaki boy adları ve açıklamaları; tasarımdaki "kart" bizde "plastik".
const TPL: Record<
    Size,
    { l: string; d: string; per: number; cols: number; cls: string }
> = {
    dikey: {
        l: 'Büyük dikey',
        d: "A6 · 10,5 × 14,8 cm · A4'e 4",
        per: 4,
        cols: 2,
        cls: 'dikey',
    },
    yatay: {
        l: 'Yatay',
        d: "9 × 6,5 cm · A4'e 8 · şimdiki boy",
        per: 8,
        cols: 2,
        cls: 'yatay',
    },
    plastik: {
        l: 'Plastik kart',
        d: '8,6 × 5,4 cm · kart yazıcı',
        per: 10,
        cols: 2,
        cls: 'kart',
    },
};

const form = ref<Settings>({
    ...props.settings,
    fields: [...props.settings.fields],
    back_languages: [...props.settings.back_languages],
});
watch(
    () => props.settings,
    (s) =>
        (form.value = {
            ...s,
            fields: [...s.fields],
            back_languages: [...s.back_languages],
        }),
);

// Kartta kısa adlar (tasarımdaki "Ajyad · 1201", "1 · koltuk 7"); PDF ile aynı kural.
const short = (hotel: string) => hotel.trim().split(' ')[0];
const busShort = (label: string) => label.match(/^(\d+)/)?.[1] ?? label;
const has = (f: string) => form.value.fields.includes(f);
const arabic = computed(() => form.value.back_languages.includes('ar'));
const t = computed(() => TPL[form.value.size]);

function save(): void {
    router.put(BadgeSettingController.update.url(), form.value, {
        preserveScroll: true,
        preserveState: true,
        only: ['settings'],
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Kaydedilemedi.'),
    });
}

function setSize(size: Size): void {
    form.value.size = size;
    save();
}

function toggleField(f: string): void {
    const list = form.value.fields;
    form.value.fields = list.includes(f)
        ? list.filter((x) => x !== f)
        : [...list, f];
    save();
}

function toggleArabic(): void {
    form.value.back_languages = arabic.value
        ? form.value.back_languages.filter((l) => l !== 'ar')
        : [...form.value.back_languages, 'ar'];
    save();
}

function toggleHealth(): void {
    form.value.health_note = !form.value.health_note;
    save();
}

// Grup renkleri: seçilince kaydedilir (kart bandı ve otobüs tabelası).
const colors = ref<Record<string, string>>(
    Object.fromEntries(props.groups.map((g) => [g.id, g.color])),
);
function setColor(groupId: string, color: string): void {
    colors.value[groupId] = color;
    router.put(
        TourBadgeController.color.url(groupId),
        { color },
        { preserveScroll: true, preserveState: true, only: ['groups'] },
    );
}
const colorOf = (b: Badge) =>
    (b.group_id && colors.value[b.group_id]) || b.color;

// Önizlenen yolcu ve basılacaklar
const previewId = ref(props.badges[0]?.registration_id ?? null);
const preview = computed(
    () =>
        props.badges.find((b) => b.registration_id === previewId.value) ??
        props.badges[0] ??
        null,
);
const previewPills = computed(() => {
    const first = props.badges.slice(0, 5);

    return preview.value && !first.includes(preview.value)
        ? [...first.slice(0, 4), preview.value]
        : first;
});
const scope = ref<string>('tur');
const list = computed(() =>
    scope.value === 'tur'
        ? props.badges
        : props.badges.filter((b) => b.group_id === scope.value),
);
const pages = computed(() =>
    Math.max(1, Math.ceil(list.value.length / t.value.per)),
);
const rowsN = computed(() => Math.ceil(t.value.per / t.value.cols));

const pdfUrl = computed(() =>
    badgeReport.url(props.tour.id, {
        query: scope.value === 'tur' ? {} : { group: scope.value },
    }),
);
const exports = computed<ExportItem[]>(() => [
    {
        title: 'Toplu yaka kartları',
        description: `${scope.value === 'tur' ? 'Bütün tur' : (props.groups.find((g) => g.id === scope.value)?.name ?? '')} · ${list.value.length} kart`,
        url: pdfUrl.value,
        pdfOnly: true,
    },
    ...(preview.value
        ? [
              {
                  title: 'Tek kart',
                  description: `${preview.value.first_name} ${preview.value.last_name}`,
                  url: badgeReport.url(props.tour.id, {
                      query: { registration: preview.value.registration_id },
                  }),
                  pdfOnly: true,
              },
          ]
        : []),
]);

const crumbs = computed(() => [
    { label: 'Turlar', href: toursIndex.url() },
    { label: props.tour.name, href: showTour.url(props.tour.id) },
    { label: 'Yaka kartları' },
]);

// Kartın satırları (tasarımdaki gibi; rehber plastik kartta yok, yatayda yalnız ad).
const rows = computed(() => {
    const b = preview.value;

    if (!b) {
        return [];
    }

    const out: [string, string][] = [];

    if (has('hotels')) {
        b.hotels.forEach((h) =>
            out.push([h.city, `${short(h.hotel)} · ${h.room ?? '—'}`]),
        );
    }

    if (has('bus')) {
        out.push([
            'Otobüs',
            b.bus ? `${busShort(b.bus.label)} · koltuk ${b.bus.value}` : '—',
        ]);
    }

    if (has('guide') && form.value.size !== 'plastik' && b.guide_name) {
        out.push([
            'Rehber',
            form.value.size === 'dikey'
                ? [b.guide_name, b.guide_phone].filter(Boolean).join(' · ')
                : b.guide_name,
        ]);
    }

    return out;
});
const qrSize = computed(() =>
    form.value.size === 'dikey' ? 28 : form.value.size === 'plastik' ? 14 : 20,
);

// Önizlemedeki QR yalnız görünüm içindir (gerçek QR PDF'te).
const qrCanvas = ref<HTMLCanvasElement | null>(null);
function drawQr(): void {
    const c = qrCanvas.value;
    const x = c?.getContext('2d');

    if (!c || !x) {
        return;
    }

    let seed =
        (preview.value?.serial ?? '1')
            .split('')
            .reduce((n, ch) => n + ch.charCodeAt(0), 0) *
            9301 +
        49297;
    const rnd = () => (seed = (seed * 9301 + 49297) % 233280) / 233280;
    x.fillStyle = '#fff';
    x.fillRect(0, 0, 21, 21);
    x.fillStyle = '#15211b';

    for (let i = 0; i < 21; i++) {
        for (let j = 0; j < 21; j++) {
            if (rnd() > 0.5) {
                x.fillRect(i, j, 1, 1);
            }
        }
    }

    [
        [0, 0],
        [14, 0],
        [0, 14],
    ].forEach(([a, b]) => {
        x.fillStyle = '#fff';
        x.fillRect(a, b, 7, 7);
        x.fillStyle = '#15211b';
        x.fillRect(a, b, 7, 7);
        x.fillStyle = '#fff';
        x.fillRect(a + 1, b + 1, 5, 5);
        x.fillStyle = '#15211b';
        x.fillRect(a + 2, b + 2, 3, 3);
    });
}
onMounted(drawQr);
watch([preview, () => form.value.fields, () => form.value.size], () =>
    nextTick(drawQr),
);
</script>

<template>
    <Head :title="`Yaka kartları — ${tour.name}`" />

    <div class="mx">
        <div class="main">
            <MockTop :crumbs="crumbs" title="Yaka kartları" :exports="exports">
                <a class="btn" :href="pdfUrl"
                    ><Download /> PDF hazırla · {{ list.length }} kart</a
                >
            </MockTop>

            <div class="badgeview">
                <div class="card opt">
                    <div class="fi">
                        Boy
                        <div>
                            <span
                                v-for="(v, k) in TPL"
                                :key="k"
                                class="pill"
                                :class="{ on: form.size === k }"
                                :title="v.d"
                                role="button"
                                tabindex="0"
                                @click="setSize(k)"
                                >{{ v.l }}</span
                            >
                        </div>
                        <small class="lbl">{{ t.d }}</small>
                    </div>
                    <div v-if="badges.length" class="fi">
                        Önizlenen yolcu
                        <div>
                            <span
                                v-for="b in previewPills"
                                :key="b.registration_id"
                                class="pill"
                                :class="{
                                    on:
                                        preview?.registration_id ===
                                        b.registration_id,
                                }"
                                role="button"
                                tabindex="0"
                                @click="previewId = b.registration_id"
                                >{{
                                    b.first_name.charAt(0) +
                                    b.first_name
                                        .slice(1)
                                        .toLocaleLowerCase('tr')
                                }}</span
                            >
                            <select
                                v-if="badges.length > 5"
                                class="mini-in"
                                aria-label="Başka yolcu"
                                :value="previewId ?? ''"
                                @change="
                                    previewId = (
                                        $event.target as HTMLSelectElement
                                    ).value
                                "
                            >
                                <option
                                    v-for="b in badges"
                                    :key="b.registration_id"
                                    :value="b.registration_id"
                                >
                                    {{ b.first_name }} {{ b.last_name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="fi">
                        Ön yüzde
                        <div
                            v-for="[k, l] in [
                                ['photo', 'Fotoğraf'],
                                ['hotels', 'Oteller ve oda'],
                                ['bus', 'Otobüs ve koltuk'],
                                ['guide', 'Rehber ve telefonu'],
                                ['qr', 'QR kod (acil bilgi sayfası)'],
                            ]"
                            :key="k"
                            class="ck"
                            :class="{ on: has(k) }"
                            role="checkbox"
                            :aria-checked="has(k)"
                            tabindex="0"
                            @click="toggleField(k)"
                        >
                            <span class="bx">{{ has(k) ? '✓' : '' }}</span
                            >{{ l }}
                        </div>
                    </div>
                    <div class="fi">
                        Arka yüzde
                        <div
                            class="ck"
                            :class="{ on: arabic }"
                            role="checkbox"
                            :aria-checked="arabic"
                            tabindex="0"
                            @click="toggleArabic"
                        >
                            <span class="bx">{{ arabic ? '✓' : '' }}</span
                            >Arapça "kaybolursa" satırı
                        </div>
                        <div
                            class="ck"
                            :class="{ on: form.health_note }"
                            role="checkbox"
                            :aria-checked="form.health_note"
                            tabindex="0"
                            @click="toggleHealth"
                        >
                            <span class="bx">{{
                                form.health_note ? '✓' : ''
                            }}</span
                            >Sağlık notu (rızası olanlar)
                        </div>
                    </div>
                    <div v-if="groups.length" class="fi">
                        Grup renkleri
                        <small class="lbl"
                            >Otobüs tabelasında da aynı renk</small
                        >
                        <div v-for="g in groups" :key="g.id" class="swatches">
                            <span style="width: 52px; font-size: 12px">{{
                                g.name
                            }}</span>
                            <i
                                v-for="c in palette"
                                :key="c"
                                :class="{ on: colors[g.id] === c }"
                                :style="{ background: c }"
                                :title="c"
                                role="button"
                                @click="setColor(g.id, c)"
                            />
                        </div>
                    </div>
                    <div class="fi">
                        Basılacaklar
                        <div>
                            <span
                                class="pill"
                                :class="{ on: scope === 'tur' }"
                                role="button"
                                tabindex="0"
                                @click="scope = 'tur'"
                                >Bütün tur</span
                            >
                            <span
                                v-for="g in groups"
                                :key="g.id"
                                class="pill"
                                :class="{ on: scope === g.id }"
                                role="button"
                                tabindex="0"
                                @click="scope = g.id"
                                >{{ g.name }}</span
                            >
                        </div>
                        <small class="lbl"
                            >{{ list.length }} kart · {{ pages }} sayfa A4 ·
                            kesme çizgili</small
                        >
                    </div>
                </div>

                <div class="card">
                    <div v-if="preview" class="stage">
                        <figure>
                            <div
                                class="bdg"
                                :class="t.cls"
                                :style="{ '--g': colorOf(preview) }"
                            >
                                <span class="hole" />
                                <div class="band">
                                    <span class="lg">
                                        <img
                                            v-if="agency.logo_url"
                                            :src="agency.logo_url"
                                            alt=""
                                            style="
                                                max-width: 100%;
                                                max-height: 100%;
                                            "
                                        />
                                        <template v-else>{{
                                            (agency.name ?? 'M').charAt(0)
                                        }}</template>
                                    </span>
                                    <div>
                                        <b>{{ agency.name }}</b
                                        ><small>{{ tour.name }}</small>
                                    </div>
                                </div>
                                <div class="body">
                                    <template v-if="has('photo')">
                                        <span class="ph">
                                            <img
                                                v-if="preview.photo_url"
                                                :src="preview.photo_url"
                                                alt=""
                                                style="
                                                    width: 100%;
                                                    height: 100%;
                                                    object-fit: cover;
                                                    border-radius: inherit;
                                                "
                                            />
                                            <template v-else>{{
                                                preview.initials
                                            }}</template>
                                        </span>
                                    </template>
                                    <span v-else-if="form.size !== 'dikey'" />
                                    <div>
                                        <div class="fn">
                                            {{ preview.first_name }}
                                        </div>
                                        <div class="ln">
                                            {{ preview.last_name }}
                                        </div>
                                        <div v-if="preview.group" class="grp">
                                            <i />{{ preview.group }}
                                        </div>
                                    </div>
                                    <dl v-if="rows.length">
                                        <template
                                            v-for="[a, b] in rows"
                                            :key="a"
                                        >
                                            <dt>{{ a }}</dt>
                                            <dd>{{ b }}</dd>
                                        </template>
                                    </dl>
                                </div>
                                <div class="foot">
                                    <span>Seri no {{ preview.serial }}</span>
                                    <canvas
                                        v-if="has('qr')"
                                        ref="qrCanvas"
                                        class="qr"
                                        width="21"
                                        height="21"
                                        :style="{
                                            width: `${qrSize}px`,
                                            height: `${qrSize}px`,
                                        }"
                                        aria-label="QR kod"
                                    />
                                </div>
                            </div>
                            <figcaption>Ön yüz</figcaption>
                        </figure>
                        <figure>
                            <div
                                class="bdg"
                                :class="t.cls"
                                :style="{ '--g': colorOf(preview) }"
                            >
                                <span class="hole" />
                                <div class="back">
                                    <h6>KAYBOLURSANIZ · IF LOST</h6>
                                    <div v-if="arabic" class="ar">
                                        إذا وجدت هذا المعتمر تائها، يرجى الاتصال
                                        بالرقم التالي
                                    </div>
                                    <small
                                        style="font-size: 9.5px; color: #4b5852"
                                        >Bulursanız lütfen arayın · If found,
                                        please call</small
                                    >
                                    <div class="tel">
                                        {{ agency.phone ?? '—' }}
                                    </div>
                                    <dl v-if="form.size === 'dikey'">
                                        <template v-if="preview.guide_name">
                                            <dt>Rehber</dt>
                                            <dd>
                                                {{
                                                    [
                                                        preview.guide_name,
                                                        preview.guide_phone,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                }}
                                            </dd>
                                        </template>
                                        <template
                                            v-for="h in preview.hotels"
                                            :key="h.city"
                                        >
                                            <dt>{{ h.city }}</dt>
                                            <dd>
                                                {{
                                                    [h.hotel, h.address]
                                                        .filter(Boolean)
                                                        .join(', ')
                                                }}
                                            </dd>
                                        </template>
                                    </dl>
                                    <small
                                        v-else
                                        style="
                                            font-size: 9px;
                                            line-height: 1.35;
                                        "
                                    >
                                        <template v-if="preview.guide_name"
                                            >Rehber
                                            {{
                                                [
                                                    preview.guide_name,
                                                    preview.guide_phone,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')
                                            }}<br
                                        /></template>
                                        {{
                                            preview.hotels
                                                .map(
                                                    (h) =>
                                                        `${h.city}: ${short(h.hotel)}`,
                                                )
                                                .join(' · ')
                                        }}
                                    </small>
                                    <div
                                        v-if="
                                            form.health_note && preview.health
                                        "
                                        class="med"
                                    >
                                        Sağlık: {{ preview.health }}
                                    </div>
                                </div>
                                <div class="foot">
                                    <span>{{ agency.name }} · 7/24</span
                                    ><span>{{ preview.group }}</span>
                                </div>
                            </div>
                            <figcaption>Arka yüz</figcaption>
                        </figure>
                        <figure style="width: min(300px, 100%)">
                            <div
                                class="sheet"
                                :style="{
                                    gridTemplateColumns: `repeat(${t.cols},1fr)`,
                                    gridTemplateRows: `repeat(${rowsN},1fr)`,
                                }"
                            >
                                <div
                                    v-for="b in list.slice(0, t.per)"
                                    :key="b.registration_id"
                                    :style="{ '--g': colorOf(b) }"
                                >
                                    <i /><span>{{ b.first_name }}</span>
                                </div>
                            </div>
                            <figcaption>
                                A4 baskı · 1. sayfa / {{ pages }}
                            </figcaption>
                        </figure>
                    </div>
                    <div v-else class="empty-pool">
                        Bu turda yaka kartı basılacak yolcu yok.
                    </div>
                    <span class="lbl"
                        >Ad iki metreden okunacak büyüklükte. Şerit rengi grubu
                        gösterir, rehber kalabalıkta kendi grubunu bir bakışta
                        bulur. Oda ve koltuk planlardan canlı gelir.</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
