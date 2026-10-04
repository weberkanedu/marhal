<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import RoomPlanController from '@/actions/App/Http/Controllers/RoomPlanController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';

type CopyPreview = {
    placed: number;
    placements: { room_no: string; from: string; names: string[] }[];
    unplaced: { name: string; reason: string }[];
};

/**
 * Başka bir otelin oda düzenini (kim kiminle kalıyor) bu otele kopyalar. Önce önizleme, sonra onay.
 */
const props = defineProps<{
    stayId: string;
    sources: { id: string; label: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const from = ref('');
const preview = ref<CopyPreview | null>(null);
const loading = ref(false);
const applying = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        from.value = props.sources[0]?.id ?? '';
    }
});

watch(from, async (value) => {
    preview.value = null;

    if (!value) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            RoomPlanController.copyPreview.url(props.stayId, {
                query: { from: value },
            }),
            { headers: { Accept: 'application/json' } },
        );
        preview.value = response.ok ? await response.json() : null;
    } finally {
        loading.value = false;
    }
});

function apply(): void {
    applying.value = true;
    router.post(
        RoomPlanController.copy.url(props.stayId),
        { from: from.value },
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Kopyalanamadı.'),
            onFinish: () => (applying.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Başka otelden kopyala</DialogTitle>
                <DialogDescription>
                    Örneğin Mekke'de aynı odada kalanlar, burada da aynı odaya
                    yerleşir. Sadece henüz odası olmayan yolcular taşınır; boş
                    odalar kullanılır.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="copy-from">Kopyalanacak otel</Label>
                <select id="copy-from" v-model="from" :class="selectClass">
                    <option
                        v-for="source in sources"
                        :key="source.id"
                        :value="source.id"
                    >
                        {{ source.label }}
                    </option>
                </select>
            </div>

            <p v-if="loading" class="text-sm text-muted-foreground">
                Plan hazırlanıyor…
            </p>
            <template v-else-if="preview">
                <p class="text-sm">
                    <strong>{{ preview.placed }}</strong> yolcu
                    {{ preview.placements.length }} odaya yerleşecek.
                </p>
                <ul
                    v-if="preview.placements.length"
                    class="max-h-64 divide-y overflow-y-auto rounded-md border text-sm"
                >
                    <li
                        v-for="item in preview.placements"
                        :key="item.room_no"
                        class="px-3 py-2"
                    >
                        <span class="font-medium">{{ item.room_no }}</span>
                        <span class="text-xs text-muted-foreground">
                            (önceki oda {{ item.from }})
                        </span>
                        — {{ item.names.join(', ') }}
                    </li>
                </ul>
                <div
                    v-if="preview.unplaced.length"
                    class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
                >
                    <p class="font-medium">
                        {{ preview.unplaced.length }} yolcu yerleşemeyecek:
                    </p>
                    <ul class="mt-1 list-disc pl-5">
                        <li v-for="u in preview.unplaced" :key="u.name">
                            {{ u.name }} — {{ u.reason }}
                        </li>
                    </ul>
                    <p class="mt-1">Oda ekleyip tekrar deneyebilirsiniz.</p>
                </div>
            </template>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">Vazgeç</Button>
                <Button
                    :disabled="!preview || preview.placed === 0 || applying"
                    @click="apply"
                >
                    Onayla ve kopyala
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
