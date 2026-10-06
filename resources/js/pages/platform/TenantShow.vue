<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import TenantController from '@/actions/App/Http/Controllers/Platform/TenantController';
import TenantFields from '@/components/platform/TenantFields.vue';
import type {
    TenantOptions,
    TenantValues,
} from '@/components/platform/TenantFields.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { index as tenantsIndex } from '@/routes/platform/tenants';

type FeatureRow = {
    key: string;
    label: string;
    plan: boolean;
    override: boolean | null;
    effective: boolean;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    last_login_at: string | null;
};

const props = defineProps<{
    tenant: TenantValues & {
        id: string;
        accessible: boolean;
        active_tours: number;
        passengers_used: number;
        passenger_limit: number | null;
    };
    features: FeatureRow[];
    users: UserRow[];
    options: TenantOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Acenteler', href: tenantsIndex() }],
    },
});

const roleLabels: Record<string, string> = {
    admin: 'Yönetici',
    operasyon: 'Operasyon',
    rehber: 'Rehber',
};

function setFeature(feature: FeatureRow, value: string): void {
    router.put(
        TenantController.updateFeature.url(props.tenant.id),
        {
            feature: feature.key,
            enabled: value === 'default' ? null : value === 'on',
        },
        { preserveScroll: true },
    );
}

function resetPassword(user: UserRow): void {
    if (confirm(`${user.name} için yeni geçici şifre oluşturulsun mu?`)) {
        router.post(
            TenantController.resetUserPassword.url({
                tenant: props.tenant.id,
                user: user.id,
            }),
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head :title="tenant.name ?? 'Acente'" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ tenant.name }}
            </h1>
            <Badge :variant="tenant.accessible ? 'secondary' : 'destructive'">
                {{ tenant.accessible ? 'Erişim açık' : 'Erişim kapalı' }}
            </Badge>
            <span class="text-sm text-muted-foreground">
                {{ tenant.active_tours }} aktif tur · Yolcu
                {{ tenant.passengers_used }}
                <template v-if="tenant.passenger_limit !== null"
                    >/ {{ tenant.passenger_limit }}</template
                >
                (bu abonelik yılı)
            </span>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Acente ve abonelik</CardTitle>
                <CardDescription>
                    Durum "Askıda" veya süre dolmuşsa acente kullanıcıları giriş
                    yapsa da ekranlara erişemez.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="TenantController.update.form(tenant.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                >
                    <TenantFields
                        :options="options"
                        :values="tenant"
                        :errors="errors"
                    />
                    <Button type="submit" :disabled="processing">Kaydet</Button>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Modüller</CardTitle>
                <CardDescription>
                    Varsayılan olarak paket belirler. Tek bir acenteye özel açıp
                    kapatmak için "Açık" / "Kapalı" seçin.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 font-medium">Modül</th>
                            <th class="py-2 font-medium">Pakette</th>
                            <th class="py-2 font-medium">Ayar</th>
                            <th class="py-2 font-medium">Sonuç</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="feature in features"
                            :key="feature.key"
                            class="border-b last:border-0"
                        >
                            <td class="py-2">{{ feature.label }}</td>
                            <td class="py-2 text-muted-foreground">
                                {{ feature.plan ? 'Var' : 'Yok' }}
                            </td>
                            <td class="py-2">
                                <select
                                    class="rounded-md border bg-transparent px-2 py-1 text-sm"
                                    :value="
                                        feature.override === null
                                            ? 'default'
                                            : feature.override
                                              ? 'on'
                                              : 'off'
                                    "
                                    @change="
                                        setFeature(
                                            feature,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="default">
                                        Paket varsayılanı
                                    </option>
                                    <option value="on">Açık</option>
                                    <option value="off">Kapalı</option>
                                </select>
                            </td>
                            <td class="py-2">
                                <Badge
                                    :variant="
                                        feature.effective
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    {{ feature.effective ? 'Açık' : 'Kapalı' }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Kullanıcılar</CardTitle>
                <CardDescription>
                    Destek için: acente yöneticisi şifresini unutursa buradan
                    yeni geçici şifre verilebilir.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="divide-y text-sm">
                    <li
                        v-for="user in users"
                        :key="user.id"
                        class="flex items-center justify-between py-2"
                        :class="{ 'opacity-50': !user.is_active }"
                    >
                        <span>
                            <span class="font-medium">{{ user.name }}</span>
                            <span class="ml-2 text-muted-foreground">{{
                                user.email
                            }}</span>
                            <Badge variant="outline" class="ml-2">
                                {{ roleLabels[user.role] ?? user.role }}
                            </Badge>
                        </span>
                        <span
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            {{
                                user.last_login_at
                                    ? `Son giriş ${formatDate(user.last_login_at)}`
                                    : 'Hiç giriş yapmadı'
                            }}
                            <Button
                                v-if="user.is_active"
                                variant="ghost"
                                size="icon-sm"
                                title="Yeni geçici şifre"
                                @click="resetPassword(user)"
                            >
                                <KeyRound />
                            </Button>
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
