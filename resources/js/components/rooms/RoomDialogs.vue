<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import RoomController from '@/actions/App/Http/Controllers/RoomController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import type { PlanRoom, RoomKind } from '@/types/room';
import type { Option } from '@/types/person';

/**
 * Toplu oda ekleme ve tek oda düzenleme diyalogları (oda planı ekranı).
 */
const props = defineProps<{
    stayId: string;
    kinds: Option<RoomKind>[];
    editing: PlanRoom | null;
}>();

const addOpen = defineModel<boolean>('addOpen', { required: true });
const editOpen = defineModel<boolean>('editOpen', { required: true });

function removeRoom(): void {
    const room = props.editing;

    if (!room) {
        return;
    }

    const extra = room.occupants.length
        ? ` İçindeki ${room.occupants.length} yolcu yerleşmemiş listesine döner.`
        : '';

    if (confirm(`${room.room_no} numaralı oda silinsin mi?${extra}`)) {
        router.delete(RoomController.destroy.url(room.id), {
            preserveScroll: true,
            onSuccess: () => (editOpen.value = false),
        });
    }
}
</script>

<template>
    <Dialog v-model:open="addOpen">
        <DialogContent>
            <Form
                v-bind="RoomController.store.form(stayId)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="addOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>Oda ekle</DialogTitle>
                    <DialogDescription>
                        Aynı katta sıralı odaları tek seferde ekleyin. Örn.
                        501'den başlayarak 10 oda → 501–510.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-3 gap-3">
                    <div class="grid gap-2">
                        <Label for="room-floor">Kat</Label>
                        <Input id="room-floor" name="floor" placeholder="5" />
                        <InputError :message="errors.floor" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="room-start">İlk oda no *</Label>
                        <Input
                            id="room-start"
                            name="start_no"
                            placeholder="501"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="room-count">Oda sayısı *</Label>
                        <Input
                            id="room-count"
                            name="count"
                            type="number"
                            min="1"
                            max="100"
                            default-value="1"
                            required
                        />
                    </div>
                </div>
                <InputError :message="errors.start_no ?? errors.count" />
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="room-capacity">Kişi sayısı *</Label>
                        <select
                            id="room-capacity"
                            name="capacity"
                            :class="selectClass"
                        >
                            <option
                                v-for="n in [1, 2, 3, 4, 5, 6]"
                                :key="n"
                                :value="n"
                                :selected="n === 4"
                            >
                                {{ n }} kişilik
                            </option>
                        </select>
                        <InputError :message="errors.capacity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="room-kind">Oda türü *</Label>
                        <select id="room-kind" name="kind" :class="selectClass">
                            <option
                                v-for="kind in kinds"
                                :key="kind.value"
                                :value="kind.value"
                            >
                                {{ kind.label }}
                            </option>
                        </select>
                        <InputError :message="errors.kind" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="room-elevator">Asansöre yakın oda sayısı</Label>
                    <Input
                        id="room-elevator"
                        name="near_elevator"
                        type="number"
                        min="0"
                        max="100"
                        :default-value="0"
                        class="w-32"
                    />
                    <p class="text-xs text-muted-foreground">
                        İlk eklenen odalar (ör. 501, 502) asansöre yakın
                        işaretlenir; hareket güçlüğü olanlar buraya önerilir.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    Tür sonradan değiştirilebilir; otomatik dağıtma boş odaların
                    türünü yerleşenlere göre ayarlar.
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="addOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">Ekle</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editOpen">
        <DialogContent v-if="editing">
            <Form
                :key="editing.id"
                v-bind="RoomController.update.form(editing.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="editOpen = false"
            >
                <DialogHeader>
                    <DialogTitle
                        >{{ editing.room_no }} numaralı oda</DialogTitle
                    >
                </DialogHeader>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="edit-room-no">Oda no *</Label>
                        <Input
                            id="edit-room-no"
                            name="room_no"
                            :default-value="editing.room_no"
                            required
                        />
                        <InputError :message="errors.room_no" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-room-floor">Kat</Label>
                        <Input
                            id="edit-room-floor"
                            name="floor"
                            :default-value="editing.floor ?? undefined"
                        />
                        <InputError :message="errors.floor" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-room-capacity">Kişi sayısı *</Label>
                        <select
                            id="edit-room-capacity"
                            name="capacity"
                            :class="selectClass"
                        >
                            <option
                                v-for="n in [1, 2, 3, 4, 5, 6, 7, 8]"
                                :key="n"
                                :value="n"
                                :selected="n === editing.capacity"
                            >
                                {{ n }} kişilik
                            </option>
                        </select>
                        <InputError :message="errors.capacity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="edit-room-kind">Oda türü *</Label>
                        <select
                            id="edit-room-kind"
                            name="kind"
                            :class="selectClass"
                        >
                            <option
                                v-for="kind in kinds"
                                :key="kind.value"
                                :value="kind.value"
                                :selected="kind.value === editing.kind"
                            >
                                {{ kind.label }}
                            </option>
                        </select>
                        <InputError :message="errors.kind" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="edit-room-notes">Not</Label>
                    <Input
                        id="edit-room-notes"
                        name="notes"
                        :default-value="editing.notes ?? undefined"
                        placeholder="Örn. Harem manzaralı, ek yatak"
                    />
                    <InputError :message="errors.notes" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="near_elevator" value="0" />
                    <input
                        type="checkbox"
                        name="near_elevator"
                        value="1"
                        class="size-4 accent-(--primary)"
                        :checked="editing.near_elevator"
                    />
                    Asansöre yakın
                </label>
                <DialogFooter class="sm:justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        @click="removeRoom"
                    >
                        <Trash2 /> Odayı sil
                    </Button>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editOpen = false"
                        >
                            Vazgeç
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Kaydet
                        </Button>
                    </div>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
