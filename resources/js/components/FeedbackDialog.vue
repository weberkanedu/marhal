<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { Bug, Heart, Lightbulb, Star } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { textareaClass } from '@/lib/formClasses';
import { store } from '@/routes/feedback';

/**
 * "Görüşünü paylaş": tür, yıldız, mesaj. Hangi ekranda yazıldığı (yalnız adres yolu) birlikte gider;
 * kullanıcıya takip numarası gösterilir (bildirim mesajında).
 */
const open = defineModel<boolean>('open', { required: true });

const types = [
    { value: 'oneri', label: 'Öneri', icon: Lightbulb },
    { value: 'hata', label: 'Hata', icon: Bug },
    { value: 'begeni', label: 'Beğendim', icon: Heart },
] as const;

const type = ref<(typeof types)[number]['value']>('oneri');
const rating = ref(0);
const page = usePage();

watch(open, (isOpen) => {
    if (isOpen) {
        type.value = 'oneri';
        rating.value = 0;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>Görüşün bizim için değerli</DialogTitle>
                    <DialogDescription>
                        Marhal'ı her gün kullanan sizsiniz. Ne düşündüğünüzü
                        doğrudan ürün ekibine iletin.
                    </DialogDescription>
                </DialogHeader>

                <input type="hidden" name="type" :value="type" />
                <input
                    type="hidden"
                    name="screen"
                    :value="page.url.split('?')[0]"
                />
                <input
                    v-if="rating > 0"
                    type="hidden"
                    name="rating"
                    :value="rating"
                />

                <div class="grid grid-cols-3 gap-2" role="radiogroup">
                    <button
                        v-for="item in types"
                        :key="item.value"
                        type="button"
                        role="radio"
                        :aria-checked="type === item.value"
                        class="flex flex-col items-center gap-1 rounded-xl border p-3 text-sm transition-colors"
                        :class="
                            type === item.value
                                ? 'border-primary bg-accent font-semibold'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="type = item.value"
                    >
                        <component :is="item.icon" class="size-5" />
                        {{ item.label }}
                    </button>
                </div>

                <div class="flex items-center gap-1" aria-label="Puan">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        class="rounded p-0.5"
                        :aria-label="`${n} yıldız`"
                        @click="rating = rating === n ? 0 : n"
                    >
                        <Star
                            class="size-6 transition-colors"
                            :class="
                                n <= rating
                                    ? 'fill-primary text-primary'
                                    : 'text-muted-foreground'
                            "
                        />
                    </button>
                    <span class="ml-2 text-xs text-muted-foreground">
                        İsteğe bağlı
                    </span>
                </div>

                <div class="grid gap-1.5">
                    <textarea
                        name="message"
                        rows="4"
                        required
                        :class="textareaClass"
                        :placeholder="
                            type === 'hata'
                                ? 'Ne oldu? Hangi adımda? (Örn: Oda planında yolcuyu bırakınca uyarı çıkmadı)'
                                : 'Aklınızdakini birkaç cümleyle yazın'
                        "
                    />
                    <InputError :message="errors.message" />
                    <p class="text-xs text-muted-foreground">
                        Lütfen yolcuların kimlik veya sağlık bilgisini yazmayın.
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">
                        Gönder
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
