<script setup lang="ts">
import { Activity } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ActivityItem } from '@/types/dashboard';

/**
 * "Son hareketler": yeni yolcu, tura kayıt, iptal, tahsilat / iade (erişim kayıtlarından).
 */
defineProps<{ items: ActivityItem[] }>();

const dot: Record<ActivityItem['tone'], string> = {
    neutral: 'bg-muted-foreground',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
};

function ago(iso: string): string {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return 'az önce';
    }

    if (minutes < 60) {
        return `${minutes} dk önce`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours} sa önce`;
    }

    return new Date(iso).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Card class="h-full">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Activity class="size-4" /> Son hareketler
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p v-if="items.length === 0" class="text-sm text-muted-foreground">
                Henüz hareket yok.
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex gap-2.5 text-sm"
                >
                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :class="dot[item.tone]"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <p class="leading-snug">{{ item.text }}</p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="item.user"
                                >{{ item.user }} · </template
                            >{{ ago(item.at) }}
                        </p>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
