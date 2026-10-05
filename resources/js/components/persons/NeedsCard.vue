<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { HeartPulse, Lock, ShieldCheck } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PersonNeedController from '@/actions/App/Http/Controllers/PersonNeedController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';

/**
 * Yolcunun ihtiyaç profili (sağlık verisi). Önce ayrı açık rıza işaretlenir; rıza geri alınınca
 * ihtiyaçlar silinir. İhtiyaçlar oda / koltuk / uçak kurallarında ve listelerde kullanılır.
 */
export type PersonNeeds = {
    consent_at: string | null;
    items: { type_id: string; note: string | null }[];
    types: {
        id: string;
        name: string;
        category: string;
        category_label: string;
    }[];
};

const props = defineProps<{
    personId: string;
    needs: PersonNeeds;
    canUpdate: boolean;
}>();

const editing = ref(false);
const saving = ref(false);
// type_id → not (seçili olanlar)
const draft = reactive<Record<string, string>>({});

function reset(): void {
    Object.keys(draft).forEach((k) => delete draft[k]);
    props.needs.items.forEach((i) => (draft[i.type_id] = i.note ?? ''));
}

watch(() => props.needs.items, reset, { immediate: true });

const names = computed(
    () => new Map(props.needs.types.map((t) => [t.id, t.name])),
);
const categories = computed(() => {
    const groups = new Map<
        string,
        { label: string; types: PersonNeeds['types'] }
    >();

    for (const type of props.needs.types) {
        const group = groups.get(type.category) ?? {
            label: type.category_label,
            types: [],
        };
        group.types.push(type);
        groups.set(type.category, group);
    }

    return [...groups.values()];
});

function toggle(id: string): void {
    if (id in draft) {
        delete draft[id];
    } else {
        draft[id] = '';
    }
}

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'Kaydedilemedi.');
}

function save(): void {
    saving.value = true;
    router.put(
        PersonNeedController.update.url(props.personId),
        {
            items: Object.entries(draft).map(([type_id, note]) => ({
                type_id,
                note: note || null,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = false),
            onError: showError,
            onFinish: () => (saving.value = false),
        },
    );
}

function setConsent(granted: boolean): void {
    if (
        !granted &&
        !confirm(
            'Açık rıza geri alınsın mı? Bu yolcunun bütün ihtiyaç bilgileri silinir.',
        )
    ) {
        return;
    }

    router.put(
        PersonNeedController.consent.url(props.personId),
        { granted },
        { preserveScroll: true, onError: showError },
    );
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-3">
            <div>
                <CardTitle class="flex items-center gap-2">
                    <HeartPulse class="size-4" /> İhtiyaçlar
                </CardTitle>
                <CardDescription class="flex items-center gap-1">
                    <Lock class="size-3" /> Sağlık verisi: şifreli saklanır,
                    rehber yalnız adını görür.
                </CardDescription>
            </div>
            <Button
                v-if="canUpdate && needs.consent_at && !editing"
                size="sm"
                variant="outline"
                @click="editing = true"
            >
                Düzenle
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-3 text-sm">
            <!-- Açık rıza -->
            <div
                v-if="!needs.consent_at"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed p-3"
            >
                <span class="text-muted-foreground">
                    Sağlık / ihtiyaç bilgisi için yolcudan ayrı açık rıza
                    alınmadan bilgi girilemez.
                </span>
                <Button v-if="canUpdate" size="sm" @click="setConsent(true)">
                    <ShieldCheck /> Açık rıza alındı
                </Button>
            </div>

            <template v-else>
                <p
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <ShieldCheck class="size-3.5 text-success" />
                    Açık rıza: {{ formatDate(needs.consent_at) }}
                    <button
                        v-if="canUpdate"
                        type="button"
                        class="underline hover:text-destructive"
                        @click="setConsent(false)"
                    >
                        Rızayı geri al
                    </button>
                </p>

                <!-- Görüntüleme -->
                <template v-if="!editing">
                    <p
                        v-if="needs.items.length === 0"
                        class="text-muted-foreground"
                    >
                        Özel ihtiyaç girilmedi.
                    </p>
                    <ul v-else class="flex flex-col gap-1.5">
                        <li
                            v-for="item in needs.items"
                            :key="item.type_id"
                            class="flex flex-wrap items-baseline gap-2"
                        >
                            <span
                                class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground"
                            >
                                {{ names.get(item.type_id) ?? '—' }}
                            </span>
                            <span
                                v-if="item.note"
                                class="text-muted-foreground"
                            >
                                {{ item.note }}
                            </span>
                        </li>
                    </ul>
                </template>

                <!-- Düzenleme -->
                <template v-else>
                    <div
                        v-for="group in categories"
                        :key="group.label"
                        class="flex flex-col gap-1.5"
                    >
                        <small
                            class="text-[10.5px] tracking-wider text-muted-foreground uppercase"
                            >{{ group.label }}</small
                        >
                        <div
                            v-for="type in group.types"
                            :key="type.id"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <label class="flex min-w-48 items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="size-4 accent-(--primary)"
                                    :checked="type.id in draft"
                                    @change="toggle(type.id)"
                                />
                                {{ type.name }}
                            </label>
                            <Input
                                v-if="type.id in draft"
                                v-model="draft[type.id]"
                                class="h-8 max-w-sm flex-1"
                                placeholder="Not (isteğe bağlı)"
                                maxlength="500"
                            />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" :disabled="saving" @click="save">
                            Kaydet
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="(reset(), (editing = false))"
                        >
                            Vazgeç
                        </Button>
                    </div>
                </template>
            </template>
        </CardContent>
    </Card>
</template>
