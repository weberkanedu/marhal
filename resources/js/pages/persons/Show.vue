<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Camera,
    Eye,
    EyeOff,
    Pencil,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import RelationsCard from '@/components/persons/RelationsCard.vue';
import type { PersonRelationRow } from '@/components/persons/RelationsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ageFrom, formatDate, formatMoney } from '@/lib/format';
import { destroy, edit, index, reveal } from '@/routes/persons';
import { show as showRegistration } from '@/routes/registrations';
import type { Option, PersonDetail } from '@/types/person';

type RegistrationRow = {
    id: string;
    tour: { id: string; name: string; start_date: string; end_date: string };
    group: string | null;
    status: string;
    currency: string;
    net_price: string;
    balance: string;
};

const props = defineProps<{
    person: PersonDetail;
    registrations: RegistrationRow[];
    relations: PersonRelationRow[];
    relationOptions: Option[];
    can: { update: boolean; delete: boolean; reveal: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Yolcular', href: index() }],
    },
});

const genderLabels: Record<string, string> = { erkek: 'Erkek', kadin: 'Kadın' };
const statusLabels: Record<string, string> = {
    on_kayit: 'Ön kayıt',
    kesin_kayit: 'Kesin kayıt',
    iptal: 'İptal',
};

const revealed = ref<{
    national_id: string | null;
    passport_no: string | null;
} | null>(null);
const revealing = ref(false);

async function toggleReveal(): Promise<void> {
    if (revealed.value) {
        revealed.value = null;

        return;
    }

    revealing.value = true;

    try {
        const token = decodeURIComponent(
            document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        );
        const response = await fetch(reveal.url(props.person.id), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            revealed.value = await response.json();
        }
    } finally {
        revealing.value = false;
    }
}

function confirmDelete(): void {
    if (
        confirm(
            `${props.person.full_name} silinsin mi? Bu işlem kayıt geçmişinde saklanır.`,
        )
    ) {
        router.delete(destroy.url(props.person.id));
    }
}
</script>

