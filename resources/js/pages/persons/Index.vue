<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Search, UserRound } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ageFrom, formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/persons';
import type { Paginated, PersonListItem } from '@/types/person';

const props = defineProps<{
    persons: Paginated<PersonListItem>;
    filters: { q: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Yolcular', href: index() }],
    },
});

const search = ref(props.filters.q);

const runSearch = useDebounceFn((value: string) => {
    router.get(index.url(), value ? { q: value } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}, 300);

watch(search, (value) => runSearch(value));

const genderLabels: Record<string, string> = { erkek: 'Erkek', kadin: 'Kadın' };
</script>

<template>
    <Head title="Yolcular" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Yolcular</h1>
                <p class="text-sm text-muted-foreground">
                    Toplam {{ persons.total }} kişi
                </p>
            </div>
            <Button as-child>
                <Link :href="create()"><Plus /> Yeni yolcu</Link>
            </Button>
        </div>

        <div class="relative max-w-md">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                class="pl-9"
                placeholder="Ad, soyad, telefon, T.C. Kimlik No veya pasaport no"
            />
        </div>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <div
                    v-if="persons.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    <template v-if="filters.q">
                        "{{ filters.q }}" için sonuç bulunamadı.
                    </template>
                    <template v-else> Henüz yolcu eklenmedi. </template>
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Ad Soyad</th>
                            <th class="px-4 py-2 font-medium">
                                Cinsiyet / Yaş
                            </th>
                            <th class="px-4 py-2 font-medium">Telefon</th>
                            <th class="px-4 py-2 font-medium">
                                T.C. Kimlik No
                            </th>
                            <th class="px-4 py-2 font-medium">Pasaport</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in persons.data"
                            :key="person.id"
                            class="cursor-pointer border-t hover:bg-muted/40"
                            @click="router.visit(show(person.id))"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="show(person.id)"
                                    class="flex items-center gap-2 font-medium"
                                    @click.stop
                                >
                                    <UserRound
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ person.full_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">
                                {{ genderLabels[person.gender] }}
                                <span
                                    v-if="person.birth_date"
                                    class="text-muted-foreground"
                                >
                                    · {{ ageFrom(person.birth_date) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 tabular-nums">
                                {{ person.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ person.masked_national_id ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-mono text-xs">
                                    {{ person.masked_passport_no ?? '—' }}
                                </span>
                                <span
                                    v-if="person.passport_expiring"
                                    class="ml-2 inline-flex items-center gap-1 text-xs text-warning"
                                    :title="`Geçerlilik: ${formatDate(person.passport_expiry_date)}`"
                                >
                                    <AlertTriangle class="size-3" /> 6 aydan az
                                </span>
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
