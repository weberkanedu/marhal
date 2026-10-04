<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Check, Search, UserPlus } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
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
import { selectClass, textareaClass } from '@/lib/formClasses';
import { create as createPerson, lookup } from '@/routes/persons';
import { store } from '@/routes/tours/registrations';
import type { PersonListItem } from '@/types/person';
import type {
    RegistrationRow,
    TourGroup,
    TourShowOptions,
    TourSummary,
} from '@/types/tour';

type LookupResult = PersonListItem & { already_registered: boolean };

const props = defineProps<{
    tour: TourSummary;
    groups: TourGroup[];
    options: TourShowOptions;
    registration: RegistrationRow | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const search = ref('');
const results = ref<LookupResult[]>([]);
const searching = ref(false);
const selected = ref<LookupResult | null>(null);
const status = ref<string>('kesin_kayit');

const isEdit = computed(() => props.registration !== null);

const form = computed(() =>
    props.registration
        ? RegistrationController.update.form(props.registration.id)
        : store.form(props.tour.id),
);

// Tek grup varsa otomatik seçilir (tek gruplu turlarda grup adımı atlanır).
const defaultGroupId = computed(
    () =>
        props.registration?.group_id ??
        (props.groups.length === 1 ? props.groups[0].id : ''),
);

watch(open, (isOpen) => {
    if (isOpen) {
        search.value = '';
        results.value = [];
        selected.value = null;
        status.value = props.registration?.status ?? 'kesin_kayit';
    }
});

const runSearch = useDebounceFn(async (value: string) => {
    if (value.trim().length < 2) {
        results.value = [];

        return;
    }

    searching.value = true;

    try {
        const response = await fetch(
            lookup.url({ query: { q: value, exclude_tour: props.tour.id } }),
            { headers: { Accept: 'application/json' } },
        );
        results.value = response.ok ? await response.json() : [];
    } finally {
        searching.value = false;
    }
}, 300);

watch(search, (value) => runSearch(value));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <Form
                v-bind="form"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            isEdit
                                ? registration?.person.full_name
                                : 'Tura yolcu ekle'
                        }}
                    </DialogTitle>
                    <DialogDescription>{{ tour.name }}</DialogDescription>
                </DialogHeader>

                <!-- Yolcu seçimi (sadece yeni kayıtta) -->
                <div v-if="!isEdit" class="grid gap-2">
                    <Label>Yolcu *</Label>
                    <input
                        type="hidden"
                        name="person_id"
                        :value="selected?.id ?? ''"
                    />
                    <div
                        v-if="selected"
                        class="flex items-center justify-between rounded-md border bg-muted/40 px-3 py-2 text-sm"
                    >
                        <span class="flex items-center gap-2 font-medium">
                            <Check class="size-4 text-success" />
                            {{ selected.full_name }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="selected = null"
                        >
                            Değiştir
                        </Button>
                    </div>
                    <template v-else>
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Ad, soyad, telefon veya TC ile arayın"
                                autocomplete="off"
                            />
                        </div>
                        <ul
                            v-if="results.length > 0"
                            class="max-h-56 divide-y overflow-y-auto rounded-md border text-sm"
                        >
                            <li v-for="person in results" :key="person.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="person.already_registered"
                                    @click="selected = person"
                                >
                                    <span>
                                        <span class="font-medium">
                                            {{ person.full_name }}
                                        </span>
                                        <span
                                            class="ml-2 text-muted-foreground"
                                        >
                                            {{ person.phone }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="person.already_registered"
                                        class="text-xs"
                                    >
                                        Zaten kayıtlı
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p
                            v-else-if="search.trim().length >= 2 && !searching"
                            class="text-sm text-muted-foreground"
                        >
                            Sonuç yok.
                            <Link
                                :href="createPerson()"
                                class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                            >
                                <UserPlus class="size-3" /> Yeni yolcu oluştur
                            </Link>
                        </p>
                    </template>
                    <InputError :message="errors.person_id" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="group_id">Grup</Label>
                        <select
                            id="group_id"
                            name="group_id"
                            :class="selectClass"
                        >
                            <option value="" :selected="!defaultGroupId">
                                — Grupsuz —
                            </option>
                            <option
                                v-for="group in groups"
                                :key="group.id"
                                :value="group.id"
                                :selected="defaultGroupId === group.id"
                            >
                                {{ group.name }}
                            </option>
                        </select>
                        <InputError :message="errors.group_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="room_type">Oda tipi</Label>
                        <select
                            id="room_type"
                            name="room_type"
                            :class="selectClass"
                        >
                            <option value="">— Belirtilmedi —</option>
                            <option
                                v-for="option in options.roomTypes"
                                :key="option.value"
                                :value="option.value"
                                :selected="
                                    registration?.room_type === option.value
                                "
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.room_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="price">Fiyat ({{ tour.currency }}) *</Label>
                        <Input
                            id="price"
                            name="price"
                            type="number"
                            step="0.01"
                            min="0"
                            :default-value="
                                registration?.price ??
                                tour.default_price ??
                                undefined
                            "
                            required
                        />
                        <InputError :message="errors.price" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="discount"
                            >İndirim ({{ tour.currency }})</Label
                        >
                        <Input
                            id="discount"
                            name="discount"
                            type="number"
                            step="0.01"
                            min="0"
                            :default-value="registration?.discount ?? '0'"
                        />
                        <InputError :message="errors.discount" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="reg-status">Kayıt durumu</Label>
                        <select
                            id="reg-status"
                            v-model="status"
                            name="status"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in options.registrationStatuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div
                        v-if="status === 'iptal'"
                        class="grid gap-2 sm:col-span-2"
                    >
                        <Label for="cancel_reason">İptal sebebi</Label>
                        <Input
                            id="cancel_reason"
                            name="cancel_reason"
                            :default-value="
                                registration?.cancel_reason ?? undefined
                            "
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="reg-notes">Not</Label>
                        <textarea
                            id="reg-notes"
                            name="notes"
                            rows="2"
                            :class="textareaClass"
                            >{{ registration?.notes ?? '' }}</textarea>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
                        Vazgeç
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing || (!isEdit && !selected)"
                    >
                        {{ isEdit ? 'Kaydet' : 'Tura ekle' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
