<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BusController from '@/actions/App/Http/Controllers/BusController';
import InputError from '@/components/InputError.vue';
import MockIcon from '@/components/mock/MockIcon.vue';
import MockRing from '@/components/mock/MockRing.vue';
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
import { seatPlan } from '@/routes/buses';
import { index as vehicleTypesIndex } from '@/routes/vehicle-types';
import type { TourBus, VehicleTypeOption } from '@/types/bus';
import type { TourGroup, TourSummary } from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    buses: TourBus[];
    groups: TourGroup[];
    vehicleTypes: VehicleTypeOption[];
    canUpdate: boolean;
}>();

const open = ref(false);
const editing = ref<TourBus | null>(null);

const form = computed(() =>
    editing.value
        ? BusController.update.form(editing.value.id)
        : BusController.store.form(props.tour.id),
);

// Yeni otobüste: henüz otobüsü olmayan gruplar seçili gelir.
function defaultChecked(groupId: string): boolean {
    if (editing.value) {
        return editing.value.groups.some((g) => g.id === groupId);
    }

    return !props.buses.some((b) => b.groups.some((g) => g.id === groupId));
}

function openDialog(bus: TourBus | null): void {
    editing.value = bus;
    open.value = true;
}

function remove(bus: TourBus): void {
    if (
        confirm(
            `${bus.name} silinsin mi? ${bus.occupied} yolcunun koltuğu boşalır.`,
        )
    ) {
        router.delete(BusController.destroy.url(bus.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <!-- Tasarımdaki araç kartları (Tur → Ulaşım; sarmalayan .g3 sayfadadır). -->
    <div
        v-for="bus in buses"
        :key="bus.id"
        class="vehicle"
        role="link"
        tabindex="0"
        @click="router.visit(seatPlan.url(bus.id))"
        @keydown.enter="router.visit(seatPlan.url(bus.id))"
    >
        <span class="ic"><MockIcon name="bus" /></span>
        <div>
            <b>{{ bus.name }} · {{ bus.vehicle_type ?? bus.label }}</b>
            <small
                >{{ bus.plate ? `${bus.plate} · ` : '' }}{{ bus.occupied }}/{{
                    bus.seats
                }}
                koltuk<template v-if="bus.groups.length">
                    · {{ bus.groups.map((g) => g.name).join(', ') }}</template
                ></small
            >
            <small v-if="bus.driver_name"
                >Şoför {{ bus.driver_name }} {{ bus.driver_phone ?? '' }}</small
            >
        </div>
        <MockRing :done="bus.occupied" :total="bus.seats" />
        <span v-if="canUpdate" class="acts" @click.stop>
            <button type="button" title="Düzenle" @click="openDialog(bus)">
                <Pencil class="size-3.5" />
            </button>
            <button type="button" title="Sil" @click="remove(bus)">
                <Trash2 class="size-3.5" />
            </button>
        </span>
    </div>
    <div
        v-if="canUpdate"
        class="vehicle add"
        role="button"
        tabindex="0"
        @click="openDialog(null)"
    >
        <Plus class="size-4" /> Araç ekle
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto">
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="form"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? editing.name : 'Otobüs ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Koltuk düzeni seçtiğiniz araç tipinden alınır.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="vehicleTypes.length === 0" class="text-sm">
                    Henüz araç tipi tanımlanmadı.
                    <Link :href="vehicleTypesIndex()" class="underline">
                        Önce araç tipi ekleyin.
                    </Link>
                </div>

                <template v-else>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="bus-name">Ad *</Label>
                            <Input
                                id="bus-name"
                                name="name"
                                :default-value="
                                    editing?.name ??
                                    `${buses.length + 1}. Otobüs`
                                "
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bus-plate">Plaka</Label>
                            <Input
                                id="bus-plate"
                                name="plate"
                                :default-value="editing?.plate ?? undefined"
                            />
                            <InputError :message="errors.plate" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="bus-type">Araç tipi *</Label>
                        <select
                            id="bus-type"
                            name="vehicle_type_id"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="type in vehicleTypes"
                                :key="type.id"
                                :value="type.id"
                                :selected="editing?.vehicle_type_id === type.id"
                            >
                                {{ type.name }} ({{ type.label }})
                            </option>
                        </select>
                        <InputError :message="errors.vehicle_type_id" />
                        <Link
                            :href="vehicleTypesIndex()"
                            class="text-xs text-muted-foreground underline"
                        >
                            Araç tiplerini düzenle
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="bus-driver">Şoför</Label>
                            <Input
                                id="bus-driver"
                                name="driver_name"
                                :default-value="
                                    editing?.driver_name ?? undefined
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bus-driver-phone">Şoför telefonu</Label>
                            <Input
                                id="bus-driver-phone"
                                name="driver_phone"
                                type="tel"
                                :default-value="
                                    editing?.driver_phone ?? undefined
                                "
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="bus-reserved">
                            Rehber / görevli için ayrılan koltuklar
                        </Label>
                        <Input
                            id="bus-reserved"
                            name="reserved_seats"
                            :default-value="editing?.reserved.join(', ') ?? ''"
                            placeholder="Örn. 1, 2"
                        />
                        <InputError
                            :message="
                                errors.reserved_seats ??
                                errors['reserved_seats.0']
                            "
                        />
                    </div>
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Bu otobüsteki gruplar
                        </legend>
                        <label
                            v-for="group in groups"
                            :key="group.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="group_ids[]"
                                :value="group.id"
                                :checked="defaultChecked(group.id)"
                            />
                            {{ group.name }}
                            <span class="text-xs text-muted-foreground">
                                ({{ group.registrations_count }} yolcu)
                            </span>
                        </label>
                        <InputError :message="errors.group_ids" />
                    </fieldset>
                </template>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing || vehicleTypes.length === 0"
                    >
                        Kaydet
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
