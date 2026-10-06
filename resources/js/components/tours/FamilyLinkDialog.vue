<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import FamilyLinkController from '@/actions/App/Http/Controllers/FamilyLinkController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { RegistrationRow } from '@/types/tour';

/**
 * Aile ekranı linki: yolcunun izni işaretlenir, link oluşturulur; kopyalanır ya da WhatsApp'tan gönderilir,
 * iptal edilebilir. Aile yalnız yolcunun nerede olduğunu ve programı görür.
 */
const props = defineProps<{ registration: RegistrationRow | null }>();
const open = defineModel<boolean>('open', { required: true });

const consent = ref(false);
const busy = ref(false);
watch(open, (isOpen) => isOpen && (consent.value = false));

const options = {
    preserveScroll: true,
    preserveState: true,
    only: ['registrations'],
    onFinish: () => (busy.value = false),
    onError: (e: Record<string, string>) =>
        toast.error(Object.values(e)[0] ?? 'İşlem yapılamadı.'),
};

function create(): void {
    if (!props.registration) {
        return;
    }

    busy.value = true;
    router.post(
        FamilyLinkController.store.url(props.registration.id),
        { consent: consent.value },
        options,
    );
}

function revoke(): void {
    const link = props.registration?.family_link;

    if (
        link &&
        confirm('Link iptal edilsin mi? Ailenin elindeki link artık açılmaz.')
    ) {
        busy.value = true;
        router.delete(FamilyLinkController.destroy.url(link.id), options);
    }
}

async function copy(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Link kopyalandı.');
    } catch {
        window.prompt('Linki kopyalayın:', url);
    }
}

const whatsapp = (url: string) =>
    `https://wa.me/?text=${encodeURIComponent(`${props.registration?.person.full_name} yolculuğunu buradan takip edebilirsiniz: ${url}`)}`;
const date = (d: string) =>
    new Date(`${d}T00:00:00`).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
    });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    >Aile ekranı ·
                    {{ registration?.person.full_name }}</DialogTitle
                >
                <DialogDescription>
                    Ailesi uygulama indirmeden, linkle yolcunun hangi şehirde ve
                    otelde olduğunu ve günün programını görür. Kimlik, pasaport,
                    sağlık ve ödeme bilgisi görünmez.
                </DialogDescription>
            </DialogHeader>

            <template v-if="registration?.family_link">
                <div class="flex gap-2">
                    <Input
                        :model-value="registration.family_link.url"
                        readonly
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        variant="outline"
                        @click="copy(registration.family_link.url)"
                        >Kopyala</Button
                    >
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ registration.family_link.views }} kez açıldı ·
                    {{ date(registration.family_link.expires_at) }}
                    tarihine kadar geçerli (tur bitişinden 7 gün sonra kapanır).
                </p>
                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        variant="ghost"
                        class="text-destructive"
                        :disabled="busy"
                        @click="revoke"
                        >İptal et</Button
                    >
                    <Button as-child>
                        <a
                            :href="whatsapp(registration.family_link.url)"
                            target="_blank"
                            rel="noopener"
                            >WhatsApp'ta gönder</a
                        >
                    </Button>
                </DialogFooter>
            </template>
            <template v-else>
                <label class="flex items-start gap-2 text-sm">
                    <input v-model="consent" type="checkbox" class="mt-1" />
                    <span
                        >Yolcu, yerleşim bilgisinin ve tur programının ailesiyle
                        paylaşılmasına izin verdi.</span
                    >
                </label>
                <DialogFooter>
                    <Button variant="ghost" @click="open = false"
                        >Vazgeç</Button
                    >
                    <Button :disabled="!consent || busy" @click="create"
                        >Linki oluştur</Button
                    >
                </DialogFooter>
            </template>
        </DialogContent>
    </Dialog>
</template>
