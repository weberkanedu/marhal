<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import TourReadinessController from '@/actions/App/Http/Controllers/TourReadinessController';
import MockIcon from '@/components/mock/MockIcon.vue';
import type { ReadinessBoardData, TourGroup } from '@/types/tour';

/**
 * Tur → "Hazırlık" sekmesi — tasarım sayfasındaki "Hazırlık takibi" kartının birebir hâli (gerçek veriyle):
 * madde hapları (personel seçer), Nusuk anahtarı (Faz 4'e kadar kapalı), yolcu × madde tablosu
 * (✓ tamam, ! sorun, boş bekliyor; pasaport / fotoğraf kendiliğinden), Ravza randevu kutuları.
 * Hücreye tıklayınca boş → ✓ → ! → boş. Sütun başlığından sütunu toplu "✓" yapılır.
 */
const props = defineProps<{
    tourId: string;
    board: ReadinessBoardData | undefined;
    groups: TourGroup[];
    canUpdate: boolean;
}>();

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? 'İşlem yapılamadı.');
}

const options = {
    preserveScroll: true,
    preserveState: true,
    only: ['readinessBoard', 'readiness'],
    onError: showError,
};

// Madde hapları: takip edilenler açık; en az bir madde kalır.
const selected = computed(
    () => new Set(props.board?.items.map((i) => i.id) ?? []),
);

function toggleItem(id: string): void {
    if (!props.canUpdate || !props.board) {
        return;
    }

    const next = props.board.all_items
        .map((i) => i.id)
        .filter((x) =>
            x === id ? !selected.value.has(id) : selected.value.has(x),
        );

    if (next.length === 0) {
        toast.error('En az bir hazırlık maddesi kalmalı.');

        return;
    }

    router.put(
        TourReadinessController.items.url(props.tourId),
        { item_ids: next },
        options,
    );
}

// Süzgeç: arama, grup, yalnız eksikler.
const q = ref('');
const group = ref<string>('all');
const onlyMissing = ref(false);
const norm = (s: string) => s.toLocaleLowerCase('tr');
const rows = computed(() =>
    (props.board?.rows ?? []).filter(
        (r) =>
            norm(r.name).includes(norm(q.value)) &&
            (group.value === 'all' || r.group_id === group.value) &&
            (!onlyMissing.value || r.done < (props.board?.items.length ?? 0)),
    ),
);

const next = { null: 'tamam', tamam: 'sorun', sorun: null } as const;
type Status = 'tamam' | 'sorun' | null;

// Tıklanan hücre sunucu yanıtını beklemeden değişir; tablo yenilenince kaydedilenler düşer.
const pending = ref<Record<string, Status>>({});
const statusOf = (registrationId: string, itemId: string): Status => {
    const key = `${registrationId}|${itemId}`;

    return key in pending.value
        ? pending.value[key]
        : (props.board?.rows.find((r) => r.registration_id === registrationId)
              ?.cells[itemId]?.status ?? null);
};
watch(
    () => props.board,
    (board) => {
        for (const key of Object.keys(pending.value)) {
            const [reg, item] = key.split('|');
            const saved = board?.rows.find((r) => r.registration_id === reg)
                ?.cells[item]?.status;

            if (saved === pending.value[key]) {
                delete pending.value[key];
            }
        }
    },
);

function mark(registrationId: string, itemId: string): void {
    const key = `${registrationId}|${itemId}`;
    const status = next[statusOf(registrationId, itemId) ?? 'null'];
    pending.value = { ...pending.value, [key]: status };

    router.post(
        TourReadinessController.mark.url(props.tourId),
        { registration_id: registrationId, item_id: itemId, status },
        {
            ...options,
            // Hızlı art arda tıklamalar birbirini iptal etmesin.
            async: true,
            onError: (errors) => {
                const rest = { ...pending.value };
                delete rest[key];
                pending.value = rest;
                showError(errors);
            },
        },
    );
}

