<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, KeyRound } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Sunucunun `credentials` flash verisini (yeni kullanıcı / şifre sıfırlama) bir kez gösterir.
 * Şifre sayfada saklanmaz; pencere kapanınca bir daha görülemez.
 */
type Credentials = {
    name: string;
    email: string;
    password: string;
    tenant?: string;
};

const open = ref(false);
const credentials = ref<Credentials | null>(null);
const copied = ref(false);

const off = router.on('flash', (event) => {
    const data = (event as CustomEvent).detail?.flash?.credentials as
        | Credentials
        | undefined;

    if (data) {
        credentials.value = data;
        copied.value = false;
        open.value = true;
    }
});

onBeforeUnmount(() => off());

async function copy(): Promise<void> {
    if (!credentials.value) {
        return;
    }

    const text = `Marhal giriş bilgileri\nAdres: ${window.location.origin}/login\nE-posta: ${credentials.value.email}\nGeçici şifre: ${credentials.value.password}\nİlk girişte şifrenizi değiştirmeniz istenecek.`;

    await navigator.clipboard.writeText(text);
    copied.value = true;
}

function close(isOpen: boolean): void {
    open.value = isOpen;

    if (!isOpen) {
        credentials.value = null;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent v-if="credentials">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5" /> Giriş bilgileri
                </DialogTitle>
                <DialogDescription>
                    <template v-if="credentials.tenant">
                        {{ credentials.tenant }} oluşturuldu.
                    </template>
                    Bu geçici şifre <strong>sadece bir kez</strong> gösterilir.
                    Kişiye güvenli bir yoldan iletin; ilk girişte şifresini
                    değiştirmesi istenecek.
                </DialogDescription>
            </DialogHeader>

            <dl
                class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-md border bg-muted/40 p-4 text-sm"
            >
                <dt class="text-muted-foreground">Kişi</dt>
                <dd class="font-medium">{{ credentials.name }}</dd>
                <dt class="text-muted-foreground">E-posta</dt>
                <dd class="font-mono">{{ credentials.email }}</dd>
                <dt class="text-muted-foreground">Geçici şifre</dt>
                <dd class="font-mono text-base font-semibold tracking-wider">
                    {{ credentials.password }}
                </dd>
            </dl>

            <DialogFooter>
                <Button variant="outline" @click="copy">
                    <component :is="copied ? Check : Copy" />
                    {{ copied ? 'Kopyalandı' : 'Bilgileri kopyala' }}
                </Button>
                <Button @click="close(false)">Tamam</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
