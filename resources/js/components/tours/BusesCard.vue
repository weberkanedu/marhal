<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import { Armchair, Bus as BusIcon, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BusController from '@/actions/App/Http/Controllers/BusController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    <Card class="min-w-0">
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="flex items-center gap-2">
                <BusIcon class="size-4" /> Otobüsler
            </CardTitle>
            <Button
                v-if="canUpdate"
                variant="ghost"
                size="sm"
                @click="openDialog(null)"
            >
                <Plus /> Otobüs ekle
            </Button>
        </CardHeader>
        <CardContent class="text-sm">
            <p v-if="buses.length === 0" class="text-muted-foreground">
                Henüz otobüs eklenmedi.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="bus in buses"
                    :key="bus.id"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2"
                >
                    <div class="min-w-48 flex-1">
                        <div class="font-medium">
                            {{ bus.name }}
                            <span
                                v-if="bus.plate"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                · {{ bus.plate }}
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ bus.vehicle_type ?? bus.label }} ·
                            {{ bus.seats + bus.reserved.length }} koltuk
                            <template v-if="bus.driver_name">
                                · Şoför: {{ bus.driver_name }}
                                {{ bus.driver_phone ?? '' }}
                            </template>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="group in bus.groups"
                            :key="group.id"
                            variant="outline"
                        >
                            {{ group.name }}
                        </Badge>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="seatPlan(bus.id)">
                            <Armchair />
                            Koltuk planı
                            <span class="text-xs text-muted-foreground">
                                {{ bus.occupied }}/{{ bus.seats }}
                            </span>
                        </Link>
                    </Button>
                    <div v-if="canUpdate" class="flex">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Düzenle"
                            @click="openDialog(bus)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive"
                            title="Sil"
                            @click="remove(bus)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>

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
