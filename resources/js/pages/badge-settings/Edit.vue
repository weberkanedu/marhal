<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { HeartPulse, QrCode, UserRound } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import BadgeSettingController from '@/actions/App/Http/Controllers/BadgeSettingController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit } from '@/routes/badge-settings';
import type { Option } from '@/types/person';

type Settings = {
    size: 'dikey' | 'yatay' | 'plastik';
    fields: string[];
    back_languages: string[];
    back_side: boolean;
    health_note: boolean;
};

const props = defineProps<{
    settings: Settings;
    sizes: Option<Settings['size']>[];
    agency: { name: string | null; phone: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: edit() },
            { title: 'Yaka kartı', href: edit() },
        ],
    },
});

const form = reactive<Settings>({
    ...props.settings,
    fields: [...props.settings.fields],
    back_languages: [...props.settings.back_languages],
});
const saving = ref(false);

const fieldOptions = [
    { value: 'photo', label: 'Fotoğraf' },
    { value: 'hotels', label: 'Oteller ve oda no' },
    { value: 'bus', label: 'Otobüs ve koltuk' },
    { value: 'guide', label: 'Rehber adı ve telefonu' },
    { value: 'qr', label: 'QR kod (yolcu, acente, acil telefon)' },
];
const languageOptions = [
    { value: 'tr', label: 'Türkçe — KAYBOLURSANIZ' },
    { value: 'en', label: 'İngilizce — IF LOST' },
    { value: 'ar', label: 'Arapça — إذا ضللت الطريق' },
];

function toggle(list: string[], value: string): void {
    const i = list.indexOf(value);

    if (i === -1) {
        list.push(value);
    } else {
        list.splice(i, 1);
    }
}

const has = (field: string) => form.fields.includes(field);

// Önizleme oranı (mm): kart çizimi gerçeğe yakın görünsün.
const ratio = computed(
    () =>
        ({ dikey: '102 / 140', yatay: '90 / 64', plastik: '86 / 54' })[
            form.size
        ],
);
const previewWidth = computed(
    () => ({ dikey: '15rem', yatay: '21rem', plastik: '19rem' })[form.size],
);

