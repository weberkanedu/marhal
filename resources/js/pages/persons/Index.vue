<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronDown,
    FileSpreadsheet,
    Link2,
    PenLine,
    Plane,
    Plus,
    Search,
    ShieldAlert,
    Users,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ExportMenu from '@/components/ExportMenu.vue';
import PersonAvatar from '@/components/persons/PersonAvatar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ageFrom, formatDate } from '@/lib/format';
import { show as importPage } from '@/routes/person-import';
import { create, index, show } from '@/routes/persons';
import {
    list as personReport,
    passports as passportReport,
} from '@/routes/reports/persons';
import type { ExportItem } from '@/types/export';
import type { Option, Paginated, PersonListItem } from '@/types/person';

type FilterKey = 'pasaport' | 'turda' | 'kvkk';

const props = defineProps<{
    persons: Paginated<PersonListItem>;
    filters: { q: string; filtre: FilterKey | null };
    filterOptions: Option<FilterKey>[];
    stats: Record<FilterKey | 'total', number>;
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

// Sayı kartları = hazır süzgeçler (tıklayınca liste süzülür).
const statCards = computed(() => [
    {
        key: null,
        label: 'Toplam yolcu',
        value: props.stats.total,
        icon: Users,
        tone: '',
    },
    ...props.filterOptions.map((o) => ({
        key: o.value,
        label: o.label,
        value: props.stats[o.value],
        icon: { pasaport: AlertTriangle, turda: Plane, kvkk: ShieldAlert }[
            o.value
        ],
        tone:
            o.value === 'turda'
                ? ''
                : props.stats[o.value] > 0
                  ? 'text-warning'
                  : '',
    })),
]);

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
            description: 'Sorunlular üstte · 6 ay kuralı',
            url: passportReport.url({ query }),
        },
    ];
});

const genderLabels: Record<string, string> = { erkek: 'Erkek', kadin: 'Kadın' };
</script>

<template>
    <Head title="Yolcular" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Yolcular</h1>
                <p class="text-sm text-muted-foreground">
                    Kimlik, telefon ve pasaport numaraları listede maskeli;
                    tamamı yolcunun sayfasında.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <ExportMenu :items="exportItems" />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            ><Plus /> Yeni yolcu <ChevronDown class="-mr-1"
                        /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-72">
                        <DropdownMenuItem as-child>
                            <Link
                                :href="create()"
                                class="flex items-start gap-2"
                            >
                                <PenLine class="mt-0.5" />
                                <span>
                                    <span class="block font-medium"
                                        >Bilgileri elle gir</span
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >Kimlik, pasaport, iletişim</span
                                    >
                                </span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link
                                :href="importPage()"
                                class="flex items-start gap-2"
                            >
                                <FileSpreadsheet class="mt-0.5" />
                                <span>
                                    <span class="block font-medium"
                                        >Excel'den aktar</span
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >Toplu yükleme, önizlemeli</span
                                    >
                                </span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled
                            class="flex items-start gap-2"
                        >
                            <Link2 class="mt-0.5" />
                            <span>
                                <span class="block font-medium"
                                    >Ön kayıt linki gönder</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Yolcu kendisi doldurur · yakında</span
                                >
                            </span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Sayı kartları (süzgeç) -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button
                v-for="card in statCards"
                :key="card.label"
                type="button"
                class="kpi-tile"
                :class="{
                    'border-primary!':
                        filters.filtre === card.key && card.key !== null,
                }"
                :aria-pressed="filters.filtre === card.key"
                @click="toggleFilter(card.key)"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-accent-foreground"
                >
                    <component :is="card.icon" class="size-5" />
                </span>
                <span class="min-w-0">
                    <small>{{ card.label }}</small>
                    <span class="kpi-value num text-xl" :class="card.tone">{{
                        card.value
                    }}</span>
                </span>
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative w-full max-w-md">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-9"
                    placeholder="Ad, soyad, telefon, T.C. Kimlik No veya pasaport no"
                />
            </div>
            <button
                v-for="option in filterOptions"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="
                    filters.filtre === option.value
                        ? 'border-primary bg-accent text-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="toggleFilter(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <div
                    v-if="persons.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    <template v-if="filters.q || filters.filtre">
                        Bu aramaya / süzgece uyan yolcu yok.
                    </template>
                    <template v-else> Henüz yolcu eklenmedi. </template>
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Yolcu</th>
                            <th class="px-4 py-2.5 font-medium">Telefon</th>
                            <th class="px-4 py-2.5 font-medium">
                                T.C. Kimlik No
                            </th>
                            <th class="px-4 py-2.5 font-medium">Pasaport</th>
                            <th class="px-4 py-2.5 font-medium">Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in persons.data"
                            :key="person.id"
                            class="cursor-pointer border-t hover:bg-muted"
                            @click="router.visit(show(person.id))"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="show(person.id)"
                                    class="flex items-center gap-2.5"
                                    @click.stop
                                >
                                    <PersonAvatar
                                        :name="person.full_name"
                                        :gender="person.gender"
                                    />
                                    <span>
                                        <span class="block font-medium">{{
                                            person.full_name
                                        }}</span>
                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            {{ genderLabels[person.gender] }}
                                            <template v-if="person.birth_date">
                                                ·
                                                {{ ageFrom(person.birth_date) }}
                                            </template>
                                        </span>
                                    </span>
                                </Link>
                            </td>
                            <td
                                class="px-4 py-2 whitespace-nowrap tabular-nums"
                            >
                                {{ person.masked_phone ?? '—' }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ person.masked_national_id ?? '—' }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="font-mono text-xs">
                                    {{ person.masked_passport_no ?? '—' }}
                                </span>
                                <span
                                    v-if="person.passport_expiry_date"
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{
                                        formatDate(person.passport_expiry_date)
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-if="person.passport_issue"
                                        class="inline-flex items-center gap-1 rounded-full bg-warning-soft px-2 py-0.5 text-xs font-semibold text-warning"
                                    >
                                        <AlertTriangle class="size-3" />
                                        {{ person.passport_issue }}
                                    </span>
                                    <span
                                        v-if="person.on_tour"
                                        class="rounded-full bg-success-soft px-2 py-0.5 text-xs font-semibold text-success"
                                    >
                                        Turda
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div
            v-if="persons.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <span class="text-muted-foreground">
                {{ persons.from }}–{{ persons.to }} / {{ persons.total }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!persons.prev_page_url"
                    @click="
                        persons.prev_page_url &&
                        router.visit(persons.prev_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Önceki
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!persons.next_page_url"
                    @click="
                        persons.next_page_url &&
                        router.visit(persons.next_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Sonraki
                </Button>
            </div>
        </div>
    </div>
</template>
