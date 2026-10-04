<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    RotateCcw,
    Upload,
    UserCheck,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PersonImportController from '@/actions/App/Http/Controllers/PersonImportController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { selectClass } from '@/lib/formClasses';
import { show as importPage, template } from '@/routes/person-import';
import { index } from '@/routes/persons';
import type { Option } from '@/types/person';

type PreviewRow = {
    line: number;
    name: string;
    status: 'yeni' | 'mevcut' | 'hata';
    errors: string[];
    person_id: string | null;
    summary: {
        gender: string | null;
        birth_date: string | null;
        national_id: string | null;
        passport_no: string | null;
        phone: string | null;
        room_type: string | null;
        price: string | number | null;
    };
};

const props = defineProps<{
    token: string | null;
    preview: {
        rows: PreviewRow[];
        unknown_headers: string[];
        file_name: string | null;
    } | null;
    expired: boolean;
    tours: {
        id: string;
        name: string;
        default_price: string | null;
        currency: string;
        groups: { id: string; name: string }[];
    }[];
    statuses: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Yolcular', href: index() },
            { title: "Excel'den aktar", href: importPage() },
        ],
    },
});

const statusInfo = {
    yeni: { label: 'Yeni', variant: 'success' as const },
    mevcut: { label: 'Zaten kayıtlı', variant: 'secondary' as const },
    hata: { label: 'Hatalı', variant: 'danger' as const },
};

const counts = computed(() => {
    const rows = props.preview?.rows ?? [];

    return {
        yeni: rows.filter((r) => r.status === 'yeni').length,
        mevcut: rows.filter((r) => r.status === 'mevcut').length,
        hata: rows.filter((r) => r.status === 'hata').length,
    };
});

const filter = ref<'all' | PreviewRow['status']>(
    counts.value.hata > 0 ? 'hata' : 'all',
);
const visibleRows = computed(() =>
    (props.preview?.rows ?? []).filter(
        (r) => filter.value === 'all' || r.status === filter.value,
    ),
);

// Tura da kaydet (isteğe bağlı)
const withTour = ref(false);
const tourId = ref('');
const groupId = ref('');
const status = ref('on_kayit');
const price = ref('');
const selectedTour = computed(() =>
    props.tours.find((t) => t.id === tourId.value),
);
watch(tourId, () => {
    groupId.value =
        selectedTour.value?.groups.length === 1
            ? selectedTour.value.groups[0].id
            : '';
});

const willImport = computed(
    () =>
        counts.value.yeni +
        (withTour.value && tourId.value ? counts.value.mevcut : 0),
);

const confirming = ref(false);

function confirmImport(): void {
    confirming.value = true;
    router.post(
        PersonImportController.store.url(),
        {
            token: props.token,
            tour_id: withTour.value && tourId.value ? tourId.value : null,
            group_id: withTour.value && groupId.value ? groupId.value : null,
            status: status.value,
            price: withTour.value && price.value !== '' ? price.value : null,
        },
        { onFinish: () => (confirming.value = false) },
    );
}

const genderLabel = (g: string | null) =>
    g === 'erkek' ? 'Erkek' : g === 'kadin' ? 'Kadın' : (g ?? '—');
</script>

