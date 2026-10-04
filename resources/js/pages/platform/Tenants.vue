<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import InputError from '@/components/InputError.vue';
import TenantFields from '@/components/platform/TenantFields.vue';
import type { TenantOptions } from '@/components/platform/TenantFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { index as tenantsIndex, show } from '@/routes/platform/tenants';

type TenantRow = {
    id: string;
    name: string;
    plan: string;
    status: string;
    users_count: number;
    accessible: boolean;
    trial_ends_at: string | null;
    subscription_ends_at: string | null;
    created_at: string | null;
};

defineProps<{
    tenants: TenantRow[];
    options: TenantOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Acenteler', href: tenantsIndex() }],
    },
});

const statusLabels: Record<string, string> = {
    trial: 'Deneme',
    active: 'Aktif',
    suspended: 'Askıda',
};

const createOpen = ref(false);
</script>

<template>
    <Head title="Acenteler" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <Card>
            <CardHeader class="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>Acenteler</CardTitle>
                    <CardDescription>
                        Sistemi kullanan tüm acenteler ve paketleri
                    </CardDescription>
                </div>
                <Button @click="createOpen = true">
                    <Building2 /> Yeni acente
                </Button>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <p
                    v-if="tenants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Henüz acente yok.
                </p>
                <table v-else class="w-full text-sm whitespace-nowrap">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 font-medium">Acente</th>
                            <th class="py-2 font-medium">Paket</th>
                            <th class="py-2 font-medium">Durum</th>
                            <th class="py-2 font-medium">Bitiş</th>
                            <th class="py-2 text-right font-medium">
                                Aktif kullanıcı
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2 font-medium">
                                <Link
                                    :href="show(tenant.id)"
                                    class="hover:underline"
                                >
                                    {{ tenant.name }}
                                </Link>
                            </td>
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
                                    <template
                                        v-if="
                                            !tenant.accessible &&
                                            tenant.status !== 'suspended'
                                        "
                                    >
                                        (süresi doldu)
                                    </template>
                                </Badge>
                            </td>
                            <td class="py-2 text-muted-foreground">
                                {{
                                    tenant.status === 'trial'
                                        ? formatDate(tenant.trial_ends_at)
                                        : formatDate(
                                              tenant.subscription_ends_at,
                                          )
                                }}
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{ tenant.users_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <Form
                v-bind="TenantController.store.form()"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="createOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>Yeni acente</DialogTitle>
                    <DialogDescription>
                        Acente ve ilk yönetici hesabı birlikte oluşturulur.
                        Yöneticinin geçici şifresi bir sonraki ekranda
                        gösterilir.
                    </DialogDescription>
                </DialogHeader>

                <TenantFields
                    :options="options"
                    :values="{
                        status: 'trial',
                        default_currency: 'USD',
                        trial_ends_at: new Date(Date.now() + 14 * 864e5)
                            .toISOString()
                            .slice(0, 10),
                    }"
                    :errors="errors"
                />

                <div class="grid gap-4 border-t pt-4 sm:grid-cols-2">
                    <p class="text-sm font-medium sm:col-span-2">
                        İlk yönetici hesabı
                    </p>
                    <div class="grid gap-2">
                        <Label for="admin_name">Ad Soyad *</Label>
                        <Input id="admin_name" name="admin_name" required />
                        <InputError :message="errors.admin_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="admin_email">E-posta *</Label>
                        <Input
                            id="admin_email"
                            name="admin_email"
                            type="email"
                            required
                        />
                        <InputError :message="errors.admin_email" />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="createOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">
                        Acenteyi oluştur
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
