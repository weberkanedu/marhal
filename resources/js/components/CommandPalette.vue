<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import { search as searchRoute } from '@/routes';
import { show as showPerson } from '@/routes/persons';
import { show as showTour } from '@/routes/tours';

/**
 * Yan menüdeki "Ara" (Ctrl K) — tasarımdaki arama penceresi: yolcular ve turlar sunucudan
 * (SearchController), ekranlar ve işlemler buradan süzülür. Ok tuşları, Enter ve Esc çalışır.
 */
type Item = { icon: string; title: string; sub: string; href: string };

const props = defineProps<{
    screens: { title: string; href: string }[];
    actions: { title: string; href: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const q = ref('');
const index = ref(0);
const input = ref<HTMLInputElement | null>(null);
const remote = ref<{
    persons: { id: string; name: string; sub: string }[];
    tours: { id: string; name: string; sub: string }[];
}>({ persons: [], tours: [] });

const norm = (s: string) => s.toLocaleLowerCase('tr');

const load = useDebounceFn(async (value: string) => {
    if (value.trim().length < 2) {
        remote.value = { persons: [], tours: [] };

        return;
    }

    try {
        const response = await fetch(
            searchRoute.url({ query: { q: value.trim() } }),
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok && value === q.value) {
            remote.value = await response.json();
        }
    } catch {
        // Arama ağ hatasında sessizce boş kalır; ekranlar / işlemler yine çalışır.
    }
}, 200);

watch(q, (value) => {
    index.value = 0;
    load(value);
});

watch(open, async (isOpen) => {
    if (isOpen) {
        q.value = '';
        index.value = 0;
        remote.value = { persons: [], tours: [] };
        await nextTick();
        input.value?.focus();
    }
});

const groups = computed(() => {
    const match = (title: string) =>
        !q.value || norm(title).includes(norm(q.value));
    const list: [string, Item[]][] = [
        [
            'Yolcular',
            remote.value.persons.map((p) => ({
                icon: 'user',
                title: p.name,
                sub: p.sub,
                href: showPerson.url(p.id),
            })),
        ],
        [
            'Turlar',
            remote.value.tours.map((t) => ({
                icon: 'plane',
                title: t.name,
                sub: t.sub,
                href: showTour.url(t.id),
            })),
        ],
        [
            'Ekranlar',
            props.screens
                .filter((s) => match(s.title))
                .map((s) => ({
                    icon: 'grid',
                    title: s.title,
                    sub: 'Ekran',
                    href: s.href,
                })),
        ],
        [
            'İşlemler',
            props.actions
                .filter((a) => match(a.title))
                .map((a) => ({
                    icon: 'bolt',
                    title: a.title,
                    sub: 'İşlem',
                    href: a.href,
                })),
        ],
    ];

    return list.filter(([, items]) => items.length > 0);
});
const flat = computed(() => groups.value.flatMap(([, items]) => items));
const offset = (g: number) =>
    groups.value.slice(0, g).reduce((n, [, items]) => n + items.length, 0);

function run(item: Item | undefined): void {
    if (!item) {
        return;
    }

    open.value = false;
    router.visit(item.href);
}

function key(e: KeyboardEvent): void {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        index.value = Math.min(index.value + 1, flat.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        index.value = Math.max(index.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        run(flat.value[index.value]);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="mx pal-host">
            <div class="pal-bg" @click.self="open = false">
                <div class="pal" role="dialog" aria-label="Arama">
                    <div class="search">
                        <MockIcon name="search" /><input
                            ref="input"
                            v-model="q"
                            placeholder="Yolcu, tur, ekran ya da işlem ara"
                            aria-label="Ara"
                            @keydown="key"
                        />
                    </div>
                    <ul>
                        <template
                            v-for="([title, items], g) in groups"
                            :key="title"
                        >
                            <li class="grp" aria-hidden="true">{{ title }}</li>
                            <li
                                v-for="(item, i) in items"
                                :key="item.href"
                                :class="{ act: offset(g) + i === index }"
                                @mouseenter="index = offset(g) + i"
                                @click="run(item)"
                            >
                                <MockIcon :name="item.icon" />{{ item.title
                                }}<small>{{ item.sub }}</small>
                            </li>
                        </template>
                        <li v-if="!groups.length" class="grp">Sonuç yok</li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>
