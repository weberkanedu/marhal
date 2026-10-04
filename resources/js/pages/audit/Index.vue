<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/audit';
import type { Paginated } from '@/types/person';

type LogRow = {
    id: number;
    created_at: string | null;
    user: string | null;
    action: string;
    action_key: string;
    subject: string | null;
    subject_id: string | null;
    details: string | null;
    ip: string | null;
};

const props = defineProps<{
    logs: Paginated<LogRow>;
    filters: { action: string | null; user: string | null };
    actions: { value: string; label: string }[];
    users: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Erişim kayıtları', href: index() }],
    },
});

const action = ref(props.filters.action ?? '');
const user = ref(props.filters.user ?? '');

function apply(): void {
    router.get(
        index.url(),
        {
            ...(action.value ? { action: action.value } : {}),
            ...(user.value ? { user: user.value } : {}),
        },
        { preserveScroll: true, preserveState: true },
    );
}

// Kritik işlemler renkli rozetle öne çıkar.
type BadgeVariant = 'warning' | 'danger' | 'secondary';

const variants: Record<string, BadgeVariant> = {
    view_sensitive: 'warning',
    export: 'warning',
    login_failed: 'danger',
    delete: 'danger',
};

const variant = (key: string): BadgeVariant => variants[key] ?? 'secondary';

const formatDateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('tr-TR', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
</script>

<template>
    <Head title="Erişim kayıtları" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Erişim kayıtları
            </h1>
            <p class="text-sm text-muted-foreground">
                Kim, ne zaman, hangi kayda ne yaptı. Kimlik ve pasaport
                numaraları kayıtlara yazılmaz. Bu liste silinemez ve
                değiştirilemez.
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1">
                <Label for="action" class="text-xs">İşlem</Label>
                <select
                    id="action"
                    v-model="action"
                    :class="selectClass"
                    class="w-56"
                    @change="apply"
                >
                    <option value="">Tümü</option>
                    <option
                        v-for="item in actions"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="user" class="text-xs">Kullanıcı</Label>
                <select
                    id="user"
                    v-model="user"
                    :class="selectClass"
                    class="w-56"
                    @change="apply"
                >
                    <option value="">Tümü</option>
                    <option
                        v-for="u in users"
                        :key="u.id"
                        :value="String(u.id)"
                    >
                        {{ u.name }}
                    </option>
                </select>
            </div>
        </div>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <p
                    v-if="logs.data.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Kayıt yok.
                </p>
                <table v-else class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Zaman</th>
                            <th class="px-4 py-2 font-medium">Kullanıcı</th>
                            <th class="px-4 py-2 font-medium">İşlem</th>
                            <th class="px-4 py-2 font-medium">Kayıt</th>
                            <th class="px-4 py-2 font-medium">Ayrıntı</th>
                            <th class="px-4 py-2 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2 tabular-nums">
                                {{ formatDateTime(log.created_at) }}
                            </td>
                            <td class="px-4 py-2">{{ log.user ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <Badge :variant="variant(log.action_key)">
                                    {{ log.action }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ log.subject ?? '—' }}
                            </td>
                            <td
                                class="max-w-72 truncate px-4 py-2 text-muted-foreground"
                                :title="log.details ?? ''"
                            >
                                {{ log.details ?? '' }}
                            </td>
                            <td
                                class="px-4 py-2 font-mono text-xs text-muted-foreground"
                            >
                                {{ log.ip ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div
            v-if="logs.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <span class="text-muted-foreground">
                {{ logs.from }}–{{ logs.to }} / {{ logs.total }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!logs.prev_page_url"
                    @click="
                        logs.prev_page_url &&
                        router.visit(logs.prev_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Önceki
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!logs.next_page_url"
                    @click="
                        logs.next_page_url &&
                        router.visit(logs.next_page_url, {
                            preserveScroll: true,
                        })
                    "
                >
                    Sonraki
                </Button>
            </div>
        </div>
    </div>
</template>
