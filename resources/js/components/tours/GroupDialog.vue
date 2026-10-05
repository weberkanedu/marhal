<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import GroupController from '@/actions/App/Http/Controllers/GroupController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import type { TourGroup } from '@/types/tour';

const props = defineProps<{
    tourId: string;
    group: TourGroup | null;
    guides: { id: number; name: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const guideUserId = ref<string>('');
// Grup rengi (App\Support\GroupColors::PALETTE ile aynı).
const palette = [
    '#0f6b4e',
    '#9c3d2e',
    '#1d4f91',
    '#b07d12',
    '#6b3fa0',
    '#2f7d86',
];
const color = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        guideUserId.value = props.group?.guide_user_id
            ? String(props.group.guide_user_id)
            : '';
        color.value = props.group?.color_chosen ? props.group.color : null;
    }
});

const form = computed(() =>
    props.group
        ? GroupController.update.form(props.group.id)
        : GroupController.store.form(props.tourId),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <Form
                v-bind="form"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{ group ? 'Grubu düzenle' : 'Yeni grup' }}
                    </DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="group-name">Grup adı *</Label>
                    <Input
                        id="group-name"
                        name="name"
                        :default-value="group?.name"
                        placeholder="Örn. B Grubu"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div v-if="guides.length > 0" class="grid gap-2">
                    <Label for="guide_user_id"
                        >Rehber (sistem kullanıcısı)</Label
                    >
                    <select
                        id="guide_user_id"
                        v-model="guideUserId"
                        name="guide_user_id"
                        :class="selectClass"
                    >
                        <option value="">— Seçilmedi —</option>
                        <option
                            v-for="guide in guides"
                            :key="guide.id"
                            :value="String(guide.id)"
                        >
                            {{ guide.name }}
                        </option>
                    </select>
                    <InputError :message="errors.guide_user_id" />
                </div>

                <div v-if="!guideUserId" class="grid gap-2">
                    <Label for="guide_name">Rehber / grup sorumlusu</Label>
                    <Input
                        id="guide_name"
                        name="guide_name"
                        :default-value="group?.guide_name ?? undefined"
                        placeholder="Ad Soyad"
                    />
                    <InputError :message="errors.guide_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="guide_phone">Rehber telefonu</Label>
                    <Input
                        id="guide_phone"
                        name="guide_phone"
                        type="tel"
                        :default-value="group?.guide_phone ?? undefined"
                    />
                    <InputError :message="errors.guide_phone" />
                </div>

                <div class="grid gap-2">
                    <Label>Renk (yaka kartı bandı, otobüs tabelası)</Label>
                    <input type="hidden" name="color" :value="color ?? ''" />
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="swatch in palette"
                            :key="swatch"
                            type="button"
                            class="size-8 rounded-full border-2 transition"
                            :class="
                                color === swatch
                                    ? 'scale-110 border-foreground'
                                    : 'border-transparent'
                            "
                            :style="{ background: swatch }"
                            :aria-label="`Renk ${swatch}`"
                            :aria-pressed="color === swatch"
                            @click="color = color === swatch ? null : swatch"
                        />
                        <span class="text-xs text-muted-foreground">
                            {{ color ? '' : 'Seçilmezse otomatik verilir' }}
                        </span>
                    </div>
                    <InputError :message="errors.color" />
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
