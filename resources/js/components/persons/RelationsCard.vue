<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Plus, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PersonRelationController from '@/actions/App/Http/Controllers/PersonRelationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { selectClass } from '@/lib/formClasses';
import { lookup, show as showPerson } from '@/routes/persons';
import type { Option } from '@/types/person';

export type PersonRelationRow = {
    id: string;
    person_id: string;
    full_name: string;
    relation: string;
    relation_label: string;
    is_family: boolean;
};

const props = defineProps<{
    personId: string;
    personName: string;
    relations: PersonRelationRow[];
    options: Option[];
    canUpdate: boolean;
}>();

const adding = ref(false);
const search = ref('');
const results = ref<{ id: string; full_name: string }[]>([]);
const chosen = ref<{ id: string; full_name: string } | null>(null);
const relation = ref('es');
const saving = ref(false);

const runSearch = useDebounceFn(async (value: string) => {
    if (value.trim().length < 2) {
        results.value = [];

        return;
    }

    const response = await fetch(lookup.url({ query: { q: value } }), {
        headers: { Accept: 'application/json' },
    });
    const found: { id: string; full_name: string }[] = response.ok
        ? await response.json()
        : [];
    results.value = found.filter(
        (p) =>
            p.id !== props.personId &&
            !props.relations.some((r) => r.person_id === p.id),
    );
}, 300);

watch(search, (value) => {
    chosen.value = null;
    runSearch(value);
});

function choose(person: { id: string; full_name: string }): void {
    chosen.value = person;
    results.value = [];
}

function save(): void {
    if (!chosen.value) {
        return;
    }

    saving.value = true;
    router.post(
        PersonRelationController.store.url(props.personId),
        { related_person_id: chosen.value.id, relation: relation.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                adding.value = false;
                search.value = '';
                chosen.value = null;
            },
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Eklenemedi.'),
            onFinish: () => (saving.value = false),
        },
    );
}

function remove(row: PersonRelationRow): void {
    if (confirm(`${row.full_name} ile yakınlık kaldırılsın mı?`)) {
        router.delete(PersonRelationController.destroy.url(row.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-2">
            <div>
                <CardTitle>Yakınlar</CardTitle>
                <CardDescription>
                    Aile odasında birlikte kalabilmek için gerekir. Karşı tarafa
                    da otomatik eklenir.
                </CardDescription>
            </div>
            <Button
                v-if="canUpdate && !adding"
                variant="ghost"
                size="sm"
                @click="adding = true"
            >
                <Plus /> Ekle
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-3 text-sm">
            <div
                v-if="adding"
                class="flex flex-col gap-2 rounded-md border p-3"
            >
                <div class="relative">
                    <Search
                        class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="pl-8"
                        placeholder="Yakınını ad, T.C. veya pasaport no ile arayın"
                    />
                    <ul
                        v-if="results.length"
                        class="absolute z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md"
                    >
                        <li v-for="p in results" :key="p.id">
                            <button
                                type="button"
                                class="w-full px-3 py-2 text-left hover:bg-muted"
                                @click="choose(p)"
                            >
                                {{ p.full_name }}
                            </button>
                        </li>
                    </ul>
                </div>
                <div v-if="chosen" class="flex flex-wrap items-center gap-2">
                    <span>
                        <strong>{{ chosen.full_name }}</strong
                        >, {{ personName }} adlı yolcunun
                    </span>
                    <select v-model="relation" :class="[selectClass, 'w-auto']">
                        <option
                            v-for="option in options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="
                            adding = false;
                            search = '';
                        "
                    >
                        Vazgeç
                    </Button>
                    <Button
                        size="sm"
                        :disabled="!chosen || saving"
                        @click="save"
                    >
                        Kaydet
                    </Button>
                </div>
            </div>

            <p
                v-if="relations.length === 0 && !adding"
                class="text-muted-foreground"
            >
                Kayıtlı yakını yok.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="row in relations"
                    :key="row.id"
                    class="flex items-center justify-between gap-2 py-2"
                >
                    <div>
                        <Link
                            :href="showPerson(row.person_id)"
                            class="font-medium hover:underline"
                        >
                            {{ row.full_name }}
                        </Link>
                        <span class="text-muted-foreground">
                            — {{ row.relation_label }}
                        </span>
                        <Badge
                            v-if="!row.is_family"
                            variant="outline"
                            class="ml-2"
                        >
                            Aile odası dışı
                        </Badge>
                    </div>
                    <Button
                        v-if="canUpdate"
                        variant="ghost"
                        size="icon-sm"
                        class="text-destructive"
                        title="Kaldır"
                        @click="remove(row)"
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
