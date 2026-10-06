<script setup lang="ts">
import type { ActivityItem } from '@/types/dashboard';

/**
 * "Son hareketler": yeni yolcu, tura kayıt, iptal, tahsilat / iade (erişim kayıtlarından).
 */
defineProps<{ items: ActivityItem[] }>();

const dot: Record<ActivityItem['tone'], string> = {
    neutral: '',
    success: 'success',
    warning: 'warning',
    danger: 'warning',
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
    <div class="card a-act">
        <h4>Son hareketler</h4>
        <ul class="acts">
            <li v-for="item in items" :key="item.id">
                <span class="d" :class="dot[item.tone]" />
                <div>
                    {{ item.text
                    }}<small
                        >{{ item.user ? `${item.user} · ` : ''
                        }}{{ ago(item.at) }}</small
                    >
                </div>
            </li>
            <li v-if="items.length === 0">
                <span class="d" />
                <div>Henüz hareket yok.</div>
            </li>
        </ul>
    </div>
</template>