<template>
    <Head :title="person.full_name" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
        <!-- Başlık -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-20 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                >
                    <img
                        v-if="person.photo_url"
                        :src="person.photo_url"
                        :alt="person.full_name"
                        class="size-full object-cover"
                    />
                    <Camera v-else class="size-8 text-muted-foreground" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ person.full_name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ genderLabels[person.gender] }}
                        <template v-if="person.birth_date">
                            · {{ ageFrom(person.birth_date) }} yaş
                        </template>
                        ·
                        {{
                            person.nationality === 'TR'
                                ? 'T.C.'
                                : 'Yabancı uyruklu'
                        }}
                    </p>
                    <p
                        v-if="person.kvkk_consent"
                        class="mt-1 flex items-center gap-1 text-xs text-success"
                    >
                        <ShieldCheck class="size-3" /> KVKK onayı alındı ({{
                            formatDate(person.kvkk_consent_at)
                        }})
                    </p>
                    <p
                        v-else
                        class="mt-1 flex items-center gap-1 text-xs text-warning"
                    >
                        <AlertTriangle class="size-3" /> KVKK onayı kaydedilmedi
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="edit(person.id)"><Pencil /> Düzenle</Link>
                </Button>
                <Button
                    v-if="can.delete"
                    variant="ghost"
                    class="text-destructive"
                    @click="confirmDelete"
                >
                    <Trash2 /> Sil
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Kimlik ve pasaport</CardTitle>
                    <Button
                        v-if="can.reveal"
                        variant="ghost"
                        size="sm"
                        :disabled="revealing"
                        @click="toggleReveal"
                    >
                        <component :is="revealed ? EyeOff : Eye" />
                        {{ revealed ? 'Gizle' : 'Göster' }}
                    </Button>
                </CardHeader>
                <CardContent>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm"
                    >
                        <dt class="text-muted-foreground">T.C. Kimlik No</dt>
                        <dd class="font-mono">
                            {{
                                revealed?.national_id ??
                                person.masked_national_id ??
                                '—'
                            }}
                        </dd>
                        <dt class="text-muted-foreground">Pasaport No</dt>
                        <dd class="font-mono">
                            {{
                                revealed?.passport_no ??
                                person.masked_passport_no ??
                                '—'
                            }}
                        </dd>
                        <dt class="text-muted-foreground">Veriliş</dt>
                        <dd>{{ formatDate(person.passport_issue_date) }}</dd>
                        <dt class="text-muted-foreground">Geçerlilik</dt>
                        <dd>
                            {{ formatDate(person.passport_expiry_date) }}
                            <span
                                v-if="person.passport_expiring"
                                class="ml-1 inline-flex items-center gap-1 text-xs text-warning"
                            >
                                <AlertTriangle class="size-3" /> 6 aydan az
                                kaldı
                            </span>
                        </dd>
                        <dt class="text-muted-foreground">Doğum tarihi</dt>
                        <dd>{{ formatDate(person.birth_date) }}</dd>
                    </dl>
                    <p
                        v-if="revealed"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Bu görüntüleme erişim kayıtlarına yazıldı.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>İletişim</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm"
                    >
                        <dt class="text-muted-foreground">Telefon</dt>
                        <dd>{{ person.phone ?? '—' }}</dd>
                        <dt class="text-muted-foreground">E-posta</dt>
                        <dd>{{ person.email ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Adres</dt>
                        <dd class="whitespace-pre-line">
                            {{ person.address ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">Acil durum</dt>
                        <dd>
                            {{ person.emergency_contact_name ?? '—' }}
                            <span v-if="person.emergency_contact_phone">
                                · {{ person.emergency_contact_phone }}
                            </span>
                        </dd>
                    </dl>
                </CardContent>
            </Card>
        </div>

        <Card v-if="person.notes">
            <CardHeader>
                <CardTitle>Notlar</CardTitle>
            </CardHeader>
            <CardContent class="text-sm whitespace-pre-line">
                {{ person.notes }}
            </CardContent>
        </Card>

        <RelationsCard
            :person-id="person.id"
            :person-name="person.full_name"
            :relations="relations"
            :options="relationOptions"
            :can-update="can.update"
        />

        <Card>
            <CardHeader>
                <CardTitle>Tur kayıtları</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="registrations.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Henüz bir tura kaydı yok.
                </p>
                <table v-else class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr class="border-b">
                            <th class="py-2 font-medium">Tur</th>
                            <th class="py-2 font-medium">Grup</th>
                            <th class="py-2 font-medium">Durum</th>
                            <th class="py-2 text-right font-medium">Ücret</th>
                            <th class="py-2 text-right font-medium">Kalan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="registration in registrations"
                            :key="registration.id"
                            class="border-b last:border-0"
                        >
                            <td class="py-2">
                                <Link
                                    :href="showRegistration(registration.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ registration.tour.name }}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        formatDate(registration.tour.start_date)
                                    }}
                                </div>
                            </td>
                            <td class="py-2">
                                {{ registration.group ?? '—' }}
                            </td>
                            <td class="py-2">
                                <Badge
                                    :variant="
                                        registration.status === 'iptal'
                                            ? 'danger'
                                            : registration.status ===
                                                'kesin_kayit'
                                              ? 'success'
                                              : 'warning'
                                    "
                                >
                                    {{ statusLabels[registration.status] }}
                                </Badge>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                {{
                                    formatMoney(
                                        registration.net_price,
                                        registration.currency,
                                    )
                                }}
                            </td>
                            <td
                                class="py-2 text-right font-medium tabular-nums"
                                :class="
                                    Number(registration.balance) > 0
                                        ? 'text-warning'
                                        : 'text-success'
                                "
                            >
                                {{
                                    formatMoney(
                                        registration.balance,
                                        registration.currency,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