function save(): void {
    saving.value = true;
    router.put(BadgeSettingController.update.url(), form, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? 'Kaydedilemedi.'),
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <Head title="Yaka kartı" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight">Yaka kartı</h2>
            <p class="text-sm text-muted-foreground">
                Tur sayfasından basılan bütün yaka kartları bu ayarla üretilir.
                Kartın bandı grubun rengindedir (grup ayarından seçilir).
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Boy</CardTitle>
                        <CardDescription>
                            Hepsi A4 kâğıda dizilir, kesik çizgiden kesilir.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="size in sizes"
                            :key="size.value"
                            type="button"
                            class="rounded-xl border p-3 text-left text-sm transition"
                            :class="
                                form.size === size.value
                                    ? 'border-primary bg-accent font-semibold'
                                    : 'hover:bg-muted'
                            "
                            :aria-pressed="form.size === size.value"
                            @click="form.size = size.value"
                        >
                            {{ size.label }}
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ön yüz</CardTitle>
                        <CardDescription>
                            Ad soyad, grup ve acil telefon her zaman yazar.
                            Kimlik ve pasaport no hiçbir zaman yazmaz.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="field in fieldOptions"
                            :key="field.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-(--primary)"
                                :checked="has(field.value)"
                                @change="toggle(form.fields, field.value)"
                            />
                            {{ field.label }}
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Arka yüz</CardTitle>
                        <CardDescription>
                            "Kaybolursanız" yazısı, acil telefon ve otel
                            adresleri. Çift taraflı baskıda kartın arkası doğru
                            yere düşer (uzun kenardan çevirin).
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 text-sm">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.back_side"
                                type="checkbox"
                                class="size-4 accent-(--primary)"
                            />
                            Arka yüzü bas
                        </label>
                        <div
                            v-if="form.back_side"
                            class="grid gap-2 sm:grid-cols-3"
                        >
                            <label
                                v-for="lang in languageOptions"
                                :key="lang.value"
                                class="flex items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 accent-(--primary)"
                                    :checked="
                                        form.back_languages.includes(lang.value)
                                    "
                                    @change="
                                        toggle(form.back_languages, lang.value)
                                    "
                                />
                                {{ lang.label }}
                            </label>
                        </div>
                        <label
                            v-if="form.back_side"
                            class="flex items-start gap-2 rounded-lg border border-dashed p-3"
                        >
                            <input
                                v-model="form.health_note"
                                type="checkbox"
                                class="mt-0.5 size-4 accent-(--primary)"
                            />
                            <span>
                                <span
                                    class="flex items-center gap-1 font-medium"
                                >
                                    <HeartPulse class="size-4" /> Sağlık notu
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    Yalnız sağlık verisi için açık rızası olan
                                    yolcularda, ihtiyaçları ve notları arka yüze
                                    yazılır (kartı bulan kişi görür).
                                </span>
                            </span>
                        </label>
                    </CardContent>
                </Card>

                <div>
                    <Button :disabled="saving" @click="save">Kaydet</Button>
                </div>
            </div>

            <!-- Canlı önizleme -->
            <div
                class="flex flex-col items-center gap-3 lg:sticky lg:top-2 lg:self-start"
            >
                <p class="text-xs text-muted-foreground">Önizleme</p>
                <div
                    class="flex flex-col overflow-hidden rounded-md border bg-white text-[#111] shadow-lg"
                    :style="{ aspectRatio: ratio, width: previewWidth }"
                >
                    <div
                        class="flex items-start justify-between bg-[#0f6b4e] px-3 py-1.5 text-white"
                    >
                        <div>
                            <div class="text-xs font-bold">
                                {{ agency.name ?? 'Acente' }}
                            </div>
                            <div class="text-[10px] tracking-wide uppercase">
                                A Grubu
                            </div>
                        </div>
                        <div class="text-right text-[9px]">
                            Umre Turu<br />25.10 – 08.11
                        </div>
                    </div>
                    <div class="flex gap-2 p-2.5">
                        <div
                            v-if="has('photo')"
                            class="grid aspect-[3/4] w-14 shrink-0 place-items-center border bg-[#f4f4f4] text-[#999]"
                        >
                            <UserRound class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1 text-[10px] leading-tight">
                            <div class="text-sm font-bold">AYŞE</div>
                            <div class="text-xs font-bold">YILMAZ</div>
                            <div v-if="has('guide')" class="mt-1">
                                <span class="text-[#666]">Rehber:</span> Ahmet
                                Bey 0555 …
                            </div>
                            <template v-if="has('hotels')">
                                <div class="mt-0.5">
                                    <span class="text-[#666]">Mekke:</span>
                                    Swissôtel · <b>Oda 501</b>
                                </div>
                                <div class="mt-0.5">
                                    <span class="text-[#666]">Medine:</span>
                                    Pullman · <b>Oda 302</b>
                                </div>
                            </template>
                            <div v-if="has('bus')" class="mt-0.5">
                                <span class="text-[#666]">Otobüs:</span> 1.
                                Otobüs · <b>Koltuk 7</b>
                            </div>
                        </div>
                        <QrCode
                            v-if="has('qr') && form.size !== 'plastik'"
                            class="size-10 shrink-0"
                        />
                    </div>
                    <div
                        class="mt-auto flex justify-between border-t-2 border-[#0f6b4e] px-3 py-1 text-[9px] text-[#555]"
                    >
                        <span
                            >Acil: <b>{{ agency.phone ?? '—' }}</b></span
                        >
                        <span>No 4A2C9E</span>
                    </div>
                </div>
                <p
                    v-if="form.back_side"
                    class="max-w-72 text-center text-xs text-muted-foreground"
                >
                    Arka yüz:
                    {{
                        form.back_languages
                            .map(
                                (l) =>
                                    ({
                                        tr: 'Türkçe',
                                        en: 'İngilizce',
                                        ar: 'Arapça',
                                    })[l],
                            )
                            .join(', ')
                    }}
                    · acil telefon · otel adresleri<template
                        v-if="form.health_note"
                    >
                        · sağlık notu (rızalı yolcular)</template
                    >
                </p>
            </div>
        </div>
    </div>
</template>