function markColumn(item: {
    id: string;
    name: string;
    automatic: boolean;
}): void {
    if (item.automatic) {
        return;
    }

    const scope =
        group.value === 'all'
            ? 'bütün yolcular'
            : (props.groups.find((g) => g.id === group.value)?.name ??
              'bu grup');

    if (confirm(`${item.name}: ${scope} için "tamam" işaretlensin mi?`)) {
        router.post(
            TourReadinessController.column.url(props.tourId),
            {
                item_id: item.id,
                group_id: group.value === 'all' ? null : group.value,
            },
            options,
        );
    }
}

const ini = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toLocaleUpperCase('tr');
const pct = (done: number, total: number) =>
    total ? Math.round((done / total) * 100) : 0;

// Ravza randevuları (personel düzenler).
const editingRavza = ref(false);
const men = ref('');
const women = ref('');
watch(
    () => props.board?.ravza,
    (r) => {
        men.value = r?.men.at ?? '';
        women.value = r?.women.at ?? '';
    },
    { immediate: true },
);

function saveRavza(): void {
    router.put(
        TourReadinessController.ravza.url(props.tourId),
        { men: men.value || null, women: women.value || null },
        { ...options, onSuccess: () => (editingRavza.value = false) },
    );
}

const when = (at: string | null) =>
    at
        ? new Date(at).toLocaleString('tr-TR', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : 'Randevu girilmedi';
</script>

<template>
    <p v-if="!board" class="lbl">Hazırlık tablosu yükleniyor…</p>
    <template v-else>
        <div v-if="canUpdate" class="fi">
            Kontrol maddeleri (acente seçer)
            <div class="itemchips">
                <span
                    v-for="item in board.all_items"
                    :key="item.id"
                    :class="{ on: selected.has(item.id) }"
                    role="button"
                    tabindex="0"
                    @click="toggleItem(item.id)"
                    >{{ item.name }}</span
                >
            </div>
        </div>
        <div class="swrow">
            <span
                class="sw"
                role="switch"
                aria-checked="false"
                aria-disabled="true"
                title="Faz 4'te acente ayarlarından açılacak"
            />Nusuk entegrasyonu <b>kapalı</b>: her şey elle işaretlenir ·
            pasaport ve fotoğraf yolcu bilgisinden kendiliğinden gelir
        </div>

        <div class="row">
            <div class="search">
                <MockIcon name="search" /><input
                    v-model="q"
                    placeholder="Yolcu ara"
                    aria-label="Yolcu ara"
                />
            </div>
            <div class="pills">
                <span
                    class="pill"
                    :class="{ on: group === 'all' }"
                    role="button"
                    tabindex="0"
                    @click="group = 'all'"
                    >Tümü</span
                >
                <span
                    v-for="g in groups"
                    :key="g.id"
                    class="pill"
                    :class="{ on: group === g.id }"
                    role="button"
                    tabindex="0"
                    @click="group = g.id"
                    >{{ g.name }}</span
                >
                <span
                    class="pill"
                    :class="{ on: onlyMissing }"
                    role="button"
                    tabindex="0"
                    @click="onlyMissing = !onlyMissing"
                    >Yalnız eksikler</span
                >
            </div>
            <span class="lbl"
                >{{ board.ready }} / {{ board.total }} yolcu hazır</span
            >
        </div>

        <div class="tbl mx">
            <table>
                <thead>
                    <tr>
                        <th>Yolcu</th>
                        <th v-for="item in board.items" :key="item.id">
                            <a
                                v-if="!item.automatic"
                                role="button"
                                :title="`${item.name}: herkesi ✓ işaretle`"
                                style="cursor: pointer"
                                @click="markColumn(item)"
                                >{{ item.name }}</a
                            >
                            <span
                                v-else
                                :title="`${item.name} yolcu bilgisinden kendiliğinden dolar`"
                                >{{ item.name }}</span
                            >
                        </th>
                        <th>Hazır</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.registration_id">
                        <td>
                            <div class="person">
                                <span
                                    class="av"
                                    :class="row.gender === 'kadin' ? 'k' : 'e'"
                                    >{{ ini(row.name) }}</span
                                >
                                <div>
                                    {{ row.name
                                    }}<small
                                        >{{
                                            row.gender === 'kadin'
                                                ? 'Kadın'
                                                : 'Erkek'
                                        }}<template v-if="row.age !== null">
                                            · {{ row.age }} yaş</template
                                        ><template v-if="row.group_name">
                                            · {{ row.group_name }}</template
                                        ></small
                                    >
                                </div>
                            </div>
                        </td>
                        <td v-for="item in board.items" :key="item.id">
                            <span
                                v-if="row.cells[item.id].auto"
                                class="c"
                                :class="
                                    row.cells[item.id].status === 'sorun'
                                        ? 'late'
                                        : row.cells[item.id].status
                                          ? 'auto'
                                          : ''
                                "
                                style="cursor: default"
                                :title="`${item.name} · ${row.cells[item.id].title ?? ''}`"
                                >{{
                                    row.cells[item.id].status === 'sorun'
                                        ? '!'
                                        : row.cells[item.id].status
                                          ? '✓'
                                          : ''
                                }}</span
                            >
                            <span
                                v-else
                                class="c"
                                :class="{
                                    on:
                                        statusOf(
                                            row.registration_id,
                                            item.id,
                                        ) === 'tamam',
                                    late:
                                        statusOf(
                                            row.registration_id,
                                            item.id,
                                        ) === 'sorun',
                                }"
                                role="button"
                                tabindex="0"
                                :title="
                                    [item.name, row.cells[item.id].title]
                                        .filter(Boolean)
                                        .join(' · ')
                                "
                                @click="mark(row.registration_id, item.id)"
                                >{{
                                    statusOf(row.registration_id, item.id) ===
                                    'tamam'
                                        ? '✓'
                                        : statusOf(
                                                row.registration_id,
                                                item.id,
                                            ) === 'sorun'
                                          ? '!'
                                          : ''
                                }}</span
                            >
                        </td>
                        <td>
                            <div class="bar" style="width: 56px">
                                <i
                                    :class="{
                                        full: row.done === board.items.length,
                                    }"
                                    :style="{
                                        width: `${pct(row.done, board.items.length)}%`,
                                    }"
                                />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.length" class="empty-row">
                        <td :colspan="board.items.length + 2">
                            {{
                                onlyMissing
                                    ? 'Eksiği olan yolcu yok ✓'
                                    : 'Bu listede yolcu yok'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template v-if="board.ravza">
            <div class="slots2">
                <div
                    :role="canUpdate ? 'button' : undefined"
                    :style="canUpdate ? 'cursor: pointer' : undefined"
                    @click="canUpdate && (editingRavza = true)"
                >
                    <b>Ravza · Erkekler</b
                    ><small
                        >{{ when(board.ravza.men.at) }} ·
                        {{ board.ravza.men.done }} kişi<template
                            v-if="board.ravza.men.waiting"
                        >
                            · {{ board.ravza.men.waiting }} kişi
                            bekliyor</template
                        ></small
                    >
                </div>
                <div
                    :role="canUpdate ? 'button' : undefined"
                    :style="canUpdate ? 'cursor: pointer' : undefined"
                    @click="canUpdate && (editingRavza = true)"
                >
                    <b>Ravza · Kadınlar</b
                    ><small
                        >{{ when(board.ravza.women.at) }} ·
                        {{ board.ravza.women.done }} kişi<template
                            v-if="board.ravza.women.waiting"
                        >
                            · {{ board.ravza.women.waiting }} kişi
                            bekliyor</template
                        ></small
                    >
                </div>
            </div>
            <div v-if="editingRavza" class="row">
                <label class="fi"
                    >Erkekler randevusu<input
                        v-model="men"
                        class="mini-in"
                        type="datetime-local"
                /></label>
                <label class="fi"
                    >Kadınlar randevusu<input
                        v-model="women"
                        class="mini-in"
                        type="datetime-local"
                /></label>
                <button class="btn sm" type="button" @click="saveRavza">
                    Kaydet
                </button>
                <button
                    class="btn ghost sm"
                    type="button"
                    @click="editingRavza = false"
                >
                    Vazgeç
                </button>
            </div>
        </template>
    </template>
</template>
