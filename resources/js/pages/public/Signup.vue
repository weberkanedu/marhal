<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicSignupController from '@/actions/App/Http/Controllers/PublicSignupController';
import { readPassport } from '@/lib/passportReader';
import type { PassportFields } from '@/lib/passportReader';

/**
 * Telefonla ön kayıt — tasarımdaki dört adımın gerçek hâli: 1) pasaportu okut (fotoğraf; tarayıcıda
 * okunur, sunucuya gitmez), 2) bilgiler doldu, kontrol et + telefon, 3) sağlık ve ihtiyaçlar (ayrı rıza),
 * 4) teşekkürler. Başvuru acentenin onayına düşer.
 */
const props = defineProps<{
    token: string;
    tour: { name: string; start: string; end: string };
    agency: { name: string; phone: string | null };
    needTypes: { id: string; name: string }[];
}>();

const step = ref(0);
const scanning = ref(false);
const progress = ref(0);
const readOk = ref<boolean | null>(null);
const fromPassport = ref(false);
const sending = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref<PassportFields & { phone: string; email: string }>({
    first_name: '',
    last_name: '',
    gender: '',
    birth_date: '',
    nationality: 'TR',
    passport_no: '',
    passport_expiry_date: '',
    phone: '',
    email: '',
});
const needs = ref<Set<string>>(new Set());
const healthConsent = ref(false);
const kvkk = ref(false);

const fileInput = ref<HTMLInputElement | null>(null);

async function onPhoto(e: Event): Promise<void> {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    scanning.value = true;
    progress.value = 0;
    readOk.value = null;

    try {
        const result = await Promise.race([
            readPassport(file, (p) => (progress.value = Math.round(p * 100))),
            // Okuma uzarsa (eski telefon, kötü bağlantı) elle doldurmaya geçilir.
            new Promise<null>((resolve) =>
                setTimeout(() => resolve(null), 60_000),
            ),
        ]);

        if (result) {
            form.value = { ...form.value, ...result.fields };
            readOk.value = result.valid;
            fromPassport.value = true;
        } else {
            readOk.value = false;
        }
    } catch {
        readOk.value = false;
    } finally {
        scanning.value = false;
        (e.target as HTMLInputElement).value = '';
        step.value = 1;
    }
}

function manual(): void {
    fromPassport.value = false;
    readOk.value = null;
    step.value = 1;
}

const fields: { key: keyof PassportFields; label: string; type?: string }[] = [
    { key: 'last_name', label: 'Soyad' },
    { key: 'first_name', label: 'Ad' },
    { key: 'passport_no', label: 'Pasaport no' },
    { key: 'birth_date', label: 'Doğum', type: 'date' },
    { key: 'passport_expiry_date', label: 'Geçerlilik', type: 'date' },
    { key: 'nationality', label: 'Uyruk' },
];

const canContinue = computed(
    () =>
        form.value.first_name.trim() &&
        form.value.last_name.trim() &&
        form.value.gender &&
        form.value.phone.trim().length >= 7,
);

