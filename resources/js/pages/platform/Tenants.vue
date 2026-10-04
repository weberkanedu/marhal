<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as tenantsIndex } from '@/routes/platform/tenants';

type TenantRow = {
    id: string;
    name: string;
    plan: string;
    status: string;
    users_count: number;
    accessible: boolean;
    created_at: string | null;
};

defineProps<{
    tenants: TenantRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Acenteler',
                href: tenantsIndex(),
            },
        ],
    },
});

const statusLabels: Record<string, string> = {
    trial: 'Deneme',
    active: 'Aktif',
    suspended: 'Askıda',
};
</script>

<template>
    <Head title="Acenteler" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <Card>
            <CardHeader>
                <CardTitle>Acenteler</CardTitle>
                <CardDescription>
                    Sistemi kullanan tüm acenteler ve paketleri
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="tenants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Henüz acente yok.
                </p>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 font-medium">Acente</th>
                            <th class="py-2 font-medium">Paket</th>
                            <th class="py-2 font-medium">Durum</th>
                            <th class="py-2 text-right font-medium">
                                Kullanıcı
                            </th>
                            <th class="py-2 text-right font-medium">Kayıt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2 font-medium">{{ tenant.name }}</td>
                            <td class="py-2">{{ tenant.plan }}</td>
                            <td class="py-2">
                                <Badge
                                    :variant="
                                        tenant.accessible
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                >
                                    {{
                                        statusLabels[tenant.status] ??
                                        tenant.status
                                    }}
                                </Badge>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ tenant.users_count }}
                            </td>
                            <td class="py-2 text-right">
                                {{ tenant.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
