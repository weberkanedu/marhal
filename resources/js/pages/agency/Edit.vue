<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImageIcon, X } from '@lucide/vue';
import { ref } from 'vue';
import AgencySettingsController from '@/actions/App/Http/Controllers/AgencySettingsController';
import Heading from '@/components/Heading.vue';
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
import { selectClass, textareaClass } from '@/lib/formClasses';
import { edit } from '@/routes/agency';

const props = defineProps<{
    agency: {
        name: string;
        phone: string | null;
        email: string | null;
        website: string | null;
        address: string | null;
        city: string | null;
        tax_office: string | null;
        tax_no: string | null;
        diyanet_license_no: string | null;
        tursab_no: string | null;
        default_currency: string;
        logo_url: string | null;
        plan: string;
    };
    currencies: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: edit() },
            { title: 'Acente bilgileri', href: edit() },
        ],
    },
});

const logoPreview = ref<string | null>(props.agency.logo_url);
const removeLogo = ref(false);

function onLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        logoPreview.value = URL.createObjectURL(file);
        removeLogo.value = false;
    }
}
</script>

<template>
    <Head title="Acente bilgileri" />

    <div class="w-full max-w-3xl p-4">
        <Heading
            title="Acente bilgileri"
            :description="`Paketiniz: ${agency.plan}. Bu bilgiler PDF raporlarında ve yaka kartlarında kullanılır.`"
        />

        <Form
            v-bind="AgencySettingsController.update.form()"
            class="space-y-6"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Logo</CardTitle>
                    <CardDescription>
                        PNG, JPG veya WEBP, en fazla 2 MB. Geniş (yatay) logolar
                        raporlarda daha iyi görünür.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex items-center gap-4">
                    <div
                        class="relative flex h-20 w-48 items-center justify-center rounded-md border bg-muted/40"
                    >
                        <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            alt="Logo"
                            class="max-h-16 max-w-44 object-contain"
                        />
                        <ImageIcon
                            v-else
                            class="size-8 text-muted-foreground"
                        />
                        <button
                            v-if="logoPreview"
                            type="button"
                            class="absolute top-1 right-1 rounded-full bg-background/80 p-1"
                            title="Logoyu kaldır"
                            @click="
                                logoPreview = null;
                                removeLogo = true;
                            "
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                    <label
                        class="cursor-pointer text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {{ logoPreview ? 'Değiştir' : 'Logo yükle' }}
                        <input
                            type="file"
                            name="logo"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="onLogo"
                        />
                    </label>
                    <input
                        type="hidden"
                        name="remove_logo"
                        :value="removeLogo ? '1' : '0'"
                    />
                    <InputError :message="errors.logo" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Firma bilgileri</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="name">Acente adı *</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="agency.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Telefon</Label>
                        <Input
                            id="phone"
                            name="phone"
                            :default-value="agency.phone ?? undefined"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">E-posta</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="agency.email ?? undefined"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="website">Web sitesi</Label>
                        <Input
                            id="website"
                            name="website"
                            :default-value="agency.website ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="city">Şehir</Label>
                        <Input
                            id="city"
                            name="city"
                            :default-value="agency.city ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tursab_no">TÜRSAB belge no</Label>
                        <Input
                            id="tursab_no"
                            name="tursab_no"
                            :default-value="agency.tursab_no ?? undefined"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tax_office">Vergi dairesi</Label>
                        <Input
                            id="tax_office"
                            name="tax_office"
                            :default-value="agency.tax_office ?? undefined"
                        />
                        <InputError :message="errors.tax_office" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tax_no">Vergi no</Label>
                        <Input
                            id="tax_no"
                            name="tax_no"
                            inputmode="numeric"
                            :default-value="agency.tax_no ?? undefined"
                        />
                        <InputError :message="errors.tax_no" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="diyanet_license_no">Diyanet yetki no</Label>
                        <Input
                            id="diyanet_license_no"
                            name="diyanet_license_no"
                            :default-value="
                                agency.diyanet_license_no ?? undefined
                            "
                        />
                        <InputError :message="errors.diyanet_license_no" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="address">Adres</Label>
                        <textarea
                            id="address"
                            name="address"
                            rows="2"
                            :class="textareaClass"
                            >{{ agency.address ?? '' }}</textarea>
                    </div>
                    <div class="grid gap-2">
                        <Label for="default_currency">
                            Varsayılan paket para birimi
                        </Label>
                        <select
                            id="default_currency"
                            name="default_currency"
                            :class="selectClass"
                        >
                            <option
                                v-for="currency in currencies"
                                :key="currency"
                                :value="currency"
                                :selected="agency.default_currency === currency"
                            >
                                {{ currency }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Yeni turlarda önerilen para birimi.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" :disabled="processing">Kaydet</Button>
        </Form>
    </div>
</template>
