<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import FeedbackController from '@/actions/App/Http/Controllers/FeedbackController';
import MockTop from '@/components/mock/MockTop.vue';
import { index } from '@/routes/platform/feedback';
import type { Paginated } from '@/types/person';

type FeedbackRow = {
    id: number;
    tracking: string;
    tenant: string;
    user: string | null;
    type: 'oneri' | 'hata' | 'soru' | 'begeni';
    type_label: string;
    rating: number | null;
    message: string;
    screen: string | null;
    wants_reply: boolean;
    status: 'yeni' | 'yanitlandi';
    reply: string | null;
    replied_at: string | null;
    created_at: string;
};

defineProps<{ items: Paginated<FeedbackRow>; newCount: number }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Geri bildirimler', href: index() }],
    },
});

/** Tasarımdaki rozet renkleri: Sorun turuncu, Teşekkür yeşil, öneri / soru altın. */
const tone: Record<FeedbackRow['type'], string> = {
    hata: 'warning',
    begeni: 'ok',
    oneri: 'acc',
    soru: 'acc',
};

const when = (iso: string) =>
    new Date(iso).toLocaleString('tr-TR', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });

const editing = ref<number | null>(null);
const draft = ref('');
const sending = ref(false);

function startReply(item: FeedbackRow): void {
    editing.value = item.id;
    draft.value = item.reply ?? '';
}

function send(item: FeedbackRow): void {
    sending.value = true;
    router.post(
        FeedbackController.reply.url(item.id),
        { reply: draft.value },
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
            onFinish: () => (sending.value = false),
        },
    );
}
</script>

<template>
    <Head title="Geri bildirimler" />

    <div class="mx">
        <div class="main">
            <MockTop
                :crumbs="[{ label: 'Platform' }]"
                title="Geri bildirimler"
            />
            <p class="lbl">
                Uygulamadaki "Görüşünü paylaş" düğmesinden gelenler; yanıt
                bekleyenler üstte ({{ newCount }}). Yanıtınız kullanıcının
                "Gönderdiklerim" bölümünde görünür; e-posta servisi bağlanınca
                e-postayla da gidecek.
            </p>

            <p v-if="items.data.length === 0" class="card lbl">
                Henüz geri bildirim yok.
            </p>
            <div v-else class="inbox">
                <div v-for="item in items.data" :key="item.id" class="msg">
                    <div>
                        <div class="meta">
                            <span class="chip" :class="tone[item.type]">{{
                                item.type_label
                            }}</span>
                            <span
                                v-if="item.rating"
                                class="stars"
                                :aria-label="`${item.rating} yıldız`"
                                >{{ '★'.repeat(item.rating)
                                }}{{ '☆'.repeat(5 - item.rating) }}</span
                            >
                            <span
                                >{{ item.tenant }} ·
                                {{ item.user ?? 'silinmiş kullanıcı' }}</span
                            >
                            <span v-if="item.screen"
                                >Ekran: {{ item.screen }}</span
                            >
                            <span>{{ item.tracking }}</span>
                            <span>{{ when(item.created_at) }}</span>
                            <span v-if="item.wants_reply" class="chip"
                                >Dönüş bekliyor</span
                            >
                        </div>
                        <p class="text">{{ item.message }}</p>
                    </div>
                    <div class="act">
                        <button
                            v-if="item.status === 'yeni' && editing !== item.id"
                            class="btn"
                            type="button"
                            @click="startReply(item)"
                        >
                            Yanıtla
                        </button>
                        <span
                            v-else-if="item.status === 'yanitlandi'"
                            class="chip ok"
                            >Yanıtlandı</span
                        >
                    </div>
                    <form
                        v-if="editing === item.id"
                        class="reply-form"
                        @submit.prevent="send(item)"
                    >
                        <textarea
                            v-model="draft"
                            rows="3"
                            maxlength="3000"
                            aria-label="Yanıtınız"
                            placeholder="Yanıtınız"
                            required
                        />
                        <div class="btns">
                            <button
                                class="btn ghost sm"
                                type="button"
                                @click="editing = null"
                            >
                                Vazgeç
                            </button>
                            <button
                                class="btn sm"
                                type="submit"
                                :disabled="sending || draft.trim().length < 2"
                            >
                                Yanıtı gönder
                            </button>
                        </div>
                    </form>
                    <div v-else-if="item.reply" class="reply">
                        <b>Yanıtın:</b> {{ item.reply }}
                        <a role="button" tabindex="0" @click="startReply(item)"
                            >Düzelt</a
                        >
                    </div>
                </div>
            </div>

            <div v-if="items.last_page > 1" class="btns">
                <Link
                    v-if="items.prev_page_url"
                    class="btn ghost sm"
                    :href="items.prev_page_url"
                    >← Yeniler</Link
                >
                <Link
                    v-if="items.next_page_url"
                    class="btn ghost sm"
                    :href="items.next_page_url"
                    >Eskiler →</Link
                >
            </div>
        </div>
    </div>
</template>
