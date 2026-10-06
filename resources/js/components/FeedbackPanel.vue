<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { mine as mineRoute, store } from '@/routes/feedback';

/**
 * "Görüşünü paylaş" — tasarımdaki sağ alttaki yuvarlak düğme ve açılan panel: konu, memnuniyet
 * yıldızı, mesaj, "bulunduğum ekranı ekle", "bana dönüş yapılsın"; gönderince takip numaralı
 * teşekkür ekranı. Ekran bilgisi yalnız adres yolu olarak gider (sorgu parametresi gönderilmez).
 * "Gönderdiklerim": kullanıcının kendi gönderdikleri, durumu ve platformun yanıtı; yeni yanıt gelince
 * düğmede nokta yanar, liste açılınca söner (kullanıcı kararı 2026-10-07).
 */
const page = usePage();

const TYPES = [
    { value: 'oneri', icon: '💡', label: 'Öneri' },
    { value: 'hata', icon: '🛠️', label: 'Sorun' },
    { value: 'soru', icon: '❓', label: 'Soru' },
    { value: 'begeni', icon: '💚', label: 'Teşekkür' },
] as const;
const RATE_TEXT = [
    'Bir yıldız seçin',
    'Hiç memnun değilim',
    'Geliştirilmeli',
    'İdare eder',
    'Memnunum',
    'Bayıldım',
];

const open = ref(false);
const step = ref<'form' | 'sending' | 'done'>('form');
const type = ref<(typeof TYPES)[number]['value']>('oneri');
const rate = ref(0);
const text = ref('');
const attach = ref(true);
const reply = ref(true);
const error = ref<string | null>(null);
const trackingNo = ref<number | null>(null);
const textarea = ref<HTMLTextAreaElement | null>(null);

type SentItem = {
    id: number;
    tracking: string;
    type_label: string;
    message: string;
    status: string;
    status_label: string;
    reply: string | null;
    replied_at: string | null;
    unseen: boolean;
    created_at: string;
};

const view = ref<'form' | 'mine'>('form');
const sent = ref<SentItem[] | null>(null);
const unread = computed(() => page.props.feedbackUnread ?? 0);

async function showMine(): Promise<void> {
    view.value = 'mine';
    sent.value = null;

    try {
        const res = await fetch(mineRoute.url(), {
            headers: { Accept: 'application/json' },
        });
        sent.value = res.ok ? ((await res.json()).items as SentItem[]) : [];
    } catch {
        sent.value = [];
    }

    // Yanıtlar görüldü: düğmedeki nokta sönsün.
    if (unread.value > 0) {
        router.reload({ only: ['feedbackUnread'] });
    }
}

const day = (iso: string) =>
    new Date(iso).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });

const user = computed(() => page.props.auth.user);
const firstName = computed(() => (user.value?.name ?? '').split(' ')[0]);
const screenName = computed(() => document.title.split(' - ')[0] || 'Bu ekran');

function reset(): void {
    step.value = 'form';
    view.value = 'form';
    type.value = 'oneri';
    rate.value = 0;
    text.value = '';
    error.value = null;
}

async function toggle(): Promise<void> {
    open.value = !open.value;

    if (open.value) {
        if (step.value === 'done') {
            reset();
        }

        await nextTick();
        textarea.value?.focus();
    }
}

function close(): void {
    open.value = false;

    if (step.value === 'done') {
        reset();
    }
}

function send(): void {
    if (text.value.trim().length < 3) {
        error.value = 'Birkaç kelimeyle yazmanız yeterli.';
        textarea.value?.focus();

        return;
    }

    step.value = 'sending';
    router.post(
        store.url(),
        {
            type: type.value,
            rating: rate.value || null,
            message: text.value,
            screen: attach.value ? page.url.split('?')[0] : null,
            wants_reply: reply.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (p) => {
                const flash = (p as { flash?: { feedback?: { no?: number } } })
                    .flash;
                trackingNo.value = flash?.feedback?.no ?? null;
                step.value = 'done';
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? 'Gönderilemedi.';
                step.value = 'form';
            },
        },
    );
}

const tracking = computed(() =>
    trackingNo.value === null
        ? null
        : `GB-${new Date().getFullYear()}-${String(trackingNo.value).padStart(4, '0')}`,
);
</script>

