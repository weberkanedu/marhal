<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Hotel as HotelIcon, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import HotelController from '@/actions/App/Http/Controllers/HotelController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { selectClass, textareaClass } from '@/lib/formClasses';
import { index } from '@/routes/hotels';
import type { HotelRow } from '@/types/hotel';
import type { Option } from '@/types/person';

const props = defineProps<{
    hotels: HotelRow[];
    cities: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'Oteller', href: index() },
        ],
    },
});

const cityLabel = (value: string) =>
    props.cities.find((c) => c.value === value)?.label ?? value;

const cityFilter = ref<string>('all');
const visibleHotels = computed(() =>
    cityFilter.value === 'all'
        ? props.hotels
        : props.hotels.filter((h) => h.city === cityFilter.value),
);

const dialogOpen = ref(false);
const editing = ref<HotelRow | null>(null);

const form = computed(() =>
    editing.value
        ? HotelController.update.form(editing.value.id)
        : HotelController.store.form(),
);

function openDialog(hotel: HotelRow | null): void {
    editing.value = hotel;
    dialogOpen.value = true;
}

function remove(hotel: HotelRow): void {
    if (confirm(`${hotel.name} silinsin mi?`)) {
        router.delete(HotelController.destroy.url(hotel.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Oteller" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Oteller</h2>
                <p class="text-sm text-muted-foreground">
                    Çalıştığınız oteller. Turun sayfasında "Konaklama"
                    bölümünden gruplara atanır.
                </p>
            </div>
            <Button @click="openDialog(null)"><Plus /> Otel ekle</Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="cityFilter === 'all' ? 'default' : 'outline'"
                @click="cityFilter = 'all'"
            >
                Tümü ({{ hotels.length }})
            </Button>
            <Button
                v-for="city in cities"
                :key="city.value"
                size="sm"
                :variant="cityFilter === city.value ? 'default' : 'outline'"
                @click="cityFilter = city.value"
            >
                {{ city.label }}
                ({{ hotels.filter((h) => h.city === city.value).length }})
            </Button>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <div
                    v-if="visibleHotels.length === 0"
                    class="flex flex-col items-center gap-3 p-10 text-sm text-muted-foreground"
                >
                    <HotelIcon class="size-8" />
                    Henüz otel eklenmedi.
                    <Button size="sm" @click="openDialog(null)">
                        <Plus /> Otel ekle
                    </Button>
                </div>

                <ul v-else class="divide-y">
                    <li
                        v-for="hotel in visibleHotels"
                        :key="hotel.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm"
                    >
                        <div class="min-w-48 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{
                                    hotel.name
                                }}</span>
                                <Badge variant="secondary">
                                    {{ cityLabel(hotel.city) }}
                                </Badge>
                                <span
                                    v-if="hotel.stars"
                                    class="flex items-center text-muted-foreground"
                                    :title="`${hotel.stars} yıldız`"
                                >
                                    <Star
                                        v-for="n in hotel.stars"
                                        :key="n"
                                        class="size-3 fill-current"
                                    />
                                </span>
                            </div>
                            <div
                                v-if="hotel.address || hotel.phone"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    [hotel.address, hotel.phone]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </div>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{
                                hotel.stays_count > 0
                                    ? `${hotel.stays_count} turda kullanıldı`
                                    : 'Henüz kullanılmadı'
                            }}
                        </span>
                        <div class="flex">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Düzenle"
                                @click="openDialog(hotel)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                v-if="hotel.stays_count === 0"
                                variant="ghost"
                                size="icon-sm"
                                class="text-destructive"
                                title="Sil"
                                @click="remove(hotel)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <Form
                :key="editing?.id ?? 'new'"
                v-bind="form"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="dialogOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? editing.name : 'Otel ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Otel bir kez eklenir, sonraki turlarda tekrar seçilir.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="hotel-name">Otel adı *</Label>
                    <Input
                        id="hotel-name"
                        name="name"
                        :default-value="editing?.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="hotel-city">Şehir *</Label>
                        <select
                            id="hotel-city"
                            name="city"
                            :class="selectClass"
                        >
                            <option
                                v-for="city in cities"
                                :key="city.value"
                                :value="city.value"
                                :selected="
                                    (editing?.city ??
                                        (cityFilter !== 'all'
                                            ? cityFilter
                                            : 'mekke')) === city.value
                                "
                            >
                                {{ city.label }}
                            </option>
                        </select>
                        <InputError :message="errors.city" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="hotel-stars">Yıldız</Label>
                        <select
                            id="hotel-stars"
                            name="stars"
                            :class="selectClass"
                        >
                            <option value="">—</option>
                            <option
                                v-for="n in [5, 4, 3, 2, 1]"
                                :key="n"
                                :value="n"
                                :selected="editing?.stars === n"
                            >
                                {{ n }} yıldız
                            </option>
                        </select>
                        <InputError :message="errors.stars" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="hotel-address">Adres</Label>
                    <Input
                        id="hotel-address"
                        name="address"
                        :default-value="editing?.address ?? undefined"
                    />
                    <InputError :message="errors.address" />
                </div>
                <div class="grid gap-2">
                    <Label for="hotel-phone">Telefon</Label>
                    <Input
                        id="hotel-phone"
                        name="phone"
                        type="tel"
                        :default-value="editing?.phone ?? undefined"
                    />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="hotel-notes">Notlar</Label>
                    <textarea
                        id="hotel-notes"
                        name="notes"
                        :class="textareaClass"
                        :value="editing?.notes ?? ''"
                    />
                    <InputError :message="errors.notes" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="dialogOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">Kaydet</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
