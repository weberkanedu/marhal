<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import RoomPlanController from '@/actions/App/Http/Controllers/RoomPlanController';
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
import { Label } from '@/components/ui/label';

/**
 * "Oteli tanımla": binanın kat sayısı (otel geneli) ve bu konaklamada bize verilen katlar.
 * Katlara oda eklemek "Oda ekle" ile (kat, başlangıç no, sayı, asansöre yakın oda sayısı).
 */
const props = defineProps<{
    stayId: string;
    hotelName: string;
    floorsCount: number | null;
    usedFloors: number[];
    // Odası olan katlar listeden çıkarılamaz.
    roomFloors: number[];
}>();

const open = defineModel<boolean>('open', { required: true });

const count = ref(props.floorsCount ?? 10);
const used = ref<number[]>([...props.usedFloors]);
const saving = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        count.value = props.floorsCount ?? 10;
        used.value = [...props.usedFloors, ...props.roomFloors].filter(
            (f, i, all) => all.indexOf(f) === i,
        );
    }
});

const floors = computed(() =>
    Array.from(
        { length: Math.min(Math.max(Number(count.value) || 1, 1), 150) },
        (_, i) => i + 1,
    ),
);

function toggle(floor: number): void {
    used.value = used.value.includes(floor)
        ? used.value.filter((f) => f !== floor)
        : [...used.value, floor];
}

function save(): void {
    saving.value = true;
    router.put(
        RoomPlanController.floors.url(props.stayId),
        {
            floors_count: Number(count.value),
            used_floors: used.value.filter((f) => f <= Number(count.value)),
        },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Kaydedilemedi.'),
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Oteli tanımla — {{ hotelName }}</DialogTitle>
                <DialogDescription>
                    Kat sayısı otelde saklanır; bize verilen katlar bu tura
                    özeldir. Odaları "Oda ekle" ile kat kat ekleyin.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="floors-count">Binanın kat sayısı</Label>
                <Input
                    id="floors-count"
                    v-model.number="count"
                    type="number"
                    min="1"
                    max="150"
                    class="w-32"
                />
            </div>

            <div class="grid gap-2">
                <Label>Bize verilen katlar</Label>
                <div class="grid max-h-60 grid-cols-6 gap-1.5 overflow-y-auto">
                    <button
                        v-for="floor in floors"
                        :key="floor"
                        type="button"
                        class="rounded-md border px-2 py-1.5 text-sm tabular-nums transition"
                        :class="
                            used.includes(floor)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        :disabled="roomFloors.includes(floor)"
                        :title="
                            roomFloors.includes(floor)
                                ? 'Bu katta oda var'
                                : undefined
                        "
                        @click="toggle(floor)"
                    >
                        {{ floor }}
                    </button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Odası olan katlar kilitli (önce odaları silinmeli).
                </p>
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">Vazgeç</Button>
                <Button :disabled="saving" @click="save">Kaydet</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
