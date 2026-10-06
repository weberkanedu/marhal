<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import TourProgramController from '@/actions/App/Http/Controllers/TourProgramController';
import type { ProgramItem } from '@/types/tour';

/**
 * Tur → "Program" sekmesi: gün gün etkinlikler (saat, etkinlik, yer). Aile ekranı ve "Tur programı"
 * çıktısı buradan okur. Saatler Mekke / Medine (Suudi Arabistan) saatidir.
 */
const props = defineProps<{
    tour: { id: string; start_date: string; end_date: string };
    items: ProgramItem[];
    canUpdate: boolean;
}>();

const days = computed(() => {
    const list: { date: string; label: string }[] = [];
    const start = new Date(`${props.tour.start_date}T00:00:00`);
    const end = new Date(`${props.tour.end_date}T00:00:00`);

    for (
        let d = new Date(start), n = 1;
        d <= end;
        d.setDate(d.getDate() + 1), n++
    ) {
        const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        list.push({
            date,
            label: `${n}. gün · ${d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short', weekday: 'short' })}`,
        });
    }

    return list;
});
const byDay = (date: string) => props.items.filter((i) => i.day === date);

// Ekle / düzelt formu
const editing = ref<ProgramItem | null>(null);
const form = ref({
    day: props.tour.start_date,
    time: '',
    title: '',
    place: '',
});

function edit(item: ProgramItem): void {
    editing.value = item;
    form.value = {
        day: item.day,
        time: item.time ?? '',
        title: item.title,
        place: item.place ?? '',
    };
}

function reset(): void {
    editing.value = null;
    form.value = { ...form.value, time: '', title: '', place: '' };
}

function save(): void {
    if (!form.value.title.trim()) {
        toast.error('Etkinliği yazın.');

        return;
    }

    const data = {
        ...form.value,
        time: form.value.time || null,
        place: form.value.place || null,
    };
    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['program', 'journey'],
        onSuccess: reset,
        onError: (e: Record<string, string>) =>
            toast.error(Object.values(e)[0] ?? 'Kaydedilemedi.'),
    };

    if (editing.value) {
        router.put(
            TourProgramController.update.url(editing.value.id),
            data,
            options,
        );
    } else {
        router.post(
            TourProgramController.store.url(props.tour.id),
            data,
            options,
        );
    }
}

function remove(item: ProgramItem): void {
    if (confirm(`"${item.title}" programdan silinsin mi?`)) {
        router.delete(TourProgramController.destroy.url(item.id), {
            preserveScroll: true,
            preserveState: true,
            only: ['program'],
        });
    }
}
</script>

<template>
    <div v-if="canUpdate" class="row">
        <select v-model="form.day" class="mini-in" aria-label="Gün">
            <option v-for="d in days" :key="d.date" :value="d.date">
                {{ d.label }}
            </option>
        </select>
        <input
            v-model="form.time"
            class="mini-in"
            type="time"
            aria-label="Saat"
            style="width: 100px"
        />
        <input
            v-model="form.title"
            class="mini-in"
            placeholder="Etkinlik (ör. Umre ibadeti, rehberle)"
            aria-label="Etkinlik"
            maxlength="150"
            style="flex: 1; min-width: 200px"
            @keydown.enter="save"
        />
        <input
            v-model="form.place"
            class="mini-in"
            placeholder="Yer (isteğe bağlı)"
            aria-label="Yer"
            maxlength="100"
            style="width: 180px"
            @keydown.enter="save"
        />
        <button class="btn sm" type="button" @click="save">
            {{ editing ? 'Kaydet' : 'Ekle' }}
        </button>
        <button
            v-if="editing"
            class="btn ghost sm"
            type="button"
            @click="reset"
        >
            Vazgeç
        </button>
    </div>
    <span class="lbl"
        >Saatler Mekke / Medine saatidir. Aile ekranında geçen etkinlikler soluk
        ve ✓ görünür.</span
    >

    <div class="tbl">
        <table>
            <thead>
                <tr>
                    <th>Gün</th>
                    <th class="num">Saat</th>
                    <th>Etkinlik</th>
                    <th>Yer</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <template v-for="d in days" :key="d.date">
                    <tr v-for="(item, i) in byDay(d.date)" :key="item.id">
                        <td>
                            <b v-if="i === 0">{{ d.label }}</b>
                        </td>
                        <td class="num">{{ item.time ?? '—' }}</td>
                        <td>{{ item.title }}</td>
                        <td>{{ item.place ?? '' }}</td>
                        <td class="acts-cell">
                            <span v-if="canUpdate" class="acts">
                                <button
                                    type="button"
                                    title="Düzelt"
                                    @click="edit(item)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    title="Sil"
                                    @click="remove(item)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </span>
                        </td>
                    </tr>
                </template>
                <tr v-if="!items.length" class="empty-row">
                    <td colspan="5">
                        Henüz program girilmedi. Yukarıdan gün, saat ve
                        etkinliği yazıp ekleyin.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
