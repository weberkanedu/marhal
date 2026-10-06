<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { ExportItem } from '@/types/export';

/**
 * Tasarımdaki ekran başlığı: üstte kırıntı yolu, büyük başlık, sağda "Çıktı al" menüsü ve düğmeler.
 */
defineProps<{
    crumbs: { label: string; href?: string }[];
    title: string;
    exports?: ExportItem[];
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);

const withFormat = (url: string, format: 'xlsx' | 'pdf') =>
    `${url}${url.includes('?') ? '&' : '?'}format=${format}`;

function outside(e: MouseEvent): void {
    if (open.value && !root.value?.contains(e.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', outside));
onBeforeUnmount(() => document.removeEventListener('click', outside));
</script>

<template>
    <div class="top">
        <div>
            <div class="crumb">
                <template v-for="(crumb, i) in crumbs" :key="i">
                    <template v-if="i > 0"> · </template>
                    <Link v-if="crumb.href" :href="crumb.href">{{
                        crumb.label
                    }}</Link>
                    <template v-else>{{ crumb.label }}</template>
                </template>
            </div>
            <h3>{{ title }}</h3>
        </div>
        <div class="btns">
            <div v-if="exports?.length" ref="root" class="exp">
                <button
                    class="btn ghost"
                    type="button"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    @click="open = !open"
                >
                    <Download /> Çıktı al
                </button>
                <div v-if="open" class="menu" role="menu">
                    <div
                        v-for="item in exports"
                        :key="item.url"
                        class="mi"
                        role="menuitem"
                    >
                        <div>
                            <b>{{ item.title }}</b>
                            <small>{{ item.description }}</small>
                        </div>
                        <a
                            v-if="!item.pdfOnly"
                            class="dl x"
                            :href="withFormat(item.url, 'xlsx')"
                            @click="open = false"
                            >Excel</a
                        >
                        <a
                            class="dl p"
                            :href="
                                item.pdfOnly
                                    ? item.url
                                    : withFormat(item.url, 'pdf')
                            "
                            @click="open = false"
                            >PDF</a
                        >
                    </div>
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>
