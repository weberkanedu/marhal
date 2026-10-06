<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import MockTop from '@/components/mock/MockTop.vue';
import { ageFrom } from '@/lib/format';
import { show as importPage } from '@/routes/person-import';
import { create, index, show } from '@/routes/persons';
import {
    list as personReport,
    passports as passportReport,
} from '@/routes/reports/persons';
import type { ExportItem } from '@/types/export';
import type { Option, Paginated, PersonListItem } from '@/types/person';

/**
 * Yolcular — tasarım sayfasındaki "Yolcular" ekranının birebir hâli (gerçek veriyle). Kimlik, telefon
 * ve pasaport numaraları listede maskeli; tamamı yolcunun sayfasında.
 */
type FilterKey = 'pasaport' | 'turda' | 'kvkk' | 'ihtiyac';

const props = defineProps<{
    persons: Paginated<PersonListItem>;
    filters: { q: string; filtre: FilterKey | null };
    filterOptions: Option<FilterKey>[];
    stats: Record<FilterKey | 'total' | 'week', number>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Yolcular', href: index() }],
    },
});

const search = ref(props.filters.q);

function load(params: { q?: string; filtre?: FilterKey | null }): void {
    const q = params.q ?? search.value;
    const filtre =
        params.filtre === undefined ? props.filters.filtre : params.filtre;

    router.get(
        index.url(),
        { ...(q ? { q } : {}), ...(filtre ? { filtre } : {}) },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const runSearch = useDebounceFn((value: string) => load({ q: value }), 300);
watch(search, (value) => runSearch(value));

function toggleFilter(key: FilterKey | null): void {
    load({ filtre: props.filters.filtre === key ? null : key });
}

// Çıktılar ekrandaki süzgeci taşır.
const exportItems = computed<ExportItem[]>(() => {
    const query = props.filters.filtre ? { filtre: props.filters.filtre } : {};
    const scope =
        props.filterOptions.find((o) => o.value === props.filters.filtre)
            ?.label ?? 'Tüm yolcular';

    return [
        {
            title: 'Yolcu listesi',
            description: scope,
            url: personReport.url({ query }),
        },
        {
            title: 'Pasaport kontrol listesi',
            description: 'Süresi kısa ve eksik olanlar',
            url: passportReport.url({ query }),
        },
    ];
});

// "Yeni yolcu" menüsü (elle / Excel'den).
const newOpen = ref(false);
const newMenu = ref<HTMLElement | null>(null);

function outside(e: MouseEvent): void {
    if (newOpen.value && !newMenu.value?.contains(e.target as Node)) {
        newOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
// Maskeli numaralar tasarımdaki gibi noktalı.
const dots = (value: string | null) => value?.replace(/\*/g, '•') ?? '—';
const passportChip = (p: PersonListItem): [string, string] =>
    p.passport_issue === null
        ? ['ok', 'Geçerli']
        : p.passport_issue.startsWith('6 ay')
          ? ['warning', '6 aydan kısa']
          : ['danger', p.passport_issue];
</script>

<template>
    <Head title="Yolcular" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: 'Yolcular' }]"
                title="Yolcular"
                :exports="exportItems"
            >
                <div ref="newMenu" class="exp">
                    <button
                        class="btn"
                        type="button"
                        :aria-expanded="newOpen"
                        aria-haspopup="menu"
                        @click="newOpen = !newOpen"
                    >
                        <MockIcon name="plus" />Yeni yolcu
                    </button>
                    <div v-if="newOpen" class="menu" role="menu">
                        <Link class="mi" role="menuitem" :href="create()">
                            <div>
                                <b>Bilgileri elle gir</b
                                ><small>Kimlik, pasaport, iletişim</small>
                            </div>
                        </Link>
                        <Link class="mi" role="menuitem" :href="importPage()">
                            <div>
                                <b>Excel'den aktar</b
                                ><small>Toplu yükleme, önizlemeli</small>
                            </div>
                        </Link>
                    </div>
                </div>
            </MockTop>

            <div class="g3">
                <div class="card">
                    <span class="lbl">Kayıtlı kişi</span>
                    <div class="big">{{ stats.total }}</div>
                    <span class="lbl"
                        ><span class="chip ok">+{{ stats.week }}</span> bu
                        hafta</span
                    >
                </div>
                <div
                    class="card"
                    role="button"
                    tabindex="0"
                    style="cursor: pointer"
                    @click="toggleFilter('pasaport')"
                >
                    <span class="lbl">Pasaportu sorunlu</span>
                    <div class="big">{{ stats.pasaport }}</div>
                    <span class="lbl"
                        >Tur tarihine göre 6 ay kuralı, eksik bilgi</span
                    >
                </div>
                <div
                    class="card"
                    role="button"
                    tabindex="0"
                    style="cursor: pointer"
                    @click="toggleFilter('ihtiyac')"
                >
                    <span class="lbl">Özel ihtiyacı olan</span>
                    <div class="big">{{ stats.ihtiyac }}</div>
                    <span class="lbl"
                        >Yürüme güçlüğü, tekerlekli sandalye, diyet</span
                    >
                </div>
            </div>

            <div class="card">
                <div class="row">
                    <div class="search">
                        <MockIcon name="search" /><input
                            v-model="search"
                            placeholder="Ad soyad, telefon, T.C. Kimlik No veya pasaport no"
                            aria-label="Yolcu ara"
                        />
                    </div>
                    <div class="pills">
                        <span
                            class="pill"
                            :class="{ on: !filters.filtre }"
                            role="button"
                            tabindex="0"
                            @click="toggleFilter(null)"
                            >Tümü</span
                        >
                        <span
                            v-for="option in filterOptions"
                            :key="option.value"
                            class="pill"
                            :class="{ on: filters.filtre === option.value }"
                            role="button"
                            tabindex="0"
                            @click="toggleFilter(option.value)"
                            >{{ option.label }}</span
                        >
                    </div>
                </div>
                <div class="tbl">
                    <table>
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th class="num hide-sm">TC Kimlik</th>
                                <th class="num hide-sm">Telefon</th>
                                <th>Pasaport</th>
                                <th>Durum</th>
                                <th>İhtiyaç</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="person in persons.data"
                                :key="person.id"
                                style="cursor: pointer"
                                @click="router.visit(show(person.id))"
                            >
                                <td>
                                    <div class="person">
                                        <span
                                            class="av"
                                            :class="
                                                person.gender === 'kadin'
                                                    ? 'k'
                                                    : 'e'
                                            "
                                            >{{ ini(person.full_name) }}</span
                                        >
                                        <div>
                                            <Link
                                                :href="show(person.id)"
                                                @click.stop
                                                >{{ person.full_name }}</Link
                                            ><small
                                                >{{
                                                    person.gender === 'kadin'
                                                        ? 'Kadın'
                                                        : 'Erkek'
                                                }}<template
                                                    v-if="person.birth_date"
                                                >
                                                    ·
                                                    {{
                                                        ageFrom(
                                                            person.birth_date,
                                                        )
                                                    }}
                                                    yaş</template
                                                ></small
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td class="num hide-sm">
                                    {{ dots(person.masked_national_id) }}
                                </td>
                                <td class="num hide-sm">
                                    {{ dots(person.masked_phone) }}
                                </td>
                                <td>
                                    {{ dots(person.masked_passport_no) }}
                                    <small
                                        v-if="person.passport_expiry_date"
                                        style="color: var(--m-muted)"
                                        >{{
                                            person.passport_expiry_date.slice(
                                                0,
                                                7,
                                            )
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <span
                                        class="chip"
                                        :class="passportChip(person)[0]"
                                        >{{ passportChip(person)[1] }}</span
                                    >
                                    <span
                                        v-if="person.on_tour"
                                        class="chip acc"
                                        style="margin-left: 4px"
                                        >Turda</span
                                    >
                                </td>
                                <td>
                                    <template v-if="person.needs?.length">
                                        <span
                                            v-for="need in person.needs"
                                            :key="need"
                                            class="tag"
                                            style="margin-right: 4px"
                                            >{{ need }}</span
                                        >
                                    </template>
                                    <span v-else class="lbl">—</span>
                                </td>
                            </tr>
                            <tr v-if="!persons.data.length" class="empty-row">
                                <td colspan="6">
                                    {{
                                        filters.q || filters.filtre
                                            ? 'Bu filtrede yolcu yok'
                                            : 'Henüz yolcu eklenmedi'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="persons.last_page > 1" class="row">
                    <span class="lbl"
                        >{{ persons.from }}–{{ persons.to }} /
                        {{ persons.total }}</span
                    >
                    <span style="flex: 1" />
                    <button
                        class="btn ghost sm"
                        type="button"
                        :disabled="!persons.prev_page_url"
                        @click="
                            persons.prev_page_url &&
                            router.visit(persons.prev_page_url, {
                                preserveScroll: true,
                            })
                        "
                    >
                        Önceki
                    </button>
                    <button
                        class="btn ghost sm"
                        type="button"
                        :disabled="!persons.next_page_url"
                        @click="
                            persons.next_page_url &&
                            router.visit(persons.next_page_url, {
                                preserveScroll: true,
                            })
                        "
                    >
                        Sonraki
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
