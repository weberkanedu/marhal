<script setup lang="ts">
import { computed } from 'vue';
import type { Gender } from '@/types/person';

/**
 * Yolcunun baş harfleri; kenar halkası cinsiyete göre (kadın / erkek renkleri temadan).
 * Oda / koltuk planlarında da aynı işaret kullanılır.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        gender?: Gender | null;
        size?: 'sm' | 'md';
    }>(),
    { gender: null, size: 'md' },
);

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toLocaleUpperCase('tr'))
        .join(''),
);
</script>

<template>
    <span
        class="grid shrink-0 place-items-center rounded-full border bg-muted font-semibold"
        :class="[
            size === 'sm' ? 'size-6 text-[10px]' : 'size-8 text-[11px]',
            gender === 'kadin'
                ? 'ring-2 ring-women/70 ring-inset'
                : gender === 'erkek'
                  ? 'ring-2 ring-men/70 ring-inset'
                  : '',
        ]"
        aria-hidden="true"
    >
        {{ initials }}
    </span>
</template>
