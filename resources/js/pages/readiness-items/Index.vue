<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ListChecks, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReadinessItemController from '@/actions/App/Http/Controllers/ReadinessItemController';
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
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/readiness-items';
import type { Option } from '@/types/person';

/**
 * Acente ayarları → Hazırlık maddeleri: turdaki "Hazırlık" sekmesinin sütunları. Maddeler silinmez,
 * kapatılır; "yeni turlarda seçili" olanlar yeni bir turda kendiliğinden takip edilir.
 */
type ItemRow = {
    id: string;
    name: string;
    kind: string;
    kind_label: string;
    default_on: boolean;
    is_active: boolean;
};

defineProps<{
    items: ItemRow[];
    checks: number;
    kinds: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'Hazırlık maddeleri', href: index() },
        ],
    },
});

const open = ref(false);
const editing = ref<ItemRow | null>(null);

function openDialog(item: ItemRow | null): void {
    editing.value = item;
    open.value = true;
}

const form = computed(() =>
    editing.value
        ? ReadinessItemController.update.form(editing.value.id)
        : ReadinessItemController.store.form(),
);

function save(item: ItemRow, changes: Partial<ItemRow>): void {
    router.put(
        ReadinessItemController.update.url(item.id),
        {
            name: item.name,
            kind: item.kind,
            default_on: item.default_on,
            is_active: item.is_active,
            ...changes,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Hazırlık maddeleri" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    Hazırlık maddeleri
                </h2>
                <p class="text-sm text-muted-foreground">
                    Turdaki "Hazırlık" tablosunun sütunları. Pasaport ve
                    fotoğraf yolcu bilgisinden kendiliğinden dolar;
                    {{ checks }} işaretleme kayıtlı.
                </p>
            </div>
            <Button @click="openDialog(null)"><Plus /> Madde ekle</Button>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <ul class="divide-y">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-sm"
                        :class="{ 'opacity-50': !item.is_active }"
                    >
                        <ListChecks class="size-4 text-muted-foreground" />
                        <span class="min-w-40 flex-1 font-medium">
                            {{ item.name }}
                        </span>
                        <Badge variant="secondary">{{ item.kind_label }}</Badge>
                        <label
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <input
                                type="checkbox"
                                :checked="item.default_on"
                                :disabled="!item.is_active"
                                @change="
                                    save(item, { default_on: !item.default_on })
                                "
                            />
                            Yeni turlarda seçili
                        </label>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="save(item, { is_active: !item.is_active })"
                        >
                            {{ item.is_active ? 'Kapat' : 'Aç' }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Düzenle"
                            @click="openDialog(item)"
                        >
                            <Pencil />
                        </Button>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
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
                        {{ editing ? editing.name : 'Hazırlık maddesi ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        Örn. "Mest", "Kurban vekâleti", "Bilgilendirme
                        toplantısı".
                    </DialogDescription>
                </DialogHeader>
                <template v-if="editing">
                    <input
                        type="hidden"
                        name="is_active"
                        :value="editing.is_active ? 1 : 0"
                    />
                </template>
                <div class="grid gap-2">
                    <Label for="ri-name">Ad *</Label>
                    <Input
                        id="ri-name"
                        name="name"
                        maxlength="60"
                        :default-value="editing?.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="ri-kind">Nasıl dolar?</Label>
                    <select id="ri-kind" name="kind" :class="selectClass">
                        <option
                            v-for="k in kinds"
                            :key="k.value"
                            :value="k.value"
                            :selected="(editing?.kind ?? 'elle') === k.value"
                        >
                            {{ k.label }}
                        </option>
                    </select>
                    <InputError :message="errors.kind" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="default_on" value="0" />
                    <input
                        type="checkbox"
                        name="default_on"
                        value="1"
                        :checked="editing?.default_on ?? true"
                    />
                    Yeni turlarda seçili gelsin
                </label>
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
