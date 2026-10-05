<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plane, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AircraftTypeController from '@/actions/App/Http/Controllers/AircraftTypeController';
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
import { index } from '@/routes/aircraft-types';

type AircraftTypeRow = {
    id: string;
    name: string;
    cabin: string;
    first_row: number;
    last_row: number;
    exit_rows: number[];
    notes: string | null;
    label: string;
    flights_count: number;
};

type Preset = {
    key: string;
    name: string;
    cabin: string;
    first_row: number;
    last_row: number;
    exit_rows: number[];
};

defineProps<{ types: AircraftTypeRow[]; presets: Preset[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'Uçak tipleri', href: index() },
        ],
    },
});

const open = ref(false);
const editing = ref<AircraftTypeRow | null>(null);

function openDialog(type: AircraftTypeRow | null): void {
    editing.value = type;
    open.value = true;
}

const form = computed(() =>
    editing.value
        ? AircraftTypeController.update.form(editing.value.id)
        : AircraftTypeController.store.form(),
);

function addPreset(preset: Preset): void {
    router.post(
        AircraftTypeController.store.url(),
        { preset: preset.key },
        { preserveScroll: true },
    );
}

function remove(type: AircraftTypeRow): void {
    const note = type.flights_count
        ? ` ${type.flights_count} uçuşta kullanıldı; o uçuşların planı korunur.`
        : '';

    if (confirm(`${type.name} silinsin mi?${note}`)) {
        router.delete(AircraftTypeController.destroy.url(type.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Uçak tipleri" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    Uçak tipleri
                </h2>
                <p class="text-sm text-muted-foreground">
                    Uçuşların koltuk planı bu düzenlerle çizilir. Havayolunuzun
                    sıra aralığına ve acil çıkışlarına göre düzenleyebilirsiniz.
                </p>
            </div>
            <Button @click="openDialog(null)"><Plus /> Uçak tipi ekle</Button>
        </div>

        <div v-if="presets.length" class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-muted-foreground">Hazır tipler:</span>
            <Button
                v-for="preset in presets"
                :key="preset.key"
                size="sm"
                variant="outline"
                @click="addPreset(preset)"
            >
                <Plus /> {{ preset.name }} ({{ preset.cabin }})
            </Button>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <div
                    v-if="types.length === 0"
                    class="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground"
                >
                    <Plane class="size-8" />
                    Henüz uçak tipi yok. Yukarıdaki hazır tiplerden ekleyin.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="type in types"
                        :key="type.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm"
                    >
                        <Plane class="size-5 text-muted-foreground" />
                        <div class="min-w-48 flex-1">
                            <div class="font-medium">{{ type.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ type.label }} · sıra {{ type.first_row }}–{{
                                    type.last_row
                                }}
                                <template v-if="type.exit_rows.length">
                                    · acil çıkış {{ type.exit_rows.join(', ') }}
                                </template>
                            </div>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{
                                type.flights_count
                                    ? `${type.flights_count} uçuşta kullanıldı`
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
        <DialogContent class="sm:max-w-lg">
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
                        {{ editing ? editing.name : 'Uçak tipi ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Değişiklik mevcut uçuşların koltuk planını etkilemez.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="at-name">Ad *</Label>
                    <Input
                        id="at-name"
                        name="name"
                        :default-value="editing?.name"
                        placeholder="Örn. THY A321neo"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="grid gap-2">
                        <Label for="at-cabin">Kabin düzeni *</Label>
                        <Input
                            id="at-cabin"
                            name="cabin"
                            :default-value="editing?.cabin ?? '3-3'"
                            placeholder="3-3"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="at-first">İlk sıra</Label>
                        <Input
                            id="at-first"
                            name="first_row"
                            type="number"
                            min="1"
                            :default-value="editing?.first_row ?? 1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="at-last">Son sıra</Label>
                        <Input
                            id="at-last"
                            name="last_row"
                            type="number"
                            min="1"
                            :default-value="editing?.last_row ?? 30"
                            required
                        />
                    </div>
                    <InputError
                        class="col-span-3"
                        :message="
                            errors.cabin ?? errors.first_row ?? errors.last_row
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="at-exit">Acil çıkış sıraları</Label>
                    <Input
                        id="at-exit"
                        name="exit_rows"
                        :default-value="editing?.exit_rows.join(', ') ?? ''"
                        placeholder="Örn. 11, 12, 25"
                    />
                    <p class="text-xs text-muted-foreground">
                        Bu sıralara 15 yaş altı ve 65 yaş üstü yolcu oturursa
                        uyarı verilir.
                    </p>
                    <InputError :message="errors.exit_rows" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">Kaydet</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
