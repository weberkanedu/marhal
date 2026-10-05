<script setup lang="ts">
import { FileSpreadsheet, FileText } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Excel / PDF indirme düğmeleri. `url` format parametresi olmadan verilir;
 * düğmeler ?format=xlsx|pdf ekler. İndirme Inertia değil, normal bağlantıdır.
 */
const props = defineProps<{
    url: string;
    label?: string;
    size?: 'sm' | 'default';
}>();

const withFormat = (format: 'xlsx' | 'pdf') =>
    computed(
        () =>
            `${props.url}${props.url.includes('?') ? '&' : '?'}format=${format}`,
    );

const xlsx = withFormat('xlsx');
const pdf = withFormat('pdf');
</script>

<template>
    <div class="inline-flex items-center gap-1">
        <span v-if="label" class="mr-1 text-sm text-muted-foreground">
            {{ label }}
        </span>
        <Button variant="outline" :size="size ?? 'sm'" as-child>
            <a :href="xlsx" title="Excel olarak indir">
                <FileSpreadsheet class="text-success" /> Excel
            </a>
        </Button>
        <Button variant="outline" :size="size ?? 'sm'" as-child>
            <a :href="pdf" title="PDF olarak indir">
                <FileText class="text-danger" /> PDF
            </a>
        </Button>
    </div>
</template>
