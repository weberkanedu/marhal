<script setup lang="ts">
import { Download, FileSpreadsheet, FileText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ExportItem } from '@/types/export';

/**
 * Her ekranın "Çıktı al" menüsü: liste adı, kısa açıklama ve Excel / PDF düğmeleri.
 * `url` format parametresi olmadan verilir (ExportButtons ile aynı kural: ?format=xlsx|pdf).
 * Yalnız PDF olan çıktılar (yaka kartı, koltuk planı) için `pdfOnly`.
 */
defineProps<{
    items: ExportItem[];
    label?: string;
}>();

const withFormat = (url: string, format: 'xlsx' | 'pdf') =>
    `${url}${url.includes('?') ? '&' : '?'}format=${format}`;

// PDF'i olmayan tek tip çıktılar kendi biçimini adresinde taşır (yaka kartı vb.).
const pdfUrl = (item: ExportItem) =>
    item.pdfOnly ? item.url : withFormat(item.url, 'pdf');
</script>

<template>
    <DropdownMenu v-if="items.length > 0">
        <DropdownMenuTrigger as-child>
            <Button variant="outline">
                <Download /> {{ label ?? 'Çıktı al' }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-1.5">
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                Excel veya PDF olarak indir
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div
                v-for="item in items"
                :key="item.url"
                class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-muted"
            >
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">
                        {{ item.title }}
                    </div>
                    <div
                        v-if="item.description"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ item.description }}
                    </div>
                </div>
                <a
                    v-if="!item.pdfOnly"
                    :href="withFormat(item.url, 'xlsx')"
                    class="inline-flex items-center gap-1 rounded-md bg-success-soft px-2 py-1 text-xs font-semibold text-success hover:brightness-110"
                    :aria-label="`${item.title} Excel`"
                >
                    <FileSpreadsheet class="size-3.5" /> Excel
                </a>
                <a
                    :href="pdfUrl(item)"
                    class="inline-flex items-center gap-1 rounded-md bg-danger-soft px-2 py-1 text-xs font-semibold text-danger hover:brightness-110"
                    :aria-label="`${item.title} PDF`"
                >
                    <FileText class="size-3.5" /> PDF
                </a>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
