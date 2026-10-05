<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Bus as BusIcon, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import VehicleTypeController from '@/actions/App/Http/Controllers/VehicleTypeController';
import BusDiagram from '@/components/buses/BusDiagram.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { index } from '@/routes/vehicle-types';
import { busGrid } from '@/types/bus';
import type { VehicleBody, VehicleTypeRow } from '@/types/bus';
import type { Option } from '@/types/person';

defineProps<{ types: VehicleTypeRow[]; bodies: Option<VehicleBody>[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'Araç tipleri', href: index() },
        ],
    },
});

const open = ref(false);
const editing = ref<VehicleTypeRow | null>(null);
const shape = reactive({
    left: 2,
    right: 2,
    rows: 11,
    back: 5,
    door: '' as string,
    front: 0,
    body: 'otobus' as VehicleBody,
});

// Hazır şablonlar: tek tıkla doldurulur, sonra değiştirilebilir.
const presets = [
    {
        name: 'Standart otobüs (2+2)',
        left: 2,
        right: 2,
        rows: 11,
        back: 5,
        door: '6',
        front: 0,
        body: 'otobus' as VehicleBody,
    },
    {
        name: 'VIP otobüs (2+1)',
        left: 2,
        right: 1,
        rows: 10,
        back: 0,
        door: '6',
        front: 0,
        body: 'otobus' as VehicleBody,
    },
    {
        name: 'Midibüs (2+1)',
        left: 2,
        right: 1,
        rows: 8,
        back: 4,
        door: '',
        front: 0,
        body: 'midibus' as VehicleBody,
    },
    {
        name: 'Minibüs / Sprinter (2+1)',
        left: 2,
        right: 1,
        rows: 4,
        back: 4,
        door: '',
        front: 2,
        body: 'minibus' as VehicleBody,
    },
    {
        name: 'VIP van (1+1)',
        left: 1,
        right: 1,
        rows: 2,
        back: 3,
        door: '',
        front: 1,
        body: 'van' as VehicleBody,
    },
];
const presetName = ref('');

function applyPreset(preset: (typeof presets)[number]): void {
    Object.assign(shape, preset);
    presetName.value = preset.name;
}

function openDialog(type: VehicleTypeRow | null): void {
    editing.value = type;
    presetName.value = type?.name ?? '';

    if (type) {
        Object.assign(shape, {
            left: type.left_seats,
            right: type.right_seats,
            rows: type.rows,
            back: type.back_row_seats,
            door: type.door_row ? String(type.door_row) : '',
            front: type.front_seats,
            body: type.body,
        });
    } else {
        applyPreset(presets[0]);
    }

    open.value = true;
}

const preview = computed(() =>
    busGrid(
        Number(shape.left),
        Number(shape.right),
        Math.min(Math.max(Number(shape.rows) || 1, 1), 20),
        Math.min(
            Number(shape.back) || 0,
            Number(shape.left) + Number(shape.right) + 1,
        ),
        shape.door ? Number(shape.door) : null,
        Math.min(
            Number(shape.front) || 0,
            Number(shape.left) - 1 + Number(shape.right),
        ),
    ),
);
const previewCount = computed(
    () => preview.value.flat().filter((c) => typeof c === 'number').length,
);

const form = computed(() =>
    editing.value
        ? VehicleTypeController.update.form(editing.value.id)
        : VehicleTypeController.store.form(),
);

