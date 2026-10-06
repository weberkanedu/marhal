<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MonitorSmartphone } from '@lucide/vue';
import DeviceController from '@/actions/App/Http/Controllers/Settings/DeviceController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

/**
 * "Cihazlarım" (10d): kullanıcının giriş yaptığı tarayıcılar. Eski cihazı kaldırmak cihaz sınırında yer açar;
 * kullanılan cihaz kaldırılamaz.
 */
export type Device = {
    id: number;
    label: string;
    last_seen_at: string;
    current: boolean;
};

const props = defineProps<{ devices: Device[]; deviceLimit: number }>();

const when = (iso: string) =>
    new Date(iso).toLocaleString('tr-TR', {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });

function remove(device: Device): void {
    if (confirm(`${device.label} cihazı kaldırılsın mı?`)) {
        router.delete(DeviceController.destroy.url(device.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Cihazlarım"
            :description="`Hesabınıza giriş yapılan tarayıcılar. Sınır ${props.deviceLimit} cihaz; kullanmadığınız cihazı kaldırın. Hesap aynı anda yalnız bir cihazda açık kalır.`"
        />
        <p v-if="devices.length === 0" class="text-sm text-muted-foreground">
            Henüz kayıtlı cihaz yok; bir sonraki girişinizde eklenecek.
        </p>
        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="device in devices"
                :key="device.id"
                class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
            >
                <span class="flex items-center gap-3">
                    <MonitorSmartphone class="size-4 text-muted-foreground" />
                    <span>
                        <span class="font-medium">{{ device.label }}</span>
                        <span class="block text-xs text-muted-foreground">
                            {{
                                device.current
                                    ? 'Bu cihaz'
                                    : `Son giriş ${when(device.last_seen_at)}`
                            }}
                        </span>
                    </span>
                </span>
                <Button
                    v-if="!device.current"
                    variant="outline"
                    size="sm"
                    @click="remove(device)"
                    >Kaldır</Button
                >
            </li>
        </ul>
    </div>
</template>
