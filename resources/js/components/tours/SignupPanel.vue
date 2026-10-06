<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import SignupController from '@/actions/App/Http/Controllers/SignupController';
import type { SignupSummary } from '@/types/tour';

/**
 * Tur → Yolcular: telefonla ön kayıt linki (oluştur / kopyala / WhatsApp / yenile / kapat), "N kişi linki
 * açtı, M'i tamamladı" ve onay bekleyen başvurular (onayla → yolcu + ön kayıt; reddet → bilgiler silinir).
 */
const props = defineProps<{
    tourId: string;
    tourName: string;
    signup: SignupSummary;
}>();

const busy = ref<string | null>(null);
const options = (key: string) => ({
    preserveScroll: true,
    preserveState: true,
    onFinish: () => (busy.value = null),
    onStart: () => (busy.value = key),
    onError: (e: Record<string, string>) =>
        toast.error(Object.values(e)[0] ?? 'İşlem yapılamadı.'),
});

function createLink(): void {
    if (
        props.signup.link &&
        !confirm('Yeni link verilsin mi? Eski link artık açılmaz.')
    ) {
        return;
    }

    router.post(SignupController.link.url(props.tourId), {}, options('link'));
}

function closeLink(): void {
    if (confirm('Ön kayıt linki kapatılsın mı? Artık başvuru alınmaz.')) {
        router.delete(
            SignupController.close.url(props.tourId),
            options('link'),
        );
    }
}

async function copy(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Ön kayıt linki kopyalandı.');
    } catch {
        window.prompt('Linki kopyalayın:', url);
    }
}

const whatsapp = (url: string) =>
    `https://wa.me/?text=${encodeURIComponent(`${props.tourName} ön kaydı için pasaportunuzun fotoğrafını çekip bilgilerinizi buradan gönderebilirsiniz: ${url}`)}`;

function approve(id: string): void {
    router.post(SignupController.approve.url(id), {}, options(id));
}

function reject(id: string, name: string): void {
    if (
        confirm(
            `${name} başvurusu reddedilsin mi? Gönderdiği bilgiler silinir.`,
        )
    ) {
        router.post(SignupController.reject.url(id), {}, options(id));
    }
}

const date = (d: string | null) =>
    d ? new Date(`${d}T00:00:00`).toLocaleDateString('tr-TR') : '—';
</script>

<template>
    <div class="aitem signup-box">
        <span class="chip acc">Ön kayıt</span>
        <p>
            <template v-if="signup.link">
                Telefonla ön kayıt linki açık ·
                <b
                    >{{ signup.opened }} kişi linki açtı,
                    {{ signup.completed }}'i tamamladı</b
                >
            </template>
            <template v-else>
                Yolcular pasaportunun fotoğrafını çekip bilgilerini kendisi
                göndersin. Linki WhatsApp grubuna atın.
            </template>
        </p>
        <span style="display: flex; gap: 6px; flex-wrap: wrap">
            <template v-if="signup.link">
                <button
                    class="btn ghost sm"
                    type="button"
                    @click="copy(signup.link.url)"
                >
                    Kopyala
                </button>
                <a
                    class="btn ghost sm"
                    :href="whatsapp(signup.link.url)"
                    target="_blank"
                    rel="noopener"
                    >WhatsApp</a
                >
                <button
                    class="btn ghost sm"
                    type="button"
                    :disabled="busy === 'link'"
                    @click="createLink"
                >
                    Yenile
                </button>
                <button
                    class="btn ghost sm"
                    type="button"
                    :disabled="busy === 'link'"
                    @click="closeLink"
                >
                    Kapat
                </button>
            </template>
            <button
                v-else
                class="btn sm"
                type="button"
                :disabled="busy === 'link'"
                @click="createLink"
            >
                Link oluştur
            </button>
        </span>
    </div>

    <div v-if="signup.pending.length" class="tbl">
        <table>
            <thead>
                <tr>
                    <th>Başvuru ({{ signup.pending.length }} bekliyor)</th>
                    <th>Pasaport</th>
                    <th>Doğum</th>
                    <th>Telefon</th>
                    <th>İhtiyaç</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in signup.pending" :key="r.id">
                    <td>
                        <div class="person">
                            <span
                                class="av"
                                :class="r.gender === 'kadin' ? 'k' : 'e'"
                                >{{
                                    (r.first_name[0] ?? '') +
                                    (r.last_name[0] ?? '')
                                }}</span
                            >
                            <div>
                                {{ r.first_name }} {{ r.last_name
                                }}<small
                                    >{{
                                        r.read_from_passport
                                            ? 'Pasaporttan okundu'
                                            : 'Elle yazıldı'
                                    }}<template v-if="r.existing">
                                        · kayıtlı kişi, ona bağlanır</template
                                    ></small
                                >
                            </div>
                        </div>
                    </td>
                    <td>
                        {{ r.passport_no ?? '—' }}
                        <small style="color: var(--m-muted)">{{
                            r.passport_expiry_date?.slice(0, 7) ?? ''
                        }}</small>
                    </td>
                    <td>{{ date(r.birth_date) }}</td>
                    <td>
                        <a :href="`tel:${r.phone}`">{{ r.phone }}</a>
                    </td>
                    <td>
                        <span
                            v-for="n in r.needs"
                            :key="n"
                            class="tag"
                            style="margin-right: 4px"
                            >{{ n }}</span
                        >
                        <span v-if="!r.needs.length" class="lbl">—</span>
                    </td>
                    <td style="white-space: nowrap">
                        <button
                            class="btn sm"
                            type="button"
                            :disabled="busy === r.id"
                            @click="approve(r.id)"
                        >
                            Onayla
                        </button>
                        <button
                            class="btn ghost sm"
                            type="button"
                            :disabled="busy === r.id"
                            @click="
                                reject(r.id, `${r.first_name} ${r.last_name}`)
                            "
                        >
                            Reddet
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