function remove(type: VehicleTypeRow): void {
    const note = type.buses_count
        ? ` ${type.buses_count} otobüste kullanıldı; o otobüslerin koltuk düzeni korunur.`
        : '';

    if (confirm(`${type.name} silinsin mi?${note}`)) {
        router.delete(VehicleTypeController.destroy.url(type.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Araç tipleri" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    Araç tipleri
                </h2>
                <p class="text-sm text-muted-foreground">
                    Kullandığınız otobüs, midibüs, minibüs ve vanların koltuk
                    düzenleri. Tura araç eklerken buradan seçilir.
                </p>
            </div>
            <Button @click="openDialog(null)"><Plus /> Araç tipi ekle</Button>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <div
                    v-if="types.length === 0"
                    class="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground"
                >
                    <BusIcon class="size-8" />
                    Henüz araç tipi yok.
                    <Button size="sm" @click="openDialog(null)">
                        <Plus /> Araç tipi ekle
                    </Button>
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="type in types"
                        :key="type.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm"
                    >
                        <BusDiagram
                            :body="type.body"
                            :grid="
                                busGrid(
                                    type.left_seats,
                                    type.right_seats,
                                    Math.min(type.rows, 4),
                                    0,
                                    null,
                                    type.front_seats,
                                )
                            "
                            compact
                            class="hidden scale-75 sm:inline-flex"
                        />
                        <div class="min-w-48 flex-1">
                            <div class="font-medium">{{ type.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ type.body_label }} · {{ type.label }} ·
                                {{ type.rows }} sıra
                                <template v-if="type.front_seats">
                                    · şoför yanı {{ type.front_seats }}
                                </template>
                                <template v-if="type.back_row_seats">
                                    + arka sıra {{ type.back_row_seats }}
                                </template>
                                <template v-if="type.door_row">
                                    · orta kapı {{ type.door_row }}. sırada
                                </template>
                            </div>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{
                                type.buses_count
                                    ? `${type.buses_count} otobüste kullanıldı`
                                    : 'Henüz kullanılmadı'
                            }}
                        </span>
                        <div class="flex">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Düzenle"
                                @click="openDialog(type)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="text-destructive"
                                title="Sil"
                                @click="remove(type)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="form"
                class="grid gap-6 md:grid-cols-[1fr_auto]"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <div class="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? editing.name : 'Araç tipi ekle' }}
                        </DialogTitle>
                        <DialogDescription>
                            Koltuklar önden arkaya, soldan sağa numaralanır.
                            Değişiklik mevcut otobüsleri etkilemez.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="!editing" class="flex flex-wrap gap-1">
                        <Button
                            v-for="preset in presets"
                            :key="preset.name"
                            type="button"
                            size="sm"
                            :variant="
                                presetName === preset.name
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="applyPreset(preset)"
                        >
                            {{ preset.name }}
                        </Button>
                    </div>

                    <div class="grid gap-2">
                        <Label for="vt-name">Ad *</Label>
                        <Input
                            id="vt-name"
                            v-model="presetName"
                            name="name"
                            placeholder="Örn. Mercedes Travego 2+2"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="vt-body">Gövde</Label>
                            <select
                                id="vt-body"
                                v-model="shape.body"
                                name="body"
                                :class="selectClass"
                            >
                                <option
                                    v-for="body in bodies"
                                    :key="body.value"
                                    :value="body.value"
                                >
                                    {{ body.label }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="vt-front">Şoför yanı koltuk</Label>
                            <select
                                id="vt-front"
                                v-model.number="shape.front"
                                name="front_seats"
                                :class="selectClass"
                            >
                                <option :value="0">Yok</option>
                                <option :value="1">1</option>
                                <option :value="2">2</option>
                            </select>
                            <InputError :message="errors.front_seats" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="vt-left">Sol koltuk</Label>
                            <select
                                id="vt-left"
                                v-model.number="shape.left"
                                name="left_seats"
                                :class="selectClass"
                            >
                                <option :value="1">1</option>
                                <option :value="2">2</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="vt-right">Sağ koltuk</Label>
                            <select
                                id="vt-right"
                                v-model.number="shape.right"
                                name="right_seats"
                                :class="selectClass"
                            >
                                <option :value="1">1</option>
                                <option :value="2">2</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="vt-rows">Sıra sayısı *</Label>
                            <Input
                                id="vt-rows"
                                v-model.number="shape.rows"
                                name="rows"
                                type="number"
                                min="1"
                                max="20"
                                required
                            />
                            <InputError :message="errors.rows" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="vt-back">Arka sıra koltuk</Label>
                            <Input
                                id="vt-back"
                                v-model.number="shape.back"
                                name="back_row_seats"
                                type="number"
                                min="0"
                                max="5"
                            />
                            <InputError :message="errors.back_row_seats" />
                        </div>
                        <div class="col-span-2 grid gap-2">
                            <Label for="vt-door">
                                Orta kapı hangi sırada? (o sıranın sağında
                                koltuk olmaz)
                            </Label>
                            <Input
                                id="vt-door"
                                v-model="shape.door"
                                name="door_row"
                                type="number"
                                min="1"
                                placeholder="Yoksa boş bırakın"
                            />
                            <InputError :message="errors.door_row" />
                        </div>
                    </div>
                    <p class="text-sm font-medium">
                        Toplam {{ previewCount }} yolcu koltuğu
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="open = false"
                        >
                            Vazgeç
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Kaydet
                        </Button>
                    </DialogFooter>
                </div>
                <div class="flex items-start justify-center">
                    <BusDiagram :grid="preview" :body="shape.body" compact />
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
