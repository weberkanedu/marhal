<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import { BedDouble, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import TourHotelController from '@/actions/App/Http/Controllers/TourHotelController';
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
import { formatDate } from '@/lib/format';
import { selectClass, textareaClass } from '@/lib/formClasses';
import { index as hotelsIndex } from '@/routes/hotels';
import type { HotelOption, TourStay } from '@/types/hotel';
import type { TourGroup, TourSummary } from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    stays: TourStay[];
    groups: TourGroup[];
    hotels: HotelOption[];
    canUpdate: boolean;
}>();

const open = ref(false);
const editing = ref<TourStay | null>(null);

const form = computed(() =>
    editing.value
        ? TourHotelController.update.form(editing.value.id)
        : TourHotelController.store.form(props.tour.id),
);

// Yeni konaklamada bütün gruplar seçili gelir (tek gruplu turlarda en sık durum).
function defaultChecked(groupId: string): boolean {
    if (editing.value) {
        return editing.value.groups.some((g) => g.id === groupId);
    }

    return true;
}

function openDialog(stay: TourStay | null): void {
    editing.value = stay;
    open.value = true;
}

function remove(stay: TourStay): void {
    if (
        confirm(
            `${stay.hotel_name} konaklaması turdan kaldırılsın mı? (Otel, otel listenizde kalır.)`,
        )
    ) {
        router.delete(TourHotelController.destroy.url(stay.id), {
            preserveScroll: true,
        });
    }
}

const cityVariant = (city: string) =>
    city === 'mekke' ? 'default' : city === 'medine' ? 'secondary' : 'outline';
</script>

<template>
    <Card class="min-w-0">
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle class="flex items-center gap-2">
                <BedDouble class="size-4" /> Konaklama
            </CardTitle>
            <Button
                v-if="canUpdate"
                variant="ghost"
                size="sm"
                @click="openDialog(null)"
            >
                <Plus /> Otel ekle
            </Button>
        </CardHeader>
        <CardContent class="text-sm">
            <p v-if="stays.length === 0" class="text-muted-foreground">
                Henüz otel eklenmedi.
                <template v-if="canUpdate">
                    Mekke ve Medine otellerini gruplara göre ekleyin.
                </template>
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="stay in stays"
                    :key="stay.id"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2"
                >
                    <Badge :variant="cityVariant(stay.city)" class="w-16">
                        {{ stay.city_label }}
                    </Badge>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ stay.hotel_name }}</div>
                        <div class="text-xs text-muted-foreground">
                            {{ formatDate(stay.check_in) }} –
                            {{ formatDate(stay.check_out) }} ·
                            {{ stay.nights }} gece
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="group in stay.groups"
                            :key="group.id"
                            variant="outline"
                        >
                            {{ group.name }}
                        </Badge>
                        <span
                            v-if="stay.groups.length === 0"
                            class="text-xs text-warning"
                        >
                            Grup seçilmedi (sadece tek tek yerleştirilen
                            yolcular)
                        </span>
                    </div>
                    <div v-if="canUpdate" class="flex">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Düzenle"
                            @click="openDialog(stay)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive"
                            title="Kaldır"
                            @click="remove(stay)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>

    <Dialog v-model:open="open">
        <DialogContent>
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
                        {{ editing ? 'Konaklamayı düzenle' : 'Otel ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Hangi grupların hangi otelde, hangi tarihlerde
                        kalacağını seçin. Gruptan farklı otelde kalacak yolcular
                        oda planında tek tek yerleştirilir.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="hotels.length === 0" class="text-sm">
                    Otel listeniz boş.
                    <Link :href="hotelsIndex()" class="underline">
                        Önce otel ekleyin.
                    </Link>
                </div>

                <template v-else>
                    <div class="grid gap-2">
                        <Label for="stay-hotel">Otel *</Label>
                        <select
                            id="stay-hotel"
                            name="hotel_id"
                            :class="selectClass"
                            required
                        >
                            <option value="">— Seçin —</option>
                            <option
                                v-for="hotel in hotels"
                                :key="hotel.id"
                                :value="hotel.id"
                                :selected="editing?.hotel_id === hotel.id"
                            >
                                {{ hotel.city_label }} — {{ hotel.name }}
                            </option>
                        </select>
                        <InputError :message="errors.hotel_id" />
                        <Link
                            :href="hotelsIndex()"
                            class="text-xs text-muted-foreground underline"
                        >
                            Listede yok mu? Otel listesine ekleyin.
                        </Link>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="stay-in">Giriş *</Label>
                            <Input
                                id="stay-in"
                                name="check_in"
                                type="date"
                                :default-value="
                                    editing?.check_in ?? tour.start_date
                                "
                                required
                            />
                            <InputError :message="errors.check_in" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stay-out">Çıkış *</Label>
                            <Input
                                id="stay-out"
                                name="check_out"
                                type="date"
                                :default-value="
                                    editing?.check_out ?? tour.end_date
                                "
                                required
                            />
                            <InputError :message="errors.check_out" />
                        </div>
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">
                            Bu otelde kalan gruplar
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

                    <div class="grid gap-2">
                        <Label for="stay-notes">Notlar</Label>
                        <textarea
                            id="stay-notes"
                            name="notes"
                            :class="textareaClass"
                            :value="editing?.notes ?? ''"
                            placeholder="Örn. kahvaltı dahil, Harem'e 300 m"
                        />
                        <InputError :message="errors.notes" />
                    </div>
                </template>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing || hotels.length === 0"
                    >
                        Kaydet
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