<template>
    <Head title="Excel'den yolcu aktar" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Excel'den yolcu aktar
            </h1>
            <p class="text-sm text-muted-foreground">
                Yolcu listenizi tek seferde ekleyin. Yükledikten sonra her satır
                kontrol edilir; siz onaylamadan hiçbir şey kaydedilmez.
            </p>
        </div>

        <p
            v-if="expired"
            class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
        >
            Önizlemenin süresi doldu (30 dakika). Dosyayı tekrar yükleyin.
        </p>

        <!-- 1. Yükleme -->
        <div v-if="!preview" class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-muted text-xs"
                            >1</span
                        >
                        Şablonu indirin
                    </CardTitle>
                    <CardDescription>
                        Sütun başlıkları hazır Excel dosyası. Kendi listeniz
                        varsa başlıkları şablondaki gibi yapmanız yeterli;
                        sıraları önemli değil.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <Button variant="outline" as-child>
                        <a :href="template.url()"
                            ><Download /> Şablonu indir (.xlsx)</a
                        >
                    </Button>
                    <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>Zorunlu: <strong>Ad, Soyad, Cinsiyet</strong></li>
                        <li>Tarihler GG.AA.YYYY (ör. 15.03.1965)</li>
                        <li>T.C. Kimlik No doğruluğu kontrol edilir</li>
                        <li>
                            Sistemde olan kişiler (aynı T.C. / pasaport) tekrar
                            eklenmez
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <span
                            class="flex size-6 items-center justify-center rounded-full bg-muted text-xs"
                            >2</span
                        >
                        Dosyayı yükleyin
                    </CardTitle>
                    <CardDescription
                        >Excel (.xlsx, .xls) veya CSV; en fazla 1000 yolcu, 5
                        MB.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="PersonImportController.upload.form()"
                        class="space-y-3"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="import-file">Dosya</Label>
                            <Input
                                id="import-file"
                                name="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                required
                            />
                            <InputError :message="errors.file" />
                        </div>
                        <Button type="submit" :disabled="processing">
                            <Upload />
                            {{
                                processing
                                    ? 'Kontrol ediliyor…'
                                    : 'Yükle ve kontrol et'
                            }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>

        <!-- 2. Önizleme -->
        <template v-else>
            <Card>
                <CardHeader
                    class="flex flex-row flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <FileSpreadsheet class="size-4" />
                            {{ preview.file_name ?? 'Yüklenen dosya' }}
                        </CardTitle>
                        <CardDescription
                            >{{ preview.rows.length }} satır
                            okundu.</CardDescription
                        >
                    </div>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="importPage()"
                            ><RotateCcw /> Başka dosya yükle</Link
                        >
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            :variant="filter === 'all' ? 'default' : 'outline'"
                            @click="filter = 'all'"
                        >
                            Tümü ({{ preview.rows.length }})
                        </Button>
                        <Button
                            size="sm"
                            :variant="filter === 'yeni' ? 'default' : 'outline'"
                            @click="filter = 'yeni'"
                        >
                            <CheckCircle2 /> Yeni ({{ counts.yeni }})
                        </Button>
                        <Button
                            size="sm"
                            :variant="
                                filter === 'mevcut' ? 'default' : 'outline'
                            "
                            @click="filter = 'mevcut'"
                        >
                            <UserCheck /> Zaten kayıtlı ({{ counts.mevcut }})
                        </Button>
                        <Button
                            size="sm"
                            :variant="filter === 'hata' ? 'default' : 'outline'"
                            @click="filter = 'hata'"
                        >
                            <XCircle /> Hatalı ({{ counts.hata }})
                        </Button>
                    </div>
                    <p
                        v-if="counts.hata > 0"
                        class="flex items-start gap-2 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        Hatalı satırlar aktarılmaz. İsterseniz Excel'de düzeltip
                        dosyayı tekrar yükleyin; isterseniz geçerli satırları
                        şimdi aktarın.
                    </p>
                    <p
                        v-if="preview.unknown_headers.length"
                        class="text-xs text-muted-foreground"
                    >
                        Tanınmayan sütunlar atlandı:
                        {{ preview.unknown_headers.join(', ') }}
                    </p>
                </CardContent>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Satır</th>
                                <th class="px-3 py-2 font-medium">Yolcu</th>
                                <th class="px-3 py-2 font-medium">Durum</th>
                                <th class="px-3 py-2 font-medium">Bilgiler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in visibleRows"
                                :key="row.line"
                                class="border-t align-top"
                            >
                                <td
                                    class="px-3 py-2 text-muted-foreground tabular-nums"
                                >
                                    {{ row.line }}
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    {{ row.name }}
                                </td>
                                <td class="px-3 py-2">
                                    <Badge
                                        :variant="
                                            statusInfo[row.status].variant
                                        "
                                        >{{
                                            statusInfo[row.status].label
                                        }}</Badge
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <ul
                                        v-if="row.errors.length"
                                        class="list-disc space-y-0.5 pl-4 text-danger"
                                    >
                                        <li
                                            v-for="error in row.errors"
                                            :key="error"
                                        >
                                            {{ error }}
                                        </li>
                                    </ul>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            [
                                                genderLabel(row.summary.gender),
                                                row.summary.birth_date
                                                    ? formatDate(
                                                          row.summary
                                                              .birth_date,
                                                      )
                                                    : null,
                                                row.summary.national_id
                                                    ? `T.C. ${row.summary.national_id}`
                                                    : null,
                                                row.summary.passport_no
                                                    ? `Pasaport ${row.summary.passport_no}`
                                                    : null,
                                                row.summary.phone,
                                                row.summary.room_type
                                                    ? `Oda ${row.summary.room_type}`
                                                    : null,
                                                row.summary.price
                                                    ? `Ücret ${row.summary.price}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- 3. Onay (ve isteğe bağlı tur kaydı) -->
            <Card>
                <CardHeader>
                    <CardTitle>Aktar</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <label v-if="tours.length" class="flex items-center gap-2">
                        <input v-model="withTour" type="checkbox" />
                        Aynı anda bir tura da kaydet
                    </label>
                    <div
                        v-if="withTour"
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="grid gap-2">
                            <Label for="import-tour">Tur</Label>
                            <select
                                id="import-tour"
                                v-model="tourId"
                                :class="selectClass"
                            >
                                <option value="">— Seçin —</option>
                                <option
                                    v-for="tour in tours"
                                    :key="tour.id"
                                    :value="tour.id"
                                >
                                    {{ tour.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="import-group">Grup</Label>
                            <select
                                id="import-group"
                                v-model="groupId"
                                :class="selectClass"
                                :disabled="!selectedTour"
                            >
                                <option value="">— Grupsuz —</option>
                                <option
                                    v-for="group in selectedTour?.groups ?? []"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="import-status">Kayıt durumu</Label>
                            <select
                                id="import-status"
                                v-model="status"
                                :class="selectClass"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="import-price"
                                >Ücret ({{
                                    selectedTour?.currency ?? '—'
                                }})</Label
                            >
                            <Input
                                id="import-price"
                                v-model="price"
                                type="number"
                                min="0"
                                :placeholder="
                                    selectedTour?.default_price ??
                                    'Turun fiyatı'
                                "
                            />
                        </div>
                        <p
                            class="text-xs text-muted-foreground sm:col-span-2 lg:col-span-4"
                        >
                            Excel'de "Oda Tipi" ve "Ücret" sütunları doluysa
                            onlar kullanılır; ücret boşsa buraya yazdığınız, o
                            da boşsa turun fiyatı. Zaten turda olan kişi tekrar
                            kaydedilmez.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            :disabled="
                                willImport === 0 ||
                                confirming ||
                                (withTour && !tourId)
                            "
                            @click="confirmImport"
                        >
                            <CheckCircle2 />
                            {{
                                withTour && tourId
                                    ? `${willImport} yolcuyu aktar ve tura kaydet`
                                    : `${counts.yeni} yeni yolcuyu aktar`
                            }}
                        </Button>
                        <span v-if="counts.hata" class="text-muted-foreground">
                            {{ counts.hata }} hatalı satır atlanacak.
                        </span>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
