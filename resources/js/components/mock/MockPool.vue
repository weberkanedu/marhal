<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Tasarımdaki "Yerleşmemiş" havuzu: aileler bir arada, arama, yolcu hapları ([data-pid], sürüklenir).
 * Yerleşmiş bir yolcu buraya bırakılırsa yeri boşalır ([data-drop="list"]).
 */
export type PoolPerson = {
    id: string;
    name: string;
    // 'E' | 'K'
    g: 'E' | 'K';
    age: number | null;
    tag?: string | null;
    group?: string | null;
};

const props = defineProps<{
    units: { label: string | null; people: PoolPerson[] }[];
    hint: string;
    selected: string | null;
    locked?: boolean;
}>();

const q = ref('');
const norm = (s: string) => s.toLocaleLowerCase('tr');
const total = computed(() =>
    props.units.reduce((n, u) => n + u.people.length, 0),
);
const shown = computed(() =>
    props.units
        .map((u) => ({
            ...u,
            people: u.people.filter((p) =>
                norm(p.name).includes(norm(q.value)),
            ),
        }))
        .filter((u) => u.people.length > 0),
);

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');

const surname = (name: string) => name.trim().split(/\s+/).slice(-1)[0];

const familyLabel = (u: { label: string | null; people: PoolPerson[] }) =>
    [u.label ?? `${surname(u.people[0].name)} ailesi`, u.people[0].group]
        .filter(Boolean)
        .join(' · ');
</script>

<template>
    <div class="card pool">
        <h4>
            Yerleşmemiş <em>{{ total }} yolcu</em>
        </h4>
        <div class="search">
            <Search />
            <input
                v-model="q"
                placeholder="Ara"
                aria-label="Yerleşmemiş yolcu ara"
            />
        </div>
        <div class="list" data-drop="list">
            <template v-if="shown.length">
                <div v-for="(u, i) in shown" :key="i" class="fam">
                    <small>{{ familyLabel(u) }}</small>
                    <div
                        v-for="p in u.people"
                        :key="p.id"
                        class="pax"
                        :class="{ sel: selected === p.id }"
                        :data-pid="p.id"
                        data-from="list"
                        :data-locked="locked ? '' : undefined"
                        tabindex="0"
                        role="button"
                        :aria-label="`${p.name} seç`"
                    >
                        <span class="av" :class="p.g.toLowerCase()">{{
                            ini(p.name)
                        }}</span>
                        <span class="nm">{{ p.name }}</span>
                        <span v-if="p.tag" class="tag">{{ p.tag }}</span>
                        <small
                            >{{ p.g
                            }}<template v-if="p.age !== null">
                                · {{ p.age }}</template
                            ></small
                        >
                    </div>
                </div>
            </template>
            <div v-else class="empty-pool">
                Herkes yerleşti. Bir yolcuyu buraya bırakırsan yeri boşalır.
            </div>
        </div>
        <span class="lbl">{{ hint }}</span>
    </div>
</template>