<template>
    <div class="mx fb-host" @keydown.esc="close">
        <div
            v-if="open"
            class="fbpanel"
            role="dialog"
            :aria-label="
                step === 'done' ? 'Geri bildirim gönderildi' : 'Görüşünü paylaş'
            "
        >
            <button
                class="xbtn x"
                type="button"
                aria-label="Kapat"
                @click="close"
            >
                ×
            </button>
            <div v-if="step === 'done'" class="fbdone">
                <svg viewBox="0 0 80 80" aria-hidden="true">
                    <circle cx="40" cy="40" r="34" />
                    <path d="M26 41l10 10 19-21" />
                </svg>
                <h4>Teşekkürler{{ firstName ? `, ${firstName}` : '' }}</h4>
                <p>
                    Mesajınız doğrudan ürün ekibimize ulaştı. En geç
                    <b>2 iş günü</b> içinde değerlendirip
                    {{ reply ? 'size dönüş yapacağız' : 'ele alacağız' }}.
                </p>
                <span v-if="tracking" class="trk"
                    >Takip no · {{ tracking }}</span
                >
                <p style="font-size: 12px">
                    Fikirleriniz Marhal'ı birlikte büyütüyor. Durumunu
                    "Gönderdiklerim" bölümünden takip edebilirsiniz.
                </p>
                <div class="btns">
                    <button
                        class="btn ghost"
                        type="button"
                        @click="
                            step = 'form';
                            showMine();
                        "
                    >
                        Gönderdiklerim
                    </button>
                    <button class="btn" type="button" @click="close">
                        Kapat
                    </button>
                </div>
            </div>
            <template v-else>
                <div class="fbhead">
                    <h4>Görüşün bizim için değerli</h4>
                    <p>
                        Marhal'ı her gün kullanan sizsiniz. Ne düşündüğünüzü
                        doğrudan ürün ekibine iletin.
                    </p>
                    <div class="seg fbswitch" role="group" aria-label="Bölüm">
                        <button
                            type="button"
                            :aria-pressed="view === 'form'"
                            @click="view = 'form'"
                        >
                            Yeni görüş
                        </button>
                        <button
                            type="button"
                            :aria-pressed="view === 'mine'"
                            @click="showMine"
                        >
                            Gönderdiklerim<em v-if="unread"
                                >{{ unread }} yanıt</em
                            >
                        </button>
                    </div>
                </div>
                <div v-if="view === 'mine'" class="fbbody fbmine">
                    <p v-if="sent === null" class="lbl">Yükleniyor…</p>
                    <p v-else-if="sent.length === 0" class="lbl">
                        Henüz bir şey göndermediniz.
                    </p>
                    <div v-for="item in sent ?? []" :key="item.id" class="sent">
                        <div class="sent-head">
                            <span class="chip">{{ item.type_label }}</span>
                            <span class="lbl"
                                >{{ item.tracking }} ·
                                {{ day(item.created_at) }}</span
                            >
                            <span
                                class="chip"
                                :class="
                                    item.status === 'yanitlandi' ? 'ok' : 'acc'
                                "
                                >{{ item.status_label }}</span
                            >
                        </div>
                        <p>{{ item.message }}</p>
                        <div
                            v-if="item.reply"
                            class="sent-reply"
                            :class="{ fresh: item.unseen }"
                        >
                            <b>Marhal ekibi:</b> {{ item.reply }}
                        </div>
                    </div>
                </div>
                <form v-else class="fbbody" @submit.prevent="send">
                    <div class="fbtypes" role="group" aria-label="Konu">
                        <button
                            v-for="t in TYPES"
                            :key="t.value"
                            type="button"
                            :aria-pressed="type === t.value"
                            @click="type = t.value"
                        >
                            <b>{{ t.icon }}</b
                            >{{ t.label }}
                        </button>
                    </div>
                    <div>
                        <div class="lbl2">Marhal'dan ne kadar memnunsunuz?</div>
                        <div class="stars">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                :class="{ on: n <= rate }"
                                :aria-label="`${n} yıldız`"
                                @click="rate = rate === n ? 0 : n"
                            >
                                ★
                            </button>
                            <small>{{ RATE_TEXT[rate] }}</small>
                        </div>
                    </div>
                    <label class="fi" for="fbText"
                        ><span class="lbl2">{{
                            type === 'hata'
                                ? 'Ne oldu? Hangi adımda?'
                                : type === 'soru'
                                  ? 'Sorunuz'
                                  : type === 'begeni'
                                    ? 'Mesajınız'
                                    : 'Fikriniz'
                        }}</span>
                        <textarea
                            id="fbText"
                            ref="textarea"
                            v-model="text"
                            maxlength="3000"
                            :placeholder="
                                type === 'hata'
                                    ? 'Örn: Oda planında yolcuyu bırakınca uyarı çıkmadı…'
                                    : 'Aklınızdakini birkaç cümleyle yazın'
                            "
                        />
                    </label>
                    <span
                        v-if="error"
                        class="lbl"
                        style="color: var(--m-danger)"
                        >{{ error }}</span
                    >
                    <span class="lbl"
                        >Lütfen yolcuların kimlik veya sağlık bilgisini
                        yazmayın.</span
                    >
                    <div class="fbmeta">
                        <div
                            class="ck"
                            :class="{ on: attach }"
                            role="checkbox"
                            :aria-checked="attach"
                            tabindex="0"
                            @click="attach = !attach"
                        >
                            <span class="bx">{{ attach ? '✓' : '' }}</span
                            >Bulunduğum ekranı ekle · {{ screenName }}
                        </div>
                        <div
                            class="ck"
                            :class="{ on: reply }"
                            role="checkbox"
                            :aria-checked="reply"
                            tabindex="0"
                            @click="reply = !reply"
                        >
                            <span class="bx">{{ reply ? '✓' : '' }}</span
                            >Bana dönüş yapılsın<template v-if="user?.email">
                                · {{ user.email }}</template
                            >
                        </div>
                    </div>
                    <button
                        class="btn fbsend"
                        type="submit"
                        :disabled="step === 'sending'"
                    >
                        <template v-if="step === 'sending'"
                            ><span class="spin" />Gönderiliyor…</template
                        >
                        <template v-else>Gönder</template>
                    </button>
                </form>
            </template>
        </div>
        <button
            class="fbbtn"
            type="button"
            aria-label="Görüşünü paylaş"
            @click="toggle"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path
                    d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.6A8.4 8.4 0 1 1 21 11.5z"
                />
                <path
                    d="M12 15s-3-1.8-3-3.8a1.6 1.6 0 0 1 3-.8 1.6 1.6 0 0 1 3 .8c0 2-3 3.8-3 3.8z"
                    fill="currentColor"
                    stroke="none"
                /></svg
            ><span>Görüşünü paylaş</span
            ><i v-if="unread" class="fbdot" aria-label="Yeni yanıt var" />
        </button>
    </div>
</template>