function toggleNeed(id: string): void {
    const next = new Set(needs.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    needs.value = next;
}

function send(): void {
    errors.value = {};
    sending.value = true;
    router.post(
        PublicSignupController.store.url(props.token),
        {
            ...form.value,
            nationality: form.value.nationality.toUpperCase().slice(0, 2),
            birth_date: form.value.birth_date || null,
            passport_no: form.value.passport_no || null,
            passport_expiry_date: form.value.passport_expiry_date || null,
            email: form.value.email || null,
            needs: [...needs.value],
            health_consent: healthConsent.value,
            kvkk: kvkk.value,
            read_from_passport: fromPassport.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (step.value = 3),
            onError: (e) => {
                errors.value = e;
                // Kimlik alanlarında hata varsa o adıma dön.
                if (
                    Object.keys(e).some(
                        (k) => !['kvkk', 'health_consent', 'needs'].includes(k),
                    )
                ) {
                    step.value = 1;
                }
            },
            onFinish: () => (sending.value = false),
        },
    );
}

const shortDate = (d: string) =>
    new Date(`${d}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
// "AYSE" → "Ayse Hanım" (pasaporttaki büyük harfli ad).
const salutation = computed(() => {
    const first = (form.value.first_name.split(' ')[0] ?? '').toLocaleLowerCase(
        'tr',
    );
    const name = first.charAt(0).toLocaleUpperCase('tr') + first.slice(1);
    const title =
        form.value.gender === 'kadin'
            ? 'Hanım'
            : form.value.gender === 'erkek'
              ? 'Bey'
              : '';

    return `${name} ${title}`.trim();
});
</script>

<template>
    <Head :title="`Ön kayıt · ${tour.name}`" />

    <div class="mx fam-page">
        <div class="screen">
            <small class="lbl"
                >{{ agency.name }} · {{ tour.name }} ·
                {{ shortDate(tour.start) }} – {{ shortDate(tour.end) }}</small
            >
            <div class="steps">
                <i v-for="i in 4" :key="i" :class="{ on: i - 1 <= step }" />
            </div>

            <!-- 1 · Pasaportu okut -->
            <template v-if="step === 0">
                <h5>Pasaportunu okut</h5>
                <div class="pass">
                    <span v-if="scanning" class="laser" />
                    <b>TÜRKİYE CUMHURİYETİ<br />PASAPORT</b>
                    <div class="mrz">
                        P&lt;TURYILMAZ&lt;&lt;AYSE&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;<br />U28401234&lt;8TUR7104123F3104157&lt;&lt;&lt;&lt;&lt;&lt;
                    </div>
                </div>
                <small class="lbl">{{
                    scanning
                        ? `Alttaki iki satır okunuyor… %${progress}`
                        : 'Pasaportun fotoğraflı sayfasını, alttaki iki satır net görünecek şekilde çek. Fotoğraf telefonundan çıkmaz.'
                }}</small>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    hidden
                    @change="onPhoto"
                />
                <button
                    class="btn"
                    type="button"
                    :disabled="scanning"
                    @click="fileInput?.click()"
                >
                    Fotoğrafını çek
                </button>
                <button
                    class="btn ghost"
                    type="button"
                    :disabled="scanning"
                    @click="manual"
                >
                    Elle doldur
                </button>
            </template>

            <!-- 2 · Bilgiler -->
            <template v-else-if="step === 1">
                <h5>
                    {{
                        fromPassport
                            ? 'Bilgilerin dolduruldu'
                            : 'Bilgilerini yaz'
                    }}
                </h5>
                <small
                    v-if="fromPassport"
                    class="lbl"
                    :style="readOk ? '' : 'color: var(--m-warn)'"
                    >{{
                        readOk
                            ? 'Pasaporttan okundu. Yine de kontrol et.'
                            : 'Bazı harfler net okunamadı; lütfen her alanı kontrol edip düzelt.'
                    }}</small
                >
                <label
                    v-for="(f, i) in fields"
                    :key="f.key"
                    class="fld"
                    :class="{ auto: fromPassport }"
                    :style="`animation-delay: ${i * 90}ms`"
                >
                    <span>{{ f.label }}</span>
                    <input
                        v-model="form[f.key]"
                        class="mini-in"
                        :type="f.type ?? 'text'"
                        :aria-label="f.label"
                        style="text-align: right; max-width: 60%"
                    />
                </label>
                <label
                    class="fld"
                    :class="{ auto: fromPassport && !!form.gender }"
                >
                    <span>Cinsiyet</span>
                    <select
                        v-model="form.gender"
                        class="mini-in"
                        aria-label="Cinsiyet"
                    >
                        <option value="" disabled>Seç</option>
                        <option value="kadin">Kadın</option>
                        <option value="erkek">Erkek</option>
                    </select>
                </label>
                <label class="fld">
                    <span>Telefon *</span>
                    <input
                        v-model="form.phone"
                        class="mini-in"
                        type="tel"
                        inputmode="tel"
                        placeholder="05xx xxx xx xx"
                        style="text-align: right; max-width: 60%"
                    />
                </label>
                <small
                    v-for="(m, k) in errors"
                    :key="k"
                    class="lbl"
                    style="color: var(--m-danger)"
                    >{{ m }}</small
                >
                <button
                    class="btn"
                    type="button"
                    :disabled="!canContinue"
                    @click="step = 2"
                >
                    Doğru, devam
                </button>
                <button class="btn ghost" type="button" @click="step = 0">
                    Pasaportu yeniden okut
                </button>
            </template>

            <!-- 3 · Sağlık ve ihtiyaçlar -->
            <template v-else-if="step === 2">
                <h5>Sağlık ve ihtiyaçlar</h5>
                <small class="lbl"
                    >Otel ve araçta sana uygun yeri ayırmak için. Yoksa boş
                    bırak.</small
                >
                <div
                    v-for="n in needTypes"
                    :key="n.id"
                    class="ck"
                    :class="{ on: needs.has(n.id) }"
                    role="checkbox"
                    :aria-checked="needs.has(n.id)"
                    tabindex="0"
                    @click="toggleNeed(n.id)"
                >
                    <span class="bx">{{ needs.has(n.id) ? '✓' : '' }}</span
                    >{{ n.name }}
                </div>
                <div
                    v-if="needs.size"
                    class="ck"
                    :class="{ on: healthConsent }"
                    role="checkbox"
                    :aria-checked="healthConsent"
                    tabindex="0"
                    @click="healthConsent = !healthConsent"
                >
                    <span class="bx">{{ healthConsent ? '✓' : '' }}</span
                    >Sağlık bilgimin {{ agency.name }} tarafından yalnız
                    yerleşim ve yolculuk için kullanılmasına açık rıza
                    veriyorum.
                </div>
                <div
                    class="ck"
                    :class="{ on: kvkk }"
                    role="checkbox"
                    :aria-checked="kvkk"
                    tabindex="0"
                    @click="kvkk = !kvkk"
                >
                    <span class="bx">{{ kvkk ? '✓' : '' }}</span
                    >Kişisel verilerimin tur kaydı için işlenmesine ilişkin
                    aydınlatma metnini okudum.
                </div>
                <small
                    v-for="(m, k) in errors"
                    :key="k"
                    class="lbl"
                    style="color: var(--m-danger)"
                    >{{ m }}</small
                >
                <button
                    class="btn"
                    type="button"
                    :disabled="
                        !kvkk || (needs.size > 0 && !healthConsent) || sending
                    "
                    @click="send"
                >
                    <template v-if="sending"
                        ><span class="spin" />Gönderiliyor…</template
                    >
                    <template v-else>Gönder</template>
                </button>
                <button class="btn ghost" type="button" @click="step = 1">
                    Geri
                </button>
            </template>

            <!-- 4 · Teşekkürler -->
            <template v-else>
                <h5>Teşekkürler{{ salutation ? `, ${salutation}` : '' }}</h5>
                <div class="box">
                    <b>Kaydın acenteye ulaştı ✓</b>
                    <small
                        >Bilgilerin {{ agency.name }} ekibine iletildi. Kontrol
                        edip seninle iletişime geçecekler.</small
                    >
                </div>
                <div v-if="agency.phone" class="box">
                    <small>Sorun olursa</small>
                    <b
                        ><a :href="`tel:${agency.phone}`">{{
                            agency.phone
                        }}</a></b
                    >
                </div>
            </template>
        </div>
    </div>
</template>
