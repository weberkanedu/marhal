<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/platform/feedback';
import type { Paginated } from '@/types/person';

type FeedbackRow = {
    id: number;
    tenant: string;
    user: string | null;
    type: 'oneri' | 'hata' | 'begeni';
    type_label: string;
    rating: number | null;
    message: string;
    screen: string | null;
    status: string;
    created_at: string;
};

defineProps<{ items: Paginated<FeedbackRow> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Geri bildirimler', href: index() }],
    },
});

const variant = {
    oneri: 'secondary',
    hata: 'destructive',
    begeni: 'default',
} as const;

const when = (iso: string) =>
    new Date(iso).toLocaleString('tr-TR', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
</script>

<template>
    <Head title="Geri bildirimler" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Geri bildirimler
            </h1>
            <p class="text-sm text-muted-foreground">
                Acentelerin "Görüşünü paylaş" ile gönderdikleri. Yanıtlama ve
                durum takibi platform paneli aşamasında eklenecek.
            </p>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <p
                    v-if="items.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Henüz geri bildirim yok.
                </p>
                <ul v-else class="divide-y">
                    <li
                        v-for="item in items.data"
                        :key="item.id"
                        class="grid gap-1.5 px-4 py-3"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >#{{ item.id }}</span
                            >
                            <Badge :variant="variant[item.type]">
                                {{ item.type_label }}
                            </Badge>
                            <span class="font-medium">{{ item.tenant }}</span>
                            <span class="text-muted-foreground">
                                {{ item.user ?? '—' }} ·
                                {{ when(item.created_at) }}
                            </span>
                            <span
                                v-if="item.rating"
                                class="ml-auto flex items-center gap-0.5"
                                :aria-label="`${item.rating} yıldız`"
                            >
                                <Star
                                    v-for="n in 5"
                                    :key="n"
                                    class="size-3.5"
                                    :class="
                                        n <= item.rating
                                            ? 'fill-primary text-primary'
                                            : 'text-muted-foreground'
                                    "
                                />
                            </span>
                        </div>
                        <p class="text-sm whitespace-pre-line">
                            {{ item.message }}
                        </p>
                        <p
                            v-if="item.screen"
                            class="font-mono text-xs text-muted-foreground"
                        >
                            Ekran: {{ item.screen }}
                        </p>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="items.last_page > 1"
            class="flex items-center justify-end gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!items.prev_page_url"
                @click="
                    items.prev_page_url && router.visit(items.prev_page_url)
                "
            >
                Önceki
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!items.next_page_url"
                @click="
                    items.next_page_url && router.visit(items.next_page_url)
                "
            >
                Sonraki
            </Button>
        </div>
    </div>
</template>
