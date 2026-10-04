<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import type { FormComponentProps } from '@inertiajs/core';
import { Camera, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PersonDetail, PersonFormOptions } from '@/types/person';

const props = defineProps<{
    form: Pick<FormComponentProps, 'action' | 'method'>;
    options: PersonFormOptions;
    person?: PersonDetail;
    submitLabel: string;
}>();

const selectClass =
    'border-input dark:bg-input/30 h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';
const textareaClass =
    'border-input dark:bg-input/30 min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const isEdit = computed(() => props.person !== undefined);

const photoPreview = ref<string | null>(props.person?.photo_url ?? null);
const removePhoto = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);

function onPhotoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        photoPreview.value = URL.createObjectURL(file);
        removePhoto.value = false;
    }
}

function clearPhoto(): void {
    photoPreview.value = null;
    removePhoto.value = true;

    if (photoInput.value) {
        photoInput.value.value = '';
    }
}
</script>

<template>
    <Form
        v-bind="form"
        class="space-y-6"
        v-slot="{ errors, processing }"
        :options="{ preserveScroll: true }"
    >
        <Card>
            <CardHeader>
                <CardTitle>Kişisel bilgiler</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-6 md:grid-cols-[160px_1fr]">
                <!-- Fotoğraf -->
                <div class="flex flex-col items-center gap-2">
                    <div
                        class="relative flex size-36 items-center justify-center overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            alt="Fotoğraf"
                            class="size-full object-cover"
                        />
                        <Camera v-else class="size-10 text-muted-foreground" />
                        <button
                            v-if="photoPreview"
                            type="button"
                            class="absolute top-1 right-1 rounded-full bg-background/80 p-1"
                            title="Fotoğrafı kaldır"
                            @click="clearPhoto"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <label
                        class="cursor-pointer text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {{ photoPreview ? 'Değiştir' : 'Fotoğraf yükle' }}
                        <input
                            ref="photoInput"
                            type="file"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onPhotoChange"
                        />
                    </label>
                    <input
                        type="hidden"
                        name="remove_photo"
                        :value="removePhoto ? '1' : '0'"
                    />
                    <InputError :message="errors.photo" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">Ad *</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            :default-value="person?.first_name"
                            required
                            autofocus
                        />
                        <InputError :message="errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">Soyad *</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            :default-value="person?.last_name"
                            required
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="gender">Cinsiyet *</Label>
                        <select
                            id="gender"
                            name="gender"
                            :class="selectClass"
                            required
                        >
                            <option value="" :selected="!person">
                                Seçiniz
                            </option>
                            <option
                                v-for="option in options.genders"
                                :key="option.value"
                                :value="option.value"
                                :selected="person?.gender === option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.gender" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="birth_date">Doğum tarihi</Label>
                        <Input
                            id="birth_date"
                            name="birth_date"
                            type="date"
                            :default-value="person?.birth_date ?? undefined"
                        />
                        <InputError :message="errors.birth_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Telefon</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            :default-value="person?.phone ?? undefined"
                            placeholder="05xx xxx xx xx"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">E-posta</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="person?.email ?? undefined"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="address">Adres</Label>
                        <textarea
                            id="address"
                            name="address"
                            rows="2"
                            :class="textareaClass"
                            >{{ person?.address ?? '' }}</textarea>
                        <InputError :message="errors.address" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Kimlik ve pasaport</CardTitle>
                <CardDescription>
                    Bu bilgiler şifreli saklanır. Listelerde maskeli görünür.
                    <template v-if="isEdit">
                        Değiştirmek istemiyorsanız boş bırakın.
                    </template>
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nationality">Uyruk</Label>
                    <select
                        id="nationality"
                        name="nationality"
                        :class="selectClass"
                    >
                        <option
                            value="TR"
                            :selected="!person || person.nationality === 'TR'"
                        >
                            Türkiye
                        </option>
                        <option
                            value="XX"
                            :selected="person && person.nationality !== 'TR'"
                        >
                            Diğer
                        </option>
                    </select>
                    <InputError :message="errors.nationality" />
                </div>
                <div class="grid gap-2">
                    <Label for="national_id">T.C. Kimlik No</Label>
                    <Input
                        id="national_id"
                        name="national_id"
                        inputmode="numeric"
                        maxlength="11"
                        autocomplete="off"
                        :placeholder="person?.masked_national_id ?? '11 haneli'"
                    />
                    <InputError :message="errors.national_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="passport_no">Pasaport No</Label>
                    <Input
                        id="passport_no"
                        name="passport_no"
                        autocomplete="off"
                        :placeholder="person?.masked_passport_no ?? 'U12345678'"
                    />
                    <InputError :message="errors.passport_no" />
                </div>
                <div class="hidden sm:block" />
                <div class="grid gap-2">
                    <Label for="passport_issue_date"
                        >Pasaport veriliş tarihi</Label
                    >
                    <Input
                        id="passport_issue_date"
                        name="passport_issue_date"
                        type="date"
                        :default-value="
                            person?.passport_issue_date ?? undefined
                        "
                    />
                    <InputError :message="errors.passport_issue_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="passport_expiry_date">
                        Pasaport geçerlilik tarihi
                    </Label>
                    <Input
                        id="passport_expiry_date"
                        name="passport_expiry_date"
                        type="date"
                        :default-value="
                            person?.passport_expiry_date ?? undefined
                        "
                    />
                    <InputError :message="errors.passport_expiry_date" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Acil durum ve notlar</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="emergency_contact_name"
                        >Acil durum kişisi</Label
                    >
                    <Input
                        id="emergency_contact_name"
                        name="emergency_contact_name"
                        :default-value="
                            person?.emergency_contact_name ?? undefined
                        "
                        placeholder="Ad Soyad (yakınlık)"
                    />
                    <InputError :message="errors.emergency_contact_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="emergency_contact_phone">
                        Acil durum telefonu
                    </Label>
                    <Input
                        id="emergency_contact_phone"
                        name="emergency_contact_phone"
                        type="tel"
                        :default-value="
                            person?.emergency_contact_phone ?? undefined
                        "
                    />
                    <InputError :message="errors.emergency_contact_phone" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="notes">Notlar</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        :class="textareaClass"
                        placeholder="Sağlık durumu, özel istekler vb."
                        >{{ person?.notes ?? '' }}</textarea>
                    <InputError :message="errors.notes" />
                </div>
                <label class="flex items-start gap-2 text-sm sm:col-span-2">
                    <input type="hidden" name="kvkk_consent" value="0" />
                    <input
                        type="checkbox"
                        name="kvkk_consent"
                        value="1"
                        class="mt-0.5"
                        :checked="person?.kvkk_consent ?? false"
                    />
                    <span>
                        Yolcudan KVKK aydınlatma metni onayı / açık rıza alındı.
                    </span>
                </label>
            </CardContent>
        </Card>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                {{ submitLabel }}
            </Button>
            <slot name="actions" />
        </div>
    </Form>
</template>
