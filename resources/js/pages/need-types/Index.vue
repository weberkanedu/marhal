<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { HeartPulse, Lock, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import NeedTypeController from '@/actions/App/Http/Controllers/NeedTypeController';
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
import { index } from '@/routes/need-types';
import type { Option } from '@/types/person';

type NeedTypeRow = {
    id: string;
    name: string;
    category: string;
    category_label: string;
    effect: string | null;
    effect_label: string | null;
    airline_code: string | null;
    is_active: boolean;
};

const props = defineProps<{
    types: NeedTypeRow[];
    profiles: number;
    categories: Option[];
    effects: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'İhtiyaç türleri', href: index() },
        ],
    },
});

const open = ref(false);
const editing = ref<NeedTypeRow | null>(null);

function openDialog(type: NeedTypeRow | null): void {
    editing.value = type;
    open.value = true;
}

const form = computed(() =>
    editing.value
        ? NeedTypeController.update.form(editing.value.id)
        : NeedTypeController.store.form(),
);

const grouped = computed(() =>
    props.categories
        .map((c) => ({
            ...c,
            types: props.types.filter((t) => t.category === c.value),
        }))
        .filter((c) => c.types.length > 0),
);

function toggle(type: NeedTypeRow): void {
    router.put(
        NeedTypeController.update.url(type.id),
        {
            name: type.name,
            category: type.category,
            effect: type.effect,
            airline_code: type.airline_code,
            is_active: !type.is_active,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="İhtiyaç türleri" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    İhtiyaç türleri
                </h2>
                <p
                    class="flex items-center gap-1 text-sm text-muted-foreground"
                >
                    <Lock class="size-3.5" /> Yolcu sayfasındaki "İhtiyaçlar"
                    seçenekleri. Bilgiler şifreli saklanır;
                    {{ profiles }} yolcuda kayıtlı.
                </p>
            </div>
            <Button @click="openDialog(null)"><Plus /> Tür ekle</Button>
        </div>

        <Card class="py-0">
            <CardContent class="p-0">
                <div v-for="group in grouped" :key="group.value">
                    <div
                        class="border-b bg-muted px-4 py-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ group.label }}
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="type in group.types"
                            :key="type.id"
                            class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-sm"
                            :class="{ 'opacity-50': !type.is_active }"
                        >
                            <HeartPulse class="size-4 text-muted-foreground" />
                            <span class="min-w-40 flex-1 font-medium">
                                {{ type.name }}
                            </span>
                            <Badge v-if="type.effect_label" variant="secondary">
                                {{ type.effect_label }}
                            </Badge>
                            <span
                                v-if="type.airline_code"
                                class="font-mono text-xs text-muted-foreground"
                                >{{ type.airline_code }}</span
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="toggle(type)"
                            >
                                {{ type.is_active ? 'Kapat' : 'Aç' }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Düzenle"
                                @click="openDialog(type)"
                            >
                                <Pencil />
                            </Button>
                        </li>
                    </ul>
                </div>
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
                        {{ editing ? editing.name : 'İhtiyaç türü ekle' }}
                    </DialogTitle>
                    <DialogDescription>
                        "Etki" seçilirse oda, koltuk ve uçak kurallarında
                        kullanılır.
                    </DialogDescription>
                </DialogHeader>
                <input
                    v-if="editing"
                    type="hidden"
                    name="is_active"
                    :value="editing.is_active ? 1 : 0"
                />
                <div class="grid gap-2">
                    <Label for="nt-name">Ad *</Label>
                    <Input
                        id="nt-name"
                        name="name"
                        :default-value="editing?.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="nt-category">Grup</Label>
                        <select
                            id="nt-category"
                            name="category"
                            :class="selectClass"
                        >
                            <option
                                v-for="c in categories"
                                :key="c.value"
                                :value="c.value"
                                :selected="
                                    (editing?.category ?? 'diger') === c.value
                                "
                            >
                                {{ c.label }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="nt-effect">Etki</Label>
                        <select
                            id="nt-effect"
                            name="effect"
                            :class="selectClass"
                        >
                            <option value="" :selected="!editing?.effect">
                                Yok (sadece bilgi)
                            </option>
                            <option
                                v-for="e in effects"
                                :key="e.value"
                                :value="e.value"
                                :selected="editing?.effect === e.value"
                            >
                                {{ e.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="nt-code">Havayolu kodu</Label>
                    <Input
                        id="nt-code"
                        name="airline_code"
                        :default-value="editing?.airline_code ?? ''"
                        placeholder="Örn. WCHR (özel yardım listesinde görünür)"
                    />
                    <InputError :message="errors.airline_code" />
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
